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
use WP_Error;
use WP_Post;

/**
 * Class Post_Translation_Handler
 *
 * Manages post translation operations
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
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->meta_handler        = new Meta_Translation_Handler();
	}

	/**
	 * Translate post to a single target language
	 *
	 * @param int         $post_id         Source post ID.
	 * @param LanguageTag $target_language Target language tag.
	 * @return array<string, mixed>|WP_Error Translation result or error
	 */
	public function translate_post( int $post_id, LanguageTag $target_language ): array|WP_Error {
		// Verify post exists.
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Source post not found', 'multilingual-bridge' ),
				array( 'status' => 404 )
			);
		}

		// Verify post is original/source language.
		if ( ! WPML_Post_Helper::is_original_post( $post_id ) ) {
			return new WP_Error(
				'not_source_post',
				__( 'Post is not a source language post', 'multilingual-bridge' ),
				array( 'status' => 400 )
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
	 * @return array<string, mixed>|WP_Error Translation result or error
	 */
	private function translate_to_language( int $source_post_id, WP_Post $source_post, string $source_lang, LanguageTag $target_lang_tag ): array|WP_Error {
		$target_lang = strtolower( $target_lang_tag->toString() );

		// Check if translation already exists.
		$existing_translation = WPML_Post_Helper::get_translation_for_lang( $source_post_id, $target_lang );
		try {
			// Convert source language to LanguageTag.
			$source_lang_tag = LanguageTag::tryFromString( $source_lang );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_source_language',
				sprintf(
				/* translators: %s: language code */
					__( 'Invalid source language code: %s', 'multilingual-bridge' ),
					$source_lang
				),
				array( 'status' => 400 )
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

		// Handle translation errors.
		if ( is_wp_error( $target_post_id ) ) {
			return $target_post_id;
		}

		// Translate post meta.
		$meta_results = $this->meta_handler->translate_post_meta(
			$source_post_id,
			$target_post_id,
			$target_lang_tag,
			$source_lang_tag
		);

		// Accumulate errors using WP_Error.
		$errors = new WP_Error();

		// Add meta translation errors if any.
		if ( ! empty( $meta_results['errors'] ) ) {
			foreach ( $meta_results['errors'] as $error_message ) {
				$errors->add( 'meta_translation_error', $error_message );
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
			$errors->add(
				'post_update_failed',
				$update_result->get_error_message()
			);
		}

		// If there are critical errors, return WP_Error.
		if ( $errors->has_errors() ) {
			return $errors;
		}

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
	 * @return array<string, mixed>|WP_Error Post data array or error
	 */
	private function translate_and_build_post_data( WP_Post $source_post, LanguageTag $target_lang, LanguageTag $source_lang, ?int $target_post_id = null ): array|WP_Error {
		// Translate post title (always required).
		$translated_title = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_title,
			$source_lang
		);

		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate post content.
		$translated_content = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_content,
			$source_lang
		);

		if ( is_wp_error( $translated_content ) ) {
			return $translated_content;
		}

		// Translate post excerpt.
		$translated_excerpt = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_excerpt,
			$source_lang
		);

		if ( is_wp_error( $translated_excerpt ) ) {
			return $translated_excerpt;
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
	 * @return int|WP_Error Target post ID or error
	 */
	private function create_translation_post( WP_Post $source_post, int $source_post_id, string $target_lang, LanguageTag $target_lang_tag, LanguageTag $source_lang_tag ): int|WP_Error {
		// Translate and build post data.
		$post_data = $this->translate_and_build_post_data( $source_post, $target_lang_tag, $source_lang_tag );

		if ( is_wp_error( $post_data ) ) {
			return $post_data;
		}

		// Insert post.
		$target_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $target_post_id ) ) {
			return $target_post_id;
		}

		// Set language for new post (using WPML format).
		$wpml_result = WPML_Post_Helper::set_language( $target_post_id, $target_lang );

		if ( is_wp_error( $wpml_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $wpml_result;
		}

		// Now relate the posts as translations (using WPML format).
		$relation_result = WPML_Post_Helper::relate_posts_as_translations( $target_post_id, $source_post_id, $target_lang );

		if ( is_wp_error( $relation_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $relation_result;
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
	 * @return int|WP_Error Target post ID or error
	 */
	private function update_translation_post( WP_Post $source_post, int $target_post_id, LanguageTag $source_lang, LanguageTag $target_lang ): int|WP_Error {
		// Translate and build post data.
		$post_data = $this->translate_and_build_post_data( $source_post, $target_lang, $source_lang, $target_post_id );

		if ( is_wp_error( $post_data ) ) {
			return $post_data;
		}

		// Update post.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
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
