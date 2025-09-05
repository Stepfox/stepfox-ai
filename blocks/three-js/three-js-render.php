<?php
if (!defined('ABSPATH')) { exit; }

function stepfox_ai_render_three_js_block_inline($attributes, $content, $block) {
    $code = isset($attributes['code']) ? $attributes['code'] : '';
    $height = isset($attributes['height']) ? intval($attributes['height']) : 480;
    $autoLoad = !empty($attributes['autoLoadThree']);

    $container_id = 'sfl-threejs-' . wp_generate_uuid4();

    // Centralized plugin-local Three.js path for fallback
    $localPlugin = trailingslashit( STEPFOX_AI_PLUGIN_URL . 'blocks/three-js' ) . 'three.min.js';
    $localTheme  = '';

    $payload = array(
        'code' => $code,
        'autoLoadThree' => $autoLoad,
        'local1' => $localPlugin,
        'local2' => $localTheme,
        'isHtml' => (bool) preg_match('/<(?:!doctype|html|head|body|meta|link|style|script)/i', $code),
    );
    $json = wp_json_encode($payload);

    $out  = '<div class="sfa-three-inline sfl-threejs-block" style="position:relative;height:' . esc_attr($height) . 'px;">';
    $out .= '<div class="sfa-three-canvas" style="position:absolute;inset:0;"></div>';
    $out .= '<div data-payload="' . esc_attr($json) . '" style="display:none"></div>';
    $out .= '</div>';

    return $out;
}


