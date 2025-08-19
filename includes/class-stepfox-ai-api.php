<?php
/**
 * The API handler for OpenAI requests.
 *
 * @since      1.0.0
 * @package    StepFox_AI
 * @subpackage StepFox_AI/includes
 */

class StepFox_AI_API {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * OpenAI API endpoint
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $openai_api_url    The OpenAI API endpoint.
     */
    private $openai_api_url = 'https://api.openai.com/v1/chat/completions';
    private $openai_responses_url = 'https://api.openai.com/v1/responses';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of the plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register REST API routes
     *
     * @since    1.0.0
     */
    public function register_rest_routes() {
        register_rest_route('stepfox-ai/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_generate_request'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'prompt' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param);
                    }
                ),
                'images' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array()
                ),
                'async' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true
                ),
            ),
        ));

        // Poll job status
        register_rest_route('stepfox-ai/v1', '/job', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_job_status'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string'
                ),
            ),
        ));

        // Cancel job
        register_rest_route('stepfox-ai/v1', '/job/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_job'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string'
                ),
            ),
        ));

        // Delete job
        register_rest_route('stepfox-ai/v1', '/job/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_job'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string'
                ),
            ),
        ));

        // Run job immediately (fallback for environments without loopback/cron)
        register_rest_route('stepfox-ai/v1', '/job/run', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_job_rest'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string'
                ),
            ),
        ));

        // Register background processor via AJAX for non-blocking execution
        add_action('wp_ajax_stepfox_ai_process_job', array($this, 'process_job'));
        add_action('wp_ajax_nopriv_stepfox_ai_process_job', array($this, 'process_job'));

        // Also register a WP-Cron event handler as a robust fallback when loopback HTTP is blocked
        add_action('stepfox_ai_process_job_event', array($this, 'process_job_cron'), 10, 1);
    }

    /**
     * Check if the user has permission to use the API
     *
     * @since    1.0.0
     * @return   bool
     */
    public function check_permission($request) {
        // Check if user is authenticated
        if (current_user_can('edit_posts') || current_user_can('manage_options')) {
            return true;
        }
        
        // Fallback: Check if the request is coming from the block editor
        // This helps with some local development environments
        $referer = wp_get_referer();
        if ($referer && strpos($referer, 'post.php') !== false && is_user_logged_in()) {
            return true;
        }
        
        // Additional check for REST API authentication issues
        // Check if nonce is valid (which means user is authenticated)
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            if (is_user_logged_in()) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle the generate request
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    The REST request object.
     * @return   WP_REST_Response|WP_Error
     */
    public function handle_generate_request($request) {
        // Extend PHP execution time for long-running AI requests
        @set_time_limit(6000); // 100 minutes
        @ini_set('max_execution_time', 6000);
        
        $prompt = $request->get_param('prompt');
        $images = $request->get_param('images');
        $is_async = filter_var($request->get_param('async'), FILTER_VALIDATE_BOOLEAN);
        $api_key = get_option('stepfox_ai_openai_api_key', '');
        $model = get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo');

        // Debug logging (only log essential info)
        error_log('StepFox AI - Generate request received. Model: ' . $model);

        // Check if API key is configured
        if (empty($api_key)) {
            error_log('StepFox AI - No API key configured');
            return new WP_Error(
                'no_api_key',
                __('OpenAI API key is not configured. Please configure it in the StepFox AI settings.', 'stepfox-ai'),
                array('status' => 400)
            );
        }

        // If async, enqueue background task and return immediately
        if ($is_async) {
            $job_id = wp_generate_uuid4();
            set_transient('stepfox_ai_job_' . $job_id, array(
                'status' => 'queued',
                'created' => time()
            ), 15 * MINUTE_IN_SECONDS);
            set_transient('stepfox_ai_payload_' . $job_id, array(
                'prompt' => $prompt,
                'images' => $images,
            ), 15 * MINUTE_IN_SECONDS);

            error_log('StepFox AI - Async job queued: ' . $job_id . ' | Prompt length: ' . strlen($prompt) . ' | Images: ' . (is_array($images) ? count($images) : 0));

            // Fire background AJAX to process the job (non-blocking)
            wp_remote_post(admin_url('admin-ajax.php'), array(
                'timeout' => 1, // allow connection to establish
                'blocking' => false,
                'sslverify' => false,
                'body' => array(
                    'action' => 'stepfox_ai_process_job',
                    'job_id' => $job_id,
                ),
            ));

            // Schedule immediate WP-Cron fallback (runs on next hit) in case loopback HTTP is blocked
            if (!wp_next_scheduled('stepfox_ai_process_job_event', array($job_id))) {
                wp_schedule_single_event(time(), 'stepfox_ai_process_job_event', array($job_id));
            }

            // Try to trigger WP-Cron immediately via loopback request (non-blocking)
            $cron_url = site_url('wp-cron.php?doing_wp_cron=' . urlencode(microtime(true)));
            wp_remote_post($cron_url, array('timeout' => 1, 'blocking' => false, 'sslverify' => false));

            return new WP_REST_Response(array(
                'success' => true,
                'async' => true,
                'job_id' => $job_id,
            ), 200);
        }

        // Prepare the system prompt based on model capabilities
        // Ensure variable is defined before use for logging
        if (!isset($uses_responses_api)) { $uses_responses_api = false; }
        // Log the model selected so frontend can see it in console
        error_log('StepFox AI - Selected model: ' . $model . ' (will use ' . ($uses_responses_api ? 'Responses API' : 'Chat Completions') . ')');
        // System prompt from settings (optional). If empty, we default to a minimal guardrail.
        $settings_system_prompt = trim((string) get_option('stepfox_ai_system_prompt', ''));
        if ($settings_system_prompt === '') {
            $base_prompt = 'You are a helpful assistant that returns only raw code (no prose). Output must be valid WordPress block markup.';
        } else {
            $base_prompt = $settings_system_prompt;
        }
        
        // Define vision-capable models (GPT-5 conditional via setting)
        $gpt5_images_enabled = (bool) get_option('stepfox_ai_gpt5_images', false);
        $vision_models = array(
            'gpt-4-vision-preview', 
            'gpt-4o', 
            'gpt-4o-mini'
        );
        if ($gpt5_images_enabled) {
            $vision_models = array_merge($vision_models, array('gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-chat-latest'));
        }
        
        // GPT-5 models are available via API aliases; log selection
        $gpt5_models = array('gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-chat-latest');
        if (in_array($model, $gpt5_models)) {
            error_log('StepFox AI - Using GPT-5 model: ' . $model);
        }
        
        // Add vision-specific instructions for vision-capable models
        if (in_array($model, $vision_models) && !empty($images)) {
            $vision_addendum = 'You can analyze provided images. Return only valid WordPress block markup (no prose). ' .
                'When extracting text or describing images, create appropriate blocks (paragraph/heading/list/etc.). ' .
                'If an image should be placed, use wp:image or wp:cover and include the given URL.';
            if ($settings_system_prompt === '') {
                // No custom prompt: use a concise default geared for vision
                $base_prompt = 'You are an expert JavaScript, HTML, and WordPress block editor developer with image analysis capabilities. ' . $vision_addendum;
            } else {
                // Append vision guidance to the saved system prompt (do not overwrite)
                $base_prompt = $settings_system_prompt . "\n\n" . $vision_addendum;
            }
        }
        
        // The effective system prompt is whatever is saved in settings; we append the user prompt below.
        $system_prompt = $base_prompt;
        $system_prompt_length = strlen($system_prompt);
        $system_prompt_preview = substr($system_prompt, 0, 1200);
        error_log('StepFox AI - System prompt length: ' . $system_prompt_length);
        
        // Add image context if images are provided
        if (!empty($images) && is_array($images)) {
            $image_context = "\n\nImages provided in the request:";
            foreach ($images as $index => $image) {
                $image_num = $index + 1;
                $image_url = isset($image['url']) ? $image['url'] : '';
                $image_alt = isset($image['alt']) ? $image['alt'] : '';
                $image_title = isset($image['title']) ? $image['title'] : '';
                $image_filename = isset($image['filename']) ? $image['filename'] : '';
                
                $image_context .= "\n\nImage {$image_num}:";
                if (!empty($image_title)) {
                    $image_context .= "\n- Title: {$image_title}";
                }
                if (!empty($image_alt)) {
                    $image_context .= "\n- Alt text: {$image_alt}";
                }
                if (!empty($image_filename)) {
                    $image_context .= "\n- Filename: {$image_filename}";
                }
                if (!empty($image_url)) {
                    $image_context .= "\n- URL: {$image_url}";
                }
            }
            
            $system_prompt .= $image_context;
            $system_prompt .= "\n\nWhen generating WordPress blocks, use the provided images in appropriate blocks like wp:image, wp:cover (with useFeaturedImage or url attribute), wp:media-text, etc. Use the exact URLs provided.";
        }

        // Determine if this model should use the Responses API (GPT-5 text family)
        $gpt5_text_models = array('gpt-5', 'gpt-5-mini', 'gpt-5-nano');
        $uses_responses_api = in_array($model, $gpt5_text_models);
        // If GPT‑5 images are enabled and images are present, force Chat Completions (messages) instead of Responses API
        $force_chat_for_images = ($gpt5_images_enabled && in_array($model, $gpt5_text_models, true) && !empty($images));
        if ($force_chat_for_images) {
            // Ensure both endpoint and body builder use Chat schema
            $uses_responses_api = false;
        }

        // Prepare the request body based on model type
        $has_images = !empty($images) && is_array($images);
        if ($uses_responses_api) {
            // Responses API schema with input content blocks
            $content = array(
                array('type' => 'input_text', 'text' => trim($system_prompt . "\n\n" . $prompt))
            );
            if ($has_images) {
                foreach ($images as $image) {
                    if (!isset($image['url'])) { continue; }
                    $image_url = $image['url'];
                    $is_local = $this->is_local_url($image_url);
                    $final_url = $is_local ? $this->convert_local_image_to_base64($image_url) : $image_url;
                    if (!$final_url) { continue; }
                    $content[] = array(
                        'type' => 'input_image',
                        'image_url' => array('url' => $final_url)
                    );
                }
            }
            $body = array(
                'model' => $model,
                'input' => array(
                    array('role' => 'user', 'content' => $content)
                ),
                'text' => array('format' => array('type' => 'text')),
                'max_output_tokens' => 20000,
            );
            if (!$this->is_temperature_restricted_model($model)) {
                $body['temperature'] = 0.7;
            }
        } else {
            // Chat Completions schema
            $max_tokens_param = $this->get_max_tokens_parameter($model);
            if (in_array($model, $vision_models) && $has_images) {
                // user content array: text + image_url parts
                $user_content = array(array('type' => 'text', 'text' => trim($system_prompt . "\n\n" . $prompt)));
                foreach ($images as $image) {
                    if (!isset($image['url'])) { continue; }
                    $image_url = $image['url'];
                    $is_local = $this->is_local_url($image_url);
                    $final_url = $is_local ? $this->convert_local_image_to_base64($image_url) : $image_url;
                    if (!$final_url) { continue; }
                    $user_content[] = array('type' => 'image_url', 'image_url' => array('url' => $final_url, 'detail' => 'auto'));
                }
                $body = array(
                    'model' => $model,
                    'messages' => array(array('role' => 'user', 'content' => $user_content)),
                    $max_tokens_param => 16000,
                );
            } else {
                $body = array(
                    'model' => $model,
                    'messages' => array(
                        array('role' => 'system', 'content' => $system_prompt),
                        array('role' => 'user', 'content' => $prompt)
                    ),
                    $max_tokens_param => 16000,
                );
            }
            if (!$this->is_temperature_restricted_model($model)) {
                $body['temperature'] = 0.7;
            }
        }

        // Release PHP session locks before long-running external call to avoid freezing other requests for this user
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        // Make the API request with extended timeout for complex generations
        $target_url = ($uses_responses_api && !$force_chat_for_images) ? $this->openai_responses_url : $this->openai_api_url;
        $response = wp_remote_post($target_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 6000, // allow long generations
            'blocking' => true,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to connect to OpenAI API: %s', 'stepfox-ai'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        // Re-open session after network call if needed
        if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
            // Do not start a session; WordPress typically doesn't use PHP sessions. Just ensure we didn't leave it open.
        }
        $response_data = json_decode($response_body, true);

        // Check for API errors
        if ($response_code !== 200) {
            $error_message = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : __('Unknown API error', 'stepfox-ai');
            
            // Log error details for debugging intermittent issues
            error_log('StepFox AI - API Error: ' . $error_message . ' (HTTP ' . $response_code . ')');
            error_log('StepFox AI - Model: ' . $model);
            error_log('StepFox AI - Prompt length (user input only): ' . strlen($prompt));
            
            // Check for rate limiting
            if ($response_code === 429 || stripos($error_message, 'rate') !== false) {
                $error_message = __('Rate limit exceeded. Please wait 20 seconds and try again.', 'stepfox-ai');
            }
            // Check for quota issues
            elseif (stripos($error_message, 'quota') !== false || stripos($error_message, 'billing') !== false) {
                $error_message = __('API quota exceeded. Check your OpenAI account billing.', 'stepfox-ai');
            }
            // Check for model issues
            elseif (stripos($error_message, 'model') !== false && stripos($error_message, 'not found') !== false) {
                $error_message = __('Model not available. Please select a different model in settings.', 'stepfox-ai');
            }
            
            return new WP_Error(
                'openai_error',
                sprintf(__('OpenAI API Error: %s', 'stepfox-ai'), $error_message),
                array('status' => $response_code)
            );
        }

        // Extract the generated code
        $generated_code = '';
        $model_used = $model;
        if ($uses_responses_api) {
            // Responses API: prefer consolidated output_text; fallback to text array under output[...]
            if (!empty($response_data['output_text']) && is_string($response_data['output_text'])) {
                $generated_code = $response_data['output_text'];
            } elseif (!empty($response_data['output']) && is_array($response_data['output'])) {
                // Traverse output array to find first text item
                foreach ($response_data['output'] as $out) {
                    if (isset($out['content']) && is_array($out['content'])) {
                        foreach ($out['content'] as $contentItem) {
                            if (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                                $generated_code = $contentItem['text'];
                                break 2;
                            }
                            if (isset($contentItem['text'])) {
                                $generated_code = $contentItem['text'];
                                break 2;
                            }
                        }
                    }
                }
            }
        } else if (isset($response_data['choices'][0]['message']['content'])) {
            $generated_code = $response_data['choices'][0]['message']['content'];
            
            // Clean up any markdown formatting if present
            $generated_code = preg_replace('/^```(javascript|js|html)?\n|```$/m', '', $generated_code);
            $generated_code = trim($generated_code);
        }

        // If the model returned an empty string, attempt limited retries with the same model
        if ($generated_code === '') {
            for ($attempt = 1; $attempt <= 2 && $generated_code === ''; $attempt++) {
                error_log('StepFox AI - Empty generation; retry attempt ' . $attempt . ' with model ' . $model);
                $retry_response = wp_remote_post($target_url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                    ),
                    'body' => json_encode($body),
                    'timeout' => 6000,
                    'blocking' => true,
                ));
                if (!is_wp_error($retry_response)) {
                    $retry_code = wp_remote_retrieve_response_code($retry_response);
                    $retry_body = wp_remote_retrieve_body($retry_response);
                    $retry_data = json_decode($retry_body, true);
                    if ($retry_code === 200) {
                        if ($uses_responses_api) {
                            if (!empty($retry_data['output_text']) && is_string($retry_data['output_text'])) {
                                $generated_code = $retry_data['output_text'];
                            } elseif (!empty($retry_data['output']) && is_array($retry_data['output'])) {
                                foreach ($retry_data['output'] as $out) {
                                    if (isset($out['content']) && is_array($out['content'])) {
                                        foreach ($out['content'] as $contentItem) {
                                            if (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                                                $generated_code = $contentItem['text'];
                                                break 2;
                                            }
                                            if (isset($contentItem['text'])) {
                                                $generated_code = $contentItem['text'];
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        } else if (isset($retry_data['choices'][0]['message']['content'])) {
                            $generated_code = trim(preg_replace('/^```(javascript|js|html)?\n|```$/m', '', $retry_data['choices'][0]['message']['content']));
                        }
                    }
                }
            }
        }

        // If still empty after retries, do not fallback to another model
        if ($generated_code === '') {
            $snapshot = substr(json_encode($response_data), 0, 2000);
            error_log('StepFox AI - Empty generation received from model ' . $model . ' (no fallback). Snapshot: ' . $snapshot);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('The model returned an empty response. Try a shorter prompt, fewer/smaller images, or switch to a lighter model. You can also retry in a few seconds.', 'stepfox-ai'),
                'snapshot' => $snapshot,
            ), 502);
        }

        // First, normalize inline styles to match Gutenberg save output (expand shorthands, strip invalid values).
        // IMPORTANT: Do NOT touch block JSON attributes; we only edit inline CSS in the HTML wrappers.
        if (!empty($generated_code)) {
            $generated_code = $this->normalize_inline_styles($generated_code);
            $generated_code = $this->normalize_block_classes($generated_code);
        }

        // Normalize the generated markup to canonical Gutenberg output to avoid validation errors.
        // We preserve all original block attributes as returned by parse_blocks, then serialize back.
        if (!empty($generated_code) && function_exists('parse_blocks') && function_exists('serialize_block')) {
            $blocks = parse_blocks($generated_code);
            if (is_array($blocks) && !empty($blocks)) {
                $normalized = '';
                foreach ($blocks as $b) {
                    $normalized .= serialize_block($b);
                }
                if (!empty($normalized)) {
                    $generated_code = $normalized;
                }
            }
        }

        // Return the response
        return new WP_REST_Response(array(
            'success' => true,
            'code' => $generated_code,
            'usage' => isset($response_data['usage']) ? $response_data['usage'] : null,
            'model_used' => $model_used,
            'api' => $uses_responses_api ? 'responses' : 'chat',
            'prompt_length' => $system_prompt_length,
            'prompt_preview' => $system_prompt_preview,
            // Debug fields for editor console logging
            'system_prompt_full' => $system_prompt,
            'user_prompt_sent' => (string) $prompt,
        ), 200);
    }

    /**
     * AJAX background processor: runs the OpenAI generation and stores result in a transient.
     */
    public function process_job() {
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        if (empty($job_id)) { wp_die(); }
        $this->run_job($job_id);
        wp_die();
    }

    /**
     * Cron-compatible processor
     */
    public function process_job_cron($job_id) {
        $job_id = sanitize_text_field($job_id);
        if (empty($job_id)) { return; }
        $this->run_job($job_id);
    }

    /**
     * Execute job logic (shared by AJAX and Cron)
     */
    private function run_job($job_id) {
        // If job was canceled, abort
        $current = get_transient('stepfox_ai_job_' . $job_id);
        if ($current && isset($current['status']) && $current['status'] === 'canceled') {
            return;
        }

        // Mark as processing
        set_transient('stepfox_ai_job_' . $job_id, array('status' => 'processing', 'created' => isset($current['created']) ? $current['created'] : time()), 15 * MINUTE_IN_SECONDS);

        $payload = get_transient('stepfox_ai_payload_' . $job_id);
        if (!$payload || empty($payload['prompt'])) { set_transient('stepfox_ai_job_' . $job_id, array('status'=>'error','message'=>'Missing payload'), 5*MINUTE_IN_SECONDS); error_log('StepFox AI - Job ' . $job_id . ' missing payload'); return; }
        error_log('StepFox AI - Processing job: ' . $job_id);

        // Build a pseudo request object to reuse main logic synchronously
        $request = new WP_REST_Request('POST', '/stepfox-ai/v1/generate');
        $request->set_param('prompt', $payload['prompt']);
        $request->set_param('images', $payload['images']);
        $request->set_param('async', false);

        $result = $this->handle_generate_request($request);
        $data = is_wp_error($result) ? array('success'=>false,'message'=>$result->get_error_message()) : $result->get_data();

        set_transient('stepfox_ai_job_' . $job_id, array(
            'status' => 'done',
            'data' => $data,
            'finished' => time()
        ), 15 * MINUTE_IN_SECONDS);
        error_log('StepFox AI - Job finished: ' . $job_id . ' | success=' . (isset($data['success']) ? var_export($data['success'], true) : 'n/a'));
        delete_transient('stepfox_ai_payload_' . $job_id);
    }

    /**
     * REST wrapper to run a queued job immediately (manual trigger)
     */
    public function run_job_rest($request) {
        $job_id = sanitize_text_field($request->get_param('job_id'));
        if (empty($job_id)) {
            return new WP_REST_Response(array('success'=>false,'message'=>'Missing job_id'), 400);
        }
        $this->run_job($job_id);
        return new WP_REST_Response(array('success'=>true), 200);
    }

    /**
     * Cancel a queued/processing job
     */
    public function cancel_job($request) {
        $job_id = sanitize_text_field($request->get_param('job_id'));
        if (empty($job_id)) {
            return new WP_REST_Response(array('success'=>false,'message'=>'Missing job_id'), 400);
        }
        delete_transient('stepfox_ai_payload_' . $job_id);
        set_transient('stepfox_ai_job_' . $job_id, array('status'=>'canceled','finished'=>time()), 5 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(array('success'=>true,'status'=>'canceled'), 200);
    }

    /**
     * Delete a job (remove transients)
     */
    public function delete_job($request) {
        $job_id = sanitize_text_field($request->get_param('job_id'));
        if (empty($job_id)) {
            return new WP_REST_Response(array('success'=>false,'message'=>'Missing job_id'), 400);
        }
        delete_transient('stepfox_ai_payload_' . $job_id);
        delete_transient('stepfox_ai_job_' . $job_id);
        return new WP_REST_Response(array('success'=>true,'deleted'=>true), 200);
    }

    /**
     * Get job status for polling
     */
    public function get_job_status($request) {
        $job_id = sanitize_text_field($request->get_param('job_id'));
        $state = get_transient('stepfox_ai_job_' . $job_id);
        if (!$state) {
            return new WP_REST_Response(array('success'=>false,'status'=>'missing'), 200);
        }
        return new WP_REST_Response(array('success'=>true) + $state, 200);
    }
    
    /**
     * Normalize inline styles for better block validation compatibility
     * - Expand padding/margin shorthand to individual sides
     * - Remove obviously invalid values like [object Object]
     */
    private function normalize_inline_styles(string $html): string {
        return preg_replace_callback('/style="([^"]*)"/i', function($m) {
            $style = $m[1];
            // Remove placeholders like [object Object]
            $style = str_replace('[object Object]', '', $style);

            // Parse declarations
            $decls = array();
            foreach (explode(';', $style) as $pair) {
                $pair = trim($pair);
                if ($pair === '') { continue; }
                $parts = explode(':', $pair, 2);
                if (count($parts) !== 2) { continue; }
                $prop = trim(strtolower($parts[0]));
                $val  = trim($parts[1]);
                if ($val === '') { continue; }
                $decls[$prop] = $val;
            }

            // Expand padding shorthand
            if (isset($decls['padding']) && !isset($decls['padding-top']) && !isset($decls['padding-right']) && !isset($decls['padding-bottom']) && !isset($decls['padding-left'])) {
                $vals = preg_split('/\s+/', $decls['padding']);
                $t = $r = $b = $l = $vals[0] ?? '0';
                if (count($vals) === 2) { $b = $t = $vals[0]; $r = $l = $vals[1]; }
                elseif (count($vals) === 3) { $t = $vals[0]; $r = $l = $vals[1]; $b = $vals[2]; }
                elseif (count($vals) >= 4) { $t = $vals[0]; $r = $vals[1]; $b = $vals[2]; $l = $vals[3]; }
                $decls['padding-top']    = $t;
                $decls['padding-right']  = $r;
                $decls['padding-bottom'] = $b;
                $decls['padding-left']   = $l;
                unset($decls['padding']);
            }

            // Expand margin shorthand
            if (isset($decls['margin']) && !isset($decls['margin-top']) && !isset($decls['margin-right']) && !isset($decls['margin-bottom']) && !isset($decls['margin-left'])) {
                $vals = preg_split('/\s+/', $decls['margin']);
                $t = $r = $b = $l = $vals[0] ?? '0';
                if (count($vals) === 2) { $b = $t = $vals[0]; $r = $l = $vals[1]; }
                elseif (count($vals) === 3) { $t = $vals[0]; $r = $l = $vals[1]; $b = $vals[2]; }
                elseif (count($vals) >= 4) { $t = $vals[0]; $r = $vals[1]; $b = $vals[2]; $l = $vals[3]; }
                $decls['margin-top']    = $t;
                $decls['margin-right']  = $r;
                $decls['margin-bottom'] = $b;
                $decls['margin-left']   = $l;
                unset($decls['margin']);
            }

            // Rebuild style string preserving order of common properties first
            $order = array(
                'border-width','border-style','border-color','border-radius',
                'padding-top','padding-right','padding-bottom','padding-left',
                'margin-top','margin-right','margin-bottom','margin-left',
                'box-shadow','background','background-color','background-image'
            );
            $rebuilt = array();
            foreach ($order as $prop) {
                if (isset($decls[$prop]) && $decls[$prop] !== '') {
                    $rebuilt[] = $prop . ':' . $decls[$prop];
                    unset($decls[$prop]);
                }
            }
            // Append remaining declarations
            foreach ($decls as $prop => $val) {
                if ($val === '') { continue; }
                $rebuilt[] = $prop . ':' . $val;
            }

            $new = implode(';', $rebuilt);
            if ($new !== '' && substr($new, -1) !== ';') {
                $new .= ';';
            }
            return 'style="' . esc_attr($new) . '"';
        }, $html);
    }

    /**
     * Normalize classes for specific core blocks to satisfy Gutenberg validation
     * - core/button: ensure wp-element-button and has-custom-font-size/has-text-color/has-background
     * - core/group: ensure has-background / has-text-color when inline background/color present
     */
    private function normalize_block_classes(string $html): string {
        // Button <a class="wp-block-button__link ..." ... style="...">
        $html = preg_replace_callback('/<a([^>]*)class="([^"]*\bwp-block-button__link\b[^"]*)"([^>]*)>(.*?)<\/a>/is', function($m) {
            $before = $m[1];
            $classes = $m[2];
            $after  = $m[3];
            $inner  = $m[4];

            // Ensure wp-element-button
            if (strpos($classes, 'wp-element-button') === false) {
                $classes .= ' wp-element-button';
            }

            // Inspect style for hints
            $styleAttr = '';
            if (preg_match('/style="([^"]*)"/i', $before . $after, $sm)) {
                $styleAttr = strtolower($sm[1]);
            }
            if ($styleAttr !== '') {
                if (strpos($styleAttr, 'font-size') !== false && strpos($classes, 'has-custom-font-size') === false) {
                    $classes .= ' has-custom-font-size';
                }
                if ((strpos($styleAttr, 'background:') !== false || strpos($styleAttr, 'background-color:') !== false) && strpos($classes, 'has-background') === false) {
                    $classes .= ' has-background';
                }
                if (strpos($styleAttr, 'color:') !== false && strpos($classes, 'has-text-color') === false) {
                    $classes .= ' has-text-color';
                }
            }
            return '<a' . $before . 'class="' . trim($classes) . '"' . $after . '>' . $inner . '</a>';
        }, $html);

        // Group <div class="wp-block-group ..." style="...">
        $html = preg_replace_callback('/<div([^>]*)class="([^"]*\bwp-block-group\b[^"]*)"([^>]*)>/i', function($m) {
            $before = $m[1];
            $classes = $m[2];
            $after  = $m[3];
            $styleAttr = '';
            if (preg_match('/style="([^"]*)"/i', $before . $after, $sm)) {
                $styleAttr = strtolower($sm[1]);
            }
            if ($styleAttr !== '') {
                if ((strpos($styleAttr, 'background:') !== false || strpos($styleAttr, 'background-color:') !== false) && strpos($classes, 'has-background') === false) {
                    $classes .= ' has-background';
                }
                if (strpos($styleAttr, 'color:') !== false && strpos($classes, 'has-text-color') === false) {
                    $classes .= ' has-text-color';
                }
                if ((strpos($styleAttr, 'border-color:') !== false || strpos($styleAttr, 'border:') !== false) && strpos($classes, 'has-border-color') === false) {
                    $classes .= ' has-border-color';
                }
            }
            return '<div' . $before . 'class="' . trim($classes) . '"' . $after . '>';
        }, $html);

        return $html;
    }

    /**
     * Check if a URL is local/localhost
     *
     * @param string $url The URL to check
     * @return bool True if local, false otherwise
     */
    private function is_local_url($url) {
        $host = parse_url($url, PHP_URL_HOST);
        
        // List of local/development domains
        $local_domains = array(
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.test',
            '.example',
            '.invalid',
            '.localhost',
            '.dev',
            '.loca.lt',
            '.ngrok.io'
        );
        
        // Check if it's a local IP
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            // Check if it's a private IP
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }
        
        // Check against local domains
        foreach ($local_domains as $local_domain) {
            if ($host === trim($local_domain, '.') || substr($host, -strlen($local_domain)) === $local_domain) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Convert a local image to base64 data URL
     *
     * @param string $image_url The local image URL
     * @return string|false Base64 data URL or false on failure
     */
    private function convert_local_image_to_base64($image_url) {
        // Try to get the local file path
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];
        
        // Replace the URL with the file path
        if (strpos($image_url, $base_url) === 0) {
            $relative_path = substr($image_url, strlen($base_url));
            $file_path = $base_dir . $relative_path;
            
            if (file_exists($file_path)) {
                // Check file size (limit to 20MB for base64 encoding)
                $file_size = filesize($file_path);
                if ($file_size > 20 * 1024 * 1024) {
                    error_log('StepFox AI - Image too large for base64 encoding: ' . $file_size . ' bytes');
                    return false;
                }
                
                // Read the file
                $image_data = file_get_contents($file_path);
                if ($image_data !== false) {
                    // Get MIME type
                    $mime_type = mime_content_type($file_path);
                    if (!$mime_type) {
                        // Fallback to extension-based detection
                        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                        $mime_types = array(
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp'
                        );
                        $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'image/jpeg';
                    }
                    
                    // Convert to base64
                    $base64 = base64_encode($image_data);
                    return 'data:' . $mime_type . ';base64,' . $base64;
                } else {
                    error_log('StepFox AI - Failed to read file: ' . $file_path);
                }
            } else {
                error_log('StepFox AI - File does not exist: ' . $file_path);
            }
        } else {
            error_log('StepFox AI - URL not in uploads directory: ' . $image_url);
            
            // Try alternative method using WordPress functions
            $attachment_id = attachment_url_to_postid($image_url);
            if ($attachment_id) {
                $file_path = get_attached_file($attachment_id);
                if ($file_path && file_exists($file_path)) {
                    $image_data = file_get_contents($file_path);
                    if ($image_data !== false) {
                        $mime_type = get_post_mime_type($attachment_id);
                        $base64 = base64_encode($image_data);
                        return 'data:' . $mime_type . ';base64,' . $base64;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get the appropriate max tokens parameter name based on model
     *
     * @param string $model The model name
     * @return string The parameter name to use
     */
    private function get_max_tokens_parameter($model) {
        // Models that use the new max_completion_tokens parameter
        $new_parameter_models = array(
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4-turbo-preview',
            'gpt-5',           
            'gpt-5-mini',      
            'gpt-5-nano',      
            'gpt-5-chat-latest'
        );
        
        // Check if this model uses the new parameter
        if (in_array($model, $new_parameter_models)) {
            return 'max_completion_tokens';
        }
        
        // Default to the classic parameter
        return 'max_tokens';
    }
    
    /**
     * Check if a model doesn't support custom temperature values
     *
     * @param string $model The model name
     * @return bool True if temperature is restricted, false otherwise
     */
    private function is_temperature_restricted_model($model) {
        // Models that only support default temperature value of 1
        // Check if model contains 'o' variant (like gpt-4o, gpt-4o-mini, etc)
        if (strpos($model, 'gpt-4o') !== false) {
            return true;
        }
        
        // Check if it's any GPT-5 model (they may have temperature restrictions)
        if (strpos($model, 'gpt-5') !== false) {
            return true;
        }
        
        // Specific models that only support default temperature
        $restricted_models = array(
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4o-2024-05-13',  // Date-versioned models
            'gpt-4o-2024-08-06',
            'gpt-5',              
            'gpt-5-mini',        
            'gpt-5-nano',             
            'gpt-5-chat-latest'
        );
        
        return in_array($model, $restricted_models);
    }
}
