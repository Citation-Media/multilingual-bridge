<?php
/**
 * REST API endpoints for translation functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\DeepL\DeepL_Translator;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class WPML_REST_Translation
 */
class WPML_REST_Translation extends WP_REST_Controller {

	/**
	 * Namespace for the API
	 *
	 * @var string
	 */
	protected $namespace = 'multilingual-bridge/v1';

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get meta value from default language
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
							'description' => 'Post ID to retrieve meta value from',
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'field_key' => array(
							'description' => 'Meta field key to retrieve',
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

		// Translate text
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
							'description' => 'Text to translate',
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50000,
						),
						'target_lang' => array(
							'description' => 'Target language code (ISO 639-1)',
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 2,
							'maxLength'   => 5,
							'pattern'     => '^[a-zA-Z]{2}(-[a-zA-Z]{2})?$',
						),
						'source_lang' => array(
							'description' => 'Source language code (ISO 639-1), auto-detect if not provided',
							'type'        => 'string',
							'minLength'   => 2,
							'maxLength'   => 5,
							'pattern'     => '^[a-zA-Z]{2}(-[a-zA-Z]{2})?$',
						),
					),
				),
			)
		);

		// Get all translatable fields for bulk translation
		register_rest_route(
			$this->namespace,
			'/fields/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_translatable_fields' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_id' => array(
							'description' => 'Post ID to retrieve translatable fields from',
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
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

		// Get default language version of the post
		$default_lang_post_id = WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $default_lang_post_id ) {
			return new WP_Error(
				'post_not_found',
				'Default language version of post not found',
				array( 'status' => 404 )
			);
		}

		$value = null;

		// Try ACF fields first if available
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $field_key, $default_lang_post_id );
		}

		// Fallback to post meta if ACF returns nothing
		if ( empty( $value ) ) {
			$value = get_post_meta( $default_lang_post_id, $field_key, true );
		}

		return new WP_REST_Response(
			array(
				'value' => $value,
			),
			200
		);
	}

	/**
	 * Translate text using DeepL
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function translate_text( WP_REST_Request $request ) {
		$text        = $request->get_param( 'text' );
		$target_lang = $request->get_param( 'target_lang' );
		$source_lang = $request->get_param( 'source_lang' );

		$translation = DeepL_Translator::translate( $text, $target_lang, $source_lang );

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
	 * Get all translatable fields for bulk translation
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function get_translatable_fields( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		$default_lang_post_id = WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $default_lang_post_id ) {
			return new WP_Error(
				'post_not_found',
				'Default language version of post not found',
				array( 'status' => 404 )
			);
		}

		$fields = array();

		// Get ACF fields if available
		if ( function_exists( 'get_field_objects' ) ) {
			$acf_fields = $this->get_acf_fields( $post_id, $default_lang_post_id );
			$fields     = array_merge( $fields, $acf_fields );
		}

		// Get native meta fields
		$meta_fields = $this->get_meta_fields( $post_id, $default_lang_post_id );
		$fields      = array_merge( $fields, $meta_fields );

		return new WP_REST_Response(
			array(
				'fields' => $fields,
			),
			200
		);
	}

	/**
	 * Get ACF fields
	 *
	 * @param int $post_id                Post ID.
	 * @param int $default_lang_post_id   Default language post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_acf_fields( int $post_id, int $default_lang_post_id ): array {
		$field_objects = get_field_objects( $post_id );
		if ( ! $field_objects ) {
			return array();
		}

		$supported_types = apply_filters( 'multilingual_bridge_acf_supported_types', array( 'text', 'textarea', 'wysiwyg', 'lexical-editor' ) );
		$fields          = array();

		foreach ( $field_objects as $field_key => $field ) {
			if ( ! in_array( $field['type'], $supported_types, true ) ) {
				continue;
			}

			$source_value = get_field( $field_key, $default_lang_post_id );
			$target_value = $field['value'];

			$fields[] = array(
				'key'         => $field_key,
				'name'        => $field['name'],
				'label'       => $field['label'],
				'type'        => $field['type'],
				'sourceValue' => $source_value,
				'targetValue' => $target_value,
				'hasSource'   => ! empty( $source_value ),
				'needsUpdate' => empty( $target_value ) && ! empty( $source_value ),
			);
		}

		return $fields;
	}

	/**
	 * Get native meta fields
	 *
	 * @param int $post_id                Post ID.
	 * @param int $default_lang_post_id   Default language post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_meta_fields( int $post_id, int $default_lang_post_id ): array {
		$all_meta = get_post_meta( $default_lang_post_id );
		$fields   = array();

		// Exclude internal WordPress fields, ACF fields
		$excluded_prefixes = array( '_', 'acf-' );

		foreach ( $all_meta as $meta_key => $meta_value ) {

			$should_exclude = false;
			foreach ( $excluded_prefixes as $prefix ) {
				if ( str_starts_with( $meta_key, $prefix ) ) {
					$should_exclude = true;
					break;
				}
			}

			if ( $should_exclude ) {
				continue;
			}

			// Only include text-based meta fields
			if ( ! is_string( $meta_value[0] ) || empty( $meta_value[0] ) ) {
				continue;
			}

			$source_value = $meta_value[0];
			$target_value = get_post_meta( $post_id, $meta_key, true );

			$fields[] = array(
				'key'         => $meta_key,
				'name'        => $meta_key,
				'label'       => $this->format_meta_label( $meta_key ),
				'type'        => 'meta',
				'sourceValue' => $source_value,
				'targetValue' => $target_value,
				'hasSource'   => ! empty( $source_value ),
				'needsUpdate' => empty( $target_value ) && ! empty( $source_value ),
			);
		}

		return apply_filters( 'multilingual_bridge_translatable_meta_fields', $fields, $post_id, $default_lang_post_id );
	}

	/**
	 * Format meta key into human-readable label
	 *
	 * @param string $meta_key Meta key.
	 * @return string
	 */
	private function format_meta_label( string $meta_key ): string {
		return ucwords( str_replace( array( '_', '-' ), ' ', $meta_key ) );
	}
}
