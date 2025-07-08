# WPML Post Helper Functions

The Multilingual Bridge plugin provides a set of static helper functions to simplify common WPML post operations that typically require multiple API calls.

## Overview

The `WPML_Post_Helper` class provides convenient methods for:
- Getting post language information
- Retrieving all translations of a post
- Checking translation status across languages
- Finding missing translations
- Safely deleting term relationships across language contexts
- Setting or updating post language assignments
- Detecting and fixing cross-language term issues
- Safely assigning terms with language validation
- Triggering automatic translations

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

Get all translations of a post, including the original. The results are sorted with the original language first.

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

### set_language()

Set or update a post's language assignment in WPML. This method can be used to fix posts with no language assignment, change a post's language, or reassign posts from deactivated languages.

```php
// Assign language to a post
$result = WPML_Post_Helper::set_language(123, 'de');
if (is_wp_error($result)) {
    echo 'Error: ' . $result->get_error_message();
} elseif ($result) {
    echo 'Language successfully set!';
}

// Change a post's language
$post = get_post(456);
$result = WPML_Post_Helper::set_language($post, 'fr');

// Handle validation errors
$result = WPML_Post_Helper::set_language(789, 'invalid_lang');
if (is_wp_error($result) && $result->get_error_code() === 'invalid_language') {
    // Language code is not configured in WPML
    echo $result->get_error_message(); // "Language "invalid_lang" is not configured in WPML."
}
```

**Returns**: 
- `true` if the language was set successfully
- `false` if the post ID is invalid or post type cannot be determined
- `WP_Error` if the target language is not configured in WPML

**Note**: This method validates that the target language is active in WPML before attempting to set it.

### is_post_in_unconfigured_language()

Check if a post is in a language that is not configured/active in WPML.

```php
if (WPML_Post_Helper::is_post_in_unconfigured_language(123)) {
    echo 'This post is in a deactivated language!';
    // You might want to reassign it to an active language
}
```

### has_cross_language_term_relationships()

Check if a post has term relationships in languages other than its own.

```php
// Check all taxonomies
if (WPML_Post_Helper::has_cross_language_term_relationships(123)) {
    echo 'This post has terms in wrong languages!';
}

// Check specific taxonomy
if (WPML_Post_Helper::has_cross_language_term_relationships($post, 'category')) {
    echo 'This post has categories in wrong languages!';
}
```

### get_cross_language_term_relationships()

Get detailed information about cross-language term relationships, organized by language for efficient processing.

```php
$mismatches = WPML_Post_Helper::get_cross_language_term_relationships(123);

// Returns array indexed by language code, then taxonomy
// Example structure:
// [
//     'de' => [
//         'category' => [12, 15],
//         'post_tag' => [34]
//     ],
//     'fr' => [
//         'category' => [56]
//     ]
// ]

foreach ($mismatches as $language => $taxonomies) {
    echo "Terms in language '$language':\n";
    foreach ($taxonomies as $taxonomy => $term_ids) {
        echo "  $taxonomy: " . implode(', ', $term_ids) . "\n";
    }
}
```

### remove_cross_language_term_relationships()

Remove term relationships where term language doesn't match post language.

```php
// Remove all cross-language terms
$removed = WPML_Post_Helper::remove_cross_language_term_relationships(123);

// Remove only for specific taxonomy
$removed = WPML_Post_Helper::remove_cross_language_term_relationships($post, 'post_tag');

// Display what was removed
// Returns array indexed by taxonomy with term IDs that were removed
foreach ($removed as $taxonomy => $term_ids) {
    echo "Removed from $taxonomy: " . implode(', ', $term_ids) . "\n";
}
```

### safe_assign_terms()

Safely assign terms to a post with automatic language validation and translation lookup.

```php
// Basic usage - assign terms with language validation
$error = WPML_Post_Helper::safe_assign_terms(
    123,                    // Post ID
    [45, 67, 89],          // Term IDs to assign
    'category',            // Taxonomy
    false                  // Replace existing terms (not append)
);

// Check for errors
if ($error->has_errors()) {
    foreach ($error->get_error_codes() as $code) {
        foreach ($error->get_error_messages($code) as $message) {
            echo "Error ($code): $message\n";
        }
    }
} else {
    echo 'All terms assigned successfully';
}

// Example with automatic translation lookup
$german_term_ids = [12, 34]; // German language terms
$english_post_id = 456;      // English language post

$error = WPML_Post_Helper::safe_assign_terms(
    $english_post_id,
    $german_term_ids,
    'product_cat',
    true  // Append to existing terms
);

// The function will automatically find English translations of German terms
// and assign those instead, or return errors for terms without translations
```

**Returns:**
- `WP_Error`: An error object that may contain multiple error messages. Check with `has_errors()` to see if any errors occurred.

### trigger_automatic_translation()

Trigger automatic translation for a post to specified target languages or all available languages.

**Attention**: By default the automatic translation is triggered on save_post hook. Before using always check if your code calls the hook correctly or has good reason to not do so!

```php
// Basic usage - translate to all available languages
$job_ids = WPML_Post_Helper::trigger_automatic_translation(123);

if (is_wp_error($job_ids)) {
    error_log('Translation failed: ' . $job_ids->get_error_message());
} else {
    // Translation jobs created successfully
    error_log('Created ' . count($job_ids) . ' translation jobs');
}
```

Trigger automatic translation for a post to specified languages.
```php
// Translate to specific languages only
$job_ids = WPML_Post_Helper::trigger_automatic_translation(
    123,                        // Post ID
    ['de', 'fr', 'es']         // Target language codes
);

// Using in production code
if (!is_wp_error($job_ids)) {
    // Store job IDs for later reference
    update_post_meta($post_id, '_translation_job_ids', $job_ids);
    
    echo 'Created ' . count($job_ids) . ' translation jobs';
}
```

**Parameters:**
- `$post` (int|WP_Post): Post ID or WP_Post object to translate
- `$target_languages` (array|null): Array of target language codes, or null for all available languages

**Returns:** 
- `array<int>`: Array of job IDs on success
- `WP_Error`: Error object on failure

**Common Errors:**
- `invalid_post`: Invalid post ID provided
- `wpml_dependencies_missing`: WPML dependencies are not available
- `no_source_language`: Post has no language assigned
- `no_valid_languages`: No valid target languages specified

**Important Notes about WPML's Translation Update Detection:**

WPML has built-in mechanisms to prevent unnecessary translations:

1. **Content Hash Comparison**: WPML uses MD5 hashes to detect if post content has changed. When a post is updated, WPML compares the new content hash with the stored hash to determine if translations need updating.

2. **The `needs_update` Flag**: When content changes are detected, WPML sets the `needs_update` flag in the database. Posts with this flag will show the `ICL_TM_NEEDS_UPDATE` status (value 3).

3. **Manual Trigger Behavior**: When using `trigger_automatic_translation()`, be aware that:
   - If a translation already exists and the content hasn't changed (same MD5 hash), WPML may not create a new translation job
   - If you need to force retranslation regardless of content changes, you may need to manually set the `needs_update` flag first
   - The method sends posts for translation based on current content, so ensure content is saved before calling
   - Jobs are automatically configured with the ATE (Advanced Translation Editor) for automatic translation processing

4. **Automatic Translation Processing**: 
   - The method sets the `automatic` flag for proper automatic translation
   - WPML's background sync process will pick up these jobs and send them to the ATE service
   - The sync happens through AJAX calls when admin pages are loaded
   - No additional manual steps are required after calling this method

5. **Best Practice**: Before triggering automatic translation, consider checking if the post actually needs translation updates to avoid unnecessary API calls and costs.

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

### Example 6: Detect and Fix Cross-Language Term Issues

```php
/**
 * Scan and fix posts with cross-language term relationships
 */
function fix_cross_language_terms_for_post_type($post_type = 'post') {
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'suppress_filters' => false,
        'fields' => 'ids'
    ];
    
    $posts = get_posts($args);
    $fixed_count = 0;
    
    foreach ($posts as $post_id) {
        // Check if has cross-language terms
        if (WPML_Post_Helper::has_cross_language_term_relationships($post_id)) {
            // Get details before fixing
            $mismatches = WPML_Post_Helper::get_cross_language_term_relationships($post_id);
            
            // Log what we're about to fix
            error_log(sprintf(
                'Post %d (%s) has cross-language terms:',
                $post_id,
                WPML_Post_Helper::get_language($post_id)
            ));
            
            foreach ($mismatches as $language => $taxonomies) {
                foreach ($taxonomies as $taxonomy => $term_ids) {
                    error_log(sprintf(
                        '  - %s in %s: %s',
                        $taxonomy,
                        $language,
                        implode(', ', $term_ids)
                    ));
                }
            }
            
            // Fix the relationships
            $removed = WPML_Post_Helper::remove_cross_language_term_relationships($post_id);
            
            if (!empty($removed)) {
                $fixed_count++;
            }
        }
    }
    
    return $fixed_count;
}
```

### Example 7: Admin Column for Cross-Language Terms

```php
// Add column to post list
add_filter('manage_posts_columns', function($columns) {
    $columns['term_language_check'] = __('Term Languages', 'my-plugin');
    return $columns;
});

// Display column content
add_action('manage_posts_custom_column', function($column, $post_id) {
    if ($column === 'term_language_check') {
        if (WPML_Post_Helper::has_cross_language_term_relationships($post_id)) {
            $mismatches = WPML_Post_Helper::get_cross_language_term_relationships($post_id);
            $count = 0;
            foreach ($mismatches as $lang_data) {
                foreach ($lang_data as $tax_terms) {
                    $count += count($tax_terms);
                }
            }
            
            echo sprintf(
                '<span style="color: red;">⚠️ %d %s</span>',
                $count,
                _n('mismatched term', 'mismatched terms', $count, 'my-plugin')
            );
        } else {
            echo '<span style="color: green;">✓</span>';
        }
    }
}, 10, 2);
```

### Example 8: Prevent Cross-Language Terms on Save

```php
// Simple cleanup - just remove cross-language terms when saving
add_action('save_post', function($post_id) {
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Simply remove any cross-language term relationships
    $removed = WPML_Post_Helper::remove_cross_language_term_relationships($post_id);
    
    // Optional: Log what was cleaned up
    if (!empty($removed)) {
        error_log(sprintf(
            'Cleaned cross-language terms from post %d: %s',
            $post_id,
            implode(', ', array_keys($removed))
        ));
    }
}, 99);
```

### Example 9: Safe Term Assignment with Language Validation

```php
/**
 * Import handler that safely assigns terms regardless of their language
 */
function import_product_with_categories($product_data) {
    // Create product in English
    $product_id = wp_insert_post([
        'post_title' => $product_data['title'],
        'post_type' => 'product',
        'post_status' => 'publish'
    ]);
    
    // Set product language
    WPML_Post_Helper::set_language($product_id, 'en');
    
    // Terms might be in any language from the import
    $category_ids = $product_data['category_ids']; // e.g., [45, 67, 89]
    
    // Safe assignment with automatic translation lookup
    $error = WPML_Post_Helper::safe_assign_terms(
        $product_id,
        $category_ids,
        'product_cat'
    );
    
    // Log any issues
    if ($error->has_errors()) {
        foreach ($error->get_error_codes() as $code) {
            foreach ($error->get_error_messages($code) as $message) {
                error_log(sprintf(
                    'Product %d assignment error: %s',
                    $product_id,
                    $message
                ));
            }
        }
    }
    
    return [
        'product_id' => $product_id,
        'has_errors' => $error->has_errors(),
        'error_count' => count($error->get_error_codes())
    ];
}

/**
 * Bulk update terms with language safety
 */
function bulk_update_post_terms($post_ids, $term_ids, $taxonomy) {
    $results = [];
    
    foreach ($post_ids as $post_id) {
        $error = WPML_Post_Helper::safe_assign_terms(
            $post_id,
            $term_ids,
            $taxonomy,
            true // Append mode
        );
        
        $results[$post_id] = [
            'has_errors' => $error->has_errors(),
            'error_count' => count($error->get_error_codes())
        ];
    }
    
    return $results;
}
```

## Performance Considerations

1. **Caching**: These methods make WPML API calls which query the database. Consider caching results for repeated calls.

2. **Bulk Operations**: When checking multiple posts, consider using a single query with WPML's internal functions rather than looping.

3. **Active Languages**: The number of active languages affects performance of `get_translation_status()` and related methods.

## Requirements

- WPML plugin must be installed and activated
- PHP 8.0 or higher (for union type support)
- WordPress 5.0 or higher

## Comparison with Native WPML Functions

| Task | Native WPML | WPML_Post_Helper |
|------|-------------|------------------|
| Get post language | `apply_filters('wpml_post_language_details', null, $id)['language_code']` | `WPML_Post_Helper::get_language($id)` |
| Get all translations | 3 filters: wpml_element_type → wpml_element_trid → wpml_get_element_translations | `WPML_Post_Helper::get_language_versions($id)` |
| Check translation completeness | Manual loop through active languages and translations | `WPML_Post_Helper::has_all_translations($id)` |
| Delete terms across languages | Manual language switching and deletion loop | `WPML_Post_Helper::safe_delete_term_relationships($id, $taxonomy)` |
| Check cross-language terms | Complex manual checking with language switching | `WPML_Post_Helper::has_cross_language_term_relationships($id)` |
| Fix cross-language terms | Complex manual process | `WPML_Post_Helper::remove_cross_language_term_relationships($id)` |
| Safe term assignment | Manual validation and translation lookup | `WPML_Post_Helper::safe_assign_terms($id, $terms, $taxonomy)` |
| Trigger automatic translation | Manual batch creation with WPML_TM_Translation_Batch classes | `WPML_Post_Helper::trigger_automatic_translation($id, $languages)` |

## Error Handling

All methods handle invalid input gracefully:
- Invalid post IDs return empty arrays or strings
- Non-existent posts return empty results
- Methods work with both post IDs and WP_Post objects