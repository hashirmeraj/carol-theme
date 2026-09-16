<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create campaign-related database tables.
 */
function cem_create_campaign_tables() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    /*
     * Campaigns table.
     */
    $campaigns_table = $wpdb->prefix . 'em_campaigns';

    $sql_campaigns = "CREATE TABLE $campaigns_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL DEFAULT '',
        from_name VARCHAR(255) NOT NULL DEFAULT '',
        from_email VARCHAR(320) NOT NULL DEFAULT '',
        reply_to VARCHAR(320) NOT NULL DEFAULT '',
        html_content LONGTEXT NULL,
        plain_content LONGTEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        campaign_type VARCHAR(30) NOT NULL DEFAULT 'newsletter',
        list_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        scheduled_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY status (status),
        KEY campaign_type (campaign_type),
        KEY list_id (list_id),
        KEY scheduled_at (scheduled_at)
    ) $charset_collate;";


    /*
     * Campaign recipients table.
     */
    $campaign_recipients_table =
        $wpdb->prefix . 'em_campaign_recipients';

    $sql_campaign_recipients = "CREATE TABLE $campaign_recipients_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        queued_at DATETIME NULL,
        sent_at DATETIME NULL,
        failed_at DATETIME NULL,
        error_message TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY campaign_contact (campaign_id, contact_id),
        KEY campaign_id (campaign_id),
        KEY contact_id (contact_id),
        KEY status (status)
    ) $charset_collate;";


    /*
     * Email queue table.
     */
    $email_queue_table =
        $wpdb->prefix . 'em_email_queue';

    $sql_email_queue = "CREATE TABLE $email_queue_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        campaign_recipient_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        to_email VARCHAR(320) NOT NULL,
        to_name VARCHAR(255) NOT NULL DEFAULT '',
        subject VARCHAR(500) NOT NULL DEFAULT '',
        from_email VARCHAR(320) NOT NULL DEFAULT '',
        from_name VARCHAR(255) NOT NULL DEFAULT '',
        reply_to VARCHAR(320) NOT NULL DEFAULT '',
        html_content LONGTEXT NULL,
        plain_content LONGTEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        queued_at DATETIME NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY campaign_recipient_id (campaign_recipient_id),
        KEY contact_id (contact_id),
        KEY status (status),
        KEY queued_at (queued_at)
    ) $charset_collate;";


    /*
     * Create/update tables.
     */
    dbDelta($sql_campaigns);
    dbDelta($sql_campaign_recipients);
    dbDelta($sql_email_queue);


    /*
     * Update database version.
     */
    update_option(
        'cem_campaign_db_version',
        '1.0.0'
    );
}