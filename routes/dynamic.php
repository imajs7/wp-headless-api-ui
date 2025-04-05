<?php

function wp_headless_api_require_basic_auth() {
    if (!is_user_logged_in()) {
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $auth = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $auth = $headers['authorization'];
            }

            if (!empty($auth) && preg_match('/Basic\\s+(.*)$/i', $auth, $matches)) {
                list($user, $pass) = explode(':', base64_decode($matches[1]), 2);
                $_SERVER['PHP_AUTH_USER'] = $user;
                $_SERVER['PHP_AUTH_PW'] = $pass;
            }
        }

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            return new WP_Error('unauthorized', 'Authorization header missing.', ['status' => 401]);
        }

        $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

        if (is_wp_error($user)) {
            return new WP_Error('unauthorized', 'Invalid credentials.', ['status' => 401]);
        }

        wp_set_current_user($user->ID);
    }

    return true;
}

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
                    [$key, $alias] = array_map('sanitize_key', explode(':', $line));
                    $field_map[$key] = $alias;
                }

                $args = [
                    'post_type' => $ep->post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => isset($_GET['per_page']) ? absint($_GET['per_page']) : 10,
                    'paged' => isset($_GET['page']) ? absint($_GET['page']) : 1,
                ];

                if (isset($_GET['orderby'])) {
                    $args['orderby'] = sanitize_text_field($_GET['orderby']);
                }

                if (isset($_GET['order'])) {
                    $args['order'] = strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
                }

                if (isset($_GET['author'])) {
                    $args['author'] = absint($_GET['author']);
                }

                if (isset($_GET['search'])) {
                    $args['s'] = sanitize_text_field($_GET['search']);
                }

                if (isset($_GET['category'])) {
                    $args['category_name'] = sanitize_title($_GET['category']);
                }

                // Dynamic taxonomy filters
                $taxonomies = get_object_taxonomies($ep->post_type, 'names');
                foreach ($taxonomies as $taxonomy) {
                    if (isset($_GET[$taxonomy])) {
                        $args['tax_query'][] = [
                            'taxonomy' => $taxonomy,
                            'field' => 'slug',
                            'terms' => sanitize_text_field($_GET[$taxonomy])
                        ];
                    }
                }

                $query = new WP_Query($args);
                $posts = $query->posts;
                $total = $query->found_posts;

                $results = array_map(function ($post) use ($field_map, $taxonomies) {
                    $data = [];

                    foreach ($field_map as $key => $alias) {
                        if (in_array($key, ['post_title', 'post_content', 'post_excerpt', 'post_date'])) {
                            $value = get_post_field($key, $post->ID);
                        } elseif (function_exists('get_field')) {
                            $acf_value = get_field($key, $post->ID);

                            if (is_array($acf_value)) {
                                // ACF image or file
                                if (isset($acf_value['url'])) {
                                    $value = $acf_value['url'];
                                }
                                // Repeater or multi-value
                                elseif (array_keys($acf_value) === range(0, count($acf_value) - 1)) {
                                    $value = array_map(function ($item) {
                                        if (is_array($item) && isset($item['url'])) {
                                            return $item['url'];
                                        }
                                        return $item;
                                    }, $acf_value);
                                }
                                // Flexible content or object
                                else {
                                    $value = $acf_value;
                                }
                            } else {
                                $value = $acf_value;
                            }
                        } else {
                            $value = get_post_meta($post->ID, $key, true);
                        }

                        $data[$alias] = $value !== '' ? $value : null;
                    }

                    foreach ($taxonomies as $taxonomy) {
                        $terms = get_the_terms($post->ID, $taxonomy);
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $data[$taxonomy] = array_map(function ($term) {
                                return $term->slug;
                            }, $terms);
                        }
                    }

                    return $data;
                }, $posts);

                return [
                    'total' => $total,
                    'page' => $args['paged'],
                    'per_page' => $args['posts_per_page'],
                    'total_pages' => ceil($total / $args['posts_per_page']),
                    'data' => $results
                ];
            },
            // 'permission_callback' => '__return_true'
            // For production use:
            'permission_callback' => 'wp_headless_api_require_basic_auth'
        ]);
    }
});
