# WPML Post Helper Functions

The Multilingual Bridge plugin provides a set of static helper functions to simplify common WPML post operations that typically require multiple API calls.

## Overview

The `WPML_Post_Helper` class provides convenient methods for:
- Getting post language information
- Retrieving all translations of a post
- Checking translation status across languages
- Finding missing translations
- Safely deleting term relationships across language contexts

All methods are static and can be called directly without instantiation.

## Usage

```php
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
```

## Available Methods

### get_language()

Get the language code of a specific post.

```php
// Get language of post ID 123
$language = WPML_Post_Helper::get_language(123);
// Returns: 'en', 'de', 'fr', etc.

// Also works with WP_Post object
$post = get_post(123);
$language = WPML_Post_Helper::get_language($post);
```

### get_language_versions()

Get all translations of a post, including the original.

```php
// Get all translations as IDs
$translations = WPML_Post_Helper::get_language_versions(123);
// Returns: ['en' => 123, 'de' => 456, 'fr' => 789]

// Get all translations as WP_Post objects
$translations = WPML_Post_Helper::get_language_versions(123, true);
// Returns: ['en' => WP_Post, 'de' => WP_Post, 'fr' => WP_Post]
```

### get_translation_status()

Check which languages have translations for a post.

```php
$status = WPML_Post_Helper::get_translation_status(123);
// Returns: ['en' => true, 'de' => true, 'fr' => false, 'es' => false]
```

### has_all_translations()

Check if a post has been translated into all active languages.

```php
if (WPML_Post_Helper::has_all_translations(123)) {
    echo 'This post is fully translated!';
} else {
    echo 'Some translations are missing.';
}
```

### get_missing_translations()

Get a list of languages that don't have translations yet.

```php
$missing = WPML_Post_Helper::get_missing_translations(123);
// Returns: ['fr', 'es'] (language codes without translations)

// Display missing languages
foreach ($missing as $lang_code) {
    $language_name = apply_filters('wpml_translated_language_name', null, $lang_code);
    echo "Missing translation: $language_name\n";
}
```

### safe_delete_term_relationships()

Safely delete term relationships across all WPML language contexts. This method works around a WPML bug where term relationships with terms in the wrong language don't get properly deleted.

```php
// Delete all category relationships for a post across all languages
WPML_Post_Helper::safe_delete_term_relationships(123, 'category');

// Delete custom taxonomy relationships
WPML_Post_Helper::safe_delete_term_relationships($post, 'product_brand');

// Use case: Before assigning new terms, ensure clean slate across all languages
WPML_Post_Helper::safe_delete_term_relationships($post_id, 'post_tag');
wp_set_post_terms($post_id, $new_tags, 'post_tag');
```

**Important**: This method temporarily switches WPML language context to ensure term relationships are deleted in all language contexts, then restores the original language.

## Practical Examples

### Example 1: Display Translation Status in Admin

```php
add_filter('manage_posts_columns', function($columns) {
    $columns['translations'] = __('Translations', 'my-plugin');
    return $columns;
});

add_action('manage_posts_custom_column', function($column, $post_id) {
    if ($column === 'translations') {
        $status = WPML_Post_Helper::get_translation_status($post_id);
        $translated = array_filter($status);
        $total = count($status);
        $completed = count($translated);
        
        echo sprintf('%d/%d', $completed, $total);
        
        if ($completed < $total) {
            $missing = WPML_Post_Helper::get_missing_translations($post_id);
            echo ' <span class="missing">(' . implode(', ', $missing) . ')</span>';
        }
    }
}, 10, 2);
```

### Example 2: Create Language Switcher

```php
function my_custom_language_switcher($post_id) {
    $current_lang = WPML_Post_Helper::get_language($post_id);
    $translations = WPML_Post_Helper::get_language_versions($post_id);
    
    echo '<ul class="language-switcher">';
    foreach ($translations as $lang => $translated_id) {
        $language_name = apply_filters('wpml_translated_language_name', null, $lang);
        $class = ($lang === $current_lang) ? 'current' : '';
        $url = get_permalink($translated_id);
        
        echo sprintf(
            '<li class="%s"><a href="%s">%s</a></li>',
            esc_attr($class),
            esc_url($url),
            esc_html($language_name)
        );
    }
    echo '</ul>';
}
```

### Example 3: Bulk Check Translation Status

```php
function check_untranslated_posts($post_type = 'post') {
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'suppress_filters' => false,
    ];
    
    $posts = get_posts($args);
    $incomplete = [];
    
    foreach ($posts as $post) {
        if (!WPML_Post_Helper::has_all_translations($post->ID)) {
            $missing = WPML_Post_Helper::get_missing_translations($post->ID);
            $incomplete[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'missing' => $missing
            ];
        }
    }
    
    return $incomplete;
}
```

### Example 4: REST API Integration

```php
// Add translation info to REST API responses
add_action('rest_api_init', function() {
    register_rest_field('post', 'translation_status', [
        'get_callback' => function($post) {
            return [
                'language' => WPML_Post_Helper::get_language($post['id']),
                'translations' => WPML_Post_Helper::get_language_versions($post['id']),
                'complete' => WPML_Post_Helper::has_all_translations($post['id']),
                'missing' => WPML_Post_Helper::get_missing_translations($post['id'])
            ];
        },
        'schema' => [
            'type' => 'object',
            'description' => 'Translation status information'
        ]
    ]);
});
```

### Example 5: Safely Update Post Terms Across Languages

```php
/**
 * Update post categories ensuring clean relationships across all languages
 */
function update_post_categories_safely($post_id, $category_ids) {
    // First, safely remove all existing category relationships
    // This ensures terms in wrong language contexts are properly removed
    WPML_Post_Helper::safe_delete_term_relationships($post_id, 'category');
    
    // Now assign the new categories
    wp_set_post_categories($post_id, $category_ids);
    
    // Log the update
    error_log(sprintf(
        'Updated categories for post %d (language: %s)',
        $post_id,
        WPML_Post_Helper::get_language($post_id)
    ));
}

// Usage example: Bulk update product categories
$products = get_posts(['post_type' => 'product', 'posts_per_page' => -1]);
foreach ($products as $product) {
    update_post_categories_safely($product->ID, [15, 23, 47]);
}
```

## Performance Considerations

1. **Caching**: These methods make WPML API calls which query the database. Consider caching results for repeated calls.

2. **Bulk Operations**: When checking multiple posts, consider using a single query with WPML's internal functions rather than looping.

3. **Active Languages**: The number of active languages affects performance of `get_translation_status()` and related methods.

## Requirements

- WPML plugin must be installed and activated
- PHP 7.4 or higher
- WordPress 5.0 or higher

## Comparison with Native WPML Functions

| Task | Native WPML | WPML_Post_Helper |
|------|-------------|------------------|
| Get post language | `apply_filters('wpml_post_language_details', null, $id)['language_code']` | `WPML_Post_Helper::get_language($id)` |
| Get all translations | 3 filters: wpml_element_type → wpml_element_trid → wpml_get_element_translations | `WPML_Post_Helper::get_language_versions($id)` |
| Check translation completeness | Manual loop through active languages and translations | `WPML_Post_Helper::has_all_translations($id)` |
| Delete terms across languages | Manual language switching and deletion loop | `WPML_Post_Helper::safe_delete_term_relationships($id, $taxonomy)` |

## Error Handling

All methods handle invalid input gracefully:
- Invalid post IDs return empty arrays or strings
- Non-existent posts return empty results
- Methods work with both post IDs and WP_Post objects