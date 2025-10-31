<?php
/**
 * Language Code Helper for type-safe language code handling
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use PrinsFrank\Standards\Language\LanguageAlpha2;
use PrinsFrank\Standards\LanguageTag\LanguageTag;
use PrinsFrank\Standards\InvalidArgumentException as StandardsInvalidArgumentException;

/**
 * Language Code Helper
 *
 * Provides type-safe language code validation and conversion using prinsfrank/standards library
 */
class Language_Code_Helper {

	/**
	 * Validate a language code string
	 *
	 * Accepts both ISO 639-1 codes (e.g., 'en', 'de') and language tags with subtags (e.g., 'zh-hans', 'pt-br')
	 *
	 * @param string $language_code Language code to validate.
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid_language_code( string $language_code ): bool {
		// Try parsing as language tag first (supports both simple codes and complex tags).
		try {
			LanguageTag::fromString( strtolower( $language_code ) );
			return true;
		} catch ( StandardsInvalidArgumentException $e ) {
			return false;
		}
	}

	/**
	 * Get the primary language code from a language tag
	 *
	 * For 'zh-hans' returns 'zh', for 'en' returns 'en'
	 *
	 * @param string $language_code Language code or tag.
	 * @return string|null Primary language code or null if invalid
	 */
	public static function get_primary_language( string $language_code ): ?string {
		try {
			$tag = LanguageTag::fromString( strtolower( $language_code ) );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Property name from external library.
			$primary = $tag->primaryLanguageSubtag;

			if ( $primary instanceof LanguageAlpha2 ) {
				return $primary->value;
			}

			return null;
		} catch ( StandardsInvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Normalize a language code to lowercase
	 *
	 * Converts 'EN', 'En', 'en' all to 'en'
	 * Converts 'ZH-HANS', 'zh-Hans' to 'zh-hans'
	 *
	 * @param string $language_code Language code to normalize.
	 * @return string Normalized language code
	 */
	public static function normalize( string $language_code ): string {
		return strtolower( trim( $language_code ) );
	}

	/**
	 * Convert language code to format expected by DeepL API
	 *
	 * DeepL expects uppercase language codes (e.g., 'EN', 'DE').
	 * For Chinese, DeepL supports 'ZH' for simplified Chinese.
	 * Traditional Chinese (ZH-HANT) is not currently supported by DeepL.
	 *
	 * @param string $language_code Language code to convert.
	 * @return string|null Uppercase language code for DeepL or null if invalid
	 */
	public static function to_deepl_format( string $language_code ): ?string {
		if ( ! self::is_valid_language_code( $language_code ) ) {
			return null;
		}

		// Normalize first.
		$normalized = self::normalize( $language_code );

		// DeepL uses uppercase.
		return strtoupper( $normalized );
	}

	/**
	 * Get all supported languages as ISO 639-1 codes
	 *
	 * @return array<string> Array of ISO 639-1 language codes
	 */
	public static function get_all_iso_639_1_codes(): array {
		$cases = LanguageAlpha2::cases();
		return array_map(
			fn( LanguageAlpha2 $enum_case ): string => $enum_case->value,
			$cases
		);
	}

	/**
	 * Check if a language code is a valid ISO 639-1 code
	 *
	 * @param string $language_code Language code to check.
	 * @return bool True if valid ISO 639-1 code, false otherwise
	 */
	public static function is_iso_639_1( string $language_code ): bool {
		return LanguageAlpha2::tryFrom( strtolower( $language_code ) ) !== null;
	}
}
