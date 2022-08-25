<?php
/**
 * Class Logger file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 *
 * @package PostNLWooCommerce
 */
class Logger {

	/**
	 * Debug flag.
	 *
	 * @var debug.
	 */
	private $debug;

	/**
	 * Logger constructor.
	 *
	 * @param boolean $debug debug flag.
	 */
	public function __construct( $debug ) {
		$this->debug = $debug;
	}

	/**
	 * Check if logging is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return empty( $this->debug ) || false === is_bool( $this->debug ) ? false : $this->debug;
	}

	/**
	 * Write the message to log.
	 *
	 * @param String $message Message to be written in log.
	 */
	public function write( $message ) {

		// Check if enabled.
		if ( $this->is_enabled() ) {

			// Logger object.
			$wc_logger = new \WC_Logger();

			// Add to logger.
			$wc_logger->add( 'PostNLWooCommerce', $message );
		}

	}

	/**
	 * Get log URL.
	 */
	public static function get_log_url() {
		return admin_url( 'admin.php?page=wc-status&tab=logs' );
	}

}
