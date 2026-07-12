<?php
/**
 * Class Rest_API\SDK\Logger_Adapter file.
 *
 * @package PostNLWooCommerce\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\SDK;

use PostNLWooCommerce\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger_Adapter
 *
 * Bridges the V4 SDK's PSR-3 logging to the plugin's WooCommerce logger.
 * Messages are tagged [postnl-v4] so support can filter the shared WC log on
 * the originating API version; legacy entries carry no tag.
 *
 * The SDK redacts label binary, PII, and credentials via its
 * RedactionRegistry::forProduction() (on by default) before a message ever
 * reaches this adapter, so the adapter writes what it receives verbatim — it
 * deliberately does not re-run the legacy Logger::check_pdf_content() scan.
 *
 * @since   5.9.6
 * @package PostNLWooCommerce\Rest_API\SDK
 */
class Logger_Adapter extends AbstractLogger {

	/**
	 * Tag prefixed to every V4 message so support can filter the shared WC log.
	 */
	private const TAG = '[postnl-v4]';

	/**
	 * WC log source (channel) shared with the legacy plugin logger.
	 */
	private const SOURCE = 'PostNLWooCommerce';

	/**
	 * The eight valid PSR-3 levels, which are identical to WooCommerce's
	 * WC_Log_Levels (both follow RFC 5424). Any value outside this set falls
	 * back to notice rather than reaching WC_Logger, which rejects unknown
	 * levels.
	 */
	private const VALID_LEVELS = array(
		LogLevel::EMERGENCY,
		LogLevel::ALERT,
		LogLevel::CRITICAL,
		LogLevel::ERROR,
		LogLevel::WARNING,
		LogLevel::NOTICE,
		LogLevel::INFO,
		LogLevel::DEBUG,
	);

	/**
	 * Plugin logger, consulted only for the merchant "enable logging" gate so
	 * the V4 path honours the same setting as the legacy path.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Logger_Adapter constructor.
	 *
	 * @param Logger $logger Plugin logger providing the enable-logging gate.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Write a PSR-3 log record to the WooCommerce logger.
	 *
	 * Best-effort: any failure while formatting or writing (e.g. a throwing
	 * Stringable message, or wc_get_logger() being unavailable) is swallowed so
	 * logging can never break the SDK operation that emitted the line.
	 *
	 * @param mixed              $level   PSR-3 level (one of LogLevel::*).
	 * @param string|\Stringable $message Message, optionally with {placeholders}.
	 * @param array              $context Context values for placeholder interpolation.
	 * @return void
	 */
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		// Respect the same merchant "enable logging" toggle the legacy path uses.
		if ( ! $this->logger->is_enabled() ) {
			return;
		}

		try {
			$level   = $this->normalize_level( $level );
			$message = self::TAG . ' ' . $this->interpolate( (string) $message, $context );

			$wc_logger = wc_get_logger();
			if ( $wc_logger ) {
				$wc_logger->log( $level, $message, array( 'source' => self::SOURCE ) );
			}
		} catch ( \Throwable $e ) {
			// Swallowed deliberately — logging is best-effort and must not propagate.
			return;
		}
	}

	/**
	 * Coerce an arbitrary level into a value WC_Logger accepts.
	 *
	 * @param mixed $level Incoming PSR-3 level.
	 * @return string A valid WC log level; notice when unrecognised.
	 */
	private function normalize_level( $level ): string {
		$level = is_string( $level ) ? strtolower( $level ) : '';

		return in_array( $level, self::VALID_LEVELS, true ) ? $level : LogLevel::NOTICE;
	}

	/**
	 * Interpolate {placeholder} tokens from context, per the PSR-3 spec.
	 *
	 * Only context values that can be cast to a string are substituted; other
	 * values (arrays, non-stringable objects) leave their placeholder intact.
	 *
	 * @param string $message Raw message.
	 * @param array  $context Context values.
	 * @return string
	 */
	private function interpolate( string $message, array $context ): string {
		if ( empty( $context ) || false === strpos( $message, '{' ) ) {
			return $message;
		}

		$replacements = array();
		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) || $value instanceof \Stringable ) {
				$replacements[ '{' . $key . '}' ] = (string) $value;
			}
		}

		return strtr( $message, $replacements );
	}
}
