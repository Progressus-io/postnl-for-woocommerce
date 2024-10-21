<?php
/**
 * Class Checkout_Blocks/Blocks_Integration file.
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */

namespace PostNLWooCommerce\Checkout_Blocks;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Extend store endpoint
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */
class Extend_Store_Endpoint {
	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendRestApi
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'postnl';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public static function init() {
		self::$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class );
		self::extend_store();
	}

	/**
	 * Registers the actual data into each endpoint.
	 */
	public static function extend_store() {
		if ( is_callable( [ self::$extend, 'register_endpoint_data' ] ) ) {
			self::$extend->register_endpoint_data(
				[
					'endpoint'        => CheckoutSchema::IDENTIFIER,
					'namespace'       => self::IDENTIFIER,
					'schema_callback' => [ __CLASS__, 'extend_checkout_schema' ],
					'schema_type'     => ARRAY_A,
				]
			);
		}
	}

	/**
	 * Register PostNL delivery day schema into the Checkout endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extend_checkout_schema() {
		return [
			'postnl_billing_house_number' => [
				'description' => 'Billing house number PostNL',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],
			'postnl_shipping_house_number' => [
				'description' => 'Shipping house number PostNL',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],

			'postnl_delivery_day_date' => [
				'description' => 'Selected delivery day date for PostNL',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],
			'postnl_delivery_day_from' => [
				'description' => 'Delivery start time for PostNL delivery day',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],
			'postnl_delivery_day_to' => [
				'description' => 'Delivery end time for PostNL delivery day',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],
			'postnl_delivery_day_price' => [
				'description' => 'Price for the selected PostNL delivery time',
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_numeric( $value );
					},
				],
			],
			'postnl_delivery_day_type' => [
				'description' => 'Type of delivery (Morning, Evening, etc.) for PostNL delivery day',
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'arg_options' => [
					'validate_callback' => function ( $value ) {
						return is_string( $value );
					},
				],
			],
		];
	}
}
