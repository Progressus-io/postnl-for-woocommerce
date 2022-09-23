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
	 * @param Mixed $message Message to be written in log.
	 */
	public function write( $message ) {
		// Check if enabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		$message = apply_filters( 'postnl_logger_write_message', $message );
		$message = $this->check_pdf_content( $message );

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		// Logger object.
		$wc_logger = new \WC_Logger();

		// Add to logger.
		$wc_logger->add( 'PostNLWooCommerce', $message );
	}

	/**
	 * Check if the content has PDF binary value.
	 *
	 * @param Mixed $message Message to be written in log.
	 *
	 * @return Mixed.
	 */
	public function check_pdf_content( $message ) {
		if ( ! Utils::is_json( $message ) ) {
			return $message;
		}

		$message       = json_decode( $message, true );
		$message_types = Utils::get_label_response_type();

		foreach ( $message_types as $type => $content_type ) {
			if ( empty( $message[ $type ] ) ) {
				continue;
			}

			foreach ( $message[ $type ] as $shipment_idx => $shipment_contents ) {

				if ( empty( $shipment_contents['Labels'] ) ) {
					continue 2;
				}

				foreach ( $shipment_contents['Labels'] as $label_idx => $label_contents ) {
					if ( empty( $label_contents['Content'] ) ) {
						continue 3;
					}

					if ( empty( $label_contents[ $content_type['content_type_key'] ] ) ) {
						continue 3;
					}

					if ( $content_type['content_type_value'] !== $label_contents[ $content_type['content_type_key'] ] ) {
						continue 3;
					}

					$message[ $type ][ $shipment_idx ]['Labels'][ $label_idx ]['Content'] = '[PDF data]';
				}
			}
		}

		return $message;
	}

	/**
	 * Get log URL.
	 */
	public static function get_log_url() {
		return admin_url( 'admin.php?page=wc-status&tab=logs' );
	}

}
