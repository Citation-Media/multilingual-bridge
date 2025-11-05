<?php
/**
 * REST API endpoints for translation functionality
 *
 * Provides endpoints for:
 * - Fetching original field values from default language posts
 * - Translating text using configured translation provider
 * - One-time post translation to multiple languages (overwrites existing translations)
 *
 * ## Language Code Architecture
 *
 * This class handles three different language code formats throughout the translation process:
 *
 * 1. **WPML Language Codes** (e.g., 'zh-hans', 'pt-br', 'en-us')
 *    - Format: Lowercase with hyphens
 *    - Used by: WPML core functions and database storage
 *    - Purpose: WPML's internal representation configured in WordPress admin
 *
 * 2. **BCP 47 Language Tags** (e.g., 'zh-Hans', 'pt-BR', 'en-US')
 *    - Format: Proper casing - language(lowercase), Script(Titlecase), Region(UPPERCASE)
 *    - Used by: LanguageTag library for validation and standardization
 *    - Purpose: International standard for language identification (RFC 5646)
 *
 * 3. **Provider-Specific Codes** (e.g., DeepL uses 'ZH', 'ZH-HANT', 'PT-BR')
 *    - Format: Varies by translation service provider
 *    - Used by: External translation APIs (DeepL, Google Translate, etc.)
 *    - Purpose: Service-specific requirements for API requests
 *
 * ### Why We Need Both WPML Codes AND LanguageTag Objects
 *
 * **The Challenge:**
 * - WPML stores and expects lowercase codes: `zh-hans`, `pt-br`
 * - LanguageTag validation requires proper BCP 47 casing: `zh-Hans`, `pt-BR`
 * - LanguageTag->primaryLanguageSubtag only extracts base code: `zh-hans` → `zh`
 *
 * **The Solution:**
 * We maintain BOTH representations throughout the translation flow:
 * - Original WPML code (string) → For all WPML API operations
 * - Normalized LanguageTag (object) → For validation and translation provider operations
 *
 * **Critical Operations That Require Original WPML Codes:**
 * - `WPML_Post_Helper::set_language()` - Sets post language in WPML database
 * - `WPML_Post_Helper::relate_posts_as_translations()` - Links translations
 * - `WPML_Post_Helper::get_translation_for_lang()` - Retrieves existing translations
 *
 * Using only `primaryLanguageSubtag` (e.g., 'zh') would fail because:
 * - WPML expects full code 'zh-hans' or 'zh-hant' to distinguish variants
 * - WPML's database stores the complete language code
 * - Error: "Language 'zh' is not configured in WPML" when using subtag only
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\REST;

use Multilingual_Bridge\Helpers\WPML_Language_Helper;
use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Meta_Translation_Handler;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Translation_API
 *
 * REST API controller for translation operations
 */
class Translation_API extends WP_REST_Controller {

	/**
	 * Namespace for the API
	 *
	 * @var string
	 */
	protected $namespace = 'multilingual-bridge/v1';

	/**
	 * Translation Manager instance
	 *
	 * @var Translation_Manager
	 */
	private Translation_Manager $translation_manager;

	/**
	 * Meta Translation Handler instance
	 *
	 * @var Meta_Translation_Handler
	 */
	private Meta_Translation_Handler $meta_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->meta_handler        = new Meta_Translation_Handler();
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get meta value from default language.
		register_rest_route(
			$this->namespace,
			'/meta/(?P<post_id>\d+)/(?P<field_key>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_meta_value' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_id'   => array(
							'description' => __( 'Post ID to retrieve meta value from', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'field_key' => array(
							'description' => __( 'Meta field key to retrieve', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 255,
							'pattern'     => '^[a-zA-Z0-9_-]+$',
						),
					),
				),
			)
		);

		// Translate text.
		register_rest_route(
			$this->namespace,
			'/translate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'translate_text' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'text'        => array(
							'description' => __( 'Text to translate', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 50000,
						),
						'target_lang' => array(
							'description' => __( 'Target language code (BCP 47)', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'string',
							'enum'        => $this->get_language_tag_enum(),
						),
						'source_lang' => array(
							'description' => __( 'Source language code (BCP 47), auto-detect if not provided', 'multilingual-bridge' ),
							'type'        => 'string',
							'enum'        => $this->get_language_tag_enum(),
						),
					),
				),
			)
		);

		// One-time post translation (overwrites existing translations).
		register_rest_route(
			$this->namespace,
			'/post-translate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'post_translate' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'post_id'          => array(
							'description' => __( 'Source post ID to translate from', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'target_languages' => array(
							'description' => __( 'Array of target language codes (BCP 47). This will overwrite existing translations.', 'multilingual-bridge' ),
							'required'    => true,
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => $this->get_language_tag_enum(),
							),
							'minItems'    => 1,
							'maxItems'    => 20,
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for API access
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get REST API enum values for LanguageTag
	 *
	 * Returns valid BCP 47 language tags for REST API validation
	 * Uses WPML's active languages to generate available tags
	 *
	 * Note: Returns both the original WPML codes AND their BCP 47 normalized versions
	 * to allow flexibility in API requests (e.g., both "zh-hans" and "zh-Hans" work)
	 *
	 * @return string[]
	 */
	private function get_language_tag_enum(): array {
		// Get active WPML languages.
		$active_languages = WPML_Language_Helper::get_active_language_codes();
		$enum_values      = array();

		foreach ( $active_languages as $language ) {
			// Always include the original WPML language code.
			$enum_values[] = $language;

			// Also try to include the normalized BCP 47 version if it differs.
			try {
				$lang_tag   = LanguageTag::fromString( $language );
				$normalized = $lang_tag->toString();

				// Only add normalized version if it's different from original.
				if ( $normalized !== $language ) {
					$enum_values[] = $normalized;
				}
			} catch ( \Exception $e ) {
				// Skip if conversion fails, we already have the original code.
				continue;
			}
		}

		// Remove duplicates and re-index array.
		return array_values( array_unique( $enum_values ) );
	}

	/**
	 * Get meta value from default language post
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function get_meta_value( WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post_id' );
		$field_key = $request->get_param( 'field_key' );

		// Get default language version of the post.
		$default_lang_post_id = WPML_Post_Helper::get_default_language_post_id( $post_id );

		if ( ! $default_lang_post_id ) {
			return new WP_Error(
				'post_not_found',
				__( 'Default language version of post not found', 'multilingual-bridge' ),
				array( 'status' => 404 )
			);
		}

		$value = null;

		// Try ACF fields first if available.
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $field_key, $default_lang_post_id );
		}

		// Fallback to post meta if ACF returns nothing.
		if ( empty( $value ) ) {
			$value = get_post_meta( $default_lang_post_id, $field_key, true );
		}

		/**
		 * Filter meta value before returning
		 *
		 * @param mixed  $value     Meta value
		 * @param string $field_key Field key
		 * @param int    $post_id   Original post ID
		 */
		$value = apply_filters( 'multilingual_bridge_rest_meta_value', $value, $field_key, $default_lang_post_id );

		return new WP_REST_Response(
			array(
				'value' => $value,
			),
			200
		);
	}

	/**
	 * Translate text using Translation Manager
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function translate_text( WP_REST_Request $request ) {
		$text             = $request->get_param( 'text' );
		$target_lang_code = $request->get_param( 'target_lang' );
		$source_lang_code = $request->get_param( 'source_lang' );

		// Convert string codes to LanguageTag instances.
		try {
			$target_lang = LanguageTag::fromString( $target_lang_code );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %1$s: language code, %2$s: error message */
					__( 'Invalid target language code: %1$s (%2$s)', 'multilingual-bridge' ),
					$target_lang_code,
					$e->getMessage()
				),
				array( 'status' => 400 )
			);
		}

		$source_lang = null;
		if ( ! empty( $source_lang_code ) ) {
			try {
				$source_lang = LanguageTag::fromString( $source_lang_code );
			} catch ( \Exception $e ) {
				return new WP_Error(
					'invalid_language',
					sprintf(
						/* translators: %1$s: language code, %2$s: error message */
						__( 'Invalid source language code: %1$s (%2$s)', 'multilingual-bridge' ),
						$source_lang_code,
						$e->getMessage()
					),
					array( 'status' => 400 )
				);
			}
		}

		// Use Translation Manager to translate (provider managed by Translation Manager).
		$translation = $this->translation_manager->translate(
			$target_lang,
			$text,
			$source_lang
		);

		if ( is_wp_error( $translation ) ) {
			return $translation;
		}

		return new WP_REST_Response(
			array(
				'translation' => $translation,
			),
			200
		);
	}

	/**
	 * Translate post and meta to multiple languages (one-time translation, overwrites existing translations)
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function post_translate( WP_REST_Request $request ) {
		$post_id          = (int) $request->get_param( 'post_id' );
		$target_languages = $request->get_param( 'target_languages' );

		// Verify post exists.
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Source post not found', 'multilingual-bridge' ),
				array( 'status' => 404 )
			);
		}

		// Verify post is original/source language.
		if ( ! WPML_Post_Helper::is_original_post( $post_id ) ) {
			return new WP_Error(
				'not_source_post',
				__( 'Post is not a source language post', 'multilingual-bridge' ),
				array( 'status' => 400 )
			);
		}

		$source_language = WPML_Post_Helper::get_language( $post_id );

		// Normalize WPML language code to BCP 47 format before creating LanguageTag.
		// Example: 'zh-hans' (WPML) → 'zh-Hans' (BCP 47)
		// This is required because LanguageTag library validates against BCP 47 standard.
		$normalized_source = $this->normalize_wpml_language_code( $source_language );

		// Convert source language string to LanguageTag.
		try {
			$source_lang_tag = LanguageTag::fromString( $normalized_source );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %1$s: language code, %2$s: error message */
					__( 'Invalid source language code: %1$s (%2$s)', 'multilingual-bridge' ),
					$source_language,
					$e->getMessage()
				),
				array( 'status' => 400 )
			);
		}

		$results = array(
			'success'     => true,
			'source_post' => $post_id,
			'languages'   => array(),
		);

		// Process each target language.
		foreach ( $target_languages as $target_lang_code ) {
			// Normalize WPML language code to BCP 47 format before creating LanguageTag.
			// Example: 'pt-br' (WPML) → 'pt-BR' (BCP 47)
			// We preserve the original WPML code ($target_lang_code) for WPML operations.
			$normalized_code = $this->normalize_wpml_language_code( $target_lang_code );

			// Convert target language string to LanguageTag.
			try {
				$target_lang_tag = LanguageTag::fromString( $normalized_code );
			} catch ( \Exception $e ) {
				$results['languages'][ $target_lang_code ] = array(
					'success'         => false,
					'target_post_id'  => 0,
					'created_new'     => false,
					'meta_translated' => 0,
					'meta_skipped'    => 0,
					'errors'          => array(
						sprintf(
							/* translators: %1$s: language code, %2$s: error message */
							__( 'Invalid target language code: %1$s (%2$s)', 'multilingual-bridge' ),
							$target_lang_code,
							$e->getMessage()
						),
					),
				);
				$results['success']                        = false;
				continue;
			}

			// Translate to target language.
			// IMPORTANT: We pass both the LanguageTag (for translation provider) AND
			// the original WPML code (for WPML API operations).
			// Why? Because WPML_Post_Helper methods require the exact WPML language code
			// that's configured in WordPress (e.g., 'zh-hans' not 'zh').
			$language_result = $this->translate_to_language(
				$source_post,
				$source_lang_tag,
				$target_lang_tag,
				$target_lang_code // Original WPML code preserved here.
			);

			$results['languages'][ $target_lang_code ] = $language_result;

			// Mark overall success as false if any language fails.
			if ( ! $language_result['success'] ) {
				$results['success'] = false;
			}
		}

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Translate post to a single target language
	 *
	 * This method orchestrates the complete translation process:
	 * 1. Check for existing translations
	 * 2. Create new or update existing translation post
	 * 3. Translate post meta fields
	 *
	 * ## Language Code Parameters Explained:
	 *
	 * We accept BOTH LanguageTag objects AND the original WPML code string because:
	 *
	 * - **$source_lang / $target_lang (LanguageTag):**
	 *   Used for translation operations via Translation_Manager and provider APIs.
	 *   These are normalized BCP 47 format (e.g., 'zh-Hans') required by LanguageTag library.
	 *
	 * - **$target_lang_wpml (string):**
	 *   Used for ALL WPML API operations (set_language, relate_posts, get_translation).
	 *   Must be the exact WPML code (e.g., 'zh-hans') configured in WordPress admin.
	 *
	 * **Why not just use $target_lang->primaryLanguageSubtag->value?**
	 * Because it only extracts the base language code:
	 * - 'zh-hans' → becomes 'zh' (loses script information)
	 * - 'pt-br' → becomes 'pt' (loses region information)
	 *
	 * WPML needs the FULL code to distinguish between variants:
	 * - 'zh-hans' (Simplified Chinese) vs 'zh-hant' (Traditional Chinese)
	 * - 'pt-br' (Brazilian Portuguese) vs 'pt-pt' (European Portuguese)
	 *
	 * @param \WP_Post    $source_post         Source post object.
	 * @param LanguageTag $source_lang         Source language tag (BCP 47 normalized).
	 * @param LanguageTag $target_lang         Target language tag (BCP 47 normalized).
	 * @param string      $target_lang_wpml    Original WPML language code (e.g., 'zh-hans', 'pt-br').
	 * @return array<string, mixed> Translation result
	 */
	private function translate_to_language( \WP_Post $source_post, LanguageTag $source_lang, LanguageTag $target_lang, string $target_lang_wpml ): array {
		$result = array(
			'success'         => false,
			'target_post_id'  => 0,
			'created_new'     => false,
			'meta_translated' => 0,
			'meta_skipped'    => 0,
			'errors'          => array(),
		);

		// Check if translation already exists using the original WPML language code.
		$existing_translation = WPML_Post_Helper::get_translation_for_lang( $source_post->ID, $target_lang_wpml );

		if ( $existing_translation ) {
			// Update existing translation.
			$target_post_id = $this->update_translation_post( $source_post, $existing_translation, $source_lang, $target_lang );

			if ( is_wp_error( $target_post_id ) ) {
				$result['errors'][] = $target_post_id->get_error_message();
				return $result;
			}

			$result['target_post_id'] = $target_post_id;
			$result['created_new']    = false;
		} else {
			// Create new translation post.
			$target_post_id = $this->create_translation_post( $source_post, $source_lang, $target_lang, $target_lang_wpml );

			if ( is_wp_error( $target_post_id ) ) {
				$result['errors'][] = $target_post_id->get_error_message();
				return $result;
			}

			$result['target_post_id'] = $target_post_id;
			$result['created_new']    = true;
		}

		// Translate post meta.
		$meta_results = $this->meta_handler->translate_post_meta(
			$source_post->ID,
			$target_post_id,
			$target_lang,
			$source_lang
		);

		$result['meta_translated'] = $meta_results['translated'];
		$result['meta_skipped']    = $meta_results['skipped'];

		// Only include actual critical errors (not skip errors).
		if ( ! empty( $meta_results['errors'] ) ) {
			$result['errors'] = array_merge( $result['errors'], $meta_results['errors'] );
		}

		// Trigger wp_update_post to fire WordPress hooks (save_post, etc.) after all changes are complete.
		// This ensures that other plugins and systems are notified of the translation updates.
		$update_result = wp_update_post(
			array(
				'ID'          => $target_post_id,
				'post_status' => get_post_status( $target_post_id ), // Preserve current status.
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			$result['errors'][] = $update_result->get_error_message();
		}

		// Consider success if:
		// 1. Target post exists
		// 2. Either some meta was translated, OR there were no critical errors in meta translation.
		$has_critical_errors = ! empty( $meta_results['errors'] );
		$result['success']   = $target_post_id > 0 && ! $has_critical_errors;

		return $result;
	}

	/**
	 * Translate post content (title, content, excerpt)
	 *
	 * @param \WP_Post    $source_post Source post object.
	 * @param LanguageTag $target_lang Target language tag.
	 * @param LanguageTag $source_lang Source language tag.
	 * @return array{title: string, content: string, excerpt: string}|WP_Error Translated content or error
	 */
	private function translate_post_content( \WP_Post $source_post, LanguageTag $target_lang, LanguageTag $source_lang ) {
		// Translate post title.
		$translated_title = $this->translation_manager->translate(
			$target_lang,
			$source_post->post_title,
			$source_lang
		);

		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate post content (if not empty).
		$translated_content = '';
		if ( ! empty( $source_post->post_content ) ) {
			$translated_content = $this->translation_manager->translate(
				$target_lang,
				$source_post->post_content,
				$source_lang
			);

			if ( is_wp_error( $translated_content ) ) {
				return $translated_content;
			}
		}

		// Translate post excerpt (if not empty).
		$translated_excerpt = '';
		if ( ! empty( $source_post->post_excerpt ) ) {
			$translated_excerpt = $this->translation_manager->translate(
				$target_lang,
				$source_post->post_excerpt,
				$source_lang
			);

			if ( is_wp_error( $translated_excerpt ) ) {
				return $translated_excerpt;
			}
		}

		return array(
			'title'   => $translated_title,
			'content' => $translated_content,
			'excerpt' => $translated_excerpt,
		);
	}

	/**
	 * Create a new translation post
	 *
	 * Creates a new WordPress post with translated content and establishes
	 * the translation relationship in WPML.
	 *
	 * ## Language Code Usage in This Method:
	 *
	 * - **LanguageTag objects ($source_lang, $target_lang):**
	 *   Passed to translate_post_content() → Translation_Manager → Provider
	 *   Used for actual text translation via external APIs (DeepL, etc.)
	 *
	 * - **WPML language code string ($target_lang_wpml):**
	 *   Used for WPML operations that store/retrieve from WordPress database:
	 *   - Line ~605: WPML_Post_Helper::set_language() - Sets post language
	 *   - Line ~615: WPML_Post_Helper::relate_posts_as_translations() - Links translations
	 *
	 * **Critical:** These WPML methods expect the exact language code from WPML config.
	 * Using $target_lang->primaryLanguageSubtag->value would fail with:
	 * "Language 'zh' is not configured in WPML" (when it expects 'zh-hans')
	 *
	 * @param \WP_Post    $source_post       Source post object.
	 * @param LanguageTag $source_lang       Source language tag (for translation).
	 * @param LanguageTag $target_lang       Target language tag (for translation).
	 * @param string      $target_lang_wpml  Original WPML language code (for WPML operations).
	 * @return int|WP_Error Target post ID or error
	 */
	private function create_translation_post( \WP_Post $source_post, LanguageTag $source_lang, LanguageTag $target_lang, string $target_lang_wpml ) {
		// Translate post content.
		$translated = $this->translate_post_content( $source_post, $target_lang, $source_lang );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		// Create post data with translated content.
		$post_data = array(
			'post_title'   => $translated['title'],
			'post_content' => $translated['content'],
			'post_excerpt' => $translated['excerpt'],
			'post_status'  => 'draft', // Create as draft for review.
			'post_type'    => $source_post->post_type,
			'post_author'  => (int) $source_post->post_author,
		);

		// Insert post.
		$target_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $target_post_id ) ) {
			return $target_post_id;
		}

		// Set language for new post using the original WPML language code.
		// MUST use $target_lang_wpml (e.g., 'zh-hans') NOT $target_lang->primaryLanguageSubtag->value (e.g., 'zh').
		// WPML stores and expects the full language code that matches its configuration.
		$wpml_result = WPML_Post_Helper::set_language( $target_post_id, $target_lang_wpml );

		if ( is_wp_error( $wpml_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $wpml_result;
		}

		// Now relate the posts as translations using the original WPML language code.
		// MUST use $target_lang_wpml (e.g., 'pt-br') NOT $target_lang->primaryLanguageSubtag->value (e.g., 'pt').
		// WPML's translation relationship table uses the full language code as the key.
		$relation_result = WPML_Post_Helper::relate_posts_as_translations( $target_post_id, $source_post->ID, $target_lang_wpml );

		if ( is_wp_error( $relation_result ) ) {
			// Clean up created post.
			wp_delete_post( $target_post_id, true );
			return $relation_result;
		}

		// Copy custom fields marked as "Copy" in WPML settings.
		// This ensures ACF fields and other custom fields configured as "Copy" mode
		// are properly copied from the source post to the translation.
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'copy_custom_fields' ) ) {
			$sitepress->copy_custom_fields( $source_post->ID, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post->ID, $target_post_id );

		return $target_post_id;
	}

	/**
	 * Update an existing translation post with fresh translations
	 *
	 * @param \WP_Post    $source_post    Source post object.
	 * @param int         $target_post_id Existing translation post ID.
	 * @param LanguageTag $source_lang    Source language tag.
	 * @param LanguageTag $target_lang    Target language tag.
	 * @return int|WP_Error Target post ID or error
	 */
	private function update_translation_post( \WP_Post $source_post, int $target_post_id, LanguageTag $source_lang, LanguageTag $target_lang ) {
		// Translate post content.
		$translated = $this->translate_post_content( $source_post, $target_lang, $source_lang );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		// Update post data with translated content.
		$post_data = array(
			'ID'           => $target_post_id,
			'post_title'   => $translated['title'],
			'post_content' => $translated['content'],
			'post_excerpt' => $translated['excerpt'],
		);

		// Update post.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Copy custom fields marked as "Copy" in WPML settings.
		// This ensures ACF fields and other custom fields configured as "Copy" mode
		// are properly synced when updating existing translations.
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'copy_custom_fields' ) ) {
			$sitepress->copy_custom_fields( $source_post->ID, $target_post_id );
		}

		// Trigger action for other plugins that may need to hook into field copying.
		do_action( 'wpml_after_copy_custom_fields', $source_post->ID, $target_post_id );

		return $target_post_id;
	}

	/**
	 * Normalize WPML language code to BCP 47 format
	 *
	 * ## The Problem This Solves:
	 *
	 * WPML uses lowercase language codes (e.g., 'zh-hans', 'pt-br', 'en-us'),
	 * but the LanguageTag library requires proper BCP 47 casing for validation.
	 *
	 * Without normalization, LanguageTag::fromString('zh-hans') would fail because
	 * BCP 47 standard requires script codes to be Title case ('Hans' not 'hans').
	 *
	 * ## BCP 47 Casing Rules (RFC 5646):
	 * - Language subtag: lowercase (e.g., 'en', 'zh', 'pt')
	 * - Script subtag: Title case (e.g., 'Hans', 'Hant', 'Latn')
	 * - Region subtag: UPPERCASE (e.g., 'US', 'BR', 'GB', 'CN')
	 * - Variant subtag: lowercase or numeric
	 *
	 * ## Transformation Examples:
	 * - 'zh-hans' → 'zh-Hans' (Chinese Simplified)
	 * - 'zh-hant' → 'zh-Hant' (Chinese Traditional)
	 * - 'pt-br' → 'pt-BR' (Brazilian Portuguese)
	 * - 'en-us' → 'en-US' (American English)
	 * - 'sr-latn-rs' → 'sr-Latn-RS' (Serbian Latin Serbia)
	 *
	 * ## Usage Flow:
	 * 1. Receive WPML code from API or database: 'zh-hans'
	 * 2. Normalize to BCP 47: 'zh-Hans'
	 * 3. Create LanguageTag object: LanguageTag::fromString('zh-Hans') ✓ Valid
	 * 4. Keep original WPML code for WPML operations: 'zh-hans'
	 *
	 * @param string $wpml_code WPML language code (e.g., 'zh-hans', 'pt-br', 'en-us').
	 * @return string BCP 47 formatted language code (e.g., 'zh-Hans', 'pt-BR', 'en-US').
	 */
	private function normalize_wpml_language_code( string $wpml_code ): string {
		// Already normalized or simple code.
		if ( ! str_contains( $wpml_code, '-' ) ) {
			return strtolower( $wpml_code );
		}

		$parts = explode( '-', strtolower( $wpml_code ) );

		// First part is always the language code (lowercase).
		$normalized = array( $parts[0] );

		// Process remaining parts.
		$parts_count = count( $parts );
		for ( $i = 1; $i < $parts_count; $i++ ) {
			$part = $parts[ $i ];

			// Script codes are 4 letters (e.g., 'hans', 'hant', 'latn') - use Title case.
			if ( strlen( $part ) === 4 && ctype_alpha( $part ) ) {
				$normalized[] = ucfirst( strtolower( $part ) );
			} elseif ( ( strlen( $part ) === 2 && ctype_alpha( $part ) ) || ( strlen( $part ) === 3 && ctype_digit( $part ) ) ) {
				// Region codes are 2 letters or 3 digits - use UPPERCASE.
				$normalized[] = strtoupper( $part );
			} else {
				// Variant codes - keep lowercase.
				$normalized[] = strtolower( $part );
			}
		}

		return implode( '-', $normalized );
	}
}
