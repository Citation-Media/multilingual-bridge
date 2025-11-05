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

use Multilingual_Bridge\Helpers\WPML_Language_Helper;
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
							'description' => __( 'Array of target language codes. This will overwrite existing translations.', 'multilingual-bridge' ),
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

			// Use tolerant parsing to get normalized BCP 47 version.
			$lang_tag = $this->parse_language_tag( $language );
			if ( ! is_wp_error( $lang_tag ) ) {
				$normalized = $lang_tag->toString();
				if ( $normalized !== $language ) {
					$enum_values[] = $normalized;
				}
			}
		}

		// Remove duplicates and re-index array.
		return array_unique( $enum_values );
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
		 * @param mixed $value Meta value
		 * @param string $field_key Field key
		 * @param int $post_id Original post ID
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

		// Convert language codes to LanguageTag objects using tolerant parsing.
		$target_lang = $this->parse_language_tag( $target_lang_code );
		if ( is_wp_error( $target_lang ) ) {
			return new WP_Error(
				'invalid_target_lang',
				sprintf(
				/* translators: 1: language code, 2: error message */
					__( 'Invalid target language code "%1$s": %2$s', 'multilingual-bridge' ),
					$target_lang_code,
					$target_lang->get_error_message()
				),
				array( 'status' => 400 )
			);
		}

		$source_lang = null;
		if ( ! empty( $source_lang_code ) ) {
			$source_lang = $this->parse_language_tag( $source_lang_code );
			if ( is_wp_error( $source_lang ) ) {
				return new WP_Error(
					'invalid_source_lang',
					sprintf(
					/* translators: 1: language code, 2: error message */
						__( 'Invalid source language code "%1$s": %2$s', 'multilingual-bridge' ),
						$source_lang_code,
						$source_lang->get_error_message()
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
	 * Parse language code with tolerant handling of common variants
	 *
	 * Accepts WPML language codes and BCP-47 variants with flexible casing.
	 * Handles common issues like lowercase script subtags (zh-hans -> zh-Hans).
	 *
	 * @param string $code Language code from request.
	 * @return LanguageTag|WP_Error Parsed language tag or error
	 */
	private function parse_language_tag( string $code ) {
		$code = trim( $code );
		if ( '' === $code ) {
			return new WP_Error(
				'invalid_language_code',
				__( 'Empty language code', 'multilingual-bridge' ),
				array( 'status' => 400 )
			);
		}

		// Try direct parsing first.
		$tag = LanguageTag::tryFromString( $code );
		if ( null !== $tag ) {
			return $tag;
		}

		// Common alias mappings for frequently used codes.
		$aliases = array(
			'zh-hans' => 'zh-Hans',
			'zh-hant' => 'zh-Hant',
		);

		$lower = strtolower( $code );
		if ( isset( $aliases[ $lower ] ) ) {
			$tag = LanguageTag::tryFromString( $aliases[ $lower ] );
			if ( null !== $tag ) {
				return $tag;
			}
		}

		// Case normalization for BCP-47 format:
		// language-Script-REGION (e.g., en-US, zh-Hans, pt-BR).
		$normalized = preg_replace_callback(
			'/^([A-Za-z]{2,3})(?:-([A-Za-z]{4}))?(?:-([A-Za-z]{2}|\d{3}))?$/i',
			function ( $m ) {
				$out = strtolower( $m[1] ); // Language code (lowercase).
				if ( ! empty( $m[2] ) ) {
					// Script subtag (Title case).
					$out .= '-' . ucfirst( strtolower( $m[2] ) );
				}
				if ( ! empty( $m[3] ) ) {
					// Region subtag (uppercase).
					$out .= '-' . strtoupper( $m[3] );
				}
				return $out;
			},
			$code
		);

		if ( is_string( $normalized ) && $normalized !== $code ) {
			$tag = LanguageTag::tryFromString( $normalized );
			if ( null !== $tag ) {
				return $tag;
			}
		}

		// Fallback: check WPML language details for stored BCP-47 tag.
		$details = WPML_Language_Helper::get_language_details( $code );
		if ( ! empty( $details['tag'] ) ) {
			$tag = LanguageTag::tryFromString( $details['tag'] );
			if ( null !== $tag ) {
				return $tag;
			}
		}

		// All parsing attempts failed.
		return new WP_Error(
			'invalid_language_code',
			sprintf(
				/* translators: %s: language code */
				__( 'Invalid language code "%s". Expected valid BCP-47 format (e.g., en, zh-Hans, pt-BR)', 'multilingual-bridge' ),
				$code
			),
			array( 'status' => 400 )
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
			// Convert language code to LanguageTag using tolerant parsing.
			$language_tag = $this->parse_language_tag( $lang_code );

			if ( is_wp_error( $language_tag ) ) {
				$errors->add(
					$language_tag->get_error_code(),
					$language_tag->get_error_message(),
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
