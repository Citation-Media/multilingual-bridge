<?php
/**
 * Translation Post Types Helper
 *
 * Central helper for managing which post types have Multilingual Bridge
 * translation features enabled. Provides a single source of truth for
 * Post Translation Widget, ACF Translation Modal, and WPML integration.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

/**
 * Class Translation_Post_Types
 *
 * Manages enabled post types for translation features
 */
class Translation_Post_Types {

	/**
	 * Get post types enabled for Multilingual Bridge translation
	 *
	 * This is the central filter that controls which post types have access to:
	 * - Post Translation Widget (sidebar widget for translating posts)
	 * - ACF Translation Modal (inline ACF field translation)
	 * - WPML integration (disables WPML for these types)
	 *
	 * By default, no post type is enabled. Use the filter to add/remove more post types.
	 *
	 * @return string[] Array of post type slugs enabled for translation
	 */
	public static function get_enabled_post_types(): array {
		/**
		 * Filter post types enabled for Multilingual Bridge translation
		 *
		 * Controls which post types have Multilingual Bridge translation features enabled:
		 * - Post Translation Widget in the sidebar
		 * - ACF Translation Modal for inline field translation
		 * - WPML automatic translation disabled (Multilingual Bridge handles it)
		 *
		 * @param string[] $enabled_post_types Post types enabled for Multilingual Bridge translation
		 *
		 * @example
		 * // Enable translation for custom post types
		 * add_filter( 'multilingual_bridge_translation_post_types', function( $post_types ) {
		 *     $post_types[] = 'my_custom_type';
		 *     $post_types[] = 'another_type';
		 *     return $post_types;
		 * } );
		 */
		return apply_filters(
			'multilingual_bridge_translation_post_types',
			array()
		);
	}

	/**
	 * Check if a post type is enabled for translation
	 *
	 * @param string $post_type Post type slug to check.
	 * @return bool True if post type is enabled for translation
	 */
	public static function is_enabled( string $post_type ): bool {
		return in_array( $post_type, self::get_enabled_post_types(), true );
	}
}
