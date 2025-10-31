# Multilingual Bridge

[![PHPStan](https://img.shields.io/badge/PHPStan-Level%206-blue)](https://phpstan.org/)
[![PHPCS](https://img.shields.io/badge/PHPCS-WordPress-green)](https://github.com/WordPress/WordPress-Coding-Standards)
[![Test/Analyse](https://github.com/Citation-Media/multilingual-bridge/actions/workflows/test-analyse.yml/badge.svg)](https://github.com/Citation-Media/multilingual-bridge/actions/workflows/test-analyse.yml)

A WordPress plugin that bridges the gap between WPML and the WordPress REST API, providing comprehensive multilingual support for modern WordPress applications.

## Overview

Multilingual Bridge enhances WPML's functionality by adding full REST API support for multilingual content. It solves common challenges developers face when building headless WordPress applications or integrating with external systems that need language-aware content.

## Problems This Plugin Solves

- **No Native WPML REST API Support**: WPML doesn't provide built-in REST API integration
- **Complex Language Queries**: Filtering posts by language in REST API requires custom implementation
- **Missing Translation Links**: No easy way to discover content translations via REST API
- **Cumbersome WPML API**: Simplifies complex WPML operations with helper functions
- **Term Relationship Bugs**: Works around WPML bugs when deleting term relationships across languages
- **ACF Field Sync Issues**: WPML doesn't sync empty ACF fields to translations, causing stale data

## Key Features

### 🌐 REST API Language Support
- Automatically adds `language_code` field to all REST API responses
- Includes translation links in the `_links` property with full embed support
- Support for all post types with REST API enabled

### 🛠️ Developer-Friendly Helpers
- Simplified functions for common WPML operations
- Get post language with a single function call
- Retrieve all translations of a post easily
- Check translation completeness
- Safely delete term relationships across languages
- Automatic synchronization of empty ACF fields across translations

### 🚀 Modern Development
- Full support for headless WordPress architectures
- Compatible with JavaScript frameworks (React, Vue, Next.js)
- Type-safe with PHPStan level 5
- WordPress coding standards compliant

### 🔧 Admin Tools
- Language Debug tool for managing posts in unconfigured languages
- Bulk operations for cleaning up orphaned content
- Safe language reassignment for posts

## Requirements

- WordPress 5.0 or higher
- WPML plugin installed and activated
- PHP 8.1 or higher

## Installation

1. Download the plugin from the releases page
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure WPML is installed and activated

## Quick Start

### REST API Examples

#### Response includes language information and translation links:
```json
{
  "id": 123,
  "title": "Hello World",
  "language_code": "en",
  "_links": {
    "translations": [
      {
        "href": "https://site.com/wp-json/wp/v2/posts/456",
        "wpml_language": "de",
        "title": "Translation: German",
        "embeddable": true
      }
    ]
  }
}
```

### PHP Helper Examples

```php
use Multilingual_Bridge\Helpers\WPML_Post_Helper;

// Get post language
$language = WPML_Post_Helper::get_language($post_id);

// Get all translations
$translations = WPML_Post_Helper::get_language_versions($post_id);

// Check if post has all translations
if (WPML_Post_Helper::has_all_translations($post_id)) {
    echo "Fully translated!";
}

// Safely delete term relationships
WPML_Post_Helper::safe_delete_term_relationships($post_id, 'category');

// Check if post is in unconfigured language
if (WPML_Post_Helper::is_post_in_unconfigured_language($post_id)) {
    echo "Post is in a deactivated language!";
}
```

### ACF Integration

The plugin includes automatic synchronization of empty ACF fields across translations:

**Problem it solves:**
WPML does not properly sync empty fields from the original language to translations when fields are set to "translate" mode. This causes translations to keep old values even after the original field is emptied.

**How it works:**
When you empty an ACF field in the original language post, the plugin automatically empties the same field in all translations. This works for all ACF field types and requires no configuration.

**Example:**
```php
// Empty a field in the original English post
update_field('featured_quote', '', $english_post_id);

// Plugin automatically clears the same field in German, French, and all other translations
// No manual intervention needed!
```

**Note:** This only affects fields set to "translate" mode. Fields in "copy" mode are correctly handled by WPML.

## Documentation

Comprehensive documentation is available in the `/docs` directory:

### REST API Features
- [Language Fields and Translation Links](docs/REST_API/language-fields-and-links.md) - Automatic language information in REST responses with full embed support
- [WPML Language Filtering](docs/REST_API/wpml-language-filtering.md) - Information about WPML's native language filtering

### Helper Functions
- [WPML Post Helper](docs/Helpers/wpml-post-helper.md) - Simplified WPML operations for developers

### Admin Tools
- [Language Debug](docs/Admin/language-debug.md) - Manage posts in unconfigured languages

## Use Cases

- **Headless WordPress**: Build multilingual JAMstack sites with Next.js, Gatsby, or Nuxt
- **Mobile Apps**: Develop language-aware mobile applications
- **External Integrations**: Connect WordPress content to external systems
- **Content Migration**: Export/import multilingual content via REST API
- **Custom Admin Interfaces**: Build custom editing experiences with language support

## Development

### Project Structure
```
multilingual-bridge/
├── src/
│   ├── Admin/           # Admin interface components
│   ├── Helpers/         # WPML helper functions
│   └── REST/           # REST API functionality
├── docs/               # Documentation
└── languages/          # Translation files
```

### Code Quality

The plugin maintains high code quality standards:
- PHPStan level 5 static analysis
- WordPress Coding Standards (WPCS)
- Comprehensive inline documentation

To run quality checks:
```bash
composer phpcs    # Check coding standards
composer phpstan  # Run static analysis
composer phpcbf   # Auto-fix coding standards
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under GPL v2 or later.

## Credits

Created by Justin Vogt and [Citation Media](https://citation.media)

---

This plugin was created using the [wordpress-plugin-boilerplate](https://github.com/JUVOJustin/wordpress-plugin-boilerplate).