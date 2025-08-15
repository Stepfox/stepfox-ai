(function() {
    console.log('StepFox AI: Script loaded');
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBlock);
    } else {
        initializeBlock();
    }
    
    function initializeBlock() {
        // Ensure all required WordPress packages are available
        if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.data || !window.wp.blockEditor || !window.wp.components) {
            console.error('StepFox AI: Gutenberg packages not found. Make sure you are on a WordPress editor screen.');
            return;
        }

        // Destructure for easier access
        var el = window.wp.element.createElement;
        var registerBlockType = window.wp.blocks.registerBlockType;
        var useBlockProps = window.wp.blockEditor.useBlockProps;
    var RichText = window.wp.blockEditor.RichText;
    var PlainText = window.wp.blockEditor.PlainText;
    var Button = window.wp.components.Button;
    var Notice = window.wp.components.Notice;
    var useState = window.wp.element.useState;
    var useEffect = window.wp.element.useEffect;
    var useRef = window.wp.element.useRef;
    var __ = window.wp.i18n.__;

    registerBlockType('stepfox-ai/console-runner', {
        title: __('AI Console Runner', 'stepfox-ai'),
        description: __('Generate JS/HTML with AI and see a live preview.', 'stepfox-ai'),
        icon: 'shortcode',
        category: 'widgets',
        supports: {
            align: true,
            color: {
                background: true,
                text: true,
                gradients: true,
                link: true
            },
            typography: {
                fontSize: true,
                lineHeight: true,
                __experimentalFontFamily: true,
                __experimentalFontStyle: true,
                __experimentalFontWeight: true,
                __experimentalLetterSpacing: true,
                __experimentalTextDecoration: true,
                __experimentalTextTransform: true
            },
            spacing: {
                padding: true,
                margin: true
            },
            border: {
                color: true,
                radius: true,
                style: true,
                width: true
            },
            html: false,
        },
        attributes: {
            promptContent: {
                type: 'string',
                source: 'html',
                selector: '.prompt-field'
            },
            codeContent: {
                type: 'string',
                source: 'text',
                selector: '.code-field-for-save'
            },
        },
        edit: function(props) {
            var { attributes, setAttributes } = props;
            var blockProps = useBlockProps({
                className: 'wp-block-stepfox-ai-console-runner'
            });

            var [isGenerating, setIsGenerating] = useState(false);
            var [error, setError] = useState('');
            var iframeRef = useRef(null);

            // Effect to update the iframe when codeContent changes
            useEffect(function() {
                var iframe = iframeRef.current;
                if (!iframe) return;

                var doc = iframe.contentWindow.document;
                doc.open();
                doc.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>
                            body { 
                                margin: 10px; 
                                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                            }
                        </style>
                    </head>
                    <body>
                        ${attributes.codeContent || '<p style="color: #666;">Your generated code will appear here...</p>'}
                    </body>
                    </html>
                `);
                doc.close();
            }, [attributes.codeContent]);

            async function generateCodeWithAI() {
                if (!attributes.promptContent) {
                    setError(__('Please enter a prompt first!', 'stepfox-ai'));
                    return;
                }

                setIsGenerating(true);
                setError('');
                setAttributes({ codeContent: __('AI is thinking...', 'stepfox-ai') });

                try {
                    const response = await fetch(stepfoxAI.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': stepfoxAI.nonce
                        },
                        body: JSON.stringify({
                            prompt: attributes.promptContent
                        })
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || __('AI generation failed', 'stepfox-ai'));
                    }

                    if (result.success && result.code) {
                        setAttributes({ codeContent: result.code });
                    } else {
                        throw new Error(__('No code generated', 'stepfox-ai'));
                    }
                } catch (error) {
                    console.error('AI Generation Failed:', error);
                    setError(error.message);
                    setAttributes({ codeContent: `// ${__('AI failed. Error:', 'stepfox-ai')} ${error.message}` });
                } finally {
                    setIsGenerating(false);
                }
            }

            function runCodeInConsole() {
                var codeToRun = attributes.codeContent;
                if (!codeToRun) {
                    console.warn('[StepFox AI] Code field is empty. Nothing to run.');
                    return;
                }

                console.log('%c▶️ Running code from StepFox AI block...', 'color: #0073aa; font-weight: bold;');

                try {
                    var script = document.createElement('script');
                    script.textContent = `try {
                        ${codeToRun}
                    } catch(e) {
                        console.error('Error during execution:', e)
                    }`;
                    document.body.appendChild(script);
                    document.body.removeChild(script);
                } catch (e) {
                    console.error('[StepFox AI] Syntax Error:', e);
                }
            }

            return el(
                'div',
                blockProps,
                error && el(Notice, {
                    status: 'error',
                    isDismissible: true,
                    onRemove: () => setError('')
                }, error),
                el(RichText, {
                    tagName: 'p',
                    className: 'prompt-field',
                    placeholder: __('Write a prompt for the AI (e.g., "create a red button that says Click Me")...', 'stepfox-ai'),
                    value: attributes.promptContent,
                    onChange: (promptContent) => setAttributes({ promptContent }),
                    allowedFormats: ['core/bold', 'core/italic'],
                }),
                el('div', { className: 'button-group' },
                    el(Button, {
                        isPrimary: true,
                        onClick: generateCodeWithAI,
                        isBusy: isGenerating,
                        disabled: isGenerating
                    }, isGenerating ? __('Generating...', 'stepfox-ai') : __('Run with AI', 'stepfox-ai')),
                    el(Button, {
                        isSecondary: true,
                        onClick: runCodeInConsole,
                        disabled: isGenerating
                    }, __('Run in Console', 'stepfox-ai'))
                ),
                el('div', { className: 'live-preview-wrapper' },
                    el('p', {}, __('Live Preview:', 'stepfox-ai')),
                    el('iframe', {
                        ref: iframeRef,
                        className: 'live-preview-iframe',
                        sandbox: 'allow-scripts allow-same-origin',
                        title: __('Live Code Preview', 'stepfox-ai')
                    })
                ),
                el(PlainText, {
                    className: 'code-field',
                    placeholder: __('AI-generated JS/HTML code will appear here...', 'stepfox-ai'),
                    value: attributes.codeContent,
                    onChange: (codeContent) => setAttributes({ codeContent }),
                })
            );
        },
        save: function(props) {
            var { attributes } = props;
            var blockProps = useBlockProps.save();

            return el(
                'div',
                blockProps,
                el(RichText.Content, {
                    tagName: 'p',
                    className: 'prompt-field',
                    value: attributes.promptContent,
                }),
                attributes.codeContent && el(
                    'pre',
                    {},
                    el('code', {
                        className: 'code-field-for-save'
                    }, attributes.codeContent)
                )
            );
        },
    });
    
    console.log('StepFox AI: Block registered successfully');
    } // End initializeBlock
})();
