<?php
/**
 * Translation Manager - Central registry for translation providers
 *
 * Manages registration, selection, and execution of translation providers.
 * Provides hooks for third-party plugins to register custom providers.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

use PrinsFrank\Standards\LanguageTag\LanguageTag;
use WP_Error;

/**
 * Class Translation_Manager
 *
 * Singleton class managing all translation providers
 */
class Translation_Manager {

	/**
	 * Singleton instance
	 *
	 * @var Translation_Manager|null
	 */
	private static ?Translation_Manager $instance = null;

	/**
	 * Registered translation providers
	 *
	 * @var array<string, Translation_Provider_Interface>
	 */
	private array $providers = array();

	/**
	 * Default provider ID
	 *
	 * @var string|null
	 */
	private ?string $default_provider = null;

	/**
	 * Get singleton instance
	 *
	 * @return Translation_Manager
	 */
	public static function instance(): Translation_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton
	 */
	private function __construct() {
		// Hook for third-party providers to register themselves.
		add_action( 'multilingual_bridge_register_translation_providers', array( $this, 'register_default_providers' ), 5 );
	}

	/**
	 * Register default providers bundled with plugin
	 */
	public function register_default_providers(): void {
		// Allow plugins to register providers before defaults.
		do_action( 'multilingual_bridge_before_register_default_providers', $this );
	}

	/**
	 * Register a translation provider
	 *
	 * @param Translation_Provider_Interface $provider Provider instance.
	 * @return bool True on success, false if provider already registered
	 */
	public function register_provider( Translation_Provider_Interface $provider ): bool {
		$provider_id = $provider->get_id();

		if ( isset( $this->providers[ $provider_id ] ) ) {
			return false;
		}

		$this->providers[ $provider_id ] = $provider;

		// Set as default if it's the first available provider.
		if ( null === $this->default_provider && $provider->is_available() ) {
			$this->default_provider = $provider_id;
		}

		/**
		 * Fires after a translation provider is registered
		 *
		 * @param Translation_Provider_Interface $provider Registered provider instance
		 * @param Translation_Manager           $manager  Manager instance
		 */
		do_action( 'multilingual_bridge_provider_registered', $provider, $this );

		return true;
	}

	/**
	 * Get a specific provider by ID
	 *
	 * @param string $provider_id Provider ID.
	 * @return Translation_Provider_Interface|null Provider instance or null if not found
	 */
	public function get_provider( string $provider_id ): ?Translation_Provider_Interface {
		return $this->providers[ $provider_id ] ?? null;
	}

	/**
	 * Get all registered providers
	 *
	 * @param bool $available_only Only return available/configured providers.
	 * @return array<string, Translation_Provider_Interface> Array of providers
	 */
	public function get_providers( bool $available_only = false ): array {
		if ( ! $available_only ) {
			return $this->providers;
		}

		return array_filter(
			$this->providers,
			function ( Translation_Provider_Interface $provider ) {
				return $provider->is_available();
			}
		);
	}

	/**
	 * Get default provider ID
	 *
	 * @return string|null Default provider ID or null if none set
	 */
	private function get_default_provider_id(): ?string {
		return $this->default_provider;
	}

	/**
	 * Get default provider instance
	 *
	 * @return Translation_Provider_Interface|null Default provider or null
	 */
	public function get_default_provider(): ?Translation_Provider_Interface {
		$provider_id = $this->get_default_provider_id();

		if ( null === $provider_id ) {
			return null;
		}

		return $this->get_provider( $provider_id );
	}

	/**
	 * Translate text using the default provider
	 *
	 * @param LanguageTag      $target_lang Target language tag.
	 * @param string           $text        Text to translate.
	 * @param LanguageTag|null $source_lang Source language tag (optional).
	 * @return string|WP_Error Translated text or error
	 */
	public function translate( LanguageTag $target_lang, string $text, ?LanguageTag $source_lang = null ) {
		$provider_id = $this->get_default_provider_id();

		if ( null === $provider_id ) {
			return new WP_Error(
				'no_provider',
				__( 'No translation provider available. Please configure a translation service.', 'multilingual-bridge' )
			);
		}

		$provider = $this->get_provider( $provider_id );

		if ( null === $provider ) {
			return new WP_Error(
				'invalid_provider',
				sprintf(
					/* translators: %s: provider ID */
					__( 'Translation provider "%s" not found.', 'multilingual-bridge' ),
					$provider_id
				)
			);
		}

		if ( ! $provider->is_available() ) {
			return new WP_Error(
				'provider_unavailable',
				sprintf(
					/* translators: %s: provider name */
					__( 'Translation provider "%s" is not properly configured.', 'multilingual-bridge' ),
					$provider->get_name()
				)
			);
		}

		/**
		 * Filter text before translation
		 *
		 * @param string                         $text        Text to translate
		 * @param LanguageTag                    $target_lang Target language
		 * @param LanguageTag|null               $source_lang Source language
		 * @param Translation_Provider_Interface $provider    Provider instance
		 */
		$text = apply_filters( 'multilingual_bridge_before_translate', $text, $target_lang, $source_lang, $provider );

		$translation = $provider->translate( $target_lang, $text, $source_lang );

		if ( is_wp_error( $translation ) ) {
			return $translation;
		}

		/**
		 * Filter translation result
		 *
		 * @param string                         $translation Translated text
		 * @param string                         $text        Original text
		 * @param LanguageTag                    $target_lang Target language
		 * @param LanguageTag|null               $source_lang Source language
		 * @param Translation_Provider_Interface $provider    Provider instance
		 */
		return apply_filters( 'multilingual_bridge_after_translate', $translation, $text, $target_lang, $source_lang, $provider );
	}
}
