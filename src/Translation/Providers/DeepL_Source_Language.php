<?php
/**
 * DeepL Supported Source Languages Enum
 *
 * Type-safe enum for source languages supported by DeepL translation API
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation\Providers;

use PrinsFrank\Standards\Language\LanguageAlpha2;

/**
 * DeepL Supported Source Languages
 *
 * Based on DeepL documentation: https://developers.deepl.com/docs/resources/supported-languages
 */
enum DeepL_Source_Language: string {
	case Arabic           = 'ar';
	case Bulgarian        = 'bg';
	case Czech            = 'cs';
	case Danish           = 'da';
	case German           = 'de';
	case Greek            = 'el';
	case English          = 'en';
	case Spanish          = 'es';
	case Estonian         = 'et';
	case Finnish          = 'fi';
	case French           = 'fr';
	case Hungarian        = 'hu';
	case Indonesian       = 'id';
	case Italian          = 'it';
	case Japanese         = 'ja';
	case Korean           = 'ko';
	case Lithuanian       = 'lt';
	case Latvian          = 'lv';
	case Norwegian_Bokmal = 'nb';
	case Dutch            = 'nl';
	case Polish           = 'pl';
	case Portuguese       = 'pt';
	case Romanian         = 'ro';
	case Russian          = 'ru';
	case Slovak           = 'sk';
	case Slovenian        = 'sl';
	case Swedish          = 'sv';
	case Turkish          = 'tr';
	case Ukrainian        = 'uk';
	case Chinese          = 'zh';

	/**
	 * Get all source language codes as array
	 *
	 * @return array<string>
	 */
	public static function values(): array {
		return array_map( fn( self $enum_case ): string => $enum_case->value, self::cases() );
	}

	/**
	 * Try to create from string value
	 *
	 * @param string $value Language code string.
	 * @return self|null Enum case or null if not found
	 */
	public static function try_from_value( string $value ): ?self {
		return self::tryFrom( strtolower( $value ) );
	}

	/**
	 * Check if a language code is supported as source
	 *
	 * @param string $language_code Language code to check.
	 * @return bool True if supported, false otherwise
	 */
	public static function is_supported( string $language_code ): bool {
		return self::try_from_value( $language_code ) !== null;
	}

	/**
	 * Get corresponding ISO 639-1 language enum if available
	 *
	 * @return LanguageAlpha2|null
	 */
	public function to_iso_639(): ?LanguageAlpha2 {
		// Extract primary language code (before hyphen).
		$primary = explode( '-', $this->value )[0];
		return LanguageAlpha2::tryFrom( $primary );
	}
}
