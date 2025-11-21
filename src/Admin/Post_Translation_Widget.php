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
use Multilingual_Bridge\Helpers\Translation_Post_Types;
use Multilingual_Bridge\Translation\Change_Tracking\Post_Data_Tracker;
use Multilingual_Bridge\Translation\Change_Tracking\Post_Meta_Tracker;

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
	}

	/**
	 * Add meta box to post edit screen
	 *
	 * Shows full widget on source posts and navigation-only widget on translated posts.
	 *
	 * @param string $post_type Current post type.
	 */
	public function add_meta_box( string $post_type ): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Only show for enabled post types.
		if ( ! Translation_Post_Types::is_enabled( $post_type ) ) {
			return;
		}

		// Show full widget on original/source language posts.
		if ( WPML_Post_Helper::is_original_post( $post->ID ) ) {
			add_meta_box(
				'multilingual-bridge-post-translation',
				__( 'Post Translation', 'multilingual-bridge' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		} elseif ( WPML_Post_Helper::is_translated_post( $post->ID ) ) {
			// Show navigation-only widget on translated posts.
			add_meta_box(
				'multilingual-bridge-post-translation-nav',
				__( 'Post Languages', 'multilingual-bridge' ),
				array( $this, 'render_navigation_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
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

		// Get pending updates for this post (post change tracking feature).
		// Build pending updates data for each translation language.
		$translations_pending = array();
		foreach ( $translations as $lang_code => $translation_value ) {
			// Skip source language.
			if ( $lang_code === $source_language ) {
				continue;
			}

			// Get translation post ID.
			$translation_post_id = is_int( $translation_value ) ? $translation_value : $translation_value->ID;

			// Check if this translation post has pending updates (using static methods).
			$has_content_pending  = Post_Data_Tracker::has_pending_content_updates( $translation_post_id );
			$has_meta_pending     = Post_Meta_Tracker::has_pending_meta_updates( $translation_post_id );
			$has_pending_for_lang = $has_content_pending || $has_meta_pending;

			$translations_pending[ $lang_code ] = array(
				'hasPending' => $has_pending_for_lang,
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
	 * Render the navigation meta box for translated posts
	 *
	 * Shows language links without translation controls.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_navigation_meta_box( \WP_Post $post ): void {
		$current_language    = WPML_Post_Helper::get_language( $post->ID );
		$available_languages = WPML_Language_Helper::get_available_languages();
		$translations        = WPML_Post_Helper::get_language_versions( $post->ID );

		// Get pending updates for all translations INCLUDING current language.
		// Build pending updates data for each translation language.
		$translations_pending = array();
		foreach ( $translations as $lang_code => $translation_value ) {
			// Get translation post ID.
			$translation_post_id = is_int( $translation_value ) ? $translation_value : $translation_value->ID;

			// Check if this translation post has pending updates (using static methods).
			$has_content_pending  = Post_Data_Tracker::has_pending_content_updates( $translation_post_id );
			$has_meta_pending     = Post_Meta_Tracker::has_pending_meta_updates( $translation_post_id );
			$has_pending_for_lang = $has_content_pending || $has_meta_pending;

			$translations_pending[ $lang_code ] = array(
				'hasPending' => $has_pending_for_lang,
			);
		}

		?>
		<div class="mlb-widget-header">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: language name */
						__( 'Current Language: %s', 'multilingual-bridge' ),
						$available_languages[ $current_language ]['name'] ?? $current_language
					)
				);
				?>
			</p>
		</div>

		<div
			id="multilingual-bridge-post-widget-nav"
			data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-current-language="<?php echo esc_attr( $current_language ); ?>"
			data-available-languages="<?php echo esc_attr( wp_json_encode( $available_languages ) ); ?>"
			data-translations="<?php echo esc_attr( wp_json_encode( $translations ) ); ?>"
			data-translations-pending="<?php echo esc_attr( wp_json_encode( $translations_pending ) ); ?>"
			data-is-navigation="true"
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
					'pendingUpdateTooltip' => __( 'This field has translation updates from the source language', 'multilingual-bridge' ),
				),
			)
		);
	}
}
