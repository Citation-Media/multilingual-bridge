# Language Fields and Translation Links

This document describes the language field and translation link features available in the WordPress REST API through the Multilingual Bridge plugin.

## Overview

The Multilingual Bridge plugin extends the WordPress REST API with WPML integration, providing language information and translation links for all post types that have REST API support enabled.

## Features

### 1. Language Code Field

Every post type with REST API support automatically gets a `language_code` field that indicates the language of the content.

#### Example Request
```
GET /wp-json/wp/v2/posts/123
```

#### Example Response
```json
{
  "id": 123,
  "title": {
    "rendered": "Hello World"
  },
  "language_code": "en",
  // ... other fields
}
```

#### Field Details
- **Field Name**: `language_code`
- **Type**: string
- **Read-only**: true
- **Description**: The two-letter language code (ISO 639-1) of the content
- **Examples**: `en` (English), `de` (German), `fr` (French), etc.

### 2. Translation Links in _links with Full Embed Support

The plugin automatically adds translation links to the `_links` property of REST API responses with full embed support. This allows you to discover all available translations of a post and embed them in a single request.

#### Example Request
```
GET /wp-json/wp/v2/posts/123
```

#### Example Response with Translation Links
```json
{
  "id": 123,
  "title": {
    "rendered": "Hello World"
  },
  "language_code": "en",
  "_links": {
    "self": [
      {
        "href": "https://example.com/wp-json/wp/v2/posts/123"
      }
    ],
    "translations": [
      {
        "href": "https://example.com/wp-json/wp/v2/posts/456",
        "title": "Translation: German",
        "wpml_language": "de",
        "embeddable": true
      },
      {
        "href": "https://example.com/wp-json/wp/v2/posts/789",
        "title": "Translation: French", 
        "wpml_language": "fr",
        "embeddable": true
      }
    ]
  }
}
```

#### Translation Link Properties
- **href**: The full REST API URL to fetch the translated post
- **title**: Human-readable description (e.g., "Translation: German")
- **wpml_language**: The language code of the translation
- **embeddable**: Set to `true`, allowing the translation to be embedded using `_embed` parameter

### 3. Field Filtering Support

Both features respect the `_fields` parameter for performance optimization:

```
GET /wp-json/wp/v2/posts/123?_fields=id,title,language_code
```

This will only return the requested fields, improving response time and reducing bandwidth.

## Usage Examples

### Getting All Translations of a Post

```javascript
// Fetch a post
const response = await fetch('https://example.com/wp-json/wp/v2/posts/123');
const post = await response.json();

// Check if translations exist
if (post._links && post._links.translations) {
  // Iterate through available translations
  for (const translation of post._links.translations) {
    console.log(`${translation.wpml_language}: ${translation.href}`);
    
    // Fetch the translated post
    const translatedResponse = await fetch(translation.href);
    const translatedPost = await translatedResponse.json();
    console.log(translatedPost.title.rendered);
  }
}
```

### Displaying Language Information

```javascript
// Fetch multiple posts
const response = await fetch('https://example.com/wp-json/wp/v2/posts');
const posts = await response.json();

// Display posts with their language
posts.forEach(post => {
  console.log(`${post.title.rendered} (${post.language_code})`);
});
```

### Using Embed to Fetch Translations

You can use the `_embed` parameter to fetch all translations in a single request:

```javascript
// Fetch a post with embedded translations
const response = await fetch('https://example.com/wp-json/wp/v2/posts/123?_embed');
const post = await response.json();

// Access embedded translations
if (post._embedded && post._embedded.translations) {
  post._embedded.translations.forEach(translation => {
    console.log(`${translation.wpml_language}: ${translation.title.rendered}`);
  });
}
```

This is more efficient than making separate requests for each translation.

## Supported Post Types

These features are automatically available for all post types that have REST API support enabled, including:
- Posts (`/wp/v2/posts`)
- Pages (`/wp/v2/pages`)
- Custom Post Types (if REST API is enabled)

## Requirements

- WordPress 5.0 or higher
- WPML plugin installed and activated
- Post types must have REST API support enabled

## Performance Considerations

1. The language code is fetched using WPML's `wpml_post_language_details` filter
2. Translation links are generated using WPML's `wpml_element_trid` and `wpml_get_element_translations` filters
3. Both features check the `_fields` parameter to avoid unnecessary processing

## Limitations

- The features only work when WPML is active
- Translation links are only added for posts that have translations
- The language code field returns an empty string if language information is not available