# Multilingual Bridge

A WordPress plugin that bridges the gap between WPML and the WordPress REST API, providing comprehensive multilingual support for modern WordPress applications.

## Overview

Multilingual Bridge enhances WPML's functionality by adding full REST API support for multilingual content. It solves common challenges developers face when building headless WordPress applications or integrating with external systems that need language-aware content.

## Problems This Plugin Solves

- **No Native WPML REST API Support**: WPML doesn't provide built-in REST API integration
- **Complex Language Queries**: Filtering posts by language in REST API requires custom implementation
- **Missing Translation Links**: No easy way to discover content translations via REST API
- **Cumbersome WPML API**: Simplifies complex WPML operations with helper functions
- **Term Relationship Bugs**: Works around WPML bugs when deleting term relationships across languages

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

### 🚀 Modern Development
- Full support for headless WordPress architectures
- Compatible with JavaScript frameworks (React, Vue, Next.js)
- Type-safe with PHPStan level 5
- WordPress coding standards compliant

## Requirements

- WordPress 5.0 or higher
- WPML plugin installed and activated
- PHP 8.0 or higher

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
```

## Documentation

Comprehensive documentation is available in the `/docs` directory:

### REST API Features
- [Language Fields and Translation Links](docs/REST_API/language-fields-and-links.md) - Automatic language information in REST responses with full embed support
- [WPML Language Filtering](docs/REST_API/wpml-language-filtering.md) - Information about WPML's native language filtering

### Helper Functions
- [WPML Post Helper](docs/Helpers/wpml-post-helper.md) - Simplified WPML operations for developers

## Use Cases

- **Headless WordPress**: Build multilingual JAMstack sites with Next.js, Gatsby, or Nuxt
- **Mobile Apps**: Develop language-aware mobile applications
- **External Integrations**: Connect WordPress content to external systems
- **Content Migration**: Export/import multilingual content via REST API
- **Custom Admin Interfaces**: Build custom editing experiences with language support

## Development

### Project Structure
```
multilang-bridge/
├── src/
│   ├── Helpers/          # WPML helper functions
│   └── REST/            # REST API functionality
├── docs/                # Documentation
└── languages/           # Translation files
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

Created by Justin Vogt

---

This plugin was created using the [wordpress-plugin-boilerplate](https://github.com/JUVOJustin/wordpress-plugin-boilerplate).