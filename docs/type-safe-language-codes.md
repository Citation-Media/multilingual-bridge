# Type-Safe Language Code Handling

This document describes how the Multilingual Bridge plugin handles language codes in a type-safe manner using the `prinsfrank/standards` library.

## Overview

As of version 1.4.0, the plugin uses the `prinsfrank/standards` library to validate and handle language codes according to ISO 639 standards. This ensures that:

- Language codes are validated against actual ISO standards
- Invalid language codes are rejected at the API level
- Translation providers can declare their supported languages
- Developers get better IDE support and type safety

## Language Code Helper

The `Language_Code_Helper` class provides centralized methods for working with language codes:

### Validation

```php
use Multilingual_Bridge\Helpers\Language_Code_Helper;

// Check if a language code is valid
Language_Code_Helper::is_valid_language_code( 'en' );      // true
Language_Code_Helper::is_valid_language_code( 'zh-hans' ); // true
Language_Code_Helper::is_valid_language_code( 'invalid' ); // false

// Check if it's a simple ISO 639-1 code
Language_Code_Helper::is_iso_639_1( 'en' );      // true
Language_Code_Helper::is_iso_639_1( 'zh-hans' ); // false
```

### Normalization

```php
// Normalize language codes to lowercase
Language_Code_Helper::normalize( 'EN' );       // 'en'
Language_Code_Helper::normalize( 'ZH-HANS' );  // 'zh-hans'
```

### Conversion

```php
// Convert to DeepL API format (uppercase)
Language_Code_Helper::to_deepl_format( 'en' );      // 'EN'
Language_Code_Helper::to_deepl_format( 'zh-hans' ); // 'ZH-HANS'

// Get primary language from complex tag
Language_Code_Helper::get_primary_language( 'zh-hans' ); // 'zh'
Language_Code_Helper::get_primary_language( 'en-us' );   // 'en'
```

### All Supported Languages

```php
// Get all ISO 639-1 language codes
$all_codes = Language_Code_Helper::get_all_iso_639_1_codes();
// Returns: ['aa', 'ab', 'af', 'ak', 'sq', 'am', 'ar', ...]
```

## REST API Changes

The REST API endpoints now use type-safe validation instead of regex patterns:

### Before (regex validation)
```php
'target_lang' => array(
    'type'    => 'string',
    'pattern' => '^[a-zA-Z]{2}(-[a-zA-Z]{2})?$',
)
```

### After (enum-based validation)
```php
'target_lang' => array(
    'type'              => 'string',
    'validate_callback' => array( $this, 'validate_language_code' ),
    'sanitize_callback' => array( Language_Code_Helper::class, 'normalize' ),
)
```

## Translation Providers

All translation providers must now implement methods to declare their supported languages:

```php
interface Translation_Provider_Interface {
    /**
     * Get list of supported target languages
     *
     * @return array<string> Array of supported target language codes
     */
    public function get_supported_target_languages(): array;

    /**
     * Get list of supported source languages
     *
     * @return array<string> Array of supported source language codes
     */
    public function get_supported_source_languages(): array;
}
```

### DeepL Provider Example

The DeepL provider now validates language support before making API calls:

```php
// Check if language is supported
$provider = new DeepL_Provider();
$provider->is_target_language_supported( 'en' );    // true
$provider->is_target_language_supported( 'tlh' );   // false (Klingon not supported)

// Get all supported languages
$target_languages = $provider->get_supported_target_languages();
$source_languages = $provider->get_supported_source_languages();
```

## Supported Language Formats

The system supports both simple ISO 639-1 codes and BCP 47 language tags:

### Simple ISO 639-1 Codes
- `en` - English
- `de` - German
- `fr` - French
- `zh` - Chinese

### Language Tags with Variants
- `en-gb` - English (British)
- `en-us` - English (American)
- `zh-hans` - Chinese (Simplified)
- `pt-br` - Portuguese (Brazilian)
- `pt-pt` - Portuguese (European)

## PHP Version Requirement

The `prinsfrank/standards` library requires PHP 8.1 or higher. The plugin's minimum PHP requirement has been updated accordingly:

- **Previous**: PHP 8.0
- **Current**: PHP 8.1

## Benefits

1. **Type Safety**: Language codes are validated against actual standards
2. **Better Errors**: Users get clear error messages about unsupported languages
3. **Provider Transparency**: Each provider declares exactly which languages it supports
4. **Future Proof**: Easy to add new providers with different language support
5. **IDE Support**: Better autocomplete and type hints in development

## Migration Notes

For developers extending the plugin:

1. **Custom Providers**: If you've created custom translation providers, you must implement the new interface methods:
   - `get_supported_target_languages()`
   - `get_supported_source_languages()`

2. **API Calls**: Language code validation is stricter. Ensure you're using valid ISO 639-1 codes or language tags.

3. **PHP Version**: Projects using this plugin must upgrade to PHP 8.1+ if still on PHP 8.0.

## Examples

### REST API Call with Type-Safe Language Code

```javascript
// Valid language code
fetch('/wp-json/multilingual-bridge/v1/translate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        text: 'Hello World',
        target_lang: 'de',      // Valid: German
        source_lang: 'en'       // Valid: English
    })
});

// Invalid language code - will be rejected
fetch('/wp-json/multilingual-bridge/v1/translate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        text: 'Hello World',
        target_lang: 'invalid',  // Error: Invalid language code
        source_lang: 'en'
    })
});
```

### PHP Usage

```php
use Multilingual_Bridge\Helpers\Language_Code_Helper;
use Multilingual_Bridge\Translation\Translation_Manager;

// Validate before translating
$target_lang = 'de';

if ( ! Language_Code_Helper::is_valid_language_code( $target_lang ) ) {
    wp_die( 'Invalid language code' );
}

// Get translation manager and translate
$manager = Translation_Manager::instance();
$result = $manager->translate( 'Hello World', $target_lang, 'en' );

if ( is_wp_error( $result ) ) {
    // Handle error (e.g., unsupported language for provider)
    echo $result->get_error_message();
} else {
    echo $result; // Translated text
}
```

## References

- [ISO 639-1 Language Codes](https://www.iso.org/iso-639-language-codes.html)
- [BCP 47 Language Tags](https://datatracker.ietf.org/doc/html/rfc5646)
- [prinsfrank/standards Library](https://github.com/PrinsFrank/standards)
