<?php
/**
 * ACF Translation Modal Integration
 *
 * Provides inline translation modal UI for ACF fields in Classic Editor.
 * Adds "Translate" buttons to ACF fields that open a modal for translating
 * field content from the original language post.
 *
 * Only works with Classic Editor. Block Editor (Gutenberg) is not supported.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;

/**
 * Class ACF_Translation_Modal
 *
 * Provides inline translation modal for ACF fields
 */
class ACF_Translation_Modal {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {
		// Hook into ACF field wrapper to add data attributes.
		add_filter( 'acf/field_wrapper_attributes', array( $this, 'add_field_wrapper_attributes' ), 10, 2 );

		// Add React modal container.
		add_action( 'acf/input/admin_footer', array( $this, 'add_react_container' ) );
	}

	/**
	 * Add field wrapper attributes for translatable fields
	 *
	 * Uses ACF_Translation_Handler to determine which field types are translatable.
	 * Adds data attributes that JavaScript uses to inject translation UI.
	 *
	 * Only works with Classic Editor. Block Editor (Gutenberg) is not supported.
	 *
	 * @param array<string, mixed> $wrapper The field wrapper attributes.
	 * @param array<string, mixed> $field   The field array.
	 * @return array<string, mixed>
	 */
	public function add_field_wrapper_attributes( array $wrapper, array $field ): array {
		global $post;

		// Only add translation UI if Classic Editor is active.
		if ( ! $this->is_classic_editor_active() ) {
			return $wrapper;
		}

		// Only add translation UI to non-original posts.
		if ( ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return $wrapper;
		}

		// Check if this field is translatable (both type and WPML preference).
		if ( ! ACF_Translation_Handler::is_translatable_field( $field['key'], $post->ID ) ) {
			return $wrapper;
		}

		/**
		 * Filter whether to show translation UI for this specific field
		 *
		 * @param bool                 $show_ui Whether to show translation UI
		 * @param array<string, mixed> $field   ACF field configuration
		 * @param int                  $post_id Post ID
		 */
		$show_translation_ui = apply_filters( 'multilingual_bridge_acf_show_translation_ui', true, $field, $post->ID );

		if ( ! $show_translation_ui ) {
			return $wrapper;
		}

		// Get language information.
		$current_lang = WPML_Post_Helper::get_language( $post->ID );
		$default_lang = \Multilingual_Bridge\Helpers\WPML_Language_Helper::get_default_language();

		// Add CSS class for JavaScript detection.
		$wrapper['class'] = isset( $wrapper['class'] )
			? $wrapper['class'] . ' multilingual-translatable-field'
			: 'multilingual-translatable-field';

		// Add data attributes for JavaScript.
		$wrapper['data-field-key']   = $field['name'];
		$wrapper['data-field-label'] = $field['label'];
		$wrapper['data-post-id']     = $post->ID;
		$wrapper['data-source-lang'] = $default_lang;
		$wrapper['data-target-lang'] = $current_lang;
		$wrapper['data-field-type']  = $field['type'];

		/**
		 * Filter field wrapper attributes before returning
		 *
		 * @param array<string, mixed> $wrapper Field wrapper attributes
		 * @param array<string, mixed> $field   ACF field configuration
		 * @param int                  $post_id Post ID
		 */
		return apply_filters( 'multilingual_bridge_acf_field_wrapper_attributes', $wrapper, $field, $post->ID );
	}

	/**
	 * Add React modal container to ACF admin footer
	 *
	 * Only renders on translation posts (not original language)
	 * and only when Classic Editor is active.
	 */
	public function add_react_container(): void {
		global $post;

		// Only add container if Classic Editor is active.
		if ( ! $this->is_classic_editor_active() ) {
			return;
		}

		if ( ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		echo '<div id="multilingual-bridge-react-modal"></div>';
	}

	/**
	 * Check if Classic Editor is active
	 *
	 * Detects whether the current editing screen uses the Classic Editor
	 * or the Block Editor (Gutenberg). Translation modal only works with
	 * Classic Editor + ACF fields.
	 *
	 * @since 1.4.0
	 * @return bool True if Classic Editor is active, false if Block Editor
	 */
	private function is_classic_editor_active(): bool {
		global $post;

		// Not on an edit screen.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		// Not on a post edit screen.
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return false;
		}

		// Check if Classic Editor plugin is active and enabled for this post type.
		if ( function_exists( 'classic_editor_replace_block_editor' ) ) {
			// Classic Editor plugin controls the editor.
			return classic_editor_replace_block_editor();
		}

		// Check if Block Editor is explicitly disabled via filter.
		$use_block_editor = use_block_editor_for_post( $post );

		/**
		 * Filter whether translation modal should be enabled
		 *
		 * Allows overriding the automatic detection. Useful for custom
		 * editor implementations or specific post types.
		 *
		 * @param bool $enabled Whether translation modal is enabled
		 * @param \WP_Post|null $post Current post object
		 * @param \WP_Screen|null $screen Current admin screen
		 */
		return apply_filters(
			'multilingual_bridge_enable_translation_modal',
			! $use_block_editor,
			$post,
			$screen
		);
	}
}
