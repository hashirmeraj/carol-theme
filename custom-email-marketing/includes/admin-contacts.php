<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Contacts submenu.
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
 * Render Contacts page.
 */
function cem_render_contacts_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $contacts_table = $wpdb->prefix . 'em_contacts';

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
     * Build WHERE clause.
     */
    $where = 'WHERE 1=1';
    $query_values = array();

    if ($search !== '') {

        $where .= ' AND email LIKE %s';

        $query_values[] = '%' . $wpdb->esc_like($search) . '%';
    }

    /*
     * Count contacts.
     */
    $count_sql = "SELECT COUNT(*)
                  FROM $contacts_table
                  $where";

    if (!empty($query_values)) {
        $total_contacts = $wpdb->get_var(
            $wpdb->prepare(
                $count_sql,
                $query_values
            )
        );
    } else {
        $total_contacts = $wpdb->get_var($count_sql);
    }

    /*
     * Get contacts.
     */
    $contacts_sql = "SELECT
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
                     LIMIT %d OFFSET %d";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    $contacts = $wpdb->get_results(
        $wpdb->prepare(
            $contacts_sql,
            $query_values
        )
    );

    /*
     * Calculate pages.
     */
    $total_pages = ceil($total_contacts / $per_page);

    ?>

    <div class="wrap">

        <h1 class="wp-heading-inline">
            Contacts
        </h1>

        <hr class="wp-header-end">

        <div style="margin: 20px 0;">

            <strong>
                <?php echo esc_html(number_format_i18n($total_contacts)); ?>
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

                    <th style="width: 40px;">
                        ID
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Name
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
                                    <?php echo esc_html($contact->email); ?>
                                </strong>
                            </td>

                            <td>

                                <?php

                                $full_name = trim(
                                    $contact->first_name . ' ' .
                                    $contact->last_name
                                );

                                echo $full_name
                                    ? esc_html($full_name)
                                    : '—';

                                ?>

                            </td>

                            <td>
                                <?php echo esc_html($contact->source ?: '—'); ?>
                            </td>

                            <td>

                                <?php if ($contact->status === 'active'): ?>

                                    <span style="color: #008a20; font-weight: 600;">
                                        Active
                                    </span>

                                <?php elseif ($contact->status === 'unsubscribed'): ?>

                                    <span style="color: #b32d2e; font-weight: 600;">
                                        Unsubscribed
                                    </span>

                                <?php else: ?>

                                    <?php echo esc_html(
                                        ucfirst($contact->status)
                                    ); ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ((int) $contact->marketing_consent === 1): ?>

                                    <span style="color: #008a20;">
                                        Yes
                                    </span>

                                <?php else: ?>

                                    <span style="color: #b32d2e;">
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
                                            strtotime($contact->consent_at)
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
                            colspan="7"
                            style="text-align: center; padding: 30px;"
                        >

                            <?php if ($search): ?>

                                No contacts found for:
                                <strong>
                                    <?php echo esc_html($search); ?>
                                </strong>

                            <?php else: ?>

                                No contacts yet.

                            <?php endif; ?>

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
                            'type' => 'plain',
                        )
                    );

                    ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <?php
}