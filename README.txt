=== Multilingual Bridge ===
Contributors: juvodesign, citationmedia
Tags: wpml, rest-api, multilingual, translation, headless
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Bridges the gap between WPML and WordPress REST API, adding comprehensive multilingual support for modern WordPress applications.

== Description ==

Multilingual Bridge enhances WPML's functionality by adding full REST API support for multilingual content. Perfect for headless WordPress, mobile apps, and external integrations that need language-aware content.

**Key Problems Solved:**

* WPML has no native REST API support
* No easy way to filter posts by language in REST API
* Complex WPML API calls simplified
* Missing translation information in REST responses
* WPML bugs when deleting term relationships

**Main Features:**

* **Automatic Language Fields** - Adds `language_code` to all REST API responses
* **Translation Links** - Discover all translations via `_links.translation`
* **Language Filtering** - Query posts by language with `?lang=` parameter
* **Developer Helpers** - Simplified functions for common WPML operations
* **Bug Workarounds** - Safely handle term relationships across languages
* **Language Debug Tool** - Admin tool to manage posts in unconfigured languages

**REST API Examples:**

Get German posts:
`GET /wp-json/wp/v2/posts?lang=de`

Get all posts (all languages):
`GET /wp-json/wp/v2/posts?lang=all`

**Perfect for:**

* Headless WordPress with Next.js, Gatsby, or Nuxt
* Mobile app development
* External system integrations
* Custom admin interfaces
* Multilingual content migrations

== Installation ==

1. Install and activate WPML (required)
2. Upload the plugin files to `/wp-content/plugins/multilang-bridge`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Start using the enhanced REST API endpoints

== Frequently Asked Questions ==

= Does this plugin require WPML? =

Yes, WPML must be installed and activated. This plugin extends WPML's functionality to work with the REST API.

= Which post types are supported? =

All post types that have REST API support enabled will automatically get language fields and filtering capabilities.

= Can I filter custom post types by language? =

Yes! Any custom post type with `show_in_rest => true` will support language filtering.

= How do I get all translations of a post? =

The plugin automatically adds translation links to the `_links` property of each REST response. You can also use the helper functions in PHP.

= Does it work with custom taxonomies? =

Yes, the helper functions support all taxonomies, including custom ones.

= Is it compatible with Gutenberg? =

Yes, the plugin is fully compatible with the block editor and enhances its REST API endpoints.

= How do I clean up posts in deactivated languages? =

Use the Language Debug tool under Tools → Language Debug in your WordPress admin. It can find and manage posts in languages that are no longer active in WPML.

== Screenshots ==

1. REST API response showing language_code field
2. Translation links in _links property
3. Language filtering in action

== Changelog ==

= 1.1.2 =
* Enhancement: Improved plugin initialization using WPML's wpml_loaded hook
* Enhancement: Added badges for PHPStan, PHPCS, and tests status to README
* Fix: Updated initialization timing to ensure WPML is fully loaded before plugin runs
* Fix: Resolved all PHPCS coding standards errors
* Maintenance: Code style improvements for better consistency

= 1.1.1 =
* Performance: Optimized Language Debug tool queries for large sites
* Performance: Implemented batch processing to prevent memory exhaustion
* Performance: Now fetches only post IDs instead of full post objects
* Refactor: Moved language assignment logic to WPML_Post_Helper::set_language()
* Refactor: Removed boilerplate setup command from production code
* Enhancement: Improved code organization and reusability
* Fix: Resolved coding standards issues
* Fix: Updated deploy workflow to ignore specific WPCS warnings

= 1.1.0 =
* Added Language Debug admin tool
* New helper method: is_post_in_unconfigured_language()
* Bulk operations for managing orphaned content
* Language reassignment functionality
* Admin interface improvements

= 1.0.0 =
* Initial release
* Language fields in REST API responses
* Translation links in _links property
* Language filtering with ?lang= parameter
* WPML helper functions for developers
* Safe term relationship handling

== Upgrade Notice ==

= 1.1.2 =
Improved WPML compatibility with better initialization timing. Includes code quality improvements.

= 1.1.1 =
Major performance improvements for Language Debug tool. Recommended update for sites with large content databases.

= 1.1.0 =
New Language Debug tool for managing posts in deactivated languages. Includes helper improvements and admin tools.

= 1.0.0 =
Initial release of Multilingual Bridge. Adds comprehensive WPML REST API support.

== Developer Documentation ==

**Helper Functions:**

`WPML_Post_Helper::get_language($post_id)` - Get post language
`WPML_Post_Helper::get_language_versions($post_id)` - Get all translations
`WPML_Post_Helper::has_all_translations($post_id)` - Check translation completeness
`WPML_Post_Helper::safe_delete_term_relationships($post_id, $taxonomy)` - Safely delete terms
`WPML_Post_Helper::is_post_in_unconfigured_language($post_id)` - Check if post is in deactivated language
`WPML_Post_Helper::set_language($post_id, $language_code)` - Set or update post language assignment

**REST API Parameters:**

`?lang=en` - Get posts in English
`?lang=all` - Get posts in all languages
`?_fields=id,title,language_code` - Optimize response size

Full documentation available on [GitHub](https://github.com/JUVOJustin/multilang-bridge).