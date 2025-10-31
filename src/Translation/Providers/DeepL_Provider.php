<?php
/**
 * DeepL Translation Provider
 *
 * Implements Translation_Provider_Interface for DeepL API integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation\Providers;

use Multilingual_Bridge\Helpers\Language_Code_Helper;
use Multilingual_Bridge\Translation\Translation_Provider_Interface;
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
	 * Supported target languages by DeepL API
	 * Based on DeepL documentation: https://developers.deepl.com/docs/resources/supported-languages
	 *
	 * @var array<string>
	 */
	private const SUPPORTED_TARGET_LANGUAGES = array(
		'ar',    // Arabic.
		'bg',    // Bulgarian.
		'cs',    // Czech.
		'da',    // Danish.
		'de',    // German.
		'el',    // Greek.
		'en',    // English (unspecified variant for backward compatibility).
		'en-gb', // English (British).
		'en-us', // English (American).
		'es',    // Spanish.
		'et',    // Estonian.
		'fi',    // Finnish.
		'fr',    // French.
		'hu',    // Hungarian.
		'id',    // Indonesian.
		'it',    // Italian.
		'ja',    // Japanese.
		'ko',    // Korean.
		'lt',    // Lithuanian.
		'lv',    // Latvian.
		'nb',    // Norwegian (Bokmål).
		'nl',    // Dutch.
		'pl',    // Polish.
		'pt',    // Portuguese (unspecified variant for backward compatibility).
		'pt-br', // Portuguese (Brazilian).
		'pt-pt', // Portuguese (European).
		'ro',    // Romanian.
		'ru',    // Russian.
		'sk',    // Slovak.
		'sl',    // Slovenian.
		'sv',    // Swedish.
		'tr',    // Turkish.
		'uk',    // Ukrainian.
		'zh',    // Chinese (simplified).
	);

	/**
	 * Supported source languages by DeepL API
	 *
	 * @var array<string>
	 */
	private const SUPPORTED_SOURCE_LANGUAGES = array(
		'ar',    // Arabic.
		'bg',    // Bulgarian.
		'cs',    // Czech.
		'da',    // Danish.
		'de',    // German.
		'el',    // Greek.
		'en',    // English.
		'es',    // Spanish.
		'et',    // Estonian.
		'fi',    // Finnish.
		'fr',    // French.
		'hu',    // Hungarian.
		'id',    // Indonesian.
		'it',    // Italian.
		'ja',    // Japanese.
		'ko',    // Korean.
		'lt',    // Lithuanian.
		'lv',    // Latvian.
		'nb',    // Norwegian (Bokmål).
		'nl',    // Dutch.
		'pl',    // Polish.
		'pt',    // Portuguese.
		'ro',    // Romanian.
		'ru',    // Russian.
		'sk',    // Slovak.
		'sl',    // Slovenian.
		'sv',    // Swedish.
		'tr',    // Turkish.
		'uk',    // Ukrainian.
		'zh',    // Chinese.
	);

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
	 * Get supported target languages
	 *
	 * @return array<string> Array of supported language codes
	 */
	public function get_supported_target_languages(): array {
		return self::SUPPORTED_TARGET_LANGUAGES;
	}

	/**
	 * Get supported source languages
	 *
	 * @return array<string> Array of supported language codes
	 */
	public function get_supported_source_languages(): array {
		return self::SUPPORTED_SOURCE_LANGUAGES;
	}

	/**
	 * Check if a target language is supported
	 *
	 * @param string $language_code Language code to check.
	 * @return bool True if supported, false otherwise
	 */
	public function is_target_language_supported( string $language_code ): bool {
		$normalized = Language_Code_Helper::normalize( $language_code );
		return in_array( $normalized, self::SUPPORTED_TARGET_LANGUAGES, true );
	}

	/**
	 * Check if a source language is supported
	 *
	 * @param string $language_code Language code to check.
	 * @return bool True if supported, false otherwise
	 */
	public function is_source_language_supported( string $language_code ): bool {
		$normalized = Language_Code_Helper::normalize( $language_code );
		return in_array( $normalized, self::SUPPORTED_SOURCE_LANGUAGES, true );
	}

	/**
	 * Translate text using DeepL API
	 *
	 * @param string $text        Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $source_lang Source language code (optional).
	 * @return string|WP_Error Translated text or error
	 */
	public function translate( string $text, string $target_lang, string $source_lang = '' ) {
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

		// Validate target language.
		if ( ! $this->is_target_language_supported( $target_lang ) ) {
			return new WP_Error(
				'deepl_unsupported_target_language',
				sprintf(
					/* translators: %s: Language code */
					__( 'Target language "%s" is not supported by DeepL', 'multilingual-bridge' ),
					$target_lang
				)
			);
		}

		// Validate source language if provided.
		if ( ! empty( $source_lang ) && ! $this->is_source_language_supported( $source_lang ) ) {
			return new WP_Error(
				'deepl_unsupported_source_language',
				sprintf(
					/* translators: %s: Language code */
					__( 'Source language "%s" is not supported by DeepL', 'multilingual-bridge' ),
					$source_lang
				)
			);
		}

		$api_url = $this->get_api_url() . '/translate';

		// Prepare request data with normalized and uppercase language codes for DeepL.
		$deepl_target = Language_Code_Helper::to_deepl_format( $target_lang );
		$data         = array(
			'text'        => array( $text ),
			'target_lang' => $deepl_target,
		);

		if ( ! empty( $source_lang ) ) {
			$deepl_source        = Language_Code_Helper::to_deepl_format( $source_lang );
			$data['source_lang'] = $deepl_source;
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
