<?php
/**
 * ACF Field Helper - Simplified ACF field information and configuration
 *
 * Provides methods to fetch ACF field details and determine which field types
 * are translatable. Replaces the more complex Field_Registry pattern.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

/**
 * Class ACF_Field_Helper
 *
 * Helper class for ACF field operations and field type configuration
 */
class ACF_Field_Helper {

	/**
	 * Default translatable field types
	 *
	 * These are the ACF field types that can be translated.
	 * Other field types (image, file, relationship, etc.) should be copied as-is.
	 *
	 * @var string[]
	 */
	private const DEFAULT_TRANSLATABLE_TYPES = array( 'text', 'textarea', 'wysiwyg' );

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
		// 3 = "Copy once" (copy on first translation, then don't update).
		switch ( $preference ) {
			case 2:
				return 'translate';
			case 1:
			case 3: // Treat "copy once" as "copy" for our purposes.
				return 'copy';
			case 0:
				return 'ignore';
			default:
				// If no preference set, return safe default.
				return 'copy';
		}
	}

	/**
	 * Check if a field type is translatable
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
	 * Get all translatable field types
	 *
	 * @return string[] Array of translatable field type names
	 */
	public static function get_translatable_field_types(): array {
		/**
		 * Filter translatable field types
		 *
		 * @param string[] $types Default translatable field types
		 */
		return apply_filters(
			'multilingual_bridge_acf_translatable_field_types',
			self::DEFAULT_TRANSLATABLE_TYPES
		);
	}
}
