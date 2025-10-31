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
	 * Translate text from source to target language
	 *
	 * @param string           $text        Text to translate.
	 * @param LanguageTag      $target_lang Target language tag (e.g., 'en', 'zh-hans').
	 * @param LanguageTag|null $source_lang Source language tag (optional, auto-detect if null).
	 * @return string|WP_Error Translated text on success, WP_Error on failure.
	 */
	public function translate( string $text, LanguageTag $target_lang, ?LanguageTag $source_lang = null );

	/**
	 * Get list of supported target languages
	 *
	 * Returns array of language codes that this provider can translate TO.
	 * Language codes should be lowercase (e.g., 'en', 'de', 'zh', 'zh-hans').
	 *
	 * @return array<string> Array of supported target language codes
	 */
	public function get_supported_target_languages(): array;

	/**
	 * Get list of supported source languages
	 *
	 * Returns array of language codes that this provider can translate FROM.
	 * Language codes should be lowercase (e.g., 'en', 'de', 'zh', 'zh-hans').
	 *
	 * @return array<string> Array of supported source language codes
	 */
	public function get_supported_source_languages(): array;
}
