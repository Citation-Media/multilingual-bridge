<?php
/**
 * ACF Translation Handler
 *
 * Handles translation of Advanced Custom Fields (ACF) fields.
 * Provides methods to:
 * - Determine which ACF field types are translatable
 * - Read WPML translation preferences from ACF fields
 * - Translate ACF field values from source to target post
 *
 * This class focuses exclusively on ACF field translation.
 * Regular post meta translation is handled by Meta_Translation_Handler.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Translation\Translation_Manager;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;

/**
 * Class Translation_Handler
 *
 * Handles ACF field translation operations
 */
class Translation_Handler {

	/**
	 * Translation Manager instance
	 *
	 * @var Translation_Manager
	 */
	private Translation_Manager $translation_manager;

	/**
	 * Default translatable field types
	 *
	 * These are the ACF field types that can be translated.
	 * Other field types (image, file, etc.) should be copied as-is.
	 *
	 * @var string[]
	 */
	private const DEFAULT_TRANSLATABLE_TYPES = array( 'text', 'textarea', 'wysiwyg', 'taxonomy', 'relationship', 'post_object', 'page_link' );

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
	}

	/**
	 * Get ACF field object by meta key
	 *
	 * @param string $meta_key Meta key (field name).
	 * @param int    $post_id  Post ID for context.
	 * @return array<string, mixed>|null ACF field object or null if not found
	 */
	private static function get_field_object( string $meta_key, int $post_id ): ?array {
		if ( ! function_exists( 'get_field_object' ) ) {
			return null;
		}

		$field = get_field_object( $meta_key, $post_id );

		return is_array( $field ) ? $field : null;
	}

	/**
	 * Get WPML translation preference for an ACF field
	 *
	 * Reads the WPML preference directly from the ACF field object.
	 * ACF fields include a 'wpml_cf_preferences' key that contains the WPML setting.
	 *
	 * @param string $meta_key Meta key to check.
	 * @param int    $post_id  Post ID for context (to retrieve ACF field object).
	 * @return string Translation preference: 'translate', 'copy', or 'ignore'
	 */
	public static function get_wpml_translation_preference( string $meta_key, int $post_id ): string {
		// Get ACF field object using meta key.
		// ACF automatically resolves the field and includes WPML preferences.
		$field = self::get_field_object( $meta_key, $post_id );

		// If not an ACF field, return default.
		if ( ! $field ) {
			return 'copy';
		}

		// Get WPML preference from field object.
		// ACF includes this directly in the field array as 'wpml_cf_preferences'.
		$preference = $field['wpml_cf_preferences'] ?? null;

		// WPML uses numeric codes:
		// 0 = "Don't translate" (ignore)
		// 1 = "Copy" (copy as-is)
		// 2 = "Translate" (translate the value)
		switch ( $preference ) {
			case 2:
				return 'translate';
			case 1:
			case 0:
				return 'ignore';
			default:
				// If no preference set, return safe default.
				return 'copy';
		}
	}

	/**
	 * Check if a field is translatable
	 *
	 * Validates both:
	 * 1. Field type is in the translatable types list
	 * 2. WPML translation preference is set to "translate"
	 *
	 * @param string $meta_key  ACF field name/meta key.
	 * @param int    $post_id   Post ID for context.
	 * @return bool True if field is translatable
	 */
	public static function is_translatable_field( string $meta_key, int $post_id ): bool {
		// Get ACF field object.
		$field = self::get_field_object( $meta_key, $post_id );

		// Not an ACF field.
		if ( ! $field ) {
			return false;
		}

		// Check if field type is in translatable types list.
		if ( ! self::is_translatable_field_type( $field['type'] ) ) {
			return false;
		}

		// Check WPML translation preference.
		$preference = self::get_wpml_translation_preference( $meta_key, $post_id );

		return 'translate' === $preference;
	}

	/**
	 * Check if a field type is translatable (type check only)
	 *
	 * This method only checks if the field TYPE supports translation.
	 * Use is_translatable_field() to check both type and WPML preference.
	 *
	 * @param string $field_type ACF field type.
	 * @return bool True if field type is translatable
	 */
	public static function is_translatable_field_type( string $field_type ): bool {
		/**
		 * Filter translatable field types
		 *
		 * Allows developers to add or remove field types from the translatable list.
		 *
		 * @param string[] $types      Default translatable field types
		 * @param string   $field_type The field type being checked
		 */
		$translatable_types = apply_filters(
			'multilingual_bridge_acf_translatable_field_types',
			self::DEFAULT_TRANSLATABLE_TYPES,
			$field_type
		);

		return in_array( $field_type, $translatable_types, true );
	}

	/**
	 * Translate ACF field value
	 *
	 * Main translation method for ACF fields. Handles:
	 * - Empty value syncing (deletes field in translation)
	 * - Field type validation (only translates supported types)
	 * - String translation via Translation Manager
	 *
	 * @param string      $meta_key       Meta key (ACF field name).
	 * @param mixed       $meta_value     Meta value to translate.
	 * @param int         $source_post_id Source post ID.
	 * @param int         $target_post_id Target post ID.
	 * @param LanguageTag $target_language Target language tag.
	 * @param LanguageTag $source_language Source language tag.
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function translate_field( string $meta_key, $meta_value, int $source_post_id, int $target_post_id, LanguageTag $target_language, LanguageTag $source_language ) {
		// Check if ACF is active.
		if ( ! function_exists( 'get_field_object' ) ) {
			return new WP_Error( 'acf_not_available', __( 'ACF is not available', 'multilingual-bridge' ) );
		}

		// Get ACF field object.
		$field = get_field_object( $meta_key, $source_post_id );

		if ( ! $field ) {
			return new WP_Error( 'not_acf_field', __( 'Not an ACF field', 'multilingual-bridge' ) );
		}

		// Handle empty values by syncing them to translations (delete field).
		if ( $this->is_empty_value( $meta_value ) ) {
			// Delete the field from translation to sync empty state.
			if ( function_exists( 'delete_field' ) ) {
				delete_field( $field['name'], $target_post_id );
			}
			// Return success - empty field was synced.
			return true;
		}

		// Validate field type is translatable.
		if ( ! self::is_translatable_field_type( $field['type'] ) ) {
			// Field type is not registered for translation - skip it.
			return new WP_Error(
				'field_type_not_translatable',
				sprintf(
					/* translators: %s: Field type */
					__( 'Field type "%s" is not registered for translation', 'multilingual-bridge' ),
					$field['type']
				)
			);
		}

		// Handle taxonomy fields separately (they require term ID translation, not text translation).
		if ( 'taxonomy' === $field['type'] ) {
			$taxonomy_handler = new Taxonomy_Field_Handler();
			return $taxonomy_handler->translate_taxonomy_field(
				$field,
				$meta_value,
				$source_post_id,
				$target_post_id,
				$target_language,
				$source_language
			);
		}

		// Handle relationship fields separately (they require post ID translation, not text translation).
		if ( Relationship_Field_Handler::can_handle_field( $field ) ) {
			$relationship_handler = new Relationship_Field_Handler();
			return $relationship_handler->translate_relationship_field(
				$field,
				$meta_value,
				$source_post_id,
				$target_post_id,
				$target_language,
				$source_language
			);
		}

		// Only translate string values (for text-based fields).
		if ( ! is_string( $meta_value ) ) {
			// Non-string value for translatable field type - copy as-is.
			// This handles cases like arrays or serialized data.
			return false;
		}

		// Translate the value (for text-based fields like text, textarea, wysiwyg).
		$translated_value = $this->translation_manager->translate(
			$target_language,
			$meta_value,
			$source_language
		);

		if ( is_wp_error( $translated_value ) ) {
			return $translated_value;
		}

		// Update target post meta using ACF's update_field function.
		return update_field( $field['key'], $translated_value, $target_post_id );
	}

	/**
	 * Check if a value is truly empty (for ACF field syncing)
	 *
	 * We want to sync: null, '', [] (empty array)
	 * We don't want to sync: 0, '0', false (valid values)
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
	 * Check if a meta value is an ACF field key reference
	 *
	 * ACF stores field key references as meta with underscore prefix.
	 * Example: _autoscout-id = "field_5ff8033f42629"
	 * These should never be translated.
	 *
	 * @param string $meta_key   Meta key to check.
	 * @param mixed  $meta_value Meta value to check.
	 * @return bool True if this is an ACF field key reference
	 */
	public static function is_acf_field_key_reference( string $meta_key, $meta_value ): bool {
		// Must start with underscore (ACF pattern).
		if ( ! str_starts_with( $meta_key, '_' ) ) {
			return false;
		}

		// Value must be a string.
		if ( ! is_string( $meta_value ) ) {
			return false;
		}

		// Check if value looks like an ACF field key (starts with "field_").
		if ( str_starts_with( $meta_value, 'field_' ) ) {
			return true;
		}

		// Use ACF's function if available for more accurate detection.
		if ( function_exists( 'acf_is_field_key' ) && acf_is_field_key( $meta_value ) ) {
			return true;
		}

		return false;
	}
}
