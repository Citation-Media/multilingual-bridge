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

		// Remove WPML meta box on enabled post types.
		// WPML adds its meta box via 'admin_head' hook, so we need to run after that.
		// We use priority 999 to ensure this runs after WPML's hook (default priority 10).
		add_action( 'admin_head', array( $this, 'remove_wpml_meta_box' ), 999 );

		// Force native/classic translation editor for enabled post types.
		// This disables WPML's Translation Management dashboard.
		add_filter( 'wpml_use_tm_editor', array( $this, 'force_native_editor' ), 10, 2 );

		// Exclude enabled post types from WPML's automatic translation.
		add_filter( 'wpml_exclude_post_from_auto_translate', array( $this, 'exclude_from_auto_translate' ), 10, 2 );
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

	/**
	 * Force native/classic translation editor for enabled post types
	 *
	 * Disables WPML's Translation Management dashboard and forces the use
	 * of the native/classic translation editor for post types where
	 * Multilingual Bridge handles translations.
	 *
	 * @param bool $use_tm_editor Whether to use TM editor (true) or native editor (false).
	 * @param int  $post_id       The post ID being edited.
	 * @return bool False to use native editor, original value otherwise.
	 */
	public function force_native_editor( bool $use_tm_editor, int $post_id ): bool {
		$post_type = get_post_type( $post_id );

		// Force native editor for enabled post types.
		if ( $post_type && Translation_Post_Types::is_enabled( $post_type ) ) {
			return false; // false = native/classic editor
		}

		return $use_tm_editor;
	}

	/**
	 * Exclude enabled post types from WPML's automatic translation
	 *
	 * Prevents WPML from automatically translating posts of types where
	 * Multilingual Bridge handles translations. This ensures manual control
	 * over the translation process.
	 *
	 * @param bool $exclude Whether to exclude from automatic translation.
	 * @param int  $post_id The post ID being checked.
	 * @return bool True to exclude from auto-translation, original value otherwise.
	 */
	public function exclude_from_auto_translate( bool $exclude, int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return $exclude;
		}

		// Exclude enabled post types from automatic translation.
		if ( Translation_Post_Types::is_enabled( $post->post_type ) ) {
			return true; // Exclude from automatic translation
		}

		return $exclude;
	}
}
