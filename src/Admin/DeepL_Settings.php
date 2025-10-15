<?php
/**
 * DeepL Settings functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

/**
 * DeepL Settings class
 *
 * Handles DeepL API configuration settings in WordPress admin.
 *
 * @package Multilingual_Bridge\Admin
 */
class DeepL_Settings {

	/**
	 * Option name for storing DeepL settings
	 */
	const OPTION_NAME = 'multilingual_bridge_deepl_settings';

	/**
	 * Registers WordPress hooks for the DeepL Settings functionality
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Register admin menu for settings
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Admin notice for success or error messages
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * Registers a new entry under the "Settings" menu in WordPress admin
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_options_page(
			__( 'DeepL Settings', 'multilingual-bridge' ),
			__( 'DeepL Settings', 'multilingual-bridge' ),
			'manage_options',
			'multilingual-bridge-deepl-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers settings using WordPress Settings API
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register setting
		register_setting(
			'multilingual_bridge_deepl',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'api_key'     => '',
					'use_premium' => false,
				),
			)
		);

		// Add settings section
		add_settings_section(
			'multilingual_bridge_deepl_main',
			__( 'DeepL API Configuration', 'multilingual-bridge' ),
			array( $this, 'render_section_description' ),
			'multilingual_bridge_deepl'
		);

		// Add API key field
		add_settings_field(
			'deepl_api_key',
			__( 'DeepL API Key', 'multilingual-bridge' ),
			array( $this, 'render_api_key_field' ),
			'multilingual_bridge_deepl',
			'multilingual_bridge_deepl_main'
		);

		// Add premium API toggle
		add_settings_field(
			'deepl_use_premium',
			__( 'API Type', 'multilingual-bridge' ),
			array( $this, 'render_premium_toggle_field' ),
			'multilingual_bridge_deepl',
			'multilingual_bridge_deepl_main'
		);
	}

	/**
	 * Sanitizes the settings input
	 *
	 * @param array<string, mixed> $input Raw input data.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Sanitize API key
		$sanitized['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';

		// Sanitize premium toggle
		$sanitized['use_premium'] = isset( $input['use_premium'] ) && '1' === $input['use_premium'];

		return $sanitized;
	}

	/**
	 * Renders the section description
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		?>
		<p><?php esc_html_e( 'Configure your DeepL API settings for translation functionality. You can use either the Free API or Premium API.', 'multilingual-bridge' ); ?></p>
		<p>
			<strong><?php esc_html_e( 'Note:', 'multilingual-bridge' ); ?></strong>
			<?php esc_html_e( 'Get your API key from', 'multilingual-bridge' ); ?>
			<a href="https://www.deepl.com/pro-api" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'DeepL API', 'multilingual-bridge' ); ?></a>
			<?php esc_html_e( 'and select the appropriate API type below.', 'multilingual-bridge' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the API key input field
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$settings = $this->get_settings();
		$api_key  = $settings['api_key'] ?? '';
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
			value="<?php echo esc_attr( $api_key ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Enter your DeepL API key', 'multilingual-bridge' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Your DeepL API key. This will be stored securely in the database.', 'multilingual-bridge' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the premium API toggle field
	 *
	 * @return void
	 */
	public function render_premium_toggle_field(): void {
		$settings    = $this->get_settings();
		$use_premium = $settings['use_premium'] ?? false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[use_premium]"
				value="1"
				<?php checked( $use_premium ); ?>
			/>
			<?php esc_html_e( 'Use DeepL Premium API', 'multilingual-bridge' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Check this box if you have a DeepL Premium API subscription. Leave unchecked for Free API.', 'multilingual-bridge' ); ?>
		</p>
		<?php
	}

	/**
	 * Displays admin notices based on the 'msg' parameter in the query string
	 *
	 * @return void
	 */
	public function display_admin_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Displaying admin notice only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$msg  = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $msg ) && 'multilingual-bridge-deepl-settings' === $page ) {
			switch ( $msg ) {
				case 'settings_updated':
					wp_admin_notice( __( 'DeepL settings updated successfully.', 'multilingual-bridge' ), array( 'type' => 'success' ) );
					break;
				case 'api_key_valid':
					wp_admin_notice( __( 'DeepL API key is valid and working correctly.', 'multilingual-bridge' ), array( 'type' => 'success' ) );
					break;
				case 'api_key_invalid':
					wp_admin_notice( __( 'DeepL API key is invalid or not working. Please check your key and API type.', 'multilingual-bridge' ), array( 'type' => 'error' ) );
					break;
				default:
					break;
			}
		}
	}

	/**
	 * Renders the WordPress admin page for the DeepL Settings
	 *
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DeepL Settings', 'multilingual-bridge' ); ?></h1>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'DeepL API Configuration', 'multilingual-bridge' ); ?></h2>
				</div>
				<div class="inside">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'multilingual_bridge_deepl' );
						do_settings_sections( 'multilingual_bridge_deepl' );
						submit_button( __( 'Save Settings', 'multilingual-bridge' ) );
						?>
					</form>

					<hr style="margin: 20px 0;">

					<h3><?php esc_html_e( 'Test API Connection', 'multilingual-bridge' ); ?></h3>
					<p><?php esc_html_e( 'Click the button below to test your DeepL API configuration.', 'multilingual-bridge' ); ?></p>

					<form method="post" action="admin-post.php">
						<input type="hidden" name="action" value="test_deepl_api" />
						<?php wp_nonce_field( 'test_deepl_api', 'test_deepl_api_nonce' ); ?>
						<?php submit_button( __( 'Test API Connection', 'multilingual-bridge' ), 'secondary', 'test_api' ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets the DeepL settings from database
	 *
	 * @return array<string, mixed> Settings array.
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		// Ensure we return an array with proper defaults
		if ( ! is_array( $settings ) ) {
			return array(
				'api_key'     => '',
				'use_premium' => false,
			);
		}

		return wp_parse_args(
			$settings,
			array(
				'api_key'     => '',
				'use_premium' => false,
			)
		);
	}

	/**
	 * Gets the DeepL API key from settings
	 *
	 * @return string|null API key or null if not set.
	 */
	public static function get_api_key(): ?string {
		$settings = get_option( self::OPTION_NAME, array() );

		// Ensure we have an array
		if ( ! is_array( $settings ) ) {
			return null;
		}

		$api_key = $settings['api_key'] ?? '';

		return ! empty( $api_key ) ? $api_key : null;
	}

	/**
	 * Checks if premium API should be used
	 *
	 * @return bool True if premium API should be used.
	 */
	public static function use_premium_api(): bool {
		$settings = get_option( self::OPTION_NAME, array() );

		// Ensure we have an array
		if ( ! is_array( $settings ) ) {
			return false;
		}

		return $settings['use_premium'] ?? false;
	}
}