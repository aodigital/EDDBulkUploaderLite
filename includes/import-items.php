<?php

function edd_bulk_lite_upload_confirm_import_callback()
{
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])), 'edd_bulk_lite_upload')
    ) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }


    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to access this page.']);
    }

    $csv_file_path = isset($_POST['csv_file_path']) ? sanitize_text_field(wp_unslash($_POST['csv_file_path'])) : '';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $chunk_size = isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 5;

    if (empty($csv_file_path) || strpos($csv_file_path, wp_upload_dir()['baseurl']) === false) {
        wp_send_json_error(['message' => 'CSV file path is missing or invalid.']);
    }

    $uploads_dir = wp_upload_dir();
    $csv_file_path = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $csv_file_path);

    if (!file_exists($csv_file_path)) {
        wp_send_json_error(['message' => 'CSV file does not exist at the specified path.']);
    }

    $batch_id = get_option('current_batch_id', 'edd_bulk_lite_' . time());
    if (!get_option('current_batch_id')) {
        update_option('current_batch_id', $batch_id);
    }

    $rows = [];
    $total_rows = 0;
    global $wp_filesystem;
    if (!isset($wp_filesystem) || empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    if ($wp_filesystem->exists($csv_file_path)) {
        // Read the file content
        $csv_content = $wp_filesystem->get_contents($csv_file_path);
        if ($csv_content === false) {
            wp_send_json_error(['message' => 'Failed to read the CSV file.']);
        }

        $lines = explode("\n", $csv_content); // Split content into lines
        $headers = [];
        $rows = [];
        $total_rows = 0;
        $current_row = 0;

        if (!empty($lines)) {
            // Get headers (first line)
            $headers = str_getcsv(array_shift($lines));

            // Calculate total rows (excluding blank rows)
            foreach ($lines as $line) {
                $row = str_getcsv($line);
                if (!array_filter($row)) {
                    continue; // Skip blank rows
                }
                $total_rows++;
            }

            // Rewind equivalent: reset the line array
            $lines = explode("\n", $csv_content);
            array_shift($lines); // Skip the header row again

            // Process the lines with an offset and chunk size
            foreach ($lines as $line) {
                $data = str_getcsv($line);

                if ($current_row < $offset) {
                    $current_row++;
                    continue;
                }

                if (!array_filter($data)) {
                    continue; // Skip blank rows
                }

                $rows[] = array_combine($headers, $data);
                $current_row++;

                if (count($rows) >= $chunk_size) {
                    break; // Stop when the chunk size is reached
                }
            }
        }
    } else {
        wp_send_json_error(['message' => 'The CSV file does not exist.']);
    }


    if (empty($rows)) {
        // Clear the batch ID when done
        delete_option('current_batch_id');
        wp_send_json_success([
            'completed' => true,
            'redirect_url' => admin_url('edit.php?post_type=download&orderby=date&order=desc&batch_id=' . $batch_id)
        ]);
    }

    // Process each row
    foreach ($rows as $post_data) {
        try {
            $new_download = [
                'post_title'   => sanitize_text_field($post_data['post_title']),
                'post_content' => sanitize_textarea_field($post_data['content']),
                'post_status'  => 'publish',
                'post_type'    => 'download',
                'post_name'    => sanitize_title_with_dashes(str_replace('.', '-', $post_data['post_title'])),
            ];

            $download_id = wp_insert_post($new_download);

            if (!is_wp_error($download_id)) {
                // Set the featured image
                $image_url = esc_url_raw($post_data['image_url']);
                if (!empty($image_url)) {
                    $attachment_id = media_sideload_image($image_url, $download_id, null, 'id');
                    if (!is_wp_error($attachment_id)) {
                        set_post_thumbnail($download_id, $attachment_id);
                        $command = sprintf('wp media regenerate %d --yes', intval($attachment_id)); 
                        $output = shell_exec($command); 

                        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                            edd_bulk_lite_debug_log("Thumbnail regeneration output for attachment ID {$attachment_id}: {$output}");
                        }
                    }
                }

                if (!empty($post_data['download_tag'])) {
                    $tags = array_map('sanitize_text_field', explode(',', $post_data['download_tag']));
                    wp_set_object_terms($download_id, $tags, 'download_tag');
                }
                if (!empty($post_data['download_category'])) {
                    $categories = array_map('sanitize_text_field', explode(',', $post_data['download_category']));
                    foreach ($categories as $category) {
                        $category_parts = explode('/', $category);
                        $parent = 0; // Default to no parent

                        foreach ($category_parts as $part) {
                            $term = term_exists($part, 'download_category', $parent);
                            if (!$term) {
                                $term = wp_insert_term($part, 'download_category', ['parent' => $parent]);
                            }

                            if (!is_wp_error($term)) {
                                $parent = $term['term_id'];
                            }
                        }

                        // Assign the lowest-level term (the child) to the download
                        if ($parent > 0) {
                            wp_set_object_terms($download_id, (int) $parent, 'download_category', true);
                        }
                    }
                }


                $price = isset($post_data['item_price']) ? floatval($post_data['item_price']) : 110;
                update_post_meta($download_id, 'edd_price', $price);

                $download_url = isset($post_data['download_url']) ? esc_url_raw($post_data['download_url']) : '';
                if (!empty($download_url)) {
                    $files = edd_get_download_files($download_id);
                    $files[] = [
                        'name' => basename($download_url),
                        'file' => $download_url,
                    ];
                    update_post_meta($download_id, 'edd_download_files', $files);
                }

                update_post_meta($download_id, 'edd_bulk_lite_upload_batch', $batch_id);
            }
        } catch (Exception $e) {
          	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            	edd_bulk_lite_debug_log('Error processing row: ' . $e->getMessage());
            }
            continue;
        }
    }

    $remaining_rows = max(0, $total_rows - $offset - count($rows));
    $completed = $remaining_rows <= 0;

    wp_send_json_success([
        'offset'       => $offset + $chunk_size,
        'remaining'    => $remaining_rows,
        'completed'    => $completed,
        'total_rows'   => $total_rows, 
        'redirect_url' => $completed ? admin_url('edit.php?post_type=download&orderby=date&order=desc&batch_id=' . $batch_id) : '',
    ]);
}

add_action('wp_ajax_edd_bulk_lite_upload_confirm_import', 'edd_bulk_lite_upload_confirm_import_callback');