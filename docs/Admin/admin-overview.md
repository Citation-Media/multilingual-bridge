# Admin Features Overview

This document provides a high-level overview of the admin features in Multilingual Bridge.

## Translation Features

### 1. ACF Translation Modal

**Purpose:** Allows translating individual ACF fields using a modal interface in the Classic Editor.

**How It Works:**
- Adds translation icons next to ACF field labels on translation posts
- Opens a modal showing original text (left) and translated text (right)
- Uses DeepL API to automatically translate text
- Supports text, textarea, wysiwyg, and lexical-editor field types

**Requirements:**
- Classic Editor must be active (not Block Editor/Gutenberg)
- Post must be a translation (not the original language post)
- ACF and WPML plugins must be active

**Key Files:**
- `src/Integrations/ACF/ACF_Translation_Modal.php` - Main integration class
- `resources/admin/js/translation.js` - Entry point and initialization
- `resources/admin/js/components/TranslationModal.js` - React modal UI

**Architecture:**
- PHP adds data attributes to ACF field wrappers
- JavaScript detects translatable fields and injects action buttons
- React modal handles translation workflow and API calls
- Custom events coordinate between React and vanilla JavaScript

---

### 2. Automatic Translation Widget

**Purpose:** Bulk translate all meta fields from source post to multiple target languages at once.

**How It Works:**
- Appears as a sidebar widget on source language post edit screens
- Select multiple target languages via checkboxes
- Automatically creates translation posts if they don't exist
- Translates all translatable meta (ACF fields and custom meta)
- Shows progress and detailed results per language

**Key Features:**
- Auto-creates draft translation posts
- Smart meta detection (skips internal WordPress/WPML meta)
- Visual feedback with status icons and progress bar
- Field type-aware translation (text translated, images copied, etc.)

**Key Files:**
- `src/Admin/Automatic_Translation_Widget.php` - Widget UI
- `src/Translation/Meta_Translation_Handler.php` - Core translation logic
- `src/REST/WPML_REST_Translation.php` - REST endpoint
- `resources/admin/js/automatic-translation.js` - Frontend interactions

**REST Endpoint:** `POST /wp-json/multilingual-bridge/v1/automatic-translate`

---

### 3. Language Debug Tool

**Purpose:** Identify and fix posts with language-related issues.

**Location:** Tools → Language Debug

**What It Fixes:**

1. **Unconfigured Language Issues** - Posts in deactivated/removed languages
   - Preflight check to see affected posts
   - Delete posts in unconfigured languages
   - Reassign posts to an active language

2. **Cross-Language Term Issues** - Posts with categories/tags in wrong language
   - Preflight check to see mismatched terms
   - Remove incorrect term associations

**Key Files:**
- `src/Admin/Language_Debug.php` - Admin interface and logic
- `src/Helpers/WPML_Post_Helper.php` - Post language operations
- `src/Helpers/WPML_Term_Helper.php` - Term language operations

**Best Practice:** Always run preflight checks before executing fix operations.

---

## Important Implementation Details

### Meta Translation Handler

**File:** `src/Translation/Meta_Translation_Handler.php`

Handles automatic meta translation with smart field detection:

- **ACF Fields:** Routes to ACF-specific handler based on field type registry
- **Text Fields:** Translated via DeepL API
- **Non-text Fields:** Copied as-is (images, files, relationships, etc.)
- **ACF Field Keys:** Internal references (`_fieldname` meta) are never translated
- **Empty Fields:** Synced to translations (deleted to maintain consistency)

### Field Type Registry

**File:** `src/Translation/Field_Registry.php`

Central registry for translatable field types. Default supported types:
- `text`
- `textarea`
- `wysiwyg`

Extend via filter:
```php
add_filter( 'multilingual_bridge_translatable_field_types', function( $types ) {
    $types[] = 'email';
    return $types;
} );
```

### Translation Providers

**File:** `src/Translation/Translation_Manager.php`

Provider-agnostic translation system:
- DeepL provider included by default
- Custom providers can be registered via hooks
- Returns `WP_Error` on translation failure

---

## REST API Endpoints

### Get Original Meta Value
```
GET /wp-json/multilingual-bridge/v1/meta/{post_id}/{field_key}
```
Returns field value from default language post.

### Translate Text
```
POST /wp-json/multilingual-bridge/v1/translate
```
Translates text using configured provider (DeepL).

### Automatic Translate
```
POST /wp-json/multilingual-bridge/v1/automatic-translate
```
Bulk translates all meta for specified target languages.

---

## Common Hooks & Filters

### ACF Field Types
```php
// Add custom translatable field types
add_filter( 'multilingual_bridge_acf_supported_types', function( $types ) {
    $types[] = 'custom_field_type';
    return $types;
} );
```

### Translation Modal Availability
```php
// Override automatic editor detection
add_filter( 'multilingual_bridge_enable_translation_modal', function( $enabled, $post, $screen ) {
    return $enabled;
}, 10, 3 );
```

### Skip Meta Translation
```php
// Exclude specific meta keys from translation
add_filter( 'multilingual_bridge_skip_meta_key', function( $should_skip, $meta_key ) {
    if ( str_starts_with( $meta_key, 'temp_' ) ) {
        return true;
    }
    return $should_skip;
}, 10, 2 );
```

---

## Security & Permissions

All admin features require appropriate capabilities:
- **Translation modal & widget:** `edit_posts`
- **Language debug tool:** `manage_options`
- REST API uses WordPress nonce verification
- All inputs validated and sanitized

---

## Troubleshooting Quick Reference

**Translation buttons not appearing:**
- Verify Classic Editor is active (not Block Editor)
- Check post is a translation, not original
- Ensure field type is registered as translatable

**Bulk translation not working:**
- Verify DeepL API key is configured
- Check target language is supported
- Review error messages in results

**Language debug finds no issues:**
- Clear WPML cache: WPML → Support → Troubleshooting
- Verify WPML is fully activated

---

## Related Documentation

- `/docs/Translation/` - Translation system architecture
- `/docs/Helpers/` - WPML helper functions
- `/docs/REST_API/` - REST API extensions
