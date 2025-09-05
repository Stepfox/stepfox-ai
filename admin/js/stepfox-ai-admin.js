/**
 * StepFox AI Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Toggle API key visibility
        var $apiKeyField = $('#stepfox_ai_openai_api_key');
        if ($apiKeyField.length) {
            var $toggleButton = $('<button type="button" class="button button-small stepfox-ai-toggle-key">Show</button>');
            $apiKeyField.after($toggleButton);

            $toggleButton.on('click', function(e) {
                e.preventDefault();
                
                if ($apiKeyField.attr('type') === 'password') {
                    $apiKeyField.attr('type', 'text');
                    $toggleButton.text('Hide');
                } else {
                    $apiKeyField.attr('type', 'password');
                    $toggleButton.text('Show');
                }
            });
        }

        // Validate API key format
        $apiKeyField.on('blur', function() {
            var apiKey = $(this).val();
            var $notice = $('.stepfox-ai-api-key-notice');
            
            // Remove existing notice
            $notice.remove();
            
            if (apiKey && !apiKey.startsWith('sk-')) {
                $(this).after('<p class="stepfox-ai-api-key-notice" style="color: #dc3232; margin-top: 5px;">API key should start with "sk-"</p>');
            }
        });

        // Test API connection button
        var $testButton = $('<button type="button" class="button button-secondary stepfox-ai-test-connection" style="margin-left: 10px;">Test Connection</button>');
        $('#stepfox_ai_openai_api_key').parent().append($testButton);

        $testButton.on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            var apiKey = $('#stepfox_ai_openai_api_key').val();
            var model = $('#stepfox_ai_openai_model').val();
            
            if (!apiKey) {
                alert('Please enter an API key first.');
                return;
            }
            
            $button.text('Testing...').prop('disabled', true);
            
            // Get the nonce value - prefer the localized AJAX nonce if available
            var nonceValue = '';
            if (typeof stepfox_ai_ajax !== 'undefined' && stepfox_ai_ajax.nonce) {
                nonceValue = stepfox_ai_ajax.nonce;
            } else {
                // Fallback to form nonces
                nonceValue = $('input[name="_wpnonce"]').val() || 
                           $('#_wpnonce').val() || 
                           $('input[name="stepfox_ai_settings[_wpnonce]"]').val() ||
                           '';
            }
            
            // Test the API connection
            var ajaxUrl = (typeof stepfox_ai_ajax !== 'undefined' && stepfox_ai_ajax.ajax_url) ? stepfox_ai_ajax.ajax_url : ajaxurl;
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'stepfox_ai_test_connection',
                    api_key: apiKey,
                    model: model,
                    nonce: nonceValue
                },
                success: function(response) {
                    if (response.success) {
                        alert('Success! API connection is working.');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to test connection. Please try again.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Model selection help text
        $('#stepfox_ai_openai_model').on('change', function() {
            var $helpText = $('.stepfox-ai-model-help');
            $helpText.remove();
            
            var helpTexts = {
                'gpt-3.5-turbo': 'Fast and cost-effective for most tasks',
                'gpt-4': 'More capable but slower and more expensive',
                'gpt-4-turbo-preview': 'Latest GPT-4 model with improved performance',
                'gpt-4-vision-preview': 'Can analyze and understand images',
                'gpt-4o': 'Latest multimodal model with vision capabilities',
                'gpt-4o-mini': 'Faster, cost-effective multimodal model',
                'gpt-5': 'Complex reasoning, code-heavy and multi-step tasks',
                'gpt-5-mini': 'Cost-optimized reasoning, balances speed and capability',
                'gpt-5-nano': 'High-throughput, simple instruction-following tasks'
            };
            
            var selectedModel = $(this).val();
            if (helpTexts[selectedModel]) {
                $(this).after('<p class="stepfox-ai-model-help description">' + helpTexts[selectedModel] + '</p>');
            }
        }).trigger('change');
    });

})(jQuery);
