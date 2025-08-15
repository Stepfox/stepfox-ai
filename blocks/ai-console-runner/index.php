<?php
/**
 * Register AI Console Runner block
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register test block
 */
function stepfox_ai_register_test_block() {
    wp_register_script(
        'stepfox-ai-test-block',
        STEPFOX_AI_PLUGIN_URL . 'blocks/test-block.js',
        array('wp-blocks', 'wp-element'),
        STEPFOX_AI_VERSION,
        true
    );
    
    register_block_type('stepfox-ai/test-block', array(
        'editor_script' => 'stepfox-ai-test-block',
    ));
}
add_action('init', 'stepfox_ai_register_test_block');

/**
 * Register the block on the server side
 */
function stepfox_ai_register_console_runner_block() {
    // Register block editor script
    wp_register_script(
        'stepfox-ai-console-runner',
        STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/ai-console-runner.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
        STEPFOX_AI_VERSION,
        true
    );

    // Register block editor styles
    wp_register_style(
        'stepfox-ai-console-runner-editor',
        STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/editor.css',
        array('wp-edit-blocks'),
        STEPFOX_AI_VERSION
    );

    // Localize script
    wp_localize_script('stepfox-ai-console-runner', 'stepfoxAI', array(
        'apiUrl' => rest_url('stepfox-ai/v1/generate'),
        'nonce' => wp_create_nonce('wp_rest'),
    ));

    // Register the block with dynamic rendering
    register_block_type('stepfox-ai/console-runner', array(
        'editor_script' => 'stepfox-ai-console-runner',
        'editor_style' => 'stepfox-ai-console-runner-editor',
        'render_callback' => 'stepfox_ai_render_console_runner_block',
        'attributes' => array(
            'promptContent' => array(
                'type' => 'string',
                'default' => ''
            ),
            'codeContent' => array(
                'type' => 'string', 
                'default' => ''
            )
        )
    ));
}
add_action('init', 'stepfox_ai_register_console_runner_block');

/**
 * Render callback for the block
 */
function stepfox_ai_render_console_runner_block($attributes, $content) {
    // For frontend, just show the code in a pre block
    $output = '<div class="stepfox-ai-output">';
    
    if (!empty($attributes['promptContent'])) {
        $output .= '<p class="stepfox-ai-prompt"><strong>Prompt:</strong> ' . esc_html($attributes['promptContent']) . '</p>';
    }
    
    if (!empty($attributes['codeContent'])) {
        $output .= '<pre class="stepfox-ai-code"><code>' . esc_html($attributes['codeContent']) . '</code></pre>';
    }
    
    $output .= '</div>';
    
    return $output;
}
