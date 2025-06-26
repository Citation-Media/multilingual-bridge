# WPML Language Filtering in REST API

WPML natively provides language filtering support for WordPress REST API endpoints through the `wpml_language` query parameter.

## Using WPML's Native Language Parameter

WPML automatically adds support for filtering posts by language using the `wpml_language` parameter:

### Query Examples

```bash
# Get posts in English
GET /wp-json/wp/v2/posts?wpml_language=en

# Get posts in German
GET /wp-json/wp/v2/posts?wpml_language=de

# Get posts in all languages
GET /wp-json/wp/v2/posts?wpml_language=all
```

### Creating/Updating Posts with Language

You can also specify the language when creating or updating posts:

```bash
# Create a post in German
POST /wp-json/wp/v2/posts
{
  "title": "Mein Titel",
  "content": "Inhalt auf Deutsch",
  "wpml_language": "de"
}

# Update a post's language
PUT /wp-json/wp/v2/posts/123
{
  "wpml_language": "fr"
}
```

## Available Language Codes

The available language codes depend on your WPML configuration. Common codes include:
- `en` - English
- `de` - German
- `fr` - French
- `es` - Spanish
- `it` - Italian
- `all` - All languages (for queries only)

## Helper Method

The Multilingual Bridge plugin provides a helper method to get all active language codes:

```php
use Multilingual_Bridge\Helpers\WPML_Post_Helper;

// Get array of active language codes
$language_codes = WPML_Post_Helper::get_active_language_codes();
// Returns: ['en', 'de', 'fr', ...]
```

## Note

The Multilingual Bridge plugin previously included custom language query parameter support via the `lang` parameter. This has been removed in favor of WPML's native `wpml_language` parameter, which provides the same functionality with better integration.