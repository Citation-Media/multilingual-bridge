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
	 * Translate post to multiple target languages
	 *
	 * @param int      $post_id           Source post ID.
	 * @param string[] $target_languages  Array of target language codes.
	 * @return array<string, mixed> Translation results
	 */
	public function translate_post( int $post_id, array $target_languages ): array {
		// Verify post exists.
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return array(
				'success'    => false,
				'error'      => __( 'Source post not found', 'multilingual-bridge' ),
				'error_code' => 'post_not_found',
			);
		}

		// Verify post is original/source language.
		if ( ! WPML_Post_Helper::is_original_post( $post_id ) ) {
			return array(
				'success'    => false,
				'error'      => __( 'Post is not a source language post', 'multilingual-bridge' ),
				'error_code' => 'not_source_post',
			);
		}

		$source_language = WPML_Post_Helper::get_language( $post_id );
		$results         = array(
			'success'     => true,
			'source_post' => $post_id,
			'languages'   => array(),
		);

		// Process each target language.
		foreach ( $target_languages as $target_lang ) {
			$language_result = $this->translate_to_language(
				$post_id,
				$source_post,
				$source_language,
				$target_lang
			);

			$results['languages'][ $target_lang ] = $language_result;

			// Mark overall success as false if any language fails.
			if ( ! $language_result['success'] ) {
				$results['success'] = false;
			}
		}

		return $results;
	}

	/**
	 * Translate post to a single target language
	 *
	 * @param int     $source_post_id Source post ID.
	 * @param WP_Post $source_post    Source post object.
	 * @param string  $source_lang    Source language code.
	 * @param string  $target_lang    Target language code.
	 * @return array<string, mixed> Translation result
	 */
	private function translate_to_language( int $source_post_id, WP_Post $source_post, string $source_lang, string $target_lang ): array {
		$result = array(
			'success'         => false,
			'target_post_id'  => 0,
			'created_new'     => false,
			'meta_translated' => 0,
			'meta_skipped'    => 0,
			'errors'          => array(),
		);

		// Check if translation already exists.
		$existing_translation = WPML_Post_Helper::get_translation_for_lang( $source_post_id, $target_lang );

		if ( $existing_translation ) {
			// Update existing translation.
			$target_post_id = $this->update_translation_post( $source_post, $existing_translation, $source_lang, $target_lang );

			if ( is_wp_error( $target_post_id ) ) {
				$result['errors'][] = $target_post_id->get_error_message();
				return $result;
			}

			$result['target_post_id'] = $target_post_id;
			$result['created_new']    = false;
		} else {
			// Create new translation post.
			$target_post_id = $this->create_translation_post( $source_post, $source_post_id, $target_lang );

			if ( is_wp_error( $target_post_id ) ) {
				$result['errors'][] = $target_post_id->get_error_message();
				return $result;
			}

			$result['target_post_id'] = $target_post_id;
			$result['created_new']    = true;
		}

		// Translate post meta.
		$meta_results = $this->meta_handler->translate_post_meta(
			$source_post_id,
			$target_post_id,
			$target_lang,
			$source_lang
		);

		$result['meta_translated'] = $meta_results['translated'];
		$result['meta_skipped']    = $meta_results['skipped'];

		// Only include actual critical errors (not skip errors).
		if ( ! empty( $meta_results['errors'] ) ) {
			$result['errors'] = array_merge( $result['errors'], $meta_results['errors'] );
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
			$result['errors'][] = $update_result->get_error_message();
		}

		// Consider success if:
		// 1. Target post exists
		// 2. Either some meta was translated, OR there were no critical errors in meta translation.
		$has_critical_errors = ! empty( $meta_results['errors'] );
		$result['success']   = $target_post_id > 0 && ! $has_critical_errors;

		return $result;
	}

	/**
	 * Translate post content (title, content, excerpt)
	 *
	 * @param WP_Post $source_post Source post object.
	 * @param string  $target_lang Target language code.
	 * @param string  $source_lang Source language code.
	 * @return array{title: string, content: string, excerpt: string}|WP_Error Translated content or error
	 */
	private function translate_post_content( WP_Post $source_post, string $target_lang, string $source_lang ) {
		// Translate post title.
		$translated_title = $this->translation_manager->translate(
			$source_post->post_title,
			$target_lang,
			$source_lang
		);

		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate post content (if not empty).
		$translated_content = '';
		if ( ! empty( $source_post->post_content ) ) {
			$translated_content = $this->translation_manager->translate(
				$source_post->post_content,
				$target_lang,
				$source_lang
			);

			if ( is_wp_error( $translated_content ) ) {
				return $translated_content;
			}
		}

		// Translate post excerpt (if not empty).
		$translated_excerpt = '';
		if ( ! empty( $source_post->post_excerpt ) ) {
			$translated_excerpt = $this->translation_manager->translate(
				$source_post->post_excerpt,
				$target_lang,
				$source_lang
			);

			if ( is_wp_error( $translated_excerpt ) ) {
				return $translated_excerpt;
			}
		}

		return array(
			'title'   => $translated_title,
			'content' => $translated_content,
			'excerpt' => $translated_excerpt,
		);
	}

	/**
	 * Create a new translation post
	 *
	 * @param WP_Post $source_post    Source post object.
	 * @param int     $source_post_id Source post ID.
	 * @param string  $target_lang    Target language code.
	 * @return int|WP_Error Target post ID or error
	 */
	private function create_translation_post( WP_Post $source_post, int $source_post_id, string $target_lang ) {
		// Get source language for translation.
		$source_lang = WPML_Post_Helper::get_language( $source_post_id );

		// Translate post content.
		$translated = $this->translate_post_content( $source_post, $target_lang, $source_lang );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		// Create post data with translated content.
		$post_data = array(
			'post_title'   => $translated['title'],
			'post_content' => $translated['content'],
			'post_excerpt' => $translated['excerpt'],
			'post_status'  => 'draft', // Create as draft for review.
			'post_type'    => $source_post->post_type,
			'post_author'  => (int) $source_post->post_author,
		);

		// Insert post.
		$target_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $target_post_id ) ) {
			return $target_post_id;
		}

		// Set language for new post.
		$wpml_result = WPML_Post_Helper::set_language( $target_post_id, $target_lang );

		if ( is_wp_error( $wpml_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $wpml_result;
		}

		// Now relate the posts as translations.
		$relation_result = WPML_Post_Helper::relate_posts_as_translations( $target_post_id, $source_post_id, $target_lang );

		if ( is_wp_error( $relation_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $relation_result;
		}

		// Copy custom fields marked as "Copy" in WPML settings.
		// This ensures ACF fields and other custom fields configured as "Copy" mode
		// are properly copied from the source post to the translation.
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'copy_custom_fields' ) ) {
			$sitepress->copy_custom_fields( $source_post_id, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post_id, $target_post_id );

		return $target_post_id;
	}

	/**
	 * Update an existing translation post with fresh translations
	 *
	 * @param WP_Post $source_post    Source post object.
	 * @param int     $target_post_id Existing translation post ID.
	 * @param string  $source_lang    Source language code.
	 * @param string  $target_lang    Target language code.
	 * @return int|WP_Error Target post ID or error
	 */
	private function update_translation_post( WP_Post $source_post, int $target_post_id, string $source_lang, string $target_lang ) {
		// Translate post content.
		$translated = $this->translate_post_content( $source_post, $target_lang, $source_lang );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		// Update post data with translated content.
		$post_data = array(
			'ID'           => $target_post_id,
			'post_title'   => $translated['title'],
			'post_content' => $translated['content'],
			'post_excerpt' => $translated['excerpt'],
		);

		// Update post.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Copy custom fields marked as "Copy" in WPML settings.
		// This ensures ACF fields and other custom fields configured as "Copy" mode
		// are properly synced when updating existing translations.
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'copy_custom_fields' ) ) {
			$sitepress->copy_custom_fields( $source_post->ID, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post->ID, $target_post_id );

		return $target_post_id;
	}
}
