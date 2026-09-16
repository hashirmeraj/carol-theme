<?php
/**
 * Plugin Name: Custom Email Marketing
 * Description: Custom email marketing system - SendLayer testing.
 * Version: 1.0.0
 * Author: Abu Huraira
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Send an email through SendLayer.
 */
function cem_send_email_via_sendlayer(
    $to_email,
    $to_name,
    $subject,
    $html_content,
    $plain_content = '',
    $from_email = '',
    $from_name = '',
    $reply_to = ''
) {

    $api_key = get_option(
        'cem_sendlayer_api_key',
        ''
    );

    /*
     * Use saved SendLayer settings if
     * from information wasn't supplied.
     */
    if (empty($from_email)) {
        $from_email = get_option(
            'cem_sendlayer_from_email',
            ''
        );
    }

    if (empty($from_name)) {
        $from_name = get_option(
            'cem_sendlayer_from_name',
            ''
        );
    }

    if (empty($reply_to)) {
        $reply_to = $from_email;
    }


    /*
     * Validate API key.
     */
    if (empty($api_key)) {

        return array(
            'success' => false,
            'message' => 'SendLayer API key is not configured.',
        );
    }


    /*
     * Validate sender.
     */
    if (
        empty($from_email) ||
        !is_email($from_email)
    ) {

        return array(
            'success' => false,
            'message' => 'Invalid SendLayer From email address.',
        );
    }


    /*
     * Validate recipient.
     */
    if (
        empty($to_email) ||
        !is_email($to_email)
    ) {

        return array(
            'success' => false,
            'message' => 'Invalid recipient email address.',
        );
    }


    /*
     * Plain text fallback.
     */
    if (empty($plain_content)) {

        $plain_content = wp_strip_all_tags(
            $html_content
        );
    }


    /*
     * Build SendLayer payload.
     */
    $payload = array(
        'From' => array(
            'name'  => $from_name ?: 'Email Marketing',
            'email' => $from_email,
        ),

        'To' => array(
            array(
                'name'  => $to_name ?: '',
                'email' => $to_email,
            ),
        ),

        'Subject' => $subject,

        'ContentType' => 'HTML',

        'HTMLContent' => $html_content,

        'PlainContent' => $plain_content,
    );


    /*
     * Add Reply-To when available.
     */
    if (
        !empty($reply_to) &&
        is_email($reply_to)
    ) {

        $payload['ReplyTo'] = array(
            array(
                'email' => $reply_to,
            ),
        );
    }


    /*
     * Send through SendLayer.
     */
    $response = wp_remote_post(
        'https://console.sendlayer.com/api/v1/email',
        array(
            'timeout' => 30,

            'headers' => array(
                'Authorization' =>
                    'Bearer ' . $api_key,

                'Content-Type' =>
                    'application/json',
            ),

            'body' =>
                wp_json_encode($payload),
        )
    );


    /*
     * WordPress HTTP error.
     */
    if (is_wp_error($response)) {

        return array(
            'success' => false,
            'message' =>
                'WordPress HTTP Error: ' .
                $response->get_error_message(),
        );
    }


    /*
     * Read response.
     */
    $status_code =
        wp_remote_retrieve_response_code(
            $response
        );

    $body =
        wp_remote_retrieve_body(
            $response
        );

    $data =
        json_decode(
            $body,
            true
        );


    /*
     * Successful SendLayer response.
     */
    if (
        $status_code >= 200 &&
        $status_code < 300
    ) {

        $message_id = '';

        if (
            is_array($data) &&
            isset($data['MessageID'])
        ) {

            $message_id =
                sanitize_text_field(
                    $data['MessageID']
                );
        }


        return array(
            'success'    => true,
            'message'    => 'Email sent successfully.',
            'message_id' => $message_id,
        );
    }


    /*
     * SendLayer error.
     */
    if (
        is_array($data) &&
        isset($data['Errors'])
    ) {

        $error_text =
            wp_json_encode(
                $data['Errors']
            );

    } else {

        $error_text = $body;
    }


    return array(
        'success' => false,
        'message' =>
            'SendLayer Error (' .
            $status_code .
            '): ' .
            $error_text,
    );
}


require_once plugin_dir_path(__FILE__) . 'includes/elementor-integration.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-contacts.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-lists.php';
require_once plugin_dir_path(__FILE__) . 'includes/database-campaigns.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-campaigns.php';
require_once plugin_dir_path(__FILE__) . 'includes/email-queue.php';
/**
 * Create custom email marketing database tables.
 */
function cem_create_database_tables() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    /*
     * Contacts table
     */
    $contacts_table = $wpdb->prefix . 'em_contacts';

    $sql_contacts = "CREATE TABLE $contacts_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(320) NOT NULL,
        first_name VARCHAR(100) NOT NULL DEFAULT '',
        last_name VARCHAR(100) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        source VARCHAR(100) NOT NULL DEFAULT '',
        marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
        consent_at DATETIME NULL,
        consent_ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY status (status),
        KEY source (source)
    ) $charset_collate;";

    /*
     * Lists table
     */
    $lists_table = $wpdb->prefix . 'em_lists';

    $sql_lists = "CREATE TABLE $lists_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY name (name)
    ) $charset_collate;";

    /*
     * Contact/List relationship table
     */
    $contact_lists_table = $wpdb->prefix . 'em_contact_lists';

    $sql_contact_lists = "CREATE TABLE $contact_lists_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contact_id BIGINT UNSIGNED NOT NULL,
        list_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'subscribed',
        subscribed_at DATETIME NULL,
        unsubscribed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY contact_list (contact_id, list_id),
        KEY contact_id (contact_id),
        KEY list_id (list_id),
        KEY status (status)
    ) $charset_collate;";

    /*
     * Create tables.
     */
    dbDelta($sql_contacts);
    dbDelta($sql_lists);
    dbDelta($sql_contact_lists);

    /*
     * Create default Newsletter list.
     */
    $newsletter_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $lists_table WHERE name = %s LIMIT 1",
            'Newsletter'
        )
    );

    if (!$newsletter_exists) {

        $now = current_time('mysql');

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
    }

    /*
     * Store database version.
     */
    update_option('cem_db_version', '1.0.0');
    cem_create_campaign_tables();
}

register_activation_hook(
    __FILE__,
    'cem_create_database_tables'
);


/**
 * Add admin menu.
 */
add_action('admin_menu', function () {

    add_menu_page(
        'Email Marketing',
        'Email Marketing',
        'manage_options',
        'custom-email-marketing',
        'cem_admin_page',
        'dashicons-email-alt',
        30
    );

});

/**
 * Admin page.
 */
function cem_admin_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $saved_api_key = get_option('cem_sendlayer_api_key', '');
    $from_email    = get_option('cem_sendlayer_from_email', '');
    $from_name     = get_option('cem_sendlayer_from_name', '');

    $message = '';
    $message_type = '';

    /**
     * Save SendLayer settings.
     */
    if (
        isset($_POST['cem_save_settings']) &&
        check_admin_referer('cem_save_settings_action', 'cem_nonce')
    ) {

        $api_key = isset($_POST['cem_api_key'])
            ? sanitize_text_field(wp_unslash($_POST['cem_api_key']))
            : '';

        $from_email = isset($_POST['cem_from_email'])
            ? sanitize_email(wp_unslash($_POST['cem_from_email']))
            : '';

        $from_name = isset($_POST['cem_from_name'])
            ? sanitize_text_field(wp_unslash($_POST['cem_from_name']))
            : '';

        update_option('cem_sendlayer_api_key', $api_key);
        update_option('cem_sendlayer_from_email', $from_email);
        update_option('cem_sendlayer_from_name', $from_name);

        $saved_api_key = $api_key;

        $message = 'SendLayer settings saved.';
        $message_type = 'success';
    }

    /**
     * Send test email.
     */
    if (
        isset($_POST['cem_send_test']) &&
        check_admin_referer('cem_send_test_action', 'cem_test_nonce')
    ) {

        $api_key = get_option('cem_sendlayer_api_key', '');
        $from_email = get_option('cem_sendlayer_from_email', '');
        $from_name = get_option('cem_sendlayer_from_name', '');

        $recipient = isset($_POST['cem_recipient'])
            ? sanitize_email(wp_unslash($_POST['cem_recipient']))
            : '';

        if (empty($api_key)) {

            $message = 'Please save your SendLayer API key first.';
            $message_type = 'error';

        } elseif (!is_email($from_email)) {

            $message = 'Please enter a valid authenticated From email address.';
            $message_type = 'error';

        } elseif (!is_email($recipient)) {

            $message = 'Please enter a valid recipient email address.';
            $message_type = 'error';

        } else {

            $payload = array(
                'From' => array(
                    'name'  => $from_name ?: 'Email Marketing Test',
                    'email' => $from_email,
                ),
                'To' => array(
                    array(
                        'name'  => 'Test Recipient',
                        'email' => $recipient,
                    ),
                ),
                'Subject' => 'Test Email From Custom Marketing Plugin',
                'ContentType' => 'HTML',
                'HTMLContent' => '
                    <html>
                        <body>
                            <h2>SendLayer Test Successful</h2>
                            <p>This email was sent from our custom WordPress email marketing plugin.</p>
                            <p>If you received this email, the connection between WordPress and SendLayer is working.</p>
                        </body>
                    </html>
                ',
                'PlainContent' => 'SendLayer Test Successful. This email was sent from our custom WordPress email marketing plugin.',
            );

            $response = wp_remote_post(
                'https://console.sendlayer.com/api/v1/email',
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body' => wp_json_encode($payload),
                )
            );

            if (is_wp_error($response)) {

                $message = 'WordPress HTTP Error: ' . $response->get_error_message();
                $message_type = 'error';

            } else {

                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if ($status_code >= 200 && $status_code < 300) {

                    $message_id = '';

                    if (is_array($data) && isset($data['MessageID'])) {
                        $message_id = $data['MessageID'];
                    }

                    $message = 'Test email sent successfully.';

                    if ($message_id) {
                        $message .= ' Message ID: ' . esc_html($message_id);
                    }

                    $message_type = 'success';

                } else {

                    $error_text = '';

                    if (is_array($data) && isset($data['Errors'])) {
                        $error_text = wp_json_encode($data['Errors']);
                    } else {
                        $error_text = $body;
                    }

                    $message = 'SendLayer Error (' . esc_html($status_code) . '): ' . esc_html($error_text);
                    $message_type = 'error';
                }
            }
        }
    }

    ?>

    <div class="wrap">

        <h1>Custom Email Marketing</h1>

        <?php if ($message): ?>

            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>

        <?php endif; ?>

        <div style="max-width: 800px;">

            <h2>SendLayer Settings</h2>

            <form method="post">

                <?php wp_nonce_field('cem_save_settings_action', 'cem_nonce'); ?>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <label for="cem_api_key">
                                SendLayer API Key
                            </label>
                        </th>

                        <td>

                            <input
                                type="password"
                                id="cem_api_key"
                                name="cem_api_key"
                                value="<?php echo esc_attr($saved_api_key); ?>"
                                class="regular-text"
                                autocomplete="new-password"
                            >

                            <p class="description">
                                Your SendLayer API key. Never share this key publicly.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="cem_from_email">
                                From Email
                            </label>
                        </th>

                        <td>

                            <input
                                type="email"
                                id="cem_from_email"
                                name="cem_from_email"
                                value="<?php echo esc_attr($from_email); ?>"
                                class="regular-text"
                                placeholder="marketing@yourdomain.com"
                            >

                            <p class="description">
                                This must use a domain authenticated in SendLayer.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="cem_from_name">
                                From Name
                            </label>
                        </th>

                        <td>

                            <input
                                type="text"
                                id="cem_from_name"
                                name="cem_from_name"
                                value="<?php echo esc_attr($from_name); ?>"
                                class="regular-text"
                                placeholder="Your Company"
                            >

                        </td>
                    </tr>

                </table>

                <p>
                    <button
                        type="submit"
                        name="cem_save_settings"
                        class="button button-primary"
                    >
                        Save SendLayer Settings
                    </button>
                </p>

            </form>

            <hr>

            <h2>Send Test Email</h2>

            <form method="post">

                <?php wp_nonce_field('cem_send_test_action', 'cem_test_nonce'); ?>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <label for="cem_recipient">
                                Recipient Email
                            </label>
                        </th>

                        <td>

                            <input
                                type="email"
                                id="cem_recipient"
                                name="cem_recipient"
                                value=""
                                class="regular-text"
                                placeholder="your-test-email@gmail.com"
                                required
                            >

                        </td>
                    </tr>

                </table>

                <p>
                    <button
                        type="submit"
                        name="cem_send_test"
                        class="button button-primary"
                    >
                        Send Test Email
                    </button>
                </p>

            </form>

        </div>

    </div>

    <?php
}