<?php
/**
 * WPML Translation Widget Integration
 *
 * Manages the visibility of WPML's default translation meta box.
 * Hides WPML's meta box on post types where the Multilingual Bridge
 * translation widget is active to avoid duplicate UI.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\WPML;

/**
 * Class Translation_Widget
 *
 * Controls WPML meta box visibility based on Multilingual Bridge widget status
 */
class Translation_Widget {

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {

		// Remove WPML meta box on post types where our widget is active.
		// WPML adds its meta box via 'admin_head' hook, so we need to run after that.
		// We use priority 999 to ensure this runs after WPML's hook (default priority 10).
		add_action( 'admin_head', array( $this, 'remove_wpml_meta_box' ), 999 );

		// Disable WPML's "Translate Everything Automatically" feature.
		add_action( 'init', array( $this, 'disable_translate_everything_automatically' ), 999 );
	}

	/**
	 * Remove WPML's language meta boxes
	 *
	 * Removes WPML's default meta boxes to avoid duplicate translation UI.
	 * - 'icl_div' = Language meta box (WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID)
	 * - 'icl_div_config' = Multilingual Content Setup meta box
	 */
	public function remove_wpml_meta_box(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		$post_type = $post->post_type;

		// Remove WPML's Language meta box from all contexts.
		remove_meta_box( 'icl_div', $post_type, 'side' );
		remove_meta_box( 'icl_div', $post_type, 'normal' );
		remove_meta_box( 'icl_div', $post_type, 'advanced' );

		// Remove WPML's Multilingual Content Setup meta box.
		remove_meta_box( 'icl_div_config', $post_type, 'normal' );
	}

	/**
	 * Disable WPML's "Translate Everything Automatically" feature
	 *
	 * This prevents WPML from automatically translating content, ensuring
	 * all translations go through the Multilingual Bridge workflow.
	 *
	 * Uses WPML's internal API to disable the feature:
	 * - Sets 'translate-everything' option to false
	 * - Prevents automatic translation from being triggered
	 */
	public function disable_translate_everything_automatically(): void {
		// Check if WPML's Option class exists.
		if ( ! class_exists( '\WPML\Setup\Option' ) ) {
			return;
		}

		// Disable "Translate Everything Automatically" feature.
		// This uses WPML's internal API to set the option to false.
		\WPML\Setup\Option::setTranslateEverything( false );
	}
}
