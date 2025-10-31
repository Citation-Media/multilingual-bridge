<?php
/**
 * Meta Translation Handler
 *
 * Handles translation of post meta, automatically detecting and routing
 * to appropriate handlers (ACF, regular meta, etc.).
 *
 * Extensible via hooks to support custom meta types.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Integrations\ACF\ACF_Field_Helper;
use WP_Error;

/**
 * Class Meta_Translation_Handler
 *
 * Routes meta translation to appropriate handlers
 */
class Meta_Translation_Handler {

	/**
	 * Translation Manager instance
	 *
	 * @var Translation_Manager
	 */
	private Translation_Manager $translation_manager;

	/**
	 * Registered meta handlers
	 *
	 * @var array<string, array{callback: callable, priority: int}>
	 */
	private array $handlers = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->translation_manager = Translation_Manager::instance();
		$this->register_default_handlers();
	}

	/**
	 * Register default meta handlers
	 */
	private function register_default_handlers(): void {
		// ACF handler (highest priority - runs first).
		$this->handlers['acf'] = array(
			'callback' => array( $this, 'handle_acf_meta' ),
			'priority' => 10,
		);

		// Regular post meta handler (lowest priority - runs last).
		$this->handlers['post_meta'] = array(
			'callback' => array( $this, 'handle_post_meta' ),
			'priority' => 100,
		);

		// Sort handlers by priority (lower number = higher priority).
		uasort(
			$this->handlers,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);
	}

	/**
	 * Translate all post meta from source to target post
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $target_language Target language code.
	 * @param string $source_language Source language code.
	 * @return array<string, mixed> Translation results
	 */
	public function translate_post_meta( int $source_post_id, int $target_post_id, string $target_language, string $source_language ): array {
		$results = array(
			'success'     => true,
			'translated'  => 0,
			'skipped'     => 0,
			'errors'      => array(),
			'meta_fields' => array(),
		);

		// Get all post meta.
		$all_meta = get_post_meta( $source_post_id );

		if ( empty( $all_meta ) ) {
			return $results;
		}

		/**
		 * Filter post meta before translation
		 *
		 * Allows filtering which meta fields should be translated
		 *
		 * @param array<string, mixed> $all_meta        All post meta
		 * @param int                  $source_post_id  Source post ID
		 * @param int                  $target_post_id  Target post ID
		 * @param string               $target_language Target language code
		 * @param string               $source_language Source language code
		 */
		$all_meta = apply_filters(
			'multilingual_bridge_pre_translate_meta',
			$all_meta,
			$source_post_id,
			$target_post_id,
			$target_language,
			$source_language
		);

		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Skip internal WordPress meta.
			if ( $this->should_skip_meta( $meta_key ) ) {
				++$results['skipped'];
				continue;
			}

			// Get first value (WordPress meta is stored as arrays).
			$meta_value = $meta_values[0] ?? null;

			if ( null === $meta_value ) {
				++$results['skipped'];
				continue;
			}

			// Skip ACF field key references (e.g., _autoscout-id = "field_5ff8033f42629").
			// These must be preserved exactly and never translated.
			if ( $this->is_acf_field_key_reference( $meta_key, $meta_value ) ) {
				// Copy the field key reference as-is to maintain ACF structure.
				update_post_meta( $target_post_id, $meta_key, $meta_value );
				++$results['skipped'];
				continue;
			}

			// Check WPML translation preference for this field.
			// Only translate fields explicitly marked as "translate" in WPML settings.
			// WPML automatically handles "copy" and "don't translate" fields.
			$wpml_preference = ACF_Field_Helper::get_wpml_translation_preference( $meta_key, $source_post_id );
			if ( 'translate' !== $wpml_preference ) {
				// Skip - let WPML handle copy/ignore preferences automatically.
				++$results['skipped'];
				continue;
			}

			// Try each registered handler until one successfully processes the field.
			$handled = false;

			foreach ( $this->handlers as $handler_id => $handler_data ) {
				// Call handler - it will determine if it can process this field.
				$handler_result = call_user_func(
					$handler_data['callback'],
					$meta_key,
					$meta_value,
					$source_post_id,
					$target_post_id,
					$target_language,
					$source_language
				);

				if ( is_wp_error( $handler_result ) ) {
					// Some errors are just "handler can't process this field" messages.
					// Only treat as critical errors if not a "skip" type error.
					$skip_error_codes = array(
						'acf_not_available',           // ACF not active - let other handlers try.
						'not_acf_field',               // Not an ACF field - let other handlers try.
						'field_type_not_translatable', // Field type not registered for translation - skip it.
					);

					if ( ! in_array( $handler_result->get_error_code(), $skip_error_codes, true ) ) {
						// Critical error - add to error list and mark overall failure.
						$results['errors'][ $meta_key ] = $handler_result->get_error_message();
						$results['success']             = false;
					}
					// Continue to next handler (don't break).
				} else {
					++$results['translated'];
					$results['meta_fields'][ $meta_key ] = array(
						'handler' => $handler_id,
						'result'  => $handler_result,
					);
					$handled                             = true;
					break; // Stop after first successful handler.
				}
			}

			if ( ! $handled ) {
				++$results['skipped'];
			}
		}

		/**
		 * Fires after meta translation is complete
		 *
		 * @param array<string, mixed> $results         Translation results
		 * @param int                  $source_post_id  Source post ID
		 * @param int                  $target_post_id  Target post ID
		 * @param string               $target_language Target language code
		 * @param string               $source_language Source language code
		 */
		do_action(
			'multilingual_bridge_after_translate_meta',
			$results,
			$source_post_id,
			$target_post_id,
			$target_language,
			$source_language
		);

		return $results;
	}

	/**
	 * Check if a value is truly empty (for ACF field syncing)
	 *
	 * We want to sync: null, '', [] (empty array)
	 * We don't want to sync: 0, '0', false (valid values)
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if value is empty and should be synced as deleted
	 */
	private function is_empty_value( $value ): bool {
		// Null is empty.
		if ( null === $value ) {
			return true;
		}

		// Empty string is empty.
		if ( '' === $value ) {
			return true;
		}

		// Empty array is empty.
		if ( array() === $value ) {
			return true;
		}

		// Everything else is not empty (including 0, '0', false).
		return false;
	}

	/**
	 * Check if meta key should be skipped
	 *
	 * @param string $meta_key Meta key to check.
	 * @return bool True if should skip
	 */
	private function should_skip_meta( string $meta_key ): bool {
		// Skip internal WordPress meta.
		$skip_prefixes = array( '_wp_', '_edit_', '_oembed_' );

		foreach ( $skip_prefixes as $prefix ) {
			if ( str_starts_with( $meta_key, $prefix ) ) {
				return true;
			}
		}

		// Skip WPML meta.
		if ( str_starts_with( $meta_key, '_wpml_' ) || str_starts_with( $meta_key, 'wpml_' ) ) {
			return true;
		}

		/**
		 * Filter whether to skip meta key
		 *
		 * @param bool   $should_skip Whether to skip
		 * @param string $meta_key    Meta key
		 */
		return apply_filters( 'multilingual_bridge_skip_meta_key', false, $meta_key );
	}

	/**
	 * Check if a meta value is an ACF field key reference
	 *
	 * ACF stores field key references as meta with underscore prefix.
	 * Example: _autoscout-id = "field_5ff8033f42629"
	 * These should never be translated.
	 *
	 * @param string $meta_key   Meta key to check.
	 * @param mixed  $meta_value Meta value to check.
	 * @return bool True if this is an ACF field key reference
	 */
	private function is_acf_field_key_reference( string $meta_key, $meta_value ): bool {
		// Must start with underscore (ACF pattern).
		if ( ! str_starts_with( $meta_key, '_' ) ) {
			return false;
		}

		// Value must be a string.
		if ( ! is_string( $meta_value ) ) {
			return false;
		}

		// Check if value looks like an ACF field key (starts with "field_").
		if ( str_starts_with( $meta_value, 'field_' ) ) {
			return true;
		}

		// Use ACF's function if available for more accurate detection.
		if ( function_exists( 'acf_is_field_key' ) && acf_is_field_key( $meta_value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle ACF field translation
	 *
	 * @param string $meta_key       Meta key.
	 * @param mixed  $meta_value     Meta value.
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $target_language Target language code.
	 * @param string $source_language Source language code.
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function handle_acf_meta( string $meta_key, $meta_value, int $source_post_id, int $target_post_id, string $target_language, string $source_language ) {
		// Check if ACF is active.
		if ( ! function_exists( 'get_field_object' ) ) {
			return new WP_Error( 'acf_not_available', 'ACF is not available' );
		}

		// Get ACF field object.
		$field = get_field_object( $meta_key, $source_post_id );

		if ( ! $field ) {
			return new WP_Error( 'not_acf_field', 'Not an ACF field' );
		}

		// Handle empty values by syncing them to translations (delete field).
		if ( $this->is_empty_value( $meta_value ) ) {
			// Delete the field from translation to sync empty state.
			if ( function_exists( 'delete_field' ) ) {
				delete_field( $field['name'], $target_post_id );
			}
			// Return success - empty field was synced.
			return true;
		}

		// Check if field type is translatable.
		// Only process translatable field types (text, textarea, wysiwyg).
		// Non-translatable field types (image, file, relationship, etc.) are skipped during automatic translation.
		if ( ! ACF_Field_Helper::is_translatable_field_type( $field['type'] ) ) {
			// Field type is not registered for translation - skip it.
			// This ensures automatic translation only processes the same field types that have manual translation buttons.
			return new WP_Error(
				'field_type_not_translatable',
				sprintf( 'Field type "%s" is not registered for translation', $field['type'] )
			);
		}

		// Only translate string values.
		if ( ! is_string( $meta_value ) ) {
			// Non-string value for translatable field type - copy as-is.
			// This handles cases like arrays or serialized data.
			return update_field( $field['key'], $meta_value, $target_post_id );
		}

		// Translate the value.
		$translated_value = $this->translation_manager->translate(
			$meta_value,
			$target_language,
			$source_language
		);

		if ( is_wp_error( $translated_value ) ) {
			return $translated_value;
		}

		// Update target post meta.
		return update_field( $field['key'], $translated_value, $target_post_id );
	}

	/**
	 * Handle regular post meta translation
	 *
	 * @param string $meta_key       Meta key.
	 * @param mixed  $meta_value     Meta value.
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $target_language Target language code.
	 * @param string $source_language Source language code.
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function handle_post_meta( string $meta_key, $meta_value, int $source_post_id, int $target_post_id, string $target_language, string $source_language ) {
		// Handle non-string values by copying them directly.
		if ( ! is_string( $meta_value ) ) {
			// Copy non-string values (arrays, objects, numbers, etc.) as-is.
			return update_post_meta( $target_post_id, $meta_key, $meta_value );
		}

		// Handle empty strings by copying them.
		if ( empty( $meta_value ) ) {
			return update_post_meta( $target_post_id, $meta_key, $meta_value );
		}

		// Skip if value is too short (likely not text content) - copy as-is.
		if ( strlen( $meta_value ) < 3 ) {
			return update_post_meta( $target_post_id, $meta_key, $meta_value );
		}

		/**
		 * Filter whether to translate this meta field
		 *
		 * @param bool   $should_translate Whether to translate
		 * @param string $meta_key         Meta key
		 * @param string $meta_value       Meta value
		 * @param int    $source_post_id   Source post ID
		 */
		$should_translate = apply_filters(
			'multilingual_bridge_should_translate_post_meta',
			true,
			$meta_key,
			$meta_value,
			$source_post_id
		);

		if ( ! $should_translate ) {
			// Filter says don't translate - copy as-is instead.
			return update_post_meta( $target_post_id, $meta_key, $meta_value );
		}

		// Translate the value.
		$translated_value = $this->translation_manager->translate(
			$meta_value,
			$target_language,
			$source_language
		);

		if ( is_wp_error( $translated_value ) ) {
			return $translated_value;
		}

		// Update target post meta.
		return update_post_meta( $target_post_id, $meta_key, $translated_value );
	}
}
