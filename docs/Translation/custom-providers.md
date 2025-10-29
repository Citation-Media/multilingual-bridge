# Creating Custom Translation Providers

This guide shows how to create and register custom translation providers for the Multilingual Bridge plugin.

## Step 1: Implement the Interface

Create a class that implements `Translation_Provider_Interface`:

```php
<?php
namespace Multilingual_Bridge\Translation\Providers;

use Multilingual_Bridge\Translation\Translation_Provider_Interface;

class Google_Translate_Provider implements Translation_Provider_Interface {

    private string $api_key;
    private array $supported_languages;

    public function __construct( string $api_key ) {
        $this->api_key = $api_key;
        $this->supported_languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            // Add more languages...
        );
    }

    /**
     * Get unique provider ID
     */
    public function get_id(): string {
        return 'google-translate';
    }

    /**
     * Get human-readable provider name
     */
    public function get_name(): string {
        return 'Google Translate';
    }

    /**
     * Translate single text
     */
    public function translate( string $text, string $target_language, string $source_language = '' ) {
        if ( empty( $text ) ) {
            return new \WP_Error( 'empty_text', 'Text cannot be empty' );
        }

        if ( ! $this->is_language_supported( $target_language ) ) {
            return new \WP_Error( 'unsupported_language', 'Target language not supported' );
        }

        $url = 'https://translation.googleapis.com/language/translate/v2';
        
        $body = array(
            'q'      => $text,
            'target' => $target_language,
            'format' => 'text',
        );

        if ( ! empty( $source_language ) ) {
            $body['source'] = $source_language;
        }

        $response = wp_remote_post(
            add_query_arg( 'key', $this->api_key, $url ),
            array(
                'body'    => wp_json_encode( $body ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['data']['translations'][0]['translatedText'] ) ) {
            return new \WP_Error( 'translation_failed', 'Translation failed' );
        }

        return $body['data']['translations'][0]['translatedText'];
    }

    /**
     * Translate multiple texts in one request
     */
    public function translate_batch( array $texts, string $target_language, string $source_language = '' ): array {
        $url = 'https://translation.googleapis.com/language/translate/v2';
        
        $body = array(
            'q'      => $texts,
            'target' => $target_language,
            'format' => 'text',
        );

        if ( ! empty( $source_language ) ) {
            $body['source'] = $source_language;
        }

        $response = wp_remote_post(
            add_query_arg( 'key', $this->api_key, $url ),
            array(
                'body'    => wp_json_encode( $body ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response );
        }

        $result = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $result['data']['translations'] ) ) {
            return array( 'error' => new \WP_Error( 'batch_failed', 'Batch translation failed' ) );
        }

        $translations = array();
        foreach ( $result['data']['translations'] as $translation ) {
            $translations[] = $translation['translatedText'];
        }

        return $translations;
    }

    /**
     * Check if language is supported
     */
    public function is_language_supported( string $language_code ): bool {
        return isset( $this->supported_languages[ $language_code ] );
    }

    /**
     * Get all supported languages
     */
    public function get_supported_languages(): array {
        return $this->supported_languages;
    }
}
```

## Step 2: Register Your Provider

Register your custom provider using the `multilingual_bridge_translation_system_init` hook:

```php
add_action( 'multilingual_bridge_translation_system_init', function() {
    $api_key = get_option( 'google_translate_api_key' );
    
    if ( empty( $api_key ) ) {
        return; // Don't register if no API key
    }

    $provider = new \Multilingual_Bridge\Translation\Providers\Google_Translate_Provider( $api_key );
    
    $manager = \Multilingual_Bridge\Translation\Translation_Manager::get_instance();
    $manager->register_provider( $provider );
} );
```

## Step 3: Use Your Provider

### Via REST API

```javascript
// Get available providers
fetch('/wp-json/multilingual-bridge/v1/translation/providers')
    .then(res => res.json())
    .then(providers => console.log(providers));

// Translate using your custom provider
fetch('/wp-json/multilingual-bridge/v1/translation/translate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Hello World',
        target_language: 'es',
        provider: 'google-translate' // Your provider ID
    })
})
.then(res => res.json())
.then(data => console.log(data.translated_text));
```

### Via PHP

```php
use Multilingual_Bridge\Translation\Translation_Manager;

$manager = Translation_Manager::get_instance();

// Translate using custom provider
$result = $manager->translate(
    'Hello World',
    'es',
    '',
    'google-translate' // Your provider ID
);

if ( is_wp_error( $result ) ) {
    error_log( $result->get_error_message() );
} else {
    echo $result; // "Hola Mundo"
}
```

## Advanced Examples

### OpenAI GPT Translation Provider

```php
class OpenAI_Translation_Provider implements Translation_Provider_Interface {

    private string $api_key;

    public function __construct( string $api_key ) {
        $this->api_key = $api_key;
    }

    public function get_id(): string {
        return 'openai-gpt';
    }

    public function get_name(): string {
        return 'OpenAI GPT Translation';
    }

    public function translate( string $text, string $target_language, string $source_language = '' ) {
        $prompt = sprintf(
            'Translate the following text to %s. Only return the translation, nothing else:\n\n%s',
            $this->get_language_name( $target_language ),
            $text
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'model'    => 'gpt-4',
                    'messages' => array(
                        array(
                            'role'    => 'user',
                            'content' => $prompt,
                        ),
                    ),
                ) ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
            return new \WP_Error( 'openai_failed', 'OpenAI translation failed' );
        }

        return trim( $body['choices'][0]['message']['content'] );
    }

    // Implement other required methods...
}
```

## Best Practices

1. **Error Handling**: Always return `WP_Error` objects for failures
2. **Language Support**: Decide whether to validate languages client-side or let the API handle it:
   - **Option A (Recommended for most APIs)**: Return empty arrays from `get_supported_source_languages()` and `get_supported_target_languages()`, and return `true` from `supports_language_pair()`. Let the API return errors for unsupported languages. This is simpler and always up-to-date with the API's capabilities.
   - **Option B**: Maintain hardcoded language lists if your API requires client-side validation or if you want to provide language selection UI before API calls.
3. **Timeout Settings**: Set appropriate timeouts for API requests (30-60 seconds)
4. **API Key Security**: Store API keys securely using WordPress options or constants
5. **Rate Limiting**: Implement retry logic and respect API rate limits
6. **Batch Support**: Implement `translate_batch()` for efficiency when supported by the API
7. **Caching**: Consider caching translations to reduce API costs

### Note on DeepL Provider

The built-in DeepL provider does not hardcode language restrictions. It accepts all languages and relies on the DeepL API to return errors if a specific language pair is not supported. This approach:
- Reduces maintenance burden (no need to update language lists)
- Automatically supports new languages added by DeepL
- Simplifies the code
- Provides accurate error messages directly from the API

## Testing Your Provider

```php
// Test provider registration
$manager = Translation_Manager::get_instance();
$provider = $manager->get_provider( 'google-translate' );

if ( is_wp_error( $provider ) ) {
    echo 'Provider not registered';
} else {
    echo 'Provider registered: ' . $provider->get_name();
}

// Test language support
$is_supported = $provider->is_language_supported( 'es' );
echo $is_supported ? 'Spanish supported' : 'Spanish not supported';

// Test translation
$result = $provider->translate( 'Hello', 'es' );
if ( ! is_wp_error( $result ) ) {
    echo 'Translation: ' . $result;
}
```

## Related Documentation

- [Architecture Overview](./architecture-overview.md)
- [Hook Reference](./hooks-reference.md)
- [REST API Reference](../REST_API/translation-endpoints.md)
