<?php

function edd_bulk_lite_upload_generate_resized_images($original_file_path) {
    // Define the resized file paths
    $file_info = pathinfo($original_file_path);
    $base_dir = $file_info['dirname'];
    $base_name = $file_info['filename'];
    $extension = $file_info['extension'];

    // Ensure the extension is valid for Imagick operations
    $supported_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($extension), $supported_extensions)) {
      	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
        	edd_bulk_lite_debug_log("Unsupported file type for resizing: $extension");
        }
        return false;
    }

    $thumbnail_path = $base_dir . '/' . $base_name . '-thumbnail.' . $extension;
    $scaled_path = $base_dir . '/' . $base_name . '-scaled.' . $extension;

    try {
        // Initialize Imagick and load the original image
        $image = new Imagick($original_file_path);

        // Generate thumbnail (e.g., 150px wide, keep aspect ratio)
        $image->thumbnailImage(150, 0); // 150px wide, maintain aspect ratio
        $image->setImageFormat($extension); // Ensure format consistency
        $image->writeImage($thumbnail_path);


        // Reset the image and generate the scaled version
        $image->clear(); // Clear the previous operation
        $image->readImage($original_file_path);
        $image->resizeImage(1920, 1920, Imagick::FILTER_LANCZOS, 1, true); // 1920px max dimension
        $image->setImageFormat($extension); // Ensure format consistency
        $image->writeImage($scaled_path);

        $image->clear();
        $image->destroy();

        return [
            'thumbnail' => $thumbnail_path,
            'scaled'    => $scaled_path,
        ];
    } catch (Exception $e) {
      	if (get_option('edd_bulk_lite_uploader_php_debugging') === 'on') {
        	edd_bulk_lite_debug_log('Error generating resized images: ' . $e->getMessage());
        }
        return false;
    }
}