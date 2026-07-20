<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function edd_bulk_lite_upload_admin_page() {
    // Add the main menu item
    add_menu_page(
        'EDD Bulk Uploader & Importer', // Page title
        'EDD Bulk Uploader & Importer', // Menu title
        'manage_options', // Capability
        'edd-bulk-uploader', // Menu slug
        'edd_bulk_lite_upload_page_content', // Callback function
        'dashicons-upload', // Icon
        58 // Position
    );

    // Add the first submenu item (Dashboard)
    add_submenu_page(
        'edd-bulk-uploader',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'edd-bulk-uploader', 
        'edd_bulk_lite_upload_page_content'
    );
}
add_action('admin_menu', 'edd_bulk_lite_upload_admin_page');

function edd_bulk_lite_upload_page_content() {
    if (
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['edd_bulk_lite_form_submit']) &&
        isset($_POST['edd_bulk_lite_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edd_bulk_lite_nonce'])), 'edd_bulk_lite_upload')
    ) {
        $message = '';
        $error = false;

        // Sanitize and validate input
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $radio = sanitize_text_field(wp_unslash($_POST['radio_select'] ?? ''));
        $message_content = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        if (!$name || !$subject || !$email || !$radio || !$message_content) {
            $message = "All required fields must be filled out.";
            $error = true;
        } elseif (!is_email($email)) {
            $message = "Please provide a valid email address.";
            $error = true;
        } elseif (!in_array($radio, ['Support Request', 'Feedback & Suggestions', 'Bug Report', 'Other'])) {
            $message = "Invalid selection for the type.";
            $error = true;
        }

        // Handle file upload
        $uploaded_file = isset($_FILES['file_upload']) ? array_map('sanitize_text_field', wp_unslash($_FILES['file_upload'])) : null;

        $file_path = '';

        if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'video/mp4', 'text/plain', 'application/pdf'];
            $file_type = mime_content_type($uploaded_file['tmp_name']);

            // Validate file type before moving the file
            if (!in_array($file_type, $allowed_types)) {
                $message = "Unsupported file type. Please upload a supported file.";
                $error = true;
            } else {
                // Preserve the original filename and move to uploads directory
                $original_filename = basename($uploaded_file['name']);
                $upload_dir = wp_upload_dir();
                $destination = trailingslashit($upload_dir['path']) . $original_filename;

                if (!function_exists('wp_handle_upload')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }

                $upload_overrides = ['test_form' => false]; // Disable form field validation as it's not a regular form upload.
                $uploaded_file_data = wp_handle_upload($uploaded_file, $upload_overrides);

                if (isset($uploaded_file_data['file'])) {
                    $file_path = $uploaded_file_data['file']; // Full path to the uploaded file.
                } else {
                    $message = "Failed to process the uploaded file: " . $uploaded_file_data['error'];
                    $error = true;
                }

            }
        }

        // Send email if no errors
        if (!$error) {
            $to = 'hello@aodigital.com.au';
            $subject_line = "EDD Bulk Uploader Free ({$radio}) from " . site_url();
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $body = "<h3>Details</h3>
                     <p><strong>Name:</strong> {$name}</p>
                     <p><strong>Email:</strong> {$email}</p>
                     <p><strong>Subject:</strong> {$subject}</p>
                     <p><strong>Type:</strong> {$radio}</p>
                     <p><strong>Message:</strong><br>" . nl2br($message_content) . "</p>";

            // Add attachment if exists
            $attachments = $file_path ? [$file_path] : [];

            if (wp_mail($to, $subject_line, $body, $headers, $attachments)) {
                $message = "Message sent successfully, I'll be in touch ASAP if required.";
            } else {
                $message = "Message not sent. Check your WordPress SMTP settings as there may be a problem.";
                $error = true;
            }

            // Delete uploaded file if it exists
            if ($file_path && file_exists($file_path)) {
                wp_delete_file($file_path);
            }
        }
    } 




    
    ?>
    <div class="edd-bulk-intro-container">
        <h1 class="edd-bulk-intro-heading">Bulk Uploader & Importer for Easy Digital Downloads Lite</h1>
        <p class="edd-bulk-intro-text">
            This lite version of the Bulk Uploader & Importer for Easy Digital Downloads is designed to streamline the process of managing digital products. With powerful tools for bulk uploading images and videos, it allows you to efficiently add or remove tags and categories, update pricing, and process bulk imports to create new download items in your store. 
        </p>
        <p class="edd-bulk-intro-text">
            The plugin also integrates seamlessly with the Amazon S3 extension for Easy Digital Downloads, enabling you to upload digital products directly to your S3 bucket for secure storage and management.
        </p>
        <p class="edd-bulk-intro-text">
            For users looking to unlock advanced features, the premium version is available for purchase at <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">this link</a>. The premium version includes capabilities such as image renaming, automatic tag extraction from metadata, watermarking, and AI-powered content creation tools using external APIs like Google Vision and OpenAI, with even more features planned for future updates.
        </p>

        <div class="edd-bulk-intro-buttons">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=download')); ?>" class="edd-bulk-button">View My Download Items</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bulk-upload-image-video')); ?>" class="edd-bulk-button">Bulk Upload Images & Video</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bulk-upload-options')); ?>" class="edd-bulk-button">Update Options</a>
        </div>
    </div>


    <div class="halfWidthColLeft">
    <div class="edd-plugin-sound-check">
        <p>
            Unlock the full potential of your workflow with the premium version! Gain access to advanced features like automated tag generation, AI-powered title and description creation, image watermarking, metadata extraction, and more. Don't miss out on the tools that can save you time and elevate your digital product management. 
            <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank" style="font-weight: bold; color: #0073aa;">Upgrade to the premium version today</a> and take your productivity to the next level!
        </p>
    </div>
    <div class="edd-plugin-sound-check">
    <h2 class="edd-plugin-sound-check-heading">Plugin Sound Check</h2>
    <p class="edd-plugin-sound-check-description">
        Some of the features in this plugin use extensions or tools that might not be available in many hosting environments, particularly shared hosting.
        To get the best experience, you should be running on a VPS or an environment that allows you to install anything that may be missing.
    </p>

        <div class="edd-sound-check-list">
            <?php
            // Helper function to generate a check result
            function generate_check($condition, $success_message, $failure_message) {
                $icon = $condition ? '&#10003;' : '&#10007;';
                $status_class = $condition ? 'success' : 'fail';
                $message = $condition ? $success_message : $failure_message;

                return "<div class='edd-sound-check-item $status_class'>
                            <span class='check-icon'>$icon</span> $message
                        </div>";
            }

            // PHP version check
            $php_version = PHP_VERSION;
            echo wp_kses(
                generate_check(
                    version_compare($php_version, '8.0', '>='),
                    "PHP version $php_version is compatible.",
                    "PHP version $php_version detected. This plugin requires PHP 8 or higher. It might not work reliably on older versions."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                    // You can expand the allowed tags and attributes as needed.
                )
            );


            // Memory limit check
            $memory_limit = ini_get('memory_limit');
            $memory_limit_bytes = intval($memory_limit) * (stripos($memory_limit, 'G') ? 1024 ** 3 : (stripos($memory_limit, 'M') ? 1024 ** 2 : 1));

            echo wp_kses(
                generate_check(
                    $memory_limit_bytes >= 256 * 1024 * 1024,
                    "Memory limit of $memory_limit is sufficient. Minimum 256M+.",
                    "Memory limit of $memory_limit is insufficient. Minimum 256M+. Large jobs may fail."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                )
            );


            // post_max_size check
            $post_max_size = ini_get('post_max_size');
            $post_max_size_bytes = intval($post_max_size) * (stripos($post_max_size, 'G') ? 1024 ** 3 : (stripos($post_max_size, 'M') ? 1024 ** 2 : 1));

            echo wp_kses(
                generate_check(
                    $post_max_size_bytes >= 128 * 1024 * 1024,
                    "post_max_size of $post_max_size is sufficient. Minimum 128M+.",
                    "post_max_size of $post_max_size is insufficient. Minimum 128M+. Large jobs may fail."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                )
            );


            // upload_max_filesize check
            $upload_max_filesize = ini_get('upload_max_filesize');
            $upload_max_filesize_bytes = intval($upload_max_filesize) * (stripos($upload_max_filesize, 'G') ? 1024 ** 3 : (stripos($upload_max_filesize, 'M') ? 1024 ** 2 : 1));

            echo wp_kses(
                generate_check(
                    $upload_max_filesize_bytes >= 64 * 1024 * 1024,
                    "upload_max_filesize of $upload_max_filesize is sufficient.",
                    "upload_max_filesize of $upload_max_filesize is insufficient. Recommend at least 64M."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                )
            );


            // max_execution_time check
            $max_execution_time = ini_get('max_execution_time');

            echo wp_kses(
                generate_check(
                    $max_execution_time >= 300,
                    "max_execution_time of $max_execution_time seconds is sufficient. Increase if needed for large batches.",
                    "max_execution_time of $max_execution_time seconds is insufficient. Recommended 300+ seconds. Large jobs may fail."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                )
            );


            // PHP Imagick extension check
            $php_imagick_installed = extension_loaded('imagick');

            echo wp_kses(
                generate_check(
                    $php_imagick_installed,
                    "PHP Imagick extension is available. Used for image crunching.",
                    "PHP Imagick extension is not available. Image scaling may not work."
                ),
                array(
                    'div' => array('class' => array()),
                    'span' => array('class' => array()),
                )
            );


            ?>
        </div>
    </div>



    </div>
    <div class="halfWidthColRight">
        
        
        <form method="post" enctype="multipart/form-data" action="">
            <?php if (!empty($message)) {
                echo '<div style="margin-bottom: 20px; color: ' . ($error ? 'red' : 'green') . ';">' . esc_html($message) . '</div>';
            }?>
            <h3 class="eddBulkUploadH3" style="max-width: 600px;">Need help?  Get in contact</h3>
            <p>If you need help with anything, want to submit a bug report, suggestions, feedback or a picture or your cat.  You can use the form below.  Just make sure your website is set up to send emails</p>
            <div class="name-email-row">
                <p>
                    <input type="hidden" name="edd_bulk_lite_nonce" value="<?php echo esc_attr(wp_create_nonce('edd_bulk_lite_upload')); ?>">
                    <label for="name">Name (required):</label><br>

                    <input type="text" id="name" name="name" required>
                </p>
                <p>
                    <label for="email">Email (required):</label><br>
                    <input type="email" id="email" name="email" required>
                </p>
            </div>
            <p>
                <label for="subject">Subject (required):</label><br>
                <input type="text" id="subject" name="subject" required>
            </p>
            <p>
                <label>Type (required):</label><br>
                <label><input type="radio" name="radio_select" value="Support Request" required> Support Request</label>
                <label><input type="radio" name="radio_select" value="Feedback & Suggestions" required> Feedback & Suggestions</label>
                <label><input type="radio" name="radio_select" value="Bug Report" required> Bug Report</label>
                <label><input type="radio" name="radio_select" value="Other" required> Other</label>
            </p>
            <p>
                <label for="message">Message (required):</label><br>
                <textarea id="message" name="message" required></textarea>
            </p>
            <p>
                <label for="file_upload">File Upload (optional):</label><br>
                <input type="file" id="file_upload" name="file_upload" accept=".jpg,.jpeg,.png,.gif,.mp4,.txt,.pdf">
            </p>
            <p>
                <button type="submit" name="edd_bulk_lite_form_submit">Submit</button>
            </p>
        </form>

    </div>
    <div class="clearFix"></div>
    <?php
}


// Add submenu item under the main admin page
function edd_bulk_lite_upload_add_uploader_submenu() {
    add_submenu_page(
        'edd-bulk-uploader',            
        'EDD Bulk Image & Video Uploader',          
        'EDD Bulk Image & Video Uploader',         
        'manage_options',                
        'bulk-upload-image-video',         
        'edd_bulk_lite_upload_media_uploader_page'  
    );
}
add_action('admin_menu', 'edd_bulk_lite_upload_add_uploader_submenu');

function edd_bulk_lite_upload_media_uploader_page() {

       ?>
    <div class="eddBulkwrap">
        <h1>EDD Bulk Image and Video Uploader and Importer Lite</h1>
        
        <div id="google-vision-image-options" class="lockedForPremiumSection">
            <p style="text-align: center;">
                Advanced API tools for content creation, including automated tag generation, title creation, and description generation (with more features coming soon), are available in the premium version of this plugin. 
                <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to unlock these powerful tools.
            </p>

            
            <?php if (
                get_option('edd_bulk_lite_auto_image_tagging', 'off') === 'on' || 
                get_option('edd_bulk_lite_auto_image_titles', 'off') === 'on' ||
                get_option('edd_bulk_lite_auto_image_description', 'off') === 'on') :
            ?>
                <h3>Advanced Image Inspection Tools</h3>
                <div id="vision-hints-section">
                    <label for="vision-hints">Image Content Hints</label>
                    <input type="text" name="vision-hints" id="google-vision-hints" />
                </div>
                        
                <?php if (get_option('edd_bulk_lite_auto_image_tagging', 'off') === 'on') : ?>         
                    <div id="auto-tagging-area">
                        <button id="generate-tags-button" class="button button-secondary" disabled>
                            Generate Additonal Tags
                        </button>           
                    </div>
                <?php endif; ?>
                
                <?php if (get_option('edd_bulk_lite_auto_image_titles', 'off') === 'on') : ?>          
                    <div id="auto-title-area">
                        <button id="generate-titles-button" class="button button-secondary" disabled>
                            Automatically Generate Titles
                        </button>           
                    </div>

                <?php endif; ?>
                <?php if (get_option('edd_bulk_lite_auto_image_description', 'off') === 'on') : ?>         
                    <div id="auto-description-area">
                        <button id="generate-descriptions-button" class="button button-secondary" disabled>
                            Automatically Generate Descriptive Content
                        </button>           
                    </div>
                <?php endif; ?>

                <p>
                    <strong>Note:</strong> Advanced content generation features, including automatic tag extraction, AI-powered titles, and descriptions using external APIs like Google Vision and OpenAI, are only available in the premium version of this plugin. 
                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to unlock these powerful tools.
                </p>

            <?php endif ?>
        </div>
        

        <div id="edd-upload-area">
            <div id="edd-drag-drop-area" class="edd-drag-drop-area">
                <p>Drag and drop files here or click the button to upload.</p>
                <label for="edd-file-upload" class="edd-upload-label">Choose Files</label>
                <input type="file" id="edd-file-upload" multiple>
            </div>
        </div>
       
        <div id="eddBulkContentControlsContainer">
            
            <div id="generate-import-button-area">         
                <div id="generate-import-button-form" style="text-align:center;width:auto;margin:auto;">
                    <p>Once all your files are uploaded and you are happy with the content in them, click Proceed to Import.  This will generate a CSV and provide options to either download it or import it.</p>
                    <button id="edd-generate-import-file" class="button button-primary" disabled>
                        Proceed to Import
                    </button>
                </div>
            </div>
            <h3>Bulk Edit Tags, Categories & Prices</h3>
            <table>
            <tr>
                <th>
                    <button type="button" class="removeButtons" id="removeSelectedItems">Remove Seleced Items</button>
                </th>
                <th>
                    <label for="add-tags-input">Add/Remove tags:</label>
                    <input type="text" id="tagsChangeInput" placeholder="Enter tags, separated by commas">
                    <button type="button" id="add-tags-button">Add Tags</button>
                    <button type="button" id="remove-tags-button" class="removeButtons">Remove Tags</button>
                </th>
                <th>
                    <label for="add-category-input">Add/Remove categories: (Parent/Child for child categories)</label>
                    <input type="text" id="categoryChangeinput" placeholder="Enter categories, separated by commas">
                    <button type="button" id="add-category-button">Add Category</button>
                    <button type="button" id="remove-category-button" class="removeButtons" >Remove Category</button>
                </th>
                <th>
                    <label for="bulk-update-prices-input">Bulk update prices:</label>
                    <input type="number" id="bulk-update-prices-input" placeholder="Enter new price" step="0.01" min="0">
                    <button type="button" id="bulk-update-prices-button">Update Prices</button>
                </th>
            </tr>
        </table>
        </div>
        
        
        <table class="edd-upload-list">
            <thead>
                
                <tr>
                    <th><input type="checkbox" id="actionSelectAll" class="bulkActionSelections"></th>
                    <th>Thumbnail</th>
                    <th>Title & Content</th>
                    <th>Tags / Category / Price</th>
                    <th>File URL</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="edd-upload-tbody"></tbody>
        </table>


        

    </div>
    <?php

}

// Add submenu item under the main admin page
function edd_bulk_lite_upload_add_options_submenu() {
    add_submenu_page(
        'edd-bulk-uploader',              // Parent slug
        'Bulk Uploader Options',          // Page title
        'Bulk Uploader Options',          // Menu title
        'manage_options',                 // Capability
        'bulk-upload-options',          // Menu slug
        'edd_bulk_lite_upload_options_page'  // Callback function
    );
}
add_action('admin_menu', 'edd_bulk_lite_upload_add_options_submenu');

function edd_bulk_lite_upload_options_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    $enable_s3_storage = get_option('edd_bulk_lite_enable_s3_storage', 'off');
    $is_edd_s3_active = is_plugin_active('edd-amazon-s3/edd_s3.php');

    ?>
    <div class="eddBulkwrap">
        <h1>EDD Bulk Uploader & Importer Lite Options</h1>
        <p>This page provides settings related to bulk uploads, content and file manipulation and creation.  Some of the more advanced options require the premium version of the plugin which can be purchased <a target="_blank"href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/">here</a></p>       
        <form method="post" action="">
            <div class="edd-bulk-option-sections-containers">
                <h2>Basic Settings</h2>
                <div class="halfWidthColLeft">
                    <h3>Debugging Options</h3>
                    <p>IMPORTANT: DO NOT LEAVE ENABLED WHILE NOT DEBUGGING</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_php_debugging">Enable PHP Debugging</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        id="enable_php_debugging"
                                        name="enable_php_debugging"
                                        <?php echo get_option('edd_bulk_lite_uploader_php_debugging') === 'on' ? 'checked' : ''; ?>
                                    />
                                    <span class="slider round"></span>
                                    
                                </label>
                                
                            </td>
                            <td>
                                <span>Adds comprehensive PHP data logging to debug log</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_js_console_debugging">Enable JS Console Debugging</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        id="enable_js_console_debugging"
                                        name="enable_js_console_debugging"
                                        <?php echo get_option('edd_bulk_lite_uploader_js_debugging') === 'on' ? 'checked' : ''; ?>
                                    />
                                    <span class="slider round"></span>
                                    
                                </label>
                                
                            </td>
                            <td>
                                <span>Adds comprehensive JS data logging to console log</span>
                            </td>
                        </tr>                   
                    </table>
                </div>

                <div class="halfWidthColRight">
                    <h3>Amazon S3 Storage Settings</h3>
                    <p>Requires Amazon S3 Extension installed and enabled</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_s3_storage">Enable S3 Storage Using Existing Settings</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        id="enable_s3_storage"
                                        name="enable_s3_storage"
                                        <?php echo get_option('edd_bulk_lite_enable_s3_storage') === 'on' ? 'checked' : ''; ?>
                                        <?php echo !$is_edd_s3_active ? 'disabled' : ''; ?>
                                    />
                                    <span class="slider round"></span>
                                </label>
                                
                            </td>
                            <td>
                                <?php if (!$is_edd_s3_active) : ?>
                                    <p style="color: red;">The EDD Amazon S3 plugin is not installed or active. This feature is locked.</p>
                                <?php endif; ?>
                                <?php if ($is_edd_s3_active) : ?>
                                    <span>This feature will upload your original file to S3 once initial upload to WordPress is completed.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="remove_original_after_upload">Remove Original File from WordPress After Upload</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        id="remove_original_after_upload"
                                        name="remove_original_after_upload"
                                        <?php echo get_option('edd_bulk_lite_s3_remove_original_after_upload') === 'on' ? 'checked' : ''; ?>
                                        <?php echo get_option('edd_bulk_lite_enable_s3_storage') !== 'on' ? 'disabled' : ''; ?>
                                    />
                                    <span class="slider round"></span>
                                </label>
                                
                            </td>
                            <td>
                                <span>This will remove the original file and all versions that WordPress created aside from the preview/featured image, leaving the original file only in S3</span> 
                            </td>
                        </tr>
                    </table>
                    
                </div>
                <div class="clearFix">&nbsp</div>
                

                
            </div>

            <p style="text-align: center;">
                Unlock the full potential of your workflow with the premium version! Gain access to advanced features like automated tag generation, AI-powered title and description creation, image watermarking, metadata extraction, and more. Don't miss out on the tools that can save you time and elevate your digital product management. 
                <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank" style="font-weight: bold; color: #0073aa;">Upgrade to the premium version today</a> and take your productivity to the next level!
            </p>

            <div class="edd-bulk-option-sections-containers lockedForPremiumSection">
                <h2>Advanced Options</h2>
                <p>
                    Advanced features such as image renaming, automatic tag extraction, and watermarking are only available in the premium version of this plugin. 
                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to access these tools.
                </p>

                <div class="halfWidthColLeft">
                    <table class="form-table">
                        <div id="edd-bulk-upload-options">

                            <tr>
                                <th scope="row">
                                    <label for="enable-image-renaming">Enable Image Renaming</label>
                                </th>
                                <td>
                                    <label class="switch">
                                        <input
                                            disabled
                                            type="checkbox"
                                            id="enable-image-renaming"
                                            name="enable-image-renaming"
                                        />
                                        <span class="slider round"></span>
                                    </label>
                                        
                                </td>
                                <td>
                                    <p>
                                            The image renaming feature is only available in the premium version of this plugin. 
                                            <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to unlock this functionality.
                                    </p>
                                </td>
                            </tr>


                        </div>
                    </table> 
                    <p>Image Renaming (Premium Feature) will rename your upload to the text prefix chosen, and '_' and a random 7 character alphanumeric string before it hits the WordPress uploads</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="extract_metadata_toggle">Metadata Tag Extraction</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input
                                        disabled
                                        type="checkbox"
                                        id="extract_metadata_toggle"
                                        name="extract_metadata_toggle"
                                    />
                                    <span class="slider round"></span>
                                </label>
                            </td>
                            <td>
                                <p>
                                    The automatic image metadata tag extraction feature is only available in the premium version of this plugin. 
                                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to access this advanced functionality.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="halfWidthColRight">
                    <table class="form-table">
                        <div id="edd-watermark-options">
                            <tr>
                                <th scope="row">
                                    <label for="enable-image-renaming">Enable Watermarking with Imagick</label>
                                </th>
                                <td>                    
                                        <label class="switch">
                                            <input
                                                type="checkbox"
                                                id="enable-watermarking"
                                                disabled
                                            />
                                            <span class="slider round"></span>
                                        </label>                                
                                        
                                </td>
                                <td>
                                    
                                    <p>
                                            The image watermarking feature is only available in the premium version of this plugin. 
                                            <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to enable this feature.
                                    </p>
                                </td>
                            </tr>
                        </div>

                    </table>
                    <p>Watermarking currently orks best with a 1920px by 1920px pre made watermark image that will be scaled to the largest parameter (height or width) and overlayed onto the preview images.  Occurs once off on upload and cannot be retriggered using thumbnail regeneration (yet).  More options coming in future releases</p>
                      
                </div>  
                <div class="clearFix">&nbsp</div>         
            </div>

            <div class="edd-bulk-option-sections-containers lockedForPremiumSection">
                <h2>API Settings</h2>
                <p>
                    Advanced AI and external API features for content automation, including tag generation, title creation, and descriptive text generation, are only available in the premium version of this plugin. 
                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to unlock these powerful tools.
                </p>

                <div class="halfWidthColLeft">
                    <h3>API Keys</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">

                                <label for="google_vision_api_key">Google API Key</label>
                                
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="google_vision_api_key"
                                    name="google_vision_api_key"
                                    class="regular-text"
                                    disabled
                                />
                                <button 
                                    disabled 
                                      type="submit" 
                                      name="save_google_vision_key" 
                                      class="button button-primary">Save Key</button>
                                <button
                                    disabled
                                      type="button"
                                      id="test_google_vision_key"
                                      class="button"
                                  >
                                      Test Key
                                  </button>
                                  <button
                                    disabled
                                      type="button"
                                      id="clear_google_vision_key"
                                      class="button button-danger"
                                  >
                                      Clear Key
                                  </button>
                                <p>
                                    The Google Vision API features, including automated tag generation based on image content, are only available in the premium version of this plugin. 
                                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to access this functionality.
                                </p>

                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="openai_api_key">OpenAI API Key</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="openai_api_key"
                                    name="openai_api_key"
                                    disabled
                                    class="regular-text"
                                />
                                <button
                                    disabled
                                    type="submit" 
                                    name="save_openai_key" 
                                    class="button button-primary">Save Key</button>
                                <button
                                    disabled
                                    type="button"
                                    id="test_openai_key"
                                    class="button"
                                    >
                                    Test Key
                                </button>
                                <button
                                    disabled
                                    type="button"
                                    id="clear_openai_key"
                                    class="button button-danger"
                                    >
                                    Clear Key
                                </button>
                                <p>
                                    The OpenAI-powered features, including title creation, descriptive text generation, and advanced tag suggestions, are only available in the premium version of this plugin. 
                                    <a href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/" target="_blank">Upgrade to the premium version</a> to unlock these AI-driven tools.
                                </p>

                              </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="halfWidthColRight">
                    <h3>Auto Image Tagging, Titles and Content</h3>
                    <table class="form-table">    
                    <tr>
                        <th scope="row">
                            <label for="auto_image_tagging">Automatic Image Tag Generation via Vision API</label>
                        </th>
                        <td>
                            <label class="switch">
                                <input
                                    type="checkbox"
                                    id="auto_image_tagging"
                                    name="auto_image_tagging"
                                    disabled
                                />
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <span>Uses Google Vision to check the image contents and return tags relating to what is in the images.</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_image_titles">Automatic Image Title Generation via Vision API & OpenAI (in testing)</label>
                        </th>
                        <td>
                            <label class="switch">
                                <input
                                    type="checkbox"
                                    id="auto_image_titles"
                                    name="auto_image_titles"
                                    disabled
                                />
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <span>Uses a combination of Google Vision & OpenAI to generate titles based on the content of the image and tags.</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="auto_image_description">Automatic Image Description Generation via Vision API & OpenAI (under development)</label>
                        </th>
                        <td>
                            <label class="switch">
                                <input 
                                    type="checkbox"
                                    id="auto_image_description"
                                    name="auto_image_description"
                                    disabled
                                />
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td>
                            <span>Uses a combination of Google Vision & OpenAI to generate descriptions based on the content of the image and tags.</span>
                        </td>
                    </tr>
                </table>
                </div> 
                <div class="clearFix">&nbsp</div>                
            </div>
        </form>           
    </div>

    <?php
}

require 'admin-page-functions.php';

?>
