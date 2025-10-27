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

			// Check WPML translation preference for ACF fields
			if ( ! $this->is_wpml_translatable_field( $field['name'] ) ) {
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
	 * Check if field is translatable according to WPML settings
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private function is_wpml_translatable_field( string $meta_key ): bool {
		global $iclTranslationManagement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		// If WPML Translation Management is not available, consider all fields translatable
		if ( ! isset( $iclTranslationManagement ) || ! is_object( $iclTranslationManagement ) || ! property_exists( $iclTranslationManagement, 'settings' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			return true;
		}

		$settings = $iclTranslationManagement->settings; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		// Check custom fields translation settings
		if ( ! isset( $settings['custom_fields_translation'] ) ) {
			return true;
		}

		$cf_settings = $settings['custom_fields_translation'];

		// Check both with and without underscore prefix (ACF compatibility)
		$keys_to_check = array( $meta_key );
		if ( 0 !== strpos( $meta_key, '_' ) ) {
			$keys_to_check[] = '_' . $meta_key;
		}

		foreach ( $keys_to_check as $key ) {
			if ( isset( $cf_settings[ $key ] ) ) {
				// WPML uses these values:
				// 0 = Don't translate (copy from original)
				// 1 = Copy (copy once from original)
				// 2 = Translate (field is translatable)
				// 3 = Ignore (don't copy or translate)
				$translation_mode = (int) $cf_settings[ $key ];

				// Only include fields marked as translatable (2)
				return 2 === $translation_mode;
			}
		}

		// If field is not in settings, it's translatable by default
		return true;
	}
}
