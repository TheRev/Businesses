<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// AJAX handler for import
add_action('wp_ajax_swi_import_businesses', 'swi_import_businesses_handler');
function swi_import_businesses_handler() {
    $api_key = get_option('swi_google_places_api_key');
    if ( ! $api_key ) {
        echo '<span class="notice notice-error">API key not set!</span>';
        wp_die();
    }
    $query  = sanitize_text_field( $_POST['swi_query'] ?? '' );
    $limit  = intval( $_POST['swi_limit'] ?? 10 );
    $region = sanitize_text_field( $_POST['swi_region'] ?? '' );
    $destination_manual = sanitize_text_field( $_POST['swi_destination_manual'] ?? '' );
    $destination_auto = isset($_POST['swi_destination_auto']) && $_POST['swi_destination_auto'] === '1';
    $use_next_page_token = !empty($_POST['swi_use_next_page_token']);

    // New: Accept lat/lng and radius from the form
    $latitude  = isset($_POST['swi_latitude']) ? floatval($_POST['swi_latitude']) : null;
    $longitude = isset($_POST['swi_longitude']) ? floatval($_POST['swi_longitude']) : null;
    $radius    = isset($_POST['swi_radius']) ? floatval($_POST['swi_radius']) : 6000;

    if ($latitude === null || $longitude === null) {
        echo '<span class="notice notice-error">Latitude and longitude are required for location restriction.</span>';
        wp_die();
    }

    // Track the search context (query+radius+lat+lng) to store their own token
    $search_context = md5($query . '|' . $radius . '|' . $latitude . '|' . $longitude);

    // Get last token for this context
    $token_option_name = 'swi_next_page_token_' . $search_context;
    $page_token = $use_next_page_token ? get_option($token_option_name, '') : '';

    // Compose POST body for Google Places API v1 (locationRestriction is correct!)
    $body = [
        'textQuery' => $query,
        'maxResultCount' => $limit,
        'locationRestriction' => [
            'circle' => [
                'center' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
                'radius' => $radius, // in meters, between 0.0 and 50000.0
            ]
        ],
    ];
    if ($page_token) {
        $body['pageToken'] = $page_token;
    }

    $response = wp_remote_post(
        'https://places.googleapis.com/v1/places:searchText',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $api_key,
            ],
            'body' => json_encode($body),
            'timeout' => 20,
        ]
    );

    if ( is_wp_error($response) ) {
        echo '<span class="notice notice-error">API request failed: ' . esc_html($response->get_error_message()) . '</span>';
        wp_die();
    }
    $api_response = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($api_response['places'])) {
        echo '<span class="notice notice-warning">No businesses found for this query.</span>';
        echo '<pre style="white-space:pre-wrap;background:#f7f7f7;border:1px solid #ccc;padding:8px;">' . esc_html(print_r($api_response, true)) . '</pre>';
        wp_die();
    }

    $imported = 0;
    $skipped = 0;
    $import_names = [];

    // Prevent duplicates by tracking imported IDs (place_id)
    $imported_ids = get_option('swi_imported_place_ids', []);
    if (!$imported_ids) $imported_ids = [];

    foreach ($api_response['places'] as $place) {
        // Use the Google place_id as unique identifier
        $place_id = isset($place['id']) ? $place['id'] : (isset($place['googleMapsUri']) ? $place['googleMapsUri'] : $place['name']);
        if (in_array($place_id, $imported_ids)) {
            $skipped++;
            continue;
        }
        $post_id = swi_import_business($place, $region, $destination_manual, $destination_auto);
        if (is_wp_error($post_id)) continue;
        $imported++;
        $import_names[] = get_the_title($post_id);
        $imported_ids[] = $place_id;
    }
    // Save imported IDs
    update_option('swi_imported_place_ids', $imported_ids);

    // Save nextPageToken or clear if none
    if (!empty($api_response['nextPageToken'])) {
        update_option($token_option_name, $api_response['nextPageToken']);
        $next_token_msg = '<strong>Next Page Token saved. Click "Import Next Batch" to continue.</strong>';
    } else {
        delete_option($token_option_name);
        $next_token_msg = '<strong>No more results. Import complete for this query/radius.</strong>';
    }

    // Output confirmation
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html("Imported $imported businesses. Skipped $skipped duplicate(s).");
    if ($import_names) {
        echo '<br><strong>Imported:</strong> ' . esc_html(implode(', ', $import_names));
    }
    echo '<br>' . $next_token_msg;
    echo '</p></div>';
    wp_die();
}

// Reset handler for import progress
add_action('wp_ajax_swi_reset_import_progress', function() {
    $query  = sanitize_text_field( $_POST['swi_query'] ?? '' );
    $radius = isset($_POST['swi_radius']) ? floatval($_POST['swi_radius']) : 6000;
    $latitude  = isset($_POST['swi_latitude']) ? floatval($_POST['swi_latitude']) : '';
    $longitude = isset($_POST['swi_longitude']) ? floatval($_POST['swi_longitude']) : '';
    $search_context = md5($query . '|' . $radius . '|' . $latitude . '|' . $longitude);
    $token_option_name = 'swi_next_page_token_' . $search_context;
    delete_option($token_option_name);
    echo 'Import progress reset. You can start a fresh import.';
    wp_die();
});
