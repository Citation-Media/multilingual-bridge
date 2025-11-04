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
	 * @return LanguageTag[] Array of supported language tag instances
	 */
	public function get_supported_languages(): array {
		// DeepL supports these languages (ISO 639-1 codes).
		return array(
			LanguageTag::fromString( 'bg' ),
			LanguageTag::fromString( 'cs' ),
			LanguageTag::fromString( 'da' ),
			LanguageTag::fromString( 'de' ),
			LanguageTag::fromString( 'el' ),
			LanguageTag::fromString( 'en' ),
			LanguageTag::fromString( 'es' ),
			LanguageTag::fromString( 'et' ),
			LanguageTag::fromString( 'fi' ),
			LanguageTag::fromString( 'fr' ),
			LanguageTag::fromString( 'hu' ),
			LanguageTag::fromString( 'id' ),
			LanguageTag::fromString( 'it' ),
			LanguageTag::fromString( 'ja' ),
			LanguageTag::fromString( 'ko' ),
			LanguageTag::fromString( 'lt' ),
			LanguageTag::fromString( 'lv' ),
			LanguageTag::fromString( 'nb' ),
			LanguageTag::fromString( 'nl' ),
			LanguageTag::fromString( 'pl' ),
			LanguageTag::fromString( 'pt' ),
			LanguageTag::fromString( 'ro' ),
			LanguageTag::fromString( 'ru' ),
			LanguageTag::fromString( 'sk' ),
			LanguageTag::fromString( 'sl' ),
			LanguageTag::fromString( 'sv' ),
			LanguageTag::fromString( 'tr' ),
			LanguageTag::fromString( 'uk' ),
			LanguageTag::fromString( 'zh' ),
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

		// Prepare request data.
		$data = array(
			'text'        => array( $text ),
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External library property.
			'target_lang' => strtoupper( $target_lang->primaryLanguageSubtag->value ),
		);

		if ( null !== $source_lang ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External library property.
			$data['source_lang'] = strtoupper( $source_lang->primaryLanguageSubtag->value );
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
