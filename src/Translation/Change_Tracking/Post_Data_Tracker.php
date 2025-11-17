<?php
/**
 * Post Content Tracker
 *
 * Tracks post content field changes (title, content, excerpt) and flags when translations need to be synced.
 * Monitors post updates to detect when translatable core fields are modified in the source language post,
 * then marks those fields for sync across all translation posts.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation\Change_Tracking;

use Multilingual_Bridge\Helpers\Post_Data_Helper;

/**
 * Class Post_Content_Tracker
 *
 * Handles translation sync tracking for post content fields (title, content, excerpt)
 */
class Post_Data_Tracker {

	/**
	 * Meta key for storing fields that need translation sync
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
	 * Get sync flag meta key
	 *
	 * Returns the meta key used for storing pending updates.
	 * Used by both Post_Data_Tracker and Post_Meta_Tracker.
	 *
	 * @return string Meta key for sync flags
	 */
	public static function get_sync_flag_meta_key(): string {
		return self::SYNC_FLAG_META_KEY;
	}

	/**
	 * Get last sync meta key
	 *
	 * Returns the meta key used for storing last sync timestamp.
	 * Used by both Post_Data_Tracker and Post_Meta_Tracker.
	 *
	 * @return string Meta key for last sync timestamp
	 */
	public static function get_last_sync_meta_key(): string {
		return self::LAST_SYNC_META_KEY;
	}

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {
		add_action( 'post_updated', array( $this, 'track_post_update' ), 10, 3 );
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
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post_after->post_status, array( 'publish', 'future' ), true ) ) {
			return;
		}

		foreach ( self::TRACKED_POST_FIELDS as $post_field => $flag_name ) {
			$old_value = $post_before->$post_field;
			$new_value = $post_after->$post_field;

			if ( Post_Data_Helper::has_value_changed( $old_value, $new_value ) ) {
				$this->flag_content_field_for_sync( $post_id, $flag_name );
			}
		}
	}

	/**
	 * Flag a post content field for sync across translations
	 *
	 * Adds the field to the list of fields that need to be synced to translations.
	 * Flags the field as pending on each translation post.
	 *
	 * @param int    $post_id    Post ID where change occurred.
	 * @param string $field_name Field name (title, content, excerpt).
	 */
	private function flag_content_field_for_sync( int $post_id, string $field_name ): void {
		Post_Data_Helper::flag_field_for_sync( $post_id, $field_name, 'content' );
	}

	/**
	 * Get pending updates for a post
	 *
	 * Returns pending updates stored on the given post (should be translation post).
	 *
	 * @param int $post_id Post ID (translation post).
	 * @return array<string, mixed> Array of pending updates
	 */
	public function get_pending_updates( int $post_id ): array {
		$pending = get_post_meta( $post_id, self::SYNC_FLAG_META_KEY, true );

		if ( ! is_array( $pending ) ) {
			return array();
		}

		return $pending;
	}

	/**
	 * Get pending content updates (title, content, excerpt)
	 *
	 * Returns array of content field names that need sync for a specific translation post.
	 *
	 * @param int $post_id Post ID (translation post).
	 * @return string[] Array of content field names that need sync (e.g., ['title', 'content'])
	 */
	public function get_pending_content_updates( int $post_id ): array {
		$pending = $this->get_pending_updates( $post_id );

		if ( ! isset( $pending['content'] ) || ! is_array( $pending['content'] ) ) {
			return array();
		}

		return array_keys( array_filter( $pending['content'] ) );
	}

	/**
	 * Check if post has pending content updates
	 *
	 * Checks for pending updates in title, content, or excerpt fields.
	 *
	 * @param int $post_id Post ID (translation post).
	 * @return bool True if post has content fields pending sync
	 */
	public function has_pending_content_updates( int $post_id ): bool {
		$pending = $this->get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['content'] ) || ! is_array( $pending['content'] ) ) {
			return false;
		}

		// Check if any content field is flagged.
		$content_fields = array( 'title', 'content', 'excerpt' );
		foreach ( $content_fields as $field ) {
			if ( ! empty( $pending['content'][ $field ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clear pending content updates for a post
	 *
	 * Marks sync operations as complete for content fields.
	 *
	 * @param int         $post_id    Post ID (translation post).
	 * @param string|null $field_name Optional. Clear only specific field (title, content, excerpt). If null, clears all content fields.
	 * @return bool True on success
	 */
	public function clear_pending_content_updates( int $post_id, ?string $field_name = null ): bool {
		$pending = $this->get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['content'] ) ) {
			return false;
		}

		if ( null === $field_name ) {
			// Clear all content fields.
			unset( $pending['content'] );
		} else {
			// Clear specific field.
			unset( $pending['content'][ $field_name ] );

			// Clean up empty content array.
			if ( empty( $pending['content'] ) ) {
				unset( $pending['content'] );
			}
		}

		// If no pending updates remain, delete the meta and set last sync timestamp.
		if ( empty( $pending ) ) {
			delete_post_meta( $post_id, self::SYNC_FLAG_META_KEY );
			update_post_meta( $post_id, self::LAST_SYNC_META_KEY, time() );
			return true;
		}

		return update_post_meta( $post_id, self::SYNC_FLAG_META_KEY, $pending );
	}

	/**
	 * Check if a specific content field has pending updates
	 *
	 * Checks if a specific content field (title, content, excerpt) needs sync.
	 *
	 * @param int    $post_id    Post ID (translation post).
	 * @param string $field_name Field name (title, content, or excerpt).
	 * @return bool True if field has pending updates
	 */
	public function has_pending_content_field_update( int $post_id, string $field_name ): bool {
		if ( ! in_array( $field_name, array( 'title', 'content', 'excerpt' ), true ) ) {
			return false;
		}

		$pending = $this->get_pending_updates( $post_id );

		if ( empty( $pending ) || ! isset( $pending['content'][ $field_name ] ) ) {
			return false;
		}

		return (bool) $pending['content'][ $field_name ];
	}
}
