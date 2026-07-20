<?php

function edd_bulk_lite_upload_send_file_to_amazon_s3_callback() {
    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
    }

    // Verify nonce
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])),
            'edd_bulk_lite_upload'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    // Get the download URL and file name
    $download_url = isset($_POST['download_url']) ? esc_url_raw( wp_unslash($_POST['download_url']) ) : '';
    $file_name    = isset($_POST['file_name'])    ? sanitize_file_name( wp_unslash($_POST['file_name']) ) : '';
    $file_type    = isset($_POST['file_type'])    ? sanitize_file_name( wp_unslash($_POST['file_type']) ) : '';


    // Ensure the EDD Amazon S3 instance exists
    if (!function_exists('edd_amazon_s3') || !edd_amazon_s3()->has_api_keys()) {
        wp_send_json_error(['message' => 'Failed to load EDD Amazon S3 instance or API keys are missing.']);
    }

    // Download the file temporarily
    $temp_file = download_url($download_url);
    if (is_wp_error($temp_file)) {
        wp_send_json_error(['message' => 'Failed to download the file.', 'error' => $temp_file->get_error_message()]);
    }

    // Prepare the upload arguments
    $bucket_name = edd_get_option('edd_amazon_s3_bucket', '');
    if (empty($bucket_name)) {
        wp_delete_file($temp_file); // Clean up the temporary file
        wp_send_json_error(['message' => 'Amazon S3 bucket is not configured.']);
    }

    $args = [
        'file' => $temp_file, // Full file path
        'name' => $file_name, // Name to save as in the bucket
        'type' => wp_check_filetype($file_name)['type'], // MIME type
    ];

    try {
        // Upload the file to S3
        $upload_result = edd_amazon_s3()->upload_file($args);
        $s3_url = edd_amazon_s3()->get_s3_url($bucket_name . '/' . $file_name);

        // Cleanup temporary file
        wp_delete_file($temp_file);
      	
      	if (get_option('edd_bulk_lite_s3_remove_original_after_upload') === 'on') {
            // Proceed only if files are set to be removed
            if (file_exists($original_file_path)) {
                $fileType = isset($_POST['file_type']) ? sanitize_file_name( wp_unslash($_POST['file_type']) ) : '';
                if (strpos($fileType, 'video/') === 0) {
                    $new_file_path = $original_file_path;
                } elseif (strpos($fileType, 'image/') === 0) {
                    if (get_option('edd_bulk_lite_upload_enable_watermarking') === '1') {
                        wp_delete_file($original_file_path);
                        $path_info = pathinfo($original_file_path);
                        $directory = $path_info['dirname'];
                        $filename = $path_info['filename'];
                        $extension = $path_info['extension'];

                        // Glob pattern to find all matching variations except '-scaled-watermarked'
                        $pattern = $directory . '/' . $filename . '-*.' . $extension;

                        foreach (glob($pattern) as $file) {
                            if (!str_contains($file, '-scaled-watermarked') && !str_contains($file, '-thumbnail')) {
                                wp_delete_file($file); // Delete the file
                                if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                                    edd_bulk_lite_debug_log("Deleted variation: " . $file);
                                }
                            } else {
                                $new_file_path = $file; // Keep the '-scaled-watermarked' file
                            }
                        }
                    } else {
                        $new_file_path = $original_file_path; // Retain original if watermarking is disabled
                    }
                }

                if ($new_file_path) {
                    // Update the Media Library only if the file path changes
                    $attachment_id = attachment_url_to_postid($download_url); // Get the attachment ID from the URL
                    if ($attachment_id) {
                        // Update the database with the new file path
                        update_attached_file($attachment_id, $new_file_path);

                        // Regenerate metadata for the attachment
                        $metadata = wp_generate_attachment_metadata($attachment_id, $new_file_path);
                        wp_update_attachment_metadata($attachment_id, $metadata);

                        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                            edd_bulk_lite_debug_log("Media Library updated for attachment ID: {$attachment_id}, New file path: {$new_file_path}");
                        }
                    } else {
                        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                            edd_bulk_lite_debug_log("Failed to locate attachment ID for URL: {$download_url}");
                        }
                    }
                }

                if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                    edd_bulk_lite_debug_log("Original file deleted: " . $original_file_path);
                }
            } else {
                if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                    edd_bulk_lite_debug_log("Original file not found for deletion: " . $original_file_path);
                }
            }
        } else {
            // Debugging message: File deletion is disabled
            if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                edd_bulk_lite_debug_log("File deletion is disabled. No changes to Media Library.");
            }
        }


        // Return the new S3 URL
        wp_send_json_success(['message' => 'File uploaded successfully.', 's3_url' => $s3_url]);
    } catch (Exception $e) {
        wp_delete_file($temp_file); // Clean up the temporary file
      	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
        	edd_bulk_lite_debug_log('Error uploading to S3: ' . $e->getMessage());
        }
        wp_send_json_error(['message' => 'Failed to upload the file.', 'error' => $e->getMessage()]);
    }
}

add_action('wp_ajax_edd_bulk_lite_upload_send_file_to_amazon_s3', 'edd_bulk_lite_upload_send_file_to_amazon_s3_callback');