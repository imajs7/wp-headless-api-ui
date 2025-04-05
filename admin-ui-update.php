<?php

function wp_headless_api_ui_update_page() {
    if (isset($_POST['update_plugin_from_github']) && current_user_can('manage_options')) {
        $file_name = sanitize_file_name($_POST['plugin_zip_file']);
        $base_url = 'https://github.com/imajs7/wp-api-builder-update/releases/download/';
        $zip_url = $base_url . str_replace('.zip', '', $file_name) . '/' . $file_name;

        $plugin_dir = plugin_dir_path(__FILE__);
        $tmp_file = download_url($zip_url);

        if (is_wp_error($tmp_file)) {
            echo '<div class="notice notice-error"><p>Download failed: ' . $tmp_file->get_error_message() . '</p></div>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            WP_Filesystem();
            $unzip = unzip_file($tmp_file, $plugin_dir . '__update_temp');

            if (is_wp_error($unzip)) {
                echo '<div class="notice notice-error"><p>Unzip failed: ' . $unzip->get_error_message() . '</p></div>';
            } else {
                $fs = WP_Filesystem();
                $contents = glob($plugin_dir . '__update_temp/*');
                $update_folder = is_dir($contents[0]) ? $contents[0] : null;

                if ($update_folder) {
                    copy_dir($update_folder, $plugin_dir);

                    // Save new version (strip .zip)
                    $version = basename($file_name, '.zip');
                    update_option('wp_headless_api_plugin_version', $version);

                    echo '<div class="notice notice-success"><p>Plugin updated to <strong>' . esc_html($version) . '</strong> successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Update folder not found inside ZIP.</p></div>';
                }

                delete_folder($plugin_dir . '__update_temp');
            }

            @unlink($tmp_file);
        }
    }

    ?>
    <div class="wrap">
        <h1>Update Plugin to Specific Version</h1>
        <form method="post">
            <p>Enter the ZIP file name from the release (e.g. <code>v1.02.zip</code>)</p>
            <input type="text" name="plugin_zip_file" class="regular-text" required>
            <input type="hidden" name="update_plugin_from_github" value="1">
            <p style="margin-top: 10px;"><button type="submit" class="button button-primary">Update Plugin</button></p>
        </form>
    </div>
    <?php
}
