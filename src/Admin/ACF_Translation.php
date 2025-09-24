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
			@click="closeModal"
			x-cloak
		>
			<div
				class="multilingual-bridge-modal"
				@click.stop
			>
				<div class="multilingual-bridge-modal-header">
					<h2 x-text="modalTitle"><?php esc_html_e( 'Translate Field', 'multilingual-bridge' ); ?></h2>
					<button
						@click="closeModal"
						aria-label="<?php esc_attr_e( 'Close modal', 'multilingual-bridge' ); ?>"
					>
						×
					</button>
				</div>
				<div class="multilingual-bridge-modal-body">
					<div class="multilingual-bridge-modal-columns">
						<!-- Original Column -->
						<div class="multilingual-bridge-modal-column">
							<h3 x-text="sourceLangLabel"><?php /* translators: %s: Language code */ printf( esc_html__( 'Original (%s)', 'multilingual-bridge' ), 'en' ); ?></h3>
							<textarea
								x-model="originalValue"
								:disabled="isLoading"
							></textarea>
						</div>

						<!-- Translation Column -->
						<div class="multilingual-bridge-modal-column">
							<h3 x-text="targetLangLabel"><?php /* translators: %s: Language code */ printf( esc_html__( 'Translation (%s)', 'multilingual-bridge' ), 'en' ); ?></h3>
							<textarea
								x-model="translatedValue"
								:disabled="isLoading"
							></textarea>
						</div>
					</div>

					<div class="multilingual-bridge-modal-error" x-show="errorMessage" x-text="errorMessage"><?php esc_html_e( 'An error occurred', 'multilingual-bridge' ); ?></div>

					<div class="multilingual-bridge-modal-actions">
						<button
							class="multilingual-bridge-btn-copy"
							@click="copyOriginalToTranslation"
							:disabled="!originalValue.trim()"
							title="<?php esc_attr_e( 'Copy original text to translation field', 'multilingual-bridge' ); ?>"
						>
							<?php esc_html_e( 'Copy Original', 'multilingual-bridge' ); ?>
						</button>
						<button
							class="multilingual-bridge-btn-translate"
							@click="translateText"
							:disabled="isLoading || !originalValue.trim()"
							:style="{ opacity: isLoading || !originalValue.trim() ? 0.6 : 1, cursor: isLoading || !originalValue.trim() ? 'not-allowed' : 'pointer' }"
						>
							<?php esc_html_e( 'Translate', 'multilingual-bridge' ); ?>
						</button>
						<button
							class="multilingual-bridge-btn-save"
							@click="saveTranslation"
							:disabled="!translatedValue.trim()"
						>
							<?php esc_html_e( 'Save Translation', 'multilingual-bridge' ); ?>
						</button>
						<button
							class="multilingual-bridge-btn-cancel"
							@click="closeModal"
						>
							<?php esc_html_e( 'Cancel', 'multilingual-bridge' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
