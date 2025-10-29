# Translation System Hooks Reference

Complete reference of WordPress hooks (actions and filters) available in the translation system.

## Action Hooks

### `multilingual_bridge_translation_system_init`

Fires after the translation system has been initialized. Use this hook to register custom translation providers.

**Parameters:** None

**Example:**
```php
add_action( 'multilingual_bridge_translation_system_init', function() {
    $manager = \Multilingual_Bridge\Translation\Translation_Manager::get_instance();
    
    // Register custom provider
    $provider = new My_Custom_Provider( $api_key );
    $manager->register_provider( $provider );
} );
```

---

### `multilingual_bridge_before_translate`

Fires immediately before a translation request is processed.

**Parameters:**
- `string $text` - Text to be translated
- `string $target_language` - Target language code
- `string $source_language` - Source language code (may be empty)
- `string $provider_id` - Provider ID that will handle the translation

**Example:**
```php
add_action( 'multilingual_bridge_before_translate', function( $text, $target_language, $source_language, $provider_id ) {
    error_log( sprintf(
        'Translating "%s" to %s using %s',
        substr( $text, 0, 50 ),
        $target_language,
        $provider_id
    ) );
}, 10, 4 );
```

---

### `multilingual_bridge_after_translate`

Fires after a translation has been completed (successfully or with error).

**Parameters:**
- `mixed $result` - Translation result (string or WP_Error)
- `string $text` - Original text
- `string $target_language` - Target language code
- `string $provider_id` - Provider ID that handled the translation

**Example:**
```php
add_action( 'multilingual_bridge_after_translate', function( $result, $text, $target_language, $provider_id ) {
    if ( is_wp_error( $result ) ) {
        error_log( 'Translation failed: ' . $result->get_error_message() );
    } else {
        // Log successful translation to analytics
        do_action( 'log_translation_success', $provider_id, $target_language );
    }
}, 10, 4 );
```

---

### `multilingual_bridge_provider_registered`

Fires when a new translation provider is registered.

**Parameters:**
- `Translation_Provider_Interface $provider` - The registered provider instance

**Example:**
```php
add_action( 'multilingual_bridge_provider_registered', function( $provider ) {
    error_log( 'Provider registered: ' . $provider->get_name() );
} );
```

---

## Filter Hooks

### `multilingual_bridge_translation_result`

Filters the translation result before it is returned to the caller. Useful for post-processing translations.

**Parameters:**
- `mixed $result` - Translation result (string or WP_Error)
- `string $text` - Original text
- `string $target_language` - Target language code
- `string $provider_id` - Provider ID that handled the translation

**Return:** `mixed` - Modified translation result

**Example:**
```php
// Add custom markup to all translations
add_filter( 'multilingual_bridge_translation_result', function( $result, $text, $target_language, $provider_id ) {
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    // Add translation metadata as HTML comment
    $metadata = sprintf(
        '<!-- Translated by %s to %s -->',
        $provider_id,
        $target_language
    );
    
    return $result . $metadata;
}, 10, 4 );
```

---

### `multilingual_bridge_default_provider`

Filters the default translation provider ID used when no provider is specified.

**Parameters:**
- `string $provider_id` - Default provider ID (default: 'deepl')

**Return:** `string` - Modified provider ID

**Example:**
```php
// Use Google Translate as default instead of DeepL
add_filter( 'multilingual_bridge_default_provider', function( $provider_id ) {
    return 'google-translate';
} );
```

---

### `multilingual_bridge_field_translatable`

Filters whether a specific field type is translatable.

**Parameters:**
- `bool $is_translatable` - Whether field is translatable
- `string $field_type` - Field type identifier

**Return:** `bool` - Modified translatability status

**Example:**
```php
// Make 'password' fields non-translatable
add_filter( 'multilingual_bridge_field_translatable', function( $is_translatable, $field_type ) {
    if ( $field_type === 'password' ) {
        return false;
    }
    return $is_translatable;
}, 10, 2 );
```

---

### `multilingual_bridge_batch_translation_chunk_size`

Filters the number of texts processed in a single batch translation request.

**Parameters:**
- `int $chunk_size` - Chunk size (default: 50)
- `string $provider_id` - Provider ID handling the batch

**Return:** `int` - Modified chunk size

**Example:**
```php
// Reduce chunk size for custom provider to avoid rate limits
add_filter( 'multilingual_bridge_batch_translation_chunk_size', function( $chunk_size, $provider_id ) {
    if ( $provider_id === 'my-custom-provider' ) {
        return 10; // Smaller chunks
    }
    return $chunk_size;
}, 10, 2 );
```

---

### `multilingual_bridge_supported_languages`

Filters the list of supported languages for a specific provider.

**Parameters:**
- `array $languages` - Associative array of language codes and names
- `string $provider_id` - Provider ID

**Return:** `array` - Modified languages array

**Example:**
```php
// Add custom language variant
add_filter( 'multilingual_bridge_supported_languages', function( $languages, $provider_id ) {
    if ( $provider_id === 'deepl' ) {
        $languages['en-us'] = 'English (US)';
        $languages['en-gb'] = 'English (UK)';
    }
    return $languages;
}, 10, 2 );
```

---

### `multilingual_bridge_translation_request_args`

Filters the HTTP request arguments before making an API call to the translation service.

**Parameters:**
- `array $args` - `wp_remote_post()` arguments
- `string $provider_id` - Provider ID making the request
- `string $text` - Text being translated

**Return:** `array` - Modified request arguments

**Example:**
```php
// Increase timeout for large translations
add_filter( 'multilingual_bridge_translation_request_args', function( $args, $provider_id, $text ) {
    if ( strlen( $text ) > 10000 ) {
        $args['timeout'] = 120; // 2 minutes
    }
    return $args;
}, 10, 3 );
```

---

## Field Registry Hooks

### `multilingual_bridge_field_processor`

Filters the processor callback for a specific field type.

**Parameters:**
- `callable $processor` - Field processor callback
- `string $field_type` - Field type identifier

**Return:** `callable` - Modified processor callback

**Example:**
```php
add_filter( 'multilingual_bridge_field_processor', function( $processor, $field_type ) {
    if ( $field_type === 'custom_markdown' ) {
        return function( $value, $target_language ) {
            // Custom processing for markdown fields
            return translate_markdown( $value, $target_language );
        };
    }
    return $processor;
}, 10, 2 );
```

---

### `multilingual_bridge_integration_field_types`

Filters the field types registered for a specific integration.

**Parameters:**
- `array $field_types` - Array of field type identifiers
- `string $integration_name` - Integration name (e.g., 'acf', 'meta_box')

**Return:** `array` - Modified field types array

**Example:**
```php
add_filter( 'multilingual_bridge_integration_field_types', function( $field_types, $integration_name ) {
    if ( $integration_name === 'acf' ) {
        // Add support for ACF Blocks
        $field_types[] = 'block';
    }
    return $field_types;
}, 10, 2 );
```

---

## Usage Examples

### Logging All Translations

```php
// Log all translation attempts
add_action( 'multilingual_bridge_before_translate', function( $text, $target_language, $source_language, $provider_id ) {
    $log_entry = array(
        'timestamp'       => current_time( 'mysql' ),
        'text_length'     => strlen( $text ),
        'target_language' => $target_language,
        'provider'        => $provider_id,
    );
    
    update_option( 'translation_log', get_option( 'translation_log', array() ) + array( $log_entry ) );
}, 10, 4 );
```

### Fallback Provider

```php
// Automatically fallback to another provider if translation fails
add_filter( 'multilingual_bridge_translation_result', function( $result, $text, $target_language, $provider_id ) {
    if ( is_wp_error( $result ) && $provider_id === 'deepl' ) {
        $manager = \Multilingual_Bridge\Translation\Translation_Manager::get_instance();
        
        // Try Google Translate as fallback
        $fallback = $manager->translate( $text, $target_language, '', 'google-translate' );
        
        if ( ! is_wp_error( $fallback ) ) {
            return $fallback;
        }
    }
    
    return $result;
}, 10, 4 );
```

### Custom Field Type Registration

```php
// Register custom field type for a custom plugin
add_action( 'multilingual_bridge_translation_system_init', function() {
    $registry = \Multilingual_Bridge\Translation\Field_Registry::get_instance();
    
    $registry->register_field_type( 'my_custom_field', function( $value, $target_language ) {
        $manager = \Multilingual_Bridge\Translation\Translation_Manager::get_instance();
        return $manager->translate( $value, $target_language );
    } );
} );
```

## Related Documentation

- [Architecture Overview](./architecture-overview.md)
- [Creating Custom Providers](./custom-providers.md)
- [Field Registry](./field-registry.md)
