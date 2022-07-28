<?php
/**
 * Class User file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User
 *
 * @package PostNLWooCommerce
 */
class User {
	/**
	 * Manipulate the WooCommerce template file location.
	 *
	 * @param string $role Template filename before manipulated.
	 *
	 * @return String
	 */
	public static function current_user_has_role( $role ) {
		return self::user_has_role_by_user_id( get_current_user_id(), $role );
	}

	/**
	 * Get user roles by using user ID.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	public static function get_user_roles_by_user_id( $user_id ) {
		$user = get_userdata( $user_id );
		return empty( $user ) ? array() : $user->roles;
	}

	/**
	 * Get user roles by using user ID.
	 *
	 * @param int   $user_id User ID.
	 * @param array $role Roles of user.
	 *
	 * @return array
	 */
	public static function user_has_role_by_user_id( $user_id, $role ) {
		$user_roles = self::get_user_roles_by_user_id( $user_id );

		if ( is_array( $role ) ) {
			return array_intersect( $role, $user_roles ) ? true : false;
		}

		return in_array( $role, $user_roles, true );
	}

	/**
	 * Static function to check if user role is shop manager or not.
	 *
	 * @param int $user_id WordPress User ID.
	 *
	 * @return bool
	 */
	public static function is_shop_manager( $user_id = false ) {
		$user = new \WP_User( $user_id );

		if ( ! $user->exists() ) {
			$user = wp_get_current_user();
		}

		$allowed_roles = array( 'shop_manager', 'administrator' );

		if ( array_intersect( $allowed_roles, $user->roles ) ) {
			return true;    // When user is shop manager.
		} else {
			return false;   // When user is not shop manager.
		}
	}
}
