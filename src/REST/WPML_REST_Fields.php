<?php
/**
 * WPML REST Fields functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Post;

/**
 * Class WPML_REST_Fields
 *
 * Adds language-related fields to the WordPress REST API for all post types.
 *
 * @package Multilingual_Bridge\REST
 */
class WPML_REST_Fields {

	/**
	 * Register language-related fields for all post types in the WordPress REST API.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		// Get post types to register fields for
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			// Register language_code field
			register_rest_field(
				$post_type,
				'language_code',
				array(
					'get_callback' => function ( $post, $field_name, $request ) {
						return $this->get_language_code( $post, $request );
					},
					'schema'       => array(
						'description' => __( 'Language code.', 'multilingual-bridge' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
				)
			);

			// Add filter to add language versions to _links property
			add_filter( 'rest_prepare_' . $post_type, array( $this, 'add_language_links' ), 10, 3 );
		}
	}

	/**
	 * Get the language code for a post.
	 *
	 * @param array<string, mixed>                       $post Post object as array.
	 * @param WP_REST_Request<array<string, mixed>>|null $request Optional. The request object.
	 * @return string Language code.
	 */
	public function get_language_code( array $post, $request = null ): string {
		// Check if _fields parameter is set and if language_code is not included
		if ( $request && $request->get_param( '_fields' ) ) {
			$fields = wp_parse_list( $request->get_param( '_fields' ) );
			if ( ! empty( $fields ) && ! rest_is_field_included( 'wpml_language', $fields ) ) {
				return '';
			}
		}

		if ( empty( $post['id'] ) ) {
			return '';
		}

		return WPML_Post_Helper::get_language( $post['id'] );
	}

	/**
	 * Add language version links to the _links property of the response.
	 *
	 * @param WP_REST_Response                      $response The response object.
	 * @param WP_Post                               $post The post object.
	 * @param WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return WP_REST_Response The modified response object.
	 */
	public function add_language_links( $response, $post, $request ): WP_REST_Response {
		// Check if _fields parameter is set and if translation links should be included
		if ( $request->get_param( '_fields' ) ) {
			$fields = wp_parse_list( $request->get_param( '_fields' ) );
			if ( ! empty( $fields ) && ! rest_is_field_included( '_links', $fields ) ) {
				return $response;
			}
		}

		// Get all translations using the helper
		$translations = WPML_Post_Helper::get_language_versions( $post->ID );

		if ( empty( $translations ) ) {
			return $response;
		}

		foreach ( $translations as $language_code => $translated_post_id ) {
			// Skip current post
			if ( $translated_post_id === $post->ID ) {
				continue;
			}

			$language_name = apply_filters( 'wpml_translated_language_name', null, $language_code, 'en' );

			// Get the REST API URL for this post
			$rest_url = rest_url( sprintf( '%s/%d', $request->get_route(), $translated_post_id ) );

			// Add link to the response
			$response->add_link(
				'translations',
				$rest_url,
				array(
					/* translators: %s: Language name */
					'title'         => sprintf( __( 'Translation: %s', 'multilingual-bridge' ), $language_name ),
					'wpml_language' => $language_code,
					'embeddable'    => true,
				)
			);
		}

		return $response;
	}
}
