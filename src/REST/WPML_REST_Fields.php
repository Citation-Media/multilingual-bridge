<?php
/**
 * WPML REST Fields functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\WPML_Term_Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Post;
use WP_Term;

/**
 * Class WPML_REST_Fields
 *
 * Adds language-related fields to the WordPress REST API for all post types and taxonomies.
 *
 * @package Multilingual_Bridge\REST
 */
class WPML_REST_Fields {

	/**
	 * Register language-related fields for all post types and taxonomies in the WordPress REST API.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		$this->register_post_fields();
		$this->register_term_fields();
	}

	/**
	 * Register language-related fields for all post types in the WordPress REST API.
	 *
	 * @return void
	 */
	private function register_post_fields(): void {
		// Get post types to register fields for
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			// Register language_code field
			register_rest_field(
				$post_type,
				'language_code',
				array(
					'get_callback' => function ( $post, $field_name, $request ) {
						return $this->get_post_language_code( $post, $request );
					},
					'schema'       => array(
						'description' => __( 'Language code.', 'multilingual-bridge' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit', 'embed' ),
						'readonly'    => true,
					),
				)
			);

			// Add filter to add language versions to _links property
			add_filter( 'rest_prepare_' . $post_type, array( $this, 'add_post_language_links' ), 10, 3 );
		}
	}

	/**
	 * Register language-related fields for all taxonomies in the WordPress REST API.
	 *
	 * @return void
	 */
	private function register_term_fields(): void {
		// Get taxonomies to register fields for
		$taxonomies = get_taxonomies( array( 'show_in_rest' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			// Register language_code field
			register_rest_field(
				$taxonomy,
				'language_code',
				array(
					'get_callback' => function ( $term, $field_name, $request ) use ( $taxonomy ) {
						return $this->get_term_language_code( $term, $taxonomy, $request );
					},
					'schema'       => array(
						'description' => __( 'Language code.', 'multilingual-bridge' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit', 'embed' ),
						'readonly'    => true,
					),
				)
			);

			// Add filter to add language versions to _links property
			add_filter( 'rest_prepare_' . $taxonomy, array( $this, 'add_term_language_links' ), 10, 3 );
		}
	}

	/**
	 * Get the language code for a post.
	 *
	 * @param array<string, mixed>                       $post Post object as array.
	 * @param WP_REST_Request<array<string, mixed>>|null $request Optional. The request object.
	 * @return string Language code.
	 */
	public function get_post_language_code( array $post, $request = null ): string {
		// Check if _fields parameter is set and if language_code is not included
		if ( $request && $request->get_param( '_fields' ) ) {
			$fields = wp_parse_list( $request->get_param( '_fields' ) );
			if ( ! empty( $fields ) && ! rest_is_field_included( 'language_code', $fields ) ) {
				return '';
			}
		}

		if ( empty( $post['id'] ) ) {
			return '';
		}

		return WPML_Post_Helper::get_language( $post['id'] );
	}

	/**
	 * Get the language code for a term.
	 *
	 * @param array<string, mixed>                       $term Term object as array.
	 * @param string                                     $taxonomy The taxonomy name.
	 * @param WP_REST_Request<array<string, mixed>>|null $request Optional. The request object.
	 * @return string Language code.
	 */
	public function get_term_language_code( array $term, string $taxonomy, $request = null ): string {
		// Check if _fields parameter is set and if language_code is not included
		if ( $request && $request->get_param( '_fields' ) ) {
			$fields = wp_parse_list( $request->get_param( '_fields' ) );
			if ( ! empty( $fields ) && ! rest_is_field_included( 'language_code', $fields ) ) {
				return '';
			}
		}

		if ( empty( $term['id'] ) ) {
			return '';
		}

		return WPML_Term_Helper::get_language( $term['id'], $taxonomy );
	}

	/**
	 * Add language version links to the _links property of the response for posts.
	 *
	 * @param WP_REST_Response                      $response The response object.
	 * @param WP_Post                               $post The post object.
	 * @param WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return WP_REST_Response The modified response object.
	 */
	public function add_post_language_links( $response, $post, $request ): WP_REST_Response {
		$translations = WPML_Post_Helper::get_language_versions( $post->ID );
		return $this->add_translation_links( $response, $translations, $post->ID, $request );
	}

	/**
	 * Add language version links to the _links property of the response for terms.
	 *
	 * @param WP_REST_Response                      $response The response object.
	 * @param WP_Term                               $term The term object.
	 * @param WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return WP_REST_Response The modified response object.
	 */
	public function add_term_language_links( $response, $term, $request ): WP_REST_Response {
		$translations = WPML_Term_Helper::get_language_versions( $term->term_id, $term->taxonomy );
		return $this->add_translation_links( $response, $translations, $term->term_id, $request );
	}

	/**
	 * Add translation links to the response object.
	 *
	 * @param WP_REST_Response   $response The response object.
	 * @param array<string, int> $translations Array of language code => ID mappings.
	 * @param int                $current_id The current post or term ID.
	 * @param WP_REST_Request    $request The request object.
	 * @return WP_REST_Response The modified response object.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function add_translation_links( WP_REST_Response $response, array $translations, int $current_id, WP_REST_Request $request ): WP_REST_Response {
		// Check if _fields parameter is set and if translation links should be included
		if ( $request->get_param( '_fields' ) ) {
			$fields = wp_parse_list( $request->get_param( '_fields' ) );
			if ( ! empty( $fields ) && ! rest_is_field_included( '_links', $fields ) ) {
				return $response;
			}
		}

		if ( empty( $translations ) ) {
			return $response;
		}

		foreach ( $translations as $language_code => $translated_id ) {
			// Skip current item
			if ( $translated_id === $current_id ) {
				continue;
			}

			// Get the REST API URL for this translation
			$route = $request->get_route();
			$id    = $request->get_param( 'id' );

			if ( ! empty( $id ) ) {
				$route = str_replace( '/' . $id, '', $route );
			}

			$rest_url = rest_url( sprintf( '%s/%d', $route, $translated_id ) );

			// Add link to the response
			$response->add_link(
				'translations',
				$rest_url,
				array(
					'language'   => $language_code,
					'embeddable' => true,
				)
			);
		}

		return $response;
	}
}
