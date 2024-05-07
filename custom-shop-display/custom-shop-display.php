<?php
/*
Plugin Name: Custom Shop Display
Description: Plugin to customize shop display on WooCommerce store.
Version: 1.0
Author: Your Name
*/
include_once (plugin_dir_path(__FILE__) . 'includes/function.php');
// Function to retrieve product list HTML

add_shortcode('custom_product_content', 'custom_product_content_shortcode');
add_action('wp_ajax_filter_products', 'filter_products_callback');
add_action('wp_ajax_nopriv_filter_products', 'filter_products_callback');
// Đăng ký short code
add_shortcode('list_categories', 'display_product_categories');

// Shortcode function
// Đăng ký shortcode
add_shortcode('all_product_attributes_dropdowns', 'custom_all_product_attributes_checkboxes_shortcode');

// Shortcode function
add_shortcode('novu_api_key', 'novu_api_key_shortcodes');

