<?php
/**
 * ACF Translation integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Integrations\ACF;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;

/**
 * Class ACF_Translation
 */
class ACF_Translation {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {
		// Only run if ACF is active
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		// Hook into ACF field wrapper to add data attributes
		add_filter( 'acf/field_wrapper_attributes', array( $this, 'add_field_wrapper_attributes' ), 10, 2 );

		// Add React modal container
		add_action( 'acf/input/admin_footer', array( $this, 'add_react_container' ) );
	}

	/**
	 * Add field wrapper attributes for translatable fields
	 *
	 * @param array<string, mixed> $wrapper The field wrapper attributes.
	 * @param array<string, mixed> $field   The field array.
	 * @return array<string, mixed>
	 */
	public function add_field_wrapper_attributes( array $wrapper, array $field ): array {
		// Allow filtering of supported field types
		$supported_types = apply_filters( 'multilingual_bridge_acf_supported_types', array( 'text', 'textarea', 'wysiwyg', 'lexical-editor' ) );
		if ( ! in_array( $field['type'], $supported_types, true ) ) {
			return $wrapper;
		}

		// Only show on translated posts (not default language)
		global $post;
		if ( ! $post || ! WPML_Post_Helper::is_translated_post( $post->ID ) ) {
			return $wrapper;
		}

		$current_lang = WPML_Post_Helper::get_language( $post->ID );
		$default_lang = \Multilingual_Bridge\Helpers\WPML_Language_Helper::get_default_language();

		if ( $current_lang === $default_lang ) {
			return $wrapper;
		}

		// Add class and data attributes
		$wrapper['class']            = isset( $wrapper['class'] ) ? $wrapper['class'] . ' multilingual-translatable-field' : 'multilingual-translatable-field';
		$wrapper['data-field-key']   = $field['name'];
		$wrapper['data-field-label'] = $field['label'];
		$wrapper['data-post-id']     = $post->ID;
		$wrapper['data-source-lang'] = $default_lang;
		$wrapper['data-target-lang'] = $current_lang;
		$wrapper['data-field-type']  = $field['type'];

		return $wrapper;
	}


	/**
	 * Add React modal container to ACF admin footer
	 */
	public function add_react_container(): void {
		// Only show on translated posts (not default language)
		global $post;
		if ( ! $post || ! WPML_Post_Helper::is_translated_post( $post->ID ) ) {
			return;
		}

		$current_lang = WPML_Post_Helper::get_language( $post->ID );
		$default_lang = \Multilingual_Bridge\Helpers\WPML_Language_Helper::get_default_language();

		if ( $current_lang === $default_lang ) {
			return;
		}

		// React will render the modal here
		echo '<div id="multilingual-bridge-react-modal"></div>';
	}
}
