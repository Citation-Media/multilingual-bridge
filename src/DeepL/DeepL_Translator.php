<?php
/**
 * DeepL Translator class for handling Free API interactions
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
	const API_BASE_URL = 'https://api-free.deepl.com/v2/translate';

	/**
	 * Get DeepL Free API key from wp-config
	 *
	 * @return string|null
	 */
	public static function get_api_key(): ?string {
		return defined( 'DEEPL_API_KEY' ) ? DEEPL_API_KEY : null;
	}

	/**
	 * Translate text using DeepL Free API
	 *
	 * @param string $text       Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $source_lang Source language code (optional).
	 * @return string|WP_Error Translated text or error.
	 */
	public static function translate( string $text, string $target_lang, string $source_lang = '' ) {
		$api_key = self::get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'deepl_api_key_missing', 'DeepL API key not configured' );
		}

		if ( empty( $text ) ) {
			return '';
		}

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
			self::API_BASE_URL,
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

	/**
	 * Check if DeepL Free API key is configured and valid
	 *
	 * @return bool|WP_Error True if valid, WP_Error if not.
	 */
	public static function validate_api_key() {
		$api_key = self::get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'deepl_api_key_missing', 'DeepL API key not configured in wp-config.php' );
		}

		// Test with a simple translation
		$result = self::translate( 'Hello', 'ES', 'EN' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
