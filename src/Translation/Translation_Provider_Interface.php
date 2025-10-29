<?php
/**
 * Translation Provider Interface
 *
 * Defines contract for all translation providers (DeepL, Google Translate, etc.)
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use WP_Error;

/**
 * Interface Translation_Provider_Interface
 *
 * All translation providers must implement this interface to be registered
 * with the Translation_Manager and used throughout the plugin.
 */
interface Translation_Provider_Interface {

	/**
	 * Get provider unique identifier
	 *
	 * @return string Provider ID (e.g., 'deepl', 'google', 'openai')
	 */
	public function get_id(): string;

	/**
	 * Get provider display name
	 *
	 * @return string Provider name for UI display
	 */
	public function get_name(): string;

	/**
	 * Check if provider is configured and ready to use
	 *
	 * @return bool True if provider has valid credentials and is ready
	 */
	public function is_available(): bool;

	/**
	 * Translate text from source to target language
	 *
	 * @param string $text        Text to translate.
	 * @param string $target_lang Target language code (ISO 639-1).
	 * @param string $source_lang Source language code (optional, auto-detect if empty).
	 * @return string|WP_Error Translated text on success, WP_Error on failure.
	 */
	public function translate( string $text, string $target_lang, string $source_lang = '' );
}
