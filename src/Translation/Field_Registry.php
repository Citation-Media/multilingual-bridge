<?php
/**
 * Field Registry - Extensible field type support
 *
 * Manages registration of field integrations (ACF, Meta Box, etc.)
 * and field type configurations for translation UI.
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Translation;

/**
 * Class Field_Registry
 *
 * Singleton managing field type registrations and integrations
 */
class Field_Registry {

	/**
	 * Singleton instance
	 *
	 * @var Field_Registry|null
	 */
	private static ?Field_Registry $instance = null;

	/**
	 * Registered field integrations (ACF, Meta Box, etc.)
	 *
	 * @var array<string, callable>
	 */
	private array $integrations = array();

	/**
	 * Supported field types for translation
	 * Simple array of field type strings
	 *
	 * @var string[]
	 */
	private array $field_types = array();

	/**
	 * Get singleton instance
	 *
	 * @return Field_Registry
	 */
	public static function instance(): Field_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		// Register default field types.
		$this->register_default_field_types();
	}

	/**
	 * Register default translatable field types
	 */
	private function register_default_field_types(): void {
		$this->field_types = array( 'text', 'textarea', 'wysiwyg' );
	}

	/**
	 * Register a field type as translatable
	 *
	 * @param string $type Field type identifier.
	 * @return bool True on success, false if already registered
	 */
	public function register_field_type( string $type ): bool {
		if ( in_array( $type, $this->field_types, true ) ) {
			return false;
		}

		$this->field_types[] = $type;

		/**
		 * Fires after a field type is registered
		 *
		 * @param string $type Field type
		 */
		do_action( 'multilingual_bridge_field_type_registered', $type );

		return true;
	}

	/**
	 * Unregister a field type
	 *
	 * @param string $type Field type identifier.
	 * @return bool True on success, false if not found
	 */
	public function unregister_field_type( string $type ): bool {
		$key = array_search( $type, $this->field_types, true );

		if ( false === $key ) {
			return false;
		}

		unset( $this->field_types[ $key ] );
		// Re-index array.
		$this->field_types = array_values( $this->field_types );
		return true;
	}

	/**
	 * Get all registered field types
	 *
	 * @return string[] Field types
	 */
	public function get_field_types(): array {
		/**
		 * Filter registered field types
		 *
		 * @param string[] $field_types Registered field types
		 */
		return apply_filters( 'multilingual_bridge_field_types', $this->field_types );
	}

	/**
	 * Check if field type is registered
	 *
	 * @param string $type Field type identifier.
	 * @return bool True if registered
	 */
	public function is_field_type_registered( string $type ): bool {
		return in_array( $type, $this->field_types, true );
	}

	/**
	 * Register a field integration (ACF, Meta Box, etc.)
	 *
	 * @param string   $integration_id Unique integration identifier.
	 * @param callable $init_callback  Callback to initialize the integration.
	 * @return bool True on success, false if already registered
	 */
	public function register_integration( string $integration_id, callable $init_callback ): bool {
		if ( isset( $this->integrations[ $integration_id ] ) ) {
			return false;
		}

		$this->integrations[ $integration_id ] = $init_callback;

		/**
		 * Fires after a field integration is registered
		 *
		 * @param string   $integration_id Integration ID
		 * @param callable $init_callback  Initialization callback
		 */
		do_action( 'multilingual_bridge_integration_registered', $integration_id, $init_callback );

		return true;
	}

	/**
	 * Initialize all registered integrations
	 */
	public function init_integrations(): void {
		foreach ( $this->integrations as $integration_id => $init_callback ) {
			call_user_func( $init_callback );
		}

		/**
		 * Fires after all integrations are initialized
		 */
		do_action( 'multilingual_bridge_integrations_initialized' );
	}

	/**
	 * Get registered integrations
	 *
	 * @return array<string, callable> Integrations
	 */
	public function get_integrations(): array {
		return $this->integrations;
	}
}
