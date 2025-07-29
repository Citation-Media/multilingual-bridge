<?php
/**
 * WPML Post Helper functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use WP_Post;
use WP_Term;

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
		$active_languages = WPML_Language_Helper::get_available_languages();
		if ( empty( $active_languages ) ) {
			return array();
		}

		// Get existing translations
		$translations = self::get_language_versions( $post_id );

		$status = array();
		foreach ( $active_languages as $language_code => $language ) {
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
		$current_language = WPML_Language_Helper::get_current_language();

		// Delete term relationships for the post's original language
		wp_delete_object_term_relationships( $post_id, $taxonomy );

		// Delete term relationships for all translations of this post
		$language_versions = self::get_language_versions( $post_id );
		foreach ( $language_versions as $language_code => $translated_post_id ) {
			WPML_Language_Helper::switch_language( $language_code );
			wp_delete_object_term_relationships( $post_id, $taxonomy );
		}

		// Restore original language
		WPML_Language_Helper::restore_language( $current_language );
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
		$active_language_codes = WPML_Language_Helper::get_active_language_codes();

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
	 * @return bool|\WP_Error True if language was set successfully, false for invalid post,
	 *                        WP_Error if target language is not configured in WPML.
	 */
	public static function set_language( int|WP_Post $post, string $target_language ): bool|\WP_Error {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return false;
		}

		// Validate that the target language is configured in WPML
		if ( ! WPML_Language_Helper::is_language_active( $target_language ) ) {
			return new \WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %s: Language code */
					__( 'Language "%s" is not configured in WPML.', 'multilingual-bridge' ),
					$target_language
				)
			);
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

	/**
	 * Check if a post has term relationships in languages other than its own
	 *
	 * This method detects when a post is incorrectly associated with terms
	 * from different languages, which can happen due to WPML bugs or
	 * language switching issues.
	 *
	 * @param int|WP_Post $post     Post ID or WP_Post object.
	 * @param string      $taxonomy Optional. Specific taxonomy to check. If empty, checks all taxonomies.
	 * @return bool True if cross-language term relationships exist, false otherwise.
	 */
	public static function has_cross_language_term_relationships( int|WP_Post $post, string $taxonomy = '' ): bool {
		// Reuse get_cross_language_term_relationships to avoid code duplication
		$mismatched_terms = self::get_cross_language_term_relationships( $post, $taxonomy );

		// If we have any mismatched terms, return true
		return ! empty( $mismatched_terms );
	}

	/**
	 * Get detailed information about cross-language term relationships
	 *
	 * Returns an array of terms that are in a different language than the post,
	 * organized by language and then by taxonomy for efficient removal.
	 *
	 * @param int|WP_Post $post     Post ID or WP_Post object.
	 * @param string      $taxonomy Optional. Specific taxonomy to check. If empty, checks all taxonomies.
	 * @return array<string, array<string, array<int, int>>> Array indexed by language code, then taxonomy, containing term IDs.
	 */
	public static function get_cross_language_term_relationships( int|WP_Post $post, string $taxonomy = '' ): array {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return array();
		}

		// Get the post's language
		$post_language = self::get_language( $post_id );
		if ( empty( $post_language ) ) {
			return array();
		}

		// Get taxonomies to check
		if ( ! empty( $taxonomy ) ) {
			$taxonomies = array( $taxonomy );
		} else {
			$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
		}

		$mismatched_terms = array();

		// Store current language to restore later
		$current_language = WPML_Language_Helper::get_current_language();

		// Get all language codes
		$all_languages = WPML_Language_Helper::get_active_language_codes();

		// Check each taxonomy
		foreach ( $taxonomies as $tax ) {
			foreach ( $all_languages as $lang_code ) {
				// Switch to each language context
				WPML_Language_Helper::switch_language( $lang_code );

				// Get terms in this language context
				$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'all' ) );

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					// Check each term's language
					foreach ( $terms as $term ) {
						$term_language = WPML_Term_Helper::get_language( $term );

						// If term language doesn't match post language, track it
						if ( ! empty( $term_language ) && $term_language !== $post_language ) {
							// Initialize language array if not exists
							if ( ! isset( $mismatched_terms[ $lang_code ] ) ) {
								$mismatched_terms[ $lang_code ] = array();
							}

							// Initialize taxonomy array if not exists
							if ( ! isset( $mismatched_terms[ $lang_code ][ $tax ] ) ) {
								$mismatched_terms[ $lang_code ][ $tax ] = array();
							}

							// Add term ID (avoid duplicates)
							if ( ! in_array( $term->term_id, $mismatched_terms[ $lang_code ][ $tax ], true ) ) {
								$mismatched_terms[ $lang_code ][ $tax ][] = $term->term_id;
							}
						}
					}
				}
			}
		}

		// Restore original language
		WPML_Language_Helper::restore_language( $current_language );

		return $mismatched_terms;
	}

	/**
	 * Remove term relationships where term language doesn't match post language
	 *
	 * This method removes incorrect term associations where a post is linked
	 * to terms in different languages. It switches language contexts to ensure
	 * all mismatched relationships are properly removed.
	 *
	 * @param int|WP_Post $post     Post ID or WP_Post object.
	 * @param string      $taxonomy Optional. Specific taxonomy to clean. If empty, cleans all taxonomies.
	 * @return array<string, array<int, int>> Array of removed term IDs organized by taxonomy.
	 */
	public static function remove_cross_language_term_relationships( int|WP_Post $post, string $taxonomy = '' ): array {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return array();
		}

		// Get mismatched terms organized by language
		$mismatched_terms = self::get_cross_language_term_relationships( $post_id, $taxonomy );
		if ( empty( $mismatched_terms ) ) {
			return array();
		}

		$removed_terms = array();

		// Store current language to restore later
		$current_language = WPML_Language_Helper::get_current_language();

		// Process each language context
		foreach ( $mismatched_terms as $lang_code => $taxonomies ) {
			// Switch to the language context
			WPML_Language_Helper::switch_language( $lang_code );

			// Remove terms for each taxonomy in this language context
			foreach ( $taxonomies as $tax => $term_ids ) {
				// Remove all term IDs at once for this taxonomy
				$result = wp_remove_object_terms( $post_id, $term_ids, $tax );

				if ( ! is_wp_error( $result ) && $result ) {
					// Track removed terms by taxonomy
					if ( ! isset( $removed_terms[ $tax ] ) ) {
						$removed_terms[ $tax ] = array();
					}

					// Add the removed term IDs (merge to avoid duplicates)
					$removed_terms[ $tax ] = array_unique( array_merge( $removed_terms[ $tax ], $term_ids ) );
				}
			}
		}

		// Restore original language
		WPML_Language_Helper::restore_language( $current_language );

		return $removed_terms;
	}

	/**
	 * Trigger automatic translation for a post
	 *
	 * Attention: By default the automatic translation is triggered on save_post hook.
	 * Before using always check if your code calls the hook correctly or has good reason to not do so!
	 *
	 * This method sends a post for automatic translation to specified target languages
	 * or all available languages if none are specified.
	 *
	 * @param int|WP_Post        $post            Post ID or WP_Post object.
	 * @param array<string>|null $target_languages Array of target language codes, or null for all available languages.
	 * @return array<int>|\WP_Error Array of job IDs on success, WP_Error on failure.
	 */
	public static function trigger_automatic_translation( int|WP_Post $post, ?array $target_languages = null ): array|\WP_Error {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid post ID provided.', 'multilingual-bridge' ) );
		}

		// Check WPML dependencies
		if ( ! function_exists( 'wpml_tm_load_job_factory' ) ||
			! defined( '\WPML\TM\API\Jobs::SENT_AUTOMATICALLY' ) ||
			! class_exists( '\WPML\TM\API\Jobs' ) ) {
			return new \WP_Error( 'wpml_dependencies_missing', __( 'WPML dependencies are not available.', 'multilingual-bridge' ) );
		}

		// Get the post's source language
		$source_language = self::get_language( $post_id );
		if ( empty( $source_language ) ) {
			return new \WP_Error( 'no_source_language', __( 'Post has no language assigned.', 'multilingual-bridge' ) );
		}

		// If no target languages specified, get all available languages except source
		if ( null === $target_languages ) {
			$all_languages    = WPML_Language_Helper::get_active_language_codes();
			$target_languages = array_diff( $all_languages, array( $source_language ) );
		} else {
			// Remove source language from target languages if present
			$target_languages = array_diff( $target_languages, array( $source_language ) );
		}

		// If no valid target languages, return error
		if ( empty( $target_languages ) ) {
			return new \WP_Error( 'no_valid_languages', __( 'No valid target languages specified.', 'multilingual-bridge' ) );
		}

		// Get job factory
		$job_factory = wpml_tm_load_job_factory();
		$job_ids     = array();

		// Create jobs for each target language using the factory
		foreach ( $target_languages as $target_language ) {
			$job_id = $job_factory->create_local_post_job(
				$post_id,
				$target_language,
				null,
				\WPML\TM\API\Jobs::SENT_AUTOMATICALLY
			);

			if ( $job_id ) {
				$job_ids[] = $job_id;
			}
		}

		// Return the array of job IDs
		return $job_ids;
	}

	/**
	 * Get translation of a post for a specific language
	 *
	 * This method checks if the provided post is already in the target language.
	 * If not, it looks up the translation of the post for the requested language.
	 *
	 * @since 1.0.0
	 *
	 * @param int|WP_Post $post            Post ID or WP_Post object to get translation for.
	 * @param string      $target_language Target language code (e.g., 'en', 'de', 'fr').
	 * @return int|null Post ID of the translation if found, original post ID if already in target language, null if no translation exists.
	 */
	public static function get_translation_for_lang( int|WP_Post $post, string $target_language ): ?int {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return null;
		}

		// Validate that the target language is active in WPML
		if ( ! WPML_Language_Helper::is_language_active( $target_language ) ) {
			return null;
		}

		// Get the current language of the post
		$post_language = self::get_language( $post_id );

		// If post has no language assigned, return null
		if ( empty( $post_language ) ) {
			return null;
		}

		// If the post is already in the target language, return the same post ID
		if ( $post_language === $target_language ) {
			return $post_id;
		}

		// Get all language versions of the post
		$language_versions = self::get_language_versions( $post_id );

		// Check if a translation exists for the target language
		if ( isset( $language_versions[ $target_language ] ) ) {
			return $language_versions[ $target_language ];
		}

		// No translation found for the target language
		return null;
	}

	/**
	 * Safely assign terms to a post with language validation
	 *
	 * This method validates that terms are in the same language as the post.
	 * If a term is in a different language, it attempts to find the translation
	 * in the post's language and assign that instead.
	 *
	 * @param int|WP_Post        $post Post ID or WP_Post object.
	 * @param array<int|WP_Term> $terms Array of term IDs or WP_Term objects to assign.
	 * @param string             $taxonomy Taxonomy name.
	 * @param bool               $append Whether to append terms or replace existing ones.
	 * @return \WP_Error Contains all error of terms that could not have been assigned.
	 */
	public static function safe_assign_terms( int|WP_Post $post, array $terms, string $taxonomy, bool $append = false ): \WP_Error {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		// Validate taxonomy
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error(
				'invalid_taxonomy',
				sprintf(
							/* translators: %s: Taxonomy name */
					__( 'Taxonomy "%s" does not exist.', 'multilingual-bridge' ),
					$taxonomy
				)
			);
		}

		// Get post language
		$post_language = self::get_language( $post_id );
		if ( empty( $post_language ) ) {
			return new \WP_Error( 'no_post_language', __( 'Post has no language assigned.', 'multilingual-bridge' ) );
		}

		$valid_term_ids = array();
		$error          = new \WP_Error();

		// Process each term
		foreach ( $terms as $term ) {
			// Get term ID
			$term_id = is_object( $term ) ? $term->term_id : (int) $term;

			// Validate term exists
			$term_obj = get_term( $term_id, $taxonomy );
			if ( ! $term_obj || is_wp_error( $term_obj ) ) {
				$error->add(
					'invalid_term',
					sprintf(
					/* translators: 1: Term name, 2: Post language */
						__( 'Term "%1$s" has no translation in post language "%2$s".', 'multilingual-bridge' ),
						$term_id,
						$taxonomy
					),
					array(
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					)
				);
				continue;
			}

			// Get term language
			$term_language = WPML_Term_Helper::get_language( $term_obj );

			// If term has no language, we can't validate it
			if ( empty( $term_language ) ) {
				$error->add(
					'no_term_language',
					sprintf(
						/* translators: 1: Term name, 2: Term ID */
						__( 'Term "%1$s" (ID: %2$d) has no language assigned.', 'multilingual-bridge' ),
						$term_obj->name,
						$term_id
					),
					array(
						'term_id' => $term_id,
					)
				);
				continue;
			}

			// If term language matches post language, use it directly
			if ( $term_language === $post_language ) {
				$valid_term_ids[] = $term_id;
				continue;
			}

			// Term is in different language - try to find translation
			$translated_term_id = WPML_Term_Helper::get_translation_id( $term_obj, $taxonomy, $post_language );

			if ( $translated_term_id ) {
				// Use the translated term instead
				$valid_term_ids[] = $translated_term_id;
			} else {
				// No translation available
				$error->add(
					'no_translation',
					sprintf(
						/* translators: 1: Term name, 2: Term ID, 3: Term language, 4: Post language */
						__( 'Term "%1$s" has no translation in post language "%2$s".', 'multilingual-bridge' ),
						$term_obj->name,
						$post_language
					),
					array(
						'term_id'       => $term_id,
						'term_language' => $term_language,
						'post_language' => $post_language,
					)
				);
			}
		}

		if ( ! empty( $valid_term_ids ) ) {
			$result = wp_set_object_terms( $post_id, $valid_term_ids, $taxonomy, $append );
			if ( is_wp_error( $result ) ) {
				$error->merge_from( $result );
				return $error;
			}
		}

		return $error;
	}
}
