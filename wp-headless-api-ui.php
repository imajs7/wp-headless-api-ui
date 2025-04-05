<?php
/**
 * Plugin Name: WP Headless API
 * Description: Minimal plugin to register UI-defined REST API endpoints from the database.
 * Version: 0.1
 * Author: Anurag Jaisingh
 */

defined('ABSPATH') || exit;


// Create database table on plugin activation
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table_name = $wpdb->prefix . "custom_api_endpoints";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        slug varchar(255) NOT NULL,
        post_type varchar(100) NOT NULL,
        fields text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

require_once plugin_dir_path(__FILE__) . 'routes/dynamic.php';
require_once plugin_dir_path(__FILE__) . 'admin-ui.php';

