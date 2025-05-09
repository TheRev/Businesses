<?php
/**
 * Plugin Name: Shops WP Importer
 * Description: Import businesses from Google Places API (New v1) as custom post types with meta and taxonomies.
 * Version: 0.3.0
 * Author: TheRev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Register Business CPT
function swi_register_business_cpt() {
    register_post_type('business', [
        'labels' => [
            'name' => 'Businesses',
            'singular_name' => 'Business',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'supports' => ['title', 'editor'],
    ]);
}
add_action('init', 'swi_register_business_cpt');

// Register Region & Destination Taxonomies
function swi_register_taxonomies() {
    register_taxonomy('region', 'business', [
        'label' => 'Region',
        'hierarchical' => true,
        'show_in_menu' => true,
    ]);
    register_taxonomy('destination', 'business', [
        'label' => 'Destination',
        'hierarchical' => false,
        'show_in_menu' => true,
    ]);
}
add_action('init', 'swi_register_taxonomies');

// Add Importer and Settings as submenus under Businesses CPT menu
function swi_admin_menu() {
    $parent_slug = 'edit.php?post_type=business';

    add_submenu_page(
        $parent_slug,
        'Import',           // Page title
        'Import',           // Menu title
        'manage_options',   // Capability
        'swi-import',       // Menu slug
        'swi_import_page'   // Callback
    );

    add_submenu_page(
        $parent_slug,
        'Settings',         // Page title
        'Settings',         // Menu title
        'manage_options',   // Capability
        'swi-settings',     // Menu slug
        'swi_settings_page' // Callback
    );
}
add_action('admin_menu', 'swi_admin_menu');

// Import Page
function swi_import_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/import-page.php';
}

// Settings Page
function swi_settings_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';
}

// Settings registration
function swi_register_settings() {
    register_setting('swi_settings_group', 'swi_google_places_api_key');
}
add_action('admin_init', 'swi_register_settings');

// Import logic
require_once plugin_dir_path(__FILE__) . 'importer.php';
