<?php
/**
 * WPML Term Helper functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use WP_Term;

/**
 * WPML Term Helper Functions
 *
 * Provides simplified static methods for common WPML term/taxonomy operations that are not
 * available out-of-the-box in WPML's API.
 *
 * @package Multilingual_Bridge\Helpers
 */
class WPML_Term_Helper {

	/**
	 * Extract term ID and taxonomy from a term parameter
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return array{term_id: int, term_taxonomy_id: int, taxonomy: string}|null Array with term_id, term_taxonomy_id and taxonomy or null if invalid
	 */
	private static function extract_term_data( int|WP_Term $term, string $taxonomy = '' ): ?array {
		if ( $term instanceof WP_Term ) {
			$term_id          = $term->term_id;
			$term_taxonomy_id = $term->term_taxonomy_id;
			$taxonomy         = $term->taxonomy;
		} else {
			$term_id = (int) $term;
			if ( empty( $taxonomy ) ) {
				$term_obj = get_term( $term_id );
				if ( ! $term_obj || is_wp_error( $term_obj ) ) {
					return null;
				}
				$term_taxonomy_id = $term_obj->term_taxonomy_id;
				$taxonomy         = $term_obj->taxonomy;
			} else {
				// We need to get the full term object to get term_taxonomy_id
				$term_obj = get_term( $term_id, $taxonomy );
				if ( ! $term_obj || is_wp_error( $term_obj ) ) {
					return null;
				}
				$term_taxonomy_id = $term_obj->term_taxonomy_id;
			}
		}

		if ( ! $term_id || empty( $taxonomy ) ) {
			return null;
		}

		return array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $term_taxonomy_id,
			'taxonomy'         => $taxonomy,
		);
	}

	/**
	 * Get the language code of a term
	 *
	 * @param int|WP_Term $term Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return string Language code (e.g., 'en', 'de') or empty string if not found
	 */
	public static function get_language( int|WP_Term $term, string $taxonomy = '' ): string {
		$term_data = self::extract_term_data( $term, $taxonomy );
		if ( null === $term_data ) {
			return '';
		}

		$term_language_details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $term_data['term_taxonomy_id'],
				'element_type' => $term_data['taxonomy'],
			)
		);

		if ( empty( $term_language_details ) || ! is_object( $term_language_details ) || ! isset( $term_language_details->language_code ) ) {
			return '';
		}

		return (string) $term_language_details->language_code;
	}

	/**
	 * Get all language versions of a term
	 *
	 * @param int|WP_Term $term           Term ID or WP_Term object.
	 * @param string      $taxonomy       Optional. Taxonomy name. Required if $term is an ID.
	 * @param bool        $return_objects Whether to return WP_Term objects instead of IDs.
	 * @return array<string, int|WP_Term> Array with language code as key and term ID or WP_Term object as value
	 */
	public static function get_language_versions( int|WP_Term $term, string $taxonomy = '', bool $return_objects = false ): array {
		$term_data = self::extract_term_data( $term, $taxonomy );
		if ( null === $term_data ) {
			return array();
		}

		$term_trid = apply_filters( 'wpml_element_trid', null, $term_data['term_taxonomy_id'], 'tax_' . $term_data['taxonomy'] );

		// Get all translations of the current term
		$translations = apply_filters( 'wpml_get_element_translations', null, $term_trid, 'tax_' . $term_data['taxonomy'] );

		if ( empty( $translations ) || ! is_array( $translations ) ) {
			return array();
		}

		// Sort translations so original language is first
		usort(
			$translations,
			function ( $a, $b ) {
				return $b->original <=> $a->original;
			}
		);

		$language_versions = array();
		foreach ( $translations as $translation ) {
			// Skip invalid objects
			if ( ! is_object( $translation ) ||
				! property_exists( $translation, 'element_id' ) ||
				! property_exists( $translation, 'language_code' ) ) {
				continue;
			}

			// Ensure types are correct
			$language_code    = (string) $translation->language_code;
			$term_taxonomy_id = (int) $translation->element_id;

			// Convert term_taxonomy_id to term_id
			// We need to get the term by term_taxonomy_id
			$cache_key = 'wpml_term_id_' . $term_taxonomy_id;
			$term_id   = wp_cache_get( $cache_key, 'multilingual_bridge' );

			if ( false === $term_id ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $term_taxonomy_id ) );
				if ( $term_id ) {
					wp_cache_set( $cache_key, $term_id, 'multilingual_bridge', 3600 );
				}
			}

			if ( ! $term_id ) {
				continue;
			}

			if ( $return_objects ) {
				$term_object = get_term( $term_id, $taxonomy );
				if ( $term_object instanceof WP_Term ) {
					$language_versions[ $language_code ] = $term_object;
				}
			} else {
				$language_versions[ $language_code ] = (int) $term_id;
			}
		}

		return $language_versions;
	}

	/**
	 * Get translation status for all active languages
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return array<string, bool> Array with language code as key and boolean (true if translation exists) as value
	 */
	public static function get_translation_status( int|WP_Term $term, string $taxonomy = '' ): array {
		$term_data = self::extract_term_data( $term, $taxonomy );
		if ( null === $term_data ) {
			return array();
		}

		// Get all active languages
		$active_languages = WPML_Language_Helper::get_available_languages();
		if ( empty( $active_languages ) ) {
			return array();
		}

		// Get existing translations
		$translations = self::get_language_versions( $term, $taxonomy );

		$status = array();
		foreach ( $active_languages as $language_code => $language ) {
			$status[ $language_code ] = isset( $translations[ $language_code ] );
		}

		return $status;
	}

	/**
	 * Check if a term has translations in all active languages
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return bool True if translations exist for all active languages
	 */
	public static function has_all_translations( int|WP_Term $term, string $taxonomy = '' ): bool {
		$translation_status = self::get_translation_status( $term, $taxonomy );

		if ( empty( $translation_status ) ) {
			return false;
		}

		// Check if any language is missing a translation
		return ! in_array( false, $translation_status, true );
	}

	/**
	 * Get list of languages without translations for a term
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return array<int, string> Array of language codes that don't have translations
	 */
	public static function get_missing_translations( int|WP_Term $term, string $taxonomy = '' ): array {
		$translation_status = self::get_translation_status( $term, $taxonomy );

		$missing = array();
		foreach ( $translation_status as $language_code => $exists ) {
			if ( ! $exists ) {
				$missing[] = $language_code;
			}
		}

		return $missing;
	}

	/**
	 * Check if a term is in a language that is not configured/active in WPML
	 *
	 * This is useful for finding terms that were created in a language that has since
	 * been deactivated or removed from WPML configuration.
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return bool True if term is in an unconfigured language, false otherwise.
	 */
	public static function is_term_in_unconfigured_language( int|WP_Term $term, string $taxonomy = '' ): bool {
		// Get the term's language
		$term_language = self::get_language( $term, $taxonomy );

		// If no language is set, consider it unconfigured
		if ( empty( $term_language ) ) {
			return true;
		}

		// Get all active language codes
		$active_language_codes = WPML_Language_Helper::get_active_language_codes();

		// If no active languages, something is wrong with WPML
		if ( empty( $active_language_codes ) ) {
			return false;
		}

		// Check if term language is not in active languages
		return ! in_array( $term_language, $active_language_codes, true );
	}

	/**
	 * Set or update a term's language assignment in WPML
	 *
	 * This method assigns a term to a specific language in WPML. It can be used to:
	 * - Fix terms with no language assignment
	 * - Change a term's language
	 * - Reassign terms from deactivated languages
	 *
	 * @param int|WP_Term $term            Term ID or WP_Term object.
	 * @param string      $taxonomy        Optional. Taxonomy name. Required if $term is an ID.
	 * @param string      $target_language The target language code to assign.
	 * @return bool True if language was set successfully, false otherwise.
	 */
	public static function set_language( int|WP_Term $term, string $taxonomy, string $target_language ): bool {
		$term_data = self::extract_term_data( $term, $taxonomy );
		if ( null === $term_data || empty( $target_language ) ) {
			return false;
		}

		// Verify taxonomy exists
		if ( ! taxonomy_exists( $term_data['taxonomy'] ) ) {
			return false;
		}

		// Get current language details using WPML filter
		$language_details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $term_data['term_taxonomy_id'],
				'element_type' => $term_data['taxonomy'],
			)
		);

		// Create a new translation group or use existing one
		$trid = ! empty( $language_details->trid ) ? $language_details->trid : apply_filters( 'wpml_element_trid', null, $term_data['term_taxonomy_id'], 'tax_' . $term_data['taxonomy'] );

		// Set the new language for the term
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $term_data['term_taxonomy_id'],
				'element_type'         => 'tax_' . $term_data['taxonomy'],
				'trid'                 => $trid,
				'language_code'        => $target_language,
				'source_language_code' => null, // Make it an original term
			)
		);

		// Clear WPML cache for this term
		do_action( 'wpml_cache_clear' );

		return true;
	}

	/**
	 * Get the translation ID for a term in a specific language
	 *
	 * @param int|WP_Term $term            Term ID or WP_Term object.
	 * @param string      $taxonomy        Optional. Taxonomy name. Required if $term is an ID.
	 * @param string      $target_language The target language code.
	 * @return int|null Term ID in the target language or null if not found
	 */
	public static function get_translation_id( int|WP_Term $term, string $taxonomy, string $target_language ): ?int {
		if ( empty( $target_language ) ) {
			return null;
		}

		$translations = self::get_language_versions( $term, $taxonomy );

		if ( isset( $translations[ $target_language ] ) ) {
			return $translations[ $target_language ];
		}

		return null;
	}

	/**
	 * Get the original term ID from any translation
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return int|null Original term ID or null if not found
	 */
	public static function get_original_term_id( int|WP_Term $term, string $taxonomy = '' ): ?int {
		$term_data = self::extract_term_data( $term, $taxonomy );
		if ( null === $term_data ) {
			return null;
		}

		$term_trid = apply_filters( 'wpml_element_trid', null, $term_data['term_taxonomy_id'], 'tax_' . $term_data['taxonomy'] );

		// Get all translations
		$translations = apply_filters( 'wpml_get_element_translations', null, $term_trid, 'tax_' . $term_data['taxonomy'] );

		if ( empty( $translations ) || ! is_array( $translations ) ) {
			return null;
		}

		// Find the original
		foreach ( $translations as $translation ) {
			if ( is_object( $translation ) &&
				property_exists( $translation, 'original' ) &&
				property_exists( $translation, 'element_id' ) &&
				$translation->original ) {
				// Convert term_taxonomy_id to term_id
				$term_taxonomy_id = (int) $translation->element_id;
				$cache_key        = 'wpml_term_id_' . $term_taxonomy_id;
				$term_id          = wp_cache_get( $cache_key, 'multilingual_bridge' );

				if ( false === $term_id ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $term_taxonomy_id ) );
					if ( $term_id ) {
						wp_cache_set( $cache_key, $term_id, 'multilingual_bridge', 3600 );
					}
				}

				return $term_id ? (int) $term_id : null;
			}
		}

		return null;
	}

	/**
	 * Check if a term is the original (not a translation)
	 *
	 * @param int|WP_Term $term     Term ID or WP_Term object.
	 * @param string      $taxonomy Optional. Taxonomy name. Required if $term is an ID.
	 * @return bool True if term is the original, false if it's a translation
	 */
	public static function is_original_term( int|WP_Term $term, string $taxonomy = '' ): bool {
		$original_id = self::get_original_term_id( $term, $taxonomy );

		if ( null === $original_id ) {
			return false;
		}

		$term_id = is_object( $term ) ? $term->term_id : (int) $term;

		return $original_id === $term_id;
	}
}
