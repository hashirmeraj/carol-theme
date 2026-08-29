<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register Campaigns submenu.
 */
add_action('admin_menu', 'cem_register_campaigns_page', 20);

function cem_register_campaigns_page() {

    add_submenu_page(
        'custom-email-marketing',
        'Campaigns',
        'Campaigns',
        'manage_options',
        'cem-campaigns',
        'cem_render_campaigns_page'
    );
}


/**
 * Handle campaign actions.
 */
add_action('admin_init', 'cem_handle_campaign_actions');

function cem_handle_campaign_actions() {

    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (
        !isset($_GET['page']) ||
        $_GET['page'] !== 'cem-campaigns'
    ) {
        return;
    }

    global $wpdb;

    $campaigns_table =
        $wpdb->prefix . 'em_campaigns';


    /*
     * CREATE CAMPAIGN
     */
    if (
        isset($_POST['cem_create_campaign']) &&
        check_admin_referer(
            'cem_create_campaign_action',
            'cem_create_campaign_nonce'
        )
    ) {

        $name = isset($_POST['campaign_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['campaign_name'])
            )
            : '';

        $subject = isset($_POST['subject'])
            ? sanitize_text_field(
                wp_unslash($_POST['subject'])
            )
            : '';

        $from_name = isset($_POST['from_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['from_name'])
            )
            : '';

        $from_email = isset($_POST['from_email'])
            ? sanitize_email(
                wp_unslash($_POST['from_email'])
            )
            : '';

        $reply_to = isset($_POST['reply_to'])
            ? sanitize_email(
                wp_unslash($_POST['reply_to'])
            )
            : '';

        $campaign_type = isset($_POST['campaign_type'])
            ? sanitize_key(
                wp_unslash($_POST['campaign_type'])
            )
            : 'newsletter';

        $list_id = isset($_POST['list_id'])
            ? absint($_POST['list_id'])
            : 0;


        /*
         * Validation.
         */
        if (empty($name)) {

            add_settings_error(
                'cem_campaigns',
                'campaign_name',
                'Campaign name is required.',
                'error'
            );

        } elseif (empty($subject)) {

            add_settings_error(
                'cem_campaigns',
                'campaign_subject',
                'Email subject is required.',
                'error'
            );

        } elseif (
            empty($from_email) ||
            !is_email($from_email)
        ) {

            add_settings_error(
                'cem_campaigns',
                'from_email',
                'A valid From Email is required.',
                'error'
            );

        } elseif (!$list_id) {

            add_settings_error(
                'cem_campaigns',
                'campaign_list',
                'Please select a mailing list.',
                'error'
            );

        } else {

            /*
             * Make sure selected list exists and is active.
             */
            $lists_table =
                $wpdb->prefix . 'em_lists';

            $valid_list = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM $lists_table
                    WHERE id = %d
                    AND status = 'active'
                    LIMIT 1
                    ",
                    $list_id
                )
            );

            if (!$valid_list) {

                add_settings_error(
                    'cem_campaigns',
                    'invalid_campaign_list',
                    'The selected mailing list is not valid.',
                    'error'
                );

            } else {

                $now = current_time('mysql');

                $inserted = $wpdb->insert(
                    $campaigns_table,
                    array(
                        'name'          => $name,
                        'subject'       => $subject,
                        'from_name'     => $from_name,
                        'from_email'    => $from_email,
                        'reply_to'      => $reply_to,
                        'html_content'  => '',
                        'plain_content' => '',
                        'status'        => 'draft',
                        'campaign_type' => $campaign_type,
                        'list_id'       => $list_id,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ),
                    array(
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%s',
                        '%s',
                    )
                );

                if ($inserted) {

                    $campaign_id = $wpdb->insert_id;

                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'        => 'cem-campaigns',
                                'campaign_id' => $campaign_id,
                                'updated'     => 'created',
                            ),
                            admin_url('admin.php')
                        )
                    );

                    exit;

                } else {

                    add_settings_error(
                        'cem_campaigns',
                        'create_failed',
                        'Campaign could not be created.',
                        'error'
                    );
                }
            }
        }
    }


    /*
     * UPDATE CAMPAIGN
     */
    if (
        isset($_POST['cem_update_campaign']) &&
        check_admin_referer(
            'cem_update_campaign_action',
            'cem_update_campaign_nonce'
        )
    ) {

        $campaign_id = isset($_POST['campaign_id'])
            ? absint($_POST['campaign_id'])
            : 0;

        $name = isset($_POST['campaign_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['campaign_name'])
            )
            : '';

        $subject = isset($_POST['subject'])
            ? sanitize_text_field(
                wp_unslash($_POST['subject'])
            )
            : '';

        $from_name = isset($_POST['from_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['from_name'])
            )
            : '';

        $from_email = isset($_POST['from_email'])
            ? sanitize_email(
                wp_unslash($_POST['from_email'])
            )
            : '';

        $reply_to = isset($_POST['reply_to'])
            ? sanitize_email(
                wp_unslash($_POST['reply_to'])
            )
            : '';
        $html_content = isset($_POST['html_content'])
    ? wp_kses_post(
        wp_unslash($_POST['html_content'])
    )
    : '';    

        $campaign_type = isset($_POST['campaign_type'])
            ? sanitize_key(
                wp_unslash($_POST['campaign_type'])
            )
            : 'newsletter';

        $list_id = isset($_POST['list_id'])
            ? absint($_POST['list_id'])
            : 0;


        /*
         * Validation.
         */
        if (!$campaign_id) {

            add_settings_error(
                'cem_campaigns',
                'invalid_campaign',
                'Invalid campaign.',
                'error'
            );

        } elseif (empty($name)) {

            add_settings_error(
                'cem_campaigns',
                'campaign_name',
                'Campaign name is required.',
                'error'
            );

        } elseif (empty($subject)) {

            add_settings_error(
                'cem_campaigns',
                'campaign_subject',
                'Email subject is required.',
                'error'
            );

        } elseif (
            empty($from_email) ||
            !is_email($from_email)
        ) {

            add_settings_error(
                'cem_campaigns',
                'from_email',
                'A valid From Email is required.',
                'error'
            );

        } elseif (!$list_id) {

            add_settings_error(
                'cem_campaigns',
                'campaign_list',
                'Please select a mailing list.',
                'error'
            );

        } else {

            /*
             * Make sure selected list exists and is active.
             */
            $lists_table =
                $wpdb->prefix . 'em_lists';

            $valid_list = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM $lists_table
                    WHERE id = %d
                    AND status = 'active'
                    LIMIT 1
                    ",
                    $list_id
                )
            );

            if (!$valid_list) {

                add_settings_error(
                    'cem_campaigns',
                    'invalid_campaign_list',
                    'The selected mailing list is not valid.',
                    'error'
                );

            } else {

                $updated = $wpdb->update(
                    $campaigns_table,
                    array(
    'name'          => $name,
    'subject'       => $subject,
    'from_name'     => $from_name,
    'from_email'    => $from_email,
    'reply_to'      => $reply_to,
    'campaign_type' => $campaign_type,
    'list_id'       => $list_id,
    'html_content'  => $html_content,
    'updated_at'    => current_time('mysql'),
),
                    array(
                        'id' => $campaign_id,
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
),
                    array(
                        '%d',
                    )
                );

                if ($updated !== false) {

                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'        => 'cem-campaigns',
                                'campaign_id' => $campaign_id,
                                'updated'     => 'updated',
                            ),
                            admin_url('admin.php')
                        )
                    );

                    exit;

                } else {

                    add_settings_error(
                        'cem_campaigns',
                        'update_failed',
                        'Campaign could not be updated.',
                        'error'
                    );
                }
            }
        }
    }


    /*
 * PREPARE CAMPAIGN RECIPIENTS
 */
if (
    isset($_POST['cem_prepare_recipients']) &&
    check_admin_referer(
        'cem_prepare_recipients_action',
        'cem_prepare_recipients_nonce'
    )
) {

    $campaign_id = isset($_POST['campaign_id'])
        ? absint($_POST['campaign_id'])
        : 0;

    if (!$campaign_id) {

        add_settings_error(
            'cem_campaigns',
            'invalid_campaign',
            'Invalid campaign.',
            'error'
        );

        return;
    }


    /*
     * Get campaign.
     */
    $campaign = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $campaigns_table
            WHERE id = %d
            LIMIT 1
            ",
            $campaign_id
        )
    );


    if (!$campaign) {

        add_settings_error(
            'cem_campaigns',
            'campaign_not_found',
            'Campaign not found.',
            'error'
        );

        return;
    }


    /*
     * Campaign must have a list.
     */
    if (empty($campaign->list_id)) {

        add_settings_error(
            'cem_campaigns',
            'campaign_list_required',
            'Please select a mailing list first.',
            'error'
        );

        return;
    }


    /*
     * Only prepare draft campaigns.
     */
    if ($campaign->status !== 'draft') {

        add_settings_error(
            'cem_campaigns',
            'invalid_campaign_status',
            'Recipients can only be prepared for a draft campaign.',
            'error'
        );

        return;
    }


    $contact_lists_table =
        $wpdb->prefix . 'em_contact_lists';

    $contacts_table =
        $wpdb->prefix . 'em_contacts';

    $recipients_table =
        $wpdb->prefix . 'em_campaign_recipients';


    /*
     * Get eligible contacts.
     */
    $contacts = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT DISTINCT
                c.id
            FROM $contact_lists_table cl
            INNER JOIN $contacts_table c
                ON c.id = cl.contact_id
            WHERE cl.list_id = %d
            AND cl.status = 'subscribed'
            AND c.status = 'active'
            ORDER BY c.id ASC
            ",
            $campaign->list_id
        )
    );


    if (empty($contacts)) {

        add_settings_error(
            'cem_campaigns',
            'no_recipients',
            'No eligible subscribers were found in this mailing list.',
            'warning'
        );

        return;
    }


    /*
     * Insert recipients.
     *
     * INSERT IGNORE protects against duplicates because
     * campaign_id + contact_id is UNIQUE.
     */
    $now = current_time('mysql');

    $inserted_count = 0;

    foreach ($contacts as $contact) {

        $result = $wpdb->query(
            $wpdb->prepare(
                "
                INSERT IGNORE INTO $recipients_table
                (
                    campaign_id,
                    contact_id,
                    status,
                    queued_at,
                    sent_at,
                    failed_at,
                    error_message,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    %d,
                    %d,
                    'pending',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    %s,
                    %s
                )
                ",
                $campaign_id,
                $contact->id,
                $now,
                $now
            )
        );


        if ($result === 1) {
            $inserted_count++;
        }
    }


    /*
     * Redirect back to campaign.
     */
    wp_safe_redirect(
        add_query_arg(
            array(
                'page'        => 'cem-campaigns',
                'campaign_id' => $campaign_id,
                'updated'     => 'recipients_prepared',
                'prepared'    => $inserted_count,
            ),
            admin_url('admin.php')
        )
    );

    exit;
}

    /*
     * DELETE CAMPAIGN
     */
    if (
        isset($_GET['action']) &&
        $_GET['action'] === 'delete'
    ) {

        $campaign_id = isset($_GET['campaign_id'])
            ? absint($_GET['campaign_id'])
            : 0;

        if (!$campaign_id) {
            return;
        }

        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(
                wp_unslash($_GET['_wpnonce'])
            )
            : '';

        if (
            !wp_verify_nonce(
                $nonce,
                'cem_delete_campaign_' . $campaign_id
            )
        ) {

            wp_die('Security check failed.');
        }


        /*
         * Get campaign.
         */
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM $campaigns_table
                WHERE id = %d
                LIMIT 1
                ",
                $campaign_id
            )
        );


        if (!$campaign) {

            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'    => 'cem-campaigns',
                        'updated' => 'not_found',
                    ),
                    admin_url('admin.php')
                )
            );

            exit;
        }


        /*
         * Do not delete a sending campaign.
         */
        if ($campaign->status === 'sending') {

            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'    => 'cem-campaigns',
                        'updated' => 'cannot_delete',
                    ),
                    admin_url('admin.php')
                )
            );

            exit;
        }


        /*
         * Delete campaign recipients.
         */
        $recipients_table =
            $wpdb->prefix . 'em_campaign_recipients';

        $wpdb->delete(
            $recipients_table,
            array(
                'campaign_id' => $campaign_id,
            ),
            array(
                '%d',
            )
        );


        /*
         * Delete email queue records.
         */
        $queue_table =
            $wpdb->prefix . 'em_email_queue';

        $wpdb->delete(
            $queue_table,
            array(
                'campaign_id' => $campaign_id,
            ),
            array(
                '%d',
            )
        );


        /*
         * Delete campaign.
         */
        $wpdb->delete(
            $campaigns_table,
            array(
                'id' => $campaign_id,
            ),
            array(
                '%d',
            )
        );


        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cem-campaigns',
                    'updated' => 'deleted',
                ),
                admin_url('admin.php')
            )
        );

        exit;
    }
}


/**
 * Render Campaigns page.
 */
function cem_render_campaigns_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $campaigns_table =
        $wpdb->prefix . 'em_campaigns';


    /*
     * Check edit campaign.
     */
    $campaign_id = isset($_GET['campaign_id'])
        ? absint($_GET['campaign_id'])
        : 0;

    if ($campaign_id) {

        cem_render_campaign_editor($campaign_id);

        return;
    }


    /*
     * Get campaigns.
     */
    $campaigns = $wpdb->get_results(
        "
        SELECT
            c.*,
            COUNT(
                CASE
                    WHEN cr.status != 'skipped'
                    THEN cr.id
                END
            ) AS recipient_count
        FROM $campaigns_table c
        LEFT JOIN {$wpdb->prefix}em_campaign_recipients cr
            ON c.id = cr.campaign_id
        GROUP BY c.id
        ORDER BY c.id DESC
        "
    );

    ?>

    <div class="wrap">

        <h1 class="wp-heading-inline">
            Campaigns
        </h1>

        <a
            href="<?php echo esc_url(
                add_query_arg(
                    array(
                        'page' => 'cem-campaigns',
                        'create' => '1',
                    ),
                    admin_url('admin.php')
                )
            ); ?>"
            class="page-title-action"
        >
            + Create Campaign
        </a>

        <hr class="wp-header-end">

        <?php
        settings_errors('cem_campaigns');
        cem_display_campaign_notice();
        ?>


        <?php if (isset($_GET['create'])): ?>

            <?php cem_render_create_campaign_form(); ?>

        <?php endif; ?>


        <h2>
            Your Campaigns
        </h2>


        <table class="wp-list-table widefat fixed striped">

            <thead>

                <tr>

                    <th style="width:60px;">
                        ID
                    </th>

                    <th>
                        Campaign
                    </th>

                    <th>
                        Subject
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Recipients
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (!empty($campaigns)): ?>

                    <?php foreach ($campaigns as $campaign): ?>

                        <tr>

                            <td>
                                <?php
                                echo esc_html(
                                    $campaign->id
                                );
                                ?>
                            </td>


                            <td>

                                <strong>

                                    <a
                                        href="<?php echo esc_url(
                                            add_query_arg(
                                                array(
                                                    'page' =>
                                                        'cem-campaigns',
                                                    'campaign_id' =>
                                                        $campaign->id,
                                                ),
                                                admin_url('admin.php')
                                            )
                                        ); ?>"
                                    >

                                        <?php
                                        echo esc_html(
                                            $campaign->name
                                        );
                                        ?>

                                    </a>

                                </strong>

                            </td>


                            <td>
                                <?php
                                echo esc_html(
                                    $campaign->subject
                                );
                                ?>
                            </td>


                            <td>

                                <?php
                                $status = $campaign->status;
                                ?>

                                <?php if ($status === 'draft'): ?>

                                    Draft

                                <?php elseif ($status === 'scheduled'): ?>

                                    <span style="color:#2271b1;">
                                        Scheduled
                                    </span>

                                <?php elseif ($status === 'sending'): ?>

                                    <span style="color:#dba617;font-weight:600;">
                                        Sending
                                    </span>

                                <?php elseif ($status === 'sent'): ?>

                                    <span style="color:#008a20;font-weight:600;">
                                        Sent
                                    </span>

                                <?php elseif ($status === 'failed'): ?>

                                    <span style="color:#b32d2e;font-weight:600;">
                                        Failed
                                    </span>

                                <?php else: ?>

                                    <?php
                                    echo esc_html(
                                        ucfirst($status)
                                    );
                                    ?>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        $campaign->recipient_count
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo esc_html(
                                    wp_date(
                                        get_option('date_format'),
                                        strtotime(
                                            $campaign->created_at
                                        )
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <a
                                    href="<?php echo esc_url(
                                        add_query_arg(
                                            array(
                                                'page' =>
                                                    'cem-campaigns',
                                                'campaign_id' =>
                                                    $campaign->id,
                                            ),
                                            admin_url('admin.php')
                                        )
                                    ); ?>"
                                    class="button button-small"
                                >
                                    Edit
                                </a>


                                <?php if ($campaign->status !== 'sending'): ?>

                                    <?php

                                    $delete_nonce =
                                        wp_create_nonce(
                                            'cem_delete_campaign_' .
                                            $campaign->id
                                        );

                                    $delete_url =
                                        add_query_arg(
                                            array(
                                                'page' =>
                                                    'cem-campaigns',
                                                'action' =>
                                                    'delete',
                                                'campaign_id' =>
                                                    $campaign->id,
                                                '_wpnonce' =>
                                                    $delete_nonce,
                                            ),
                                            admin_url('admin.php')
                                        );

                                    ?>

                                    <a
                                        href="<?php echo esc_url(
                                            $delete_url
                                        ); ?>"
                                        class="button button-small"
                                        style="
                                            color:#b32d2e;
                                            border-color:#b32d2e;
                                        "
                                        onclick="return confirm('Are you sure you want to delete this campaign?');"
                                    >
                                        Delete
                                    </a>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            style="
                                text-align:center;
                                padding:30px;
                            "
                        >
                            No campaigns yet.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <?php
}


/**
 * Create Campaign form.
 */
function cem_render_create_campaign_form() {

    global $wpdb;

    $lists_table =
        $wpdb->prefix . 'em_lists';


    /*
     * Get active lists.
     */
    $lists = $wpdb->get_results(
        "
        SELECT
            id,
            name,
            description
        FROM $lists_table
        WHERE status = 'active'
        ORDER BY
            CASE
                WHEN name = 'Newsletter'
                THEN 0
                ELSE 1
            END,
            name ASC
        "
    );

    ?>

    <div
        style="
            background:#fff;
            border:1px solid #dcdcde;
            padding:25px;
            max-width:800px;
            margin:20px 0;
        "
    >

        <h2>
            Create Campaign
        </h2>


        <form method="post">

            <?php

            wp_nonce_field(
                'cem_create_campaign_action',
                'cem_create_campaign_nonce'
            );

            ?>


            <table class="form-table">

                <tr>

                    <th>
                        <label for="campaign_name">
                            Campaign Name
                        </label>
                    </th>

                    <td>

                        <input
                            type="text"
                            id="campaign_name"
                            name="campaign_name"
                            class="regular-text"
                            placeholder="August Newsletter"
                            required
                        >

                        <p class="description">
                            Internal name for this campaign.
                        </p>

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="subject">
                            Email Subject
                        </label>
                    </th>

                    <td>

                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            class="large-text"
                            placeholder="Your latest news from Project Labs"
                            required
                        >

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="from_name">
                            From Name
                        </label>
                    </th>

                    <td>

                        <input
                            type="text"
                            id="from_name"
                            name="from_name"
                            class="regular-text"
                            value="Project Labs"
                        >

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="from_email">
                            From Email
                        </label>
                    </th>

                    <td>

                        <input
                            type="email"
                            id="from_email"
                            name="from_email"
                            class="regular-text"
                            value="info@projectlabs.us"
                            required
                        >

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="reply_to">
                            Reply-To
                        </label>
                    </th>

                    <td>

                        <input
                            type="email"
                            id="reply_to"
                            name="reply_to"
                            class="regular-text"
                            value="info@projectlabs.us"
                        >

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="campaign_type">
                            Campaign Type
                        </label>
                    </th>

                    <td>

                        <select
                            id="campaign_type"
                            name="campaign_type"
                        >

                            <option value="newsletter">
                                Newsletter
                            </option>

                            <option value="marketing">
                                Marketing
                            </option>

                        </select>

                    </td>

                </tr>


                <tr>

                    <th>
                        <label for="list_id">
                            Send To
                        </label>
                    </th>

                    <td>

                        <select
                            id="list_id"
                            name="list_id"
                            required
                        >

                            <option value="">
                                — Select Mailing List —
                            </option>

                            <?php foreach ($lists as $list): ?>

                                <option
                                    value="<?php echo esc_attr(
                                        $list->id
                                    ); ?>"
                                >

                                    <?php
                                    echo esc_html(
                                        $list->name
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="description">
                            Select the mailing list for this campaign.
                        </p>

                    </td>

                </tr>

            </table>


            <p>

                <button
                    type="submit"
                    name="cem_create_campaign"
                    class="button button-primary"
                >
                    Save Draft
                </button>

                <a
                    href="<?php echo esc_url(
                        add_query_arg(
                            array(
                                'page' =>
                                    'cem-campaigns',
                            ),
                            admin_url('admin.php')
                        )
                    ); ?>"
                    class="button"
                >
                    Cancel
                </a>

            </p>

        </form>

    </div>

    <?php
}


/**
 * Campaign editor.
 */
function cem_render_campaign_editor($campaign_id) {

    global $wpdb;

    $campaigns_table =
        $wpdb->prefix . 'em_campaigns';


    /*
     * Get campaign.
     */
    $campaign = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM $campaigns_table
            WHERE id = %d
            LIMIT 1
            ",
            $campaign_id
        )
    );


    /*
     * Validate campaign before using it.
     */
    if (!$campaign) {

        echo '<div class="wrap">';
        echo '<h1>Campaign Not Found</h1>';
        echo '<p>The requested campaign does not exist.</p>';
        echo '</div>';

        return;
    }


    /*
     * Tables.
     */
    $lists_table =
        $wpdb->prefix . 'em_lists';

    $contact_lists_table =
        $wpdb->prefix . 'em_contact_lists';

    $contacts_table =
        $wpdb->prefix . 'em_contacts';


    /*
     * Get active lists.
     */
    $lists = $wpdb->get_results(
        "
        SELECT
            id,
            name,
            description
        FROM $lists_table
        WHERE status = 'active'
        ORDER BY
            CASE
                WHEN name = 'Newsletter'
                THEN 0
                ELSE 1
            END,
            name ASC
        "
    );


    /*
     * Get eligible recipient count.
     *
     * Contact must:
     * - belong to selected list
     * - have subscribed list status
     * - have active contact status
     */
    $recipient_count = 0;

    if (!empty($campaign->list_id)) {

        $recipient_count = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(DISTINCT cl.contact_id)
                FROM $contact_lists_table cl
                INNER JOIN $contacts_table c
                    ON c.id = cl.contact_id
                WHERE cl.list_id = %d
                AND cl.status = 'subscribed'
                AND c.status = 'active'
                ",
                $campaign->list_id
            )
        );
    }

    ?>

    <div class="wrap">

        <h1>
            Edit Campaign
        </h1>


        <p>

            <a
                href="<?php echo esc_url(
                    add_query_arg(
                        array(
                            'page' =>
                                'cem-campaigns',
                        ),
                        admin_url('admin.php')
                    )
                ); ?>"
            >
                &larr; Back to Campaigns
            </a>

        </p>


        <?php

        settings_errors('cem_campaigns');
        cem_display_campaign_notice();

        ?>


        <div
            style="
                background:#fff;
                border:1px solid #dcdcde;
                padding:25px;
                max-width:900px;
                margin-top:20px;
            "
        >

            <form method="post">

                <?php

                wp_nonce_field(
                    'cem_update_campaign_action',
                    'cem_update_campaign_nonce'
                );

                ?>


                <input
                    type="hidden"
                    name="campaign_id"
                    value="<?php echo esc_attr(
                        $campaign->id
                    ); ?>"
                >


                <table class="form-table">

                    <tr>

                        <th>
                            <label for="campaign_name">
                                Campaign Name
                            </label>
                        </th>

                        <td>

                            <input
                                type="text"
                                id="campaign_name"
                                name="campaign_name"
                                class="large-text"
                                value="<?php echo esc_attr(
                                    $campaign->name
                                ); ?>"
                                required
                            >

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="subject">
                                Email Subject
                            </label>
                        </th>

                        <td>

                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                class="large-text"
                                value="<?php echo esc_attr(
                                    $campaign->subject
                                ); ?>"
                                required
                            >

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="from_name">
                                From Name
                            </label>
                        </th>

                        <td>

                            <input
                                type="text"
                                id="from_name"
                                name="from_name"
                                class="regular-text"
                                value="<?php echo esc_attr(
                                    $campaign->from_name
                                ); ?>"
                            >

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="from_email">
                                From Email
                            </label>
                        </th>

                        <td>

                            <input
                                type="email"
                                id="from_email"
                                name="from_email"
                                class="regular-text"
                                value="<?php echo esc_attr(
                                    $campaign->from_email
                                ); ?>"
                                required
                            >

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="reply_to">
                                Reply-To
                            </label>
                        </th>

                        <td>

                            <input
                                type="email"
                                id="reply_to"
                                name="reply_to"
                                class="regular-text"
                                value="<?php echo esc_attr(
                                    $campaign->reply_to
                                ); ?>"
                            >

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="campaign_type">
                                Campaign Type
                            </label>
                        </th>

                        <td>

                            <select
                                id="campaign_type"
                                name="campaign_type"
                            >

                                <option
                                    value="newsletter"
                                    <?php selected(
                                        $campaign->campaign_type,
                                        'newsletter'
                                    ); ?>
                                >
                                    Newsletter
                                </option>

                                <option
                                    value="marketing"
                                    <?php selected(
                                        $campaign->campaign_type,
                                        'marketing'
                                    ); ?>
                                >
                                    Marketing
                                </option>

                            </select>

                        </td>

                    </tr>


                    <tr>

                        <th>
                            <label for="list_id">
                                Send To
                            </label>
                        </th>

                        <td>

                            <select
                                id="list_id"
                                name="list_id"
                                required
                            >

                                <option value="">
                                    — Select Mailing List —
                                </option>

                                <?php foreach ($lists as $list): ?>

                                    <option
                                        value="<?php echo esc_attr(
                                            $list->id
                                        ); ?>"
                                        <?php selected(
                                            (int) $campaign->list_id,
                                            (int) $list->id
                                        ); ?>
                                    >

                                        <?php
                                        echo esc_html(
                                            $list->name
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <p class="description">
                                Select the mailing list for this campaign.
                            </p>


                            <p>

                                <strong>
                                    Eligible Recipients:
                                </strong>

                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        (int) $recipient_count
                                    )
                                );
                                ?>

                            </p>


                            <?php if ($campaign->status === 'draft' && $recipient_count > 0): ?>

                                <form method="post" style="margin-top:15px;">

                                    <?php

                                    wp_nonce_field(
                                        'cem_prepare_recipients_action',
                                        'cem_prepare_recipients_nonce'
                                    );

                                    ?>

                                    <input
                                        type="hidden"
                                        name="campaign_id"
                                        value="<?php echo esc_attr(
                                            $campaign->id
                                        ); ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="cem_prepare_recipients"
                                        class="button"
                                    >
                                        Prepare Recipients
                                    </button>

                                    <p class="description">
                                        This prepares the subscribers for the campaign.
                                        No emails will be sent.
                                    </p>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <th>
                            Status
                        </th>

                        <td>

                            <strong>
                                <?php
                                echo esc_html(
                                    ucfirst(
                                        $campaign->status
                                    )
                                );
                                ?>
                            </strong>

                            <p class="description">
                                Email sending is not enabled yet.
                            </p>

                        </td>

                    </tr>

                </table>


                <p>

                    <button
                        type="submit"
                        name="cem_update_campaign"
                        class="button button-primary"
                    >
                        Save Campaign
                    </button>

                </p>

            </form>


            <hr>


                <h2>
                    Email Content
                </h2>

                <div
                    style="
                        background:#fff;
                        border:1px solid #dcdcde;
                        padding:20px;
                    "
                >

                    <?php

                    $editor_settings = array(
                        'textarea_name' => 'html_content',
                        'textarea_rows' => 15,
                        'media_buttons' => true,
                        'teeny'         => false,
                        'quicktags'     => true,
                        'tinymce'       => true,
                    );

                    wp_editor(
                        $campaign->html_content,
                        'cem_email_content_' . $campaign->id,
                        $editor_settings
                    );

                    ?>

                </div>


            <div
                style="
                    background:#f6f7f7;
                    border:1px solid #dcdcde;
                    padding:20px;
                "
            >

                <p>
                    Email designer will be added in the next step.
                </p>

                <p>
                    This campaign is currently only a draft.
                    No emails will be sent from this screen.
                </p>

            </div>

        </div>

    </div>



    <?php
}


/**
 * Campaign notices.
 */
function cem_display_campaign_notice() {

    if (!isset($_GET['updated'])) {
        return;
    }

    $updated = sanitize_key(
        wp_unslash($_GET['updated'])
    );


    $messages = array(
        'created' =>
            'Campaign created successfully.',

        'updated' =>
            'Campaign updated successfully.',

        'deleted' =>
            'Campaign deleted successfully.',

        'not_found' =>
            'Campaign not found.',

        'cannot_delete' =>
            'A campaign that is currently sending cannot be deleted.',

        'recipients_prepared' =>
            'Campaign recipients prepared successfully.',
    );


    if (!isset($messages[$updated])) {
        return;
    }


    $notice_type =
        $updated === 'cannot_delete'
            ? 'warning'
            : 'success';

    ?>

    <div class="notice notice-<?php echo esc_attr(
        $notice_type
    ); ?> is-dismissible">

        <p>

            <?php
            echo esc_html(
                $messages[$updated]
            );
            ?>

        </p>

    </div>

    <?php
}