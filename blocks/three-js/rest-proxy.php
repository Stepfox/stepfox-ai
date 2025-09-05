<?php
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function(){
    register_rest_route('stepfox-ai/v1', '/proxy', array(
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'args' => array(
            'url' => array('required' => true, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw')
        ),
        'callback' => function( WP_REST_Request $request ) {
            $url = $request->get_param('url');
            if (empty($url)) { return new WP_REST_Response(array('error' => 'Missing url'), 400); }
            $parts = wp_parse_url($url);
            if (!$parts || !isset($parts['scheme'], $parts['host'])) { return new WP_REST_Response(array('error' => 'Invalid url'), 400); }
            $scheme = strtolower($parts['scheme']); if ($scheme !== 'http' && $scheme !== 'https') { return new WP_REST_Response(array('error' => 'Only http/https allowed'), 400); }
            $host = strtolower($parts['host']);
            $allowed = array('cdn.jsdelivr.net','unpkg.com','cdnjs.cloudflare.com','raw.githubusercontent.com','geodata.ucdavis.edu','nominatim.openstreetmap.org','github.com','fonts.googleapis.com','fonts.gstatic.com','basemaps.cartocdn.com','tile.openstreetmap.org');
            $ok = false; foreach($allowed as $a){ if ($host===$a || substr($host,-(strlen($a)+1))==='.'. $a){ $ok=true; break; } }
            if (!$ok) { return new WP_REST_Response(array('error' => 'Host not allowed'), 403); }
            $resp = wp_remote_get($url, array('timeout' => 15, 'redirection' => 5, 'headers' => array('User-Agent' => 'Stepfox-AI-Proxy/1.0 (+'.home_url().')')));
            if (is_wp_error($resp)) { return new WP_REST_Response(array('error' => $resp->get_error_message()), 502); }
            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            $ct   = wp_remote_retrieve_header($resp, 'content-type'); if (!$ct) { $ct = 'application/octet-stream'; }
            $out = new WP_REST_Response($body, $code ? intval($code) : 200);
            $out->header('Content-Type', $ct);
            $out->header('Access-Control-Allow-Origin', '*');
            $out->header('Cache-Control', 'public, max-age=3600');
            return $out;
        }
    ));
});


