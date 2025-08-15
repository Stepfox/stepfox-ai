<?php
/**
 * Test REST API endpoint
 * Usage: Run this in browser while logged into WordPress
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Please log in to WordPress first.');
}

$api_key = get_option('stepfox_ai_openai_api_key', '');
$model = get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo');

echo "<h2>StepFox AI REST API Test</h2>";
echo "<p>API Key configured: " . (!empty($api_key) ? 'Yes' : 'No') . "</p>";
echo "<p>Model: " . esc_html($model) . "</p>";
echo "<p>Current user can edit posts: " . (current_user_can('edit_posts') ? 'Yes' : 'No') . "</p>";

// Create nonce
$nonce = wp_create_nonce('wp_rest');
echo "<p>REST Nonce: " . esc_html($nonce) . "</p>";

// Test REST API URL
$rest_url = rest_url('stepfox-ai/v1/generate');
echo "<p>REST URL: " . esc_html($rest_url) . "</p>";

// Simple test data
$test_data = array(
    'prompt' => 'Create a simple paragraph that says Hello World',
    'images' => array()
);

echo "<h3>Testing REST API...</h3>";

// Use WordPress HTTP API to test
$response = wp_remote_post($rest_url, array(
    'headers' => array(
        'Content-Type' => 'application/json',
        'X-WP-Nonce' => $nonce
    ),
    'body' => json_encode($test_data),
    'cookies' => $_COOKIE // Include cookies for authentication
));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>Error: " . $response->get_error_message() . "</p>";
} else {
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "<p>Response Status: " . $status . "</p>";
    echo "<p>Response Body:</p>";
    echo "<pre>" . esc_html($body) . "</pre>";
    
    $data = json_decode($body, true);
    if ($data) {
        echo "<p>Decoded Response:</p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
}

// Also check REST API route registration
echo "<h3>REST Route Info:</h3>";
$routes = rest_get_server()->get_routes();
if (isset($routes['/stepfox-ai/v1/generate'])) {
    echo "<p style='color: green;'>✓ REST route is registered</p>";
    echo "<pre>" . print_r($routes['/stepfox-ai/v1/generate'], true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ REST route is NOT registered</p>";
}
