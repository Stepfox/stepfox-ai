=== StepFox AI ===
Contributors: stepfox
Tags: ai, openai, code generation, gutenberg, block
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered code generation block for WordPress using OpenAI. Generate JavaScript and HTML code directly in the block editor.

== Description ==

StepFox AI adds an AI-powered console runner block to your WordPress editor. Using OpenAI's powerful language models, you can generate JavaScript and HTML code by simply describing what you want in plain English.

= Features =

* **AI Code Generation**: Generate JavaScript and HTML code using natural language prompts
* **Live Preview**: See your generated code rendered in real-time within the editor
* **Console Execution**: Run JavaScript code directly in the browser console
* **Secure API Integration**: API keys are stored securely on the server
* **Multiple Model Support**: Choose between GPT-3.5 Turbo, GPT-4, and GPT-4 Turbo
* **Block Editor Integration**: Seamlessly integrated with the WordPress block editor

= Use Cases =

* Quickly prototype interactive elements
* Generate form validations
* Create animations and effects
* Build simple games and calculators
* Generate HTML templates
* Test JavaScript code snippets

== Installation ==

1. Upload the `stepfox-ai` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to StepFox AI > Settings to configure your OpenAI API key
4. Start using the AI Console Runner block in your posts and pages

= Getting an OpenAI API Key =

1. Visit [OpenAI Platform](https://platform.openai.com/)
2. Sign up or log in to your account
3. Navigate to API keys section
4. Create a new API key
5. Copy the key and paste it in the plugin settings

== Frequently Asked Questions ==

= Is this plugin free to use? =

The plugin itself is free, but you'll need an OpenAI API key which requires a paid OpenAI account. OpenAI charges based on usage.

= Is my API key secure? =

Yes, your API key is stored securely in the WordPress database and is never exposed to the frontend. All API calls are made server-side.

= Can I use this with any OpenAI model? =

Currently, the plugin supports GPT-3.5 Turbo, GPT-4, and GPT-4 Turbo. You can select your preferred model in the settings.

= What kind of code can I generate? =

You can generate any JavaScript or HTML code. The AI is particularly good at creating interactive elements, forms, animations, and small utilities.

= Can I edit the generated code? =

Yes, the generated code appears in an editable field where you can make modifications before saving.

== Screenshots ==

1. AI Console Runner block in the editor
2. Settings page with API configuration
3. Live preview of generated code
4. Code running in the browser console

== Changelog ==

= 1.0.0 =
* Initial release
* AI Console Runner block
* OpenAI integration
* Live preview functionality
* Console execution feature
* Admin settings page

== Upgrade Notice ==

= 1.0.0 =
Initial release of StepFox AI.

== Privacy Policy ==

This plugin sends prompts to OpenAI's API to generate code. Please refer to [OpenAI's Privacy Policy](https://openai.com/privacy/) for information about how they handle data. No personal data is collected by the plugin itself.
