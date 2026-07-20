jQuery(document).ready(function ($) {
    const testButton = $("#test_google_vision_key");
    const clearButton = $("#clear_google_vision_key");
    const keyInput = $("#google_vision_api_key");
    const autoTaggingToggle = $("#auto_image_tagging");  
    const metadataTaggingToggle = $("#extract_metadata_toggle");
    const autoTitleToggle = $("#auto_image_titles");
    const autoDescriptionToggle = $("#auto_image_description");
    const s3Button = $("#send-to-s3-button"); 
    const uploadTableBody = $("#edd-upload-tbody");
    const s3Toggle = $("#enable_s3_storage");
    const removeOriginalToggle = $("#remove_original_after_upload");
    const removeOriginalWarning = $("#remove-original-warning");
    const generateTagsButton = $("#generate-tags-button");
    const extractMetaTagsButton = $('#extract-metadata-button');
    const generateImportFileButton = $('#edd-generate-import-file');
    const generateTitlesButton = $('#generate-titles-button');
    const generateDescriptionButton = $('#generate-descriptions-button');
    let pendingUploads = 0;
  
    // Save S3 Storage toggle state
    s3Toggle.on("change", function () {
        const isEnabled = $(this).is(":checked") ? "on" : "off";

        // Send the updated value via AJAX
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "edd_bulk_lite_upload_save_s3_storage_toggle",
                edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                enable_s3_storage: isEnabled,
            },
            success: function (response) {
                if (response.success) {
                    
                } else {
                    alert("Failed to update S3 Storage Setting: " + response.data.message);
                }
            },
            error: function () {
                alert("An error occurred while saving the S3 Storage Setting.");
            },
        });
    });
  
    $("#remove_original_after_upload").on("change", function () {
        const isChecked = $(this).is(":checked") ? "on" : "off";
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "edd_bulk_lite_upload_save_remove_original_toggle",
                edd_bulk_lite_upload_nonce: edd_bulk_lite_upload_vars.nonce,
                remove_original_after_upload: isChecked,
            },
            success: function (response) {
                if (response.success) {
                    
                } else {
                    alert("Failed to update Remove Original File setting: " + response.data.message);
                }
            },
            error: function () {
                alert("An error occurred while saving the Remove Original File setting.");
            },
        });
    });
  
    $('#enable_php_debugging').on('change', function () {
        const isChecked = $(this).is(':checked') ? 'on' : 'off';

        // Send AJAX request to update the toggle state
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'edd_bulk_lite_upload_update_php_debugging_toggle',
                php_debugging: isChecked,
            },
            success: function (response) {

                console.log('PHP Debugging toggle updated successfully.');
            },
            error: function () {
                alert('Error updating PHP Debugging toggle.');
                console.error('Error updating PHP Debugging toggle.');
            },
        });
    });

    // Handle enabling/disabling JS Console Debugging
    $('#enable_js_console_debugging').on('change', function () {
        const isChecked = $(this).is(':checked') ? 'on' : 'off';

        // Send AJAX request to update the toggle state
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'edd_bulk_lite_upload_update_js_debugging_toggle',
                js_debugging: isChecked,
            },
            success: function (response) {

                console.log('JS Console Debugging toggle updated successfully.');
            },
            error: function () {
                alert('Error updating JS Console Debugging.');
                console.error('Error updating JS Console Debugging toggle.');
            },
        });
    });

    // Handle locking/unlocking the "Remove Original" toggle based on the S3 Storage toggle
    s3Toggle.on("change", function () {
        const isS3Enabled = $(this).is(":checked");

        if (!isS3Enabled) {
            removeOriginalToggle.prop("checked", false).prop("disabled", true);
        } else {
            removeOriginalToggle.prop("disabled", false);
        }
    });

    // Initial toggle state setup
    removeOriginalToggle.prop("disabled", !s3Toggle.is(":checked"));


    // Function to toggle the "Generate Tags" button state
    function toggleGenerateTagsButtonState() {

    }

    // Monitor the file upload count
    $(document).on("uploadStart", function () {
        pendingUploads++;
    });
  
    $(document).on("uploadComplete", function () {
        pendingUploads--;
    });

  
});
