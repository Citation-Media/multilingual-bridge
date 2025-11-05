<?php
/**
 * DeepL Translation Provider
 *
 * Implements Translation_Provider_Interface for DeepL API integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation\Providers;

use Multilingual_Bridge\Translation\Translation_Provider_Interface;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;

/**
 * Class DeepL_Provider
 *
 * DeepL translation service provider
 */
class DeepL_Provider implements Translation_Provider_Interface {

	/**
	 * API base URL for DeepL Free API
	 */
	private const FREE_API_BASE_URL = 'https://api-free.deepl.com/v2';

	/**
	 * API base URL for DeepL Premium API
	 */
	private const PREMIUM_API_BASE_URL = 'https://api.deepl.com/v2';

	/**
	 * Get provider ID
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'deepl';
	}

	/**
	 * Get provider display name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'DeepL', 'multilingual-bridge' );
	}

	/**
	 * Check if provider is available (has API key configured)
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return null !== $this->get_api_key();
	}

	/**
	 * Get supported languages for DeepL
	 *
	 * Returns all source and target languages supported by DeepL API.
	 * Includes both base language codes and regional variants.
	 *
	 * @see https://developers.deepl.com/docs/getting-started/supported-languages
	 * @return LanguageTag[] Array of supported language tag instances
	 */
	public function get_supported_languages(): array {
		// DeepL supported languages (includes source and target languages).
		return array(
			// Source & Target: Core languages.
			LanguageTag::fromString( 'ar' ),    // Arabic.
			LanguageTag::fromString( 'bg' ),    // Bulgarian.
			LanguageTag::fromString( 'cs' ),    // Czech.
			LanguageTag::fromString( 'da' ),    // Danish.
			LanguageTag::fromString( 'de' ),    // German.
			LanguageTag::fromString( 'el' ),    // Greek.
			LanguageTag::fromString( 'en' ),    // English (generic).
			LanguageTag::fromString( 'es' ),    // Spanish.
			LanguageTag::fromString( 'et' ),    // Estonian.
			LanguageTag::fromString( 'fi' ),    // Finnish.
			LanguageTag::fromString( 'fr' ),    // French.
			LanguageTag::fromString( 'he' ),    // Hebrew (next-gen only).
			LanguageTag::fromString( 'hu' ),    // Hungarian.
			LanguageTag::fromString( 'id' ),    // Indonesian.
			LanguageTag::fromString( 'it' ),    // Italian.
			LanguageTag::fromString( 'ja' ),    // Japanese.
			LanguageTag::fromString( 'ko' ),    // Korean.
			LanguageTag::fromString( 'lt' ),    // Lithuanian.
			LanguageTag::fromString( 'lv' ),    // Latvian.
			LanguageTag::fromString( 'nb' ),    // Norwegian Bokmål.
			LanguageTag::fromString( 'nl' ),    // Dutch.
			LanguageTag::fromString( 'pl' ),    // Polish.
			LanguageTag::fromString( 'pt' ),    // Portuguese (generic).
			LanguageTag::fromString( 'ro' ),    // Romanian.
			LanguageTag::fromString( 'ru' ),    // Russian.
			LanguageTag::fromString( 'sk' ),    // Slovak.
			LanguageTag::fromString( 'sl' ),    // Slovenian.
			LanguageTag::fromString( 'sv' ),    // Swedish.
			LanguageTag::fromString( 'th' ),    // Thai (next-gen only).
			LanguageTag::fromString( 'tr' ),    // Turkish.
			LanguageTag::fromString( 'uk' ),    // Ukrainian.
			LanguageTag::fromString( 'vi' ),    // Vietnamese (next-gen only).
			LanguageTag::fromString( 'zh' ),    // Chinese (generic).

			// Target only: Regional variants.
			LanguageTag::fromString( 'en-GB' ),    // English (British).
			LanguageTag::fromString( 'en-US' ),    // English (American).
			LanguageTag::fromString( 'es-419' ),   // Spanish (Latin American).
			LanguageTag::fromString( 'pt-BR' ),    // Portuguese (Brazilian).
			LanguageTag::fromString( 'pt-PT' ),    // Portuguese (European).
			LanguageTag::fromString( 'zh-Hans' ),  // Chinese (Simplified).
			LanguageTag::fromString( 'zh-Hant' ),  // Chinese (Traditional).
		);
	}

	/**
	 * Translate text using DeepL API
	 *
	 * @param LanguageTag      $target_lang Target language tag.
	 * @param string           $text        Text to translate.
	 * @param LanguageTag|null $source_lang Source language tag (optional, auto-detect if null).
	 * @return string|WP_Error Translated text or error
	 */
	public function translate( LanguageTag $target_lang, string $text, ?LanguageTag $source_lang = null ) {
		$api_key = $this->get_api_key();

		if ( ! $api_key ) {
			return new WP_Error(
				'deepl_api_key_missing',
				__( 'DeepL API key is not configured. Please add DEEPL_API_KEY to wp-config.php', 'multilingual-bridge' )
			);
		}

		if ( empty( $text ) ) {
			return '';
		}

		$api_url = $this->get_api_url() . '/translate';
		// Convert target language to DeepL format.
		$target_lang_code = strtoupper( $target_lang->toString() );

		// Prepare request data.
		$data = array(
			'text'        => array( $text ),
			'target_lang' => $target_lang_code,
		);

		if ( null !== $source_lang ) {
			$source_lang_code    = strtoupper( $source_lang->toString() );
			$data['source_lang'] = $source_lang_code;
		}

		/**
		 * Filter DeepL translation request data
		 *
		 * @param array<string, mixed> $data Request data
		 * @param string              $text Original text
		 */
		$data = apply_filters( 'multilingual_bridge_deepl_request_data', $data, $text );

		// Make API request.
		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'deepl_json_error',
				__( 'Invalid JSON response from DeepL API', 'multilingual-bridge' )
			);
		}

		if ( 200 !== $status_code ) {
			$message = $decoded['message'] ?? __( 'Unknown API error', 'multilingual-bridge' );
			return new WP_Error(
				'deepl_api_error',
				$message,
				array( 'status_code' => $status_code )
			);
		}

		if ( ! isset( $decoded['translations'][0]['text'] ) ) {
			return new WP_Error(
				'deepl_response_error',
				__( 'Invalid response structure from DeepL API', 'multilingual-bridge' )
			);
		}

		return $decoded['translations'][0]['text'];
	}

	/**
	 * Get DeepL API key from wp-config.php constant
	 *
	 * @return string|null
	 */
	private function get_api_key(): ?string {
		if ( defined( 'DEEPL_API_KEY' ) && ! empty( DEEPL_API_KEY ) ) {
			return DEEPL_API_KEY;
		}

		return null;
	}

	/**
	 * Get API base URL based on configuration
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		if ( defined( 'DEEPL_API_TYPE' ) && 'premium' === strtolower( DEEPL_API_TYPE ) ) {
			return self::PREMIUM_API_BASE_URL;
		}

		return self::FREE_API_BASE_URL;
	}
}
