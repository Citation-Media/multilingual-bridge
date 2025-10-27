<?php
/**
 * ACF Bulk Translation integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\WPML_Language_Helper;

/**
 * Class ACF_Bulk_Translation
 */
class ACF_Bulk_Translation {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {

		// Add bulk translation button to post edit screen
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_bulk_translation_button' ) );

		// Add React modal container
		add_action( 'admin_footer', array( $this, 'add_react_container' ) );
	}

	/**
	 * Add bulk translation button to post edit screen
	 */
	public function add_bulk_translation_button(): void {
		global $post;

		if ( ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		$current_lang = WPML_Post_Helper::get_language( $post->ID );
		$default_lang = WPML_Language_Helper::get_default_language();

		if ( ! $current_lang || ! $default_lang ) {
			return;
		}

		echo '<div class="misc-pub-section misc-pub-bulk-translate">';
		echo '<button type="button" id="multilingual-bridge-bulk-translate" class="button button-secondary" ';
		echo 'data-post-id="' . esc_attr( $post->ID ) . '" ';
		echo 'data-source-lang="' . esc_attr( $default_lang ) . '" ';
		echo 'data-target-lang="' . esc_attr( $current_lang ) . '">';
		echo esc_html__( 'Translate All Fields', 'multilingual-bridge' );
		echo '</button>';
		echo '</div>';
	}

	/**
	 * Add React modal container to admin footer
	 */
	public function add_react_container(): void {
		global $post;
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		echo '<div id="multilingual-bridge-bulk-translate-modal"></div>';
	}
}
