<?php
/**
 * DeepL Translator class for handling API interactions
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\DeepL;

use WP_Error;

/**
 * Class DeepL_Translator
 */
class DeepL_Translator {

	/**
	 * API base URL for DeepL Free API
	 */
	const FREE_API_BASE_URL = 'https://api-free.deepl.com/v2/translate';

	/**
	 * API base URL for DeepL Premium API
	 */
	const PREMIUM_API_BASE_URL = 'https://api.deepl.com/v2/translate';

	/**
	 * Get DeepL API key from wp-config.php constant
	 *
	 * @return string|null
	 */
	public static function get_api_key(): ?string {
		if ( defined( 'DEEPL_API_KEY' ) && ! empty( DEEPL_API_KEY ) ) {
			return DEEPL_API_KEY;
		}

		return null;
	}

	/**
	 * Checks if premium API should be used based on DEEPL_API_TYPE constant
	 * Expects 'premium' or 'free' (defaults to 'free' if not set)
	 *
	 * @return bool True if premium API should be used.
	 */
	public static function use_premium_api(): bool {
		if ( defined( 'DEEPL_API_TYPE' ) && 'premium' === strtolower( DEEPL_API_TYPE ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Translate text using DeepL API
	 *
	 * @param string $text       Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $source_lang Source language code (optional).
	 * @return string|WP_Error Translated text or error.
	 */
	public static function translate( string $text, string $target_lang, string $source_lang = '' ) {
		$api_key = self::get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'deepl_api_key_missing', 'Please provide a DeepL API credentials in the settings.' );
		}

		if ( empty( $text ) ) {
			return '';
		}

		// Determine API URL based on constant
		$api_url = self::use_premium_api() ? self::PREMIUM_API_BASE_URL : self::FREE_API_BASE_URL;

		// Prepare request data
		$data = array(
			'text'        => array( $text ),
			'target_lang' => strtoupper( $target_lang ),
		);

		if ( ! empty( $source_lang ) ) {
			$data['source_lang'] = strtoupper( $source_lang );
		}

		// Make API request
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

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'deepl_json_error', 'Invalid JSON response from DeepL API' );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
			return new WP_Error( 'deepl_api_error', $message, array( 'status_code' => $status_code ) );
		}

		if ( ! isset( $data['translations'][0]['text'] ) ) {
			return new WP_Error( 'deepl_response_error', 'Invalid response structure from DeepL API' );
		}

		return $data['translations'][0]['text'];
	}
}
