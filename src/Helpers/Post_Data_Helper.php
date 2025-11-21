<?php
/**
 * Post Data Helper
 *
 * Provides utility functions for comparing post data values and detecting changes.
 * Used by post content and meta trackers to determine if data has actually changed.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use Multilingual_Bridge\Helpers\WPML_Language_Helper;

/**
 * Class Post_Data_Helper
 *
 * Utility class for post data comparison and sync flagging operations
 */
class Post_Data_Helper {

	/**
	 * Compare two values to determine if they've changed
	 *
	 * Handles type coercion intelligently to account for database storage differences.
	 * WordPress get_post_meta() returns strings, but update_post_meta() may receive integers.
	 * Examples:
	 * - "122" (string from DB) vs 122 (int) = NOT changed
	 * - "122" vs "123" = changed
	 * - array(1,2,3) vs array(1,2,3) = NOT changed
	 * - null vs "" = changed (different empty states)
	 *
	 * @param mixed $old_value Old value to compare.
	 * @param mixed $new_value New value to compare.
	 * @return bool True if values are different
	 */
	public static function has_value_changed( mixed $old_value, mixed $new_value ): bool {
		// Handle identical values first (includes same type)
		if ( $old_value === $new_value ) {
			return false;
		}

		// Handle arrays separately - must use deep comparison
		if ( is_array( $old_value ) || is_array( $new_value ) ) {
			// If one is array and other isn't, they're different
			if ( is_array( $old_value ) !== is_array( $new_value ) ) {
				return true;
			}
			// Both are arrays - use serialize for deep equality check (structure and values)
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Necessary for deep array comparison
			return serialize( $old_value ) !== serialize( $new_value );
		}

		// Handle objects - serialize for comparison
		if ( is_object( $old_value ) || is_object( $new_value ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Necessary for object comparison
			return serialize( $old_value ) !== serialize( $new_value );
		}

		// For scalar values (string, int, float, bool), use loose comparison
		// This handles "122" == 122, "1" == 1, etc.
		// But still catches real changes like "122" != "123"
	// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Intentional loose comparison for type coercion
		return $old_value != $new_value;
	}

	/**
	 * Check if meta value has changed
	 *
	 * Combines value retrieval and comparison for meta fields.
	 * Used by meta trackers to determine if a meta field update represents an actual change.
	 *
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key being updated.
	 * @param mixed  $new_value  New meta value.
	 * @return bool True if the meta value has actually changed
	 */
	public static function has_meta_value_changed( int $object_id, string $meta_key, mixed $new_value ): bool {
		// Always fetch current value from database for accurate comparison
		$current_value = get_post_meta( $object_id, $meta_key, true );
		return self::has_value_changed( $current_value, $new_value );
	}

	/**
	 * Check if a value is truly empty (for ACF field syncing)
	 *
	 * Determines if a value should be considered empty for ACF field operations.
	 * Used to distinguish between truly empty values and valid zero/false values.
	 *
	 * Values considered empty: null, '' (empty string), [] (empty array)
	 * Values NOT considered empty: 0, '0', false (potentially valid IDs or flags)
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if value is empty and should be synced as deleted
	 */
	public static function is_empty_value( mixed $value ): bool {
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
	 * Determine if ACF field returns multiple values
	 *
	 * Checks ACF field configuration and current value to determine
	 * if the field should return single or multiple values.
	 *
	 * @param array<string, mixed> $field      ACF field object.
	 * @param mixed                $meta_value Current field value.
	 * @return bool True if field returns multiple values
	 */
	public static function is_multiple_value_field( array $field, mixed $meta_value ): bool {
		// Relationship fields always return arrays.
		if ( isset( $field['type'] ) && 'relationship' === $field['type'] ) {
			return true;
		}

		// Check the 'multiple' setting for other field types.
		if ( isset( $field['multiple'] ) && 1 === $field['multiple'] ) {
			return true;
		}

		// Fallback: check if current value is an array.
		return is_array( $meta_value );
	}

	/**
	 * Flag a field for sync across all translation posts
	 *
	 * Unified function for flagging both content and meta fields for sync.
	 * Stores pending updates directly on each translation post instead of source post.
	 * Only processes changes from original/source language posts to prevent recursive tracking.
	 *
	 * @param int    $source_post_id Post ID where change occurred (must be original post).
	 * @param string $field_name     Field name that changed (e.g., 'title', 'content', 'field_123').
	 * @param string $field_type     Field type: 'content' or 'meta'.
	 * @return bool True if fields were flagged successfully
	 */
	public static function flag_field_for_sync( int $source_post_id, string $field_name, string $field_type ): bool {
		if ( ! get_post( $source_post_id ) ) {
			return false;
		}

		// Only flag fields from original/source language posts.
		// Translation posts should never trigger sync flags for other translations.
		if ( ! WPML_Post_Helper::is_original_post( $source_post_id ) ) {
			return false;
		}

		$source_lang = WPML_Post_Helper::get_language( $source_post_id );
		if ( '' === $source_lang ) {
			return false;
		}

		// Get all active languages except source.
		$all_languages    = WPML_Language_Helper::get_active_language_codes();
		$target_languages = array_filter(
			$all_languages,
			function ( string $lang_code ) use ( $source_lang ): bool {
				return $lang_code !== $source_lang;
			}
		);

		if ( empty( $target_languages ) ) {
			return false;
		}

		// Get all translation post IDs.
		$translations = WPML_Post_Helper::get_language_versions( $source_post_id );

		// Flag the field on each translation post.
		foreach ( $target_languages as $lang_code ) {
			// Skip if no translation exists for this language.
			if ( ! isset( $translations[ $lang_code ] ) ) {
				continue;
			}

			$translation_post_id = $translations[ $lang_code ];

			// Get existing pending updates for this translation post.
			$pending = get_post_meta( $translation_post_id, '_mlb_updates_pending', true );
			if ( ! is_array( $pending ) ) {
				$pending = array();
			}

			/*
			 * Pending updates are stored with this structure:
			 * - Top level keys: 'content' and 'meta'
			 * - Each contains field names as keys with boolean true values
			 */
			if ( ! isset( $pending[ $field_type ] ) ) {
				$pending[ $field_type ] = array();
			}

			$pending[ $field_type ][ $field_name ] = true;

			// Update the translation post meta.
			update_post_meta( $translation_post_id, '_mlb_updates_pending', $pending );
		}

		/**
		 * Fires when a field is flagged for translation sync
		 *
		 * @param int      $source_post_id   Original post ID where flag was set
		 * @param string   $field_name       Field name that needs sync
		 * @param string   $field_type       Field type (content or meta)
		 * @param string[] $target_languages Target language codes that were flagged
		 */
		do_action( 'multilingual_bridge_field_flagged_for_sync', $source_post_id, $field_name, $field_type, $target_languages );

		return true;
	}
}
