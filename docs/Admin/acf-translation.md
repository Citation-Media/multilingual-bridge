# ACF Field Translation

The ACF Translation feature allows you to translate ACF field values directly within the WordPress admin using DeepL's translation API.

## Overview

When editing a post in a non-default language, supported ACF fields (text, textarea, wysiwyg, lexical-editor) will display two action icons next to their labels:

- **Translation Icon** (dashicons-translation): Opens a modal for translating the field value
- **Paste Icon** (dashicons-editor-paste-text): Copies original text directly to the field without translation

The translation modal provides a two-column interface:

- **Left Column**: Displays the original field value from the default language version (editable for fixing typos)
- **Right Column**: Shows the translated text (can be auto-filled via DeepL or manually entered)

## Setup

### 1. DeepL API Configuration

Configure your DeepL API key through the WordPress admin:

1. Go to **Settings > Multilingual Bridge DeepL**
2. Enter your DeepL API key
3. Choose between Free or Premium API
4. Save the settings

### 2. Requirements

- ACF plugin must be installed and activated
- WPML plugin must be installed and activated
- Post must have a translation (not be in the default language)

## Supported Field Types

Currently supports:
- `text` - Single line text fields
- `textarea` - Multi-line text areas
- `wysiwyg` - Rich text editors (TinyMCE/Block Editor)
- `lexical-editor` - Lexical editor fields (modern rich text editor)

Additional field types can be added using the `multilingual_bridge_acf_supported_types` filter.

## Usage

### Translation Modal Workflow

1. Edit a post that has been translated into another language
2. Locate ACF fields with supported types
3. Click the **translation icon** (globe) next to the field label
4. Modal opens and automatically loads the original text from the default language post
5. Review the original text (editable if needed)
6. Click **"Translate"** button to automatically translate using DeepL API
7. Review and edit the translation if needed (translation field is always editable)
8. Click **"Use Translation"** to insert the translated text into the ACF field
9. Modal closes and the field is populated

### Quick Copy Workflow

For cases where text should remain unchanged (e.g., proper nouns, brand names):

1. Click the **paste icon** next to the field label
2. Original text is copied directly to the field without opening the modal

## REST API Endpoints

### Get Meta Value

Retrieves the field value from the default language version of the post.

**Endpoint:**
```
GET /wp-json/multilingual-bridge/v1/meta/{post_id}/{field_key}
```

**URL Parameters:**
- `post_id` (integer, required): The post ID from which to retrieve meta value
  - Minimum: 1
  - Automatically resolves to default language post ID via `WPML_Post_Helper::get_default_language_post_id()`
- `field_key` (string, required): The ACF field key/name
  - Min length: 1
  - Max length: 255
  - Pattern: `^[a-zA-Z0-9_-]+$`

**Permission:**
- Requires `edit_posts` capability

**Response:**
```json
{
  "value": "Original field value"
}
```

**Implementation Notes:**
- Uses ACF's `get_field()` function if available, falls back to `get_post_meta()`
- Returns 404 error if default language post not found
- Handles both ACF field keys and standard meta keys

**Error Responses:**
- `404`: Default language post not found
- `403`: Insufficient permissions

---

### Translate Text

Translates text using DeepL API.

**Endpoint:**
```
POST /wp-json/multilingual-bridge/v1/translate
```

**Body Parameters:**
- `text` (string, required): Text to translate
  - Min length: 1
  - Max length: 50,000 characters
- `target_lang` (string, required): Target language code (ISO 639-1)
  - Min length: 2
  - Max length: 5
  - Pattern: `^[a-zA-Z]{2}(-[a-zA-Z]{2})?$`
  - Examples: "en", "de", "fr", "en-US"
- `source_lang` (string, optional): Source language code (ISO 639-1)
  - If not provided, DeepL will auto-detect the source language
  - Same validation rules as `target_lang`

**Permission:**
- Requires `edit_posts` capability

**Response:**
```json
{
  "translation": "Translated text"
}
```

**Implementation Notes:**
- Uses `Translation_Manager` with provider-agnostic architecture
- Returns `WP_Error` on translation failure (API key invalid, quota exceeded, etc.)
- Validates language codes using regex patterns
- Enforces text length limits to prevent abuse

**Error Responses:**
- `400`: Invalid parameters (validation failure)
- `403`: Insufficient permissions
- `500`: Translation API error (see error message for details)

## Technical Implementation

### Architecture Overview

The translation feature uses a hybrid React + vanilla JavaScript architecture:

1. **PHP Backend**: Marks translatable fields with data attributes and serves REST API endpoints
2. **React Modal**: Manages translation UI state, API calls, and user interactions
3. **Vanilla JS Bridge**: Handles DOM manipulation and event coordination between React and ACF
4. **Event Bus**: Custom events decouple React components from ACF field updates

### PHP Classes

- **`Multilingual_Bridge\Integrations\ACF\ACF_Translation`**: Main class handling ACF integration
  - Hooks into ACF field wrapper to add data attributes and CSS classes
  - Only activates for non-default language posts
  - Uses Field_Registry to determine translatable field types

- **`Multilingual_Bridge\Translation\Translation_Manager`**: Manages translation providers
  - Provider-agnostic singleton for translation operations
  - Supports multiple translation providers (DeepL, Google, OpenAI, etc.)
  - Returns `WP_Error` on failure

- **`Multilingual_Bridge\REST\WPML_REST_Translation`**: REST API endpoints (extends `WP_REST_Controller`)
  - Implements proper REST validation and sanitization
  - See REST API Endpoints section below

### JavaScript Files

#### Entry Point: `translation.js`

Main bootstrapping file that:
- Initializes React modal app using `@wordpress/element` (React 18)
- Scans DOM for translatable fields (`.multilingual-translatable-field` class)
- Creates translation action buttons for each field
- Sets up event listeners for save operations
- Re-initializes on ACF dynamic field additions (repeater rows, flexible content)

Key functions:
- `TranslationApp()`: React component managing modal state
- `initializeACFTranslationButtons()`: Injects action buttons into ACF field labels

#### React Components

**`components/TranslationModal.js`**

Main modal component built with `@wordpress/components`:
- Uses `Modal`, `TextareaControl`, `Notice`, and `Button` components
- Implements two-column layout for original and translated text
- Both text fields are editable (allows fixing typos in original, manual translation edits)
- Manages modal lifecycle and prevents duplicate API calls via `useRef`
- Dispatches custom event `multilingual-bridge:save-translation` to update ACF fields

Props:
```javascript
{
  isOpen: boolean,           // Modal visibility state
  onClose: Function,         // Callback when modal closes
  modalData: {               // Field information (null when closed)
    fieldKey: string,
    fieldLabel: string,
    postId: number,
    sourceLang: string,
    targetLang: string,
    fieldType: string
  }
}
```

#### Custom Hooks

**`hooks/useTranslation.js`**

Encapsulates all translation state and async operations:
- Manages `originalValue`, `translatedValue`, `isLoading`, `errorMessage` states
- `loadOriginal()`: Fetches original text from default language post
- `translate()`: Calls DeepL API to translate text
- `reset()`: Clears all state when modal closes
- Uses `useCallback` to prevent unnecessary re-renders
- Memoizes `modalData` to optimize performance

Return value:
```javascript
{
  originalValue: string,
  setOriginalValue: Function,
  translatedValue: string,
  setTranslatedValue: Function,
  isLoading: boolean,
  errorMessage: string,
  loadOriginal: Function,
  translate: Function,
  reset: Function
}
```

#### Utilities

**`utils/api.js`**

API interaction utilities:
- `loadOriginalValue(postId, fieldKey)`: Fetches meta value from default language post
- `translateText(text, sourceLang, targetLang)`: Calls DeepL translation endpoint
- `updateACFField(fieldKey, value)`: Updates ACF field in DOM and triggers change events
- `cleanFieldKey(fieldKey)`: Strips `acf[...]` wrapper syntax from field keys
- Uses `@wordpress/api-fetch` for REST API communication
- Triggers both native `change` events and ACF's `acf.trigger()` for proper state sync

**`utils/fields.js`**

DOM manipulation and button creation:
- `createTranslationButton(fieldData, onTranslate, onCopy)`: Creates action icon group
  - Translation icon: Opens modal
  - Paste icon: Copies original text directly
- `copyOriginalToField(fieldKey, postId)`: Quick copy original text without modal
- Returns DOM elements ready for injection into ACF field labels

### Event Flow

1. **Page Load**:
   - `translation.js` bootstraps React app
   - `initializeACFTranslationButtons()` scans for `.multilingual-translatable-field` elements
   - Action buttons injected into field labels

2. **User Clicks Translation Icon**:
   - `fields.js` dispatches `multilingual-bridge:open-translation-modal` custom event
   - `TranslationApp` listener opens modal with field data
   - `TranslationModal` calls `loadOriginal()` via `useTranslation` hook
   - API request fetches original text from default language post

3. **User Clicks "Translate" Button**:
   - `translate()` function calls DeepL API via `api.js`
   - Translation populates the translated value field
   - User can edit translation manually if needed

4. **User Clicks "Use Translation" Button**:
   - `saveTranslation()` dispatches `multilingual-bridge:save-translation` event
   - Event listener in `translation.js` calls `updateACFField()`
   - DOM field value updated and change events triggered
   - ACF's internal state synced via `acf.trigger()`
   - Modal closes

5. **User Clicks Paste Icon** (Quick Copy):
   - `copyOriginalToField()` directly fetches and updates field
   - No modal interaction required

### WordPress Hooks

**PHP Hooks** (in `ACF_Translation` class):

- `acf/field_wrapper_attributes`: Adds data attributes to translatable field wrappers
  - `data-field-key`: ACF field name
  - `data-field-label`: Field label for display
  - `data-post-id`: Current post ID
  - `data-source-lang`: Default language code
  - `data-target-lang`: Current language code
  - `data-field-type`: ACF field type
  - `class`: Adds `multilingual-translatable-field`

- `acf/input/admin_footer`: Adds React modal container div to page

**JavaScript Hooks** (ACF actions):

- `acf.addAction('ready')`: Re-initializes buttons when ACF loads
- `acf.addAction('append')`: Re-initializes buttons when ACF adds dynamic fields (repeaters, flexible content)

### Custom Events

- `multilingual-bridge:open-translation-modal`: Dispatched by translation icon, opens modal
  - `detail`: `{ fieldKey, fieldLabel, postId, sourceLang, targetLang, fieldType }`

- `multilingual-bridge:save-translation`: Dispatched by "Use Translation" button, updates field
  - `detail`: `{ fieldKey, value }`

## Customization

### Supported Field Types Filter

Extend the list of translatable field types:

```php
add_filter( 'multilingual_bridge_acf_supported_types', function( $types ) {
    $types[] = 'email';
    $types[] = 'url';
    return $types;
} );
```

Default supported types: `['text', 'textarea', 'wysiwyg', 'lexical-editor']`

### Custom Styling

The translation UI uses standard WordPress components and can be styled via these classes:

**Modal:**
- `.multilingual-bridge-translation-modal`: Modal wrapper
- `.multilingual-bridge-modal-content`: Modal content container
- `.multilingual-bridge-modal-fields`: Two-column fields container
- `.multilingual-bridge-modal-field`: Individual field column
- `.multilingual-bridge-modal-actions`: Button container
- `.multilingual-bridge-modal-error`: Error notice

**Field Labels:**
- `.multilingual-bridge-translate-btn`: Action button group container
- `.multilingual-bridge-original-field`: Original text textarea
- `.multilingual-bridge-translation-field`: Translation textarea

**Field Buttons:**
- `.multilingual-bridge-translate-button`: Translate button
- `.multilingual-bridge-save-button`: Use Translation button

Example custom CSS:

```css
/* Style translation action icons */
.multilingual-bridge-translate-btn .dashicons {
    color: #2271b1;
    margin-left: 8px;
}

.multilingual-bridge-translate-btn .dashicons:hover {
    color: #135e96;
}

/* Customize modal appearance */
.multilingual-bridge-translation-modal .components-modal__content {
    min-width: 800px;
}
```

## Development Notes

### React Dependencies

The modal uses WordPress's built-in React 18 implementation via `@wordpress/element`:
- No external React installation required
- Uses `createElement()` instead of JSX for better compatibility
- All WordPress components from `@wordpress/components` available

### ACF Compatibility

Works with:
- ACF Pro and Free
- Standard fields, field groups, and repeaters
- Flexible content and clone fields
- Dynamically added fields (via ACF's `append` action)

### WPML Integration

Relies on:
- `WPML_Post_Helper::get_default_language_post_id()`: Resolves original post
- `WPML_Post_Helper::is_original_post()`: Checks if post is in default language
- `WPML_Post_Helper::get_language()`: Gets current post language
- `WPML_Language_Helper::get_default_language()`: Gets site default language

## Troubleshooting

### Translation buttons not appearing

1. **Check post language**: Buttons only appear on non-default language posts
2. **Verify field type**: Only `text`, `textarea`, `wysiwyg`, and `lexical-editor` are supported by default
3. **Browser console**: Check for JavaScript errors
4. **ACF availability**: Ensure ACF is loaded before translation script

### Modal not opening

1. **React container**: Verify `#multilingual-bridge-react-modal` div exists in DOM
2. **JavaScript errors**: Check browser console for errors
3. **Event listeners**: Ensure `DOMContentLoaded` event fired

### Translation not saving to field

1. **Field selector**: Verify field `name` attribute matches `data-field-key`
2. **ACF validation**: Check if ACF validation is blocking the update
3. **Console errors**: Look for errors in `updateACFField()` function

### DeepL API errors

1. **API key**: Verify DeepL API key is configured in Settings
2. **Quota**: Check DeepL account for character quota
3. **Language codes**: Ensure source/target languages are supported by DeepL
4. **Network**: Check for network connectivity issues

## Performance Considerations

- **Lazy Loading**: Modal only renders when opened (React conditional rendering)
- **Memoization**: `modalData` and callbacks memoized to prevent re-renders
- **API Caching**: Consider implementing client-side caching for translations
- **Event Delegation**: Could improve performance for pages with many fields

## Security

- **Permission Check**: All REST endpoints require `edit_posts` capability
- **Input Validation**: REST API validates all parameters (type, length, pattern)
- **Sanitization**: Field values sanitized via WordPress REST API framework
- **CSRF Protection**: WordPress nonce validation handled by `@wordpress/api-fetch`
- **XSS Prevention**: React automatically escapes all text content

## Future Enhancements

- Support for additional field types (select, checkbox, relationship, etc.)
- General meta field support (non-ACF custom fields)
- Gutenberg block attributes translation
- Bulk translation of multiple fields at once
- Translation memory/cache to reduce API calls
- Automatic translation on post save (with opt-in setting)
- Support for alternative translation services (Google Translate, Azure)
- Translation history and rollback functionality
- Character count and DeepL quota tracking