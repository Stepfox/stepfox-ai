# Troubleshooting Intermittent "Failed to generate code" Errors

## Common Causes and Solutions

### 1. **Rate Limiting**
**Symptoms**: Works sometimes, fails after multiple quick requests
**Solution**: Wait 20-60 seconds between requests

### 2. **Complex Prompts**
**Symptoms**: Simple prompts work, complex ones fail
**Solutions**:
- Break complex prompts into smaller parts
- Be more specific rather than asking for everything at once
- Example: Instead of "Create a complete blog with header, posts, sidebar, and footer", try "Create a blog header with navigation"

### 3. **Network Timeouts**
**Symptoms**: Takes a long time then fails
**Solutions**:
- Check your internet connection
- Try again - the plugin has a 5-minute timeout
- If on slow connection, use simpler prompts

### 4. **API Quota/Billing**
**Symptoms**: Worked before, now consistently fails
**Check**: 
1. Log into https://platform.openai.com
2. Check your usage and billing status
3. Ensure you have credits remaining

### 5. **Model Issues**
**Symptoms**: Specific error about model not found
**Solutions**:
1. Go to StepFox AI Settings
2. Select a different model (GPT-4o or GPT-3.5-turbo recommended)
3. Save and test connection

## How to Debug

### Check Browser Console
1. Open browser developer tools (F12)
2. Go to Console tab
3. Try generating code
4. Look for error messages starting with "StepFox AI" or "REST API error"

### Check WordPress Debug Log
Add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check: `wp-content/debug.log`

### Common Error Messages

**"Rate limit exceeded"**
- Wait 20-60 seconds
- Reduce frequency of requests

**"Insufficient quota"**
- Check OpenAI billing
- Add payment method if needed

**"Model not found"**
- Change to a supported model in settings
- Avoid GPT-5 models (not released yet)

**"Request timed out"**
- Simplify your prompt
- Check internet connection
- Try again

## Best Practices

1. **Start Simple**: Test with basic prompts first
2. **Be Specific**: Clear, focused prompts work better
3. **Patience**: Complex requests can take 1-2 minutes
4. **Iterative**: Build complex layouts step by step

## Still Having Issues?

1. **Clear Browser Cache**: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
2. **Update Plugin**: Ensure you have the latest version
3. **Test API Key**: Use the "Test Connection" button in settings
4. **Check Server Logs**: Look for PHP errors or timeouts

## Example Working Prompts

Simple (always works):
- "Create a red button that says Hello"
- "Make a paragraph with lorem ipsum text"

Medium (usually works):
- "Create a hero section with title and subtitle"
- "Build a contact form with name and email"

Complex (may need retry):
- "Generate a complete blog post layout with featured image, title, meta, and content"
- "Create a responsive navigation menu with dropdowns"
