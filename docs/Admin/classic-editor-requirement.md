# Classic Editor Requirement for ACF Translation Modal

## Overview

The **ACF Translation Modal** feature in Multilingual Bridge requires the **Classic Editor** to function properly. When the Block Editor (Gutenberg) is active, translation modal features are automatically disabled.

## Why Classic Editor Only?

The translation modal was designed to work with ACF fields in the Classic Editor environment because:

1. **DOM Structure** - The modal relies on ACF's Classic Editor DOM structure to inject translation buttons
2. **Field Detection** - JavaScript detects translatable fields using ACF's Classic Editor field wrapper classes
3. **Field Updates** - The modal updates field values using ACF's Classic Editor JavaScript API
4. **React Integration** - The modal renders alongside the Classic Editor's meta box layout

**Block Editor (Gutenberg)** has a completely different architecture, using React components and the Block API, which is incompatible with the current implementation.

## Automatic Detection

The plugin **automatically detects** which editor is active and:

- ✅ **Enables translation modal** when Classic Editor is active
- ❌ **Disables translation modal** when Block Editor is active

### Detection Logic

The plugin checks the following (in order):

1. **Classic Editor Plugin** - If the Classic Editor plugin is active and configured to replace the Block Editor
2. **`use_block_editor_for_post()` function** - WordPress core function that determines which editor is used
3. **Filter Override** - Custom filter `multilingual_bridge_enable_translation_modal` (see below)

## Enabling Classic Editor

### Method 1: Classic Editor Plugin (Recommended)

Install and activate the official **Classic Editor plugin**:

1. Install from WordPress.org: [Classic Editor](https://wordpress.org/plugins/classic-editor/)
2. Activate the plugin
3. Go to **Settings → Writing**
4. Choose:
   - **"Classic Editor"** as default editor
   - **"Yes"** to allow users to switch editors (optional)

### Method 2: Disable Block Editor for Specific Post Types

Add this to your theme's `functions.php` or a custom plugin:

```php
/**
 * Disable Block Editor for specific post types
 */
add_filter( 'use_block_editor_for_post_type', function( $use_block_editor, $post_type ) {
    // Disable for 'post' and 'page' post types
    if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
        return false;
    }
    
    return $use_block_editor;
}, 10, 2 );
```

### Method 3: Disable Block Editor for All Posts

Add this to `wp-config.php`:

```php
// Disable Block Editor entirely
define( 'CLASSIC_EDITOR_REPLACE', true );
```

## Verifying Editor Status

Check which editor is active on a post edit screen:

### Classic Editor Active (Translation Modal Works)
- Meta boxes appear below the content editor
- ACF fields appear in traditional meta boxes
- Translation icon buttons appear next to ACF field labels
- No Block Editor toolbar at the top

### Block Editor Active (Translation Modal Disabled)
- Block Editor toolbar appears at the top
- Content is composed of blocks
- ACF fields may appear in sidebar or as blocks
- Translation icon buttons do NOT appear

## Custom Override Filter

If you need to manually control when the translation modal is enabled, use the filter:

```php
/**
 * Override translation modal availability
 *
 * @param bool $enabled Whether modal is enabled (based on automatic detection)
 * @param WP_Post|null $post Current post object
 * @param WP_Screen|null $screen Current admin screen
 * @return bool
 */
add_filter( 'multilingual_bridge_enable_translation_modal', function( $enabled, $post, $screen ) {
    // Force enable for specific post type (use with caution!)
    if ( $post && $post->post_type === 'custom_post_type' ) {
        return true;
    }
    
    // Force disable for specific post type
    if ( $post && $post->post_type === 'another_post_type' ) {
        return false;
    }
    
    return $enabled; // Use automatic detection
}, 10, 3 );
```

**Warning:** Forcing the modal to enable when Block Editor is active may cause errors. Only use this if you have a custom editor implementation.

## What Still Works with Block Editor?

Even when the translation modal is disabled, these features continue to work:

✅ **REST API Translation Endpoints** - `/wp-json/multilingual-bridge/v1/translation/translate`  
✅ **Language Fields in REST API** - `language_code`, `_links.translations`  
✅ **WPML Helper Functions** - `WPML_Post_Helper`, `WPML_Language_Helper`  
✅ **Translation Manager** - PHP-based translation via `Translation_Manager`  
✅ **Language Debug Tools** - Admin page for managing language assignments  

## Future Block Editor Support

We are considering adding Block Editor support in a future version. This would involve:

- Creating a Gutenberg sidebar panel for translations
- Building Block Editor-compatible translation UI
- Using the Block Editor's data store API
- Supporting ACF Blocks in addition to meta fields

If you need Block Editor support, please open a feature request on GitHub.

## Troubleshooting

### Translation Buttons Don't Appear

**Symptoms:**
- ACF fields are visible in Classic Editor
- No translation icon buttons appear next to field labels

**Possible Causes:**

1. **Block Editor is Active**
   - Check editor toolbar - if you see the Block Editor, Classic Editor is not active
   - Solution: Enable Classic Editor (see above)

2. **Editing Original Post**
   - Translation UI only appears on translation posts, not the original
   - Solution: Edit a translation post instead

3. **Field Type Not Supported**
   - Only registered translatable field types show translation UI
   - Solution: Check `Field_Registry` documentation

4. **JavaScript Not Loaded**
   - Check browser console for errors
   - Ensure `multilingual-bridge-admin` assets are enqueued

### Classic Editor Plugin Installed but Block Editor Still Appears

1. Check **Settings → Writing**
2. Ensure "Default editor for all users" is set to "Classic Editor"
3. Clear browser cache
4. Try a different post type - Classic Editor settings can be post-type specific

### Error: "acf is not defined"

This means ACF JavaScript hasn't loaded. Ensure:
- ACF plugin is active
- You're on a post edit screen with ACF fields
- ACF fields are assigned to the current post type

## API Reference

### Filter: `multilingual_bridge_enable_translation_modal`

**Location:** `src/Multilingual_Bridge.php:197`, `src/Integrations/ACF/ACF_Translation.php:154`

**Parameters:**
- `bool $enabled` - Whether modal should be enabled (automatic detection result)
- `WP_Post|null $post` - Current post object
- `WP_Screen|null $screen` - Current admin screen object

**Returns:** `bool` - Whether to enable translation modal

**Example:**
```php
// Disable translation modal on Mondays (just because)
add_filter( 'multilingual_bridge_enable_translation_modal', function( $enabled ) {
    return date( 'N' ) !== '1' ? $enabled : false;
} );
```

## Related Documentation

- [ACF Translation](./acf-translation.md)
- [Translation Architecture](../Translation/architecture-overview.md)
- [REST API Translation](../REST_API/wpml-language-filtering.md)
