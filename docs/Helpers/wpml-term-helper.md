# WPML Term Helper Functions

The Multilingual Bridge plugin provides a comprehensive set of static helper functions to simplify WPML term/taxonomy operations that typically require multiple API calls.

## Overview

The `WPML_Term_Helper` class provides convenient methods for:
- Getting term language information
- Retrieving all translations of a term
- Checking translation status across languages
- Finding missing translations
- Managing term language assignments
- Duplicating terms across languages

All methods are static and can be called directly without instantiation.

## Usage

```php
use Multilingual_Bridge\Helpers\WPML_Term_Helper;
```

## Available Methods

### get_language()

Get the language code of a specific term.

```php
// Get language of term ID 123 (must provide taxonomy)
$language = WPML_Term_Helper::get_language(123, 'category');
// Returns: 'en', 'de', 'fr', etc.

// Also works with WP_Term object (taxonomy detected automatically)
$term = get_term(123);
$language = WPML_Term_Helper::get_language($term);
```

### get_language_versions()

Get all translations of a term, including the original. The results are sorted with the original language first.

```php
// Get all translations as IDs
$translations = WPML_Term_Helper::get_language_versions(123, 'category');
// Returns: ['en' => 123, 'de' => 456, 'fr' => 789]

// Get all translations as WP_Term objects
$translations = WPML_Term_Helper::get_language_versions(123, 'category', true);
// Returns: ['en' => WP_Term, 'de' => WP_Term, 'fr' => WP_Term]

// With WP_Term object
$term = get_term(123);
$translations = WPML_Term_Helper::get_language_versions($term);
```

### get_translation_status()

Check which languages have translations for a term.

```php
$status = WPML_Term_Helper::get_translation_status(123, 'category');
// Returns: ['en' => true, 'de' => true, 'fr' => false, 'es' => false]

// Check specific taxonomy
$tag_status = WPML_Term_Helper::get_translation_status($tag_id, 'post_tag');
```

### has_all_translations()

Check if a term has been translated into all active languages.

```php
if (WPML_Term_Helper::has_all_translations(123, 'category')) {
    echo 'This term is fully translated!';
} else {
    echo 'Some translations are missing.';
}
```

### get_missing_translations()

Get a list of languages that don't have translations yet.

```php
$missing = WPML_Term_Helper::get_missing_translations(123, 'category');
// Returns: ['fr', 'es'] (language codes without translations)

// Display missing languages
foreach ($missing as $lang_code) {
    $language_name = apply_filters('wpml_translated_language_name', null, $lang_code);
    echo "Missing translation: $language_name\n";
}
```

### is_term_in_unconfigured_language()

Check if a term is in a language that is no longer active in WPML.

```php
if (WPML_Term_Helper::is_term_in_unconfigured_language($term)) {
    echo 'This term is in a deactivated language!';
    // Maybe reassign to a new language
}
```

### set_language()

Set or update a term's language assignment.

```php
// Assign term to German
$success = WPML_Term_Helper::set_language(123, 'category', 'de');

// Fix terms with no language
$unassigned_terms = get_terms([
    'taxonomy' => 'category',
    'hide_empty' => false,
    'suppress_filters' => true
]);

foreach ($unassigned_terms as $term) {
    if (empty(WPML_Term_Helper::get_language($term))) {
        WPML_Term_Helper::set_language($term, 'category', 'en');
    }
}
```

### get_translation_id()

Get the ID of a term translation in a specific language.

```php
// Get German translation ID
$german_id = WPML_Term_Helper::get_translation_id(123, 'category', 'de');

if ($german_id) {
    $german_term = get_term($german_id);
}
```

### get_original_term_id()

Get the original term ID from any translation.

```php
// Get original from any translation
$original_id = WPML_Term_Helper::get_original_term_id(456, 'category');

// Works with WP_Term objects too
$original_id = WPML_Term_Helper::get_original_term_id($translated_term);
```

### is_original_term()

Check if a term is the original (not a translation).

```php
if (WPML_Term_Helper::is_original_term($term)) {
    echo 'This is the original term';
} else {
    echo 'This is a translation';
}
```

## Practical Examples

### Example 1: Display Term Translation Status in Admin

```php
add_filter('manage_edit-category_columns', function($columns) {
    $columns['translations'] = __('Translations', 'my-plugin');
    return $columns;
});

add_filter('manage_category_custom_column', function($content, $column_name, $term_id) {
    if ($column_name === 'translations') {
        $status = WPML_Term_Helper::get_translation_status($term_id, 'category');
        $translated = array_filter($status);
        $total = count($status);
        $completed = count($translated);
        
        $content = sprintf('%d/%d', $completed, $total);
        
        if ($completed < $total) {
            $missing = WPML_Term_Helper::get_missing_translations($term_id, 'category');
            $content .= ' <span class="missing">(' . implode(', ', $missing) . ')</span>';
        }
    }
    return $content;
}, 10, 3);
```

### Example 2: Term Language Switcher

```php
function render_term_language_switcher($term_id, $taxonomy) {
    $current_lang = WPML_Term_Helper::get_language($term_id, $taxonomy);
    $translations = WPML_Term_Helper::get_language_versions($term_id, $taxonomy, true);
    
    echo '<ul class="term-language-switcher">';
    foreach ($translations as $lang => $term) {
        $language_name = apply_filters('wpml_translated_language_name', null, $lang);
        $class = ($lang === $current_lang) ? 'current' : '';
        $url = get_term_link($term);
        
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

### Example 3: Bulk Term Translation Check

```php
function check_untranslated_terms($taxonomy = 'category') {
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'suppress_filters' => false,
    ]);
    
    $incomplete = [];
    
    foreach ($terms as $term) {
        if (!WPML_Term_Helper::has_all_translations($term)) {
            $missing = WPML_Term_Helper::get_missing_translations($term);
            $incomplete[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'missing' => $missing
            ];
        }
    }
    
    return $incomplete;
}
```

### Example 4: REST API Term Language Support

```php
// Add translation info to term REST API responses
add_action('rest_api_init', function() {
    $taxonomies = get_taxonomies(['public' => true], 'names');
    
    foreach ($taxonomies as $taxonomy) {
        register_rest_field($taxonomy, 'translation_status', [
            'get_callback' => function($term) {
                return [
                    'language' => WPML_Term_Helper::get_language($term['id'], $term['taxonomy']),
                    'translations' => WPML_Term_Helper::get_language_versions($term['id'], $term['taxonomy']),
                    'complete' => WPML_Term_Helper::has_all_translations($term['id'], $term['taxonomy']),
                    'missing' => WPML_Term_Helper::get_missing_translations($term['id'], $term['taxonomy'])
                ];
            },
            'schema' => [
                'type' => 'object',
                'description' => 'Translation status information'
            ]
        ]);
    }
});
```

### Example 5: Synchronize Term Hierarchies

```php
/**
 * Ensure term hierarchies are synchronized across languages
 */
function sync_term_hierarchies($taxonomy) {
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => 0,
        'suppress_filters' => false
    ]);
    
    foreach ($terms as $term) {
        // Get all translations
        $translations = WPML_Term_Helper::get_language_versions($term, $taxonomy, true);
        
        // Get children of original term
        $children = get_terms([
            'taxonomy' => $taxonomy,
            'parent' => $term->term_id,
            'hide_empty' => false
        ]);
        
        foreach ($children as $child) {
            // Ensure child exists in all languages
            $child_translations = WPML_Term_Helper::get_language_versions($child, $taxonomy);
            
            foreach ($translations as $lang => $parent_translation) {
                if (!isset($child_translations[$lang])) {
                    // Create missing child translation using WPML's native functionality
                    // You would need to implement your own term duplication logic here
                    
                    // Set correct parent
                    if ($new_child_id) {
                        wp_update_term($new_child_id, $taxonomy, [
                            'parent' => $parent_translation->term_id
                        ]);
                    }
                }
            }
        }
    }
}
```



## Performance Considerations

1. **Caching**: Term language data is relatively static. Consider caching translation lookups for frequently accessed terms.

2. **Bulk Operations**: When processing multiple terms, use `suppress_filters` in get_terms() and then check languages individually to avoid repeated WPML filtering.

3. **Hierarchy Operations**: When working with hierarchical taxonomies, process parents before children to maintain relationships.

## Error Handling

All methods handle invalid input gracefully:
- Invalid term IDs return empty arrays or strings
- Non-existent terms return empty results
- Methods work with both term IDs and WP_Term objects
- Missing taxonomy parameter is auto-detected when possible

## Requirements

- WPML plugin must be installed and activated
- PHP 8.1 or higher (for union type and enum support)
- WordPress 5.0 or higher

## Comparison with Native WPML Functions

| Task | Native WPML | WPML_Term_Helper |
|------|-------------|------------------|
| Get term language | Complex filter chain | `WPML_Term_Helper::get_language($id, $taxonomy)` |
| Get all translations | 3 filters: element_type → trid → translations | `WPML_Term_Helper::get_language_versions($id, $taxonomy)` |
| Check translation completeness | Manual loop through languages | `WPML_Term_Helper::has_all_translations($id, $taxonomy)` |

## Working with Custom Taxonomies

The helper works with any registered taxonomy:

```php
// Custom taxonomy example
$product_cats = get_terms(['taxonomy' => 'product_cat']);

foreach ($product_cats as $cat) {
    $translations = WPML_Term_Helper::get_language_versions($cat);
    
    if (count($translations) < 2) {
        echo "Category '{$cat->name}' needs translation\n";
    }
}
```