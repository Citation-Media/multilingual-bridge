# Language Debug Tool

The Language Debug tool helps administrators manage posts with language-related issues in WPML, including posts in unconfigured languages and posts with cross-language term relationships.

## Overview

The Language Debug tool provides functionality to identify and fix two main types of language issues:

### 1. Unconfigured Language Issues

When languages are removed or deactivated from WPML, posts in those languages can become orphaned. These posts exist in the database but are associated with language codes that WPML no longer recognizes.

**Problems caused:**
- Posts not appearing in queries
- Translation management issues
- Broken language switchers
- Performance degradation

### 2. Cross-Language Term Relationships

Posts can incorrectly have term relationships (categories, tags, custom taxonomies) in languages different from the post's language.

**Common causes:**
- WPML bugs during term assignment
- Manual database manipulation
- Plugin conflicts
- Import/export operations

**Problems caused:**
- Terms appearing in wrong language contexts
- Incorrect taxonomy filtering
- Translation synchronization problems
- Confusing editorial experience

## Location

The Language Debug tool can be found in the WordPress admin under:
**Tools → Language Debug**

## Features

### Unconfigured Language Operations

1. **Preflight Check** - See how many posts are in unconfigured languages
2. **Delete Posts** - Permanently delete posts in unconfigured languages
3. **Fix Language Assignment** - Reassign posts to an active language

### Cross-Language Term Operations

1. **Preflight Check** - See how many posts have cross-language term relationships
2. **Remove Cross-Language Terms** - Clean up incorrect term associations

## Usage

### Basic Workflow

1. Navigate to **Tools → Language Debug**
2. Select an action from the dropdown:
   - **Unconfigured Language Operations:**
     - Preflight: Check posts in unconfigured languages
     - Delete posts in unconfigured languages
     - Fix posts to target language
   - **Cross-Language Term Operations:**
     - Preflight: Check cross-language term relationships
     - Remove cross-language term relationships
3. Choose post type(s) to process (or select "All post types")
4. If fixing language assignment, select the target language
5. Click "Execute Debug Action"

### Recommended Process

1. **Always run preflight first** to see what will be affected
2. Review the results and breakdown by post type
3. Execute the appropriate fix action if needed
4. Re-run preflight to confirm all issues are resolved

## Technical Details

The Language Debug functionality is implemented in:
- `src/Admin/Language_Debug.php` - Admin interface and processing logic
- `src/Helpers/WPML_Post_Helper.php` - Helper methods for WPML operations
- `src/Helpers/WPML_Term_Helper.php` - Term language helper methods

### Key Methods

#### WPML_Post_Helper Methods

**For unconfigured languages:**
- `is_post_in_unconfigured_language()` - Checks if a post is in an inactive language
- `set_language()` - Assigns a post to a specific language
- `get_language()` - Gets the language code of a post

**For cross-language terms:**
- `has_cross_language_term_relationships()` - Checks if post has mismatched terms
- `get_cross_language_term_relationships()` - Gets detailed mismatch information
- `remove_cross_language_term_relationships()` - Removes incorrect term associations

#### WPML_Term_Helper Methods
- `get_language()` - Gets the language of a term
- `get_translation_id()` - Gets term ID in a specific language

## Examples

### Example 1: Fix Posts in Deactivated German Language

1. Select "Preflight: Check posts in unconfigured languages"
2. Select "All post types"
3. Execute to see affected posts
4. If German (de) posts are found and English is now default:
   - Select "Fix posts to target language"
   - Choose "English" as target language
   - Execute to reassign all German posts to English

### Example 2: Clean Up Cross-Language Categories

1. Select "Preflight: Check cross-language term relationships"
2. Select "post" post type
3. Execute to see posts with mismatched categories
4. If issues found:
   - Select "Remove cross-language term relationships"
   - Execute to clean up incorrect associations

### Example 3: Audit Before Major Language Changes

Before deactivating a language in WPML:

1. Run cross-language term preflight for all post types
2. Fix any cross-language issues found
3. After deactivating the language, run unconfigured language preflight
4. Decide whether to delete or reassign orphaned posts

## Security

- Requires `manage_options` capability
- All actions are protected with nonce verification
- Supports multisite installations (processes current site only)
- Batch processing prevents memory issues on large sites

## Performance Notes

- Posts are processed in batches of 100 to prevent timeouts
- Only post IDs are loaded initially for memory efficiency
- Language switching is minimized by caching current language
- Consider running during low-traffic periods for large sites

## Troubleshooting

### No posts found but issues persist

- Clear WPML cache: WPML → Support → Troubleshooting
- Check if suppress_filters is being used in queries
- Verify WPML is fully activated and configured

### Terms still appearing after cleanup

- Clear all caches (WPML, object cache, page cache)
- Check if theme/plugins are caching term queries
- Verify no custom term query filters are interfering

### Performance issues during processing

- Reduce batch size by processing specific post types
- Disable non-essential plugins during cleanup
- Consider using WP-CLI for very large sites