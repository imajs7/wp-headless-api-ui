<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Headless API Builder',
        'API Builder',
        'manage_options',
        'wp-headless-api-ui',
        'wp_headless_api_ui_page',
        'dashicons-rest-api',
        100
    );

    add_submenu_page(
        'wp-headless-api-ui',
        'Update Plugin',
        'Update Plugin',
        'manage_options',
        'wp-headless-api-ui-update',
        'wp_headless_api_ui_update_page'
    );

});

function wp_headless_api_ui_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . "custom_api_endpoints";

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {
        $slug = sanitize_text_field($_POST['slug']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $fields_map = $_POST['fields_map'] ?? [];

        $fields = [];
        foreach ($fields_map as $original => $alias) {
            $sanitized_original = sanitize_key($original);
            $sanitized_alias = sanitize_key($alias ?: $original);
            $fields[] = "{$sanitized_original}:{$sanitized_alias}";
        }

        $wpdb->insert($table_name, [
            'slug' => $slug,
            'post_type' => $post_type,
            'fields' => json_encode($fields)
        ]);
        echo '<div class="notice notice-success"><p>Endpoint saved!</p></div>';
    }

    if (isset($_GET['delete']) && current_user_can('manage_options')) {
        $wpdb->delete($table_name, ['id' => intval($_GET['delete'])]);
        echo '<div class="notice notice-warning"><p>Endpoint deleted.</p></div>';
    }

    $endpoints = $wpdb->get_results("SELECT * FROM $table_name");
    $post_types = get_post_types(['public' => true], 'objects');
    ?>
    <div class="wrap">
        <?php
        $current_version = get_option('wp_headless_api_plugin_version', 'v1.00');
        ?>
        <h1>Headless API Endpoint Builder <span style='font-size:10px'><?php echo esc_html($current_version); ?></span></h1>
        <div id="plugin-update-notice"></div>


        <form method="post">
            <h2>Add New Endpoint</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="slug">Endpoint Slug</label></th>
                    <td><input name="slug" type="text" id="slug" required class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_type">Post Type</label></th>
                    <td>
                        <select name="post_type" id="post_type">
                            <?php foreach ($post_types as $pt): ?>
                                <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Core Fields</th>
                    <td>
                        <?php $core_fields = ['post_title', 'post_content', 'post_excerpt', 'post_date']; ?>
                        <?php foreach ($core_fields as $field): ?>
                            <div style="margin-bottom:5px;">
                                <label><input type="checkbox" class="field-selector" data-field="<?php echo $field; ?>"> <?php echo $field; ?></label>
                                → <input type="text" name="field_map[<?php echo $field; ?>]" placeholder="Alias (optional)">
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Suggested Meta Fields</th>
                    <td id="meta-suggestions"><em>Select post type to see suggestions...</em></td>
                </tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Endpoint</button></p>
        </form>

        <hr>
        <h2>Registered Endpoints</h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Post Type</th>
                    <th>Fields</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($endpoints as $ep): ?>
                    <tr>
                        <td><?php echo $ep->id; ?></td>
                        <td><code><?php echo esc_html($ep->slug); ?></code></td>
                        <td><?php echo esc_html($ep->post_type); ?></td>
                        <td>
                            <ul style="margin:0;padding-left:1em;">
                                <?php foreach (json_decode($ep->fields) as $map): ?>
                                    <?php [$original, $alias] = explode(':', $map); ?>
                                    <li><code><?php echo esc_html($original); ?></code> → <strong><?php echo esc_html($alias); ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td><a href="?page=wp-headless-api-ui&delete=<?php echo $ep->id; ?>" class="button button-secondary">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('wp_ajax_headless_api_get_meta_fields', function () {
    check_ajax_referer('headless_api_nonce');
    $post_type = sanitize_text_field($_POST['post_type']);
    $sample = get_posts(['post_type' => $post_type, 'numberposts' => 1]);

    if (empty($sample)) {
        wp_send_json_error('No posts found');
    }

    $meta = get_post_meta($sample[0]->ID);
    $meta_keys = array_keys($meta);
    wp_send_json_success($meta_keys);
});

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('wp-headless-api-ui-js', plugin_dir_url(__FILE__) . 'assets/admin-ui.js', ['jquery'], null, true);
    wp_localize_script('wp-headless-api-ui-js', 'HeadlessAPIData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('headless_api_nonce')
    ]);
});


// Load other components
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'plugin-update-check.php';
    require_once plugin_dir_path(__FILE__) . 'admin-ui-update.php';  
}
