<?php
/**
 * Automatic Translation Widget for Post Edit Sidebar
 *
 * Displays a meta box on source language posts that allows automatic translation
 * of all post meta to selected target languages.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\WPML_Language_Helper;

/**
 * Class Automatic_Translation_Widget
 *
 * Renders and manages the automatic translation sidebar widget
 */
class Automatic_Translation_Widget {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {
		// Add meta box to post edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Enqueue scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add meta box to post edit screen
	 *
	 * Only shows on source language posts.
	 *
	 * @param string $post_type Current post type.
	 */
	public function add_meta_box( string $post_type ): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Only show on original/source language posts.
		if ( ! WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		/**
		 * Filter post types to disable automatic translation
		 *
		 * By default, automatic translation is enabled for all post types.
		 * Add post types to this array to disable the widget for those types.
		 *
		 * @param string[] $disabled_post_types Post types to disable automatic translation widget
		 */
		$disabled_post_types = apply_filters(
			'multilingual_bridge_disable_automatic_translation_post_types',
			array()
		);

		// Show for all post types unless explicitly disabled.
		if ( in_array( $post_type, $disabled_post_types, true ) ) {
			return;
		}

		add_meta_box(
			'multilingual-bridge-automatic-translation',
			__( 'Automatic Translation', 'multilingual-bridge' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$source_language     = WPML_Post_Helper::get_language( $post->ID );
		$available_languages = WPML_Language_Helper::get_available_languages();
		$translations        = WPML_Post_Helper::get_language_versions( $post->ID );

		// Remove source language from available languages.
		$target_languages = array_filter(
			$available_languages,
			function ( $lang_code ) use ( $source_language ) {
				return $lang_code !== $source_language;
			},
			ARRAY_FILTER_USE_KEY
		);

		wp_nonce_field( 'multilingual_bridge_automatic_translation', 'multilingual_bridge_automatic_translation_nonce' );
		?>

		<div id="multilingual-bridge-automatic-widget">
			<div class="mlb-widget-header">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: language name */
							__( 'Source Language: %s', 'multilingual-bridge' ),
							$available_languages[ $source_language ]['name'] ?? $source_language
						)
					);
					?>
				</p>
			</div>

			<div class="mlb-widget-languages">
				<p><strong><?php esc_html_e( 'Select target languages:', 'multilingual-bridge' ); ?></strong></p>

				<?php if ( empty( $target_languages ) ) : ?>
					<p class="mlb-no-languages">
						<?php esc_html_e( 'No other languages available.', 'multilingual-bridge' ); ?>
					</p>
				<?php else : ?>
					<div class="mlb-language-list">
						<?php foreach ( $target_languages as $lang_code => $language ) : ?>
							<?php
							$has_translation = isset( $translations[ $lang_code ] );
							$translation_id  = $has_translation ? $translations[ $lang_code ] : 0;
							?>
							<label class="mlb-language-item">
								<input
									type="checkbox"
									name="mlb_target_languages[]"
									value="<?php echo esc_attr( $lang_code ); ?>"
									data-language-code="<?php echo esc_attr( $lang_code ); ?>"
									data-language-name="<?php echo esc_attr( $language['name'] ?? $lang_code ); ?>"
									data-has-translation="<?php echo esc_attr( $has_translation ? '1' : '0' ); ?>"
									data-translation-id="<?php echo esc_attr( (string) $translation_id ); ?>"
								/>
								<span class="mlb-language-flag">
									<?php echo esc_html( $language['name'] ?? $lang_code ); ?>
								</span>
								<?php if ( $has_translation ) : ?>
									<span class="mlb-translation-status mlb-has-translation" title="<?php esc_attr_e( 'Translation exists', 'multilingual-bridge' ); ?>">
										<span class="dashicons dashicons-yes-alt"></span>
									</span>
								<?php else : ?>
									<span class="mlb-translation-status mlb-no-translation" title="<?php esc_attr_e( 'No translation', 'multilingual-bridge' ); ?>">
										<span class="dashicons dashicons-marker"></span>
									</span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="mlb-widget-actions">
						<button
							type="button"
							id="mlb-generate-translation"
							class="button button-primary button-large"
							data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
							data-source-language="<?php echo esc_attr( $source_language ); ?>"
						>
							<span class="dashicons dashicons-translation"></span>
							<?php esc_html_e( 'Generate Translations', 'multilingual-bridge' ); ?>
						</button>
					</div>

					<div class="mlb-widget-progress" style="display: none;">
						<div class="mlb-progress-bar">
							<div class="mlb-progress-fill" style="width: 0%"></div>
						</div>
						<p class="mlb-progress-text"><?php esc_html_e( 'Translating...', 'multilingual-bridge' ); ?></p>
					</div>

					<div class="mlb-widget-results" style="display: none;"></div>
				<?php endif; ?>
			</div>

			<div class="mlb-widget-footer">
				<p class="description">
					<?php esc_html_e( 'This will translate all translatable post meta (ACF fields, custom fields, etc.) to the selected languages.', 'multilingual-bridge' ); ?>
				</p>
			</div>
		</div>

		<?php
	}

	/**
	 * Enqueue JavaScript and CSS
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on post edit screens.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;

		if ( ! $post || ! WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return;
		}

		// Enqueue the admin script bundle that includes automatic-translation.js.
		$this->enqueue_admin_script();

		// Localize script for the automatic translation functionality.
		wp_localize_script(
			'multilingual-bridge/multilingual-bridge-admin',
			'multilingualBridgeAuto',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'apiUrl'  => rest_url( 'multilingual-bridge/v1' ),
				'strings' => array(
					'noLanguages'       => __( 'Please select at least one target language.', 'multilingual-bridge' ),
					'translating'       => __( 'Translating...', 'multilingual-bridge' ),
					'success'           => __( 'Translation completed successfully!', 'multilingual-bridge' ),
					'error'             => __( 'Translation failed. Please try again.', 'multilingual-bridge' ),
					'partial'           => __( 'Translation completed with some errors.', 'multilingual-bridge' ),
					/* translators: %s: language name */
					'processing'        => __( 'Processing language: %s', 'multilingual-bridge' ),
					'generatingPost'    => __( 'Creating translation post...', 'multilingual-bridge' ),
					'translatingMeta'   => __( 'Translating post meta...', 'multilingual-bridge' ),
					'savingTranslation' => __( 'Saving translations...', 'multilingual-bridge' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin script bundle
	 *
	 * Enqueues the built admin JavaScript bundle that includes the automatic
	 * translation widget functionality.
	 *
	 * @return void
	 */
	private function enqueue_admin_script(): void {
		$asset_file = MULTILINGUAL_BRIDGE_PATH . '/build/multilingual-bridge-admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file; // @phpstan-ignore require.fileNotFound
		if ( ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		// Enqueue script.
		wp_enqueue_script(
			'multilingual-bridge/multilingual-bridge-admin',
			MULTILINGUAL_BRIDGE_URL . 'build/multilingual-bridge-admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue styles.
		if ( file_exists( MULTILINGUAL_BRIDGE_PATH . 'build/multilingual-bridge-admin.css' ) ) {
			wp_enqueue_style(
				'multilingual-bridge/multilingual-bridge-admin',
				MULTILINGUAL_BRIDGE_URL . 'build/multilingual-bridge-admin.css',
				array(),
				$asset['version']
			);
		}
	}
}
