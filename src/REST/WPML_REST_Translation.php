<?php
/**
 * REST API endpoints for translation functionality
 *
 * Provides endpoints for:
 * - Fetching original field values from default language posts
 * - Translating text using configured translation provider
 * - Listing available translation providers
 * - One-time post translation to multiple languages (overwrites existing translations)
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Meta_Translation_Handler;
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
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->meta_handler        = new Meta_Translation_Handler();
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
							'description' => __( 'Target language code (ISO 639-1)', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 2,
							'maxLength'   => 5,
							'pattern'     => '^[a-zA-Z]{2}(-[a-zA-Z]{2})?$',
						),
						'source_lang' => array(
							'description' => __( 'Source language code (ISO 639-1), auto-detect if not provided', 'multilingual-bridge' ),
							'type'        => 'string',
							'minLength'   => 2,
							'maxLength'   => 5,
							'pattern'     => '^[a-zA-Z]{2}(-[a-zA-Z]{2})?$',
						),
						'provider'    => array(
							'description' => __( 'Translation provider ID (uses default if not specified)', 'multilingual-bridge' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50,
						),
					),
				),
			)
		);

		// List available translation providers.
		register_rest_route(
			$this->namespace,
			'/providers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_providers' ),
					'permission_callback' => array( $this, 'permissions_check' ),
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
							'description'       => __( 'Array of target language codes. This will overwrite existing translations.', 'multilingual-bridge' ),
							'required'          => true,
							'type'              => 'array',
							'items'             => array(
								'type'      => 'string',
								'minLength' => 2,
								'maxLength' => 10,
								'pattern'   => '^[a-z]{2}(-[a-z]{2,4})?$',
							),
							'minItems'          => 1,
							'maxItems'          => 20,
							'validate_callback' => array( $this, 'validate_target_languages' ),
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
	 * Validate target languages array
	 *
	 * @param mixed                          $value   Array of language codes.
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @param string                         $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function validate_target_languages( $value, $request, $param ) {
		// Validate that value is an array.
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'target_languages must be an array', 'multilingual-bridge' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'target_languages cannot be empty', 'multilingual-bridge' ),
				array( 'status' => 400 )
			);
		}

		// Validate each language code.
		foreach ( $value as $lang_code ) {
			// Ensure each item is a string.
			if ( ! is_string( $lang_code ) ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'All language codes must be strings', 'multilingual-bridge' ),
					array( 'status' => 400 )
				);
			}

			// Check if language code format is valid (e.g., "en", "zh-hans").
			if ( ! preg_match( '/^[a-z]{2}(-[a-z]{2,4})?$/', $lang_code ) ) {
				return new WP_Error(
					'rest_invalid_param',
					sprintf(
						/* translators: %s: Invalid language code */
						__( 'Invalid language code format: %s', 'multilingual-bridge' ),
						$lang_code
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
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
		$text        = $request->get_param( 'text' );
		$target_lang = $request->get_param( 'target_lang' );
		$source_lang = $request->get_param( 'source_lang' ) ?? '';
		$provider_id = $request->get_param( 'provider' );

		// Use Translation Manager to translate.
		$translation = $this->translation_manager->translate(
			$text,
			$target_lang,
			$source_lang,
			$provider_id
		);

		if ( is_wp_error( $translation ) ) {
			return $translation;
		}

		return new WP_REST_Response(
			array(
				'translation' => $translation,
				'provider'    => $provider_id ?? $this->translation_manager->get_default_provider_id(),
			),
			200
		);
	}

	/**
	 * Get list of available translation providers
	 *
	 * @return WP_REST_Response
	 */
	public function get_providers(): WP_REST_Response {
		$providers = $this->translation_manager->get_providers( true );
		$default   = $this->translation_manager->get_default_provider_id();

		$response = array(
			'providers' => array(),
			'default'   => $default,
		);

		foreach ( $providers as $provider ) {
			$response['providers'][] = array(
				'id'        => $provider->get_id(),
				'name'      => $provider->get_name(),
				'available' => $provider->is_available(),
			);
		}

		return new WP_REST_Response( $response, 200 );
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

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Translate post to a single target language
	 *
	 * @param int      $source_post_id Source post ID.
	 * @param \WP_Post $source_post    Source post object.
	 * @param string   $source_lang    Source language code.
	 * @param string   $target_lang    Target language code.
	 * @return array<string, mixed> Translation result
	 */
	private function translate_to_language( int $source_post_id, \WP_Post $source_post, string $source_lang, string $target_lang ): array {
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
	 * @param \WP_Post $source_post Source post object.
	 * @param string   $target_lang Target language code.
	 * @param string   $source_lang Source language code.
	 * @return array{title: string, content: string, excerpt: string}|WP_Error Translated content or error
	 */
	private function translate_post_content( \WP_Post $source_post, string $target_lang, string $source_lang ) {
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
	 * @param \WP_Post $source_post    Source post object.
	 * @param int      $source_post_id Source post ID.
	 * @param string   $target_lang    Target language code.
	 * @return int|WP_Error Target post ID or error
	 */
	private function create_translation_post( \WP_Post $source_post, int $source_post_id, string $target_lang ) {
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

		return $target_post_id;
	}

	/**
	 * Update an existing translation post with fresh translations
	 *
	 * @param \WP_Post $source_post        Source post object.
	 * @param int      $target_post_id     Existing translation post ID.
	 * @param string   $source_lang        Source language code.
	 * @param string   $target_lang        Target language code.
	 * @return int|WP_Error Target post ID or error
	 */
	private function update_translation_post( \WP_Post $source_post, int $target_post_id, string $source_lang, string $target_lang ) {
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

		return $target_post_id;
	}
}
