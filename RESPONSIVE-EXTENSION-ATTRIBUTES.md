# StepFox Looks - Responsive Extension Attributes

## System Attributes

### Core System Attributes
- **customId** - Custom ID for the block (default: "stepfox-not-set-id")
- **device** - Current device view (default: "desktop")
- **element_state** - Element state like hover, active, etc. (default: "normal")

### Additional System Attributes (for non-spacer/separator blocks)
- **custom_css** - Custom CSS code for the block
- **custom_js** - Custom JavaScript code for the block
- **animation** - Animation class/type
- **animation_delay** - Animation delay value
- **animation_duration** - Animation duration value

### Main Responsive Styles Container
- **responsiveStyles** - Object containing all responsive style properties for different devices

## Layout & Positioning Properties

### Position & Display
- **position** - CSS position (static, relative, absolute, fixed, sticky)
- **display** - CSS display (block, inline, flex, grid, none, etc.)
- **grid_template_columns** - Grid template columns (for display: grid)

### Dimensions
- **width** - Width value with unit
- **height** - Height value with unit
- **min_width** - Minimum width
- **max_width** - Maximum width  
- **min_height** - Minimum height
- **max_height** - Maximum height

### Position Offsets
- **top** - Top position offset
- **right** - Right position offset
- **bottom** - Bottom position offset
- **left** - Left position offset

### Other Layout
- **z_index** - Z-index stacking order
- **order** - Flexbox/Grid order
- **box_sizing** - Box sizing model (content-box, border-box)
- **visibility** - Visibility (visible, hidden, collapse)
- **float** - Float (none, left, right)
- **clear** - Clear floats (none, left, right, both)
- **overflow** - Overflow handling
- **zoom** - Zoom level

## Typography Properties

- **font_size** - Font size with unit
- **line_height** - Line height
- **font_weight** - Font weight (100-900, normal, bold)
- **font_style** - Font style (normal, italic, oblique)
- **text_transform** - Text transform (none, uppercase, lowercase, capitalize)
- **text_decoration** - Text decoration (none, underline, overline, line-through)
- **textAlign** - Text alignment (left, center, right, justify)
- **letter_spacing** - Letter spacing
- **word_spacing** - Word spacing
- **text_shadow** - Text shadow

## Spacing Properties

### Padding (Object with top, right, bottom, left)
- **padding** - Padding values object
  - padding.top
  - padding.right
  - padding.bottom
  - padding.left

### Margin (Object with top, right, bottom, left)
- **margin** - Margin values object
  - margin.top
  - margin.right
  - margin.bottom
  - margin.left

## Border Properties

### Border Width (Object with top, right, bottom, left)
- **borderWidth** - Border width values object
  - borderWidth.top
  - borderWidth.right
  - borderWidth.bottom
  - borderWidth.left

### Border Style (Object with top, right, bottom, left)
- **borderStyle** - Border style values object
  - borderStyle.top
  - borderStyle.right
  - borderStyle.bottom
  - borderStyle.left

### Border Color (Object with top, right, bottom, left)
- **borderColor** - Border color values object
  - borderColor.top
  - borderColor.right
  - borderColor.bottom
  - borderColor.left

### Border Radius (Object with topLeft, topRight, bottomLeft, bottomRight)
- **borderRadius** - Border radius values object
  - borderRadius.topLeft
  - borderRadius.topRight
  - borderRadius.bottomLeft
  - borderRadius.bottomRight

## Flexbox Properties

- **flex_direction** - Flex direction (row, column, row-reverse, column-reverse)
- **justify** - Justify content (flex-start, center, flex-end, space-between, space-around)
- **flexWrap** - Flex wrap (nowrap, wrap, wrap-reverse)
- **flex_grow** - Flex grow factor
- **flex_shrink** - Flex shrink factor
- **align_items** - Align items (flex-start, center, flex-end, stretch, baseline)
- **align_self** - Align self (auto, flex-start, center, flex-end, stretch, baseline)
- **justify_self** - Justify self
- **gap** - Gap between flex/grid items
- **align_content** - Align content (flex-start, center, flex-end, stretch, space-between, space-around)

## Background Properties

- **background_color** - Background color (supports gradients)
- **background_image** - Background image URL
- **background_size** - Background size (cover, contain, auto, etc.)
- **background_position** - Background position
- **background_repeat** - Background repeat (repeat, no-repeat, repeat-x, repeat-y)

## Effects & Transforms

- **transform** - CSS transform
- **transition** - CSS transition
- **box_shadow** - Box shadow
- **filter** - CSS filter effects
- **backdrop_filter** - Backdrop filter effects
- **opacity** - Opacity (0-1)

## Image & Media Properties

- **object_fit** - Object fit (fill, contain, cover, none, scale-down)
- **object_position** - Object position

## Interaction Properties

- **cursor** - Cursor type (pointer, default, text, etc.)
- **user_select** - User select (none, auto, text, all)
- **pointer_events** - Pointer events (none, auto)

## Usage Notes

1. All style properties are stored within the `responsiveStyles` object
2. Each property can have different values for different devices (desktop, tablet, mobile)
3. Properties support standard CSS units (px, %, em, rem, vw, vh, etc.)
4. Color properties support hex, rgb, rgba, and CSS color names
5. The extension automatically handles responsive switching based on device breakpoints

## Example Structure

```javascript
{
  customId: "unique-id-123",
  device: "desktop",
  responsiveStyles: {
    width: {
      desktop: "100%",
      tablet: "80%",
      mobile: "100%"
    },
    padding: {
      desktop: {
        top: "20px",
        right: "15px",
        bottom: "20px",
        left: "15px"
      },
      mobile: {
        top: "10px",
        right: "10px",
        bottom: "10px",
        left: "10px"
      }
    },
    font_size: {
      desktop: "16px",
      tablet: "15px",
      mobile: "14px"
    }
  },
  animation: "fadeIn",
  animation_delay: "0.5s",
  animation_duration: "1s"
}
```
