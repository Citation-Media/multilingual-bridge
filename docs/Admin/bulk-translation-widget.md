# Bulk Translation Widget

The Bulk Translation Widget provides a convenient way to translate all post meta fields from a source language post to multiple target languages simultaneously.

## Overview

The bulk translation feature appears as a sidebar widget on post edit screens for source language posts. It allows you to:

- Select multiple target languages at once
- Automatically create translation posts if they don't exist
- Translate all translatable post meta (ACF fields, custom fields, etc.)
- View translation status for each language
- Track progress and see detailed results

## Features

### Automatic Post Creation

If a translation post doesn't exist for a target language, the widget will automatically create one:

- Creates post as draft for review
- Copies title, content, and excerpt from source
- Links to source post via WPML
- Maintains post type and author

### Smart Meta Translation

The widget uses the Meta Translation Handler to intelligently translate post meta:

- **ACF Fields**: Detects and translates ACF fields based on field type registry
- **Regular Meta**: Translates custom post meta
- **Automatic Skipping**: Skips internal WordPress and WPML meta
- **Extensible**: Supports custom meta handlers via hooks

### Visual Feedback

- Translation status icons (✓ for exists, marker for new)
- Progress bar during translation
- Detailed results per language
- Error messages for failed translations

## Usage

### For Content Editors

1. **Edit a source language post** (e.g., English post)
2. **Locate the "Bulk Translation" widget** in the sidebar
3. **Select target languages** using checkboxes
4. **Click "Generate Translations"** button
5. **Review results** when complete

The widget shows:
- Source language name
- Available target languages with translation status
- Number of meta fields translated
- Any errors encountered

### For Developers

#### Supported Post Types

By default, the widget appears on `post` and `page` edit screens. Add support for custom post types:

```php
add_filter( 'multilingual_bridge_bulk_translation_post_types', function( $post_types ) {
    $post_types[] = 'product';
    $post_types[] = 'portfolio';
    return $post_types;
} );
```

#### REST API Endpoint

The widget uses the `/multilingual-bridge/v1/bulk-translate` endpoint:

**Request:**
```json
POST /wp-json/multilingual-bridge/v1/bulk-translate
{
  "post_id": 123,
  "target_languages": ["de", "fr", "es"]
}
```

**Response:**
```json
{
  "success": true,
  "source_post": 123,
  "languages": {
    "de": {
      "success": true,
      "target_post_id": 456,
      "created_new": true,
      "meta_translated": 15,
      "meta_skipped": 3,
      "errors": []
    },
    "fr": {
      "success": true,
      "target_post_id": 789,
      "created_new": false,
      "meta_translated": 15,
      "meta_skipped": 3,
      "errors": []
    }
  }
}
```

#### Customization Hooks

**Filter meta before translation:**
```php
add_filter( 'multilingual_bridge_pre_translate_meta', function( $all_meta, $source_post_id, $target_post_id, $target_lang, $source_lang ) {
    // Remove specific meta keys
    unset( $all_meta['_internal_cache'] );
    return $all_meta;
}, 10, 5 );
```

**Skip specific meta keys:**
```php
add_filter( 'multilingual_bridge_skip_meta_key', function( $should_skip, $meta_key ) {
    // Skip all meta starting with 'temp_'
    if ( str_starts_with( $meta_key, 'temp_' ) ) {
        return true;
    }
    return $should_skip;
}, 10, 2 );
```

**Control post meta translation:**
```php
add_filter( 'multilingual_bridge_should_translate_post_meta', function( $should_translate, $meta_key, $meta_value, $source_post_id ) {
    // Only translate meta with more than 10 characters
    if ( strlen( $meta_value ) < 10 ) {
        return false;
    }
    return $should_translate;
}, 10, 4 );
```

**After translation action:**
```php
add_action( 'multilingual_bridge_after_translate_meta', function( $results, $source_post_id, $target_post_id, $target_lang, $source_lang ) {
    // Log translation results
    error_log( sprintf(
        'Translated %d meta fields from post %d to post %d (%s)',
        $results['translated'],
        $source_post_id,
        $target_post_id,
        $target_lang
    ) );
}, 10, 5 );
```

## Architecture

### Components

1. **Bulk_Translation_Widget** (`src/Admin/Bulk_Translation_Widget.php`)
   - Renders sidebar widget UI
   - Enqueues JavaScript and CSS
   - Shows only on source language posts

2. **Meta_Translation_Handler** (`src/Translation/Meta_Translation_Handler.php`)
   - Routes meta to appropriate handlers
   - Manages ACF vs. regular meta detection
   - Extensible handler registry system

3. **WPML_REST_Translation** (`src/REST/WPML_REST_Translation.php`)
   - Bulk translation REST endpoint
   - Post creation and WPML linking
   - Error handling and validation

4. **JavaScript** (`resources/admin/js/bulk-translation.js`)
   - Widget interactions
   - API communication
   - Progress tracking and results display

5. **CSS** (`resources/admin/css/bulk-translation.css`)
   - Widget styling
   - WordPress admin design consistency

### Translation Flow

```
User selects languages → Click "Generate Translations"
    ↓
JavaScript validates selection
    ↓
Call REST API endpoint
    ↓
For each target language:
  ↓
  Check if translation exists
  ↓
  Create new post if needed
  ↓
  Link via WPML
  ↓
  Get all source post meta
  ↓
  For each meta field:
    ↓
    Check if should skip
    ↓
    Try ACF handler (if ACF active)
    ↓
    Try regular meta handler (fallback)
    ↓
    Translate value via Translation Manager
    ↓
    Save to target post
    ↓
  Return results
```

## Requirements

- **WPML**: Required for language management
- **Translation Provider**: DeepL or custom provider must be configured
- **Classic Editor**: Widget works on both Classic and Block Editor
- **Permissions**: User must have `edit_posts` capability

## Field Type Support

The following ACF field types are translatable by default:

- `text`
- `textarea`
- `wysiwyg`

See [Field Registry documentation](field-registry.md) for adding support for additional field types.

## Limitations

- **Maximum Languages**: Limited to 20 target languages per request
- **Text Length**: Individual text fields limited to 50,000 characters
- **Complex Fields**: Array/object meta values are skipped
- **Short Values**: Meta values under 3 characters are skipped
- **Draft Status**: New translation posts are created as drafts for review

## Troubleshooting

### Widget Not Appearing

- Verify post is a source language post (not a translation)
- Check post type is supported (see supported post types filter)
- Ensure WPML is active

### Translation Failed

- Check translation provider API key is configured
- Verify target language is supported by provider
- Check error messages in results for specific issues

### Meta Not Translated

- Verify field type is registered (for ACF fields)
- Check field value is a translatable string (not array/object)
- Review meta skip filters

### Performance Issues

- Limit number of selected languages (5-10 recommended)
- Consider translating posts with fewer meta fields first
- Check translation provider rate limits

## See Also

- [Meta Translation Handler](../Translation/meta-translation-handler.md)
- [Field Registry](field-registry.md)
- [Translation Architecture](../Translation/architecture-overview.md)
- [REST API Documentation](../REST_API/wpml-rest-translation.md)
