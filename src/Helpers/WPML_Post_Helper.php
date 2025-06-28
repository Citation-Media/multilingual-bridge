<?php
/**
 * WPML Post Helper functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use WP_Post;

/**
 * WPML Post Helper Functions
 *
 * Provides simplified static methods for common WPML post operations that are not
 * available out-of-the-box in WPML's API.
 *
 * @package Multilingual_Bridge\Helpers
 */
class WPML_Post_Helper {

	/**
	 * Get the language code of a post
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object..
	 * @return string Language code (e.g., 'en', 'de') or empty string if not found
	 */
	public static function get_language( int|WP_Post $post ): string {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return '';
		}

		$post_language_details = apply_filters( 'wpml_post_language_details', null, $post_id );

		if ( empty( $post_language_details ) || ! is_array( $post_language_details ) || ! isset( $post_language_details['language_code'] ) ) {
			return '';
		}

		return (string) $post_language_details['language_code'];
	}

	/**
	 * Get all language versions of a post
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object..
	 * @param bool        $return_objects Whether to return WP_Post objects instead of IDs.
	 * @return array<string, int|WP_Post> Array with language code as key and post ID or WP_Post object as value
	 */
	public static function get_language_versions( int|WP_Post $post, bool $return_objects = false ): array {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return array();
		}

		$post_trid = apply_filters( 'wpml_element_trid', null, $post_id );

		// Get all translations of the current post
		$translations = apply_filters( 'wpml_get_element_translations', null, $post_trid );

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
			$language_code  = (string) $translation->language_code;
			$translation_id = (int) $translation->element_id;

			if ( $return_objects ) {
				$post_object = get_post( $translation_id );
				if ( $post_object instanceof WP_Post ) {
					$language_versions[ $language_code ] = $post_object;
				}
			} else {
				$language_versions[ $language_code ] = $translation_id;
			}
		}

		return $language_versions;
	}

	/**
	 * Get translation status for all active languages
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return array<string, bool> Array with language code as key and boolean (true if translation exists) as value
	 */
	public static function get_translation_status( int|WP_Post $post ): array {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return array();
		}

		// Get all active languages
		$active_languages = apply_filters( 'wpml_active_languages', null );
		if ( empty( $active_languages ) ) {
			return array();
		}

		// Get existing translations
		$translations = self::get_language_versions( $post_id );

		$status = array();
		foreach ( $active_languages as $language ) {
			$language_code            = $language['code'];
			$status[ $language_code ] = isset( $translations[ $language_code ] );
		}

		return $status;
	}

	/**
	 * Check if a post has translations in all active languages
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return bool True if translations exist for all active languages
	 */
	public static function has_all_translations( int|WP_Post $post ): bool {
		$translation_status = self::get_translation_status( $post );

		if ( empty( $translation_status ) ) {
			return false;
		}

		// Check if any language is missing a translation
		return ! in_array( false, $translation_status, true );
	}

	/**
	 * Get list of languages without translations for a post
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return array<int, string> Array of language codes that don't have translations
	 */
	public static function get_missing_translations( int|WP_Post $post ): array {
		$translation_status = self::get_translation_status( $post );

		$missing = array();
		foreach ( $translation_status as $language_code => $exists ) {
			if ( ! $exists ) {
				$missing[] = $language_code;
			}
		}

		return $missing;
	}

	/**
	 * Safely deletes term relationships for a post across all WPML language contexts.
	 *
	 * This function works around a WPML bug where term relationships with terms in the wrong language
	 * don't get properly deleted. It temporarily switches the WPML language context for each translation
	 * and deletes the term relationships for the post in each language context, ensuring
	 * that relationships with terms in any language are properly removed.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @param string      $taxonomy The taxonomy name from which to remove relationships.
	 * @return void
	 */
	public static function safe_delete_term_relationships( int|WP_Post $post, string $taxonomy ): void {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return;
		}

		// Store current language to restore later
		$current_language = apply_filters( 'wpml_current_language', null );

		// Delete term relationships for the post's original language
		wp_delete_object_term_relationships( $post_id, $taxonomy );

		// Delete term relationships for all translations of this post
		$language_versions = self::get_language_versions( $post_id );
		foreach ( $language_versions as $language_code => $translated_post_id ) {
			do_action( 'wpml_switch_language', $language_code );
			wp_delete_object_term_relationships( $post_id, $taxonomy );
		}

		// Restore original language
		if ( null !== $current_language ) {
			do_action( 'wpml_switch_language', $current_language );
		}
	}

	/**
	 * Get all active language codes configured in WPML
	 *
	 * @return array<int, string> Array of language codes (e.g., ['en', 'de', 'fr'])
	 */
	public static function get_active_language_codes(): array {
		$active_languages = apply_filters( 'wpml_active_languages', null );
		if ( empty( $active_languages ) ) {
			return array();
		}

		$language_codes = array();
		foreach ( $active_languages as $language ) {
			if ( isset( $language['code'] ) ) {
				$language_codes[] = $language['code'];
			}
		}

		return $language_codes;
	}

	/**
	 * Check if a post is in a language that is not configured/active in WPML
	 *
	 * This is useful for finding posts that were created in a language that has since
	 * been deactivated or removed from WPML configuration.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return bool True if post is in an unconfigured language, false otherwise.
	 */
	public static function is_post_in_unconfigured_language( int|WP_Post $post ): bool {
		// Get the post's language
		$post_language = self::get_language( $post );

		// If no language is set, consider it unconfigured
		if ( empty( $post_language ) ) {
			return true;
		}

		// Get all active language codes
		$active_language_codes = self::get_active_language_codes();

		// If no active languages, something is wrong with WPML
		if ( empty( $active_language_codes ) ) {
			return false;
		}

		// Check if post language is not in active languages
		return ! in_array( $post_language, $active_language_codes, true );
	}

	/**
	 * Set or update a post's language assignment in WPML
	 *
	 * This method assigns a post to a specific language in WPML. It can be used to:
	 * - Fix posts with no language assignment
	 * - Change a post's language
	 * - Reassign posts from deactivated languages
	 *
	 * @param int|WP_Post $post            Post ID or WP_Post object.
	 * @param string      $target_language The target language code to assign.
	 * @return bool True if language was set successfully, false otherwise.
	 */
	public static function set_language( int|WP_Post $post, string $target_language ): bool {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return false;
		}

		// Get post type for the element type
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return false;
		}

		// Get WPML element type
		$element_type = apply_filters( 'wpml_element_type', $post_type );

		// Get current language details using WPML filter
		$language_details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $post_id,
				'element_type' => $element_type,
			)
		);

		// Create a new translation group or use existing one
		$trid = ! empty( $language_details->trid ) ? $language_details->trid : apply_filters( 'wpml_element_trid', null, $post_id, $element_type );

		// Set the new language for the post
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $post_id,
				'element_type'         => $element_type,
				'trid'                 => $trid,
				'language_code'        => $target_language,
				'source_language_code' => null, // Make it an original post
			)
		);

		// Clear WPML cache for this post
		do_action( 'wpml_cache_clear' );

		return true;
	}
}
