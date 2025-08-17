<?php
// Lightweight test runner for GPT‑5 models via the plugin's REST handler
// Usage (from browser):
//   /wp-content/plugins/stepfox-ai/test-gpt5.php?model=gpt-5&prompt=Say%20hello
// Defaults: model=gpt-5, prompt="Return the string TEST"

// Load WordPress (robust search for wp-load.php up the tree)
$search = __DIR__;
$loaded = false;
for ($i = 0; $i < 8; $i++) {
    if (file_exists($search . '/wp-load.php')) {
        require_once $search . '/wp-load.php';
        $loaded = true;
        break;
    }
    $search = dirname($search);
}
if (!$loaded) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Could not locate wp-load.php starting from ' . __DIR__), JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: application/json');

// Read parameters
$model  = isset($_GET['model']) && is_string($_GET['model']) ? trim($_GET['model']) : 'gpt-5';
$prompt = isset($_GET['prompt']) && is_string($_GET['prompt']) ? trim($_GET['prompt']) : 'Return the string TEST';

// Preserve and temporarily override selected model
$option_key = 'stepfox_ai_openai_model';
$prev_model = get_option($option_key, 'gpt-3.5-turbo');
update_option($option_key, $model);

try {
    // Ensure class is loaded (plugin should have loaded it already)
    if (!class_exists('StepFox_AI_API')) {
        require_once __DIR__ . '/includes/class-stepfox-ai-api.php';
    }

    $api = new StepFox_AI_API('stepfox-ai', '1.0.0');
    // Build a fake REST request
    $request = new WP_REST_Request('POST', '/stepfox-ai/v1/generate');
    $request->set_param('prompt', $prompt);
    $request->set_param('images', array());

    $response = $api->handle_generate_request($request);

    if (is_wp_error($response)) {
        $out = array(
            'ok' => false,
            'error' => $response->get_error_message(),
        );
        echo json_encode($out, JSON_PRETTY_PRINT);
    } else {
        // WP_REST_Response
        $data = $response->get_data();
        $out = array(
            'ok' => true,
            'requested_model' => $model,
            'model_used' => isset($data['model_used']) ? $data['model_used'] : '(not reported)',
            'api' => isset($data['api']) ? $data['api'] : '(unknown)',
            'code_preview' => isset($data['code']) ? substr($data['code'], 0, 300) : '',
            'usage' => isset($data['usage']) ? $data['usage'] : null,
        );
        echo json_encode($out, JSON_PRETTY_PRINT);
    }
} catch (Throwable $e) {
    $out = array(
        'ok' => false,
        'error' => $e->getMessage(),
    );
    echo json_encode($out, JSON_PRETTY_PRINT);
}
// Restore previous model
update_option($option_key, $prev_model);
exit;


