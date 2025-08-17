<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    StepFox_AI
 * @subpackage StepFox_AI/includes
 */

class StepFox_AI {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      StepFox_AI_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The admin class instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      StepFox_AI_Admin    $admin    The admin-specific functionality of the plugin.
     */
    protected $admin;

    /**
     * The API handler instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      StepFox_AI_API    $api    The API handler for OpenAI requests.
     */
    protected $api;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = STEPFOX_AI_VERSION;
        $this->plugin_name = 'stepfox-ai';
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Ensure required class files are loaded
        require_once plugin_dir_path(__FILE__) . 'class-stepfox-ai-admin.php';
        require_once plugin_dir_path(__FILE__) . 'class-stepfox-ai-api.php';
        require_once plugin_dir_path(__FILE__) . 'class-stepfox-ai-api-fallback.php';

        // The admin-specific functionality of the plugin.
        if (is_admin()) {
            $this->admin = new StepFox_AI_Admin($this->get_plugin_name(), $this->get_version());
        }

        // The API handler for OpenAI requests
        $this->api = new StepFox_AI_API($this->get_plugin_name(), $this->get_version());
        
        // Initialize the fallback API handler for environments with REST issues
        new StepFox_AI_API_Fallback();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        if (is_admin()) {
            add_action('admin_menu', array($this->admin, 'add_admin_menu'));
            add_action('admin_init', array($this->admin, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
            add_action('wp_ajax_stepfox_ai_test_connection', array($this->admin, 'test_api_connection'));
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        // Register REST API endpoint for OpenAI requests
        add_action('rest_api_init', array($this->api, 'register_rest_routes'));
    }

    /**
     * Register all of the hooks related to blocks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_block_hooks() {
        // Block registration is now handled in blocks/ai-console-runner/index.php
        // add_action('init', array($this, 'register_blocks'));
        // add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }

    /**
     * Register the blocks.
     *
     * @since    1.0.0
     */
    public function register_blocks() {
        // Register block script
        wp_register_script(
            'stepfox-ai-console-runner',
            STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/ai-console-runner.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'),
            $this->version,
            true
        );

        // Localize script with REST API URL
        wp_localize_script('stepfox-ai-console-runner', 'stepfoxAI', array(
            'apiUrl' => rest_url('stepfox-ai/v1/generate'),
            'nonce' => wp_create_nonce('wp_rest'),
            'model' => get_option('stepfox_ai_openai_model', 'gpt-3.5-turbo'),
        ));

        // Register the block
        register_block_type('stepfox-ai/console-runner', array(
            'editor_script' => 'stepfox-ai-console-runner',
        ));
    }

    /**
     * Enqueue block editor assets.
     *
     * @since    1.0.0
     */
    public function enqueue_block_editor_assets() {
        // Enqueue the block script
        wp_enqueue_script('stepfox-ai-console-runner');
        
        // Enqueue the block styles
        wp_enqueue_style(
            'stepfox-ai-editor',
            STEPFOX_AI_PLUGIN_URL . 'blocks/ai-console-runner/editor.css',
            array('wp-edit-blocks'),
            $this->version
        );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_block_hooks();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
