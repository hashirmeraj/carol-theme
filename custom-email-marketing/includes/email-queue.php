<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue settings.
 */
define('CEM_QUEUE_BATCH_SIZE', 10);
define('CEM_QUEUE_MAX_ATTEMPTS', 3);

/**
 * Register the background queue event.
 */
add_action('init', 'cem_register_queue_event');

function cem_register_queue_event() {

    if (!wp_next_scheduled('cem_process_email_queue_event')) {
        wp_schedule_event(
            time() + 60,
            'minute',
            'cem_process_email_queue_event'
        );
    }
}

/**
 * Add a one-minute cron interval.
 */
add_filter('cron_schedules', 'cem_add_minute_cron_interval');

function cem_add_minute_cron_interval($schedules) {

    if (!isset($schedules['minute'])) {
        $schedules['minute'] = array(
            'interval' => 60,
            'display'  => 'Every Minute',
        );
    }

    return $schedules;
}

/**
 * Process the email queue.
 */
add_action(
    'cem_process_email_queue_event',
    'cem_process_email_queue'
);

function cem_process_email_queue() {

    global $wpdb;

    $queue_table      = $wpdb->prefix . 'em_email_queue';
    $recipient_table  = $wpdb->prefix . 'em_campaign_recipients';
    $campaigns_table  = $wpdb->prefix . 'em_campaigns';

    $queue_items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM $queue_table
             WHERE status = %s
             AND campaign_type <> 'welcome'
             AND attempts < %d
             ORDER BY id ASC
             LIMIT %d",
            'pending',
            CEM_QUEUE_MAX_ATTEMPTS,
            CEM_QUEUE_BATCH_SIZE
        )
    );

    if (empty($queue_items)) {
        cem_mark_completed_campaigns();
        return;
    }

    foreach ($queue_items as $queue_item) {

        $claimed = $wpdb->update(
            $queue_table,
            array(
                'status'     => 'processing',
                'attempts'   => ((int) $queue_item->attempts) + 1,
                'updated_at' => current_time('mysql'),
            ),
            array(
                'id'     => $queue_item->id,
                'status' => 'pending',
            ),
            array(
                '%s',
                '%d',
                '%s',
            ),
            array(
                '%d',
                '%s',
            )
        );

        if (!$claimed) {
            continue;
        }

        $result = cem_send_email_via_sendlayer(
            $queue_item->to_email,
            $queue_item->to_name,
            $queue_item->subject,
            $queue_item->html_content,
            $queue_item->plain_content,
            $queue_item->from_email,
            $queue_item->from_name,
            $queue_item->reply_to
        );

        if (!empty($result['success'])) {

            if ((int) $queue_item->campaign_id === 0) {
                $wpdb->update(
                    $queue_table,
                    array(
                        'status'     => 'sent',
                        'sent_at'    => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                        'last_error' => '',
                    ),
                    array('id' => $queue_item->id),
                    array('%s', '%s', '%s', '%s'),
                    array('%d')
                );
                continue;
            }

            $wpdb->update(
                $queue_table,
                array(
                    'status'     => 'sent',
                    'sent_at'    => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'last_error' => '',
                ),
                array(
                    'id' => $queue_item->id,
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

            $wpdb->update(
                $recipient_table,
                array(
                    'status'     => 'sent',
                    'sent_at'    => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'error_message' => '',
                ),
                array(
                    'id' => $queue_item->campaign_recipient_id,
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

        } else {

            $error_message = !empty($result['message'])
                ? $result['message']
                : 'Unknown SendLayer error.';

            $attempts = ((int) $queue_item->attempts) + 1;

            if ($attempts >= CEM_QUEUE_MAX_ATTEMPTS) {
                $queue_status     = 'failed';
                $recipient_status = 'failed';
                $failed_at        = current_time('mysql');
            } else {
                $queue_status     = 'pending';
                $recipient_status = 'pending';
                $failed_at        = null;
            }

            $wpdb->update(
                $queue_table,
                array(
                    'status'     => $queue_status,
                    'last_error' => $error_message,
                    'updated_at' => current_time('mysql'),
                ),
                array(
                    'id' => $queue_item->id,
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                ),
                array(
                    '%d',
                )
            );

            if ((int) $queue_item->campaign_id === 0) {
                $wpdb->update(
                    $queue_table,
                    array(
                        'status'     => $queue_status,
                        'last_error' => $error_message,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $queue_item->id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                continue;
            }

            $recipient_update = array(
                'status'        => $recipient_status,
                'error_message' => $error_message,
                'updated_at'    => current_time('mysql'),
            );

            $recipient_formats = array(
                '%s',
                '%s',
                '%s',
            );

            if ($failed_at) {
                $recipient_update['failed_at'] = $failed_at;
                $recipient_formats[] = '%s';
            }

            $wpdb->update(
                $recipient_table,
                $recipient_update,
                array(
                    'id' => $queue_item->campaign_recipient_id,
                ),
                $recipient_formats,
                array(
                    '%d',
                )
            );
        }
    }

    cem_mark_completed_campaigns();
}

/**
 * Queue a welcome email for a newly created contact.
 */
function cem_queue_welcome_email($contact_id, $email, $name = '') {

    global $wpdb;

    $queue_table = $wpdb->prefix . 'em_email_queue';

    $already_queued = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
             FROM $queue_table
             WHERE campaign_id = 0
             AND contact_id = %d
             LIMIT 1",
            $contact_id
        )
    );

    if ($already_queued) {
        return false;
    }

    $from_email = get_option('cem_sendlayer_from_email', '');
    $from_name  = get_option('cem_sendlayer_from_name', '');

    $subject = get_option(
        'cem_welcome_email_subject',
        'Welcome to our newsletter!'
    );

    $html_content = get_option(
        'cem_welcome_email_html',
        '<html><body><h2>Welcome!</h2><p>Thank you for subscribing to our newsletter.</p></body></html>'
    );

    $plain_content = wp_strip_all_tags($html_content);
    $now = current_time('mysql');

    return (bool) $wpdb->insert(
        $queue_table,
        array(
            'campaign_id'           => 0,
            'campaign_recipient_id' => 0,
            'contact_id'            => $contact_id,
            'to_email'              => $email,
            'to_name'               => $name,
            'subject'               => $subject,
            'from_email'            => $from_email,
            'from_name'             => $from_name,
            'reply_to'              => $from_email,
            'html_content'          => $html_content,
            'plain_content'         => $plain_content,
            'status'                => 'pending',
            'attempts'              => 0,
            'last_error'            => '',
            'queued_at'             => $now,
            'created_at'            => $now,
            'updated_at'            => $now,
        ),
        array(
            '%d','%d','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%d','%s','%s','%s','%s'
        )
    );
}

/**
 * Mark campaigns as completed when no queue items remain.
 */
function cem_mark_completed_campaigns() {

    global $wpdb;

    $campaigns_table = $wpdb->prefix . 'em_campaigns';
    $queue_table     = $wpdb->prefix . 'em_email_queue';

    $campaign_ids = $wpdb->get_col(
        "SELECT DISTINCT campaign_id
         FROM $queue_table
         WHERE campaign_id > 0
         AND status IN ('pending', 'processing')"
    );

    $campaign_ids = array_map('absint', $campaign_ids);

    $sending_campaigns = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id
             FROM $campaigns_table
             WHERE status = %s",
            'sending'
        )
    );

    foreach ($sending_campaigns as $campaign) {

        if (in_array((int) $campaign->id, $campaign_ids, true)) {
            continue;
        }

        $failed_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM $queue_table
                 WHERE campaign_id = %d
                 AND status = %s",
                $campaign->id,
                'failed'
            )
        );

        $final_status = $failed_count > 0
            ? 'completed_with_errors'
            : 'completed';

        $wpdb->update(
            $campaigns_table,
            array(
                'status'       => $final_status,
                'completed_at' => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ),
            array(
                'id' => $campaign->id,
            ),
            array(
                '%s',
                '%s',
                '%s',
            ),
            array(
                '%d',
            )
        );
    }
}