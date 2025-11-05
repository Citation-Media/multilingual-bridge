<?php
/**
 * REST API endpoints for translation functionality
 *
 * Provides endpoints for:
 * - Fetching original field values from default language posts
 * - Translating text using configured translation provider
 * - One-time post translation to multiple languages (overwrites existing translations)
 *
 * ## Language Code Architecture
 *
 * This class maintains two parallel representations of language codes:
 *
 * 1. **WPML Language Codes (string)** - e.g., 'zh-hans', 'pt-br'
 *    - Used for all WPML operations (set_language, relate_posts, get_translation)
 *    - Must match exact codes configured in WPML admin (typically lowercase)
 *
 * 2. **LanguageTag Objects** - RFC 5646 standard
 *    - Used for validation and translation provider operations
 *    - Handles normalization internally (accepts 'zh-hans', 'zh-Hans', etc.)
 *    - Note: `toString()` returns mixed-case format (e.g., 'zh-Hans') incompatible with WPML
 *
 * **Why both?** WPML requires exact case-sensitive strings from its config, while LanguageTag
 * provides validation and provider compatibility. We cannot use `LanguageTag::toString()`
 * for WPML operations due to casing differences
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Language_Helper;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Meta_Translation_Handler;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class WPML_REST_Translation
 *
 * REST API controller for translation operations
 */
class WPML_REST_Translation extends WP_REST_Controller {

	/**
	 * Namespace for the API
	 *
	 * @var string
	 */
	protected $namespace = 'multilingual-bridge/v1';

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
	 * Post Change Tracker instance
	 *
	 * @var Post_Change_Tracker
	 */
	private Post_Change_Tracker $sync_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->meta_handler        = new Meta_Translation_Handler();
		$this->sync_handler        = new Post_Change_Tracker();
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get meta value from default language.
		register_rest_route(
			$this->namespace,
			'/meta/(?P<post_id>\d+)/(?P<field_key>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_meta_value' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_id'   => array(
							'description' => __( 'Post ID to retrieve meta value from', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'field_key' => array(
							'description' => __( 'Meta field key to retrieve', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 255,
							'pattern'     => '^[a-zA-Z0-9_-]+$',
						),
					),
				),
			)
		);

		// Translate text.
		register_rest_route(
			$this->namespace,
			'/translate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'translate_text' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'text'        => array(
							'description' => __( 'Text to translate', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50000,
						),
						'target_lang' => array(
							'description' => __( 'Target language code (BCP 47)', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'enum'        => $this->get_language_tag_enum(),
						),
						'source_lang' => array(
							'description' => __( 'Source language code (BCP 47), auto-detect if not provided', 'multilingual-bridge' ),
							'type'        => 'string',
							'enum'        => $this->get_language_tag_enum(),
						),
					),
				),
			)
		);

		// One-time post translation (overwrites existing translations).
		register_rest_route(
			$this->namespace,
			'/post-translate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'post_translate' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_id'          => array(
							'description' => __( 'Source post ID to translate from', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'target_languages' => array(
							'description' => __( 'Array of target language codes (BCP 47). This will overwrite existing translations.', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => $this->get_language_tag_enum(),
							),
							'minItems'    => 1,
							'maxItems'    => 20,
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for API access
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get REST API enum values for LanguageTag
	 *
	 * Returns valid BCP 47 language tags for REST API validation
	 * Uses WPML's active languages to generate available tags
	 *
	 * Note: Returns both the original WPML codes AND their BCP 47 normalized versions
	 * to allow flexibility in API requests (e.g., both "zh-hans" and "zh-Hans" work)
	 *
	 * @return string[]
	 */
	private function get_language_tag_enum(): array {
		// Get active WPML languages.
		$active_languages = WPML_Language_Helper::get_active_language_codes();
		$enum_values      = array();

		foreach ( $active_languages as $language ) {
			// Always include the original WPML language code.
			$enum_values[] = $language;

			// Also try to include the normalized BCP 47 version if it differs.
			try {
				$lang_tag   = LanguageTag::fromString( $language );
				$normalized = $lang_tag->toString();

				// Only add normalized version if it's different from original.
				if ( $normalized !== $language ) {
					$enum_values[] = $normalized;
				}
			} catch ( \Exception $e ) {
				// Skip if conversion fails, we already have the original code.
				continue;
			}
		}

		// Remove duplicates and re-index array.
		return array_values( array_unique( $enum_values ) );
	}

	/**
	 * Get meta value from default language post
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function get_meta_value( WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post_id' );
		$field_key = $request->get_param( 'field_key' );

		// Get default language version of the post.
		$default_lang_post_id = WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $default_lang_post_id ) {
			return new WP_Error(
				'post_not_found',
				__( 'Default language version of post not found', 'multilingual-bridge' ),
				array( 'status' => 404 )
			);
		}

		$value = null;

		// Try ACF fields first if available.
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $field_key, $default_lang_post_id );
		}

		// Fallback to post meta if ACF returns nothing.
		if ( empty( $value ) ) {
			$value = get_post_meta( $default_lang_post_id, $field_key, true );
		}

		/**
		 * Filter meta value before returning
		 *
		 * @param mixed  $value     Meta value
		 * @param string $field_key Field key
		 * @param int    $post_id   Original post ID
		 */
		$value = apply_filters( 'multilingual_bridge_rest_meta_value', $value, $field_key, $default_lang_post_id );

		return new WP_REST_Response(
			array(
				'value' => $value,
			),
			200
		);
	}

	/**
	 * Translate text using Translation Manager
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function translate_text( WP_REST_Request $request ) {
		$text             = $request->get_param( 'text' );
		$target_lang_code = $request->get_param( 'target_lang' );
		$source_lang_code = $request->get_param( 'source_lang' );

		// Convert string codes to LanguageTag instances.
		try {
			$target_lang = LanguageTag::fromString( $target_lang_code );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %1$s: language code, %2$s: error message */
					__( 'Invalid target language code: %1$s (%2$s)', 'multilingual-bridge' ),
					$target_lang_code,
					$e->getMessage()
				),
				array( 'status' => 400 )
			);
		}

		$source_lang = null;
		if ( ! empty( $source_lang_code ) ) {
			try {
				$source_lang = LanguageTag::fromString( $source_lang_code );
			} catch ( \Exception $e ) {
				return new WP_Error(
					'invalid_language',
					sprintf(
						/* translators: %1$s: language code, %2$s: error message */
						__( 'Invalid source language code: %1$s (%2$s)', 'multilingual-bridge' ),
						$source_lang_code,
						$e->getMessage()
					),
					array( 'status' => 400 )
				);
			}
		}

		// Use Translation Manager to translate (provider managed by Translation Manager).
		$translation = $this->translation_manager->translate(
			$target_lang,
			$text,
			$source_lang
		);

		if ( is_wp_error( $translation ) ) {
			return $translation;
		}

		return new WP_REST_Response(
			array(
				'translation' => $translation,
			),
			200
		);
	}

	/**
	 * Translate post and meta to multiple languages (one-time translation, overwrites existing translations)
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function post_translate( WP_REST_Request $request ) {
		$post_id          = (int) $request->get_param( 'post_id' );
		$target_languages = $request->get_param( 'target_languages' );

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

		// Convert source language string to LanguageTag.
		// LanguageTag library supports RFC 5646 format and handles various casings natively.
		try {
			$source_lang_tag = LanguageTag::fromString( $source_language );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %1$s: language code, %2$s: error message */
					__( 'Invalid source language code: %1$s (%2$s)', 'multilingual-bridge' ),
					$source_language,
					$e->getMessage()
				),
				array( 'status' => 400 )
			);
		}

		// Accumulate errors for all languages using WP_Error.
		$error            = new WP_Error();
		$translated_posts = array();

		// Process each target language.
		foreach ( $target_languages as $target_lang_code ) {
			// Convert target language string to LanguageTag.
			// LanguageTag library supports RFC 5646 format and handles various casings natively.
			// We preserve the original WPML code ($target_lang_code) for WPML operations.
			try {
				$target_lang_tag = LanguageTag::fromString( $target_lang_code );
			} catch ( \Exception $e ) {
				$error->add(
					$target_lang_code,
					sprintf(
						/* translators: %1$s: language code, %2$s: error message */
						__( 'Invalid target language code: %1$s (%2$s)', 'multilingual-bridge' ),
						$target_lang_code,
						$e->getMessage()
					)
				);
				continue;
			}

			// Translate to target language.
			// Pass both LanguageTag (for translation) and string code (for WPML operations).
			$target_post_id = $this->translate_to_language(
				$source_post,
				$source_lang_tag,
				$target_lang_tag,
				$target_lang_code,
				$error
			);

			// Track successfully translated posts (errors are accumulated in $error object).
			if ( $target_post_id > 0 ) {
				$translated_posts[ $target_lang_code ] = $target_post_id;
			}

			// Clear pending updates for this specific language if translation was successful.
			if ( $language_result['success'] ) {
				$this->sync_handler->clear_pending_updates( $post_id, null, $target_lang );
			}
		}

		// If any errors occurred, return them.
		if ( $error->has_errors() ) {
			return $error;
		}

		// Return success response with translated post IDs.
		return new WP_REST_Response(
			array(
				'source_post_id'   => $post_id,
				'translated_posts' => $translated_posts,
			),
			200
		);
	}

	/**
	 * Translate post to a single target language
	 *
	 * Orchestrates the complete translation process:
	 * 1. Check for existing translations
	 * 2. Create new or update existing translation post
	 * 3. Translate post meta fields
	 *
	 * ## Language Code Usage:
	 *
	 * - **LanguageTag objects:** Used for translation operations (Translation_Manager, providers)
	 * - **$target_lang_wpml string:** Used for WPML API operations and error identification
	 *
	 * The string parameter is kept separate for error tracking purposes - it represents
	 * the exact language code from the API request, which is used as the error code key.
	 *
	 * ## Error Handling:
	 *
	 * Accumulates errors in the WP_Error object using the language code as the error key.
	 * Returns 0 on failure, with errors stored in the $error object.
	 *
	 * @param \WP_Post    $source_post      Source post object.
	 * @param LanguageTag $source_lang      Source language tag.
	 * @param LanguageTag $target_lang      Target language tag.
	 * @param string      $target_lang_wpml Language code for WPML operations and error tracking.
	 * @param WP_Error    $error            Error object for accumulating errors.
	 * @return int Target post ID on success, or 0 if error occurred.
	 */
	private function translate_to_language( \WP_Post $source_post, LanguageTag $source_lang, LanguageTag $target_lang, string $target_lang_wpml, WP_Error $error ): int {
		// Check if translation already exists using the original WPML language code.
		$existing_translation = WPML_Post_Helper::get_translation_for_lang( $source_post->ID, $target_lang_wpml );

		if ( $existing_translation ) {
			// Update existing translation.
			$target_post_id = $this->update_translation_post( $source_post, $existing_translation, $source_lang, $target_lang );

			if ( is_wp_error( $target_post_id ) ) {
				$error->add( $target_lang_wpml, $target_post_id->get_error_message() );
				return 0;
			}
		} else {
			// Create new translation post.
			$target_post_id = $this->create_translation_post( $source_post, $source_lang, $target_lang, $target_lang_wpml );

			if ( is_wp_error( $target_post_id ) ) {
				$error->add( $target_lang_wpml, $target_post_id->get_error_message() );
				return 0;
			}
		}

		// Translate post meta.
		$meta_results = $this->meta_handler->translate_post_meta(
			$source_post->ID,
			$target_post_id,
			$target_lang,
			$source_lang,
			null
		);

		// Add meta translation errors if any occurred.
		if ( ! empty( $meta_results['errors'] ) ) {
			foreach ( $meta_results['errors'] as $meta_error ) {
				$error->add( $target_lang_wpml, $meta_error );
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
			$error->add( $target_lang_wpml, $update_result->get_error_message() );
			return 0;
		}

		return $target_post_id;
	}

	/**
	 * Translate post content (title, content, excerpt)
	 *
	 * @param \WP_Post    $source_post Source post object.
	 * @param LanguageTag $target_lang Target language tag.
	 * @param LanguageTag $source_lang Source language tag.
	 * @return array{title: string, content: string, excerpt: string}|WP_Error Translated content or error
	 */
	private function translate_post_content( \WP_Post $source_post, LanguageTag $target_lang, LanguageTag $source_lang ) {
		// Translate post title.
		$translated_title = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_title,
			$source_lang
		);

		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate post content (if not empty).
		$translated_content = $source_post->post_content;
		if ( ! empty( $source_post->post_content ) ) {
			$translated_content = $this->translation_manager->translate(
				$target_lang,
				$source_post->post_content,
				$source_lang
			);

			if ( is_wp_error( $translated_content ) ) {
				return $translated_content;
			}
		}

		// Translate post excerpt (if not empty).
		$translated_excerpt = $source_post->post_excerpt;
		if ( ! empty( $source_post->post_excerpt ) ) {
			$translated_excerpt = $this->translation_manager->translate(
				$target_lang,
				$source_post->post_excerpt,
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
	 * Creates a new WordPress post with translated content and establishes
	 * the translation relationship in WPML.
	 *
	 * @param \WP_Post    $source_post       Source post object.
	 * @param LanguageTag $source_lang       Source language tag (for translation).
	 * @param LanguageTag $target_lang       Target language tag (for translation).
	 * @param string      $target_lang_wpml  WPML language code (for WPML operations).
	 * @return int|WP_Error Target post ID or error
	 */
	private function create_translation_post( \WP_Post $source_post, LanguageTag $source_lang, LanguageTag $target_lang, string $target_lang_wpml ) {
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

		// Set language for new post using WPML language code.
		$wpml_result = WPML_Post_Helper::set_language( $target_post_id, $target_lang_wpml );

		if ( is_wp_error( $wpml_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $wpml_result;
		}

		// Relate the posts as translations using WPML language code.
		$relation_result = WPML_Post_Helper::relate_posts_as_translations( $target_post_id, $source_post->ID, $target_lang_wpml );

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
			$sitepress->copy_custom_fields( $source_post->ID, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post->ID, $target_post_id );

		return $target_post_id;
	}

	/**
	 * Update an existing translation post with fresh translations
	 *
	 * @param \WP_Post    $source_post    Source post object.
	 * @param int         $target_post_id Existing translation post ID.
	 * @param LanguageTag $source_lang    Source language tag.
	 * @param LanguageTag $target_lang    Target language tag.
	 * @return int|WP_Error Target post ID or error
	 */
	private function update_translation_post( \WP_Post $source_post, int $target_post_id, LanguageTag $source_lang, LanguageTag $target_lang ) {
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
