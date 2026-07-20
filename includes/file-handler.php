<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function edd_bulk_lite_upload_handle_file_uploads_callback()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You are not authorized to perform this action.']);
    }
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])), 'edd_bulk_lite_upload')
    ) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $uploaded_files = (isset($_FILES['edd_files']) && is_array($_FILES['edd_files']))
    ? (function($f) {
        array_walk_recursive($f, function(&$v) {
            $v = sanitize_text_field($v);
        });
        return $f;
    })(wp_unslash($_FILES['edd_files']))
    : [];



    $file_urls = [];

    foreach ($uploaded_files['name'] as $key => $filename) {
        $file = [
            'name'     => $uploaded_files['name'][$key],
            'type'     => $uploaded_files['type'][$key],
            'tmp_name' => $uploaded_files['tmp_name'][$key],
            'error'    => $uploaded_files['error'][$key],
            'size'     => $uploaded_files['size'][$key],
        ];

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['url']) && !isset($upload['error'])) {
            $file_path = $upload['file'];
            $attachment = [
                'guid'           => $upload['url'],
                'post_mime_type' => $upload['type'],
                'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file_path)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $attachment_id = wp_insert_attachment($attachment, $file_path);

            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $attach_data);
                $attachment_url = wp_get_attachment_url($attachment_id);

                if ($attachment_url) {
                    $resized_images = edd_bulk_lite_upload_generate_resized_images($file_path);

                    $thumbnail_url = $resized_images['thumbnail'] ?? '';
                    $scaled_url = $resized_images['scaled'] ?? $attachment_url; // Default to original if scaling fails
                    $file_content = '';
                    if (strpos($file['type'], 'video') !== false) {
                        $file_size_mb = round(filesize($file_path) / 1048576, 2); // File size in MB
                        $file_content = "[stockvideoembed src='{$scaled_url}' length='{VIDEO_LENGTH}' size='{$file_size_mb}' res='1920x1080']";
                    }

                    $file_urls[] = [
                        'original' => $attachment_url, 
                        'thumbnail' => $thumbnail_url,
                        'scaled' => $scaled_url,
                        'title' => sanitize_text_field(preg_replace('/\.[^.]+$/', '', basename($file_path))),
                        'content' => $file_content,
                    ];
                } else {
                    wp_send_json_error('Failed to get the attachment URL.');
                  	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                        edd_bulk_lite_debug_log('Failed to get the attachment URL.');
                    }
                }
            } else {
                wp_send_json_error('Failed to add file to Media Library.');
              	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                    edd_bulk_lite_debug_log('Failed to add file to Media Library.');
                }
            }
        } else {
            wp_send_json_error('File upload failed: ' . $upload['error']);
          	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
                edd_bulk_lite_debug_log('File upload failed: ' . $upload['error']);
            }
        }
    }

    wp_send_json_success(['files' => $file_urls]);
}
add_action('wp_ajax_edd_bulk_lite_upload_handle_file_uploads', 'edd_bulk_lite_upload_handle_file_uploads_callback');

function edd_bulk_lite_upload_delete_file() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You are not authorized to perform this action.']);
    }
    if (
        !isset($_POST['edd_bulk_lite_upload_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_upload_nonce'])), 'edd_bulk_lite_upload')
    ) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }
    
    if (!isset($_POST['file_path'])) {
        wp_send_json_error(['message' => 'No file path provided.']);
    }

    $file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';
    $absolute_path = ABSPATH . ltrim($file_path, '/'); // Normalize the file path

    if (!file_exists($absolute_path)) {
        wp_send_json_error(['message' => 'File not found.']);
    }

    $meta_value = str_replace(WP_CONTENT_DIR . '/', '', $absolute_path);
    $args = [
        'post_type'   => 'attachment',
        'meta_query'  => [
            [
                'key'   => '_wp_attached_file',
                'value' => $meta_value,
            ],
        ],
        'fields'      => 'ids', // Only return IDs
        'numberposts' => 1,     // We only need one result
    ];
    $attachment_ids = get_posts($args);

    $attachment_id = !empty($attachment_ids) ? $attachment_ids[0] : null;


    $dir = pathinfo($absolute_path, PATHINFO_DIRNAME);
    $basename = pathinfo($absolute_path, PATHINFO_FILENAME);
    $ext = pathinfo($absolute_path, PATHINFO_EXTENSION);

    foreach (glob("{$dir}/{$basename}*.$ext") as $file) {
        if (!wp_delete_file($file)) {
            wp_send_json_error(['message' => "Failed to delete file: {$file}."]);
        }
    }

    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }

    wp_send_json_success(['message' => 'File and associated data deleted successfully.']);
}
add_action('wp_ajax_edd_bulk_lite_upload_delete_file', 'edd_bulk_lite_upload_delete_file');


?>
