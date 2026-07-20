<?php
function edd_bulk_lite_add_font_awesome_to_head() {
    wp_enqueue_style(
        'font-awesome',
        plugin_dir_url(__FILE__) . '../assets/css/all.min.css',
        array(), 
        '5.15.4'
    );
}
add_action('wp_enqueue_scripts', 'edd_bulk_lite_add_font_awesome_to_head');
add_action('admin_enqueue_scripts', 'edd_bulk_lite_add_font_awesome_to_head');
