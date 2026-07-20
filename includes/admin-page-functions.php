<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Option Saving Callbacks

function edd_bulk_lite_upload_save_s3_storage_toggle_callback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You are not authorized to perform this action.']);
    }
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])),
            'edd_bulk_lite_upload'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    $enable_s3_storage = isset($_POST['enable_s3_storage']) && $_POST['enable_s3_storage'] === 'on' ? 'on' : 'off';
    update_option('edd_bulk_lite_enable_s3_storage', $enable_s3_storage);
    if ($enable_s3_storage === 'off') {
        update_option('edd_bulk_lite_s3_remove_original_after_upload', 'off');
    }
    wp_send_json_success(['message' => 'S3 Storage Setting updated to ' . $enable_s3_storage . '.']);
}
add_action('wp_ajax_edd_bulk_lite_upload_save_s3_storage_toggle', 'edd_bulk_lite_upload_save_s3_storage_toggle_callback');

function edd_bulk_lite_upload_save_remove_original_toggle_callback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You are not authorized to perform this action.']);
    }
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])),
            'edd_bulk_lite_upload'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    $remove_original_after_upload = isset($_POST['remove_original_after_upload']) && $_POST['remove_original_after_upload'] === 'on' ? 'on' : 'off';	
  	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
        edd_bulk_lite_debug_log("Received value for remove_original_after_upload: " . $remove_original_after_upload);
    }  	    
    update_option('edd_bulk_lite_s3_remove_original_after_upload', $remove_original_after_upload);
	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
        edd_bulk_lite_debug_log("Updated value in database: " . get_option('edd_bulk_lite_s3_remove_original_after_upload'));
    }    
    wp_send_json_success(['message' => 'Remove Original File setting updated to ' . $remove_original_after_upload . '.']);
}
add_action('wp_ajax_edd_bulk_lite_upload_save_remove_original_toggle', 'edd_bulk_lite_upload_save_remove_original_toggle_callback');

function edd_bulk_lite_upload_update_php_debugging_toggle_callback() {
    // Verify nonce first
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])),
            'edd_bulk_lite_upload'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    // Process form data after nonce verification
    if (isset($_POST['php_debugging'])) {
        $php_debugging = sanitize_text_field(wp_unslash($_POST['php_debugging']));
        update_option('edd_bulk_lite_uploader_php_debugging', $php_debugging);
        wp_send_json_success(['message' => 'PHP Debugging updated to ' . $php_debugging . '.']);
    } else {
        wp_send_json_error('Missing value.');
    }
}
add_action('wp_ajax_edd_bulk_lite_upload_update_php_debugging_toggle', 'edd_bulk_lite_upload_update_php_debugging_toggle_callback');


function edd_bulk_lite_upload_update_js_debugging_toggle_callback() {
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])),
            'edd_bulk_lite_upload'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    if (isset($_POST['js_debugging'])) {
        $js_debugging = sanitize_text_field(wp_unslash($_POST['js_debugging']));
        update_option('edd_bulk_lite_uploader_js_debugging', $js_debugging);
        wp_send_json_success(['message' => 'JS Debugging updated to ' . $js_debugging . '.']);
    } else {
        wp_send_json_error('Missing value.');
    }
}
add_action('wp_ajax_edd_bulk_lite_upload_update_js_debugging_toggle', 'edd_bulk_lite_upload_update_js_debugging_toggle_callback');

function edd_bulk_lite_debug_log($value) {
    // Define the path to the log file
    $log_file = WP_CONTENT_DIR . '/edd_bulk_debug.log';
    
    // Prepare the log entry
    $output = is_scalar($value) ? $value : wp_json_encode($value);
    $log_entry = "[" . gmdate('Y-m-d H:i:s') . "] " . $output . PHP_EOL;
    
    // Access the global WP_Filesystem object
    global $wp_filesystem;
    
    // Initialize WP_Filesystem if it's not already initialized
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    // Proceed only if WP_Filesystem is available
    if ($wp_filesystem) {
        // Check if the log file exists
        if ($wp_filesystem->exists($log_file)) {
            // Retrieve current contents of the log file
            $current_contents = $wp_filesystem->get_contents($log_file);
            
            // Handle potential errors during retrieval
            if ($current_contents === false) {
                error_log("Failed to read log file: {$log_file}");
                $current_contents = '';
            }
        } else {
            // If the file doesn't exist, start with an empty string
            $current_contents = '';
        }
        
        // Append the new log entry to the existing contents
        $updated_contents = $current_contents . $log_entry;
        
        // Write the updated contents back to the log file
        $write_result = $wp_filesystem->put_contents(
            $log_file,
            $updated_contents,
            FS_CHMOD_FILE // Set appropriate file permissions
        );
        
        // Handle potential errors during writing
        if ($write_result === false) {
            error_log("Failed to write to log file: {$log_file}");
        }
    } else {
        // Fallback error logging if WP_Filesystem isn't initialized
        error_log("WP_Filesystem not initialized. Cannot write to log file: {$log_file}");
    }
}

