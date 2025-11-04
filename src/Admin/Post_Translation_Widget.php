<?php
/**
 * Post Translation Widget for Post Edit Sidebar
 *
 * Displays a meta box on source language posts that allows translation
 * of all post meta to selected target languages.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Helpers\WPML_Language_Helper;
use Multilingual_Bridge\Translation\Sync_Translations;

/**
 * Class Post_Translation_Widget
 *
 * Renders and manages the post translation sidebar widget
 */
class Post_Translation_Widget {

	/**
	 * Initialize hooks
	 */
	public function register_hooks(): void {
		// Add meta box to post edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Enqueue scripts and styles.
		// Priority 200 ensures this runs AFTER main script is enqueued (priority 100)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 200 );

		// Add CSS class to ACF fields with pending updates (translated posts only).
		add_filter( 'acf/field_wrapper_attributes', array( $this, 'add_pending_class_to_acf_fields' ), 10, 2 );
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
		 * Filter post types to disable post translation
		 *
		 * By default, post translation is enabled for all post types.
		 * Add post types to this array to disable the widget for those types.
		 *
		 * @param string[] $disabled_post_types Post types to disable post translation widget
		 */
		$disabled_post_types = apply_filters(
			'multilingual_bridge_disable_post_translation_post_types',
			array()
		);

		// Show for all post types unless explicitly disabled.
		if ( in_array( $post_type, $disabled_post_types, true ) ) {
			return;
		}

		add_meta_box(
			'multilingual-bridge-post-translation',
			__( 'Post Translation', 'multilingual-bridge' ),
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

		// Get pending updates for this post (sync translations feature).
		$sync_translations = new Sync_Translations();
		$pending_updates   = $sync_translations->get_pending_updates( $post->ID );

		// Build pending updates data for each translation language.
		$translations_pending = array();
		foreach ( array_keys( $translations ) as $lang_code ) {
			// Skip source language.
			if ( $lang_code === $source_language ) {
				continue;
			}

			// Check if any field has pending updates.
			$has_pending                        = $sync_translations->has_pending_updates( $post->ID );
			$translations_pending[ $lang_code ] = array(
				'hasPending' => $has_pending,
				'content'    => $sync_translations->get_pending_content_updates( $post->ID ),
				'meta'       => $sync_translations->get_pending_meta_updates( $post->ID ),
			);
		}

		wp_nonce_field( 'multilingual_bridge_post_translation', 'multilingual_bridge_post_translation_nonce' );

		// Prepare data for React app.
		$widget_data = array(
			'postId'              => $post->ID,
			'sourceLanguage'      => $source_language,
			'targetLanguages'     => $target_languages,
			'translations'        => $translations,
			'translationsPending' => $translations_pending,
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
		id="multilingual-bridge-post-widget"
		data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
		data-source-language="<?php echo esc_attr( $source_language ); ?>"
		data-target-languages="<?php echo esc_attr( wp_json_encode( $target_languages ) ); ?>"
		data-translations="<?php echo esc_attr( wp_json_encode( $translations ) ); ?>"
		data-translations-pending="<?php echo esc_attr( wp_json_encode( $translations_pending ) ); ?>"
	>
		<!-- React app will render here -->
	</div>

		<?php
	}

	/**
	 * Enqueue localization data for post translation widget
	 *
	 * Note: The main admin script is already enqueued by Multilingual_Bridge::define_admin_hooks()
	 * at priority 100. This method runs at priority 200 to ensure the script is enqueued before
	 * we try to localize it. This method adds localization data for both source and translated posts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on post edit screens.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;

		if ( ! $post ) {
			return;
		}

		// Localize script for the post translation functionality.
		// Script is already enqueued by Multilingual_Bridge::define_admin_hooks()
		// Note: @wordpress/api-fetch handles authentication/nonce automatically
		wp_localize_script(
			'multilingual-bridge/multilingual-bridge-admin',
			'multilingualBridgePost',
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

	/**
	 * Add CSS class to ACF fields with pending translation updates
	 *
	 * This filter runs on translated posts only and adds 'mlb-field-pending-sync'
	 * class to ACF field wrappers that have pending updates from the source post.
	 *
	 * @param array<string, mixed> $wrapper ACF field wrapper attributes.
	 * @param array<string, mixed> $field   ACF field array.
	 * @return array<string, mixed> Modified wrapper attributes
	 */
	public function add_pending_class_to_acf_fields( array $wrapper, array $field ): array {
		global $post;

		// Only run on translated posts (not source posts).
		if ( ! $post || WPML_Post_Helper::is_original_post( $post->ID ) ) {
			return $wrapper;
		}

		// Get pending meta updates from source post.
		$source_post_id = WPML_Post_Helper::get_default_language_post_id( $post->ID );
		if ( ! $source_post_id ) {
			return $wrapper;
		}

		$sync_translations = new Sync_Translations();
		$pending_meta      = $sync_translations->get_pending_meta_updates( $source_post_id );

		// Check if this field has pending updates.
		$field_name = $field['name'] ?? '';
		if ( $field_name && in_array( $field_name, $pending_meta, true ) ) {
			// Add pending sync class to field wrapper.
			$wrapper['class'] = isset( $wrapper['class'] ) ? $wrapper['class'] . ' mlb-field-pending-sync' : 'mlb-field-pending-sync';
		}

		return $wrapper;
	}
}
