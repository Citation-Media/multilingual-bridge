<?php
/**
 * ACF Relationship Field Translation Handler
 *
 * Handles translation of ACF relationship fields by:
 * - Finding equivalent posts in the target language
 * - Preserving post relationships across languages
 * - Supporting single and multi-select relationship fields
 * - Handling post_object, relationship, and page_link field types
 *
 * This handler specifically deals with ACF relationship-type fields,
 * where post IDs need to be mapped to their translated equivalents
 * rather than translating text content.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;

/**
 * Class ACF_Relationship_Field_Handler
 *
 * Translates ACF relationship fields by mapping posts to target language equivalents
 */
class ACF_Relationship_Field_Handler {

	/**
	 * Supported relationship field types
	 *
	 * @var string[]
	 */
	private const RELATIONSHIP_FIELD_TYPES = array(
		'relationship',  // Relationship field (multiple posts).
		'post_object',   // Post Object field (single or multiple posts).
		'page_link',     // Page Link field (returns post IDs).
	);

	/**
	 * Translate ACF relationship field value
	 *
	 * Handles both single and multiple post selections by:
	 * - Converting source post IDs to their target language equivalents
	 * - Skipping posts that don't have translations
	 * - Preserving the field value structure (single value vs array)
	 *
	 * @param array<string, mixed> $field           ACF field object.
	 * @param mixed                $meta_value      Meta value (post ID, post IDs array, or WP_Post object).
	 * @param int                  $source_post_id  Source post ID (unused - kept for interface consistency).
	 * @param int                  $target_post_id  Target post ID.
	 * @param LanguageTag          $target_language Target language tag.
	 * @param LanguageTag          $source_language Source language tag (unused - kept for interface consistency).
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function translate_relationship_field( array $field, $meta_value, int $source_post_id, int $target_post_id, LanguageTag $target_language, LanguageTag $source_language ) {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $source_post_id, $source_language );

		// Validate field is a relationship type.
		if ( ! isset( $field['type'] ) || ! $this->is_relationship_field_type( $field['type'] ) ) {
			return new WP_Error(
				'invalid_field_type',
				sprintf(
					/* translators: 1: Field type, 2: Target post ID */
					__( 'Field is not a relationship type (got: %1$s, post ID: %2$d)', 'multilingual-bridge' ),
					$field['type'] ?? 'unknown',
					$target_post_id
				)
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

		// Determine if field returns single or multiple values.
		$is_multiple = $this->is_multiple_value_field( $field, $meta_value );

		// Convert value to array for consistent processing.
		$source_post_ids = $this->normalize_to_post_ids( $meta_value );

		if ( empty( $source_post_ids ) ) {
			// No valid post IDs found - delete field.
			if ( function_exists( 'delete_field' ) ) {
				delete_field( $field['name'], $target_post_id );
			}
			return true;
		}

		// Translate post IDs to target language.
		$target_post_ids = $this->translate_post_ids(
			$source_post_ids,
			$target_language->toString()
		);

		// If no posts could be translated, delete field in target.
		if ( empty( $target_post_ids ) ) {
			if ( function_exists( 'delete_field' ) ) {
				delete_field( $field['name'], $target_post_id );
			}
			return true;
		}

		// Preserve single/multiple value structure.
		$value_to_save = $is_multiple ? $target_post_ids : $target_post_ids[0];

		// Update field using ACF's update_field function.
		$result = update_field( $field['key'], $value_to_save, $target_post_id );

		if ( ! $result ) {
			return new WP_Error(
				'update_field_failed',
				sprintf( 'Failed to update relationship field "%s"', $field['name'] )
			);
		}

		return true;
	}

	/**
	 * Translate post IDs from source language to target language
	 *
	 * @param array<int, int> $source_post_ids Array of source post IDs.
	 * @param string          $target_language Target language code.
	 * @return array<int, int> Array of translated post IDs
	 */
	private function translate_post_ids( array $source_post_ids, string $target_language ): array {
		$target_post_ids = array();

		foreach ( $source_post_ids as $source_post_id ) {
			// Ensure post ID is valid.
			if ( $source_post_id <= 0 ) {
				continue;
			}

			// Get translation in target language.
			$target_post_id = WPML_Post_Helper::get_translation_for_lang(
				$source_post_id,
				$target_language
			);

			// Only include if translation exists.
			if ( null !== $target_post_id && $target_post_id > 0 ) {
				$target_post_ids[] = $target_post_id;
			}
		}

		return $target_post_ids;
	}

	/**
	 * Normalize various input formats to array of post IDs
	 *
	 * Handles:
	 * - Single post ID (int)
	 * - Array of post IDs
	 * - WP_Post object
	 * - Array of WP_Post objects
	 * - Mixed array of IDs and objects
	 *
	 * @param mixed $value The field value to normalize.
	 * @return array<int, int> Array of post IDs
	 */
	private function normalize_to_post_ids( $value ): array {
		// Handle null or empty.
		if ( $this->is_empty_value( $value ) ) {
			return array();
		}

		// Single WP_Post object.
		if ( $value instanceof \WP_Post ) {
			return array( $value->ID );
		}

		// Single post ID.
		if ( is_numeric( $value ) ) {
			$post_id = (int) $value;
			return $post_id > 0 ? array( $post_id ) : array();
		}

		// Array of values.
		if ( is_array( $value ) ) {
			$post_ids = array();
			foreach ( $value as $item ) {
				if ( $item instanceof \WP_Post ) {
					$post_ids[] = $item->ID;
				} elseif ( is_numeric( $item ) ) {
					$item_id = (int) $item;
					if ( $item_id > 0 ) {
						$post_ids[] = $item_id;
					}
				}
			}
			return $post_ids;
		}

		// Unknown type.
		return array();
	}

	/**
	 * Determine if field returns multiple values
	 *
	 * @param array<string, mixed> $field      ACF field object.
	 * @param mixed                $meta_value Current field value.
	 * @return bool True if field returns multiple values
	 */
	private function is_multiple_value_field( array $field, $meta_value ): bool {
		// Relationship fields always return arrays.
		if ( 'relationship' === $field['type'] ) {
			return true;
		}

		// Post object and page_link can be single or multiple.
		// Check the 'multiple' setting.
		if ( isset( $field['multiple'] ) && 1 === $field['multiple'] ) {
			return true;
		}

		// Fallback: check if current value is an array.
		return is_array( $meta_value );
	}

	/**
	 * Check if a value is truly empty (for ACF field syncing)
	 *
	 * We want to sync: null, '', [] (empty array)
	 * We don't want to sync: 0, '0', false (potentially valid post IDs)
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
	 * Check if field type is a relationship type
	 *
	 * @param string $field_type ACF field type.
	 * @return bool True if field type is a relationship type
	 */
	private function is_relationship_field_type( string $field_type ): bool {
		return in_array( $field_type, self::RELATIONSHIP_FIELD_TYPES, true );
	}

	/**
	 * Check if field can be handled by this handler
	 *
	 * @param array<string, mixed> $field ACF field object.
	 * @return bool True if this handler can process the field
	 */
	public static function can_handle_field( array $field ): bool {
		if ( ! isset( $field['type'] ) ) {
			return false;
		}

		return in_array( $field['type'], self::RELATIONSHIP_FIELD_TYPES, true );
	}
}
