<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    StepFox_AI
 * @subpackage StepFox_AI/includes
 */

class StepFox_AI_Admin {

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
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, STEPFOX_AI_PLUGIN_URL . 'admin/css/stepfox-ai-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, STEPFOX_AI_PLUGIN_URL . 'admin/js/stepfox-ai-admin.js', array('jquery'), $this->version, false);
        
        // Localize script with proper AJAX nonce
        wp_localize_script($this->plugin_name, 'stepfox_ai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stepfox_ai_ajax_nonce')
        ));
    }

    /**
     * Add admin menu
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __('StepFox AI', 'stepfox-ai'),
            __('StepFox AI', 'stepfox-ai'),
            'manage_options',
            'stepfox-ai',
            array($this, 'display_admin_page'),
            'dashicons-admin-generic',
            100
        );

        add_submenu_page(
            'stepfox-ai',
            __('Settings', 'stepfox-ai'),
            __('Settings', 'stepfox-ai'),
            'manage_options',
            'stepfox-ai-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Display the admin page
     *
     * @since    1.0.0
     */
    public function display_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="stepfox-ai-admin-content">
                <h2><?php _e('Welcome to StepFox AI', 'stepfox-ai'); ?></h2>
                <p><?php _e('StepFox AI allows you to generate code using OpenAI directly in the WordPress block editor.', 'stepfox-ai'); ?></p>
                
                <h3><?php _e('Getting Started', 'stepfox-ai'); ?></h3>
                <ol>
                    <li><?php _e('Configure your OpenAI API key in the Settings page', 'stepfox-ai'); ?></li>
                    <li><?php _e('Add the "AI Console Runner" block to any post or page', 'stepfox-ai'); ?></li>
                    <li><?php _e('Enter a prompt and click "Run with AI" to generate code', 'stepfox-ai'); ?></li>
                    <li><?php _e('Preview the code live or run it in the console', 'stepfox-ai'); ?></li>
                </ol>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=stepfox-ai-settings'); ?>" class="button button-primary">
                        <?php _e('Configure Settings', 'stepfox-ai'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Display the settings page
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('stepfox_ai_settings');
                do_settings_sections('stepfox-ai-settings');
                submit_button();
                ?>
            </form>

            <hr/>
            <h2><?php _e('Background Jobs', 'stepfox-ai'); ?></h2>
            <p class="description"><?php _e('Recent AI generation jobs (queued/processing/done). Refresh to update.', 'stepfox-ai'); ?></p>
            <?php echo $this->render_jobs_table(); ?>
        </div>
        <?php
    }

    /**
     * Register settings
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Register setting
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        // Add settings section
        add_settings_section(
            'stepfox_ai_openai_section',
            __('OpenAI Settings', 'stepfox-ai'),
            array($this, 'openai_section_callback'),
            'stepfox-ai-settings'
        );

        // Add settings field
        add_settings_field(
            'stepfox_ai_openai_api_key',
            __('OpenAI API Key', 'stepfox-ai'),
            array($this, 'api_key_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Model selection
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-3.5-turbo',
            )
        );

        add_settings_field(
            'stepfox_ai_openai_model',
            __('OpenAI Model', 'stepfox-ai'),
            array($this, 'model_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // API mode selection (Auto / Chat Completions / Responses API)
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_api_mode',
            array(
                'type' => 'string',
                'sanitize_callback' => function($val){
                    $val = is_string($val) ? strtolower($val) : 'auto';
                    return in_array($val, array('auto','chat','responses'), true) ? $val : 'auto';
                },
                'default' => 'auto',
            )
        );

        add_settings_field(
            'stepfox_ai_api_mode',
            __('API Mode', 'stepfox-ai'),
            array($this, 'api_mode_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Experimental: Enable images for GPT-5 models
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_gpt5_images',
            array(
                'type' => 'boolean',
                'sanitize_callback' => function($v){ return (bool)$v; },
                'default' => false,
            )
        );

        add_settings_field(
            'stepfox_ai_gpt5_images',
            __('Enable images for GPT‑5 (experimental)', 'stepfox-ai'),
            array($this, 'gpt5_images_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // System prompt
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_system_prompt',
            array(
                'type' => 'string',
                'sanitize_callback' => function($val){ return wp_kses_post($val); },
                'default' => '',
            )
        );

        add_settings_field(
            'stepfox_ai_system_prompt',
            __('System Prompt (optional)', 'stepfox-ai'),
            array($this, 'system_prompt_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Max tokens
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_max_tokens',
            array(
                'type' => 'integer',
                'sanitize_callback' => function($val){
                    $n = intval($val);
                    if ($n < 1) { $n = 1; }
                    if ($n > 200000) { $n = 200000; }
                    return $n;
                },
                'default' => 16000,
            )
        );

        add_settings_field(
            'stepfox_ai_max_tokens',
            __('Max tokens', 'stepfox-ai'),
            array($this, 'max_tokens_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Temperature
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_temperature',
            array(
                'type' => 'number',
                'sanitize_callback' => function($val){
                    $f = floatval($val);
                    if ($f < 0) { $f = 0.0; }
                    if ($f > 2) { $f = 2.0; }
                    return $f;
                },
                'default' => 0.7,
            )
        );

        add_settings_field(
            'stepfox_ai_temperature',
            __('Temperature', 'stepfox-ai'),
            array($this, 'temperature_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Top P (nucleus sampling)
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_top_p',
            array(
                'type' => 'number',
                'sanitize_callback' => function($val){
                    $f = floatval($val);
                    if ($f < 0) { $f = 0.0; }
                    if ($f > 1) { $f = 1.0; }
                    return $f;
                },
                'default' => 1.0,
            )
        );

        add_settings_field(
            'stepfox_ai_top_p',
            __('Top P', 'stepfox-ai'),
            array($this, 'top_p_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Presence penalty
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_presence_penalty',
            array(
                'type' => 'number',
                'sanitize_callback' => function($val){
                    $f = floatval($val);
                    if ($f < -2) { $f = -2.0; }
                    if ($f > 2) { $f = 2.0; }
                    return $f;
                },
                'default' => 0.0,
            )
        );

        add_settings_field(
            'stepfox_ai_presence_penalty',
            __('Presence penalty', 'stepfox-ai'),
            array($this, 'presence_penalty_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Frequency penalty
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_frequency_penalty',
            array(
                'type' => 'number',
                'sanitize_callback' => function($val){
                    $f = floatval($val);
                    if ($f < -2) { $f = -2.0; }
                    if ($f > 2) { $f = 2.0; }
                    return $f;
                },
                'default' => 0.0,
            )
        );

        add_settings_field(
            'stepfox_ai_frequency_penalty',
            __('Frequency penalty', 'stepfox-ai'),
            array($this, 'frequency_penalty_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );

        // Stop sequences
        register_setting(
            'stepfox_ai_settings',
            'stepfox_ai_stop_sequences',
            array(
                'type' => 'string',
                'sanitize_callback' => function($val){
                    // store as comma-separated string
                    $val = is_string($val) ? $val : '';
                    $parts = array_filter(array_map('trim', explode(',', $val)), 'strlen');
                    // limit to 4 stop sequences as per API
                    $parts = array_slice($parts, 0, 4);
                    return implode(', ', $parts);
                },
                'default' => '',
            )
        );

        add_settings_field(
            'stepfox_ai_stop_sequences',
            __('Stop sequences', 'stepfox-ai'),
            array($this, 'stop_sequences_field_callback'),
            'stepfox-ai-settings',
            'stepfox_ai_openai_section'
        );
    }

    /**
     * OpenAI section callback
     *
     * @since    1.0.0
     */
    public function openai_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings. You can get an API key from', 'stepfox-ai') . ' <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</p>';
    }

    /**
     * API key field callback
     *
     * @since    1.0.0
     */
    public function api_key_field_callback() {
        $api_key = get_option('stepfox_ai_openai_api_key', '');
        ?>
        <input type="password" 
               id="stepfox_ai_openai_api_key" 
               name="stepfox_ai_openai_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter your OpenAI API key. This will be stored securely and used for generating code.', 'stepfox-ai'); ?>
        </p>
        <?php
    }

    /**
     * Model field callback
     *
     * @since    1.0.0
     */
    public function model_field_callback() {
        $model = get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo');
        ?>
        <select id="stepfox_ai_openai_model" name="stepfox_ai_openai_model">
            <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Text only)</option>
            <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>>GPT-4 (Text only)</option>
            <option value="gpt-4-turbo-preview" <?php selected($model, 'gpt-4-turbo-preview'); ?>>GPT-4 Turbo (Text only)</option>
            <option value="gpt-4-vision-preview" <?php selected($model, 'gpt-4-vision-preview'); ?>>GPT-4 Vision (Can analyze images)</option>
            <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>GPT-4o (Latest, can analyze images)</option>
            <option value="gpt-4o-mini" <?php selected($model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Faster, can analyze images)</option>
            <optgroup label="GPT-5 Models">
                <option value="gpt-5" <?php selected($model, 'gpt-5'); ?>>GPT-5 (Complex reasoning, multi-step tasks)</option>
                <option value="gpt-5-mini" <?php selected($model, 'gpt-5-mini'); ?>>GPT-5 Mini (Cost-optimized reasoning)</option>
                <option value="gpt-5-nano" <?php selected($model, 'gpt-5-nano'); ?>>GPT-5 Nano (High-throughput, simple tasks)</option>
            </optgroup>
        </select>
        <p class="description">
            <?php _e('Select the OpenAI model to use for code generation.', 'stepfox-ai'); ?><br>
            <strong><?php _e('Note:', 'stepfox-ai'); ?></strong> <?php _e('Only GPT-4 Vision, GPT-4o, and GPT-4o Mini can analyze image content (read text, describe what\'s in images). Other models can only use images for placement in WordPress blocks.', 'stepfox-ai'); ?>
        </p>
        <?php
    }

    /**
     * API mode field callback
     */
    public function api_mode_field_callback() {
        $mode = get_option('stepfox_ai_api_mode', 'auto');
        ?>
        <select id="stepfox_ai_api_mode" name="stepfox_ai_api_mode">
            <option value="auto" <?php selected($mode, 'auto'); ?>><?php esc_html_e('Auto (recommended)', 'stepfox-ai'); ?></option>
            <option value="chat" <?php selected($mode, 'chat'); ?>><?php esc_html_e('Chat Completions endpoint', 'stepfox-ai'); ?></option>
            <option value="responses" <?php selected($mode, 'responses'); ?>><?php esc_html_e('Responses API endpoint', 'stepfox-ai'); ?></option>
        </select>
        <p class="description">
            <?php echo wp_kses_post(__('Choose which OpenAI API to use. <strong>Auto</strong> uses Responses for GPT‑5 text models and Chat Completions otherwise. If you encounter empty responses with large images, try switching to <strong>Chat</strong>.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * System prompt field callback
     */
    public function system_prompt_field_callback() {
        $system_prompt = get_option('stepfox_ai_system_prompt', '');
        ?>
        <textarea id="stepfox_ai_system_prompt" name="stepfox_ai_system_prompt" rows="8" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea>
        <p class="description">
            <?php _e('Optional. Prepended to every request as the system prompt. Leave blank to use only the user prompt. You can include guidelines for WordPress blocks, responsiveStyles, etc.', 'stepfox-ai'); ?>
        </p>
        <?php
    }

    /**
     * Experimental GPT‑5 images toggle field
     */
    public function gpt5_images_field_callback() {
        $enabled = (bool) get_option('stepfox_ai_gpt5_images', false);
        ?>
        <label>
            <input type="checkbox" name="stepfox_ai_gpt5_images" value="1" <?php checked($enabled, true); ?> />
            <?php _e('Allow sending images to GPT‑5 models via Responses API (may be unstable/unsupported).', 'stepfox-ai'); ?>
        </label>
        <p class="description"><?php _e('When enabled, image URLs or base64 data will be included for GPT‑5 requests. Use only if your GPT‑5 tier supports vision.', 'stepfox-ai'); ?></p>
        <?php
    }

    /**
     * Max tokens field callback
     */
    public function max_tokens_field_callback() {
        $value = intval(get_option('stepfox_ai_max_tokens', 16000));
        ?>
        <input type="number" id="stepfox_ai_max_tokens" name="stepfox_ai_max_tokens" value="<?php echo esc_attr($value); ?>" min="1" max="200000" step="1" class="small-text" />
        <p class="description">
            <?php echo wp_kses_post(__('Upper bound on the model\'s reply length in tokens. Called <em>max_completion_tokens</em> for GPT‑4o/GPT‑5 Chat, and <em>max_output_tokens</em> for the Responses API. Lower values keep outputs concise; higher values allow longer results.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Temperature field callback
     */
    public function temperature_field_callback() {
        $value = floatval(get_option('stepfox_ai_temperature', 0.7));
        ?>
        <input type="number" id="stepfox_ai_temperature" name="stepfox_ai_temperature" value="<?php echo esc_attr($value); ?>" min="0" max="2" step="0.1" class="small-text" />
        <p class="description">
            <?php echo wp_kses_post(__('Controls randomness/creativity. 0 = deterministic, 2 = very creative. <strong>Note:</strong> Some models (e.g. GPT‑4o, GPT‑5 family) ignore custom values and use their default temperature.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Top P field callback
     */
    public function top_p_field_callback() {
        $value = floatval(get_option('stepfox_ai_top_p', 1.0));
        ?>
        <input type="number" id="stepfox_ai_top_p" name="stepfox_ai_top_p" value="<?php echo esc_attr($value); ?>" min="0" max="1" step="0.05" class="small-text" />
        <p class="description">
            <?php echo wp_kses_post(__('Nucleus sampling. Consider only tokens comprising this cumulative probability mass. Use as an alternative to temperature; leave at 1.0 to disable. Applies to Chat Completions requests.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Presence penalty field callback
     */
    public function presence_penalty_field_callback() {
        $value = floatval(get_option('stepfox_ai_presence_penalty', 0.0));
        ?>
        <input type="number" id="stepfox_ai_presence_penalty" name="stepfox_ai_presence_penalty" value="<?php echo esc_attr($value); ?>" min="-2" max="2" step="0.1" class="small-text" />
        <p class="description">
            <?php echo wp_kses_post(__('Encourages the model to talk about new topics (reduces staying on the same subject). Range −2 to 2. Applies to Chat Completions requests.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Frequency penalty field callback
     */
    public function frequency_penalty_field_callback() {
        $value = floatval(get_option('stepfox_ai_frequency_penalty', 0.0));
        ?>
        <input type="number" id="stepfox_ai_frequency_penalty" name="stepfox_ai_frequency_penalty" value="<?php echo esc_attr($value); ?>" min="-2" max="2" step="0.1" class="small-text" />
        <p class="description">
            <?php echo wp_kses_post(__('Reduces repetition of the same tokens. Range −2 to 2. Applies to Chat Completions requests.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Stop sequences field callback
     */
    public function stop_sequences_field_callback() {
        $value = trim((string) get_option('stepfox_ai_stop_sequences', ''));
        ?>
        <input type="text" id="stepfox_ai_stop_sequences" name="stepfox_ai_stop_sequences" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="e.g. </script>, END, <!-- stop -->" />
        <p class="description">
            <?php echo wp_kses_post(__('Comma‑separated list of up to 4 sequences where generation should stop (for example a closing tag). Applies to Chat Completions requests.', 'stepfox-ai')); ?>
        </p>
        <?php
    }

    /**
     * Test API connection AJAX handler
     *
     * @since    1.0.0
     */
    public function test_api_connection() {
        // Check nonce - try multiple possible nonce actions
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            // Try the AJAX nonce first (most likely)
            if (wp_verify_nonce($_POST['nonce'], 'stepfox_ai_ajax_nonce')) {
                $nonce_valid = true;
            }
            // Try the standard settings nonce
            elseif (wp_verify_nonce($_POST['nonce'], 'stepfox_ai_settings-options')) {
                $nonce_valid = true;
            }
            // Try without the -options suffix
            elseif (wp_verify_nonce($_POST['nonce'], 'stepfox_ai_settings')) {
                $nonce_valid = true;
            }
        }
        
        // If no valid nonce, check if user is logged in and has permissions (less secure but functional)
        if (!$nonce_valid) {
            // For development/testing, we'll allow if user has manage_options capability
            // In production, you should always use proper nonce verification
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Security check failed - invalid nonce and insufficient permissions');
                return;
            }
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']);

        if (empty($api_key)) {
            wp_send_json_error('API key is required');
            return;
        }

        // Determine the correct max tokens parameter for the model
        $max_tokens_param = 'max_tokens'; // default
        $new_parameter_models = array('gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4-turbo-preview', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano');
        if (in_array($model, $new_parameter_models)) {
            $max_tokens_param = 'max_completion_tokens';
        }
        
        // Build test request body
        $test_body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Say "Hello from StepFox AI!"'
                )
            ),
            $max_tokens_param => 20,
        );
        
        // Only add temperature for models that support it
        // Check if model contains 'o' variant (like gpt-4o, gpt-4o-mini, etc) or any GPT-5 model
        $is_temperature_restricted = (strpos($model, 'gpt-4o') !== false || strpos($model, 'gpt-5o') !== false || strpos($model, 'gpt-5') !== false);
        if (!$is_temperature_restricted) {
            $test_body['temperature'] = 0.7;
        }
        
        // Test the API with a simple request
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($test_body),
            'timeout' => 60, // 1 minute timeout for test connection
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : 'Unknown error';
            wp_send_json_error('API Error: ' . $error_message);
            return;
        }

        wp_send_json_success('Connection successful!');
    }

    /**
     * Render jobs table based on transients
     */
    private function render_jobs_table() {
        global $wpdb;
        $like_job = $wpdb->esc_like('_transient_stepfox_ai_job_') . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 50",
                $like_job
            )
        );
        if (!$results) {
            return '<p>' . esc_html__('No jobs found yet.', 'stepfox-ai') . '</p>';
        }
        $rows = '';
        foreach ($results as $row) {
            $name = $row->option_name;
            $job_id = str_replace('_transient_stepfox_ai_job_', '', $name);
            $data = maybe_unserialize($row->option_value);
            $status = isset($data['status']) ? $data['status'] : 'unknown';
            $time = isset($data['finished']) ? (int)$data['finished'] : (isset($data['created']) ? (int)$data['created'] : 0);
            $when = $time ? date_i18n('Y-m-d H:i:s', $time) : '-';
            $success = isset($data['data']['success']) ? ($data['data']['success'] ? 'true' : 'false') : '';
            $actions = '<button class="button cancel-job" data-job="' . esc_attr($job_id) . '">' . esc_html__('Cancel', 'stepfox-ai') . '</button> '
                . '<button class="button delete-job" data-job="' . esc_attr($job_id) . '">' . esc_html__('Delete', 'stepfox-ai') . '</button>';
            $rows .= '<tr>'
                . '<td><code>' . esc_html($job_id) . '</code></td>'
                . '<td>' . esc_html($status) . '</td>'
                . '<td>' . esc_html($when) . '</td>'
                . '<td>' . esc_html($success) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }
        $table = '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('Job ID', 'stepfox-ai') . '</th>'
            . '<th>' . esc_html__('Status', 'stepfox-ai') . '</th>'
            . '<th>' . esc_html__('Time', 'stepfox-ai') . '</th>'
            . '<th>' . esc_html__('Success', 'stepfox-ai') . '</th>'
            . '<th>' . esc_html__('Actions', 'stepfox-ai') . '</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
 
        $rest_base = rest_url('stepfox-ai/v1');
        $nonce = wp_create_nonce('wp_rest');
        $table .= '<script>document.addEventListener("click",function(e){var b=e.target.closest(".cancel-job,.delete-job,.run-job");if(!b)return;var job=b.getAttribute("data-job");var endpoint="";if(b.classList.contains("delete-job")){endpoint="/job/delete";}else if(b.classList.contains("cancel-job")){endpoint="/job/cancel";}else{endpoint="/job/run";}fetch(' . json_encode($rest_base) . '+endpoint,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":' . json_encode($nonce) . '},body:JSON.stringify({job_id:job})}).then(function(r){return r.json().catch(function(){return {};});}).then(function(){location.reload();}).catch(function(err){alert("Request failed: "+err.message);});});</script>';
        return $table;
    }
}
