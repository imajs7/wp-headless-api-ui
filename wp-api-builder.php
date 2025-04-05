<?php
/**
 * Plugin Name: WP API Builder
 * Plugin URI: https://wpnovel.com/
 * Description: A lightweight and powerful plugin to create custom REST API endpoints directly from the WordPress admin panel. Define endpoints, fields, filters, and access rules — no coding required.
 * Version: 0.1
 * Author: Anurag Jaisingh
 * Author URI: https://imajs7.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-api-builder
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

