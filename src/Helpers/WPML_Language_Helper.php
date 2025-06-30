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
	 * IMPORTANT: This method uses direct database queries instead of WPML's API functions
	 * because WPML does not correctly purge its internal caches when switching blog context
	 * in multisite environments. When using switch_to_blog(), WPML's cached language data
	 * from the previous blog persists, returning incorrect language configurations.
	 * Direct database queries ensure each blog's language configuration is correctly retrieved.
	 *
	 * The method ensures that the default language, if configured, is placed at the
	 * beginning of the returned languages list.
	 *
	 * @return array<string, array{language_code: string, name?: string, id?: int, default_locale?: string, tag?: string}> An associative array of available languages, each containing
	 *               details such as 'language_code'. If WPML is not active, returns an empty array.
	 */
	public static function get_available_languages(): array {
		// Check cache first
		$cache_key        = 'multilingual_bridge_available_languages';
		$cached_languages = wp_cache_get( $cache_key, 'multilingual_bridge' );

		if ( false !== $cached_languages && is_array( $cached_languages ) ) {
			/**
		 * Type cast the cached languages array.
		 *
		 * @var array<string, array{language_code: string, name?: string, id?: int, default_locale?: string, tag?: string}> $cached_languages
		 */
			return $cached_languages;
		}

		/**
		 * WordPress database abstraction object.
		 *
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		// Get the table name with proper prefix (handles multisite automatically)
		$table_name = $wpdb->prefix . 'icl_languages';

		// Check if the table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( null === $table_exists ) {
			return array();
		}

		// Query active languages from the database
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				'SELECT id, code, english_name, active, default_locale, tag, country
				FROM %i
				WHERE active = %d
				ORDER BY english_name',
				$table_name,
				1
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		// Get wpml default language
		$default_lang     = self::get_default_language();

		// Transform database results to match WPML's expected structure
		/**
		 * Languages array to build.
		 *
		 * @var array<string, array{language_code: string, name?: string, id?: int, default_locale?: string, tag?: string}> $languages
		 */
		$languages = array();
		/**
		 * Database query results.
		 *
		 * @var array<int, object{id: string|int, code: string, english_name: string, active: string|int, default_locale: string, tag: string, country: string}> $results
		 */
		foreach ( $results as $row ) {
			$language_code = (string) $row->code;

			// Build the expected array structure
			$languages[ $language_code ] = array(
				'id'             => (int) $row->id,
				'default_locale' => (string) $row->default_locale,
				'name'           => (string) $row->english_name,
				'language_code'  => $language_code,
				'tag'            => (string) $row->tag,
			);
		}

		// If no default language set, use the first one
		if ( empty( $default_lang ) && ! empty( $languages ) ) {
			$default_lang = array_key_first( $languages );
		}

		// Sort languages to push the default language to the start
		if ( ! empty( $default_lang ) ) {
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
		}

		// Cache the sorted languages (cache for 1 hour)
		wp_cache_set( $cache_key, $languages, 'multilingual_bridge', 3600 );

		return $languages;
	}

	/**
	 * Get the default language code
	 *
	 * This method uses the wpml_default_language filter which correctly reads from
	 * the blog-specific options table, ensuring accurate results in multisite environments.
	 *
	 * @return string Default language code or empty string if WPML not active
	 */
	public static function get_default_language(): string {
		$default_language = apply_filters( 'wpml_default_language', null );
		return is_string( $default_language ) && ! empty( $default_language ) ? $default_language : '';
	}

	/**
	 * Get the current active language code
	 *
	 * @return string Current language code or empty string if WPML not active
	 */
	public static function get_current_language(): string {
		$current_language = apply_filters( 'wpml_current_language', null );
		return is_string( $current_language ) && ! empty( $current_language ) ? $current_language : '';
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
	 * @return array{language_code: string, name?: string, id?: int, default_locale?: string, tag?: string}|array{} Language details array or empty array if not found
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
		return isset( $details['name'] ) ? $details['name'] : '';
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
	public static function in_language_context( string $language_code, callable $callback ): mixed {
		$previous_language = self::switch_language( $language_code );

		try {
			$result = $callback();
		} finally {
			self::restore_language( $previous_language );
		}

		return $result;
	}
}
