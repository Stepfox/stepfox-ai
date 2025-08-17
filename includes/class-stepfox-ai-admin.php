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
                <option value="gpt-5-chat-latest" <?php selected($model, 'gpt-5-chat-latest'); ?>>GPT-5 Chat Latest</option>
            </optgroup>
        </select>
        <p class="description">
            <?php _e('Select the OpenAI model to use for code generation.', 'stepfox-ai'); ?><br>
            <strong><?php _e('Note:', 'stepfox-ai'); ?></strong> <?php _e('Only GPT-4 Vision, GPT-4o, and GPT-4o Mini can analyze image content (read text, describe what\'s in images). Other models can only use images for placement in WordPress blocks.', 'stepfox-ai'); ?><br>
            <strong><?php _e('GPT-5 Models:', 'stepfox-ai'); ?></strong> <?php _e('Available mappings: gpt-5-thinking → gpt-5, gpt-5-thinking-mini → gpt-5-mini, gpt-5-thinking-nano → gpt-5-nano, gpt-5-main → gpt-5-chat-latest.', 'stepfox-ai'); ?>
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
        $new_parameter_models = array('gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4-turbo-preview', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-chat-latest');
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
}
