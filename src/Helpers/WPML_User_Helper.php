<?php
/**
 * WPML User Helper functionality
 *
 * @package Multilingual_Bridge
 */

namespace Multilingual_Bridge\Helpers;

use WP_User;

/**
 * WPML User Helper Functions
 *
 * Provides simplified static methods for common WPML user and capability operations
 * that are not available out-of-the-box in WPML's API.
 *
 * @package Multilingual_Bridge\Helpers
 */
class WPML_User_Helper {

	/**
	 * Add translation manager capability to a specific user
	 *
	 * Grants the 'manage_translations' capability to a user regardless of their role,
	 * and synchronizes with WPML's ATE (Advanced Translation Editor) managers.
	 *
	 * @param int|WP_User $user User ID or WP_User object.
	 * @return bool True if capability was added successfully, false otherwise.
	 */
	public static function add_translation_manager_capability( int|WP_User $user ): bool {
		// Handle both user ID and WP_User object
		if ( $user instanceof WP_User ) {
			$user_id    = $user->ID;
			$user_object = $user;
		} else {
			$user_id = (int) $user;
			$user_object = get_user_by( 'ID', $user_id );
		}

		// Early return if user doesn't exist
		if ( ! $user_object || ! $user_object instanceof WP_User ) {
			return false;
		}

		// Check if user already has the capability
		if ( $user_object->has_cap( 'manage_translations' ) ) {
			return true; // Already has capability, consider this success
		}

		// Add the capability
		$result = $user_object->add_cap( 'manage_translations' );

		// Synchronize with WPML ATE managers if capability was added successfully
		if ( $result ) {
			/**
			 * Fires when a user is granted translation manager capability.
			 *
			 * This hook synchronizes the user with WPML's Advanced Translation Editor (ATE)
			 * managers, ensuring proper integration with WPML's translation management system.
			 *
			 * @param int $user_id The ID of the user who was granted the capability.
			 */
			do_action( 'wpml_tm_ate_synchronize_managers', $user_id );
		}

		return (bool) $result;
	}

	/**
	 * Add translation manager capability to all users with a specific role
	 *
	 * Iterates through all users assigned to the specified role and grants them
	 * the 'manage_translations' capability using the single user method.
	 *
	 * @param string $role The role name (e.g., 'author', 'contributor', 'editor').
	 * @return array{success: int, failed: int, total: int} Array with counts of successful, failed, and total operations.
	 */
	public static function add_translation_manager_capability_to_role( string $role ): array {
		// Early return for empty role
		if ( empty( $role ) ) {
			return array(
				'success' => 0,
				'failed'  => 0,
				'total'   => 0,
			);
		}

		// Get all users with the specified role
		$users = get_users( array(
			'role'   => $role,
			'fields' => 'ID', // Only get IDs for memory efficiency
		) );

		$success_count = 0;
		$failed_count  = 0;
		$total_count   = count( $users );

		// Process each user
		foreach ( $users as $user_id ) {
			$result = self::add_translation_manager_capability( (int) $user_id );
			
			if ( $result ) {
				++$success_count;
			} else {
				++$failed_count;
			}
		}

		return array(
			'success' => $success_count,
			'failed'  => $failed_count,
			'total'   => $total_count,
		);
	}

	/**
	 * Check if a user has translation manager capability
	 *
	 * Convenience method to check if a user has the 'manage_translations' capability.
	 *
	 * @param int|WP_User $user User ID or WP_User object.
	 * @return bool True if user has the capability, false otherwise.
	 */
	public static function has_translation_manager_capability( int|WP_User $user ): bool {
		// Handle both user ID and WP_User object
		if ( $user instanceof WP_User ) {
			$user_object = $user;
		} else {
			$user_id = (int) $user;
			$user_object = get_user_by( 'ID', $user_id );
		}

		// Early return if user doesn't exist
		if ( ! $user_object || ! $user_object instanceof WP_User ) {
			return false;
		}

		return $user_object->has_cap( 'manage_translations' );
	}

	/**
	 * Remove translation manager capability from a specific user
	 *
	 * Removes the 'manage_translations' capability from a user and synchronizes
	 * with WPML's ATE managers.
	 *
	 * @param int|WP_User $user User ID or WP_User object.
	 * @return bool True if capability was removed successfully, false otherwise.
	 */
	public static function remove_translation_manager_capability( int|WP_User $user ): bool {
		// Handle both user ID and WP_User object
		if ( $user instanceof WP_User ) {
			$user_id    = $user->ID;
			$user_object = $user;
		} else {
			$user_id = (int) $user;
			$user_object = get_user_by( 'ID', $user_id );
		}

		// Early return if user doesn't exist
		if ( ! $user_object || ! $user_object instanceof WP_User ) {
			return false;
		}

		// Check if user has the capability
		if ( ! $user_object->has_cap( 'manage_translations' ) ) {
			return true; // Already doesn't have capability, consider this success
		}

		// Remove the capability
		$user_object->remove_cap( 'manage_translations' );

		// Synchronize with WPML ATE managers after removal
		/**
		 * Fires when a user's translation manager capability is removed.
		 *
		 * This hook synchronizes the user with WPML's Advanced Translation Editor (ATE)
		 * managers, ensuring proper integration with WPML's translation management system.
		 *
		 * @param int $user_id The ID of the user who had the capability removed.
		 */
		do_action( 'wpml_tm_ate_synchronize_managers', $user_id );

		return true;
	}
}