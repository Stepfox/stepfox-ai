<?php
/**
 * Three.js Block (moved from Stepfox Looks)
 * A Gutenberg block providing a large code textarea to paste/run Three.js scripts.
 *
 * @package stepfox-ai
 */

if (!defined('ABSPATH')) {
    exit;
}

$render_file = STEPFOX_AI_PLUGIN_DIR . 'blocks/three-js/three-js-render.php';
if (file_exists($render_file)) {
    require_once $render_file;
}

// Load REST proxy specific to Three.js block
$proxy_file = STEPFOX_AI_PLUGIN_DIR . 'blocks/three-js/rest-proxy.php';
if (file_exists($proxy_file)) {
    require_once $proxy_file;
}

add_action('init', 'stepfox_ai_register_three_js_block', 20);

function stepfox_ai_register_three_js_block() {
    wp_register_script(
        'stepfox-ai-three-js-editor',
        STEPFOX_AI_PLUGIN_URL . 'blocks/three-js/three-js-editor.js',
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'),
        STEPFOX_AI_VERSION,
        true
    );

    // Pass local fallback URLs for Three.js and runner URL to the editor
    // Use centralized plugin-local Three.js path for easier management.
    wp_localize_script(
        'stepfox-ai-three-js-editor',
        'stepfoxThreeJs',
        array(
            'localPlugin' => trailingslashit(STEPFOX_AI_PLUGIN_URL . 'blocks/three-js') . 'three.min.js',
            'localTheme'  => '',
            'runner'      => trailingslashit(STEPFOX_AI_PLUGIN_URL . 'blocks/three-js') . 'three-runner.html',
        )
    );

    $attributes = array(
        'code' => array(
            'type' => 'string',
            'default' => ''
        ),
        'height' => array(
            'type' => 'number',
            'default' => 480
        ),
        'background' => array(
            'type' => 'string',
            'default' => 'transparent'
        ),
        'autoLoadThree' => array(
            'type' => 'boolean',
            'default' => true
        ),
        // Kill any existing iframe on rerender to reduce WebGL context warnings
        'key' => array(
            'type' => 'string',
            'default' => ''
        ),
        'className' => array(
            'type' => 'string',
            'default' => ''
        ),
    );

    wp_register_script(
        'stepfox-ai-three-inline-runner',
        STEPFOX_AI_PLUGIN_URL . 'blocks/three-js/inline-runner.js',
        array(),
        STEPFOX_AI_VERSION,
        true
    );

    // Ensure the inline runner is available inside the block editor for live preview
    add_action('enqueue_block_editor_assets', function(){
        wp_enqueue_script('stepfox-ai-three-inline-runner');
    });

    register_block_type('stepfox/three-js', array(
        'render_callback' => 'stepfox_ai_render_three_js_block_inline',
        'attributes' => $attributes,
        'editor_script' => 'stepfox-ai-three-js-editor',
        'view_script_handles' => array('stepfox-ai-three-inline-runner'),
        'supports' => array(
            'anchor' => true,
            'customClassName' => true,
        ),
    ));
}


