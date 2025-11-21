<?php
/**
 * ACF Taxonomy Field Translation Handler
 *
 * Handles translation of ACF taxonomy fields by:
 * - Finding equivalent terms in the target language
 * - Preserving term relationships across languages
 * - Supporting single and multi-select taxonomy fields
 *
 * This handler specifically deals with ACF taxonomy field types,
 * where term IDs need to be mapped to their translated equivalents
 * rather than translating text content.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Helpers\Post_Data_Helper;
use Multilingual_Bridge\Helpers\WPML_Term_Helper;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class Taxonomy_Field_Handler
 *
 * Translates ACF taxonomy fields by mapping terms to target language equivalents
 */
class Taxonomy_Field_Handler {

	/**
	 * Translate ACF taxonomy field value
	 *
	 * Handles both single and multiple term selections by:
	 * - Converting source term IDs to their target language equivalents
	 * - Skipping terms that don't have translations
	 * - Preserving the field value structure (single value vs array)
	 *
	 * @param array<string, mixed> $field           ACF field object.
	 * @param mixed                $meta_value      Meta value (term ID, term IDs array, or term object).
	 * @param int                  $target_post_id  Target post ID.
	 * @param LanguageTag          $target_language Target language tag.
	 * @return bool True on success
	 * @throws InvalidArgumentException If field is not a valid taxonomy type or taxonomy is invalid.
	 * @throws RuntimeException If field update fails.
	 */
	public function translate_taxonomy_field( array $field, $meta_value, int $target_post_id, LanguageTag $target_language ): bool {
		// Validate field is a taxonomy type.
		if ( ! isset( $field['type'] ) || 'taxonomy' !== $field['type'] ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d: Target post ID */
					esc_html__( 'Field is not a taxonomy type (post ID: %d)', 'multilingual-bridge' ),
					(int) $target_post_id
				)
			);
		}

		// Get taxonomy from field settings.
		$taxonomy = $field['taxonomy'] ?? '';
		if ( empty( $taxonomy ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d: Target post ID */
					esc_html__( 'Taxonomy not specified in field settings (post ID: %d)', 'multilingual-bridge' ),
					(int) $target_post_id
				)
			);
		}

		// Validate taxonomy exists.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: Taxonomy name, 2: Target post ID */
					esc_html__( 'Taxonomy "%1$s" does not exist (post ID: %2$d)', 'multilingual-bridge' ),
					esc_html( $taxonomy ),
					(int) $target_post_id
				)
			);
		}

		// Handle empty values.
		if ( Post_Data_Helper::is_empty_value( $meta_value ) ) {
			// Delete the field from translation to sync empty state.
			delete_field( $field['name'], $target_post_id );
			return true;
		}

		// Determine if field returns single or multiple values.
		$is_multiple = Post_Data_Helper::is_multiple_value_field( $field, $meta_value );

		// Convert value to array of term IDs for consistent processing.
		$source_term_ids = $this->normalize_to_term_ids( $meta_value );

		if ( empty( $source_term_ids ) ) {
			// No valid term IDs found - delete field.
			delete_field( $field['name'], $target_post_id );
			return true;
		}

		// Translate term IDs to target language.
		$target_term_ids = $this->translate_term_ids(
			$source_term_ids,
			$taxonomy,
			$target_language->toString()
		);

		// If no terms could be translated, delete field in target.
		if ( empty( $target_term_ids ) ) {
			delete_field( $field['name'], $target_post_id );
			return true;
		}

		// Preserve single/multiple value structure.
		$value_to_save = $is_multiple ? $target_term_ids : $target_term_ids[0];

		// Update field using ACF's update_field function.
		$result = update_field( $field['key'], $value_to_save, $target_post_id );

		if ( ! $result ) {
			throw new RuntimeException(
				sprintf(
				/* translators: 1: Field name, 2: Target post ID */
					esc_html__( 'Failed to update taxonomy field "%1$s" (post ID: %2$d)', 'multilingual-bridge' ),
					esc_html( $field['name'] ),
					(int) $target_post_id
				)
			);
		}

		return true;
	}

	/**
	 * Translate term IDs from source language to target language
	 *
	 * @param array<int, int|string> $source_term_ids Array of source term IDs.
	 * @param string                 $taxonomy        Taxonomy name.
	 * @param string                 $target_language Target language code.
	 * @return array<int, int> Array of translated term IDs
	 */
	private function translate_term_ids( array $source_term_ids, string $taxonomy, string $target_language ): array {
		$target_term_ids = array();

		foreach ( $source_term_ids as $source_term_id ) {
			// Ensure term ID is an integer.
			$source_term_id = (int) $source_term_id;

			if ( $source_term_id <= 0 ) {
				continue;
			}

			// Get translation in target language.
			$target_term_id = WPML_Term_Helper::get_translation_id(
				$source_term_id,
				$taxonomy,
				$target_language
			);

			// Only include if translation exists.
			if ( null !== $target_term_id && $target_term_id > 0 ) {
				$target_term_ids[] = $target_term_id;
			}
		}

		return $target_term_ids;
	}

	/**
	 * Normalize various input formats to array of term IDs
	 *
	 * Handles:
	 * - Single term ID (int)
	 * - Array of term IDs
	 * - WP_Term object
	 * - Array of WP_Term objects
	 * - Mixed array of IDs and objects
	 *
	 * @param mixed $value The field value to normalize.
	 * @return array<int, int> Array of term IDs
	 */
	private function normalize_to_term_ids( $value ): array {
		// Handle null or empty.
		if ( Post_Data_Helper::is_empty_value( $value ) ) {
			return array();
		}

		// Single WP_Term object.
		if ( $value instanceof \WP_Term ) {
			return array( $value->term_id );
		}

		// Single term ID.
		if ( is_numeric( $value ) ) {
			$term_id = (int) $value;
			return $term_id > 0 ? array( $term_id ) : array();
		}

		// Array of values.
		if ( is_array( $value ) ) {
			$term_ids = array();
			foreach ( $value as $item ) {
				if ( $item instanceof \WP_Term ) {
					$term_ids[] = $item->term_id;
				} elseif ( is_numeric( $item ) ) {
					$item_id = (int) $item;
					if ( $item_id > 0 ) {
						$term_ids[] = $item_id;
					}
				}
			}
			return $term_ids;
		}

		// Unknown type.
		return array();
	}

	/**
	 * Check if field can be handled by this handler
	 *
	 * @param array<string, mixed> $field ACF field object.
	 * @return bool True if this handler can process the field
	 */
	public static function can_handle_field( array $field ): bool {
		return isset( $field['type'] ) && 'taxonomy' === $field['type'];
	}
}
