(function() {
    'use strict';
    
    console.log('StepFox AI Basic: Starting...');
    
    // Wait for WordPress editor to be ready
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof wp !== 'undefined' && wp.blocks && wp.element) {
            initStepFoxAIBlock();
        } else {
            // Try again after a short delay
            setTimeout(function() {
                if (typeof wp !== 'undefined' && wp.blocks && wp.element) {
                    initStepFoxAIBlock();
                }
            }, 1000);
        }
    });
    
    function initStepFoxAIBlock() {
        console.log('StepFox AI Basic: Initializing block...');
        
        var el = wp.element.createElement;
        var registerBlockType = wp.blocks.registerBlockType;
        var RichText = wp.blockEditor.RichText;
        var PlainText = wp.blockEditor.PlainText;
        var Button = wp.components.Button;
        var InnerBlocks = wp.blockEditor.InnerBlocks;
        var MediaUpload = wp.blockEditor.MediaUpload;
        var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
        var useState = wp.element.useState;
        var __ = wp.i18n.__;
        var parse = wp.blocks.parse;
        var serialize = wp.blocks.serialize;
        var dispatch = wp.data.dispatch;
        var selectStore = wp.data.select;
        var useSelect = wp.data.useSelect;
        
        registerBlockType('stepfox-ai/console-runner', {
            title: 'AI Console Runner',
            description: 'Generate JS/HTML code with AI',
            icon: 'media-code',
            category: 'widgets',
            keywords: ['ai', 'code', 'stepfox'],
            attributes: {
                promptContent: {
                    type: 'string',
                    default: ''
                },
                codeContent: {
                    type: 'string',
                    default: ''
                },
                promptImages: {
                    type: 'array',
                    default: []
                }
            },
            supports: {
                align: true,
                html: false
            },
            
            edit: function(props) {
                var attributes = props.attributes;
                var setAttributes = props.setAttributes;
                
                var [isGenerating, setIsGenerating] = useState(false);
                var [statusMessage, setStatusMessage] = useState('');
                var [showCode, setShowCode] = useState(true);
                var clientId = props.clientId;
                var innerBlocks = useSelect(function(s){
                    return s('core/block-editor').getBlocks(clientId);
                }, [clientId]);
                

                
                // Helper function to handle successful code generation
                function handleSuccessfulGeneration(data) {
                    if (data.success && data.code) {
                        var originalCode = data.code;
                        
                        // Check if it's WordPress block markup
                        if (originalCode.includes('<!-- wp:') && originalCode.includes('-->')) {
                            // Parse the WordPress blocks
                            try {
                                var parsedBlocks = parse(originalCode);
                                if (parsedBlocks && parsedBlocks.length > 0) {
                                    // Round-trip to canonical and re-parse to keep data tree and HTML in sync
                                    var canonicalCode = serialize(parsedBlocks);
                                    var canonicalBlocks = parse(canonicalCode);
                                    setAttributes({ codeContent: canonicalCode });
                                    // Defer the heavy replaceInnerBlocks to avoid blocking the UI
                                    var schedule = window.requestIdleCallback || function(cb){ return setTimeout(cb, 0); };
                                    schedule(function(){
                                        try {
                                            wp.data.dispatch('core/block-editor').replaceInnerBlocks(clientId, canonicalBlocks);
                                            setStatusMessage('✅ WordPress blocks generated and added as innerBlocks! You can now edit them directly.');
                                            setShowCode(false);
                                        } catch (e) {
                                            console.error('replaceInnerBlocks failed:', e);
                                            setStatusMessage('⚠️ Insert failed. See console for details.');
                                        }
                                    });
                                } else {
                                    setStatusMessage('⚠️ WordPress blocks generated but could not be parsed. Check the code below.');
                                }
                            } catch (parseError) {
                                console.error('Block parsing error:', parseError);
                                setStatusMessage('⚠️ Error parsing WordPress blocks: ' + parseError.message);
                            }
                        } else if (originalCode.includes('<') && originalCode.includes('>')) {
                            setStatusMessage('✅ HTML code generated! You can copy it or convert to blocks.');
                            setAttributes({ codeContent: originalCode });
                        } else if (originalCode.includes('console.log') || originalCode.includes('function') || originalCode.includes('var') || originalCode.includes('const') || originalCode.includes('let')) {
                            setStatusMessage('✅ JavaScript code generated! Click "Run in Console" to execute it.');
                            setAttributes({ codeContent: originalCode });
                        } else {
                            setStatusMessage('✅ Code generated successfully!');
                            setAttributes({ codeContent: originalCode });
                        }
                    } else {
                        var errorMsg = data.message || 'Failed to generate code';
                        try {
                            console.groupCollapsed('[StepFox AI] Generation Failed');
                            console.error('Server payload:', data);
                            console.log('Prompt length:', (attributes.promptContent || '').length);
                            console.log('Images count:', (attributes.promptImages || []).length);
                            console.groupEnd();
                        } catch (_e) {}
                        setStatusMessage('Error: ' + errorMsg);
                        setAttributes({ codeContent: '// Error: ' + errorMsg });
                    }
                    setIsGenerating(false);
                }
                
                function generateCode() {
                    if (!attributes.promptContent) {
                        setStatusMessage('Please enter a prompt first!');
                        return;
                    }

                    // Preserve raw pasted block markup; avoid React sanitization by keeping it in attributes only
                    // and not rendering it anywhere except as plain text code preview.
                    var rawPrompt = attributes.promptContent;
                    
                    setIsGenerating(true);
                    setStatusMessage('Generating code... This may take up to 5 minutes for complex requests.');
                    setAttributes({ codeContent: '// Generating... Please wait, this may take a few minutes for complex requests.' });
                    
                    // Check if API settings exist
                    if (!window.stepfoxAI || !window.stepfoxAI.apiUrl) {
                        setStatusMessage('Error: API settings not found. Please refresh the page.');
                        setIsGenerating(false);
                        return;
                    }
                    
                    // Create AbortController for timeout
                    // Log outbound request basics
                    try {
                        console.groupCollapsed('[StepFox AI] Request');
                        console.log('API URL:', window.stepfoxAI.apiUrl);
                        console.log('Prompt length:', (rawPrompt || '').length);
                        console.log('Images count:', (attributes.promptImages || []).length);
                        // Ask backend which model will be used (it logs on server). Also print current selection if exposed.
                        if (window.stepfoxAI && window.stepfoxAI.model) {
                            console.log('Selected model (client):', window.stepfoxAI.model);
                        }
                        console.groupEnd();
                    } catch (_e) {}
                    var controller = new AbortController();
                    var timeoutId = setTimeout(function() {
                        controller.abort();
                    }, 300000); // 5 minutes timeout
                    
                    fetch(window.stepfoxAI.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.stepfoxAI.nonce
                        },
                        body: JSON.stringify({
                            prompt: rawPrompt,
                            images: attributes.promptImages,
                            async: false
                        }),
                        signal: controller.signal
                    })
                    .then(function(response) {
                        clearTimeout(timeoutId); // Clear the timeout on successful response
                        // Always try to parse the response to get error details
                        return response.json().then(function(data) {
                            if (!response.ok) {
                                // Log full error payload from server
                                try {
                                    console.groupCollapsed('[StepFox AI] HTTP ' + response.status + ' Error');
                                    console.error('Response JSON:', data);
                                    console.groupEnd();
                                } catch (_e) {}
                                // If response is not ok, throw error with server message
                                throw new Error(data.message || data.error || 'Network response was not ok (Status: ' + response.status + ')');
                            }
                            return data;
                        });
                    })
                    .then(function(data) {
                        try {
                            if (data && data.model_used) {
                                console.log('[StepFox AI] Model used:', data.model_used, '| API:', data.api || 'unknown');
                            }
                            if (data && data.prompt_length) {
                                console.log('[StepFox AI] Prompt length (system+user):', data.prompt_length);
                                if (data.prompt_preview) {
                                    console.groupCollapsed('[StepFox AI] Prompt preview');
                                    console.log(data.prompt_preview);
                                    console.groupEnd();
                                }
                            }
                        } catch (_e) {}
                        // Use synchronous response path for stability
                        handleSuccessfulGeneration(data);
                    })
                    .catch(function(err) {
                        clearTimeout(timeoutId); // Clear timeout on error too
                        console.error('REST API error:', err);
                        console.error('Error details:', {
                            message: err.message,
                            name: err.name,
                            stack: err.stack
                        });
                        
                        // Check if it was a timeout error
                        if (err.name === 'AbortError') {
                            setStatusMessage('Request timed out after 5 minutes. Try a simpler prompt or break it into smaller parts.');
                            setAttributes({ codeContent: '// Error: Request timed out. The prompt may be too complex. Try simplifying it.' });
                            setIsGenerating(false);
                            return;
                        }
                        
                        // Check for specific error patterns
                        var errorMessage = err.message.toLowerCase();
                        if (errorMessage.includes('rate') && errorMessage.includes('limit')) {
                            setStatusMessage('Rate limit reached. Please wait a moment and try again.');
                            setAttributes({ codeContent: '// Error: Rate limit exceeded. Wait a few seconds and try again.' });
                            setIsGenerating(false);
                            return;
                        }
                        
                        if (errorMessage.includes('quota') || errorMessage.includes('billing')) {
                            setStatusMessage('API quota issue. Please check your OpenAI billing.');
                            setAttributes({ codeContent: '// Error: OpenAI API quota or billing issue. Check your account.' });
                            setIsGenerating(false);
                            return;
                        }
                        
                        // Try fallback AJAX method if REST API fails
                        if (window.stepfoxAI && window.stepfoxAI.ajaxUrl) {
                            setStatusMessage('Trying alternative method...');
                            
                            // Prepare form data for AJAX
                            var formData = new FormData();
                            formData.append('action', 'stepfox_ai_generate_fallback');
                            formData.append('nonce', window.stepfoxAI.fallbackNonce);
                            formData.append('prompt', attributes.promptContent);
                            formData.append('images', JSON.stringify(attributes.promptImages));
                            
                            // Create new AbortController for fallback
                            var fallbackController = new AbortController();
                            var fallbackTimeoutId = setTimeout(function() {
                                fallbackController.abort();
                            }, 300000); // 5 minutes timeout for fallback too
                            
                            fetch(window.stepfoxAI.ajaxUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                body: formData,
                                signal: fallbackController.signal
                            })
                            .then(function(response) {
                                clearTimeout(fallbackTimeoutId); // Clear fallback timeout
                                return response.json();
                            })
                            .then(function(data) {
                                if (data.success && data.data) {
                                    handleSuccessfulGeneration(data.data);
                                } else {
                                    throw new Error(data.data || 'Alternative method also failed');
                                }
                            })
                            .catch(function(fallbackErr) {
                                clearTimeout(fallbackTimeoutId); // Clear timeout on error
                                console.error('Fallback error:', fallbackErr);
                                
                                if (fallbackErr.name === 'AbortError') {
                                    setStatusMessage('Request timed out after 5 minutes. Try a simpler prompt.');
                                    setAttributes({ codeContent: '// Error: Request timed out (5 minutes). The prompt may be too complex.' });
                                } else {
                                    setStatusMessage('Error: ' + err.message);
                                    setAttributes({ codeContent: '// Error: ' + err.message + '\n// Fallback error: ' + fallbackErr.message });
                                }
                                setIsGenerating(false);
                            });
                        } else {
                            setStatusMessage('Error: ' + err.message);
                            setAttributes({ codeContent: '// Error: ' + err.message });
                            setIsGenerating(false);
                        }
                    });
                }
                
                function runInConsole() {
                    if (attributes.codeContent && attributes.codeContent !== '// Generating...') {
                        console.log('=== Running StepFox AI Generated Code ===');
                        try {
                            eval(attributes.codeContent);
                            console.log('=== Code Execution Complete ===');
                        } catch (e) {
                            console.error('Error executing code:', e);
                        }
                    }
                }
                
                function toggleCodeView() {
                    setShowCode(!showCode);
                    if (!showCode) {
                        setStatusMessage('Showing code editor');
                    } else {
                        setStatusMessage('Showing block editor');
                    }
                }
                
                return el('div', { 
                    className: 'stepfox-ai-console-runner',
                    style: { 
                        padding: '20px',
                        border: '1px solid #e0e0e0',
                        borderRadius: '4px',
                        backgroundColor: '#f9f9f9'
                    }
                },
                    el('div', { style: { marginBottom: '10px' } },
                        el('label', { 
                            style: { 
                                display: 'block', 
                                marginBottom: '5px',
                                fontWeight: 'bold' 
                            } 
                        }, 'Enter your prompt:'),
                        // Use a plain textarea to preserve exact pasted markup (e.g., <!-- wp:... -->)
                        el('textarea', {
                            value: attributes.promptContent,
                            onChange: function(e) {
                                setAttributes({ promptContent: e.target.value });
                            },
                            placeholder: 'Paste instructions or raw WordPress block markup (<!-- wp:... -->) here',
                            style: {
                                width: '100%',
                                minHeight: '120px',
                                padding: '10px',
                                border: '1px solid #ddd',
                                borderRadius: '4px',
                                backgroundColor: 'white',
                                fontFamily: 'monospace',
                                fontSize: '12px',
                                whiteSpace: 'pre-wrap'
                            }
                        }),
                        
                        // Image selection area
                        el('div', { style: { marginTop: '10px' } },
                            // Display selected images
                            attributes.promptImages.length > 0 && el('div', {
                                style: {
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: '10px',
                                    marginBottom: '10px'
                                }
                            },
                                attributes.promptImages.map(function(image, index) {
                                    return el('div', {
                                        key: image.id,
                                        style: {
                                            position: 'relative',
                                            width: '100px',
                                            height: '100px'
                                        }
                                    },
                                        el('img', {
                                            src: image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url,
                                            alt: image.alt || '',
                                            style: {
                                                width: '100%',
                                                height: '100%',
                                                objectFit: 'cover',
                                                borderRadius: '4px',
                                                border: '1px solid #ddd'
                                            }
                                        }),
                                        el(Button, {
                                            isDestructive: true,
                                            isSmall: true,
                                            onClick: function() {
                                                var newImages = attributes.promptImages.filter(function(img, i) {
                                                    return i !== index;
                                                });
                                                setAttributes({ promptImages: newImages });
                                            },
                                            style: {
                                                position: 'absolute',
                                                top: '-5px',
                                                right: '-5px',
                                                minWidth: '24px',
                                                height: '24px',
                                                padding: '0',
                                                borderRadius: '50%'
                                            }
                                        }, '×')
                                    );
                                })
                            ),
                            
                            // Add image button
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: function(media) {
                                        // Handle multiple selection
                                        var selectedImages = Array.isArray(media) ? media : [media];
                                        var newImages = attributes.promptImages.concat(selectedImages);
                                        setAttributes({ promptImages: newImages });
                                    },
                                    allowedTypes: ['image'],
                                    multiple: true,
                                    render: function(obj) {
                                        return el(Button, {
                                            isSecondary: true,
                                            onClick: obj.open,
                                            style: { marginRight: '10px' }
                                        }, '📷 Add Images');
                                    }
                                })
                            ),
                            
                            attributes.promptImages.length > 0 && el('span', {
                                style: {
                                    fontSize: '12px',
                                    color: '#666',
                                    marginLeft: '10px'
                                }
                            }, attributes.promptImages.length + ' image(s) selected'),
                            
                            // Show warning if images are selected but model might not support vision
                            attributes.promptImages.length > 0 && el('div', {
                                style: {
                                    marginTop: '10px',
                                    padding: '10px',
                                    backgroundColor: '#fff3cd',
                                    border: '1px solid #ffeaa7',
                                    borderRadius: '4px',
                                    fontSize: '12px',
                                    color: '#856404'
                                }
                            }, 
                                el('strong', {}, '⚠️ Note: '),
                                'To analyze image content (read text, describe what\'s in the image), you need to select a vision model (GPT-4 Vision, GPT-4o, or GPT-4o Mini) in the StepFox AI settings. Other models can only use images for placement in blocks.'
                            ),
                            
                            // Check for local images and show additional warning
                            attributes.promptImages.length > 0 && attributes.promptImages.some(function(img) {
                                var url = img.url || '';
                                return url.includes('.local') || url.includes('localhost') || url.includes('127.0.0.1');
                            }) && el('div', {
                                style: {
                                    marginTop: '10px',
                                    padding: '10px',
                                    backgroundColor: '#d1ecf1',
                                    border: '1px solid #bee5eb',
                                    borderRadius: '4px',
                                    fontSize: '12px',
                                    color: '#0c5460'
                                }
                            }, 
                                el('strong', {}, '🌐 Local Images Detected: '),
                                'Your images are on a local development site. The plugin will automatically convert them to base64 format so the AI can process them. This may take a moment for large images.'
                            )
                        )
                    ),
                    
                    el('div', { style: { marginBottom: '10px', position: 'relative' } },
                        el(Button, {
                            isPrimary: true,
                            isBusy: isGenerating,
                            disabled: isGenerating,
                            onClick: generateCode,
                            style: { marginRight: '10px' }
                        }, isGenerating ? 'Generating...' : 'Generate with AI'),
                        
                        attributes.codeContent && el(Button, {
                            isSecondary: true,
                            onClick: runInConsole,
                            style: { marginRight: '10px' }
                        }, 'Run in Console'),
                        
                        // Show toggle button if we have innerBlocks
                        innerBlocks.length > 0 && el(Button, {
                            isSecondary: true,
                            onClick: toggleCodeView,
                            style: { marginRight: '10px' }
                        }, showCode ? 'Show Blocks' : 'Show Code'),
                        
                        // Clear innerBlocks button
                        innerBlocks.length > 0 && el(Button, {
                            isDestructive: true,
                            onClick: function() {
                                if (confirm('Are you sure you want to clear all innerBlocks?')) {
                                    dispatch('core/block-editor').replaceInnerBlocks(clientId, []);
                                    setStatusMessage('InnerBlocks cleared');
                                    setShowCode(true);
                                }
                            },
                            style: { marginRight: '10px' }
                        }, 'Clear Blocks'),
                        isGenerating && el('div', {
                            style: {
                                position: 'absolute',
                                inset: 0,
                                background: 'rgba(255,255,255,0.6)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                pointerEvents: 'none',
                                zIndex: 1
                            }
                        }, el('span', { style: { fontSize: '12px', color: '#333' } }, 'Generating... Please wait, this may take a few minutes for complex requests.')),
                        
                        attributes.codeContent && el(Button, {
                            isSecondary: true,
                            onClick: function() {
                                var codeToClean = attributes.codeContent;
                                
                                // Clean up WordPress blocks for better compatibility
                                if (codeToClean.includes('<!-- wp:')) {
                                    // Ensure proper line breaks around block comments
                                    codeToClean = codeToClean.replace(/<!--\s*wp:/g, '\n<!-- wp:');
                                    codeToClean = codeToClean.replace(/-->\s*</g, ' -->\n<');
                                    codeToClean = codeToClean.replace(/>\s*<!--\s*\/wp:/g, '>\n<!-- /wp:');
                                    codeToClean = codeToClean.replace(/-->\s*$/g, ' -->');
                                    
                                    // Remove extra whitespace but preserve structure
                                    codeToClean = codeToClean.replace(/\n\s*\n\s*\n/g, '\n\n');
                                    codeToClean = codeToClean.trim();
                                }
                                
                                // Copy code to clipboard
                                var textarea = document.createElement('textarea');
                                textarea.value = codeToClean;
                                textarea.style.position = 'fixed';
                                textarea.style.left = '-999999px';
                                document.body.appendChild(textarea);
                                textarea.select();
                                
                                try {
                                    document.execCommand('copy');
                                    if (codeToClean.includes('<!-- wp:')) {
                                        setStatusMessage('✅ WordPress blocks copied! Paste in Code Editor view, then switch to Visual Editor.');
                                    } else {
                                        setStatusMessage('✅ Code copied to clipboard!');
                                    }
                                } catch (err) {
                                    setStatusMessage('❌ Failed to copy code. Please select and copy manually.');
                                }
                                
                                document.body.removeChild(textarea);
                            }
                        }, '📋 Copy Code')
                    ),
                    
                    statusMessage && el('div', { 
                        style: { 
                            marginBottom: '10px',
                            padding: '10px',
                            backgroundColor: statusMessage.includes('Error') ? '#fee' : '#efe',
                            border: '1px solid ' + (statusMessage.includes('Error') ? '#fcc' : '#cfc'),
                            borderRadius: '4px'
                        } 
                    }, statusMessage),
                    

                    
                    // Always render InnerBlocks for validation, but toggle visibility via CSS
                    el('div', {
                        style: { display: showCode ? 'block' : 'none' }
                    },
                        el('label', { 
                            style: { 
                                display: 'block', 
                                marginBottom: '5px',
                                fontWeight: 'bold' 
                            } 
                        }, 'Generated Code:'),
                        el(PlainText, {
                            value: attributes.codeContent,
                            onChange: function(value) {
                                setAttributes({ codeContent: value });
                            },
                            placeholder: 'AI-generated code will appear here...',
                            style: {
                                width: '100%',
                                minHeight: '200px',
                                fontFamily: 'monospace',
                                fontSize: '13px',
                                padding: '10px',
                                border: '1px solid #ddd',
                                borderRadius: '4px',
                                backgroundColor: '#f5f5f5'
                            }
                        })
                    ),
                    el('div', {
                        style: {
                            display: showCode ? 'none' : 'block',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            padding: '20px',
                            backgroundColor: '#f9f9f9',
                            minHeight: '200px'
                        }
                    },
                        innerBlocks.length > 0 
                            ? el(InnerBlocks, {})
                            : el('p', { style: { textAlign: 'center', color: '#666' } }, 'No blocks yet. Generate some WordPress blocks to see them here.')
                    ),
                    
                    el('div', { 
                        style: { 
                            marginTop: '15px',
                            padding: '10px',
                            backgroundColor: '#f0f8ff',
                            border: '1px solid #d0e0f0',
                            borderRadius: '4px',
                            fontSize: '12px',
                            color: '#666'
                        } 
                    }, 
                        el('strong', {}, '💡 Tips:'),
                        el('ul', { style: { margin: '5px 0 0 20px', padding: 0 } },
                            el('li', {}, 'WordPress blocks will be automatically inserted as editable innerBlocks'),
                            el('li', {}, 'Add images to your prompt for AI to include them in generated blocks'),
                            el('li', {}, 'Use "Show Blocks/Code" to toggle between block editor and code view'),
                            el('li', {}, 'Use "Run in Console" for JavaScript code (open console with F12 first)'),
                            el('li', {}, 'You can edit blocks directly or modify the code and regenerate')
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('strong', {}, '📝 WordPress Block Examples:'),
                            el('ul', { style: { margin: '5px 0 0 20px', padding: 0, fontSize: '11px' } },
                                el('li', {}, '"Create a WordPress hero section with cover block"'),
                                el('li', {}, '"Make a two-column WordPress layout with image and text"'),
                                el('li', {}, '"Generate a WordPress button group with primary and secondary buttons"'),
                                el('li', {}, '"Build a WordPress post template with title, date, and excerpt"')
                            )
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('strong', {}, '🖼️ With Images (All models):'),
                            el('ul', { style: { margin: '5px 0 0 20px', padding: 0, fontSize: '11px' } },
                                el('li', {}, '"Create a hero section using the provided images as background"'),
                                el('li', {}, '"Make a gallery with the uploaded images"'),
                                el('li', {}, '"Build a media-text block with the first image"'),
                                el('li', {}, '"Generate a cover block using image 1 as background"')
                            )
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('strong', {}, '👁️ With Vision Models (GPT-4 Vision/GPT-4o):'),
                            el('ul', { style: { margin: '5px 0 0 20px', padding: 0, fontSize: '11px' } },
                                el('li', {}, '"Extract the text from the image and create a paragraph"'),
                                el('li', {}, '"Describe what\'s in the image and create content about it"'),
                                el('li', {}, '"Create a blog post based on the image content"'),
                                el('li', {}, '"Generate alt text based on what\'s shown in the image"')
                            )
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('strong', {}, '🎨 With StepFox Responsive:'),
                            el('ul', { style: { margin: '5px 0 0 20px', padding: 0, fontSize: '11px' } },
                                el('li', {}, '"Create a responsive hero with 80px desktop padding and 40px mobile"'),
                                el('li', {}, '"Make a flex group that stacks on mobile"'),
                                el('li', {}, '"Build a cover block with fade-in animation"'),
                                el('li', {}, '"Generate a section with custom ID and responsive margins"')
                            )
                        ),
                        el('div', { style: { marginTop: '10px' } },
                            el('strong', {}, '✨ WordPress Blocks Features:'),
                            el('ul', { style: { margin: '5px 0 0 20px', padding: 0, fontSize: '11px', color: '#0073aa' } },
                                el('li', {}, 'Generated blocks appear instantly as editable innerBlocks'),
                                el('li', {}, 'Move blocks around using the block toolbar'),
                                el('li', {}, 'Edit block content and settings directly'),
                                el('li', {}, 'Copy the code to use elsewhere or save as a pattern')
                            )
                        )
                    )
                );
            },
            
            save: function(props) {
                return el('div', {},
                    el(InnerBlocks.Content, {})
                );
            }
        });
        
        console.log('StepFox AI Basic: Block registered successfully!');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStepFoxAIBlock);
    } else {
        initStepFoxAIBlock();
    }
})();