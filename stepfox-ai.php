<?php
/**
 * Plugin Name: StepFox AI
 * Description: AI Console Runner block for generating code with OpenAI (server-side secured).
 * Version: 1.0.1
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
    define('STEPFOX_AI_VERSION', '1.0.1');
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

// Load GitHub updater
$sfa_updater = __DIR__ . '/admin/class-stepfox-ai-updater.php';
if (file_exists($sfa_updater)) {
    require_once $sfa_updater;
    if (class_exists('Stepfox_AI_Updater')) {
        Stepfox_AI_Updater::init();
    }
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


// Add "Check for updates" link on the plugin row
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    if (current_user_can('update_plugins')) {
        $url = network_admin_url('update-core.php?force-check=1');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'stepfox-ai') . '</a>';
    }
    return $links;
});


