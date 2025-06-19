<?php
/**
 * Session class.
 *
 * This class adds helper functions for managing custom WC session variables.
 *
 * @package Progressus\MightyKids
 */

namespace PostNLWooCommerce;

/**
 * Session class.
 */
class Session {
	/**
	 * Session variable key prefix.
	 *
	 * @var string
	 */
	const SESSION_KEY_PREFIX = 'postnl_';

	/**
	 * Check if WC Session exists.
	 *
	 * @return bool
	 */
	public static function wc_session_exists(): bool {
		return ! empty( WC()->session );
	}

	/**
	 * Get a session variable.
	 *
	 * @param string $key Session variable key.
	 *
	 * @return string|array|null Session variable value or null if not set.
	 */
	public static function get( string $key ): string|array|null {
		if ( self::wc_session_exists() ) {
			return WC()->session->get( self::SESSION_KEY_PREFIX . $key );
		}

		return null;
	}

	/**
	 * Set a session variable.
	 *
	 * @param string $key   Session variable key.
	 * @param mixed  $value Session variable value.
	 */
	public static function set( string $key, mixed $value ): void {
		if ( self::wc_session_exists() ) {
			WC()->session->set( self::SESSION_KEY_PREFIX . $key, $value );
		}
	}

	/**
	 * Delete a session variable.
	 *
	 * @param string $key Session variable key.
	 */
	public static function delete( string $key ): void {
		if ( self::wc_session_exists() ) {
			WC()->session->__unset( self::SESSION_KEY_PREFIX . $key );
		}
	}
}