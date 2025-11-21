<?php
/**
 * Translation Provider Interface
 *
 * Defines contract for all translation providers (DeepL, Google Translate, etc.)
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use PrinsFrank\Standards\LanguageTag\LanguageTag;
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
	 * Get array of supported language tags
	 *
	 * @return LanguageTag[] Array of supported language tag instances
	 */
	public function get_supported_languages(): array;

	/**
	 * Translate text from source to target language
	 *
	 * @param LanguageTag      $target_lang Target language tag.
	 * @param string           $text        Text to translate.
	 * @param LanguageTag|null $source_lang Source language tag (optional, auto-detect if null).
	 * @return string|WP_Error Translated text on success, WP_Error on failure.
	 */
	public function translate( LanguageTag $target_lang, string $text, ?LanguageTag $source_lang = null );
}
