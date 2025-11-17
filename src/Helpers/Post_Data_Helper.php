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

/**
 * Class Post_Data_Helper
 *
 * Utility class for post data comparison and sync flagging operations
 */
class Post_Data_Helper {

	/**
	 * Compare two values to determine if they've changed
	 *
	 * Handles different data types appropriately for change detection.
	 * Uses strict comparison to ensure accurate change detection.
	 *
	 * @param mixed $old_value Old value to compare.
	 * @param mixed $new_value New value to compare.
	 * @return bool True if values are different
	 */
	public static function has_value_changed( mixed $old_value, mixed $new_value ): bool {
		return $old_value !== $new_value;
	}

	/**
	 * Get the current meta value for comparison
	 *
	 * Determines the appropriate current value to use for change detection.
	 * Handles WordPress meta update filter parameters intelligently.
	 *
	 * @param mixed  $prev_value Previous meta value from filter (may be null/empty).
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key to get value for.
	 * @return mixed Current meta value to compare against
	 */
	public static function get_current_meta_value( mixed $prev_value, int $object_id, string $meta_key ): mixed {
		// Use $prev_value if it's meaningful (not empty string, not null, not empty array)
		if ( '' !== $prev_value && null !== $prev_value && ! ( is_array( $prev_value ) && empty( $prev_value ) ) ) {
			return $prev_value;
		}

		// Fall back to querying the database for current value
		return get_post_meta( $object_id, $meta_key, true );
	}

	/**
	 * Check if meta value has changed
	 *
	 * Combines value retrieval and comparison for meta fields.
	 * Used by meta trackers to determine if a meta field update represents an actual change.
	 *
	 * @param mixed  $prev_value Previous meta value from filter.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key being updated.
	 * @param mixed  $new_value  New meta value.
	 * @return bool True if the meta value has actually changed
	 */
	public static function has_meta_value_changed( mixed $prev_value, int $object_id, string $meta_key, mixed $new_value ): bool {
		$current_value = self::get_current_meta_value( $prev_value, $object_id, $meta_key );
		return self::has_value_changed( $current_value, $new_value );
	}

	/**
	 * Flag a field for sync across all translation posts
	 *
	 * Unified function for flagging both content and meta fields for sync.
	 * Stores pending updates directly on each translation post instead of source post.
	 *
	 * @param int    $source_post_id Post ID where change occurred.
	 * @param string $field_name     Field name that changed (e.g., 'title', 'content', 'field_123').
	 * @param string $field_type     Field type: 'content' or 'meta'.
	 * @return bool True if fields were flagged successfully
	 */
	public static function flag_field_for_sync( int $source_post_id, string $field_name, string $field_type ): bool {
		if ( ! get_post( $source_post_id ) ) {
			return false;
		}

		// Get original post ID if this is a translation.
		$original_post_id = WPML_Post_Helper::is_original_post( $source_post_id )
			? $source_post_id
			: WPML_Post_Helper::get_default_language_post_id( $source_post_id );

		if ( ! $original_post_id ) {
			return false;
		}

		$source_lang = WPML_Post_Helper::get_language( $original_post_id );
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
		$translations = WPML_Post_Helper::get_language_versions( $original_post_id );

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
		 * @param int      $original_post_id Original post ID where flag was set
		 * @param string   $field_name       Field name that needs sync
		 * @param string   $field_type       Field type (content or meta)
		 * @param int      $source_post_id   Post ID where change occurred (may be translation)
		 * @param string[] $target_languages Target language codes that were flagged
		 */
		do_action( 'multilingual_bridge_field_flagged_for_sync', $original_post_id, $field_name, $field_type, $source_post_id, $target_languages );

		return true;
	}
}
