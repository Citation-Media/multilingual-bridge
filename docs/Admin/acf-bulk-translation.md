# ACF Bulk Translation

This document describes the bulk translation feature for ACF fields in the Multilingual Bridge plugin.

## Overview

The bulk translation feature allows users to translate all ACF fields on a post at once, rather than translating them one by one. This significantly speeds up the translation workflow for posts with multiple translatable fields.

## Features

- **One-Click Translation**: Translate all ACF fields on a post with a single button click
- **Progress Tracking**: Real-time progress indicator showing which fields are being translated
- **Batch Processing**: Automatically translates all supported field types using DeepL
- **Smart Field Detection**: Only shows fields that have content in the source language
- **Error Handling**: Continues processing even if individual fields fail to translate
- **Preview Before Apply**: Review all translations before inserting them into fields

## User Interface

### Translate All Fields Button

A "Translate All Fields" button appears in the WordPress post edit screen sidebar, just above the "Update Post" button. This button only appears when editing a translation post (not the original language post).

### Bulk Translation Modal

When clicked, the button opens a modal that:
1. Loads all translatable ACF fields from the post
2. Displays a list of fields with their labels
3. Shows translation progress for each field
4. Provides "Translate All" and "Apply Translations" buttons

### Status Indicators

Each field in the list shows a status indicator:
- **No icon**: Field not yet processed
- **Spinner**: Field currently being translated
- **Green checkmark**: Translation completed successfully
- **Warning icon**: Translation failed for this field
- **"Skipped"**: Field has no source content to translate

## Technical Implementation

### Architecture

The bulk translation feature follows the same architecture as the single-field translation:

```
┌─────────────────┐
│ PHP Backend     │
│ ACF_Bulk_       │
│ Translation     │
└────────┬────────┘
         │
         │ Enqueues scripts
         │ Adds button to UI
         │
┌────────▼────────┐
│ React Frontend  │
│ Bulk           │
│ Translation    │
│ Modal          │
└────────┬────────┘
         │
         │ API Calls
         │
┌────────▼────────┐
│ REST API        │
│ /fields/{id}    │
│ /translate      │
└─────────────────┘
```

### PHP Components

#### `ACF_Bulk_Translation` Class
**File**: `src/Integrations/ACF/ACF_Bulk_Translation.php`

Handles server-side functionality:
- Adds "Translate All Fields" button to post edit screen
- Checks if post is a translation (not original language)
- Injects React modal container into admin footer

**Hooks**:
- `post_submitbox_misc_actions`: Adds button to post sidebar
- `admin_footer`: Adds React modal container

### REST API Endpoints

#### `GET /multilingual-bridge/v1/fields/{post_id}`

Fetches all translatable ACF fields for a post.

**Parameters**:
- `post_id` (integer, required): Post ID to fetch fields from

**Response**:
```json
{
  "fields": [
    {
      "key": "field_123abc",
      "name": "my_field",
      "label": "My Field",
      "type": "text",
      "sourceValue": "Original text",
      "targetValue": "Existing translation or empty",
      "hasSource": true,
      "needsUpdate": true
    }
  ]
}
```

**Supported Field Types**:
- `text`
- `textarea`
- `wysiwyg`
- `lexical-editor`

**Permissions**: Requires `edit_posts` capability

### JavaScript Components

#### `BulkTranslationModal` Component
**File**: `resources/admin/js/components/BulkTranslationModal.js`

React component that manages the bulk translation UI.

**Props**:
- `isOpen` (boolean): Whether modal is visible
- `onClose` (function): Callback when modal closes
- `modalData` (object): Post data (postId, sourceLang, targetLang)

**State**:
- `fields`: Array of translatable fields
- `isLoadingFields`: Loading state for initial field fetch
- `isTranslating`: Loading state during translation process
- `translationProgress`: Object tracking status of each field translation
- `errorMessage`: Error message to display (if any)

**Key Functions**:
- `loadFields()`: Fetches all translatable fields from REST API
- `translateAllFields()`: Iterates through fields and translates each one
- `applyTranslations()`: Inserts translated values into ACF fields

#### `bulk-translation.js` Entry Point
**File**: `resources/admin/js/bulk-translation.js`

Initializes the bulk translation feature on post edit screens.

**Responsibilities**:
- Renders `BulkTranslationModal` React component
- Attaches click handler to "Translate All Fields" button
- Manages modal open/close state via custom events

### Utility Functions

#### `fetchFields(postId)`
**File**: `resources/admin/js/utils/api.js`

Fetches all translatable fields for a post from the REST API.

**Parameters**:
- `postId` (integer): Post ID

**Returns**: Promise resolving to `{ fields: [...] }`

#### `updateFieldValue(fieldName, value, fieldType)`
**File**: `resources/admin/js/utils/fields.js`

Updates an ACF field value in the DOM based on field type.

**Parameters**:
- `fieldName` (string): Field name
- `value` (string): Value to set
- `fieldType` (string): Field type (text, textarea, wysiwyg, lexical-editor)

**Special Handling**:
- **WYSIWYG**: Updates TinyMCE iframe content
- **Lexical Editor**: Updates hidden input field
- **Text/Textarea**: Updates input/textarea element directly

## Event Flow

1. User clicks "Translate All Fields" button
2. Custom event `multilingual-bridge:open-bulk-translation-modal` fired
3. React app opens modal with post data
4. Modal fetches all translatable fields via REST API
5. Fields displayed in list with labels
6. User clicks "Translate All"
7. For each field:
   - Status set to "translating"
   - DeepL API called via `/translate` endpoint
   - Status updated to "completed" or "error"
   - Field object updated with translation
8. User clicks "Apply Translations"
9. For each successfully translated field:
   - `updateFieldValue()` called to update DOM
   - ACF change events triggered
10. Modal closes

## Workflow Example

### Before Translation
```
┌─────────────────────────┐
│ Post Edit Screen        │
│                         │
│ Title: My Blog Post (FR)│
│                         │
│ ACF Fields:             │
│  Heading: [empty]       │
│  Description: [empty]   │
│  Summary: [empty]       │
│                         │
│ [Translate All Fields]  │ ← Button appears here
│ [Update Post]           │
└─────────────────────────┘
```

### After Clicking Button
```
┌─────────────────────────────────────┐
│ Translate All Fields                │
│                                     │
│ Found 3 translatable field(s)       │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ Heading              ✓          │ │ ← Completed
│ │ Description          [spinner]  │ │ ← Translating
│ │ Summary              [no icon]  │ │ ← Pending
│ └─────────────────────────────────┘ │
│                                     │
│ [Translate All] [Apply Translations]│
└─────────────────────────────────────┘
```

### After Translation Complete
```
┌─────────────────────────────────────┐
│ Translate All Fields                │
│                                     │
│ Found 3 translatable field(s)       │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ Heading              ✓          │ │
│ │ Description          ✓          │ │
│ │ Summary              ✓          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Translate All] [Apply Translations]│ ← Click to insert
└─────────────────────────────────────┘
```

## Customization

### Filter: Supported Field Types

You can customize which ACF field types are supported for bulk translation:

```php
add_filter( 'multilingual_bridge_acf_supported_types', function( $types ) {
    $types[] = 'custom_field_type';
    return $types;
} );
```

Default supported types: `text`, `textarea`, `wysiwyg`, `lexical-editor`

### CSS Customization

The modal can be styled via CSS classes:

- `.multilingual-bridge-bulk-translation-modal`: Modal container
- `.multilingual-bridge-bulk-modal-content`: Modal content wrapper
- `.multilingual-bridge-fields-list`: Fields list container
- `.multilingual-bridge-field-row`: Individual field row
- `.multilingual-bridge-field-label`: Field label
- `.multilingual-bridge-field-status`: Status indicator
- `.multilingual-bridge-bulk-translate-button`: "Translate All" button
- `.multilingual-bridge-apply-button`: "Apply Translations" button

## Troubleshooting

### Button Not Appearing

**Causes**:
- Editing the original language post (button only appears on translations)
- WPML not properly configured
- Post doesn't have a default language version

**Solution**:
1. Verify WPML is active and configured
2. Ensure you're editing a translation post
3. Check that the default language post exists

### Modal Shows No Fields

**Causes**:
- No ACF fields on the post
- All fields are unsupported types
- ACF plugin not active

**Solution**:
1. Add ACF fields to the post
2. Ensure fields are supported types (text, textarea, wysiwyg, lexical-editor)
3. Verify ACF plugin is active

### Translations Not Applying

**Causes**:
- Field DOM elements not found
- ACF JavaScript not initialized
- Field names changed after modal opened

**Solution**:
1. Check browser console for JavaScript errors
2. Ensure ACF is fully loaded before opening modal
3. Close and reopen modal if field structure changed

### Some Fields Show Error Icon

**Causes**:
- DeepL API rate limit reached
- Network connection issue
- Invalid source text (e.g., only HTML tags)

**Solution**:
1. Wait a moment and try again (rate limiting)
2. Check network connection
3. Manually translate problematic fields using single-field translation

## Performance Considerations

### API Rate Limiting

DeepL API has rate limits. The bulk translation feature:
- Translates fields sequentially (not in parallel) to avoid overwhelming the API
- Shows progress for each field individually
- Continues processing even if one field fails

### Large Field Counts

For posts with many fields (>20):
- Consider translating in batches rather than all at once
- Monitor DeepL API usage
- Use manual translation for fields that don't need translation

### Field Size Limits

- Maximum field size: 50,000 characters per field
- Large WYSIWYG fields may take longer to translate
- Progress indicator shows which field is currently being processed

## Security

### Permissions

- Only users with `edit_posts` capability can use bulk translation
- REST API endpoints check permissions before processing
- Nonce verification handled by WordPress REST API

### Input Sanitization

- All field values sanitized before saving to database
- ACF handles field-specific sanitization
- HTML content preserved for WYSIWYG fields

### XSS Protection

- React components escape output by default
- Field labels and values properly escaped
- Translation API responses sanitized before display

## Development Notes

### Adding New Field Type Support

1. Add field type to supported types filter
2. Implement field value update logic in `updateFieldValue()`
3. Test translation and insertion for the new field type

Example:
```javascript
// In resources/admin/js/utils/fields.js
export function updateFieldValue(fieldName, value, fieldType) {
    if (fieldType === 'my_custom_type') {
        // Custom update logic here
    }
    // ... existing logic
}
```

### Testing Bulk Translation

1. Create a post with multiple ACF fields in the default language
2. Create a translation of the post
3. Open the translation in the editor
4. Click "Translate All Fields" button
5. Verify all fields are loaded
6. Click "Translate All"
7. Verify progress indicators update
8. Click "Apply Translations"
9. Verify field values are inserted correctly
10. Save post and verify translations persist

## Future Enhancements

Potential improvements for future versions:

- **Selective Translation**: Checkboxes to select which fields to translate
- **Translation Memory**: Cache recent translations to avoid re-translating
- **Parallel Processing**: Translate multiple fields simultaneously (with rate limiting)
- **Custom Glossary**: Use custom translation glossaries for consistency
- **Translation Quality**: Show confidence scores for translations
- **Undo/Redo**: Allow reverting translations before applying

## References

- Single Field Translation: `docs/Admin/acf-translation.md`
- WPML Post Helper: `docs/Helpers/wpml-post-helper.md`
- REST API Documentation: `docs/REST_API/language-fields-and-links.md`
