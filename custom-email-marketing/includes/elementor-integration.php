<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capture newsletter popup submissions from Elementor.
 */
add_action(
    'elementor_pro/forms/new_record',
    'cem_capture_elementor_newsletter',
    10,
    2
);

function cem_capture_elementor_newsletter($record, $handler) {

    global $wpdb;

    /*
     * Make sure this is an Elementor form record.
     */
    if (!$record) {
        return;
    }

    /*
     * Get the Elementor Form ID.
     */
    $form_id = $record->get_form_settings('form_id');

    /*
     * Only process our newsletter popup.
     */
    if ($form_id !== 'newsletter_popup') {
        return;
    }

    /*
     * Get submitted fields.
     */
    $fields = $record->get('fields');

    if (!is_array($fields)) {
        return;
    }

    /*
     * Make sure the email field exists.
     */
    if (
        !isset($fields['email']) ||
        empty($fields['email']['value'])
    ) {
        return;
    }

    /*
     * Get email.
     */
    $email = sanitize_email($fields['email']['value']);

    /*
     * Validate email.
     */
    if (!is_email($email)) {
        return;
    }

    /*
     * Normalize email.
     */
    $email = strtolower(trim($email));

    /*
     * Tables.
     */
    $contacts_table      = $wpdb->prefix . 'em_contacts';
    $lists_table         = $wpdb->prefix . 'em_lists';
    $contact_lists_table = $wpdb->prefix . 'em_contact_lists';

    /*
     * Current time.
     */
    $now = current_time('mysql');

    /*
     * Get visitor IP.
     */
    $ip = '';

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field(
            wp_unslash($_SERVER['REMOTE_ADDR'])
        );
    }

    /*
     * Check whether contact already exists.
     */
    $is_new_contact = false;

    $contact_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
             FROM $contacts_table
             WHERE email = %s
             LIMIT 1",
            $email
        )
    );

    /*
     * Create contact if it doesn't exist.
     */
    if (!$contact_id) {

        $inserted = $wpdb->insert(
            $contacts_table,
            array(
                'email'              => $email,
                'first_name'         => '',
                'last_name'          => '',
                'phone'              => '',
                'status'             => 'active',
                'source'             => 'Elementor Popup',
                'marketing_consent'  => 1,
                'consent_at'         => $now,
                'consent_ip'         => $ip,
                'created_at'         => $now,
                'updated_at'         => $now,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if (!$inserted) {
            return;
        }

        $contact_id = $wpdb->insert_id;
        $is_new_contact = true;

    } else {

    /*
     * Check current contact status.
     */
    $contact_status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT status
             FROM $contacts_table
             WHERE id = %d
             LIMIT 1",
            $contact_id
        )
    );

    /*
     * If the contact previously unsubscribed,
     * do not automatically subscribe them again.
     */
    if ($contact_status === 'unsubscribed') {
        return;
    }

    /*
     * Existing active contact.
     *
     * Refresh consent information.
     */
    $wpdb->update(
        $contacts_table,
        array(
            'status'            => 'active',
            'marketing_consent' => 1,
            'consent_at'        => $now,
            'consent_ip'        => $ip,
            'updated_at'        => $now,
        ),
        array(
            'id' => $contact_id,
        ),
        array(
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
        ),
        array(
            '%d',
        )
    );
}

    /*
     * Find Newsletter list.
     */
    $list_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
             FROM $lists_table
             WHERE name = %s
             LIMIT 1",
            'Newsletter'
        )
    );

    /*
     * Create Newsletter list if missing.
     */
    if (!$list_id) {

        $wpdb->insert(
            $lists_table,
            array(
                'name'        => 'Newsletter',
                'description' => 'Main newsletter subscribers',
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        $list_id = $wpdb->insert_id;
    }

    if (!$list_id) {
        return;
    }

    /*
     * Check contact/list relationship.
     */
    $relationship_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
             FROM $contact_lists_table
             WHERE contact_id = %d
             AND list_id = %d
             LIMIT 1",
            $contact_id,
            $list_id
        )
    );

    /*
     * Create relationship if missing.
     */
    if (!$relationship_id) {

        $wpdb->insert(
            $contact_lists_table,
            array(
                'contact_id'     => $contact_id,
                'list_id'        => $list_id,
                'status'         => 'subscribed',
                'subscribed_at'  => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

    } else {

        /*
         * Re-subscribe existing relationship.
         */
        $wpdb->update(
            $contact_lists_table,
            array(
                'status'          => 'subscribed',
                'subscribed_at'   => $now,
                'unsubscribed_at' => null,
                'updated_at'      => $now,
            ),
            array(
                'id' => $relationship_id,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
            ),
            array(
                '%d',
            )
        );
    }

    /*
     * Queue a welcome email only for a newly created contact.
     * Campaign ID 0 identifies welcome emails in the shared queue table.
     */
    if ($is_new_contact && function_exists('cem_queue_contact_welcome_campaign')) {
        cem_queue_contact_welcome_campaign($contact_id, $email, '');
    }

}

/**
 * Queue the active welcome campaign for one newly-created contact.
 */
function cem_queue_contact_welcome_campaign($contact_id, $email, $name = '') {
    global $wpdb;

    $campaigns_table   = $wpdb->prefix . 'em_campaigns';
    $recipients_table  = $wpdb->prefix . 'em_campaign_recipients';
    $queue_table       = $wpdb->prefix . 'em_email_queue';

    $campaign = $wpdb->get_row(
        "SELECT * FROM $campaigns_table
         WHERE campaign_type = 'welcome'
         AND status = 'active'
         ORDER BY id ASC LIMIT 1"
    );

    if (!$campaign) {
        return false;
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $recipients_table
         WHERE campaign_id = %d AND contact_id = %d LIMIT 1",
        $campaign->id, $contact_id
    ));

    if ($existing) {
        return false;
    }

    $now = current_time('mysql');
    $inserted = $wpdb->insert($recipients_table, array(
        'campaign_id' => (int) $campaign->id,
        'contact_id'  => (int) $contact_id,
        'status'      => 'pending',
        'created_at'  => $now,
        'updated_at'  => $now,
    ), array('%d','%d','%s','%s','%s'));

    if (!$inserted) {
        return false;
    }

    $recipient_id = $wpdb->insert_id;
    return (bool) $wpdb->insert($queue_table, array(
        'campaign_id'           => (int) $campaign->id,
        'campaign_recipient_id' => (int) $recipient_id,
        'contact_id'            => (int) $contact_id,
        'to_email'              => $email,
        'to_name'               => $name,
        'subject'               => $campaign->subject,
        'from_email'            => $campaign->from_email,
        'from_name'             => $campaign->from_name,
        'reply_to'              => $campaign->reply_to,
        'html_content'          => !empty($campaign->html_content) ? $campaign->html_content : (isset($campaign->content) ? $campaign->content : ''),
        'plain_content'         => !empty($campaign->plain_content) ? $campaign->plain_content : wp_strip_all_tags(!empty($campaign->html_content) ? $campaign->html_content : (isset($campaign->content) ? $campaign->content : '')),
        'status'                => 'pending',
        'attempts'              => 0,
        'last_error'            => '',
        'queued_at'             => $now,
        'created_at'            => $now,
        'updated_at'            => $now,
    ), array('%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s'));
}
