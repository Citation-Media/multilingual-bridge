# Language Fields and Translation Links

This document describes the language field and translation link features available in the WordPress REST API through the Multilingual Bridge plugin.

## Overview

The Multilingual Bridge plugin extends the WordPress REST API with WPML integration, providing language information and translation links for all post types and taxonomies that have REST API support enabled.

## Features

### 1. Language Code Field

Every post type and taxonomy with REST API support automatically gets a `language_code` field that indicates the language of the content.

#### Example Request for Posts
```
GET /wp-json/wp/v2/posts/123
```

#### Example Response for Posts
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

#### Example Request for Terms (Categories)
```
GET /wp-json/wp/v2/categories/45
```

#### Example Response for Terms
```json
{
  "id": 45,
  "name": "Technology",
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

The plugin automatically adds translation links to the `_links` property of REST API responses for both posts and terms with full embed support. This allows you to discover all available translations and embed them in a single request.

#### Example Request for Posts
```
GET /wp-json/wp/v2/posts/123
```

#### Example Response with Translation Links for Posts
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
        "language": "de",
        "embeddable": true
      },
      {
        "href": "https://example.com/wp-json/wp/v2/posts/789",
        "language": "fr",
        "embeddable": true
      }
    ]
  }
}
```

#### Example Request for Terms (Categories)
```
GET /wp-json/wp/v2/categories/45
```

#### Example Response with Translation Links for Terms
```json
{
  "id": 45,
  "name": "Technology",
  "language_code": "en",
  "_links": {
    "self": [
      {
        "href": "https://example.com/wp-json/wp/v2/categories/45"
      }
    ],
    "translations": [
      {
        "href": "https://example.com/wp-json/wp/v2/categories/67",
        "language": "de",
        "embeddable": true
      },
      {
        "href": "https://example.com/wp-json/wp/v2/categories/89",
        "language": "fr",
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

## Supported Content Types

These features are automatically available for all post types and taxonomies that have REST API support enabled, including:

### Post Types
- Posts (`/wp/v2/posts`)
- Pages (`/wp/v2/pages`)
- Custom Post Types (if REST API is enabled)

### Taxonomies
- Categories (`/wp/v2/categories`)
- Tags (`/wp/v2/tags`)
- Custom Taxonomies (if REST API is enabled)

## Requirements

- WordPress 5.0 or higher
- WPML plugin installed and activated
- Post types must have REST API support enabled

## Performance Considerations

1. **For Posts**: Language code is fetched using WPML's `wpml_post_language_details` filter
2. **For Terms**: Language code is fetched using WPML's `wpml_element_language_details` filter
3. **Translation Links**: Generated using WPML's `wpml_element_trid` and `wpml_get_element_translations` filters
4. Both features check the `_fields` parameter to avoid unnecessary processing

## Limitations

- The features only work when WPML is active
- Translation links are only added for content that has translations
- The language code field returns an empty string if language information is not available
- For terms, the taxonomy information is automatically available through the REST API context