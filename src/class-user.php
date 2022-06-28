<?php
/**
 * Class Main file.
 *
 * @package Progressus\PostNLWooCommerce
 */

namespace Progressus\PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User
 *
 * @package Progressus\PostNLWooCommerce
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
}
