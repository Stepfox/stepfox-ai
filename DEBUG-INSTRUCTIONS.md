# Debug Instructions for StepFox AI

## Model-Specific Requirements

Some OpenAI models have specific requirements:

1. **Temperature Parameter**: 
   - GPT-4o and GPT-4o Mini only support the default temperature value (1.0)
   - All GPT-5 models (future) are treated as temperature-restricted to avoid errors
2. **Max Tokens Parameter**: Newer models use `max_completion_tokens` instead of `max_tokens`
3. The plugin automatically handles these differences

### GPT-5 Model Support
- **GPT-5**: Best for complex reasoning, code-heavy and multi-step tasks
- **GPT-5 Mini**: Cost-optimized reasoning, balances speed and capability  
- **GPT-5 Nano**: High-throughput, simple instruction-following tasks
- **GPT-5 Chat Latest**: Latest GPT-5 chat model
- All GPT-5 models are treated conservatively (no custom temperature) for compatibility

## Timeout Configuration

The plugin is configured to handle long-running AI requests:

1. **Maximum Request Time**: 5 minutes (300 seconds)
2. **PHP Execution Time**: Extended to 5 minutes
3. **Frontend Timeout**: 5 minutes with AbortController
4. **Clear Error Messages**: Specific timeout notifications

If you're getting timeout errors:
- Try simpler prompts
- Break complex requests into smaller parts
- Check your server's max_execution_time setting

## Local Development Support

The plugin now automatically handles images from local development sites! When you upload images from domains like `.local`, `localhost`, or `127.0.0.1`, the plugin will:

1. **Automatically detect local images**
2. **Convert them to base64 format** so the AI can access them
3. **Send them directly to the AI** without requiring external access

### Image Size Limits
- Maximum image size: 20MB
- Large images may take longer to process
- The plugin will show an error if images are too large

## To Debug Errors:

1. **Check WordPress Debug Log**:
   - Open your `wp-config.php` file
   - Make sure these lines are present:
     ```php
     define( 'WP_DEBUG', true );
     define( 'WP_DEBUG_LOG', true );
     define( 'WP_DEBUG_DISPLAY', false );
     ```
   - The debug log will be at: `wp-content/debug.log`

2. **Test the REST API Directly**:
   - Visit: `https://ufc.local/wp-content/plugins/stepfox-ai/test-rest-api.php`
   - This will show you if the REST API is working correctly

3. **Check Browser Console**:
   - Open Developer Tools (F12)
   - Go to Network tab
   - Try to generate content in the block
   - Look for the request to `/wp-json/stepfox-ai/v1/generate`
   - Check the Response tab to see the actual error message

4. **Quick Fixes to Try**:
   - Clear your browser cache
   - Log out and log back into WordPress
   - Try in an incognito/private browser window
   - Make sure you saved the API key in settings

5. **Alternative Test**:
   - Run this in terminal:
     ```bash
     cd /Users/step/Local Sites/ufc/app/public/wp-content/plugins/stepfox-ai
     php test-api-key.php YOUR_API_KEY gpt-4o
     ```

## Common Issues:

1. **Permalinks**: Go to Settings > Permalinks and click "Save Changes" (don't change anything, just save)
2. **User Permissions**: Make sure your user has "edit_posts" capability
3. **Plugin Conflicts**: Try deactivating other plugins temporarily
4. **Local Development**: Some local environments have issues with REST API authentication

## Need More Help?

Share the following with support:
- The error from browser console
- The output from test-rest-api.php
- Any errors from wp-content/debug.log
