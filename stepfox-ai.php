<?php
/**
 * Plugin Name: StepFox AI
 * Description: AI Console Runner block for generating code with OpenAI (server-side secured).
 * Version: 1.0.0
 * Author: StepFox
 * License: GPL-2.0+
 * Text Domain: stepfox-ai
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('STEPFOX_AI_VERSION')) {
    define('STEPFOX_AI_VERSION', '1.0.0');
}
if (!defined('STEPFOX_AI_PLUGIN_FILE')) {
    define('STEPFOX_AI_PLUGIN_FILE', __FILE__);
}
if (!defined('STEPFOX_AI_PLUGIN_DIR')) {
    define('STEPFOX_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('STEPFOX_AI_PLUGIN_URL')) {
    define('STEPFOX_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Autoload/includes
require_once STEPFOX_AI_PLUGIN_DIR . 'includes/class-stepfox-ai.php';
require_once STEPFOX_AI_PLUGIN_DIR . 'blocks/ai-console-runner/index.php';

// Initialize
function stepfox_ai_init_plugin() {
    $plugin = new StepFox_AI();
    $plugin->run();
}
add_action('plugins_loaded', 'stepfox_ai_init_plugin');


