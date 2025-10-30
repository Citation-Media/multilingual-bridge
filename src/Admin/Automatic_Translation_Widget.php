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
		// Priority 200 ensures this runs AFTER main script is enqueued (priority 100)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 200 );
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

		// Only show on original/source language posts.
		if ( ! $post || ! WPML_Post_Helper::is_original_post( $post->ID ) ) {
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
			'high'
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

		// Prepare data for React app.
		$widget_data = array(
			'postId'          => $post->ID,
			'sourceLanguage'  => $source_language,
			'targetLanguages' => $target_languages,
			'translations'    => $translations,
		);
		?>

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

		<div
			id="multilingual-bridge-automatic-widget"
			data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-source-language="<?php echo esc_attr( $source_language ); ?>"
			data-target-languages="<?php echo esc_attr( wp_json_encode( $target_languages ) ); ?>"
			data-translations="<?php echo esc_attr( wp_json_encode( $translations ) ); ?>"
		>
			<!-- React app will render here -->
		</div>

		<?php
	}

	/**
	 * Enqueue localization data for automatic translation widget
	 *
	 * Note: The main admin script is already enqueued by Multilingual_Bridge::define_admin_hooks()
	 * at priority 100. This method runs at priority 200 to ensure the script is enqueued before
	 * we try to localize it. This method only adds localization data for the automatic translation
	 * functionality.
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

		// Localize script for the automatic translation functionality.
		// Script is already enqueued by Multilingual_Bridge::define_admin_hooks()
		// Note: @wordpress/api-fetch handles authentication/nonce automatically
		wp_localize_script(
			'multilingual-bridge/multilingual-bridge-admin',
			'multilingualBridgeAuto',
			array(
				'editPostUrl' => admin_url( 'post.php?post=POST_ID&action=edit' ),
				'strings'     => array(
					'noLanguages'          => __( 'Please select at least one target language.', 'multilingual-bridge' ),
					'translating'          => __( 'Translating...', 'multilingual-bridge' ),
					'success'              => __( 'Translation completed successfully!', 'multilingual-bridge' ),
					'error'                => __( 'Translation failed. Please try again.', 'multilingual-bridge' ),
					'partial'              => __( 'Translation completed with some errors.', 'multilingual-bridge' ),
					'editTranslation'      => __( 'Edit translation', 'multilingual-bridge' ),
					'editPost'             => __( 'Edit Post', 'multilingual-bridge' ),
					'newTranslation'       => __( 'New translation created.', 'multilingual-bridge' ),
					'updatedTranslation'   => __( 'Translation updated.', 'multilingual-bridge' ),
					'translationCompleted' => __( 'Translation completed.', 'multilingual-bridge' ),
					/* translators: %s: language name */
					'processing'           => __( 'Processing language: %s', 'multilingual-bridge' ),
					'generatingPost'       => __( 'Creating translation post...', 'multilingual-bridge' ),
					'translatingMeta'      => __( 'Translating post meta...', 'multilingual-bridge' ),
					'savingTranslation'    => __( 'Saving translations...', 'multilingual-bridge' ),
				),
			)
		);
	}
}
