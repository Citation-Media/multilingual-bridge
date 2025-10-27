<?php
/**
 * Generic Meta Field Translation integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\WPML_Language_Helper;

/**
 * Class Meta_Translation
 */
class Meta_Translation {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {

		// Add translation button to native meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_translation_box' ), 10, 2 );

		// Add React modal container
		add_action( 'admin_footer-post.php', array( $this, 'add_react_container' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'add_react_container' ) );
	}

	/**
	 * Add meta translation box to post edit screen
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post object.
	 */
	public function add_meta_translation_box( string $post_type, \WP_Post $post ): void {

		if ( WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		add_meta_box(
			'multilingual-bridge-meta-translation',
			__( 'Meta Field Translation', 'multilingual-bridge' ),
			array( $this, 'render_meta_translation_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render meta translation box
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_meta_translation_box( \WP_Post $post ): void {

		$current_lang = WPML_Post_Helper::get_language( $post->ID );
		$default_lang = WPML_Language_Helper::get_default_language();

		if ( ! $current_lang || ! $default_lang ) {
			echo '<p>' . esc_html__( 'Unable to determine language settings.', 'multilingual-bridge' ) . '</p>';
			return;
		}

		$default_lang_post_id = WPML_Post_Helper::get_default_language_post_id( $post->ID );
		if ( ! $default_lang_post_id ) {
			echo '<p>' . esc_html__( 'Default language post not found.', 'multilingual-bridge' ) . '</p>';
			return;
		}

		$meta_fields = $this->get_translatable_meta_fields( $post->ID, $default_lang_post_id );

		if ( empty( $meta_fields ) ) {
			echo '<p>' . esc_html__( 'No translatable meta fields found.', 'multilingual-bridge' ) . '</p>';
			return;
		}

		echo '<div id="multilingual-bridge-meta-fields-list">';

		foreach ( $meta_fields as $meta_key => $meta_data ) {
			echo '<div class="multilingual-translatable-meta-field" ';
			echo 'data-field-key="' . esc_attr( $meta_key ) . '" ';
			echo 'data-field-label="' . esc_attr( $meta_data['label'] ) . '" ';
			echo 'data-post-id="' . esc_attr( $post->ID ) . '" ';
			echo 'data-source-lang="' . esc_attr( $default_lang ) . '" ';
			echo 'data-target-lang="' . esc_attr( $current_lang ) . '" ';
			echo 'data-field-type="meta">';

			echo '<div class="multilingual-meta-field-row">';
			echo '<span class="multilingual-meta-field-label">' . esc_html( $meta_data['label'] ) . '</span>';
			echo '<span class="multilingual-meta-field-actions"></span>';
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Get translatable meta fields
	 *
	 * @param int $post_id                Post ID.
	 * @param int $default_lang_post_id   Default language post ID.
	 * @return array<string, array<string, mixed>>
	 */
	private function get_translatable_meta_fields( int $post_id, int $default_lang_post_id ): array {

		$all_meta = get_post_meta( $default_lang_post_id );
		$fields   = array();

		// Exclude internal WordPress fields and ACF fields
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

			$fields[ $meta_key ] = array(
				'label' => $this->format_meta_label( $meta_key ),
				'value' => $meta_value[0],
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

	/**
	 * Add React modal container to admin footer
	 */
	public function add_react_container(): void {
		global $post;

		if ( ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		echo '<div id="multilingual-bridge-meta-translation-modal"></div>';
	}
}
