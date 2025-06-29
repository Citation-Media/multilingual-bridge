<?php
/**
 * WPML Language Helper functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

/**
 * WPML Language Helper Functions
 *
 * Provides simplified static methods for common WPML language operations that are not
 * available out-of-the-box in WPML's API.
 *
 * @package Multilingual_Bridge\Helpers
 */
class WPML_Language_Helper {

	/**
	 * Get all active language codes configured in WPML
	 *
	 * @return array<int, string> Array of language codes (e.g., ['en', 'de', 'fr'])
	 */
	public static function get_active_language_codes(): array {
		$languages = self::get_available_languages();

		$language_codes = array();
		foreach ( $languages as $language ) {
			$language_codes[] = $language['language_code'];
		}

		return $language_codes;
	}

	/**
	 * Retrieves the available languages configured in WPML (WordPress Multilingual Plugin).
	 *
	 * In the absence of WPML or if no default language and active languages are set,
	 * a fallback language is returned.
	 * The method ensures that the default language, if configured, is placed at the
	 * beginning of the returned languages list.
	 *
	 * @return array<string, array{language_code: string, native_name?: string, translated_name?: string, country_flag_url?: string, url?: string}> An associative array of available languages, each containing
	 *               details such as 'language_code'. If WPML is not active, returns a fallback German language.
	 */
	public static function get_available_languages(): array {
		// Check cache first
		$cache_key = 'multilingual_bridge_available_languages';
		$cached_languages = wp_cache_get( $cache_key, 'multilingual_bridge' );
		
		if ( false !== $cached_languages ) {
			return $cached_languages;
		}

		// Get wpml default language
		$default_lang = apply_filters( 'wpml_default_language', null ) ?: '';

		// Get languages set up in wpml
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		// If wpml not installed or no languages configured, return empty array
		if ( empty( $default_lang ) || empty( $languages ) ) {
			return array();
		}

		// Sort languages to push the element with language_code equal to $default_lang to the start, while preserving keys
		uasort(
			$languages,
			function ( $a, $b ) use ( $default_lang ) {
				if ( $a['language_code'] === $default_lang ) {
					return -1;
				}
				if ( $b['language_code'] === $default_lang ) {
					return 1;
				}
				return 0;
			}
		);

		// Cache the sorted languages (cache for 1 hour)
		wp_cache_set( $cache_key, $languages, 'multilingual_bridge', 3600 );

		return $languages;
	}


	/**
	 * Get the default language code
	 *
	 * @return string Default language code or empty string if WPML not active
	 */
	public static function get_default_language(): string {
		$default_language = apply_filters( 'wpml_default_language', null );
		return ! empty( $default_language ) ? (string) $default_language : '';
	}

	/**
	 * Get the current active language code
	 *
	 * @return string Current language code or empty string if WPML not active
	 */
	public static function get_current_language(): string {
		$current_language = apply_filters( 'wpml_current_language', null );
		return ! empty( $current_language ) ? (string) $current_language : '';
	}

	/**
	 * Check if a language code is active in WPML
	 *
	 * @param string $language_code The language code to check.
	 * @return bool True if language is active, false otherwise
	 */
	public static function is_language_active( string $language_code ): bool {
		if ( empty( $language_code ) ) {
			return false;
		}

		$languages = self::get_available_languages();
		return isset( $languages[ $language_code ] );
	}

	/**
	 * Get language details by language code
	 *
	 * @param string $language_code The language code.
	 * @return array{code: string, native_name: string, translated_name: string, country_flag_url: string, url: string}|array{} Language details array or empty array if not found
	 */
	public static function get_language_details( string $language_code ): array {
		if ( empty( $language_code ) ) {
			return array();
		}

		$languages = self::get_available_languages();
		return isset( $languages[ $language_code ] ) ? $languages[ $language_code ] : array();
	}

	/**
	 * Get the native name of a language
	 *
	 * @param string $language_code The language code.
	 * @return string Native language name or empty string if not found
	 */
	public static function get_language_native_name( string $language_code ): string {
		$details = self::get_language_details( $language_code );
		return isset( $details['native_name'] ) ? $details['native_name'] : '';
	}

	/**
	 * Get the translated name of a language in the current language
	 *
	 * @param string $language_code The language code.
	 * @param string $display_language Optional. Language to display the name in. Defaults to current language.
	 * @return string Translated language name or empty string if not found
	 */
	public static function get_language_translated_name( string $language_code, string $display_language = '' ): string {
		if ( empty( $display_language ) ) {
			$details = self::get_language_details( $language_code );
			return isset( $details['translated_name'] ) ? $details['translated_name'] : '';
		}

		// Use WPML filter for specific display language
		$name = apply_filters( 'wpml_translated_language_name', '', $language_code, $display_language );
		return ! empty( $name ) ? $name : '';
	}

	/**
	 * Get language flag URL
	 *
	 * @param string $language_code The language code.
	 * @return string Flag URL or empty string if not found
	 */
	public static function get_language_flag_url( string $language_code ): string {
		$details = self::get_language_details( $language_code );
		return isset( $details['country_flag_url'] ) ? $details['country_flag_url'] : '';
	}

	/**
	 * Switch to a specific language context
	 *
	 * @param string $language_code The language code to switch to.
	 * @return string The previous language code for restoration
	 */
	public static function switch_language( string $language_code ): string {
		$current_language = self::get_current_language();

		if ( ! empty( $language_code ) && self::is_language_active( $language_code ) ) {
			do_action( 'wpml_switch_language', $language_code );
		}

		return $current_language;
	}

	/**
	 * Restore language context
	 *
	 * @param string $language_code The language code to restore.
	 * @return void
	 */
	public static function restore_language( string $language_code ): void {
		if ( ! empty( $language_code ) ) {
			do_action( 'wpml_switch_language', $language_code );
		}
	}

	/**
	 * Execute a callback in a specific language context
	 *
	 * @param string   $language_code The language code to switch to.
	 * @param callable $callback      The callback to execute.
	 * @return mixed The return value of the callback
	 */
	public static function in_language_context( string $language_code, callable $callback ) {
		$previous_language = self::switch_language( $language_code );

		try {
			$result = $callback();
		} finally {
			self::restore_language( $previous_language );
		}

		return $result;
	}
}
