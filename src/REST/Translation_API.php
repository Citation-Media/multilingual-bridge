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
use Multilingual_Bridge\Translation\Post_Translation_Handler;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
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
							'description'       => __( 'Target language code (RFC 5646)', 'multilingual-bridge' ),
							'required'          => true,
							'type'              => 'string',
							'minLength'         => 2,
							'maxLength'         => 20,
							'validate_callback' => array( $this, 'validate_language_code' ),
						),
						'source_lang' => array(
							'description'       => __( 'Source language code (RFC 5646), auto-detect if not provided', 'multilingual-bridge' ),
							'type'              => 'string',
							'minLength'         => 2,
							'maxLength'         => 20,
							'validate_callback' => array( $this, 'validate_language_code' ),
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
	 * Validate a single language code parameter
	 *
	 * @param mixed                                 $value   Language code to validate.
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @param string                                $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function validate_language_code( $value, $request, $param ) {
		// Allow empty source_lang (auto-detect).
		if ( 'source_lang' === $param && empty( $value ) ) {
			return true;
		}

		// Ensure value is a string.
		if ( ! is_string( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf(
					/* translators: %s: Parameter name */
					__( '%s must be a string', 'multilingual-bridge' ),
					$param
				),
				array( 'status' => 400 )
			);
		}

		// Validate using RFC 5646 standard.
		if ( ! $this->is_valid_language_tag( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf(
					/* translators: 1: Parameter name, 2: Invalid language code */
					__( 'Invalid language tag for %1$s (RFC 5646): %2$s', 'multilingual-bridge' ),
					$param,
					$value
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate a single language tag using RFC 5646 standard
	 *
	 * @param string $lang_code Language code to validate.
	 * @return bool True if valid, false otherwise
	 */
	private function is_valid_language_tag( string $lang_code ): bool {
		// Use LanguageTag library to validate RFC 5646 compliant language tags.
		$language_tag = LanguageTag::tryFromString( $lang_code );

		return null !== $language_tag;
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

			// Validate using RFC 5646 standard via LanguageTag library.
			if ( ! $this->is_valid_language_tag( $lang_code ) ) {
				return new WP_Error(
					'rest_invalid_param',
					sprintf(
						/* translators: %s: Invalid language code */
						__( 'Invalid language tag (RFC 5646): %s', 'multilingual-bridge' ),
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
		$text             = $request->get_param( 'text' );
		$target_lang_code = $request->get_param( 'target_lang' );
		$source_lang_code = $request->get_param( 'source_lang' ) ?? '';

		// Convert language codes to LanguageTag objects.
		try {
			$target_lang = LanguageTag::fromString( $target_lang_code );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_target_lang',
				sprintf(
					/* translators: 1: language code, 2: error message */
					__( 'Invalid target language code "%1$s": %2$s', 'multilingual-bridge' ),
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
					'invalid_source_lang',
					sprintf(
						/* translators: 1: language code, 2: error message */
						__( 'Invalid source language code "%1$s": %2$s', 'multilingual-bridge' ),
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
		$post_id               = (int) $request->get_param( 'post_id' );
		$target_language_codes = $request->get_param( 'target_languages' );

		// Track successful translations and errors.
		$errors              = new WP_Error();
		$successful_langs    = array();
		$translated_post_ids = array();

		// Process each target language.
		foreach ( $target_language_codes as $lang_code ) {
			// Convert language code to LanguageTag.
			try {
				$language_tag = LanguageTag::fromString( $lang_code );
			} catch ( \Exception $e ) {
				$errors->add(
					'invalid_language_code',
					sprintf(
						/* translators: 1: language code, 2: error message */
						__( 'Invalid language code "%1$s": %2$s', 'multilingual-bridge' ),
						$lang_code,
						$e->getMessage()
					),
					array(
						'language' => $lang_code,
						'status'   => 400,
					)
				);
				continue;
			}

			// Translate post to this language.
			$result = $this->post_handler->translate_post( $post_id, $language_tag );

			// Handle errors from handler.
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();

				// For critical errors (post_not_found, not_source_post), return immediately.
				if ( 'post_not_found' === $error_code || 'not_source_post' === $error_code ) {
					return $result;
				}

				// For other errors, accumulate all errors and continue processing other languages.
				foreach ( $result->get_error_codes() as $code ) {
					foreach ( $result->get_error_messages( $code ) as $message ) {
						$errors->add(
							$code,
							$message,
							array(
								'language' => $lang_code,
								'status'   => 400,
							)
						);
					}
				}
				continue;
			}

			// Track successful translation.
			if ( isset( $result['success'] ) && $result['success'] ) {
				$successful_langs[]                = $lang_code;
				$translated_post_ids[ $lang_code ] = $result['translated_post_id'] ?? null;
			}
		}

		// If any errors occurred, return WP_Error with all accumulated errors.
		if ( $errors->has_errors() ) {
			return $errors;
		}

		// All translations succeeded.
		return new WP_REST_Response(
			array(
				'success'              => true,
				'source_post_id'       => $post_id,
				'translated_languages' => $successful_langs,
				'translated_post_ids'  => $translated_post_ids,
				'message'              => sprintf(
					/* translators: %d: number of languages */
					_n(
						'Successfully translated post to %d language',
						'Successfully translated post to %d languages',
						count( $successful_langs ),
						'multilingual-bridge'
					),
					count( $successful_langs )
				),
			),
			200
		);
	}
}
