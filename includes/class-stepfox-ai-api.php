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
            ),
        ));
    }

    /**
     * Check if the user has permission to use the API
     *
     * @since    1.0.0
     * @return   bool
     */
    public function check_permission($request) {
        // Check if user is authenticated
        if (current_user_can('edit_posts')) {
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
        $prompt = $request->get_param('prompt');
        $images = $request->get_param('images');
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

        // Prepare the system prompt based on model capabilities
        $base_prompt = 'You are an expert JavaScript, HTML, and WordPress block editor programmer. Write only the raw code for the following request. ' .
            'Do not include any explanation, markdown formatting (like ```js or ```html), or any text other than the code itself. ' .
            'Your entire response should be executable in a browser or valid WordPress block markup.';
        
        // Define vision-capable models
        $vision_models = array('gpt-4-vision-preview', 'gpt-4o', 'gpt-4o-mini');
        
        // Add vision-specific instructions for vision-capable models
        if (in_array($model, $vision_models) && !empty($images)) {
            $base_prompt = 'You are an expert JavaScript, HTML, and WordPress block editor programmer with image analysis capabilities. ' .
                'You can see and analyze the content of images provided. ' .
                'Write only the raw code for the following request. ' .
                'Do not include any explanation, markdown formatting (like ```js or ```html), or any text other than the code itself. ' .
                'Your entire response should be executable in a browser or valid WordPress block markup. ' .
                'When asked to extract text or describe image content, create appropriate WordPress blocks with that content.';
        }
        
        $system_prompt = sprintf(
            $base_prompt . "\n\n" .
            'For WordPress block requests, you MUST use the exact block format that WordPress expects: ' .
            "\n" .
            'CRITICAL RULES:' .
            "\n" .
            '1. Each block comment MUST be on its own line with NO trailing spaces' .
            "\n" .
            '2. Block names use only lowercase and hyphens (wp:heading, wp:paragraph, wp:group)' .
            "\n" .
            '3. HTML tags must match the block type exactly (h2 for heading level 2, p for paragraph)' .
            "\n" .
            '4. Attributes must be valid JSON: {"level":2} not {level:2}' .
            "\n" .
            '5. Include proper WordPress CSS classes on HTML elements' .
            "\n\n" .
            'EXACT FORMATS:' .
            "\n" .
            '- Heading: <!-- wp:heading {"level":2} -->\\n<h2 class="wp-block-heading">Text</h2>\\n<!-- /wp:heading -->' .
            "\n" .
            '- Paragraph: <!-- wp:paragraph -->\\n<p>Text</p>\\n<!-- /wp:paragraph -->' .
            "\n" .
            '- Button: <!-- wp:buttons -->\\n<div class="wp-block-buttons"><!-- wp:button -->\\n<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Text</a></div>\\n<!-- /wp:button --></div>\\n<!-- /wp:buttons -->' .
            "\n" .
            '- Group: <!-- wp:group -->\\n<div class="wp-block-group">\\n<!-- wp:paragraph -->\\n<p>Content</p>\\n<!-- /wp:paragraph -->\\n</div>\\n<!-- /wp:group -->' .
            "\n" .
            '- Cover: <!-- wp:cover {"dimRatio":50} -->\\n<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container">\\ncontent\\n</div></div>\\n<!-- /wp:cover -->' .
            "\n" .
            '- Image: <!-- wp:image {"id":123,"sizeSlug":"large","linkDestination":"none"} -->\\n<figure class="wp-block-image size-large"><img src="URL" alt="Alt text" class="wp-image-123"/></figure>\\n<!-- /wp:image -->' .
            "\n" .
            '- Gallery: <!-- wp:gallery {"linkTo":"none"} -->\\n<figure class="wp-block-gallery has-nested-images">\\n<!-- wp:image {"id":1} -->\\n<figure class="wp-block-image"><img src="URL1" alt="" class="wp-image-1"/></figure>\\n<!-- /wp:image -->\\n</figure>\\n<!-- /wp:gallery -->' .
            "\n" .
            '- Cover with background image: <!-- wp:cover {"url":"IMAGE_URL","id":123,"dimRatio":50} -->\\n<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-123" alt="" src="IMAGE_URL" data-object-fit="cover"/><div class="wp-block-cover__inner-container">\\ncontent\\n</div></div>\\n<!-- /wp:cover -->' .
            "\n\n" .
            'STEPFOX LOOKS RESPONSIVE ATTRIBUTES:' .
            "\n" .
            'WordPress blocks can include these special attributes from StepFox Looks plugin:' .
            "\n" .
            '- customId: unique identifier (e.g., "abc123")' .
            "\n" .
            '- responsiveStyles: object containing device-specific styles' .
            "\n" .
            '  - Devices: desktop, tablet, mobile' .
            "\n" .
            '  - Properties per device:' .
            "\n" .
            '    - Layout: width, height, min_width, max_width, min_height, max_height, display, position, z_index, order' .
            "\n" .
            '    - Positioning: top, right, bottom, left' .
            "\n" .
            '    - Spacing: padding (object with top/right/bottom/left), margin (object with top/right/bottom/left)' .
            "\n" .
            '    - Typography: font_size, line_height, font_weight, font_style, text_transform, text_decoration, textAlign, letter_spacing' .
            "\n" .
            '    - Flexbox: flex_direction, justify, align_items, align_self, justify_self, flexWrap, flex_grow, flex_shrink, gap' .
            "\n" .
            '    - Grid: grid_template_columns, align_content' .
            "\n" .
            '    - Borders: borderWidth (object), borderStyle (object), borderColor (object), borderRadius (object)' .
            "\n" .
            '    - Effects: opacity, transform, transition, box_shadow, filter, backdrop_filter' .
            "\n" .
            '    - Background: background_color, background_image, background_position, background_size, background_repeat' .
            "\n" .
            '    - Other: overflow, visibility, cursor, user_select, pointer_events, object_fit, object_position' .
            "\n" .
            '- animation: animation class name' .
            "\n" .
            '- animation_delay: delay value' .
            "\n" .
            '- animation_duration: duration value' .
            "\n" .
            '- custom_css: custom CSS code' .
            "\n" .
            '- custom_js: custom JavaScript code' .
            "\n\n" .
            'Example with responsive attributes:' .
            "\n" .
            '<!-- wp:group {"customId":"hero123","responsiveStyles":{"padding":{"desktop":{"top":"80px","bottom":"80px"},"mobile":{"top":"40px","bottom":"40px"}},"background_color":{"desktop":"#f0f0f0"}}} -->' .
            "\n\n" .
            'When user asks for responsive design, include appropriate responsiveStyles attributes.' .
            "\n\n" .
            'Request: "%s"',
            $prompt
        );
        
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

        // Prepare the request body based on model type
        if (in_array($model, $vision_models) && !empty($images) && is_array($images)) {
            // For GPT-4 Vision with images
            $user_content = array(
                array(
                    'type' => 'text',
                    'text' => $system_prompt
                )
            );
            
            // Add each image URL for vision analysis
            foreach ($images as $image) {
                if (isset($image['url'])) {
                    $image_url = $image['url'];
                    
                    // Check if this is a local URL
                    $is_local = $this->is_local_url($image_url);
                    
                    if ($is_local) {
                        // Convert local image to base64
                        $base64_image = $this->convert_local_image_to_base64($image_url);
                        if ($base64_image) {
                            $user_content[] = array(
                                'type' => 'image_url',
                                'image_url' => array(
                                    'url' => $base64_image,
                                    'detail' => 'auto'
                                )
                            );
                        } else {
                            error_log('StepFox AI - Failed to convert local image: ' . $image_url);
                        }
                    } else {
                        // Use the URL directly for external images
                        $user_content[] = array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_url,
                                'detail' => 'auto'
                            )
                        );
                    }
                }
            }
            
            $body = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $user_content
                    )
                ),
                'max_tokens' => 4096, // Vision model can output more
            );
        } else {
            // Standard text-only request
            $body = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $system_prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 2000,
            );
        }

        // Make the API request
        $response = wp_remote_post($this->openai_api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 30,
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
        $response_data = json_decode($response_body, true);

        // Check for API errors
        if ($response_code !== 200) {
            $error_message = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : __('Unknown API error', 'stepfox-ai');
            
            return new WP_Error(
                'openai_error',
                sprintf(__('OpenAI API Error: %s', 'stepfox-ai'), $error_message),
                array('status' => $response_code)
            );
        }

        // Extract the generated code
        $generated_code = '';
        if (isset($response_data['choices'][0]['message']['content'])) {
            $generated_code = $response_data['choices'][0]['message']['content'];
            
            // Clean up any markdown formatting if present
            $generated_code = preg_replace('/^```(javascript|js|html)?\n|```$/m', '', $generated_code);
            $generated_code = trim($generated_code);
        }

        // Return the response
        return new WP_REST_Response(array(
            'success' => true,
            'code' => $generated_code,
            'usage' => isset($response_data['usage']) ? $response_data['usage'] : null,
        ), 200);
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
}
