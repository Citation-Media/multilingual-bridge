<?php
/**
 * Language Code Helper
 *
 * Provides type-safe language code validation and conversion using PrinsFrank/standards library
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use PrinsFrank\Standards\Language\LanguageAlpha2;
use WP_Error;

/**
 * Language Code Helper Functions
 *
 * Provides methods for validating and converting language codes using type-safe enums
 *
 * @package Multilingual_Bridge\Helpers
 */
class Language_Code_Helper {

	/**
	 * Convert string language code to LanguageAlpha2 enum
	 *
	 * @param string $code Language code string (e.g., 'en', 'de', 'fr').
	 * @return LanguageAlpha2|WP_Error Language enum on success, WP_Error on failure
	 */
	public static function from_string( string $code ): LanguageAlpha2|WP_Error {
		if ( empty( $code ) ) {
			return new WP_Error(
				'invalid_language_code',
				__( 'Language code cannot be empty', 'multilingual-bridge' )
			);
		}

		// Normalize to lowercase.
		$code = strtolower( trim( $code ) );

		try {
			return LanguageAlpha2::from( $code );
		} catch ( \ValueError $e ) {
			return new WP_Error(
				'invalid_language_code',
				sprintf(
					/* translators: %s: Invalid language code */
					__( 'Invalid language code: %s', 'multilingual-bridge' ),
					$code
				)
			);
		}
	}

	/**
	 * Get all valid language codes as strings
	 *
	 * @return array<int, string> Array of valid language codes
	 */
	public static function get_all_codes(): array {
		return array_map(
			function ( LanguageAlpha2 $lang ) {
				return $lang->value;
			},
			LanguageAlpha2::cases()
		);
	}

	/**
	 * Validate language code string
	 *
	 * @param string $code Language code to validate.
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid( string $code ): bool {
		$result = self::from_string( $code );
		return ! is_wp_error( $result );
	}

	/**
	 * Get enum values for REST API schema
	 *
	 * @return array<int, string> Array of language codes for REST API enum
	 */
	public static function get_rest_enum(): array {
		return self::get_all_codes();
	}

	/**
	 * Validate language code for REST API
	 *
	 * @param string $value   Language code to validate.
	 * @param mixed  $request Request object (unused but required by WordPress REST API).
	 * @param string $param   Parameter name (unused but required by WordPress REST API).
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public static function validate_rest_param( string $value, $request, string $param ): bool|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $request and $param required by WordPress REST API callback signature.
		$result = self::from_string( $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Convert LanguageAlpha2 enum to string
	 *
	 * @param LanguageAlpha2 $language Language enum.
	 * @return string Language code as string
	 */
	public static function to_string( LanguageAlpha2 $language ): string {
		return $language->value;
	}
}
