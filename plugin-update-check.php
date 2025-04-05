<?php

function wp_headless_api_check_for_updates() {
    // Current plugin version stored in WordPress DB (fallback to v1.01)
    $current_version = get_option('wp_headless_api_plugin_version', 'v1.01');

    // URL to version.txt file
    $version_url = 'https://raw.githubusercontent.com/imajs7/wp-api-builder-update/main/version.txt';

    echo "gvhgvjgvjhvj";

    $response = wp_remote_get($version_url);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $lines = explode("\n", trim($body));
    $version_data = [];

    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            [$key, $value] = array_map('trim', explode('=', $line));
            $version_data[$key] = $value;
        }
    }

    $latest = $version_data['latest'] ?? '';
    $stable = $version_data['stable'] ?? '';
    $has_update = version_compare(str_replace('v', '', $current_version), str_replace('v', '', $latest), '<');

    return [
        'current' => $current_version,
        'latest' => $latest,
        'stable' => $stable,
        'has_update' => $has_update,
        'update_url' => 'admin.php?page=wp-headless-api-ui-update'
    ];
}

// ✅ AJAX endpoint for async update checking
add_action('wp_ajax_wp_headless_api_check_update', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $update_info = wp_headless_api_check_for_updates();
    if (!$update_info) {
        wp_send_json_error(['message' => 'Failed to check updates']);
    }

    wp_send_json_success($update_info);
});
