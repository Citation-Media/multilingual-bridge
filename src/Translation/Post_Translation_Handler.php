<?php
/**
 * Post Translation Handler
 *
 * Handles translation of WordPress posts and their content to multiple languages.
 * This class manages the complete post translation workflow including:
 * - Creating new translated posts
 * - Updating existing translations
 * - Translating post content (title, content, excerpt)
 * - Managing WPML relationships
 * - Copying custom fields
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use PrinsFrank\Standards\LanguageTag\LanguageTag;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Translation\Change_Tracking\Post_Data_Tracker;
use Multilingual_Bridge\Translation\Change_Tracking\Post_Meta_Tracker;
use WP_Error;
use WP_Post;
use RuntimeException;
use InvalidArgumentException;

/**
 * Class Post_Translation_Handler
 *
 * Manages post translation operations
 *
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not directly output to HTML
 */
class Post_Translation_Handler {

	/**
	 * Translation Manager instance
	 *
	 * @var Translation_Manager
	 */
	private Translation_Manager $translation_manager;

	/**
	 * Meta Translation Handler instance
	 *
	 * @var Meta_Translation_Handler
	 */
	private Meta_Translation_Handler $meta_handler;

	/**
	 * Post Content Tracker instance
	 *
	 * @var Post_Data_Tracker
	 */
	private Post_Data_Tracker $content_tracker;

	/**
	 * Post Meta Tracker instance
	 *
	 * @var Post_Meta_Tracker
	 */
	private Post_Meta_Tracker $meta_tracker;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->meta_handler        = new Meta_Translation_Handler();
		$this->content_tracker     = new Post_Data_Tracker();
		$this->meta_tracker        = new Post_Meta_Tracker();
	}

	/**
	 * Translate post to a single target language
	 *
	 * @param int         $post_id         Source post ID.
	 * @param LanguageTag $target_language Target language tag.
	 * @return array<string, mixed> Translation result
	 * @throws InvalidArgumentException If post not found, not source post, or invalid language code.
	 * @throws RuntimeException If translation, post creation, or post update fails.
	 *
	 * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
	 */
	public function translate_post( int $post_id, LanguageTag $target_language ): array {
		// Verify post exists.
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			throw new InvalidArgumentException(
				__( 'Source post not found', 'multilingual-bridge' )
			);
		}

		// Verify post is original/source language.
		if ( ! WPML_Post_Helper::is_original_post( $post_id ) ) {
			throw new InvalidArgumentException(
				__( 'Post is not a source language post', 'multilingual-bridge' )
			);
		}

		$source_language = WPML_Post_Helper::get_language( $post_id );

		// Translate to the target language.
		$result = $this->translate_to_language(
			$post_id,
			$source_post,
			$source_language,
			$target_language
		);

		return $result;
	}

	/**
	 * Translate post to a single target language
	 *
	 * @param int         $source_post_id Source post ID.
	 * @param WP_Post     $source_post    Source post object.
	 * @param string      $source_lang    Source language code (WPML format).
	 * @param LanguageTag $target_lang_tag Target language tag object.
	 * @return array<string, mixed> Translation result
	 * @throws InvalidArgumentException If language code is invalid.
	 * @throws RuntimeException If translation fails.
	 */
	private function translate_to_language( int $source_post_id, WP_Post $source_post, string $source_lang, LanguageTag $target_lang_tag ): array {
		$target_lang = strtolower( $target_lang_tag->toString() );

		// Check if translation already exists.
		$existing_translation = WPML_Post_Helper::get_translation_for_lang( $source_post_id, $target_lang );
		try {
			// Convert source language to LanguageTag.
			$source_lang_tag = LanguageTag::tryFromString( $source_lang );
		} catch ( \Exception $e ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %s: language code */
					__( 'Invalid source language code: %s', 'multilingual-bridge' ),
					$source_lang
				)
			);
		}

		if ( $existing_translation ) {
			// Update existing translation.
			$target_post_id = $this->update_translation_post( $source_post, $existing_translation, $source_lang_tag, $target_lang_tag );
			$created_new    = false;
		} else {
			// Create new translation post.
			$target_post_id = $this->create_translation_post( $source_post, $source_post_id, $target_lang, $target_lang_tag, $source_lang_tag );
			$created_new    = true;
		}

		// Translate post meta.
		$meta_results = $this->meta_handler->translate_post_meta(
			$source_post_id,
			$target_post_id,
			$target_lang_tag,
			$source_lang_tag
		);

		// Collect meta translation errors.
		$meta_errors = array();
		if ( ! empty( $meta_results['errors'] ) ) {
			foreach ( $meta_results['errors'] as $field_key => $error_message ) {
				$meta_errors[] = sprintf(
					/* translators: 1: field key, 2: error message */
					__( 'Field "%1$s": %2$s', 'multilingual-bridge' ),
					$field_key,
					$error_message
				);
			}
		}

		// Trigger wp_update_post to fire WordPress hooks (save_post, etc.) after all changes are complete.
		// This ensures that other plugins and systems are notified of the translation updates.
		$update_result = wp_update_post(
			array(
				'ID'          => $target_post_id,
				'post_status' => get_post_status( $target_post_id ), // Preserve current status.
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to update post: %s', 'multilingual-bridge' ),
					$update_result->get_error_message()
				)
			);
		}

		// If there are meta translation errors, throw exception with all errors.
		if ( ! empty( $meta_errors ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: combined error messages */
					__( 'Meta translation errors: %s', 'multilingual-bridge' ),
					implode( '; ', $meta_errors )
				)
			);
		}

		// Clear pending updates from the translation post after successful translation.
		$this->content_tracker->clear_pending_content_updates( $target_post_id );
		$this->meta_tracker->clear_pending_meta_updates( $target_post_id );

		// Return success result.
		return array(
			'success'            => true,
			'translated_post_id' => $target_post_id,
			'created_new'        => $created_new,
			'meta_translated'    => $meta_results['translated'],
			'meta_skipped'       => $meta_results['skipped'],
		);
	}

	/**
	 * Translate post content and build post data array
	 *
	 * Combines translation and post data construction into single operation.
	 *
	 * @param WP_Post     $source_post    Source post object.
	 * @param LanguageTag $target_lang    Target language tag.
	 * @param LanguageTag $source_lang    Source language tag.
	 * @param int|null    $target_post_id Target post ID (null for new posts).
	 * @return array<string, mixed> Post data array
	 * @throws RuntimeException If translation fails.
	 */
	private function translate_and_build_post_data( WP_Post $source_post, LanguageTag $target_lang, LanguageTag $source_lang, ?int $target_post_id = null ): array {
		// Translate post title (always required).
		$translated_title = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_title,
			$source_lang
		);

		if ( is_wp_error( $translated_title ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to translate post title: %s', 'multilingual-bridge' ),
					$translated_title->get_error_message()
				)
			);
		}

		// Translate post content.
		$translated_content = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_content,
			$source_lang
		);

		if ( is_wp_error( $translated_content ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to translate post content: %s', 'multilingual-bridge' ),
					$translated_content->get_error_message()
				)
			);
		}

		// Translate post excerpt.
		$translated_excerpt = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_excerpt,
			$source_lang
		);

		if ( is_wp_error( $translated_excerpt ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to translate post excerpt: %s', 'multilingual-bridge' ),
					$translated_excerpt->get_error_message()
				)
			);
		}

		// Build post data with translated content.
		$post_data = array(
			'post_title'   => $translated_title,
			'post_content' => $translated_content,
			'post_excerpt' => $translated_excerpt,
		);

		if ( null === $target_post_id ) {
			// For new posts, include additional fields.
			$post_data['post_status'] = 'draft'; // Create as draft for review.
			$post_data['post_type']   = $source_post->post_type;
			$post_data['post_author'] = (int) $source_post->post_author;
		} else {
			// For updates, include the post ID.
			$post_data['ID'] = $target_post_id;
		}

		return $post_data;
	}

	/**
	 * Copy WPML custom fields from source to target post
	 *
	 * Copies custom fields marked as "Copy" in WPML settings.
	 * This ensures ACF fields and other custom fields configured as "Copy" mode
	 * are properly copied/synced between translations.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return void
	 */
	private function copy_wpml_custom_fields( int $source_post_id, int $target_post_id ): void {
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'copy_custom_fields' ) ) {
			$sitepress->copy_custom_fields( $source_post_id, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post_id, $target_post_id );
	}

	/**
	 * Create a new translation post
	 *
	 * @param WP_Post     $source_post     Source post object.
	 * @param int         $source_post_id  Source post ID.
	 * @param string      $target_lang     Target language code (WPML format).
	 * @param LanguageTag $target_lang_tag Target language tag.
	 * @param LanguageTag $source_lang_tag Source language tag.
	 * @return int Target post ID
	 * @throws RuntimeException If post creation fails.
	 */
	private function create_translation_post( WP_Post $source_post, int $source_post_id, string $target_lang, LanguageTag $target_lang_tag, LanguageTag $source_lang_tag ): int {
		// Translate and build post data.
		$post_data = $this->translate_and_build_post_data( $source_post, $target_lang_tag, $source_lang_tag );

		// Insert post.
		$target_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $target_post_id ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to create translation post: %s', 'multilingual-bridge' ),
					$target_post_id->get_error_message()
				)
			);
		}

		// Set language for new post (using WPML format).
		$wpml_result = WPML_Post_Helper::set_language( $target_post_id, $target_lang );

		if ( is_wp_error( $wpml_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to set language for translation: %s', 'multilingual-bridge' ),
					$wpml_result->get_error_message()
				)
			);
		}

		// Now relate the posts as translations (using WPML format).
		$relation_result = WPML_Post_Helper::relate_posts_as_translations( $target_post_id, $source_post_id, $target_lang );

		if ( is_wp_error( $relation_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to relate posts as translations: %s', 'multilingual-bridge' ),
					$relation_result->get_error_message()
				)
			);
		}

		// Copy WPML custom fields.
		$this->copy_wpml_custom_fields( $source_post_id, $target_post_id );

		return $target_post_id;
	}

	/**
	 * Update an existing translation post with fresh translations
	 *
	 * @param WP_Post     $source_post    Source post object.
	 * @param int         $target_post_id Existing translation post ID.
	 * @param LanguageTag $source_lang    Source language tag.
	 * @param LanguageTag $target_lang    Target language tag.
	 * @return int Target post ID
	 * @throws RuntimeException If post update fails.
	 */
	private function update_translation_post( WP_Post $source_post, int $target_post_id, LanguageTag $source_lang, LanguageTag $target_lang ): int {
		// Translate and build post data.
		$post_data = $this->translate_and_build_post_data( $source_post, $target_lang, $source_lang, $target_post_id );

		// Update post.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to update translation post: %s', 'multilingual-bridge' ),
					$result->get_error_message()
				)
			);
		}

		/*
		 * This line copies custom fields (including ACF fields) from
		 * the source post to the translated post,
		 * but only those fields configured in WPML as "Copy".
		 * This ensures that custom field data remains consistent across translations, which is essential for multilingual sites using custom fields.
		 */
		$this->copy_wpml_custom_fields( $source_post->ID, $target_post_id );

		return $target_post_id;
	}
}
