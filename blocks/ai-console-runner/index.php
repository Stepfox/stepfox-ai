<?php
/**
 * Register AI Console Runner block
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// (test block removed)

/**
 * Register the block on the server side
 */
function stepfox_ai_register_console_runner_block() {
    // Register block editor script (use the enhanced basic implementation)
    wp_register_script(
        'stepfox-ai-console-runner',
        STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/ai-console-runner-basic.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'),
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
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fallbackNonce' => wp_create_nonce('stepfox_ai_fallback_nonce'),
        'model' => get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo'),
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
    // Frontend rendering: if codeContent contains WordPress block markup, render it as blocks.
    $code = isset($attributes['codeContent']) ? (string) $attributes['codeContent'] : '';

    // If no code, render nothing
    if ($code === '') {
        return '';
    }

    // If AI returned block markup (<!-- wp:... -->) render via do_blocks
    if (stripos($code, '<!-- wp:') !== false) {
        // Ensure core function exists
        if (function_exists('do_blocks')) {
            // Attempt to normalize malformed block markup by parsing and re-serializing
            $normalized = $code;
            if (function_exists('parse_blocks') && function_exists('serialize_block')) {
                $blocks = parse_blocks($code);
                if (is_array($blocks) && !empty($blocks)) {
                    $normalized = '';
                    foreach ($blocks as $b) {
                        $normalized .= serialize_block($b);
                    }
                }
            }
            return '<div class="stepfox-ai-output">' . do_blocks($normalized) . '</div>';
        }
    }

    // Otherwise, treat as plain HTML (sanitize for safety)
    return '<div class="stepfox-ai-output">' . wp_kses_post($code) . '</div>';
}
