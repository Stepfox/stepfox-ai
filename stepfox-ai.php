<?php
/**
 * Plugin Name: StepFox AI
 * Plugin URI: https://stepfox.com/
 * Description: AI-powered code generation block for WordPress using OpenAI
 * Version: 1.0.0
 * Author: StepFox
 * Author URI: https://stepfox.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stepfox-ai
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STEPFOX_AI_VERSION', '1.0.0');
define('STEPFOX_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STEPFOX_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STEPFOX_AI_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 */
function stepfox_ai_activate() {
    // Ensure no output during activation
    ob_start();
    
    // Add default options
    update_option('stepfox_ai_openai_api_key', get_option('stepfox_ai_openai_api_key', ''));
    update_option('stepfox_ai_openai_model', get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo'));
    
    // Clear any output
    ob_end_clean();
}
register_activation_hook(__FILE__, 'stepfox_ai_activate');

/**
 * Plugin deactivation hook
 */
function stepfox_ai_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'stepfox_ai_deactivate');

/**
 * Load plugin textdomain
 */
function stepfox_ai_load_textdomain() {
    load_plugin_textdomain('stepfox-ai', false, dirname(STEPFOX_AI_BASENAME) . '/languages');
}
add_action('init', 'stepfox_ai_load_textdomain');

/**
 * Initialize the plugin
 */
function stepfox_ai_init() {
    // Only load if we're past the init phase
    if (!did_action('init')) {
        return;
    }
    
    // Include required files
    require_once STEPFOX_AI_PLUGIN_DIR . 'includes/class-stepfox-ai.php';
    require_once STEPFOX_AI_PLUGIN_DIR . 'includes/class-stepfox-ai-admin.php';
    require_once STEPFOX_AI_PLUGIN_DIR . 'includes/class-stepfox-ai-api.php';
    
    // Include block registration
    require_once STEPFOX_AI_PLUGIN_DIR . 'blocks/ai-console-runner/index.php';
    
    // Create and run the main plugin instance
    $plugin = new StepFox_AI();
    $plugin->run();
}

// Hook the initialization after WordPress is fully loaded
add_action('init', 'stepfox_ai_init', 20);

/**
 * Register blocks directly
 */
function stepfox_ai_register_blocks_direct() {
    // Simple test to ensure blocks are loading (disabled for now)
    // wp_register_script(
    //     'stepfox-ai-blocks',
    //     STEPFOX_AI_PLUGIN_URL . 'blocks/test-block.js',
    //     array('wp-blocks', 'wp-element', 'wp-editor'),
    //     STEPFOX_AI_VERSION
    // );
    
    wp_register_script(
        'stepfox-ai-console-runner-direct',
        STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/ai-console-runner-basic.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'),
        STEPFOX_AI_VERSION
    );
    
    // Localize script
    wp_localize_script('stepfox-ai-console-runner-direct', 'stepfoxAI', array(
        'apiUrl' => rest_url('stepfox-ai/v1/generate'),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fallbackNonce' => wp_create_nonce('stepfox_ai_fallback_nonce'),
    ));
}
add_action('init', 'stepfox_ai_register_blocks_direct', 5);

/**
 * Enqueue block assets in editor
 */
function stepfox_ai_enqueue_block_assets() {
    // Test simple block first
    wp_enqueue_script(
        'stepfox-ai-simple-test',
        STEPFOX_AI_PLUGIN_URL . 'blocks/simple-test.js',
        array('wp-blocks', 'wp-element', 'wp-dom-ready'),
        STEPFOX_AI_VERSION,
        true
    );
    
    // Main block
    wp_enqueue_script('stepfox-ai-console-runner-direct');
    
    // Also enqueue the CSS
    wp_enqueue_style(
        'stepfox-ai-editor',
        STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/editor-simple.css',
        array('wp-edit-blocks'),
        STEPFOX_AI_VERSION
    );
}
add_action('enqueue_block_editor_assets', 'stepfox_ai_enqueue_block_assets');