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