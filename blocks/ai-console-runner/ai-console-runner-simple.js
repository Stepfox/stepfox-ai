(function() {
    'use strict';
    
    // Wait for wp global
    var waitForWp = setInterval(function() {
        if (window.wp && window.wp.blocks && window.wp.element && window.wp.blockEditor) {
            clearInterval(waitForWp);
            registerStepFoxAIBlock();
        }
    }, 100);
    
    function registerStepFoxAIBlock() {
        var el = wp.element.createElement;
        var registerBlockType = wp.blocks.registerBlockType;
        var RichText = wp.blockEditor.RichText;
        var PlainText = wp.blockEditor.PlainText;
        var Button = wp.components.Button;
        var useState = wp.element.useState;
        var useEffect = wp.element.useEffect;
        var useRef = wp.element.useRef;
        var __ = wp.i18n.__;
        var Fragment = wp.element.Fragment;
        var InspectorControls = wp.blockEditor.InspectorControls;
        var PanelBody = wp.components.PanelBody;
        
        console.log('StepFox AI: Registering block...');
        
        registerBlockType('stepfox-ai/console-runner', {
            title: __('AI Console Runner', 'stepfox-ai'),
            description: __('Generate JS/HTML with AI and see a live preview.', 'stepfox-ai'),
            icon: 'media-code',
            category: 'widgets',
            keywords: ['ai', 'code', 'generate', 'stepfox'],
            attributes: {
                promptContent: {
                    type: 'string',
                    default: ''
                },
                codeContent: {
                    type: 'string',
                    default: ''
                },
            },
            
            edit: function(props) {
                var attributes = props.attributes;
                var setAttributes = props.setAttributes;
                
                var [isGenerating, setIsGenerating] = useState(false);
                var [error, setError] = useState('');
                var iframeRef = useRef(null);
                
                // Update iframe content using srcdoc
                useEffect(function() {
                    if (iframeRef.current && attributes.codeContent) {
                        var htmlContent = `
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset="UTF-8">
                                <style>body { margin: 10px; font-family: sans-serif; }</style>
                            </head>
                            <body>${attributes.codeContent || ''}</body>
                            </html>
                        `;
                        iframeRef.current.srcdoc = htmlContent;
                    }
                }, [attributes.codeContent]);
                
                function generateCode() {
                    if (!attributes.promptContent) {
                        setError('Please enter a prompt first!');
                        return;
                    }
                    
                    setIsGenerating(true);
                    setError('');
                    
                    fetch(stepfoxAI.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': stepfoxAI.nonce
                        },
                        body: JSON.stringify({
                            prompt: attributes.promptContent
                        })
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success && data.code) {
                            setAttributes({ codeContent: data.code });
                        } else {
                            setError(data.message || 'Failed to generate code');
                        }
                        setIsGenerating(false);
                    })
                    .catch(function(err) {
                        setError('Error: ' + err.message);
                        setIsGenerating(false);
                    });
                }
                
                return el(Fragment, {},
                    el(InspectorControls, {},
                        el(PanelBody, { title: __('Settings', 'stepfox-ai') },
                            el('p', {}, __('Enter a prompt and click "Generate with AI" to create code.', 'stepfox-ai'))
                        )
                    ),
                    el('div', { className: 'stepfox-ai-console-runner' },
                        error && el('div', { 
                            className: 'notice notice-error',
                            style: { margin: '10px 0' }
                        }, el('p', {}, error)),
                        
                        el('div', { style: { marginBottom: '10px' } },
                            el(RichText, {
                                tagName: 'p',
                                placeholder: __('Enter your prompt (e.g., "create a red button that says Click Me")', 'stepfox-ai'),
                                value: attributes.promptContent,
                                onChange: function(value) {
                                    setAttributes({ promptContent: value });
                                },
                                style: {
                                    border: '1px solid #ddd',
                                    padding: '10px',
                                    borderRadius: '4px'
                                }
                            })
                        ),
                        
                        el('div', { style: { marginBottom: '10px' } },
                            el(Button, {
                                isPrimary: true,
                                isBusy: isGenerating,
                                disabled: isGenerating,
                                onClick: generateCode
                            }, isGenerating ? __('Generating...', 'stepfox-ai') : __('Generate with AI', 'stepfox-ai'))
                        ),
                        
                        attributes.codeContent && el('div', { style: { marginBottom: '10px' } },
                            el('h4', {}, __('Preview:', 'stepfox-ai')),
                            el('iframe', {
                                ref: iframeRef,
                                style: {
                                    width: '100%',
                                    minHeight: '200px',
                                    border: '1px solid #ddd',
                                    borderRadius: '4px'
                                },
                                sandbox: 'allow-scripts'
                            })
                        ),
                        
                        el('div', {},
                            el('h4', {}, __('Generated Code:', 'stepfox-ai')),
                            el(PlainText, {
                                value: attributes.codeContent,
                                onChange: function(value) {
                                    setAttributes({ codeContent: value });
                                },
                                placeholder: __('Generated code will appear here...', 'stepfox-ai'),
                                style: {
                                    width: '100%',
                                    minHeight: '150px',
                                    fontFamily: 'monospace',
                                    fontSize: '13px',
                                    border: '1px solid #ddd',
                                    padding: '10px',
                                    borderRadius: '4px',
                                    backgroundColor: '#f5f5f5'
                                }
                            })
                        )
                    )
                );
            },
            
            save: function(props) {
                var attributes = props.attributes;
                
                return el('div', { className: 'stepfox-ai-console-runner' },
                    attributes.promptContent && el('div', { className: 'prompt' },
                        el(RichText.Content, {
                            tagName: 'p',
                            value: attributes.promptContent
                        })
                    ),
                    attributes.codeContent && el('pre', {},
                        el('code', {}, attributes.codeContent)
                    )
                );
            }
        });
        
        console.log('StepFox AI: Block registered successfully!');
    }
})();
