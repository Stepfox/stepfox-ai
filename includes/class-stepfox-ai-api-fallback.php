<?php
/**
 * Fallback AJAX handler for environments with REST API issues
 *
 * @package    StepFox_AI
 * @subpackage StepFox_AI/includes
 */

class StepFox_AI_API_Fallback {
    
    /**
     * Initialize the fallback handler
     */
    public function __construct() {
        // Add AJAX actions for logged in users
        add_action('wp_ajax_stepfox_ai_generate_fallback', array($this, 'handle_ajax_generate'));
    }
    
    /**
     * Handle AJAX generation request
     */
    public function handle_ajax_generate() {
        // Extend PHP execution time for long-running AI requests
        @set_time_limit(300); // 5 minutes
        @ini_set('max_execution_time', 300);
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stepfox_ai_fallback_nonce')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Get request data
        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        $images = isset($_POST['images']) ? json_decode(stripslashes($_POST['images']), true) : array();
        
        if (empty($prompt)) {
            wp_send_json_error('Prompt is required', 400);
            return;
        }
        
        // Use the same API logic
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-stepfox-ai-api.php';
        $api = new StepFox_AI_API('stepfox-ai', STEPFOX_AI_VERSION);
        
        // Create a mock REST request
        $request = new WP_REST_Request('POST', '/stepfox-ai/v1/generate');
        $request->set_param('prompt', $prompt);
        $request->set_param('images', $images);
        
        // Handle the request
        $response = $api->handle_generate_request($request);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message(), $response->get_error_code());
        } else {
            $data = $response->get_data();
            wp_send_json_success($data);
        }
    }
}
