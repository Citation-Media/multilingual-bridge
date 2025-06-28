# WPML Language Helper Functions

The Multilingual Bridge plugin provides a comprehensive set of static helper functions to simplify WPML language operations and provide a centralized API for language management.

## Overview

The `WPML_Language_Helper` class provides convenient methods for:
- Getting configured language codes and details
- Managing language contexts
- Checking language configuration status
- Switching between languages programmatically
- Sorting and filtering language lists

All methods are static and can be called directly without instantiation.

## Usage

```php
use Multilingual_Bridge\Helpers\WPML_Language_Helper;
```

## Available Methods

### get_available_languages()

Get all available languages configured in WPML with the default language sorted first. This is the primary method for retrieving language data.

```php
// Get all available languages with default language first
$languages = WPML_Language_Helper::get_available_languages();
// Returns: ['en' => ['language_code' => 'en', 'native_name' => 'English', ...], 'de' => [...], ...]

// Handle WPML not installed or no languages configured
if (empty($languages)) {
    // WPML is not active or no languages are configured
}
```

### get_active_language_codes()

Get a simple array of active language codes.

```php
// Get all language codes
$codes = WPML_Language_Helper::get_active_language_codes();
// Returns: ['en', 'de', 'fr', 'es']

// Use in queries or loops
foreach ($codes as $lang_code) {
    // Process each language
}
```

### get_default_language()

Get the default language code configured in WPML.

```php
$default_lang = WPML_Language_Helper::get_default_language();
// Returns: 'en' (or whatever is set as default)

if ($current_lang === $default_lang) {
    // This is the default language
}
```

### get_current_language()

Get the currently active language in the WPML context.

```php
$current_lang = WPML_Language_Helper::get_current_language();
// Returns: 'de' (or current language code)

// Store for later restoration
$original_lang = WPML_Language_Helper::get_current_language();
```

### is_language_active()

Check if a specific language code is active in WPML.

```php
if (WPML_Language_Helper::is_language_active('fr')) {
    // French is configured and active
} else {
    // French is not available
}

// Validate user input
$requested_lang = $_GET['lang'] ?? '';
if (!WPML_Language_Helper::is_language_active($requested_lang)) {
    $requested_lang = WPML_Language_Helper::get_default_language();
}
```

### get_language_details()

Get complete details for a specific language.

```php
$lang_details = WPML_Language_Helper::get_language_details('de');
// Returns: [
//     'language_code' => 'de',
//     'native_name' => 'Deutsch',
//     'translated_name' => 'German',
//     'country_flag_url' => 'https://...',
//     'url' => 'https://...'
// ]

if (empty($lang_details)) {
    // Language not found
}
```

### get_language_native_name()

Get the native name of a language (e.g., "Deutsch" for German).

```php
$native_name = WPML_Language_Helper::get_language_native_name('de');
// Returns: 'Deutsch'

echo "Switch to $native_name";
```

### get_language_translated_name()

Get the translated name of a language in the current or specified language.

```php
// Get name in current language
$translated_name = WPML_Language_Helper::get_language_translated_name('de');
// Returns: 'German' (if current language is English)

// Get name in specific language
$name_in_french = WPML_Language_Helper::get_language_translated_name('de', 'fr');
// Returns: 'Allemand'
```

### get_language_flag_url()

Get the flag URL for a language.

```php
$flag_url = WPML_Language_Helper::get_language_flag_url('de');
// Returns: 'https://example.com/wp-content/plugins/sitepress.../de.png'

if ($flag_url) {
    echo '<img src="' . esc_url($flag_url) . '" alt="' . esc_attr($lang_code) . '">';
}
```

### switch_language()

Switch to a specific language context and return the previous language.

```php
// Switch to German
$previous_lang = WPML_Language_Helper::switch_language('de');

// Do something in German context
$german_posts = get_posts(['post_type' => 'post']);

// Restore previous language
WPML_Language_Helper::restore_language($previous_lang);
```

### restore_language()

Restore a previously saved language context.

```php
$original = WPML_Language_Helper::get_current_language();

// Do work in different languages
WPML_Language_Helper::switch_language('fr');
// ... work ...

// Always restore
WPML_Language_Helper::restore_language($original);
```

### in_language_context()

Execute a callback function in a specific language context with automatic restoration.

```php
// Get posts in German context
$german_posts = WPML_Language_Helper::in_language_context('de', function() {
    return get_posts([
        'post_type' => 'post',
        'posts_per_page' => 10
    ]);
});

// Complex operations with guaranteed language restoration
$result = WPML_Language_Helper::in_language_context('fr', function() {
    // Any code here runs in French context
    $menu = wp_nav_menu(['echo' => false]);
    $widgets = dynamic_sidebar('french-sidebar');
    
    return [
        'menu' => $menu,
        'has_widgets' => $widgets
    ];
});
```

## Practical Examples

### Example 1: Language Switcher Component

```php
function render_language_switcher($current_post_id = null) {
    $languages = WPML_Language_Helper::get_available_languages();
    $current_lang = WPML_Language_Helper::get_current_language();
    
    echo '<ul class="language-switcher">';
    foreach ($languages as $code => $language) {
        $active_class = ($code === $current_lang) ? 'active' : '';
        $flag_url = $language['country_flag_url'] ?? '';
        
        echo sprintf(
            '<li class="%s"><a href="%s">',
            esc_attr($active_class),
            esc_url($language['url'])
        );
        
        if ($flag_url) {
            echo sprintf(
                '<img src="%s" alt="%s"> ',
                esc_url($flag_url),
                esc_attr($language['native_name'])
            );
        }
        
        echo esc_html($language['native_name']);
        echo '</a></li>';
    }
    echo '</ul>';
}
```

### Example 2: Language-aware Admin Notices

```php
add_action('admin_notices', function() {
    // Only show on non-default languages
    $current = WPML_Language_Helper::get_current_language();
    $default = WPML_Language_Helper::get_default_language();
    
    if ($current !== $default && $current) {
        $lang_name = WPML_Language_Helper::get_language_native_name($current);
        ?>
        <div class="notice notice-info">
            <p><?php 
                printf(
                    __('You are currently editing in %s. Some features may behave differently.', 'my-plugin'),
                    '<strong>' . esc_html($lang_name) . '</strong>'
                );
            ?></p>
        </div>
        <?php
    }
});
```

### Example 3: Bulk Operations Across Languages

```php
/**
 * Update option in all languages
 */
function update_option_all_languages($option_name, $value) {
    $languages = WPML_Language_Helper::get_active_language_codes();
    $results = [];
    
    foreach ($languages as $lang_code) {
        $results[$lang_code] = WPML_Language_Helper::in_language_context(
            $lang_code,
            function() use ($option_name, $value, $lang_code) {
                // WPML may append language code to option names
                $lang_option_name = $option_name . '_' . $lang_code;
                return update_option($lang_option_name, $value);
            }
        );
    }
    
    return $results;
}
```



## Performance Considerations

1. **Caching**: Language configuration rarely changes. Consider caching the results of `get_available_languages()` and `get_active_language_codes()` for the request duration.

2. **Context Switching**: Language switching has a performance cost. Use `in_language_context()` for isolated operations to ensure proper restoration.

3. **Bulk Operations**: When processing multiple languages, collect all data first, then process in batches to minimize context switches.

## Error Handling

All methods handle WPML absence gracefully:
- Return empty arrays for collection methods
- Return empty strings for single value methods
- Return `false` for boolean methods
- Context switching methods safely handle invalid language codes

## Requirements

- WPML plugin must be installed and activated for full functionality
- PHP 8.0 or higher (for union type support)
- WordPress 5.0 or higher

## Migration from Direct WPML Calls

| Direct WPML | WPML_Language_Helper |
|-------------|---------------------|
| `apply_filters('wpml_active_languages', null)` | `WPML_Language_Helper::get_available_languages()` |
| `apply_filters('wpml_default_language', null)` | `WPML_Language_Helper::get_default_language()` |
| `apply_filters('wpml_current_language', null)` | `WPML_Language_Helper::get_current_language()` |
| `do_action('wpml_switch_language', $lang)` | `WPML_Language_Helper::switch_language($lang)` |
| Manual language context management | `WPML_Language_Helper::in_language_context($lang, $callback)` |
| Get language codes only | `WPML_Language_Helper::get_active_language_codes()` |