<?php
/**
 * Plugin Name: Bulk Uploader & Importer for Easy Digital Downloads - Lite
 * Plugin URI: https://wordpress.org/plugins/edd-bulk-uploader-importer
 * Description: Bulk upload images and videos to create Easy Digital Downloads items quickly. Includes tools for managing tags, pricing & categories in bulk as well as integration with Amazon S3.  More advanced features are available in the premium version, get it <a target="_blank" href="https://aodigital.com.au/downloads/edd-bulk-uploader-importer-full/">here</a>
 * Version: 1.0.1
 * Author: Adam Langley
 * Author URI: https://aodigital.com.au
 * Requires at least: 6.0.0
 * Tested up to: 6.7.1
 * Stable tag: 1.0.1
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Bulk Uploader & Importer for Easy Digital Downloads Lite is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or (at your option) any later version.
 *
 * Bulk Uploader & Importer for Easy Digital Downloads is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this plugin.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * @package edd-bulk-uploader-importer
 * @category Core
 * @author Adam Langley
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('EDD_BULK_LITE_PLUGIN_VERSION', '1.0.1');

global $wp_filesystem;

if (!isset($wp_filesystem) || empty($wp_filesystem)) {
    require_once ABSPATH . '/wp-admin/includes/file.php';
    WP_Filesystem();
}

function edd_bulk_lite_upload_activation_check_combined() {
    $uploads_dir = wp_upload_dir();
    $target_dir = $uploads_dir['basedir'] . '/bulk-edd-upload-import-files/';
    if (!file_exists($target_dir)) {
        wp_mkdir_p($target_dir);

        // Initialize the WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Set the permissions using WP_Filesystem
        if ($wp_filesystem->exists($target_dir)) {
            $wp_filesystem->chmod($target_dir, 0755, true); // Recursive
        }
    }

    // Logic for EDD or EDD Pro check
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $edd_plugins = [
        'easy-digital-downloads/easy-digital-downloads.php',
        'easy-digital-downloads-pro/easy-digital-downloads-pro.php',
        'easy-digital-downloads-pro/easy-digital-downloads.php',
    ];

    $premium_plugin = 'edd-bulk-uploader-importer-full/edd-bulk-uploader-importer-full.php';

    // Check if EDD is active
    $is_edd_active = false;
    foreach ( $edd_plugins as $edd_plugin ) {
        if ( is_plugin_active( $edd_plugin ) ) {
            $is_edd_active = true;
            break;
        }
    }

    if ( ! $is_edd_active ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            'This plugin requires Easy Digital Downloads or Easy Digital Downloads Pro to be installed and active. <a href="' . esc_url( admin_url( 'plugin-install.php?s=Easy+Digital+Downloads&tab=search&type=term' ) ) . '">Click here to install Easy Digital Downloads</a>'
        );
    }

    // Check if Premium plugin is active
    if ( is_plugin_active( $premium_plugin ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            'The Premium version of this plugin is already active. The Lite version has not been enabled. <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Return to the Plugins page</a>'
        );
    }
}

register_activation_hook( __FILE__, 'edd_bulk_lite_upload_activation_check_combined' );

function edd_bulk_lite_dependency_check_on_deactivation( $plugin ) {
    // Define Easy Digital Downloads plugins
    $edd_plugins = [
        'easy-digital-downloads/easy-digital-downloads.php',
        'easy-digital-downloads-pro/easy-digital-downloads-pro.php',
        'easy-digital-downloads-pro/easy-digital-downloads.php',
    ];
    // Check if one of the EDD plugins is being deactivated
    if ( in_array( $plugin, $edd_plugins, true ) ) {
        // Schedule deactivation for the current plugin
        add_action('shutdown', function () {
            deactivate_plugins( plugin_basename(__FILE__) );
        });

        // Optional admin notice
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>EDD Bulk Uploader & Importer</strong> has been deactivated because Easy Digital Downloads or Easy Digital Downloads Pro is no longer active.</p>';
            echo '</div>';
        });
    } 
}
add_action( 'deactivated_plugin', 'edd_bulk_lite_dependency_check_on_deactivation' );



// Include fontawesome if its not already included.
include_once plugin_dir_path( __FILE__ ) . 'includes/fontawesome.php';

// Include admin pages
include_once plugin_dir_path( __FILE__ ) . 'includes/admin-page.php';

// File handler
include_once plugin_dir_path( __FILE__ ) . 'includes/file-handler.php';

//Import Handler
include_once plugin_dir_path( __FILE__ ) . 'includes/import-items.php';

//CSV Generation Handler
include_once plugin_dir_path( __FILE__ ) . 'includes/csv-functions.php';

//Image regeneration fallback handler
include_once plugin_dir_path( __FILE__ ) . 'includes/image-regeneration-fallback.php';

//S3 Integration functionality
include_once plugin_dir_path( __FILE__ ) . 'includes/amazon-s3-functions.php';

add_action('admin_enqueue_scripts', function () {
    wp_localize_script('edd-bulk-upload-script', 'ajaxurl', admin_url('admin-ajax.php'));
});

function edd_bulk_lite_upload_enqueue_scripts($hook) {
    if (
        $hook === 'toplevel_page_edd-bulk-uploader' ||               
        $hook === 'edd-bulk-uploader-importer_page_bulk-upload-image-video' || 
        $hook === 'edd-bulk-uploader-importer_page_bulk-upload-options'        
    ){  
        wp_enqueue_media(); 
        wp_enqueue_script(
            'edd-admin-options-js',
            plugin_dir_url(__FILE__) . 'assets/js/admin-options.js',
            ['jquery'],
            EDD_BULK_LITE_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'edd-bulk-upload-js',
            plugin_dir_url(__FILE__) . 'assets/js/edd-bulk-upload.js',
            ['jquery'],
            EDD_BULK_LITE_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'edd-bulk-upload-css',
            plugin_dir_url(__FILE__) . 'assets/css/edd-bulk-upload.css',
            [],
            EDD_BULK_LITE_PLUGIN_VERSION,
        );

        $js_debugging = get_option('edd_bulk_lite_uploader_js_debugging', 'off');
        $edd_bulk_lite_enable_s3_storage = get_option('edd_bulk_lite_enable_s3_storage', 'off');
        $rename_enabled = get_option('edd_bulk_lite_upload_rename_enabled', '0');
        $rename_prefix = get_option('edd_bulk_lite_upload_rename_prefix', '0');
        $enable_watermarking = get_option('edd_bulk_lite_upload_enable_watermarking', '0');
        $watermarking_image = get_option('edd_bulk_lite_upload_watermark_image', '0');
        $extractMetaDataTags = get_option('edd_bulk_lite_metadata_image_tagging', '0');
        $edd_bulk_lite_image_s3_remove_original_after_upload = get_option('edd_bulk_lite_upload_s3_remove_original_after_upload','off');
        wp_localize_script(
            'edd-bulk-upload-js',
            'edd_bulk_lite_upload_vars',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('edd_bulk_lite_upload'),
                'js_debugging' => $js_debugging,
                'edd_bulk_lite_enable_s3_storage' => $edd_bulk_lite_enable_s3_storage,
                'edd_bulk_lite_upload_s3_remove_original_after_upload' => $edd_bulk_lite_image_s3_remove_original_after_upload,
                'pluginUrl' => plugins_url('/', __FILE__), 
            ]
        );

        wp_localize_script(
            'edd-admin-options-js',
            'edd_bulk_lite_admin_vars',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('edd_bulk_lite_upload'),
                'js_debugging' => $js_debugging,
                'edd_bulk_lite_enable_s3_storage' => $edd_bulk_lite_enable_s3_storage,
                'pluginUrl' => plugins_url('/', __FILE__),           
            ]
        );
   }
}
add_action('admin_enqueue_scripts', 'edd_bulk_lite_upload_enqueue_scripts');




add_action( 'admin_init', function() {
    $update_plugins = get_site_transient( 'update_plugins' );
});

?>
