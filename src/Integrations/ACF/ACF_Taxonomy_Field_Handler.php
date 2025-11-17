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

use Multilingual_Bridge\Helpers\WPML_Term_Helper;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;

/**
 * Class ACF_Taxonomy_Field_Handler
 *
 * Translates ACF taxonomy fields by mapping terms to target language equivalents
 */
class ACF_Taxonomy_Field_Handler {

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
	 * @param int                  $source_post_id  Source post ID (unused - kept for interface consistency).
	 * @param int                  $target_post_id  Target post ID.
	 * @param LanguageTag          $target_language Target language tag.
	 * @param LanguageTag          $source_language Source language tag (unused - kept for interface consistency).
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function translate_taxonomy_field( array $field, $meta_value, int $source_post_id, int $target_post_id, LanguageTag $target_language, LanguageTag $source_language ) {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $source_post_id, $source_language );
		// Validate field is a taxonomy type.
		if ( ! isset( $field['type'] ) || 'taxonomy' !== $field['type'] ) {
			return new WP_Error(
				'invalid_field_type',
				'Field is not a taxonomy type'
			);
		}

		// Get taxonomy from field settings.
		$taxonomy = $field['taxonomy'] ?? '';
		if ( empty( $taxonomy ) ) {
			return new WP_Error(
				'missing_taxonomy',
				'Taxonomy not specified in field settings'
			);
		}

		// Validate taxonomy exists.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'invalid_taxonomy',
				sprintf( 'Taxonomy "%s" does not exist', $taxonomy )
			);
		}

		// Handle empty values.
		if ( $this->is_empty_value( $meta_value ) ) {
			// Delete the field from translation to sync empty state.
			if ( function_exists( 'delete_field' ) ) {
				delete_field( $field['name'], $target_post_id );
			}
			return true;
		}

		// Convert value to array for consistent processing.
		$is_single_value = ! is_array( $meta_value );
		$source_term_ids = is_array( $meta_value ) ? $meta_value : array( $meta_value );

		// Translate term IDs to target language.
		$target_term_ids = $this->translate_term_ids(
			$source_term_ids,
			$taxonomy,
			$target_language->toString()
		);

		// Preserve single/multiple value structure based on field settings.
		// Check ACF field 'multiple' setting - if 0, return single value.
		$field_multiple = $field['multiple'] ?? 0;
		$value_to_save  = ( 0 === $field_multiple && $is_single_value ) ? $target_term_ids[0] : $target_term_ids;

		// Update field using ACF's update_field function.
		$result = update_field( $field['key'], $value_to_save, $target_post_id );

		if ( ! $result ) {
			return new WP_Error(
				'update_field_failed',
				sprintf( 'Failed to update taxonomy field "%s"', $field['name'] )
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
	 * Check if a value is truly empty (for ACF field syncing)
	 *
	 * We want to sync: null, '', [] (empty array)
	 * We don't want to sync: 0, '0', false (potentially valid term IDs)
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if value is empty and should be synced as deleted
	 */
	private function is_empty_value( $value ): bool {
		// Null is empty.
		if ( null === $value ) {
			return true;
		}

		// Empty string is empty.
		if ( '' === $value ) {
			return true;
		}

		// Empty array is empty.
		if ( array() === $value ) {
			return true;
		}

		// Everything else is not empty (including 0, '0', false).
		return false;
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
