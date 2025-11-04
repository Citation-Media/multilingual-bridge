<?php
/**
 * Sync Translations Handler
 *
 * Tracks post content and meta changes and flags when translations need to be synced.
 * Monitors post updates and metadata changes to detect when translatable fields
 * are modified in the source language post, then marks those fields for sync
 * across all translation posts.
 *
 * Features:
 * - Tracks post content changes (title, content, excerpt)
 * - Tracks meta field changes by comparing old vs new values
 * - Only tracks translatable fields (ACF fields marked for translation)
 * - Stores pending sync flags in source post meta
 * - Provides methods to retrieve and clear sync flags
 * - Ignores non-translatable fields and internal WordPress meta
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use Multilingual_Bridge\Integrations\ACF\ACF_Translation_Handler;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;

/**
 * Class Sync_Translations
 *
 * Handles translation sync tracking and flagging
 */
class Sync_Translations {

	/**
	 * Meta key for storing fields that need translation sync
	 *
	 * Stores a JSON object with changed fields: {title: true, content: true, meta: {field_name: true}}
	 *
	 * @var string
	 */
	private const SYNC_FLAG_META_KEY = '_mlb_updates_pending';

	/**
	 * Meta key for storing last sync timestamp
	 *
	 * @var string
	 */
	private const LAST_SYNC_META_KEY = '_mlb_last_sync';

	/**
	 * Post fields that should be tracked for changes
	 * Maps post field names to simplified flag names
	 *
	 * @var array<string, string>
	 */
	private const TRACKED_POST_FIELDS = array(
		'post_title'   => 'title',
		'post_content' => 'content',
		'post_excerpt' => 'excerpt',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// No dependencies needed - using static methods from ACF_Translation_Handler.
	}

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {
		// Track post content updates (title, content, excerpt).
		add_action( 'post_updated', array( $this, 'track_post_update' ), 10, 3 );

		// Track post meta updates (before the update happens).
		add_filter( 'update_post_metadata', array( $this, 'track_meta_update' ), 10, 5 );

		// Track post meta additions (new fields).
		add_filter( 'add_post_metadata', array( $this, 'track_meta_add' ), 10, 5 );

		// Track post meta deletions.
		add_filter( 'delete_post_metadata', array( $this, 'track_meta_delete' ), 10, 5 );
	}

	/**
	 * Track post content updates
	 *
	 * Fires after a post is updated. Compares the old and new post data
	 * to determine which fields (title, content, excerpt) have changed.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public function track_post_update( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		// Skip if this is an autosave or revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if post is not published or scheduled.
		if ( ! in_array( $post_after->post_status, array( 'publish', 'future' ), true ) ) {
			return;
		}

		// Check each tracked field for changes.
		foreach ( self::TRACKED_POST_FIELDS as $post_field => $flag_name ) {
			$old_value = $post_before->$post_field;
			$new_value = $post_after->$post_field;

			// Only flag if value actually changed.
			if ( $this->has_value_changed( $old_value, $new_value ) ) {
				$this->flag_content_field_for_sync( $post_id, $flag_name );
			}
		}
	}

	/**
	 * Track post meta updates
	 *
	 * Fires before post meta is updated. Compares old and new values
	 * to determine if the field has actually changed and needs sync.
	 *
	 * @param null|bool $check Whether to allow updating metadata. Return non-null to short-circuit.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key Meta key being updated.
	 * @param mixed     $meta_value New meta value.
	 * @param mixed     $prev_value Previous meta value (for targeted updates) - unused but required by filter.
	 * @return null|bool Null to continue with update, bool to short-circuit
	 */
	public function track_meta_update( $check, int $object_id, string $meta_key, $meta_value, $prev_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		// Early validation: Skip non-translatable fields before any database queries.
		// This avoids unnecessary function calls and checks for internal WordPress/plugin meta.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Don't interfere with the update process - just track it.
		// Use $prev_value if available and meaningful, otherwise query the database.
		$current_value = ( '' !== $prev_value && null !== $prev_value && ! ( is_array( $prev_value ) && empty( $prev_value ) ) )
			? $prev_value
			: get_post_meta( $object_id, $meta_key, true );

		// Only flag if value actually changed.
		if ( $this->has_value_changed( $current_value, $meta_value ) ) {
			$this->flag_field_for_sync( $object_id, $meta_key );
		}

		// Return null to allow WordPress to continue with the update.
		return $check;
	}

	/**
	 * Track post meta additions
	 *
	 * Fires before new post meta is added.
	 *
	 * @param null|bool $check Whether to allow adding metadata. Return non-null to short-circuit.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key Meta key being added.
	 * @param mixed     $meta_value Meta value - unused but required by filter.
	 * @param bool      $unique Whether the key should be unique - unused but required by filter.
	 * @return null|bool Null to continue with add, bool to short-circuit
	 */
	public function track_meta_add( $check, int $object_id, string $meta_key, $meta_value, bool $unique ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		// Early validation: Skip non-translatable fields before calling flag_field_for_sync().
		// This avoids unnecessary function calls and checks for internal WordPress/plugin meta.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Flag new fields for sync.
		$this->flag_field_for_sync( $object_id, $meta_key );

		// Return null to allow WordPress to continue with the add.
		return $check;
	}

	/**
	 * Track post meta deletions
	 *
	 * Fires before post meta is deleted.
	 *
	 * @param null|bool $check Whether to allow deleting metadata. Return non-null to short-circuit.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key Meta key being deleted.
	 * @param mixed     $meta_value Meta value (for targeted deletes) - unused but required by filter.
	 * @param bool      $delete_all Whether to delete all entries for this key - unused but required by filter.
	 * @return null|bool Null to continue with delete, bool to short-circuit
	 */
	public function track_meta_delete( $check, int $object_id, string $meta_key, $meta_value, bool $delete_all ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress filter.
		// Early validation: Skip non-translatable fields before calling flag_field_for_sync().
		// This avoids unnecessary function calls and checks for internal WordPress/plugin meta.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return $check;
		}

		// Flag deleted fields for sync (so translations can also delete them).
		$this->flag_field_for_sync( $object_id, $meta_key );

		// Return null to allow WordPress to continue with the delete.
		return $check;
	}

	/**
	 * Flag a post content field for sync across translations
	 *
	 * Adds the field to the list of fields that need to be synced to translations.
	 * This is for core post fields (title, content, excerpt) that are always translatable.
	 *
	 * @param int    $post_id    Post ID where change occurred.
	 * @param string $field_name Field name (title, content, excerpt).
	 */
	private function flag_content_field_for_sync( int $post_id, string $field_name ): void {
		// Skip if this is not a valid post.
		if ( ! get_post( $post_id ) ) {
			return;
		}

		// Get the source/original language post ID.
		// If this post is already the original, use it. Otherwise get the original.
		$original_post_id = WPML_Post_Helper::is_original_post( $post_id )
			? $post_id
			: WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $original_post_id ) {
			// No original post found - skip.
			return;
		}

		// Get current pending updates.
		$pending_updates = $this->get_pending_updates( $original_post_id );

		// Set field flag to true.
		$pending_updates[ $field_name ] = true;

		// Store updated pending list.
		update_post_meta( $original_post_id, self::SYNC_FLAG_META_KEY, $pending_updates );

		/**
		 * Fires when a content field is flagged for translation sync
		 *
		 * @param int    $original_post_id Original post ID where flag was set
		 * @param string $field_name       Field name that needs sync (title, content, excerpt)
		 * @param int    $post_id          Post ID where change occurred (may be translation)
		 */
		do_action( 'multilingual_bridge_content_field_flagged_for_sync', $original_post_id, $field_name, $post_id );
	}

	/**
	 * Flag a field for sync across translations
	 *
	 * Adds the field to the list of fields that need to be synced to translations.
	 * Only flags fields that are:
	 * - In the source/original language post
	 * - Translatable (ACF fields marked for translation)
	 * - Not internal WordPress or plugin meta
	 *
	 * @param int    $post_id  Post ID where change occurred.
	 * @param string $meta_key Meta key that changed.
	 */
	private function flag_field_for_sync( int $post_id, string $meta_key ): void {
		// Skip if this is not a valid post.
		if ( ! get_post( $post_id ) ) {
			return;
		}

		// Skip internal WordPress and plugin meta.
		if ( $this->should_skip_meta( $meta_key ) ) {
			return;
		}

		// Skip our own sync meta to prevent infinite loops.
		if ( self::SYNC_FLAG_META_KEY === $meta_key || self::LAST_SYNC_META_KEY === $meta_key ) {
			return;
		}

		// Skip ACF field key references (e.g., _field_name => "field_123abc").
		if ( ACF_Translation_Handler::is_acf_field_key_reference( $meta_key, get_post_meta( $post_id, $meta_key, true ) ) ) {
			return;
		}

		// Check if field is translatable.
		// Priority: Check ACF settings first, then allow custom filter override.
		$wpml_preference = ACF_Translation_Handler::get_wpml_translation_preference( $meta_key, $post_id );
		$is_translatable = ( 'translate' === $wpml_preference );

		/**
		 * Filter whether a meta field should be tracked for translation sync
		 *
		 * This allows non-ACF custom fields to be marked as translatable.
		 * Return true to mark the field as translatable, false to skip tracking.
		 *
		 * @param bool   $is_translatable Whether field is translatable (ACF check result)
		 * @param string $meta_key        Meta key being checked
		 * @param int    $post_id         Post ID
		 */
		$is_translatable = apply_filters( 'multilingual_bridge_is_field_translatable', $is_translatable, $meta_key, $post_id );

		if ( ! $is_translatable ) {
			// Not marked for translation - skip.
			return;
		}

		// Get the source/original language post ID.
		// If this post is already the original, use it. Otherwise get the original.
		$original_post_id = WPML_Post_Helper::is_original_post( $post_id )
			? $post_id
			: WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $original_post_id ) {
			// No original post found - skip.
			return;
		}

		// Get current pending updates.
		$pending_updates = $this->get_pending_updates( $original_post_id );

		// Initialize meta array if not set.
		if ( ! isset( $pending_updates['meta'] ) ) {
			$pending_updates['meta'] = array();
		}

		// Set meta field flag to true.
		$pending_updates['meta'][ $meta_key ] = true;

		// Store updated pending list.
		update_post_meta( $original_post_id, self::SYNC_FLAG_META_KEY, $pending_updates );

		/**
		 * Fires when a meta field is flagged for translation sync
		 *
		 * @param int    $original_post_id Original post ID where flag was set
		 * @param string $meta_key         Meta key that needs sync
		 * @param int    $post_id          Post ID where change occurred (may be translation)
		 */
		do_action( 'multilingual_bridge_field_flagged_for_sync', $original_post_id, $meta_key, $post_id );
	}

	/**
	 * Get pending updates for a post
	 *
	 * Returns an array with boolean flags for content fields and nested meta fields.
	 * Structure: {title: true, content: true, excerpt: true, meta: {field_name: true}}
	 *
	 * @param int $post_id Post ID (should be original/source post).
	 * @return array<string, bool|array<string, bool>> Array of pending updates
	 */
	public function get_pending_updates( int $post_id ): array {
		$pending = get_post_meta( $post_id, self::SYNC_FLAG_META_KEY, true );

		if ( ! is_array( $pending ) ) {
			return array();
		}

		return $pending;
	}

	/**
	 * Check if post has pending updates
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $type    Optional. Filter by type: 'content', 'meta', or null for all.
	 * @return bool True if post has fields pending sync
	 */
	public function has_pending_updates( int $post_id, ?string $type = null ): bool {
		$pending = $this->get_pending_updates( $post_id );

		if ( empty( $pending ) ) {
			return false;
		}

		// If no type filter, return true if any pending updates exist.
		if ( null === $type ) {
			return true;
		}

		// Check for content updates.
		if ( 'content' === $type ) {
			return ! empty( $this->get_pending_content_updates( $post_id ) );
		}

		// Check for meta updates.
		if ( 'meta' === $type ) {
			return ! empty( $this->get_pending_meta_updates( $post_id ) );
		}

		return false;
	}

	/**
	 * Get pending content updates (title, content, excerpt)
	 *
	 * @param int $post_id Post ID (should be original/source post).
	 * @return string[] Array of content field names that need sync (e.g., ['title', 'content'])
	 */
	public function get_pending_content_updates( int $post_id ): array {
		$all_pending    = $this->get_pending_updates( $post_id );
		$content_fields = array();

		// Check each possible content field.
		foreach ( array( 'title', 'content', 'excerpt' ) as $field ) {
			if ( ! empty( $all_pending[ $field ] ) ) {
				$content_fields[] = $field;
			}
		}

		return $content_fields;
	}

	/**
	 * Get pending meta updates
	 *
	 * @param int $post_id Post ID (should be original/source post).
	 * @return string[] Array of meta keys that need sync (e.g., ['field_123', 'custom_field'])
	 */
	public function get_pending_meta_updates( int $post_id ): array {
		$all_pending = $this->get_pending_updates( $post_id );

		// Return keys from the nested meta object.
		if ( isset( $all_pending['meta'] ) && is_array( $all_pending['meta'] ) ) {
			return array_keys( array_filter( $all_pending['meta'] ) );
		}

		return array();
	}

	/**
	 * Clear pending updates for a post
	 *
	 * Call this after successfully syncing translations.
	 *
	 * @param int         $post_id    Post ID.
	 * @param string|null $field_name Optional. Clear only specific field (content or meta key). If null, clears all.
	 * @return bool True on success
	 */
	public function clear_pending_updates( int $post_id, ?string $field_name = null ): bool {
		if ( null === $field_name ) {
			// Clear all pending updates.
			$result = delete_post_meta( $post_id, self::SYNC_FLAG_META_KEY );

			// Update last sync timestamp.
			update_post_meta( $post_id, self::LAST_SYNC_META_KEY, time() );

			/**
			 * Fires after all pending updates are cleared
			 *
			 * @param int $post_id Post ID
			 */
			do_action( 'multilingual_bridge_sync_completed', $post_id );

			return $result;
		}

		// Clear specific field.
		$pending = $this->get_pending_updates( $post_id );

		// Check if it's a content field (title, content, excerpt).
		if ( in_array( $field_name, array( 'title', 'content', 'excerpt' ), true ) ) {
			if ( ! isset( $pending[ $field_name ] ) ) {
				// Field not in pending list.
				return false;
			}
			unset( $pending[ $field_name ] );
		} else {
			// It's a meta field - check nested meta object.
			if ( ! isset( $pending['meta'][ $field_name ] ) ) {
				// Field not in pending list.
				return false;
			}
			unset( $pending['meta'][ $field_name ] );

			// If meta object is now empty, remove it entirely.
			if ( empty( $pending['meta'] ) ) {
				unset( $pending['meta'] );
			}
		}

		if ( empty( $pending ) ) {
			// No more pending updates - delete the meta.
			return $this->clear_pending_updates( $post_id );
		}

		// Update with remaining pending items.
		return update_post_meta( $post_id, self::SYNC_FLAG_META_KEY, $pending );
	}

	/**
	 * Get last sync timestamp
	 *
	 * @param int $post_id Post ID.
	 * @return int|null Timestamp of last sync, or null if never synced
	 */
	public function get_last_sync_time( int $post_id ): ?int {
		$timestamp = get_post_meta( $post_id, self::LAST_SYNC_META_KEY, true );

		if ( empty( $timestamp ) ) {
			return null;
		}

		return (int) $timestamp;
	}

	/**
	 * Compare two values to determine if they've changed
	 *
	 * Handles different data types appropriately.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 * @return bool True if values are different
	 */
	private function has_value_changed( $old_value, $new_value ): bool {
		// Use strict comparison for all types.
		// PHP's !== operator handles arrays and objects correctly:
		// - Arrays are compared element by element
		// - Objects are compared by reference (which is what we want for change detection)
		return $old_value !== $new_value;
	}

	/**
	 * Check if meta key should be skipped
	 *
	 * @param string $meta_key Meta key to check.
	 * @return bool True if should skip
	 */
	private function should_skip_meta( string $meta_key ): bool {
		// Skip internal WordPress meta.
		$skip_prefixes = array( '_wp_', '_edit_', '_oembed_', '_thumbnail_id' );

		foreach ( $skip_prefixes as $prefix ) {
			if ( str_starts_with( $meta_key, $prefix ) ) {
				return true;
			}
		}

		// Skip WPML meta.
		if ( str_starts_with( $meta_key, '_wpml_' ) || str_starts_with( $meta_key, 'wpml_' ) ) {
			return true;
		}

		// Skip our own meta.
		if ( str_starts_with( $meta_key, '_mlb_' ) ) {
			return true;
		}

		/**
		 * Filter whether to skip meta key for sync tracking
		 *
		 * @param bool   $should_skip Whether to skip
		 * @param string $meta_key    Meta key
		 */
		return apply_filters( 'multilingual_bridge_skip_sync_tracking', false, $meta_key );
	}
}
