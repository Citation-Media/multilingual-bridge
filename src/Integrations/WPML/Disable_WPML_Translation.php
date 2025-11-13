<?php
/**
 * WPML Translation Disabler
 *
 * Disables WPML's automatic translation for post types where Multilingual Bridge
 * handles translation. Uses the central Translation_Post_Types helper to determine
 * which post types are enabled for Multilingual Bridge translation.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\WPML;

use Multilingual_Bridge\Helpers\Translation_Post_Types;

/**
 * Class Disable_WPML_Translation
 *
 * Controls WPML translation based on enabled post types from central filter
 */
class Disable_WPML_Translation {

	/**
	 * Register hooks
	 */
	public function register_hooks(): void {
		// Disable WPML translation for enabled post types.
		// This filter is called by WPML to check if a post type is translatable.
		add_filter( 'wpml_is_translated_post_type', array( $this, 'disable_wpml_for_enabled_post_types' ), 10, 1 );

		// Remove WPML meta box on enabled post types.
		// WPML adds its meta box via 'admin_head' hook, so we need to run after that.
		// We use priority 999 to ensure this runs after WPML's hook (default priority 10).
		add_action( 'admin_head', array( $this, 'remove_wpml_meta_box' ), 999 );
	}

	/**
	 * Disable WPML translation for enabled post types
	 *
	 * Uses the central Translation_Post_Types helper to determine which post types
	 * should be excluded from WPML translation and handled by Multilingual Bridge.
	 *
	 * @param string $post_type Post type to check.
	 * @return bool|string False if post type is enabled for Multilingual Bridge, otherwise original post type.
	 */
	public function disable_wpml_for_enabled_post_types( $post_type ) {
		// If this post type is enabled for Multilingual Bridge, tell WPML it's not translatable.
		if ( Translation_Post_Types::is_enabled( $post_type ) ) {
			return false;
		}

		// Return original post type for all other post types.
		return $post_type;
	}

	/**
	 * Remove WPML's language meta boxes for enabled post types
	 *
	 * Removes WPML's default meta boxes to avoid duplicate translation UI
	 * on post types where Multilingual Bridge handles translations.
	 * - 'icl_div' = Language meta box (WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID)
	 * - 'icl_div_config' = Multilingual Content Setup meta box
	 */
	public function remove_wpml_meta_box(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		$post_type = $post->post_type;

		// Only remove meta boxes for enabled post types.
		if ( ! Translation_Post_Types::is_enabled( $post_type ) ) {
			return;
		}

		// Remove WPML's Language meta box from all contexts.
		remove_meta_box( 'icl_div', $post_type, 'side' );
		remove_meta_box( 'icl_div', $post_type, 'normal' );
		remove_meta_box( 'icl_div', $post_type, 'advanced' );

		// Remove WPML's Multilingual Content Setup meta box.
		remove_meta_box( 'icl_div_config', $post_type, 'normal' );
	}
}
