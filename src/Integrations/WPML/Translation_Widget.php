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
	 * Remove WPML's language meta box on post types where our translation widget is active
	 *
	 * WPML adds its meta box via 'admin_head' action (not 'add_meta_boxes').
	 * We need to remove it to avoid duplicate translation UI when our widget is displayed.
	 *
	 * WPML registers the meta box with:
	 * - ID: 'icl_div' (defined as WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID)
	 * - Title: 'Language'
	 * - Context: 'side' (filterable via 'wpml_post_edit_meta_box_context')
	 * - Priority: 'high' (filterable via 'wpml_post_edit_meta_box_priority')
	 */
	public function remove_wpml_meta_box(): void {
		global $post;

		// Safety check: ensure we're on a post edit screen.
		if ( ! $post || ! isset( $post->ID ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}

		$post_type = $post->post_type;

		// Get the context where WPML's meta box is added (default is 'side').
		// WPML allows filtering this via 'wpml_post_edit_meta_box_context' filter.
		$context = apply_filters( 'wpml_post_edit_meta_box_context', 'side', 'icl_div' );

		// Remove WPML's language meta box.
		// The meta box ID is 'icl_div' as defined in WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID.
		remove_meta_box( 'icl_div', $post_type, $context );
		
		// Also try other contexts in case WPML or another plugin moved it.
		if ( 'side' !== $context ) {
			remove_meta_box( 'icl_div', $post_type, 'side' );
		}
		if ( 'normal' !== $context ) {
			remove_meta_box( 'icl_div', $post_type, 'normal' );
		}
		if ( 'advanced' !== $context ) {
			remove_meta_box( 'icl_div', $post_type, 'advanced' );
		}

		// Also remove the Multilingual Content Setup meta box if present.
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
