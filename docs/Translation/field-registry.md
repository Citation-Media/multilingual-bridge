# Field Registry

The Field Registry manages which field types are eligible for translation in the Multilingual Bridge plugin. It uses a simple approach: a list of supported field type strings.

## Default Supported Field Types

By default, the Field Registry supports these ACF field types:

- `text` - Single-line text fields
- `textarea` - Multi-line text areas
- `wysiwyg` - WYSIWYG/Rich text editors

## Architecture

The Field Registry is a **singleton** that maintains a simple array of field type strings. When a field is rendered in the WordPress admin, the system checks if the field type is in this list. If yes, translation UI (translate icon) is added.

### Simple Design Philosophy

The Field Registry intentionally uses a **simple array** instead of complex configuration objects:

```php
// This is the entire field types array:
array( 'text', 'textarea', 'wysiwyg' )
```

**Why?** Because all we need to know is: "Should this field type show translation icons?" The answer is yes or no - no additional metadata is needed.

## Adding Custom Field Types

### Method 1: Using the Filter (Recommended)

Add field types via the `multilingual_bridge_field_types` filter:

```php
add_filter( 'multilingual_bridge_field_types', function( $field_types ) {
    // Add your custom field types
    $field_types[] = 'my_custom_field';
    $field_types[] = 'another_field_type';
    
    return $field_types;
} );
```

### Method 2: Using the Registry API

Register field types programmatically:

```php
use Multilingual_Bridge\Translation\Field_Registry;

add_action( 'multilingual_bridge_translation_system_init', function() {
    $registry = Field_Registry::instance();
    
    // Add single field type
    $registry->register_field_type( 'my_custom_field' );
    
    // Returns false if already registered
    $success = $registry->register_field_type( 'text' ); // false - already exists
} );
```

## Removing Field Types

```php
use Multilingual_Bridge\Translation\Field_Registry;

$registry = Field_Registry::instance();

// Remove a field type
$registry->unregister_field_type( 'wysiwyg' );

// Or use filter to remove
add_filter( 'multilingual_bridge_field_types', function( $field_types ) {
    return array_diff( $field_types, array( 'wysiwyg' ) );
} );
```

## Checking Field Type Support

```php
use Multilingual_Bridge\Translation\Field_Registry;

$registry = Field_Registry::instance();

// Check if field type is supported
if ( $registry->is_field_type_registered( 'text' ) ) {
    echo 'Text fields can be translated';
}

// Get all registered field types
$field_types = $registry->get_field_types();
// Returns: array( 'text', 'textarea', 'wysiwyg' )
```

## How It Works

### 1. Field Rendering Phase

When ACF renders a field in the WordPress admin:

```php
// ACF_Translation::add_field_wrapper_attributes()
if ( ! $this->field_registry->is_field_type_registered( $field['type'] ) ) {
    return $wrapper; // No translation UI
}

// Add translation data attributes
$wrapper['class'] = 'multilingual-translatable-field';
$wrapper['data-field-key'] = $field['name'];
// ... more attributes
```

### 2. JavaScript Detection Phase

JavaScript scans for fields with the class:

```javascript
// translation.js
document.querySelectorAll('.multilingual-translatable-field').forEach(field => {
    // Add translation icons to this field
});
```

### 3. Translation Modal Phase

When user clicks translate icon, the modal uses the field type to determine behavior:

```javascript
// TranslationModal.js
const fieldType = field.dataset.fieldType; // 'text', 'textarea', 'wysiwyg'
// Render appropriate UI based on field type
```

## Field Type Restrictions

### Hiding Translation UI for Specific Fields

Even if a field type is registered, you can hide translation UI for specific fields:

```php
// Hide translation UI for specific field names
add_filter( 'multilingual_bridge_acf_show_translation_ui', function( $show, $field, $post_id ) {
    // Don't show for fields that shouldn't be translated
    $non_translatable_fields = array( 'internal_id', 'product_sku', 'date_added' );
    
    if ( in_array( $field['name'], $non_translatable_fields, true ) ) {
        return false;
    }
    
    return $show;
}, 10, 3 );
```

```php
// Hide based on WPML custom field settings
add_filter( 'multilingual_bridge_acf_show_translation_ui', function( $show, $field, $post_id ) {
    // Check WPML translation preference
    if ( isset( $field['wpml_cf_preferences'] ) && $field['wpml_cf_preferences'] === 0 ) {
        return false; // WPML says "don't translate"
    }
    
    return $show;
}, 10, 3 );
```

## Integration with Field Integrations

The Field Registry also manages **field integrations** (ACF, Meta Box, etc.):

```php
use Multilingual_Bridge\Translation\Field_Registry;

$registry = Field_Registry::instance();

// Register an integration
$registry->register_integration( 'my-plugin', function() {
    // Initialize your integration
    $integration = new My_Custom_Field_Integration();
    $integration->init();
} );

// Initialize all integrations
$registry->init_integrations();
```

### Built-in ACF Integration

The ACF integration is registered in `Multilingual_Bridge::init()`:

```php
$field_registry->register_integration( 'acf', function() {
    $acf_translation = new ACF_Translation();
    $acf_translation->register_hooks();
} );
```

## Hooks Reference

### Filters

**`multilingual_bridge_field_types`**
- **Parameters**: `array $field_types` - Array of field type strings
- **Returns**: `array` - Modified field types
- **When**: Applied when getting registered field types
- **Use**: Add or remove supported field types

**`multilingual_bridge_acf_show_translation_ui`**
- **Parameters**: 
  - `bool $show` - Whether to show UI (default: true)
  - `array $field` - ACF field configuration
  - `int $post_id` - Current post ID
- **Returns**: `bool` - Whether to show translation UI
- **When**: Before adding translation data attributes to field wrapper
- **Use**: Conditionally hide translation UI for specific fields

### Actions

**`multilingual_bridge_field_type_registered`**
- **Parameters**: `string $type` - Field type that was registered
- **When**: After a field type is successfully registered
- **Use**: React to new field type registrations

**`multilingual_bridge_integration_registered`**
- **Parameters**: 
  - `string $integration_id` - Integration ID
  - `callable $init_callback` - Initialization callback
- **When**: After an integration is registered
- **Use**: Track or modify registered integrations

**`multilingual_bridge_integrations_initialized`**
- **When**: After all integrations have been initialized
- **Use**: Perform actions after all field integrations are ready

## Examples

### Support for Meta Box Plugin

```php
add_action( 'multilingual_bridge_translation_system_init', function() {
    $registry = \Multilingual_Bridge\Translation\Field_Registry::instance();
    
    // Register Meta Box integration
    $registry->register_integration( 'meta-box', function() {
        // Add Meta Box field types
        $registry = \Multilingual_Bridge\Translation\Field_Registry::instance();
        $registry->register_field_type( 'text' ); // Already exists - safe
        $registry->register_field_type( 'textarea' );
        $registry->register_field_type( 'wysiwyg' );
        
        // Initialize Meta Box hooks
        // ... your Meta Box integration code
    } );
} );
```

### Conditional Field Type Support

```php
// Only allow translation for specific post types
add_filter( 'multilingual_bridge_field_types', function( $field_types ) {
    global $post;
    
    // Only support WYSIWYG on 'page' post type
    if ( $post && $post->post_type !== 'page' ) {
        $field_types = array_diff( $field_types, array( 'wysiwyg' ) );
    }
    
    return $field_types;
} );
```

### Debug Field Types

```php
// Log all registered field types
add_action( 'admin_init', function() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $registry = \Multilingual_Bridge\Translation\Field_Registry::instance();
        error_log( 'Registered field types: ' . implode( ', ', $registry->get_field_types() ) );
    }
} );
```

## Related Documentation

- [Architecture Overview](./architecture-overview.md)
- [ACF Translation](../Admin/acf-translation.md)
- [Custom Providers](./custom-providers.md)
