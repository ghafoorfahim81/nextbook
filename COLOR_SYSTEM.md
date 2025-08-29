# NextBook Color System

This document describes the updated color system that matches your beautiful login design.

## Overview

The color system has been updated to reflect the palette from your login interface:
- **Purples** (various shades from soft lavender to vibrant purple)
- **Whites** for backgrounds and text
- **Dark grays/blacks** for primary text
- **Bright yellow** for accent elements
- **Light blue-gray** for input fields

## CSS Variables (System Colors)

These colors are automatically available throughout your application:

### Primary Colors
- `--primary`: Vibrant purple (#8b5cf6) - matches your login button
- `--primary-foreground`: White text on primary backgrounds
- `--secondary`: Soft lavender background (#f3f1ff)
- `--accent`: Bright yellow (#f59e0b) - matches character jacket

### Background Colors
- `--background`: Pure white (#ffffff)
- `--card`: White for card backgrounds
- `--muted`: Light blue-gray (#f1f5f9) - for input fields

### Text Colors
- `--foreground`: Dark gray (#262626) - for primary text
- `--muted-foreground`: Medium gray (#737373) - for secondary text

## Custom NextBook Colors

You can also use specific color shades:

### Purple Palette
```css
bg-nextbook-purple-500  /* Main purple from login button */
bg-nextbook-purple-400  /* Lighter purple */
bg-nextbook-purple-600  /* Darker purple */
```

### Lavender Palette
```css
bg-nextbook-lavender-100  /* Soft lavender background */
bg-nextbook-lavender-200  /* Slightly darker lavender */
bg-nextbook-lavender-300  /* Medium lavender */
```

### Yellow Palette
```css
bg-nextbook-yellow-500  /* Bright yellow from character jacket */
bg-nextbook-yellow-400  /* Lighter yellow */
bg-nextbook-yellow-600  /* Darker yellow */
```

### Blue-Gray Palette
```css
bg-nextbook-blue-gray-100  /* Light blue-gray for inputs */
bg-nextbook-blue-gray-200  /* Slightly darker blue-gray */
bg-nextbook-blue-gray-300  /* Medium blue-gray */
```

## Usage Examples

### Buttons
```html
<!-- Primary button (purple) -->
<button class="bg-primary text-primary-foreground px-4 py-2 rounded">
  Sign In
</button>

<!-- Custom purple button -->
<button class="bg-nextbook-purple-500 text-white px-4 py-2 rounded">
  Custom Purple
</button>

<!-- Yellow accent button -->
<button class="bg-nextbook-yellow-500 text-foreground px-4 py-2 rounded">
  Highlight
</button>
```

### Cards and Containers
```html
<!-- System card -->
<div class="bg-card border border-border p-4 rounded">
  <h3 class="text-card-foreground">Card Title</h3>
  <p class="text-muted-foreground">Card content</p>
</div>

<!-- Custom lavender card -->
<div class="bg-nextbook-lavender-100 border border-nextbook-lavender-200 p-4 rounded">
  <h3 class="text-foreground">Lavender Card</h3>
  <p class="text-muted-foreground">Using custom colors</p>
</div>
```

### Input Fields
```html
<!-- System input -->
<input class="bg-input border border-border px-3 py-2 rounded" />

<!-- Custom blue-gray input -->
<input class="bg-nextbook-blue-gray-100 border border-nextbook-blue-gray-200 px-3 py-2 rounded" />
```

## Dark Mode Support

The color system automatically includes dark mode variants. When the `dark` class is applied to your HTML element, the colors will automatically switch to darker variants while maintaining the same purple/yellow theme.

## Implementation

The colors are implemented using:
1. **CSS Variables** in `resources/css/app.css` for system-wide consistency
2. **Tailwind CSS** classes for easy usage in components
3. **Custom color palette** in `tailwind.config.js` for specific brand colors

## Color Showcase Component

A `ColorShowcase.vue` component has been created to demonstrate all available colors. You can import and use this component to see the color system in action.

## Migration

If you were using the old color system, you can gradually migrate by:
1. Replacing old color classes with new semantic ones (e.g., `bg-primary` instead of `bg-gray-900`)
2. Using the new custom colors for specific brand elements
3. The system colors will automatically provide better contrast and consistency

The new system maintains backward compatibility while providing a more cohesive and brand-aligned color palette.
