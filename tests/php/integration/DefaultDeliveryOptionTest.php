<?php
/**
 * Integration tests for the delivery option the checkout preselects.
 *
 * The classic template checks the first option of the first date, the Blocks
 * delivery-day component selects deliveryOptions[0].options[0] from the
 * Extend_Block_Core payload, and both are built by Delivery_Day::get_content_data().
 * Container::get_default_value() is the classic fallback when nothing is picked.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use Postnl\Sdk\ResponseData\V4\TimeSlot;
use Postnl\Sdk\Service\Timeframes\Response\MultipleServicesTimeframeCollection;
use Postnl\Sdk\Service\Timeframes\Response\Timeframe;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Timeframe\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * Covers the default delivery option with paid morning and evening windows present.
 */
class DefaultDeliveryOptionTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * Store country before the test.
	 *
	 * @var string
	 */
	private $orig_country = '';

	/**
	 * Use an NL store with delivery days, morning and evening delivery on.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = (string) get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['enable_delivery_days']    = 'yes';
		$settings->settings['enable_morning_delivery'] = 'yes';
		$settings->settings['morning_delivery_fee']    = '2';
		$settings->settings['enable_evening_delivery'] = 'yes';
		$settings->settings['evening_delivery_fee']    = '2';

		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) as $day ) {
			$settings->settings[ 'dropoff_day_' . $day ] = 'yes';
		}
	}

	/**
	 * Restore the settings and the store country.
	 */
	protected function tearDown(): void {
		Settings::get_instance()->settings = $this->orig_settings;
		update_option( 'woocommerce_default_country', $this->orig_country );

		parent::tearDown();
	}

	/**
	 * @testdox The first option the classic and Blocks checkouts preselect is free Daytime, not the paid morning slot
	 */
	public function test_checkout_preselects_free_daytime(): void {
		$data = ( new Delivery_Day() )->get_content_data( $this->v4_response(), array() );

		$first = $data['delivery_options'][0]['options'][0];

		$this->assertSame( 'Daytime', $first['type'] );
		$this->assertSame( 0, $first['price'], 'The preselected option must not carry a fee.' );
		$this->assertSame(
			array( 'Daytime', '08:00-12:00', 'Evening' ),
			array_column( $data['delivery_options'][0]['options'], 'type' ),
			'Morning and evening stay selectable behind Daytime.'
		);
	}

	/**
	 * @testdox The classic fallback default is Daytime even when a paid window comes first
	 */
	public function test_fallback_default_skips_a_leading_paid_window(): void {
		$response = array(
			'DeliveryOptions' => array(
				array(
					'DeliveryDate' => '2026-11-17',
					'Timeframe'    => array(
						array(
							'From'    => '17:30:00',
							'To'      => '22:00:00',
							'Options' => array( 'Evening' ),
						),
						array(
							'From'    => '10:00:00',
							'To'      => '12:30:00',
							'Options' => array( 'Daytime' ),
						),
					),
				),
			),
		);

		$default = ( new Container() )->get_default_value( $response, array() );

		$this->assertSame( 'Daytime', $default['type'] );
		$this->assertSame( '10:00', $default['from'] );
		$this->assertSame( '12:30', $default['to'] );
		$this->assertSame( 0, $default['price'] );
	}

	/**
	 * @testdox The classic fallback default is Daytime for a V4 day with morning and evening windows
	 */
	public function test_fallback_default_for_a_v4_day(): void {
		$default = ( new Container() )->get_default_value( $this->v4_response(), array() );

		$this->assertSame( 'Daytime', $default['type'] );
		$this->assertSame( '10:00', $default['from'] );
	}

	/**
	 * A V4 timeframe response in the order the sandbox returns it, mapped by the service.
	 *
	 * @return array
	 */
	private function v4_response(): array {
		$settings = Settings::get_instance();
		$service  = new class( new Client_Factory( $settings ), $settings, 'v4-key', 10, new NullLogger() ) extends Service {

			/**
			 * Expose the response mapping.
			 *
			 * @param MultipleServicesTimeframeCollection $collection SDK timeframe collection.
			 * @return array
			 */
			public function map( MultipleServicesTimeframeCollection $collection ): array {
				return $this->map_response( $collection );
			}
		};

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '2026-11-17', timeFrame: new TimeSlot( from: '08:00:00', until: '10:00:00' ), availability: true, service: 'guaranteed before 10' ),
				new Timeframe( deliveryDate: '2026-11-17', timeFrame: new TimeSlot( from: '17:30:00', until: '22:00:00' ), availability: true, service: 'evening' ),
				new Timeframe( deliveryDate: '2026-11-17', timeFrame: new TimeSlot( from: '10:00:00', until: '12:30:00' ), availability: true, service: 'daytime' ),
			)
		);

		return array( 'DeliveryOptions' => $service->map( $collection ) );
	}
}
