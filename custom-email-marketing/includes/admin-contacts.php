<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register Contacts submenu.
 */
add_action('admin_menu', 'cem_register_contacts_page', 20);

function cem_register_contacts_page() {

    add_submenu_page(
        'custom-email-marketing',
        'Contacts',
        'Contacts',
        'manage_options',
        'cem-contacts',
        'cem_render_contacts_page'
    );
}


/**
 * Handle contact actions.
 */
add_action('admin_init', 'cem_handle_contact_actions');

function cem_handle_contact_actions() {

    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'cem-contacts') {
        return;
    }

    if (!isset($_GET['action'], $_GET['contact_id'])) {
        return;
    }

    $action = sanitize_key(
        wp_unslash($_GET['action'])
    );

    $contact_id = absint($_GET['contact_id']);

    if (!$contact_id) {
        return;
    }

    /*
     * Verify nonce.
     */
    $nonce = isset($_GET['_wpnonce'])
        ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'cem_contact_action_' . $contact_id)) {
        wp_die('Security check failed.');
    }

    global $wpdb;

    $contacts_table = $wpdb->prefix . 'em_contacts';
    $lists_table = $wpdb->prefix . 'em_lists';
    $contact_lists_table = $wpdb->prefix . 'em_contact_lists';

    /*
     * Unsubscribe contact.
     */
    if ($action === 'unsubscribe') {

        $wpdb->update(
            $contacts_table,
            array(
                'status'     => 'unsubscribed',
                'updated_at' => current_time('mysql'),
            ),
            array(
                'id' => $contact_id,
            ),
            array(
                '%s',
                '%s',
            ),
            array(
                '%d',
            )
        );

        /*
         * Mark all list memberships as unsubscribed.
         */
        $wpdb->update(
            $contact_lists_table,
            array(
                'status'          => 'unsubscribed',
                'unsubscribed_at' => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ),
            array(
                'contact_id' => $contact_id,
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

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cem-contacts',
                    'updated' => 'unsubscribed',
                ),
                admin_url('admin.php')
            )
        );

        exit;
    }


    /*
     * Manually resubscribe contact.
     *
     * This is an admin action only.
     */
    if ($action === 'resubscribe') {

        $now = current_time('mysql');

        $wpdb->update(
            $contacts_table,
            array(
                'status'     => 'active',
                'updated_at' => $now,
            ),
            array(
                'id' => $contact_id,
            ),
            array(
                '%s',
                '%s',
            ),
            array(
                '%d',
            )
        );

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

        if ($list_id) {

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

            if ($relationship_id) {

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

            } else {

                $wpdb->insert(
                    $contact_lists_table,
                    array(
                        'contact_id'    => $contact_id,
                        'list_id'       => $list_id,
                        'status'        => 'subscribed',
                        'subscribed_at' => $now,
                        'created_at'    => $now,
                        'updated_at'    => $now,
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
            }
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cem-contacts',
                    'updated' => 'resubscribed',
                ),
                admin_url('admin.php')
            )
        );

        exit;
    }


    /*
     * Permanently delete contact.
     */
    if ($action === 'delete') {

        /*
         * Delete list relationships first.
         */
        $wpdb->delete(
            $contact_lists_table,
            array(
                'contact_id' => $contact_id,
            ),
            array(
                '%d',
            )
        );

        /*
         * Delete contact.
         */
        $wpdb->delete(
            $contacts_table,
            array(
                'id' => $contact_id,
            ),
            array(
                '%d',
            )
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cem-contacts',
                    'updated' => 'deleted',
                ),
                admin_url('admin.php')
            )
        );

        exit;
    }
}


/**
 * Render Contacts page.
 */
function cem_render_contacts_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $contacts_table = $wpdb->prefix . 'em_contacts';

    /*
     * Check if viewing a specific contact.
     */
    $contact_id = isset($_GET['contact_id'])
        ? absint($_GET['contact_id'])
        : 0;

    if ($contact_id) {

        cem_render_contact_details($contact_id);

        return;
    }


    /*
     * Pagination.
     */
    $per_page = 20;

    $current_page = isset($_GET['paged'])
        ? max(1, absint($_GET['paged']))
        : 1;

    $offset = ($current_page - 1) * $per_page;


    /*
     * Search.
     */
    $search = isset($_GET['s'])
        ? sanitize_text_field(wp_unslash($_GET['s']))
        : '';


    /*
     * Build WHERE.
     */
    $where = 'WHERE 1=1';

    $query_values = array();

    if ($search !== '') {

        $where .= ' AND email LIKE %s';

        $query_values[] =
            '%' . $wpdb->esc_like($search) . '%';
    }


    /*
     * Count.
     */
    $count_sql = "
        SELECT COUNT(*)
        FROM $contacts_table
        $where
    ";

    if (!empty($query_values)) {

        $total_contacts = $wpdb->get_var(
            $wpdb->prepare(
                $count_sql,
                $query_values
            )
        );

    } else {

        $total_contacts = $wpdb->get_var(
            $count_sql
        );
    }


    /*
     * Get contacts.
     */
    $contacts_sql = "
        SELECT
            id,
            email,
            first_name,
            last_name,
            status,
            source,
            marketing_consent,
            consent_at,
            created_at
        FROM $contacts_table
        $where
        ORDER BY id DESC
        LIMIT %d OFFSET %d
    ";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    $contacts = $wpdb->get_results(
        $wpdb->prepare(
            $contacts_sql,
            $query_values
        )
    );


    $total_pages = ceil(
        $total_contacts / $per_page
    );

    ?>

    <div class="wrap">

        <h1 class="wp-heading-inline">
            Contacts
        </h1>

        <hr class="wp-header-end">

        <?php cem_display_contact_notice(); ?>

        <div style="margin: 20px 0;">

            <strong>
                <?php
                echo esc_html(
                    number_format_i18n($total_contacts)
                );
                ?>
            </strong>

            <?php
            echo $total_contacts === 1
                ? ' subscriber'
                : ' subscribers';
            ?>

        </div>


        <!-- Search -->

        <form method="get">

            <input
                type="hidden"
                name="page"
                value="cem-contacts"
            >

            <p class="search-box">

                <label
                    class="screen-reader-text"
                    for="contact-search"
                >
                    Search Contacts
                </label>

                <input
                    type="search"
                    id="contact-search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Search email..."
                >

                <button
                    type="submit"
                    class="button"
                >
                    Search
                </button>

            </p>

        </form>

        <div style="clear: both;"></div>


        <!-- Contacts table -->

        <table class="wp-list-table widefat fixed striped">

            <thead>

                <tr>

                    <th style="width: 50px;">
                        ID
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Source
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Consent
                    </th>

                    <th>
                        Subscribed
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php if (!empty($contacts)): ?>

                    <?php foreach ($contacts as $contact): ?>

                        <tr>

                            <td>
                                <?php echo esc_html($contact->id); ?>
                            </td>

                            <td>

                                <strong>

                                    <a
                                        href="<?php echo esc_url(
                                            add_query_arg(
                                                array(
                                                    'page'       => 'cem-contacts',
                                                    'contact_id' => $contact->id,
                                                ),
                                                admin_url('admin.php')
                                            )
                                        ); ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            $contact->email
                                        );
                                        ?>
                                    </a>

                                </strong>

                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $contact->source ?: '—'
                                );
                                ?>
                            </td>

                            <td>

                                <?php if ($contact->status === 'active'): ?>

                                    <span style="color:#008a20;font-weight:600;">
                                        Active
                                    </span>

                                <?php elseif ($contact->status === 'unsubscribed'): ?>

                                    <span style="color:#b32d2e;font-weight:600;">
                                        Unsubscribed
                                    </span>

                                <?php else: ?>

                                    <?php
                                    echo esc_html(
                                        ucfirst(
                                            $contact->status
                                        )
                                    );
                                    ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ((int) $contact->marketing_consent === 1): ?>

                                    <span style="color:#008a20;">
                                        Yes
                                    </span>

                                <?php else: ?>

                                    <span style="color:#b32d2e;">
                                        No
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php
                                echo !empty($contact->consent_at)
                                    ? esc_html(
                                        wp_date(
                                            get_option('date_format'),
                                            strtotime(
                                                $contact->consent_at
                                            )
                                        )
                                    )
                                    : '—';
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;padding:30px;"
                        >
                            No contacts found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>


        <?php if ($total_pages > 1): ?>

            <div class="tablenav bottom">

                <div class="tablenav-pages">

                    <?php

                    echo paginate_links(
                        array(
                            'base' => add_query_arg(
                                'paged',
                                '%#%'
                            ),
                            'format' => '',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        )
                    );

                    ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <?php
}


/**
 * Contact details page.
 */
function cem_render_contact_details($contact_id) {

    global $wpdb;

    $contacts_table = $wpdb->prefix . 'em_contacts';

    $contact = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM $contacts_table
             WHERE id = %d
             LIMIT 1",
            $contact_id
        )
    );

    if (!$contact) {

        echo '<div class="wrap">';
        echo '<h1>Contact Not Found</h1>';
        echo '<p>The requested contact does not exist.</p>';
        echo '</div>';

        return;
    }

    $back_url = add_query_arg(
        array(
            'page' => 'cem-contacts',
        ),
        admin_url('admin.php')
    );

    ?>

    <div class="wrap">

        <h1>
            Contact Details
        </h1>

        <p>

            <a href="<?php echo esc_url($back_url); ?>">
                &larr; Back to Contacts
            </a>

        </p>

        <?php cem_display_contact_notice(); ?>

        <div
            style="
                max-width: 800px;
                background: #fff;
                border: 1px solid #dcdcde;
                padding: 25px;
                margin-top: 20px;
            "
        >

            <table class="form-table">

                <tr>

                    <th>
                        Email
                    </th>

                    <td>
                        <strong>
                            <?php
                            echo esc_html(
                                $contact->email
                            );
                            ?>
                        </strong>
                    </td>

                </tr>

                <tr>

                    <th>
                        Status
                    </th>

                    <td>

                        <?php if ($contact->status === 'active'): ?>

                            <span style="color:#008a20;font-weight:600;">
                                Active
                            </span>

                        <?php elseif ($contact->status === 'unsubscribed'): ?>

                            <span style="color:#b32d2e;font-weight:600;">
                                Unsubscribed
                            </span>

                        <?php else: ?>

                            <?php
                            echo esc_html(
                                ucfirst($contact->status)
                            );
                            ?>

                        <?php endif; ?>

                    </td>

                </tr>

                <tr>

                    <th>
                        Source
                    </th>

                    <td>
                        <?php
                        echo esc_html(
                            $contact->source ?: '—'
                        );
                        ?>
                    </td>

                </tr>

                <tr>

                    <th>
                        Marketing Consent
                    </th>

                    <td>
                        <?php
                        echo (int) $contact->marketing_consent === 1
                            ? 'Yes'
                            : 'No';
                        ?>
                    </td>

                </tr>

                <tr>

                    <th>
                        Consent Date
                    </th>

                    <td>
                        <?php
                        echo !empty($contact->consent_at)
                            ? esc_html(
                                $contact->consent_at
                            )
                            : '—';
                        ?>
                    </td>

                </tr>

                <tr>

                    <th>
                        Consent IP
                    </th>

                    <td>
                        <?php
                        echo esc_html(
                            $contact->consent_ip ?: '—'
                        );
                        ?>
                    </td>

                </tr>

                <tr>

                    <th>
                        Created
                    </th>

                    <td>
                        <?php
                        echo esc_html(
                            $contact->created_at
                        );
                        ?>
                    </td>

                </tr>

                <tr>

                    <th>
                        Updated
                    </th>

                    <td>
                        <?php
                        echo esc_html(
                            $contact->updated_at
                        );
                        ?>
                    </td>

                </tr>

            </table>


            <hr>


            <h2>
                Contact Actions
            </h2>

            <?php

            $action_nonce = wp_create_nonce(
                'cem_contact_action_' . $contact->id
            );

            ?>


            <?php if ($contact->status === 'active'): ?>

                <a
                    class="button"
                    href="<?php echo esc_url(
                        add_query_arg(
                            array(
                                'page'       => 'cem-contacts',
                                'contact_id' => $contact->id,
                                'action'     => 'unsubscribe',
                                '_wpnonce'   => $action_nonce,
                            ),
                            admin_url('admin.php')
                        )
                    ); ?>"
                    onclick="return confirm('Are you sure you want to unsubscribe this contact?');"
                >
                    Unsubscribe
                </a>

            <?php else: ?>

                <a
                    class="button"
                    href="<?php echo esc_url(
                        add_query_arg(
                            array(
                                'page'       => 'cem-contacts',
                                'contact_id' => $contact->id,
                                'action'     => 'resubscribe',
                                '_wpnonce'   => $action_nonce,
                            ),
                            admin_url('admin.php')
                        )
                    ); ?>"
                    onclick="return confirm('Are you sure you want to resubscribe this contact?');"
                >
                    Resubscribe
                </a>

            <?php endif; ?>


            <a
                class="button"
                style="color:#b32d2e;border-color:#b32d2e;"
                href="<?php echo esc_url(
                    add_query_arg(
                        array(
                            'page'       => 'cem-contacts',
                            'contact_id' => $contact->id,
                            'action'     => 'delete',
                            '_wpnonce'   => $action_nonce,
                        ),
                        admin_url('admin.php')
                    )
                ); ?>"
                onclick="return confirm('This will permanently delete this contact. Continue?');"
            >
                Delete Contact
            </a>

        </div>

    </div>

    <?php
}


/**
 * Display action notices.
 */
function cem_display_contact_notice() {

    if (!isset($_GET['updated'])) {
        return;
    }

    $updated = sanitize_key(
        wp_unslash($_GET['updated'])
    );

    $messages = array(
        'unsubscribed' => 'Contact unsubscribed successfully.',
        'resubscribed' => 'Contact resubscribed successfully.',
        'deleted'      => 'Contact deleted successfully.',
    );

    if (!isset($messages[$updated])) {
        return;
    }

    ?>

    <div class="notice notice-success is-dismissible">

        <p>
            <?php echo esc_html($messages[$updated]); ?>
        </p>

    </div>

    <?php
}