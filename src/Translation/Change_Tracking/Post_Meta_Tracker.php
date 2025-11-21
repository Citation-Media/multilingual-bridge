<?php
/**
 * Post Meta Tracker
 *
 * Tracks post meta field changes and flags when translations need to be synced.
 * Monitors metadata updates, additions, and deletions to detect when translatable custom fields
 * are modified in the source language post, then marks those fields for sync across all translation posts.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation\Change_Tracking;

use Multilingual_Bridge\Integrations\ACF\Translation_Handler;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\Post_Data_Helper;

/**
 * Class Post_Meta_Tracker
 *
 * Handles translation sync tracking for post meta fields (ACF and custom fields)
 */
class Post_Meta_Tracker {

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {

		add_filter( 'update_post_metadata', array( $this, 'track_meta_update' ), 10, 4 );
		add_filter( 'add_post_metadata', array( $this, 'track_meta_add' ), 10, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'track_meta_delete' ), 10, 5 );
		add_filter( 'acf/field_wrapper_attributes', array( $this, 'add_pending_update_class' ), 10, 2 );
	}

	/**
	 * Track post meta updates
	 *
	 * Fires before post meta is updated. Compares old and new values
	 * to determine if the field has actually changed and needs sync.
	 *
	 * @param null|bool $check      Whether to allow updating metadata. Return non-null to short-circuit.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key being updated.
	 * @param mixed     $prev_value Previous value parameter used for conditional updates (not the actual current value in the database).
	 * @return null|bool Null to continue with update, bool to short-circuit
	 */
	public function track_meta_update( mixed $check, int $object_id, string $meta_key, mixed $meta_value ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Only track changes in original/source language posts.
		// Translation posts should not trigger sync flags for other translations.
		if ( ! WPML_Post_Helper::is_original_post( $object_id ) ) {
			return $check;
		}

		if ( Post_Data_Helper::has_meta_value_changed( $object_id, $meta_key, $meta_value ) ) {
			$this->flag_meta_field_for_sync( $object_id, $meta_key, $meta_value );
		}

		return $check;
	}

	/**
	 * Track post meta additions
	 *
	 * Fires before new post meta is added.
	 *
	 * @param null|bool $check      Whether to allow adding metadata. Return non-null to short-circuit.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key being added.
	 * @param mixed     $meta_value Meta value - unused but required by filter.
	 * @param bool      $unique     Whether the key should be unique - unused but required by filter.
	 * @return null|bool Null to continue with add, bool to short-circuit
	 */
	public function track_meta_add( mixed $check, int $object_id, string $meta_key, mixed $meta_value, bool $unique ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Only track changes in original/source language posts.
		// Translation posts should not trigger sync flags for other translations.
		if ( ! WPML_Post_Helper::is_original_post( $object_id ) ) {
			return $check;
		}

		$this->flag_meta_field_for_sync( $object_id, $meta_key, $meta_value );

		return $check;
	}

	/**
	 * Track post meta deletions
	 *
	 * Fires before post meta is deleted.
	 *
	 * @param null|bool $check      Whether to allow deleting metadata. Return non-null to short-circuit.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key being deleted.
	 * @param mixed     $meta_value Meta value (for targeted deletes) - unused but required by filter.
	 * @param bool      $delete_all Whether to delete all entries for this key - unused but required by filter.
	 * @return null|bool Null to continue with delete, bool to short-circuit
	 */
	public function track_meta_delete( mixed $check, int $object_id, string $meta_key, mixed $meta_value, bool $delete_all ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Only track changes in original/source language posts.
		// Translation posts should not trigger sync flags for other translations.
		if ( ! WPML_Post_Helper::is_original_post( $object_id ) ) {
			return $check;
		}

		$this->flag_meta_field_for_sync( $object_id, $meta_key, null );

		return $check;
	}

	/**
	 * Flag a meta field for sync across translations
	 *
	 * Adds the field to the list of fields that need to be synced to translations.
	 * Only flags translatable fields (ACF fields marked for translation or custom fields).
	 * Flags the field as pending on each translation post.
	 *
	 * @param int    $post_id    Post ID where change occurred.
	 * @param string $meta_key   Meta key that changed.
	 * @param mixed  $meta_value Optional. Meta value to check. If null, fetches from database.
	 */
	private function flag_meta_field_for_sync( int $post_id, string $meta_key, mixed $meta_value = null ): void {
		if ( ! get_post( $post_id ) ) {
			return;
		}

		if ( $this->should_skip_meta( $meta_key ) ) {
			return;
		}

		// Fetch meta value if not provided to avoid duplicate get_post_meta calls.
		if ( null === $meta_value ) {
			$meta_value = get_post_meta( $post_id, $meta_key, true );
		}

		if ( Translation_Handler::is_acf_field_key_reference( $meta_key, $meta_value ) ) {
			return;
		}

		$wpml_preference = Translation_Handler::get_wpml_translation_preference( $meta_key, $post_id );
		$is_translatable = ( 'translate' === $wpml_preference );

		/**
		 * Filter whether a meta field should be tracked for translation sync
		 *
		 * This allows non-ACF custom fields to be marked as translatable.
		 *
		 * @param bool   $is_translatable Whether field is translatable (ACF check result)
		 * @param string $meta_key        Meta key being checked
		 * @param int    $post_id         Post ID
		 */
		$is_translatable = apply_filters( 'multilingual_bridge_is_field_translatable', $is_translatable, $meta_key, $post_id );

		if ( ! $is_translatable ) {
			return;
		}

		Post_Data_Helper::flag_field_for_sync( $post_id, $meta_key, 'meta' );
	}

	/**
	 * Get pending meta updates
	 *
	 * Returns array of meta keys that need sync for a specific translation post.
	 *
	 * @param int $post_id Post ID (translation post).
	 * @return string[] Array of meta keys that need sync (e.g., ['field_123', 'custom_field'])
	 */
	public function get_pending_meta_updates( int $post_id ): array {
		$pending = Post_Data_Tracker::get_pending_updates( $post_id );

		if ( ! isset( $pending['meta'] ) || ! is_array( $pending['meta'] ) ) {
			return array();
		}

		return array_keys( array_filter( $pending['meta'] ) );
	}

	/**
	 * Check if post has pending meta updates
	 *
	 * Checks for pending updates in meta fields.
	 *
	 * @param int $post_id Post ID (translation post).
	 * @return bool True if post has meta fields pending sync
	 */
	public function has_pending_meta_updates( int $post_id ): bool {
		$pending = Post_Data_Tracker::get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['meta'] ) || ! is_array( $pending['meta'] ) ) {
			return false;
		}

		return ! empty( $pending['meta'] );
	}

	/**
	 * Clear pending meta updates for a post
	 *
	 * Marks sync operations as complete for meta fields.
	 *
	 * @param int         $post_id  Post ID (translation post).
	 * @param string|null $meta_key Optional. Clear only specific meta field. If null, clears all meta fields.
	 * @return bool True on success
	 */
	public function clear_pending_meta_updates( int $post_id, ?string $meta_key = null ): bool {
		$pending = Post_Data_Tracker::get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['meta'] ) ) {
			return false;
		}

		if ( null === $meta_key ) {
			// Clear all meta fields.
			unset( $pending['meta'] );
		} else {
			// Clear specific field.
			unset( $pending['meta'][ $meta_key ] );

			// Clean up empty meta array.
			if ( empty( $pending['meta'] ) ) {
				unset( $pending['meta'] );
			}
		}

		// If no pending updates remain, delete the meta and set last sync timestamp.
		if ( empty( $pending ) ) {
			delete_post_meta( $post_id, Post_Data_Tracker::get_sync_flag_meta_key() );
			update_post_meta( $post_id, Post_Data_Tracker::get_last_sync_meta_key(), time() );
			return true;
		}

		return update_post_meta( $post_id, Post_Data_Tracker::get_sync_flag_meta_key(), $pending );
	}

	/**
	 * Check if a specific meta field has pending updates
	 *
	 * Checks if a specific meta field needs sync.
	 *
	 * @param int    $post_id  Post ID (translation post).
	 * @param string $meta_key Meta key to check.
	 * @return bool True if field has pending updates
	 */
	public function has_pending_meta_field_update( int $post_id, string $meta_key ): bool {
		$pending = Post_Data_Tracker::get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['meta'][ $meta_key ] ) ) {
			return false;
		}

		return (bool) $pending['meta'][ $meta_key ];
	}

	/**
	 * Add pending update class to ACF field wrappers
	 *
	 * Adds 'mlb-pending-update' class to ACF fields that have pending updates
	 * in translation posts.
	 *
	 * @param array<string, mixed> $wrapper The field wrapper attributes.
	 * @param array<string, mixed> $field   The field array.
	 * @return array<string, mixed>
	 */
	public function add_pending_update_class( array $wrapper, array $field ): array {
		if ( ! is_admin() ) {
			return $wrapper;
		}

		global $post;

		if ( ! $post || ! isset( $post->ID ) ) {
			return $wrapper;
		}

		// Only add class to translation posts (not source posts).
		if ( WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return $wrapper;
		}

		$field_name = $field['_name'] ?? $field['name'] ?? '';
		if ( empty( $field_name ) ) {
			return $wrapper;
		}

		// Check pending updates on this translation post directly.
		if ( $this->has_pending_meta_field_update( $post->ID, $field_name ) ) {
			$wrapper['class'] = isset( $wrapper['class'] )
				? $wrapper['class'] . ' mlb-pending-update'
				: 'mlb-pending-update';
		}

		return $wrapper;
	}

	/**
	 * Check if meta key should be skipped
	 *
	 * Skips internal WordPress, WPML, and plugin meta fields.
	 * This method determines which meta keys should not be tracked for translation sync.
	 *
	 * @param string $meta_key Meta key to check.
	 * @return bool True if should skip
	 */
	private function should_skip_meta( string $meta_key ): bool {
		/**
		 * Filter the list of meta key prefixes to skip during translation tracking
		 *
		 * Allows developers to customize which meta keys should be excluded from
		 * translation sync tracking. By default, internal WordPress, WPML, and
		 * plugin-specific meta keys are skipped to avoid unnecessary tracking.
		 *
		 * @param string[] $skip_prefixes Array of meta key prefixes to skip.
		 *                                Default includes: '_wp_', '_edit_', '_oembed_', '_thumbnail_id'
		 *
		 * @example
		 * // Remove default prefixes (not recommended)
		 * add_filter( 'multilingual_bridge_should_skip_meta_tracking', function( $prefixes ) {
		 *     return array_diff( $prefixes, array( '_wp_' ) );
		 * } );
		 */
		$skip_prefixes = apply_filters( 'multilingual_bridge_should_skip_meta_tracking', array( '_wp_', '_edit_', '_oembed_', '_thumbnail_id' ) );

		foreach ( $skip_prefixes as $prefix ) {
			if ( str_starts_with( $meta_key, $prefix ) ) {
				return true;
			}
		}

		if ( str_starts_with( $meta_key, '_wpml_' ) || str_starts_with( $meta_key, 'wpml_' ) ) {
			return true;
		}

		if ( str_starts_with( $meta_key, '_mlb_' ) ) {
			return true;
		}

		return false; // Do not skip by default
	}
}
