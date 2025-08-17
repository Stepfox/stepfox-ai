# StepFox AI - Responsive Styles Examples

## How the AI Will Now Generate Code

### Example 1: Hero Section Request
**Prompt**: "Create a hero section with centered text"

**AI Will Generate**:
```html
<!-- wp:cover {
  "customId": "hero-section-123",
  "responsiveStyles": {
    "height": {
      "desktop": "600px",
      "tablet": "500px",
      "mobile": "400px"
    },
    "padding": {
      "desktop": {"top": "80px", "right": "40px", "bottom": "80px", "left": "40px"},
      "mobile": {"top": "40px", "right": "20px", "bottom": "40px", "left": "20px"}
    },
    "textAlign": {
      "desktop": "center",
      "tablet": "center",
      "mobile": "center"
    }
  }
} -->
<div class="wp-block-cover">
  <div class="wp-block-cover__inner-container">
    <!-- wp:heading {"level":1} -->
    <h1>Welcome to Our Site</h1>
    <!-- /wp:heading -->
  </div>
</div>
<!-- /wp:cover -->
```

### Example 2: Two Column Layout
**Prompt**: "Create a two column layout that stacks on mobile"

**AI Will Generate**:
```html
<!-- wp:group {
  "customId": "two-col-layout",
  "responsiveStyles": {
    "display": {
      "desktop": "flex",
      "tablet": "flex",
      "mobile": "block"
    },
    "flex_direction": {
      "desktop": "row",
      "tablet": "row",
      "mobile": "column"
    },
    "gap": {
      "desktop": "40px",
      "tablet": "30px",
      "mobile": "20px"
    },
    "padding": {
      "desktop": {"top": "60px", "right": "40px", "bottom": "60px", "left": "40px"},
      "mobile": {"top": "30px", "right": "20px", "bottom": "30px", "left": "20px"}
    }
  }
} -->
<div class="wp-block-group">
  <!-- wp:group {"responsiveStyles": {"width": {"desktop": "50%", "tablet": "50%", "mobile": "100%"}}} -->
  <div class="wp-block-group">
    <!-- wp:paragraph -->
    <p>Left column content</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
  
  <!-- wp:group {"responsiveStyles": {"width": {"desktop": "50%", "tablet": "50%", "mobile": "100%"}}} -->
  <div class="wp-block-group">
    <!-- wp:paragraph -->
    <p>Right column content</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
```

### Example 3: Animated Button
**Prompt**: "Create a button with hover animation"

**AI Will Generate**:
```html
<!-- wp:buttons -->
<div class="wp-block-buttons">
  <!-- wp:button {
    "customId": "cta-button",
    "animation": "fadeInUp",
    "animation_delay": "0.5s",
    "animation_duration": "0.8s",
    "responsiveStyles": {
      "padding": {
        "desktop": {"top": "15px", "right": "40px", "bottom": "15px", "left": "40px"},
        "mobile": {"top": "12px", "right": "30px", "bottom": "12px", "left": "30px"}
      },
      "font_size": {
        "desktop": "18px",
        "mobile": "16px"
      },
      "background_color": {
        "desktop": "#667eea"
      },
      "borderRadius": {
        "desktop": {"topLeft": "8px", "topRight": "8px", "bottomLeft": "8px", "bottomRight": "8px"}
      }
    },
    "custom_css": "this_block:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }"
  } -->
  <div class="wp-block-button">
    <a class="wp-block-button__link">Get Started</a>
  </div>
  <!-- /wp:button -->
</div>
<!-- /wp:buttons -->
```

## Key Points

1. **No More style="" attributes** - The AI uses responsiveStyles instead
2. **Device-specific values** - Different settings for desktop, tablet, mobile
3. **Proper structure** - Objects for padding, margin, borderWidth, etc.
4. **custom_css only when needed** - For hover states or special effects
5. **Animation support** - Using animation, animation_delay, animation_duration

## What NOT to Generate

❌ **Wrong** (using style attribute):
```html
<!-- wp:group {"style": {"spacing": {"padding": {"top": "40px"}}}} -->
```

✅ **Correct** (using responsiveStyles):
```html
<!-- wp:group {"responsiveStyles": {"padding": {"desktop": {"top": "40px"}}}} -->
```
