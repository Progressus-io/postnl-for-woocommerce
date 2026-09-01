<?php
/**
 * Minimal settings stand-in for Client_Factory.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

/**
 * Minimal settings stand-in for Client_Factory, which only reads the customer
 * credentials off the object it is handed.
 */
class Client_Factory_Settings {

	/**
	 * @return string
	 */
	public function get_customer_num() {
		return '11223344';
	}

	/**
	 * @return string
	 */
	public function get_customer_code() {
		return 'DEVC';
	}
}
