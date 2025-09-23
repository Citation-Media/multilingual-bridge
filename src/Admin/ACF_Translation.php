<?php
/**
 * ACF Translation integration
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

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
		if ( ! function_exists( 'acf' ) ) {
			return;
		}

		// Hook into ACF field wrapper to add data attributes
		add_filter( 'acf/field_wrapper_attributes', array( $this, 'add_field_wrapper_attributes' ), 10, 2 );

		// Add Alpine.js modal container
		add_action( 'acf/input/admin_footer', array( $this, 'add_alpine_container' ) );
	}

	/**
	 * Add field wrapper attributes for translatable fields
	 *
	 * @param array<string, mixed> $wrapper The field wrapper attributes.
	 * @param array<string, mixed> $field   The field array.
	 * @return array<string, mixed>
	 */
	public function add_field_wrapper_attributes( array $wrapper, array $field ): array {
		// Only add to supported field types
		$supported_types = array( 'text', 'textarea', 'wysiwyg' );
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
	 * Add Alpine.js modal container to ACF admin footer
	 */
	public function add_alpine_container(): void {
		?>
		<div
			x-data="multilingualBridgeModal()"
			x-show="isOpen"
			x-transition
			class="multilingual-bridge-modal-backdrop"
			style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;"
			@click="closeModal"
			x-cloak
		>
			<div
				class="multilingual-bridge-modal"
				style="background-color: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-width: 90vw; max-height: 90vh; overflow: auto; position: relative;"
				@click.stop
			>
				<div
					class="multilingual-bridge-modal-header"
					style="padding: 16px 20px; border-bottom: 1px solid #e1e5e9; display: flex; justify-content: space-between; align-items: center;"
				>
					<h2 style="margin: 0; font-size: 18px; font-weight: 600;" x-text="modalTitle"></h2>
					<button
						@click="closeModal"
						style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; padding: 4px;"
						aria-label="Close modal"
					>
						×
					</button>
				</div>
				<div
					class="multilingual-bridge-modal-body"
					style="padding: 20px;"
				>
					<div style="display: flex; gap: 20px; min-height: 400px;">
						<!-- Original Column -->
						<div style="flex: 1;">
							<h3 style="margin-top: 0; color: #374151;" x-text="sourceLangLabel"></h3>
							<textarea
								x-model="originalValue"
								style="width: 100%; min-height: 300px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit; resize: vertical;"
								:disabled="isLoading"
							></textarea>
						</div>

						<!-- Translation Column -->
						<div style="flex: 1;">
							<h3 style="margin-top: 0; color: #374151;" x-text="targetLangLabel"></h3>
							<textarea
								x-model="translatedValue"
								style="width: 100%; min-height: 300px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit; resize: vertical;"
								:disabled="isLoading"
							></textarea>
						</div>
					</div>

					<div x-show="errorMessage" style="margin-top: 16px; padding: 8px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; color: #dc2626;" x-text="errorMessage"></div>

					<div style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
						<button
							@click="translateText"
							:disabled="isLoading || !originalValue.trim()"
							style="padding: 8px 16px; background-color: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;"
							:style="{ opacity: isLoading || !originalValue.trim() ? 0.6 : 1, cursor: isLoading || !originalValue.trim() ? 'not-allowed' : 'pointer' }"
						>
							<span x-text="isLoading ? 'Translating...' : 'Translate'"></span>
						</button>
						<button
							@click="saveTranslation"
							:disabled="!translatedValue.trim()"
							style="padding: 8px 16px; background-color: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;"
						>
							Save Translation
						</button>
						<button
							@click="closeModal"
							style="padding: 8px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer;"
						>
							Cancel
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
