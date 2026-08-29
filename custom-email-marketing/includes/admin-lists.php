<?php

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register Lists submenu.
 */
add_action('admin_menu', 'cem_register_lists_page', 20);

function cem_register_lists_page() {

    add_submenu_page(
        'custom-email-marketing',
        'Lists',
        'Lists',
        'manage_options',
        'cem-lists',
        'cem_render_lists_page'
    );
}


/**
 * Handle list actions.
 */
add_action('admin_init', 'cem_handle_list_actions');

function cem_handle_list_actions() {

    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (
        !isset($_GET['page']) ||
        $_GET['page'] !== 'cem-lists'
    ) {
        return;
    }

    global $wpdb;

    $lists_table = $wpdb->prefix . 'em_lists';

    /*
     * Create list.
     */
    if (
        isset($_POST['cem_create_list']) &&
        check_admin_referer(
            'cem_create_list_action',
            'cem_create_list_nonce'
        )
    ) {

        $name = isset($_POST['list_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['list_name'])
            )
            : '';

        $description = isset($_POST['list_description'])
            ? sanitize_textarea_field(
                wp_unslash($_POST['list_description'])
            )
            : '';

        if (empty($name)) {

            add_settings_error(
                'cem_lists',
                'empty_name',
                'List name is required.',
                'error'
            );

        } else {

            /*
             * Check duplicate name.
             */
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM $lists_table
                     WHERE name = %s
                     LIMIT 1",
                    $name
                )
            );

            if ($existing) {

                add_settings_error(
                    'cem_lists',
                    'duplicate_name',
                    'A list with this name already exists.',
                    'error'
                );

            } else {

                $now = current_time('mysql');

                $inserted = $wpdb->insert(
                    $lists_table,
                    array(
                        'name'        => $name,
                        'description' => $description,
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

                if ($inserted) {

                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'    => 'cem-lists',
                                'updated' => 'created',
                            ),
                            admin_url('admin.php')
                        )
                    );

                    exit;

                } else {

                    add_settings_error(
                        'cem_lists',
                        'create_failed',
                        'The list could not be created.',
                        'error'
                    );
                }
            }
        }
    }


    /*
     * Update list.
     */
    if (
        isset($_POST['cem_update_list']) &&
        check_admin_referer(
            'cem_update_list_action',
            'cem_update_list_nonce'
        )
    ) {

        $list_id = isset($_POST['list_id'])
            ? absint($_POST['list_id'])
            : 0;

        $name = isset($_POST['list_name'])
            ? sanitize_text_field(
                wp_unslash($_POST['list_name'])
            )
            : '';

        $description = isset($_POST['list_description'])
            ? sanitize_textarea_field(
                wp_unslash($_POST['list_description'])
            )
            : '';

        if (!$list_id || empty($name)) {

            add_settings_error(
                'cem_lists',
                'update_failed',
                'List ID and list name are required.',
                'error'
            );

        } else {

            /*
             * Check if another list has same name.
             */
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM $lists_table
                     WHERE name = %s
                     AND id != %d
                     LIMIT 1",
                    $name,
                    $list_id
                )
            );

            if ($existing) {

                add_settings_error(
                    'cem_lists',
                    'duplicate_name',
                    'Another list already has this name.',
                    'error'
                );

            } else {

                $updated = $wpdb->update(
                    $lists_table,
                    array(
                        'name'        => $name,
                        'description' => $description,
                        'updated_at'  => current_time('mysql'),
                    ),
                    array(
                        'id' => $list_id,
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

                if ($updated !== false) {

                    wp_safe_redirect(
                        add_query_arg(
                            array(
                                'page'    => 'cem-lists',
                                'updated' => 'updated',
                            ),
                            admin_url('admin.php')
                        )
                    );

                    exit;

                } else {

                    add_settings_error(
                        'cem_lists',
                        'update_failed',
                        'The list could not be updated.',
                        'error'
                    );
                }
            }
        }
    }


    /*
     * Delete list.
     */
    if (
        isset($_GET['action']) &&
        $_GET['action'] === 'delete'
    ) {

        $list_id = isset($_GET['list_id'])
            ? absint($_GET['list_id'])
            : 0;

        if (!$list_id) {
            return;
        }

        /*
         * Verify nonce.
         */
        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(
                wp_unslash($_GET['_wpnonce'])
            )
            : '';

        if (
            !wp_verify_nonce(
                $nonce,
                'cem_delete_list_' . $list_id
            )
        ) {

            wp_die('Security check failed.');
        }

        /*
         * Get list.
         */
        $list = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM $lists_table
                 WHERE id = %d
                 LIMIT 1",
                $list_id
            )
        );

        if (!$list) {

            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'    => 'cem-lists',
                        'updated' => 'not_found',
                    ),
                    admin_url('admin.php')
                )
            );

            exit;
        }

        /*
         * Protect Newsletter list.
         */
        if ($list->name === 'Newsletter') {

            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'    => 'cem-lists',
                        'updated' => 'protected',
                    ),
                    admin_url('admin.php')
                )
            );

            exit;
        }

        /*
         * Remove relationships first.
         */
        $contact_lists_table =
            $wpdb->prefix . 'em_contact_lists';

        $wpdb->delete(
            $contact_lists_table,
            array(
                'list_id' => $list_id,
            ),
            array(
                '%d',
            )
        );

        /*
         * Delete list.
         */
        $wpdb->delete(
            $lists_table,
            array(
                'id' => $list_id,
            ),
            array(
                '%d',
            )
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cem-lists',
                    'updated' => 'deleted',
                ),
                admin_url('admin.php')
            )
        );

        exit;
    }
}


/**
 * Render Lists page.
 */
function cem_render_lists_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $lists_table =
        $wpdb->prefix . 'em_lists';

    $contact_lists_table =
        $wpdb->prefix . 'em_contact_lists';

    /*
     * Check if editing a list.
     */
    $edit_id = isset($_GET['edit'])
        ? absint($_GET['edit'])
        : 0;

    /*
     * Display notices.
     */
    settings_errors('cem_lists');

    if (isset($_GET['updated'])) {

        $updated = sanitize_key(
            wp_unslash($_GET['updated'])
        );

        $messages = array(
            'created' => 'List created successfully.',
            'updated' => 'List updated successfully.',
            'deleted' => 'List deleted successfully.',
            'protected' => 'The Newsletter list cannot be deleted.',
            'not_found' => 'List not found.',
        );

        if (isset($messages[$updated])) {

            $notice_type =
                $updated === 'protected'
                    ? 'warning'
                    : 'success';

            ?>

            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible">

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
    }


    /*
     * Get list being edited.
     */
    $editing_list = null;

    if ($edit_id) {

        $editing_list = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM $lists_table
                 WHERE id = %d
                 LIMIT 1",
                $edit_id
            )
        );
    }


    /*
     * Get lists and subscriber counts.
     */
    $lists = $wpdb->get_results(
        "
        SELECT
            l.id,
            l.name,
            l.description,
            l.status,
            l.created_at,
            l.updated_at,
            COUNT(
                CASE
                    WHEN cl.status = 'subscribed'
                    THEN cl.contact_id
                END
            ) AS subscriber_count
        FROM $lists_table l
        LEFT JOIN $contact_lists_table cl
            ON l.id = cl.list_id
        GROUP BY l.id
        ORDER BY
            CASE
                WHEN l.name = 'Newsletter'
                THEN 0
                ELSE 1
            END,
            l.name ASC
        "
    );

    ?>

    <div class="wrap">

        <h1 class="wp-heading-inline">
            Lists
        </h1>

        <hr class="wp-header-end">


        <?php if ($editing_list): ?>

            <!-- Edit List -->

            <div
                style="
                    background:#fff;
                    border:1px solid #dcdcde;
                    padding:20px;
                    max-width:700px;
                    margin:20px 0;
                "
            >

                <h2>
                    Edit List
                </h2>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'cem_update_list_action',
                        'cem_update_list_nonce'
                    );
                    ?>

                    <input
                        type="hidden"
                        name="list_id"
                        value="<?php echo esc_attr($editing_list->id); ?>"
                    >

                    <table class="form-table">

                        <tr>

                            <th>
                                <label for="list_name">
                                    List Name
                                </label>

                            </th>

                            <td>

                                <input
                                    type="text"
                                    id="list_name"
                                    name="list_name"
                                    value="<?php echo esc_attr($editing_list->name); ?>"
                                    class="regular-text"
                                    required
                                >

                            </td>

                        </tr>

                        <tr>

                            <th>

                                <label for="list_description">
                                    Description
                                </label>

                            </th>

                            <td>

                                <textarea
                                    id="list_description"
                                    name="list_description"
                                    rows="4"
                                    class="large-text"
                                ><?php echo esc_textarea($editing_list->description); ?></textarea>

                            </td>

                        </tr>

                    </table>

                    <p>

                        <button
                            type="submit"
                            name="cem_update_list"
                            class="button button-primary"
                        >
                            Save Changes
                        </button>

                        <a
                            href="<?php echo esc_url(
                                add_query_arg(
                                    array(
                                        'page' => 'cem-lists',
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

        <?php else: ?>

            <!-- Create List -->

            <div
                style="
                    background:#fff;
                    border:1px solid #dcdcde;
                    padding:20px;
                    max-width:700px;
                    margin:20px 0;
                "
            >

                <h2>
                    Add New List
                </h2>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'cem_create_list_action',
                        'cem_create_list_nonce'
                    );
                    ?>

                    <table class="form-table">

                        <tr>

                            <th>

                                <label for="list_name">
                                    List Name
                                </label>

                            </th>

                            <td>

                                <input
                                    type="text"
                                    id="list_name"
                                    name="list_name"
                                    class="regular-text"
                                    placeholder="Example: VIP Customers"
                                    required
                                >

                            </td>

                        </tr>

                        <tr>

                            <th>

                                <label for="list_description">
                                    Description
                                </label>

                            </th>

                            <td>

                                <textarea
                                    id="list_description"
                                    name="list_description"
                                    rows="4"
                                    class="large-text"
                                    placeholder="Describe this list..."
                                ></textarea>

                            </td>

                        </tr>

                    </table>

                    <p>

                        <button
                            type="submit"
                            name="cem_create_list"
                            class="button button-primary"
                        >
                            Add List
                        </button>

                    </p>

                </form>

            </div>

        <?php endif; ?>


        <!-- Lists -->

        <h2>
            Your Lists
        </h2>

        <table class="wp-list-table widefat fixed striped">

            <thead>

                <tr>

                    <th>
                        List
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Subscribers
                    </th>

                    <th>
                        Status
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

                <?php if (!empty($lists)): ?>

                    <?php foreach ($lists as $list): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $list->name
                                    );
                                    ?>
                                </strong>

                                <?php
                                if ($list->name === 'Newsletter') {
                                    ?>
                                    <span
                                        style="
                                            display:inline-block;
                                            margin-left:5px;
                                            color:#2271b1;
                                            font-weight:600;
                                        "
                                    >
                                        Default
                                    </span>
                                    <?php
                                }
                                ?>

                            </td>

                            <td>

                                <?php
                                echo $list->description
                                    ? esc_html(
                                        $list->description
                                    )
                                    : '—';
                                ?>

                            </td>

                            <td>

                                <strong>

                                    <?php
                                    echo esc_html(
                                        number_format_i18n(
                                            $list->subscriber_count
                                        )
                                    );
                                    ?>

                                </strong>

                            </td>

                            <td>

                                <?php if ($list->status === 'active'): ?>

                                    <span
                                        style="
                                            color:#008a20;
                                            font-weight:600;
                                        "
                                    >
                                        Active
                                    </span>

                                <?php else: ?>

                                    <?php
                                    echo esc_html(
                                        ucfirst(
                                            $list->status
                                        )
                                    );
                                    ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php
                                echo esc_html(
                                    wp_date(
                                        get_option('date_format'),
                                        strtotime(
                                            $list->created_at
                                        )
                                    )
                                );
                                ?>

                            </td>

                            <td>

                                <?php

                                $edit_url = add_query_arg(
                                    array(
                                        'page' => 'cem-lists',
                                        'edit' => $list->id,
                                    ),
                                    admin_url('admin.php')
                                );

                                ?>

                                <a
                                    href="<?php echo esc_url($edit_url); ?>"
                                    class="button button-small"
                                >
                                    Edit
                                </a>


                                <?php if ($list->name !== 'Newsletter'): ?>

                                    <?php

                                    $delete_nonce = wp_create_nonce(
                                        'cem_delete_list_' . $list->id
                                    );

                                    $delete_url = add_query_arg(
                                        array(
                                            'page'      => 'cem-lists',
                                            'action'    => 'delete',
                                            'list_id'   => $list->id,
                                            '_wpnonce'  => $delete_nonce,
                                        ),
                                        admin_url('admin.php')
                                    );

                                    ?>

                                    <a
                                        href="<?php echo esc_url($delete_url); ?>"
                                        class="button button-small"
                                        style="color:#b32d2e;border-color:#b32d2e;"
                                        onclick="return confirm('Are you sure you want to delete this list? Contacts will NOT be deleted.');"
                                    >
                                        Delete
                                    </a>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#646970;
                                            margin-left:5px;
                                        "
                                    >
                                        Protected
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            style="
                                text-align:center;
                                padding:30px;
                            "
                        >
                            No lists found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <?php
}