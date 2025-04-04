<?php
add_action('rest_api_init', function () {
    global $wpdb;
    $table_name = $wpdb->prefix . "custom_api_endpoints";
    $endpoints = $wpdb->get_results("SELECT * FROM $table_name WHERE slug IS NOT NULL");

    foreach ($endpoints as $ep) {
        register_rest_route('custom/v1', '/' . $ep->slug, [
            'methods' => 'GET',
            'callback' => function () use ($ep) {
                $field_lines = json_decode($ep->fields);
                $field_map = [];

                foreach ($field_lines as $line) {
                    // Safely split key and alias
                    [$key, $alias] = array_map('sanitize_key', explode(':', $line));
                    $field_map[$key] = $alias;
                }

                $args = [
                    'post_type' => $ep->post_type,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                ];
                
                // Basic filters
                if (isset($_GET['author'])) {
                    $args['author'] = intval($_GET['author']);
                }
                if (isset($_GET['category'])) {
                    $args['category_name'] = sanitize_title($_GET['category']);
                }
                if (isset($_GET['search'])) {
                    $args['s'] = sanitize_text_field($_GET['search']);
                }
                
                $posts = get_posts($args);
                

                return array_map(function ($post) use ($field_map) {
                    $data = [];
                    foreach ($field_map as $key => $alias) {
                        $data[$alias] = in_array($key, ['post_title', 'post_content', 'post_excerpt', 'post_date'])
                            ? get_post_field($key, $post->ID)
                            : get_post_meta($post->ID, $key, true);
                    }
                    return $data;
                }, $posts);
            },
            'permission_callback' => '__return_true'
        ]);
    }
});

