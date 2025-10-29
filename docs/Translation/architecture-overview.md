# Translation Architecture Overview

The Multilingual Bridge plugin uses a provider-agnostic translation system that allows you to integrate multiple translation services (DeepL, Google Translate, OpenAI, etc.) through a common interface.

## Core Components

### 1. Translation Provider Interface
**File:** `src/Translation/Translation_Provider_Interface.php`

Defines the contract that all translation providers must implement:

```php
interface Translation_Provider_Interface {
    public function get_id(): string;
    public function get_name(): string;
    public function translate( string $text, string $target_language, string $source_language = '' );
    public function translate_batch( array $texts, string $target_language, string $source_language = '' ): array;
    public function is_language_supported( string $language_code ): bool;
    public function get_supported_languages(): array;
}
```

### 2. Translation Manager
**File:** `src/Translation/Translation_Manager.php`

Singleton class that:
- Registers and manages translation providers
- Routes translation requests to the appropriate provider
- Provides filter hooks for customization
- Handles provider validation and error checking

**Key Methods:**
- `register_provider( Translation_Provider_Interface $provider )` - Register a new provider
- `get_provider( string $provider_id )` - Retrieve a registered provider
- `get_all_providers()` - Get all registered providers
- `translate()` - Translate text using a specific or default provider
- `translate_batch()` - Translate multiple texts efficiently

### 3. Field Registry
**File:** `src/Translation/Field_Registry.php`

Manages translatable field types and integrations:
- Registers field types (text, textarea, wysiwyg, etc.)
- Associates integrations (ACF, Meta Box, etc.) with field processors
- Provides extensible field handling system

**Key Methods:**
- `register_field_type( string $type, callable $processor )` - Register custom field type
- `register_integration( string $integration_name, array $field_types )` - Register integration field mapping
- `is_field_translatable( string $type )` - Check if field type is translatable
- `process_field( string $type, mixed $value, string $target_language )` - Process field for translation

### 4. DeepL Provider (Default)
**File:** `src/Translation/Providers/DeepL_Provider.php`

Default implementation using DeepL API:
- Supports 29 languages
- Handles single and batch translations
- Manages API authentication and error handling
- Implements retry logic for failed requests

## Architecture Flow

```
User Request
     ↓
REST API Endpoint (/translate)
     ↓
Translation_Manager::translate()
     ↓
Provider Selection (DeepL, Custom, etc.)
     ↓
Translation_Provider_Interface::translate()
     ↓
External API (DeepL, Google, etc.)
     ↓
Filter: multilingual_bridge_translation_result
     ↓
Return Translated Text
```

## Integration Points

### WordPress Hooks

**Action Hooks:**
- `multilingual_bridge_translation_system_init` - Fires after translation system initialization, register custom providers here
- `multilingual_bridge_before_translate` - Fires before translation request
- `multilingual_bridge_after_translate` - Fires after translation completes

**Filter Hooks:**
- `multilingual_bridge_translation_result` - Modify translation result before returning
- `multilingual_bridge_default_provider` - Change default provider ID
- `multilingual_bridge_field_translatable` - Filter whether a field type is translatable

### REST API Endpoints

**GET** `/wp-json/multilingual-bridge/v1/translation/providers`
- Returns list of all registered translation providers
- Response includes: `id`, `name`, `supported_languages`

**POST** `/wp-json/multilingual-bridge/v1/translation/translate`
- Translates text using specified or default provider
- Parameters:
  - `text` (required) - Text to translate
  - `target_language` (required) - Target language code
  - `source_language` (optional) - Source language code
  - `provider` (optional) - Provider ID to use (defaults to 'deepl')

## Benefits

✅ **Pluggable Architecture** - Easily add new translation services without modifying core code  
✅ **Provider Abstraction** - Switch between providers seamlessly  
✅ **Extensible Field System** - Register custom field types and integrations  
✅ **Centralized Management** - Single point for all translation operations  
✅ **WordPress Integration** - Hooks and filters for customization  
✅ **Type Safety** - Full PHPStan level 6 compliance  

## Next Steps

- [Creating Custom Providers](./custom-providers.md)
- [Hook Reference](./hooks-reference.md)
- [Migration Guide](./migration-guide.md)
