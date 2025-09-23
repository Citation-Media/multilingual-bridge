# ACF Field Translation

The ACF Translation feature allows you to translate ACF field values directly within the WordPress admin using DeepL's translation API.

## Overview

When editing a post in a non-default language, supported ACF fields (text, textarea, wysiwyg) will display a "Translate" button next to their labels. Clicking this button opens a modal with a two-column interface:

- **Left Column**: Displays the original field value from the default language version of the post
- **Right Column**: Shows the translated text using DeepL API

## Setup

### 1. DeepL Free API Configuration

Add your DeepL Free API key to `wp-config.php`:

```php
define('DEEPL_API_KEY', 'your-deepl-api-key-here');
```

### 2. Requirements

- ACF plugin must be installed and activated
- WPML plugin must be installed and activated
- Post must have a translation (not be in the default language)

## Supported Field Types

Currently supports:
- `text` - Single line text fields
- `textarea` - Multi-line text areas
- `wysiwyg` - Rich text editors

## Usage

1. Edit a post that has been translated into another language
2. Locate ACF fields with supported types
3. Click the "Translate" button next to the field label
4. Review the original text (left) and translation (right)
5. Click "Translate" to generate translation using DeepL
6. Edit the translation if needed
7. Click "Save Translation" to update the field value

## API Endpoints

### Get Meta Value

Retrieves the field value from the default language version of the post.

```
GET /wp-json/multilingual-bridge/v1/meta/{post_id}/{field_key}
```

**Parameters:**
- `post_id` (int): The post ID
- `field_key` (string): The ACF field key/name

**Response:**
```json
{
  "value": "Original field value"
}
```

### Translate Text

Translates text using DeepL API.

```
POST /wp-json/multilingual-bridge/v1/translate
```

**Parameters:**
- `text` (string): Text to translate
- `target_lang` (string): Target language code (e.g., "DE", "FR")
- `source_lang` (string, optional): Source language code

**Response:**
```json
{
  "translation": "Translated text"
}
```

## Technical Implementation

### Classes

- `Multilingual_Bridge\Admin\ACF_Translation`: Main class handling ACF integration
- `Multilingual_Bridge\DeepL\DeepL_Translator`: Handles DeepL API communication
- `Multilingual_Bridge\REST\WPML_REST_Translation`: REST API endpoints

### React Components

- `App.jsx`: Main React application entry point
- `Modal.jsx`: Generic modal component
- `TranslationModal.jsx`: Translation-specific modal with two-column layout

### Hooks

- `acf/render_field`: Adds translation buttons to ACF fields
- `acf/input/admin_enqueue_scripts`: Enqueues React scripts
- `acf/input/admin_footer`: Adds React root container

## Future Enhancements

- Support for additional field types (select, checkbox, etc.)
- General meta field support (non-ACF)
- Gutenberg sidebar integration
- Bulk translation of multiple fields
- Automatic translation on save