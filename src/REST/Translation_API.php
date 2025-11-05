<?php
/**
 * REST API endpoints for translation functionality
 *
 * Provides endpoints for:
 * - Fetching original field values from default language posts
 * - Translating text using configured translation provider
 * - One-time post translation to multiple languages (overwrites existing translations)
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Meta_Translation_Handler;
use Multilingual_Bridge\Translation\Post_Translation_Handler;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Translation_API
 *
 * REST API controller for translation operations
 */
class Translation_API extends WP_REST_Controller {

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
	 * Post Translation Handler instance
	 *
	 * @var Post_Translation_Handler
	 */
	private Post_Translation_Handler $post_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->post_handler        = new Post_Translation_Handler();
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
	 * @param mixed                                 $value   Array of language codes.
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @param string                                $param   Parameter name.
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

		// Use Translation Manager to translate (provider managed by Translation Manager).
		$translation = $this->translation_manager->translate(
			$text,
			$target_lang,
			$source_lang
		);

		if ( is_wp_error( $translation ) ) {
			return $translation;
		}

		return new WP_REST_Response(
			array(
				'translation' => $translation,
				'provider'    => $this->translation_manager->get_default_provider_id(),
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

		// Delegate to Post_Translation_Handler.
		$results = $this->post_handler->translate_post( $post_id, $target_languages );

		// Handle validation errors from handler.
		if ( isset( $results['error'] ) ) {
			$error_code = $results['error_code'] ?? 'translation_error';
			$status     = 'post_not_found' === $error_code ? 404 : 400;

			return new WP_Error(
				$error_code,
				$results['error'],
				array( 'status' => $status )
			);
		}

		return new WP_REST_Response( $results, 200 );
	}
}
