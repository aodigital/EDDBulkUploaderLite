<?php

function edd_bulk_lite_upload_edd_generate_csv_callback()
{
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])), 'edd_bulk_lite_upload')
    ) {
        wp_send_json_error('Invalid nonce');
    }

    if (!isset($_POST['files']) || !is_array($_POST['files'])) {
        wp_send_json_error('No files data received.');
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('No files data received.');
        }
    }

    $uploaded_files = isset($_POST['files']) ? (function($data){
        $data = wp_unslash($data);
        array_walk_recursive($data, function(&$value) {
            $value = sanitize_text_field($value);
        });
        return $data;
    })($_POST['files']) : [];


    $uploads_dir = wp_upload_dir();
    $csv_dir = $uploads_dir['basedir'] . '/bulk-edd-upload-import-files/';
    if (!file_exists($csv_dir) && !wp_mkdir_p($csv_dir)) {
        wp_send_json_error('Failed to create directory for CSV.');
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('Failed to create directory for CSV.');
        }
    }

    $csv_file = $csv_dir . 'edd_bulk_lite_import_' . gmdate('Ymd_His') . '.csv';

    global $wp_filesystem;
    if (!isset($wp_filesystem) || empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if (!$wp_filesystem || !$wp_filesystem->put_contents($csv_file, '', FS_CHMOD_FILE)) {
        wp_send_json_error('Failed to create CSV file.');
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('Failed to create CSV file at ' . $csv_file);
        }
    }

    // Create CSV content
    $csv_content = '';
    $header = ['post_title', 'image_url', 'download_url', 'download_tag', 'download_category', 'content', 'item_price'];
    $csv_content .= implode(',', array_map(function ($value) {
        return '"' . str_replace('"', '""', $value) . '"'; // Escape double quotes
    }, $header)) . "\n";

    foreach ($uploaded_files as $file) {
        $title = isset($file['title']) ? sanitize_text_field($file['title']) : '';
        $url = isset($file['upload_url']) ? esc_url_raw($file['upload_url']) : '';
        $download_url = isset($file['download_url']) ? esc_url_raw($file['download_url']) : '';
        $tags = isset($file['tags']) ? sanitize_text_field($file['tags']) : '';
        $category = isset($file['category']) ? sanitize_text_field($file['category']) : '';
        $content = isset($file['content']) ? sanitize_textarea_field($file['content']) : '';
        $price = isset($file['item_price']) ? sanitize_text_field($file['item_price']) : '';

        // Ensure tags are properly separated and sanitized
        $tags = implode(',', array_map('trim', explode(',', $tags)));

        // Add row to CSV content
        $row = [$title, $url, $download_url, $tags, $category, $content, $price];
        $csv_content .= implode(',', array_map(function ($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }

    // Write the CSV content to the file
    if (!$wp_filesystem->put_contents($csv_file, $csv_content, FS_CHMOD_FILE)) {
        wp_send_json_error('Failed to write to CSV file.');
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('Failed to write CSV file at ' . $csv_file);
        }
    }

    // Return CSV file path for download
    $csv_url = $uploads_dir['baseurl'] . '/bulk-edd-upload-import-files/' . basename($csv_file);
    wp_send_json_success(['csv_file' => $csv_url]);
}

add_action('wp_ajax_edd_bulk_lite_upload_generate_csv', 'edd_bulk_lite_upload_edd_generate_csv_callback');


function edd_bulk_lite_upload_edd_count_csv_rows_callback()
{
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])), 'edd_bulk_lite_upload')
    ) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }


    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to access this page.']);
    }

    // Get CSV file path from the request
    $csv_file_path = isset($_POST['csv_file_path']) ? sanitize_text_field(wp_unslash($_POST['csv_file_path'])) : '';

    // Validate the CSV file path
    if (empty($csv_file_path) || strpos($csv_file_path, wp_upload_dir()['baseurl']) === false) {
        wp_send_json_error(['message' => 'CSV file path is missing or invalid.']);
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('CSV file path is missing or invalid.');
        }
    }

    // Convert the URL to the file path
    $uploads_dir = wp_upload_dir();
    $csv_file_path = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], $csv_file_path);

    // Initialize the WordPress Filesystem API
    global $wp_filesystem;
    if (!isset($wp_filesystem) || empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if (!$wp_filesystem) {
        wp_send_json_error(['message' => 'Failed to initialize the WordPress Filesystem API.']);
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('Failed to initialize the WordPress Filesystem API.');
        }
    }

    // Check if the CSV file exists
    if (!$wp_filesystem->exists($csv_file_path)) {
        wp_send_json_error(['message' => 'CSV file does not exist at the specified path.']);
        if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
            edd_bulk_lite_debug_log('CSV file does not exist at the specified path.');
        }
    }

    // Open the CSV file and count rows
    $total_rows = 0;
    $csv_content = $wp_filesystem->get_contents($csv_file_path);

    if ($csv_content === false) {
        wp_send_json_error(['message' => 'Failed to read the CSV file.']);
    }

    $lines = explode("\n", $csv_content); // Split content into lines

    // Skip the header row
    if (!empty($lines)) {
        array_shift($lines); // Remove the first row (header row)
    }

    // Process each line
    foreach ($lines as $line) {
        // Parse each line as CSV
        $row = str_getcsv($line);

        // Skip blank rows
        if (!array_filter($row)) {
            continue;
        }

        // Increment total row count for valid rows
        $total_rows++;
    }

    // Return the total row count
    wp_send_json_success(['total_rows' => $total_rows]);
}

add_action('wp_ajax_edd_bulk_lite_upload_count_csv_rows', 'edd_bulk_lite_upload_edd_count_csv_rows_callback');
