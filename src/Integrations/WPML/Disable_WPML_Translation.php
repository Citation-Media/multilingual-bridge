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
		// Filter signature: apply_filters( 'wpml_is_translated_post_type', $is_translated, $post_type )
		add_filter( 'wpml_is_translated_post_type', array( $this, 'disable_wpml_for_enabled_post_types' ), 10, 2 );

		// Remove enabled post types from WPML's translatable documents list.
		// This prevents WPML custom columns from appearing in admin list tables.
		add_filter( 'wpml_translatable_documents', array( $this, 'exclude_post_types_from_wpml' ), 10, 1 );

		// Remove enabled post types from WPML's custom_posts_sync_option setting.
		// This prevents them from appearing as "Translatable" in WPML settings.
		add_filter( 'option_icl_sitepress_settings', array( $this, 'filter_wpml_settings' ), 10, 1 );

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
	 * WPML Filter signature: apply_filters( 'wpml_is_translated_post_type', $is_translated, $post_type )
	 *
	 * @param bool   $is_translated Whether the post type is translatable (WPML default).
	 * @param string $post_type Post type to check.
	 * @return bool False if post type is enabled for Multilingual Bridge, otherwise original value.
	 */
	public function disable_wpml_for_enabled_post_types( $is_translated, $post_type ) {
		// If this post type is enabled for Multilingual Bridge, tell WPML it's not translatable.
		if ( Translation_Post_Types::is_enabled( $post_type ) ) {
			return false;
		}

		// Return original value for all other post types.
		return $is_translated;
	}

	/**
	 * Filter WPML settings to remove enabled post types from custom_posts_sync_option
	 *
	 * This prevents enabled post types from appearing in WPML's "Post Types Translation"
	 * settings page. The setting custom_posts_sync_option controls which post types
	 * show the translation radio buttons in WPML settings.
	 *
	 * @param array<string, mixed>|false $settings WPML settings array.
	 * @return array<string, mixed>|false Modified settings with enabled post types removed.
	 */
	public function filter_wpml_settings( $settings ) {
		// Ensure we have a settings array.
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		// Check if custom_posts_sync_option exists.
		if ( ! isset( $settings['custom_posts_sync_option'] ) || ! is_array( $settings['custom_posts_sync_option'] ) ) {
			return $settings;
		}

		// Get enabled post types from central helper.
		$enabled_post_types = Translation_Post_Types::get_enabled_post_types();

		// Remove enabled post types from WPML's sync options.
		foreach ( $enabled_post_types as $post_type ) {
			$settings['custom_posts_sync_option'][ $post_type ] = 0; // 0 = Disable translation for this post type.
		}

		return $settings;
	}

	/**
	 * Exclude enabled post types from WPML's translatable documents
	 *
	 * This prevents WPML from showing enabled post types in its admin UI, including:
	 * - Custom columns in admin list tables (class-wpml-custom-columns.php)
	 * - Translation management screens
	 * - WPML dashboard widgets
	 *
	 * @param array<string, array<string, mixed>> $translatable_documents Array of translatable post types.
	 * @return array<string, array<string, mixed>> Filtered array with enabled post types removed.
	 */
	public function exclude_post_types_from_wpml( array $translatable_documents ): array {
		// Get enabled post types from central helper.
		$enabled_post_types = Translation_Post_Types::get_enabled_post_types();

		// Remove enabled post types from WPML's translatable documents.
		foreach ( $enabled_post_types as $post_type ) {
			unset( $translatable_documents[ $post_type ] );
		}

		return $translatable_documents;
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
