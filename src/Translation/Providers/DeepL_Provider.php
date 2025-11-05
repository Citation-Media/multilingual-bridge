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

		$api_url = $this->get_api_url() . '/translate';

		// Prepare request data.
		$data = array(
			'text'        => array( $text ),
			'target_lang' => strtoupper( $target_lang ),
		);

		if ( ! empty( $source_lang ) ) {
			$data['source_lang'] = strtoupper( $source_lang );
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
	 * Convert LanguageTag to DeepL-compatible language code
	 *
	 * ## Why This Conversion Is Necessary:
	 *
	 * DeepL API requires language codes in UPPERCASE format. This method converts
	 * BCP 47 LanguageTag objects to DeepL's expected format by uppercasing all components.
	 *
	 * ## DeepL API Format:
	 * - Primary language codes are UPPERCASE (e.g., 'EN', 'DE', 'FR', 'JA')
	 * - Script subtags are UPPERCASE and hyphen-separated (e.g., 'ZH-HANS', 'ZH-HANT')
	 * - Region subtags are UPPERCASE and hyphen-separated (e.g., 'EN-US', 'PT-BR')
	 *
	 * ## Conversion Flow Examples:
	 *
	 * **Chinese Simplified:**
	 * WPML: 'zh-hans' → BCP 47: 'zh-Hans' → DeepL: 'ZH-HANS'
	 *
	 * **Chinese Traditional:**
	 * WPML: 'zh-hant' → BCP 47: 'zh-Hant' → DeepL: 'ZH-HANT'
	 *
	 * **Brazilian Portuguese:**
	 * WPML: 'pt-br' → BCP 47: 'pt-BR' → DeepL: 'PT-BR'
	 *
	 * **American English:**
	 * WPML: 'en-us' → BCP 47: 'en-US' → DeepL: 'EN-US'
	 *
	 * **Japanese (simple case):**
	 * WPML: 'ja' → BCP 47: 'ja' → DeepL: 'JA'
	 *
	 * ## Related Architecture:
	 * - This is called from translate() method after receiving a LanguageTag object
	 * - The LanguageTag was created from normalized BCP 47 code (see Translation_API)
	 * - DeepL receives the final converted code in the API request
	 *
	 * @see https://developers.deepl.com/docs/getting-started/supported-languages DeepL supported languages
	 *
	 * @param LanguageTag $language_tag Language tag to convert (BCP 47 normalized).
	 * @return string DeepL API language code (e.g., 'ZH-HANS', 'ZH-HANT', 'PT-BR', 'EN-US').
	 */
	private function convert_to_deepl_language_code( LanguageTag $language_tag ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External library property.
		$primary_code = strtoupper( $language_tag->primaryLanguageSubtag->value );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External library property.
		$script_code = $language_tag->scriptSubtag ? $language_tag->scriptSubtag->value : null;
		$region_code = $language_tag->regionSubtag ? $language_tag->regionSubtag->value : null;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// If script subtag exists, combine primary + script (e.g., ZH-HANS, ZH-HANT).
		if ( $script_code ) {
			return $primary_code . '-' . strtoupper( $script_code );
		}

		// If region subtag exists, combine primary + region (e.g., EN-US, PT-BR).
		if ( $region_code ) {
			return $primary_code . '-' . strtoupper( $region_code );
		}

		// Otherwise, just return the primary language code (e.g., JA, FR, DE).
		return $primary_code;
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
