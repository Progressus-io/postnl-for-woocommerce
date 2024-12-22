<?php
/**
 * Class Checkout_Blocks/Blocks_Integration file.
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */

namespace PostNLWooCommerce\Checkout_Blocks;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending the Store API endpoint for custom PostNL data in checkout.
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */
class Extend_Store_Endpoint {
	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendSchema
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
		add_action(
			'init',
			function () {
				self::$extend = StoreApi::container()->get( ExtendSchema::class );
				self::extend_store();
			}
		);
	}

	/**
	 * Registers the actual data into the Checkout endpoint.
	 */
	public static function extend_store() {
		if ( is_callable( array( self::$extend, 'register_endpoint_data' ) ) ) {
			self::$extend->register_endpoint_data(
				array(
					'endpoint'        => CheckoutSchema::IDENTIFIER,
					'namespace'       => self::IDENTIFIER,
					'schema_callback' => array( __CLASS__, 'extend_checkout_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Defines the schema for PostNL delivery data in Checkout.
	 *
	 * @return array Schema structure.
	 */
	public static function extend_checkout_schema() {
		return array(
			'billingHouseNumber'          => array(
				'description' => 'Billing house number PostNL',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'shippingHouseNumber'         => array(
				'description' => 'Shipping house number PostNL',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'deliveryDay'                 => array(
				'description' => 'Selected delivery day for PostNL',

				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'deliveryDayDate'             => array(
				'description' => 'Selected delivery day date for PostNL',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'deliveryDayFrom'             => array(
				'description' => 'Delivery start time for PostNL delivery day',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'deliveryDayTo'               => array(
				'description' => 'Delivery end time for PostNL delivery day',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'deliveryDayPrice'            => array(
				'description' => 'Price for the selected PostNL delivery time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
			),
			'deliveryDayType'             => array(
				'description' => 'Type of delivery (Morning, Evening, etc.) for PostNL delivery day',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			// Updated hidden fields for the drop-off point
			'dropoffPoints'               => array(
				'description' => 'Selected drop-off point identifier',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsAddressCompany' => array(
				'description' => 'Company name of the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsAddress1'       => array(
				'description' => 'Address line 1 of the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsAddress2'       => array(
				'description' => 'Address line 2 of the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsCity'           => array(
				'description' => 'City of the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsPostcode'       => array(
				'description' => 'Postcode of the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsCountry'        => array(
				'description' => 'Country of the drop-off point',
				// Use 'country' format if applicable
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsPartnerID'      => array(
				'description' => 'Partner ID of the drop-off point',
				// Change to 'number' if it's numeric
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsDate'           => array(
				'description' => 'Date of the drop-off point selection',
				// Use 'date' format if applicable
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsTime'           => array(
				'description' => 'Time of the drop-off point selection',
				// Use 'time' format if applicable
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => '',
			),
			'dropoffPointsDistance'       => array(
				'description' => 'Distance to the drop-off point',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'default'     => 0.0,
				'nullable'    => true,
			),
		);
	}
}
