# Migration Guide: DeepL_Translator to Translation System

This guide helps you migrate from the old `DeepL_Translator` class to the new provider-agnostic translation system.

## What Changed?

### Old Architecture (Deprecated)
```php
// Direct instantiation of DeepL_Translator
$translator = new DeepL_Translator( $api_key );
$result = $translator->translate( $text, $target_language );
```

**Issues:**
- ❌ Tightly coupled to DeepL API
- ❌ No support for alternative providers
- ❌ Direct class instantiation required
- ❌ Limited extensibility

### New Architecture
```php
// Provider-agnostic system using Translation_Manager
$manager = Translation_Manager::get_instance();
$result = $manager->translate( $text, $target_language );
```

**Benefits:**
- ✅ Supports multiple providers (DeepL, Google, OpenAI, etc.)
- ✅ Centralized management through singleton
- ✅ Extensible via WordPress hooks
- ✅ Provider switching without code changes

## Breaking Changes

### 1. Class Namespace Changes

**Old:**
```php
use Multilingual_Bridge\DeepL\DeepL_Translator;
```

**New:**
```php
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Providers\DeepL_Provider;
```

### 2. Instantiation Pattern

**Old:**
```php
$api_key = get_option( 'deepl_api_key' );
$translator = new DeepL_Translator( $api_key );
```

**New:**
```php
// Translation_Manager is a singleton - providers are auto-registered
$manager = Translation_Manager::get_instance();
```

### 3. Method Signatures

**Old:**
```php
$translator->translate( 
    string $text, 
    string $target_language, 
    string $source_language = '' 
);
```

**New:**
```php
$manager->translate( 
    string $text, 
    string $target_language, 
    string $source_language = '', 
    string $provider_id = '' // NEW: optional provider selection
);
```

## Migration Steps

### Step 1: Update Imports

Replace all old DeepL_Translator imports:

```php
// Remove this
use Multilingual_Bridge\DeepL\DeepL_Translator;

// Add this
use Multilingual_Bridge\Translation\Translation_Manager;
```

### Step 2: Replace Instantiation

**Before:**
```php
class My_Custom_Class {
    private DeepL_Translator $translator;

    public function __construct() {
        $api_key = get_option( 'deepl_api_key' );
        $this->translator = new DeepL_Translator( $api_key );
    }
}
```

**After:**
```php
class My_Custom_Class {
    private Translation_Manager $manager;

    public function __construct() {
        $this->manager = Translation_Manager::get_instance();
    }
}
```

### Step 3: Update Method Calls

**Before:**
```php
$result = $translator->translate( $text, 'es', 'en' );

if ( is_wp_error( $result ) ) {
    // Handle error
}
```

**After:**
```php
// Same method signature, just different object
$result = $manager->translate( $text, 'es', 'en' );

if ( is_wp_error( $result ) ) {
    // Handle error
}

// NEW: Optionally specify provider
$result = $manager->translate( $text, 'es', 'en', 'deepl' );
```

### Step 4: Update Batch Translations

**Before:**
```php
$texts = array( 'Hello', 'World', 'Foo' );
$results = $translator->translate_batch( $texts, 'es' );
```

**After:**
```php
$texts = array( 'Hello', 'World', 'Foo' );
$results = $manager->translate_batch( $texts, 'es', '', 'deepl' );
```

### Step 5: Update Language Checks

**Before:**
```php
$is_supported = $translator->is_language_supported( 'es' );
```

**After:**
```php
$provider = $manager->get_provider( 'deepl' );

if ( ! is_wp_error( $provider ) ) {
    $is_supported = $provider->is_language_supported( 'es' );
}
```

## Common Patterns

### Pattern 1: Simple Translation

**Before:**
```php
function translate_post_title( $post_id, $target_language ) {
    $title = get_the_title( $post_id );
    
    $api_key = get_option( 'deepl_api_key' );
    $translator = new DeepL_Translator( $api_key );
    
    $translated = $translator->translate( $title, $target_language );
    
    return $translated;
}
```

**After:**
```php
function translate_post_title( $post_id, $target_language ) {
    $title = get_the_title( $post_id );
    
    $manager = Translation_Manager::get_instance();
    $translated = $manager->translate( $title, $target_language );
    
    return $translated;
}
```

### Pattern 2: Batch Translation with Error Handling

**Before:**
```php
function translate_meta_fields( $post_id, $target_language ) {
    $api_key = get_option( 'deepl_api_key' );
    $translator = new DeepL_Translator( $api_key );
    
    $fields = get_post_meta( $post_id );
    $texts = array_values( $fields );
    
    $results = $translator->translate_batch( $texts, $target_language );
    
    if ( isset( $results['error'] ) ) {
        return $results['error'];
    }
    
    return array_combine( array_keys( $fields ), $results );
}
```

**After:**
```php
function translate_meta_fields( $post_id, $target_language ) {
    $manager = Translation_Manager::get_instance();
    
    $fields = get_post_meta( $post_id );
    $texts = array_values( $fields );
    
    $results = $manager->translate_batch( $texts, $target_language );
    
    if ( isset( $results['error'] ) ) {
        return $results['error'];
    }
    
    return array_combine( array_keys( $fields ), $results );
}
```

### Pattern 3: REST API Endpoint

**Before:**
```php
function custom_translate_endpoint( WP_REST_Request $request ) {
    $api_key = get_option( 'deepl_api_key' );
    $translator = new DeepL_Translator( $api_key );
    
    $text = $request->get_param( 'text' );
    $target = $request->get_param( 'target_language' );
    
    $result = $translator->translate( $text, $target );
    
    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'translation_failed', $result->get_error_message() );
    }
    
    return array( 'translated_text' => $result );
}
```

**After:**
```php
function custom_translate_endpoint( WP_REST_Request $request ) {
    $manager = Translation_Manager::get_instance();
    
    $text = $request->get_param( 'text' );
    $target = $request->get_param( 'target_language' );
    $provider = $request->get_param( 'provider' ); // NEW: optional
    
    $result = $manager->translate( $text, $target, '', $provider );
    
    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'translation_failed', $result->get_error_message() );
    }
    
    return array( 'translated_text' => $result );
}
```

## JavaScript/REST API Changes

### Old API Call
```javascript
fetch('/wp-json/multilingual-bridge/v1/translate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Hello World',
        target_language: 'es'
    })
})
.then(res => res.json())
.then(data => console.log(data.translated_text));
```

### New API Call (Backward Compatible + New Features)
```javascript
// Still works the same way (uses DeepL by default)
fetch('/wp-json/multilingual-bridge/v1/translation/translate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Hello World',
        target_language: 'es'
    })
})
.then(res => res.json())
.then(data => console.log(data.translated_text));

// NEW: Specify provider
fetch('/wp-json/multilingual-bridge/v1/translation/translate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Hello World',
        target_language: 'es',
        provider: 'google-translate' // NEW
    })
})
.then(res => res.json())
.then(data => console.log(data.translated_text));

// NEW: Get available providers
fetch('/wp-json/multilingual-bridge/v1/translation/providers')
    .then(res => res.json())
    .then(providers => console.log(providers));
```

## Testing Your Migration

### 1. Unit Test Example

```php
class Translation_Migration_Test extends WP_UnitTestCase {

    public function test_manager_translates_like_old_translator() {
        $manager = Translation_Manager::get_instance();
        
        $text = 'Hello World';
        $target = 'es';
        
        $result = $manager->translate( $text, $target );
        
        $this->assertNotWPError( $result );
        $this->assertIsString( $result );
        $this->assertNotEmpty( $result );
    }
    
    public function test_provider_selection() {
        $manager = Translation_Manager::get_instance();
        
        // Should work with explicit provider
        $result = $manager->translate( 'Hello', 'es', '', 'deepl' );
        $this->assertNotWPError( $result );
        
        // Should error with non-existent provider
        $result = $manager->translate( 'Hello', 'es', '', 'fake-provider' );
        $this->assertWPError( $result );
    }
}
```

### 2. Manual Testing Checklist

- [ ] Existing translation functionality still works
- [ ] Error handling behaves the same way
- [ ] Language validation works correctly
- [ ] Batch translations process successfully
- [ ] REST API endpoints return expected results
- [ ] JavaScript calls complete without errors
- [ ] Provider switching works (if using multiple providers)

## Deprecation Timeline

- **v1.0.0** - Old `DeepL_Translator` class marked as deprecated (still works with E_USER_DEPRECATED notice)
- **v2.0.0** - `DeepL_Translator` class will be removed entirely

## Getting Help

If you encounter issues during migration:

1. Check the [Architecture Overview](./architecture-overview.md)
2. Review [Custom Providers Guide](./custom-providers.md)
3. See [Hooks Reference](./hooks-reference.md)
4. Open an issue on GitHub with the `migration` label

## Quick Reference

| Old (Deprecated) | New (Current) |
|------------------|---------------|
| `DeepL_Translator::__construct()` | `Translation_Manager::get_instance()` |
| `$translator->translate()` | `$manager->translate()` |
| `$translator->translate_batch()` | `$manager->translate_batch()` |
| `$translator->is_language_supported()` | `$provider->is_language_supported()` |
| `$translator->get_supported_languages()` | `$provider->get_supported_languages()` |
| N/A | `$manager->register_provider()` (new) |
| N/A | `$manager->get_all_providers()` (new) |
