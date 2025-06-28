<?php
/**
 * Language Debug functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Admin;

use Multilingual_Bridge\Helpers\WPML_Post_Helper;
use Exception;
use WP_Post;

/**
 * Language Debug class
 *
 * Handles debugging and managing posts in unconfigured languages within WPML.
 * Provides admin interface for finding and fixing posts with language issues.
 *
 * @package Multilingual_Bridge\Admin
 */
class Language_Debug {

	/**
	 * Registers WordPress hooks for the Language Debug functionality
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Register admin menu for tools
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Admin notice for success or error messages
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );

		// Handle form submission with admin_post
		add_action( 'admin_post_language_debug', array( $this, 'handle_language_debug' ) );
	}

	/**
	 * Registers a new entry under the "Tools" menu in WordPress admin
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_management_page(
			__( 'Language Debug', 'multilingual-bridge' ),
			__( 'Language Debug', 'multilingual-bridge' ),
			'manage_options',
			'multilingual-bridge-language-debug',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Displays admin notices based on the 'msg' parameter in the query string
	 *
	 * @return void
	 */
	public function display_admin_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['msg'] ) && isset( $_GET['page'] ) && 'multilingual-bridge-language-debug' === $_GET['page'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			switch ( $_GET['msg'] ) {
				case 'wpml_not_active':
					wp_admin_notice( __( 'WPML is not active. This tool requires WPML to function.', 'multilingual-bridge' ), array( 'type' => 'error' ) );
					break;
				case 'debug_completed':
					wp_admin_notice( __( 'Language debug operation completed successfully.', 'multilingual-bridge' ), array( 'type' => 'success' ) );
					break;
				case 'no_orphaned_posts':
					wp_admin_notice( __( 'No posts found in unconfigured languages.', 'multilingual-bridge' ), array( 'type' => 'info' ) );
					break;
				case 'preflight_complete':
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$debug_post_type = isset( $_GET['debug_post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['debug_post_type'] ) ) : 'all';
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$breakdown_raw = isset( $_GET['breakdown'] ) ? sanitize_text_field( wp_unslash( $_GET['breakdown'] ) ) : '';

					$breakdown = array();
					if ( ! empty( $breakdown_raw ) ) {
						$decoded_breakdown = json_decode( $breakdown_raw, true );
						if ( is_array( $decoded_breakdown ) ) {
							$breakdown = $decoded_breakdown;
						}
					}

					$message = sprintf(
						/* translators: %1$d: Number of posts found, %2$s: Post type name */
						__( 'Preflight check complete: Found %1$d posts in unconfigured languages for post type "%2$s".', 'multilingual-bridge' ),
						$count,
						$debug_post_type
					);

					if ( ! empty( $breakdown ) ) {
						$message .= '<br><strong>' . __( 'Breakdown by post type:', 'multilingual-bridge' ) . '</strong><ul>';
						foreach ( $breakdown as $post_type => $type_count ) {
							$post_type_object = get_post_type_object( $post_type );
							$post_type_label  = $post_type_object ? $post_type_object->labels->name : $post_type;
							$message         .= '<li>' . esc_html( $post_type_label ) . ': ' . (int) $type_count . '</li>';
						}
						$message .= '</ul>';
					}

					wp_admin_notice( $message, array( 'type' => 'info' ) );
					break;
				default:
					break;
			}
		}
	}

	/**
	 * Renders the WordPress admin page for the Language Debug tool
	 *
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap" id="poststuff">
			<h1><?php esc_html_e( 'Language Debug', 'multilingual-bridge' ); ?></h1>

			<?php
			// If WPML is not active, show a message and don't render the form.
			if ( ! defined( 'WPML_PLUGIN_PATH' ) || ! function_exists( 'icl_get_current_language' ) ) {
				wp_admin_notice(
					__( 'WPML is not active or not fully initialized. This tool requires WPML to function.', 'multilingual-bridge' ),
					array(
						'type'        => 'error',
						'dismissible' => false,
					)
				);
				echo '</div>'; // Close wrap
				return;
			}
			?>

			<div class="postbox">
				<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Language Debug', 'multilingual-bridge' ); ?></h2></div>
				<div class="inside">
					<p><?php esc_html_e( 'Debug functionality to handle posts in languages that are not configured on this site.', 'multilingual-bridge' ); ?></p>

					<form method="post" action="admin-post.php">
						<input type="hidden" name="action" value="language_debug" />
						<?php wp_nonce_field( 'language_debug', 'language_debug_nonce' ); ?>

						<p><label for="debug_action"><?php esc_html_e( 'Action to perform:', 'multilingual-bridge' ); ?></label></p>
						<p>
							<select name="debug_action" id="debug_action" required>
								<option value=""><?php esc_html_e( 'Choose an action', 'multilingual-bridge' ); ?></option>
								<option value="preflight"><?php esc_html_e( 'Preflight check (show impact)', 'multilingual-bridge' ); ?></option>
								<option value="delete"><?php esc_html_e( 'Delete posts in unconfigured languages', 'multilingual-bridge' ); ?></option>
								<option value="fix_language"><?php esc_html_e( 'Fix language to default language', 'multilingual-bridge' ); ?></option>
							</select>
						</p>

						<p><label for="debug_post_type"><?php esc_html_e( 'Post type(s) to process:', 'multilingual-bridge' ); ?></label></p>
						<p>
							<select name="debug_post_type[]" id="debug_post_type" multiple size="5" style="width: 100%; max-width: 300px;">
								<option value="all"><?php esc_html_e( 'All post types', 'multilingual-bridge' ); ?></option>
								<?php
								$post_types = get_post_types( array( 'public' => true ), 'objects' );
								foreach ( $post_types as $post_type => $post_type_object ) {
									echo '<option value="' . esc_attr( $post_type ) . '">' . esc_html( $post_type_object->labels->name ) . '</option>';
								}
								?>
							</select>
							<br><small><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple post types. Select "All post types" for everything.', 'multilingual-bridge' ); ?></small>
						</p>

						<p><label for="target_language"><?php esc_html_e( 'Target language (for fixing):', 'multilingual-bridge' ); ?></label></p>
						<p>
							<select name="target_language" id="target_language">
								<?php
								$active_languages = apply_filters( 'wpml_active_languages', null );
								if ( ! empty( $active_languages ) ) {
									foreach ( $active_languages as $lang_code => $language ) {
										echo '<option value="' . esc_attr( $lang_code ) . '">' . esc_html( $language['native_name'] ) . ' (' . esc_html( $lang_code ) . ')</option>';
									}
								}
								?>
							</select>
						</p>

						<div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0; border-radius: 4px;">
							<strong><?php esc_html_e( 'Note:', 'multilingual-bridge' ); ?></strong>
							<?php esc_html_e( 'This operation will only affect posts on the current site. Use preflight check first to see the impact.', 'multilingual-bridge' ); ?>
						</div>

						<p><input type="submit" class="button-secondary" value="<?php esc_html_e( 'Execute Debug Action', 'multilingual-bridge' ); ?>" /></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles language debug operations for posts in unconfigured languages
	 *
	 * @return void
	 */
	public function handle_language_debug(): void {
		// Early exit if WPML is not active or not fully initialized.
		if ( ! defined( 'WPML_PLUGIN_PATH' ) || ! function_exists( 'icl_get_current_language' ) ) {
			wp_safe_redirect( admin_url( 'tools.php?page=multilingual-bridge-language-debug&msg=wpml_not_active' ) );
			exit;
		}

		// Check nonce
		$nonce = isset( $_POST['language_debug_nonce'] ) ? sanitize_key( wp_unslash( $_POST['language_debug_nonce'] ) ) : false;
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'language_debug' ) ) {
			wp_die( 'Something went wrong', 'multilingual-bridge' );
		}

		// Get form data
		$debug_action     = isset( $_POST['debug_action'] ) ? sanitize_key( wp_unslash( $_POST['debug_action'] ) ) : '';
		$debug_post_types = isset( $_POST['debug_post_type'] ) && is_array( $_POST['debug_post_type'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['debug_post_type'] ) )
			: array( 'all' );
		$target_language  = isset( $_POST['target_language'] ) ? sanitize_key( wp_unslash( $_POST['target_language'] ) ) : '';

		if ( empty( $debug_action ) ) {
			wp_die( 'Invalid action specified', 'multilingual-bridge' );
		}

		// Process debug action on current site only
		try {
			$result = $this->process_language_debug( $debug_action, $debug_post_types, $target_language );
		} catch ( Exception $e ) {
			wp_die( 'Error during language debug operation: ' . esc_html( $e->getMessage() ), 'multilingual-bridge' );
		}

		// Ensure result is valid
		if ( ! isset( $result['count'] ) ) {
			wp_die( 'Invalid result from language debug operation', 'multilingual-bridge' );
		}

		// Redirect back with appropriate message
		if ( 'preflight' === $debug_action ) {
			$post_types_display = $this->format_post_types_for_display( $debug_post_types );
			$breakdown_param    = ! empty( $result['breakdown'] ) ? rawurlencode( wp_json_encode( $result['breakdown'] ) ) : '';
			$redirect_url       = admin_url( 'tools.php?page=multilingual-bridge-language-debug&msg=preflight_complete&count=' . (int) $result['count'] . '&debug_post_type=' . rawurlencode( $post_types_display ) . '&breakdown=' . $breakdown_param );
		} elseif ( 0 === $result['count'] ) {
			$redirect_url = admin_url( 'tools.php?page=multilingual-bridge-language-debug&msg=no_orphaned_posts' );
		} else {
			$redirect_url = admin_url( 'tools.php?page=multilingual-bridge-language-debug&msg=debug_completed' );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Processes language debug operations
	 *
	 * @param string        $action         The debug action to perform ('preflight', 'delete' or 'fix_language').
	 * @param array<string> $post_types      Array of post types to process or ['all'] for all types.
	 * @param string        $target_language The target language for fixing operations.
	 * @return array<string, mixed> Result with count of affected posts and breakdown by post type.
	 */
	private function process_language_debug( string $action, array $post_types, string $target_language ): array {
		// Early return for empty action
		if ( empty( $action ) ) {
			return array(
				'count'     => 0,
				'breakdown' => array(),
			);
		}

		// Get active languages on this site
		$active_language_codes = WPML_Post_Helper::get_active_language_codes();
		if ( empty( $active_language_codes ) ) {
			return array(
				'count'     => 0,
				'breakdown' => array(),
			);
		}

		$processed_count   = 0;
		$breakdown_by_type = array();

		// Get post types to process
		$post_types_to_process = $this->get_post_types_to_process( $post_types );
		if ( empty( $post_types_to_process ) ) {
			return array(
				'count'     => 0,
				'breakdown' => array(),
			);
		}

		foreach ( $post_types_to_process as $current_post_type ) {
			// Skip if post type doesn't exist
			if ( ! post_type_exists( $current_post_type ) ) {
				continue;
			}

			// Initialize count for this post type
			$type_count = 0;

			// Process posts in batches to avoid memory issues
			$batch_size = 100; // Process 100 posts at a time
			$page       = 1;

			do {
				// Query only post IDs in batches for better performance
				$query = new \WP_Query(
					array(
						'post_type'              => $current_post_type,
						'post_status'            => 'any',
						'posts_per_page'         => $batch_size, // Batch size instead of -1
						'paged'                  => $page,
						'suppress_filters'       => true, // Get posts in all languages with WPML
						'no_found_rows'          => false, // We need pagination info for batch processing
						'ignore_sticky_posts'    => true, // Not relevant for this use case
						'update_post_meta_cache' => false, // Don't preload meta unless needed
						'update_post_term_cache' => false, // Don't preload terms unless needed
						'fields'                 => 'ids', // Only get post IDs for better memory usage
					)
				);

				if ( empty( $query->posts ) ) {
					break;
				}

				// Process each post ID in the current batch
				foreach ( $query->posts as $post_id ) {
					// Check if post is in unconfigured language using the helper
					if ( WPML_Post_Helper::is_post_in_unconfigured_language( $post_id ) ) {
						++$processed_count;
						++$type_count;

						// Only execute action if not preflight
						if ( 'preflight' !== $action ) {
							$this->execute_debug_action_on_post( $post_id, $action, $target_language );
						}
					}
				}

				// Move to next page
				++$page;

			} while ( $page <= $query->max_num_pages );

			// Store breakdown for this post type if any posts found
			if ( $type_count > 0 ) {
				$breakdown_by_type[ $current_post_type ] = $type_count;
			}
		}

		return array(
			'count'     => $processed_count,
			'breakdown' => $breakdown_by_type,
		);
	}


	/**
	 * Gets the list of post types to process based on the selection
	 *
	 * @param array<string> $post_types Array of selected post types (['all'] or specific types).
	 * @return array<string> Array of post types to process.
	 */
	private function get_post_types_to_process( array $post_types ): array {
		// If 'all' is selected or array contains 'all', get all post types
		if ( in_array( 'all', $post_types, true ) ) {
			// Get all public post types
			$all_post_types = get_post_types( array( 'public' => true ), 'names' );

			return array_values( $all_post_types );
		}

		// Return only the specific post types selected
		return array_filter(
			$post_types,
			function ( $post_type ) {
				return post_type_exists( $post_type );
			}
		);
	}

	/**
	 * Formats post types array for display in admin notices
	 *
	 * @param array<string> $post_types Array of post types.
	 * @return string Formatted string for display.
	 */
	private function format_post_types_for_display( array $post_types ): string {
		if ( in_array( 'all', $post_types, true ) ) {
			return 'all';
		}

		if ( count( $post_types ) === 1 ) {
			return $post_types[0];
		}

		if ( count( $post_types ) <= 3 ) {
			return implode( ', ', $post_types );
		}

		return count( $post_types ) . ' selected types';
	}

	/**
	 * Executes the debug action on a post
	 *
	 * @param int    $post_id          The post ID.
	 * @param string $action           The action to perform.
	 * @param string $target_language  The target language for fixing.
	 * @return void
	 */
	private function execute_debug_action_on_post( int $post_id, string $action, string $target_language ): void {
		switch ( $action ) {
			case 'delete':
				// Delete the post permanently
				wp_delete_post( $post_id, true );
				break;

			case 'fix_language':
				// Fix the language assignment
				if ( ! empty( $target_language ) ) {
					WPML_Post_Helper::set_language( $post_id, $target_language );
				}
				break;
		}
	}
}