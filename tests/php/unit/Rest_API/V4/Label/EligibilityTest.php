<?php
/**
 * Unit tests for Rest_API\V4\Label\Eligibility.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Label;

use PostNLWooCommerce\Rest_API\V4\Label\Eligibility;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Label\Eligibility
 */
class EligibilityTest extends UnitTestCase {

	/**
	 * A signal set for the happy-path domestic NL base parcel.
	 *
	 * @param array $overrides Values to override on the base set.
	 * @return array
	 */
	private function signals( array $overrides = array() ): array {
		return array_merge(
			array(
				'num_labels'      => 1,
				'is_delivery_day' => false,
				'is_pickup'       => false,
				'has_return'      => false,
				'origin'          => 'NL',
				'destination'     => 'NL',
				'mapped'          => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'parcel',
					'services'          => array(),
					'deliveryLocation'  => array(),
				),
			),
			$overrides
		);
	}

	/**
	 * @testdox is_eligible() accepts the happy-path domestic NL base parcel.
	 */
	public function test_base_parcel_is_eligible(): void {
		$this->assertTrue( Eligibility::is_eligible( $this->signals() ) );
	}

	/**
	 * @testdox is_eligible() accepts a domestic parcel carrying delivery services.
	 *
	 * A signature + insured + return-when-not-home parcel has a V4 equivalent and
	 * no pickup location, so it is routed to V4 with the services attached.
	 */
	public function test_service_bearing_parcel_is_eligible(): void {
		$signals = $this->signals(
			array(
				'mapped' => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'parcel',
					'services'          => array(
						'deliveryConfirmation' => 'signature',
						'insuredValue'         => '<order_total>',
						'returnWhenNotHome'    => true,
					),
					'deliveryLocation'  => array(),
				),
			)
		);

		$this->assertTrue( Eligibility::is_eligible( $signals ), 'A service-bearing domestic parcel should route to V4.' );
	}

	/**
	 * @testdox is_eligible() accepts a multi-collo domestic parcel.
	 */
	public function test_multi_collo_parcel_is_eligible(): void {
		$this->assertTrue( Eligibility::is_eligible( $this->signals( array( 'num_labels' => 3 ) ) ) );
	}

	/**
	 * @testdox is_eligible() accepts a domestic parcel with a delivery-day selection.
	 *
	 * A home delivery-day selection is the common NL case: it no longer forces a
	 * fall-back, so a standard delivery-day parcel routes to V4.
	 */
	public function test_delivery_day_parcel_is_eligible(): void {
		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( array( 'is_delivery_day' => true ) ) ),
			'A standard delivery-day parcel should route to V4.'
		);
	}

	/**
	 * @testdox is_eligible() accepts an evening delivery-day parcel.
	 *
	 * Evening rides on a deliveryWindow service layered on the same product code, so it
	 * maps like any parcel.
	 */
	public function test_evening_parcel_is_eligible(): void {
		$signals = $this->signals(
			array(
				'is_delivery_day' => true,
				'delivery_window' => 'evening',
			)
		);

		$this->assertTrue( Eligibility::is_eligible( $signals ), 'An evening delivery-day parcel should route to V4.' );
	}

	/**
	 * @testdox is_eligible() accepts a morning (08:00-12:00) parcel whose receiver can be contacted.
	 *
	 * Morning is PostNL's "Guaranteed Before 12:00" (legacy option 118/008), which
	 * labelconfirm refuses without a receiver email or phone number.
	 */
	public function test_morning_parcel_needs_a_receiver_contact(): void {
		$morning = array(
			'is_delivery_day' => true,
			'delivery_window' => 'morning',
		);

		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( $morning + array( 'has_contact' => true ) ) ),
			'A morning parcel with a receiver contact should route to V4.'
		);
		$this->assertFalse(
			Eligibility::is_eligible( $this->signals( $morning ) ),
			'A morning parcel without a receiver contact must fall back to legacy.'
		);
	}

	/**
	 * @testdox is_eligible() keeps the service and window pairs labelconfirm rejects on the legacy path.
	 * @dataProvider window_conflict_provider
	 *
	 * labelconfirm rejects minimalAgeCheck with an evening or guaranteed (morning)
	 * window, and the delivery-code product with a morning one, even though each maps
	 * cleanly on its own.
	 *
	 * @param string $window   Delivery window signal.
	 * @param array  $services Mapped services.
	 */
	public function test_window_conflicts_fall_back( string $window, array $services ): void {
		$signals = $this->signals(
			array(
				'is_delivery_day' => true,
				'delivery_window' => $window,
				'has_contact'     => true,
				'mapped'          => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'parcel',
					'services'          => $services,
					'deliveryLocation'  => array(),
				),
			)
		);

		$this->assertFalse( Eligibility::is_eligible( $signals ) );

		$signals['delivery_window'] = 'standard';
		$this->assertTrue( Eligibility::is_eligible( $signals ), 'The same services must route to V4 without a timed window.' );
	}

	/**
	 * Timed windows paired with the services they cannot be combined with.
	 *
	 * @return array
	 */
	public static function window_conflict_provider(): array {
		$age_check     = array( 'minimalAgeCheck' => '18+' );
		$delivery_code = array( 'deliveryConfirmation' => 'deliverycode', 'insuredValue' => '<order_total>' );

		return array(
			'evening + 18+'           => array( 'evening', $age_check ),
			'morning + 18+'           => array( 'morning', $age_check ),
			'morning + delivery code' => array( 'morning', $delivery_code ),
		);
	}

	/**
	 * @testdox is_eligible() accepts a delivery-code parcel with an evening slot.
	 *
	 * The sandbox accepts that pair, unlike the morning one.
	 */
	public function test_evening_combines_with_delivery_code(): void {
		$signals = $this->signals(
			array(
				'is_delivery_day' => true,
				'delivery_window' => 'evening',
				'mapped'          => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'parcel',
					'services'          => array( 'deliveryConfirmation' => 'deliverycode', 'insuredValue' => '<order_total>' ),
					'deliveryLocation'  => array(),
				),
			)
		);

		$this->assertTrue( Eligibility::is_eligible( $signals ) );
	}

	/**
	 * @testdox is_eligible() accepts an insured or signature parcel with an evening or morning slot.
	 */
	public function test_window_combines_with_signature_and_insurance(): void {
		foreach ( array( 'evening', 'morning' ) as $window ) {
			foreach ( array( array( 'deliveryConfirmation' => 'signature' ), array( 'insuredValue' => '<order_total>' ) ) as $services ) {
				$signals = $this->signals(
					array(
						'is_delivery_day' => true,
						'delivery_window' => $window,
						'has_contact'     => true,
						'mapped'          => array(
							'has_v4_equivalent' => true,
							'shipmentType'      => 'parcel',
							'services'          => $services,
							'deliveryLocation'  => array(),
						),
					)
				);

				$this->assertTrue( Eligibility::is_eligible( $signals ), "A {$window} parcel with " . key( $services ) . ' should route to V4.' );
			}
		}
	}

	/**
	 * @testdox is_eligible() accepts an EU/ROW international parcel from NL or BE.
	 * @dataProvider international_provider
	 *
	 * @param string $origin      Origin country.
	 * @param string $destination Shipping zone (EU|ROW).
	 */
	public function test_international_parcel_is_eligible( string $origin, string $destination ): void {
		$signals = $this->signals(
			array(
				'origin'      => $origin,
				'destination' => $destination,
				'mapped'      => array(
					'has_v4_equivalent'         => true,
					'shipmentType'              => 'parcel',
					'services'                  => array(),
					'deliveryLocation'          => array(),
					'internationalShipmentData' => array( 'bundle' => 'track_trace' ),
				),
			)
		);

		$this->assertTrue( Eligibility::is_eligible( $signals ), "An {$origin}→{$destination} parcel should route to V4." );
	}

	/**
	 * Origin/destination pairs for supported international parcels.
	 *
	 * @return array
	 */
	public static function international_provider(): array {
		return array(
			'NL→EU'  => array( 'NL', 'EU' ),
			'NL→ROW' => array( 'NL', 'ROW' ),
			'BE→EU'  => array( 'BE', 'EU' ),
			'BE→ROW' => array( 'BE', 'ROW' ),
		);
	}

	/**
	 * @testdox is_eligible() accepts a domestic NL letterbox (mailbox parcel 2928).
	 */
	public function test_letterbox_is_eligible(): void {
		$signals = $this->signals(
			array(
				'mapped' => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'letterbox',
					'services'          => array(),
					'deliveryLocation'  => array(),
				),
			)
		);

		$this->assertTrue( Eligibility::is_eligible( $signals ), 'A domestic NL letterbox should route to V4.' );
	}

	/**
	 * @testdox is_eligible() rejects a letterbox shipment type for a non-domestic destination.
	 *
	 * Letterbox is a NL-only mailbox parcel; the mapper never yields it for an
	 * international destination, and the gate must reject it even if one is forced.
	 */
	public function test_international_letterbox_is_ineligible(): void {
		$signals = $this->signals(
			array(
				'destination' => 'EU',
				'mapped'      => array(
					'has_v4_equivalent' => true,
					'shipmentType'      => 'letterbox',
					'services'          => array(),
					'deliveryLocation'  => array(),
				),
			)
		);

		$this->assertFalse( Eligibility::is_eligible( $signals ), 'An international letterbox must fall back to legacy.' );
	}

	/**
	 * @testdox resolve_mapped() maps a 24h letterbox (2928) to the letterbox shipment type, eligible end-to-end.
	 */
	public function test_resolve_mapped_letterbox_is_eligible(): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, array( 'letterbox' => 'yes' ), '2928' );

		$this->assertTrue( $mapped['has_v4_equivalent'] );
		$this->assertSame( 'letterbox', $mapped['shipmentType'] );
		$this->assertSame( array( 'deliveryWindowDuration' => '24hours' ), $mapped['services'], 'labelconfirm rejects a letterbox without a duration.' );
		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( array( 'mapped' => $mapped ) ) ),
			'A 24h letterbox should route to V4.'
		);
	}

	/**
	 * @testdox resolve_mapped() maps a 48h letterbox (2948) to the non24hours duration, eligible end-to-end.
	 *
	 * The meta box collapses both variants onto the 'letterbox' option, so only the
	 * product code tells them apart; a 2948 order must not ship as a 24h letterbox.
	 */
	public function test_resolve_mapped_letterbox_48_is_eligible(): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, array( 'letterbox' => 'yes' ), '2948' );

		$this->assertTrue( $mapped['has_v4_equivalent'] );
		$this->assertSame( '2948', $mapped['legacy_product_code'] );
		$this->assertSame( 'letterbox', $mapped['shipmentType'] );
		$this->assertSame( array( 'deliveryWindowDuration' => 'non24hours' ), $mapped['services'] );
		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( array( 'mapped' => $mapped ) ) ),
			'A 48h letterbox should route to V4.'
		);
	}

	/**
	 * @testdox A domestic NL pickup order routes to V4 with the mapped services.
	 * @dataProvider pickup_provider
	 *
	 * @param array  $backend  Raw backend feature flags ('yes' strings).
	 * @param string $code     Legacy product code resolved for that combination.
	 * @param array  $services Expected mapped service flags.
	 */
	public function test_pickup_order_is_eligible( array $backend, string $code, array $services ): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', true, $backend, $code );

		$this->assertSame( $services, $mapped['services'], "Unexpected services for pickup product {$code}." );
		$this->assertTrue(
			Eligibility::is_eligible(
				$this->signals(
					array(
						'is_pickup' => true,
						'pickup_id' => '176227',
						'mapped'    => $mapped,
					)
				)
			),
			"Pickup product {$code} should route to V4."
		);
	}

	/**
	 * Every NL→NL pickup row with its expected mapped services.
	 *
	 * @return array
	 */
	public static function pickup_provider(): array {
		return array(
			'base'          => array( array(), '3533', array() ),
			'insured'       => array( array( 'insured_shipping' => 'yes' ), '3534', array( 'insuredValue' => '<order_total>' ) ),
			'18+'           => array( array( 'id_check' => 'yes' ), '3571', array( 'minimalAgeCheck' => '18+' ) ),
			'18+ + insured' => array( array( 'id_check' => 'yes', 'insured_shipping' => 'yes' ), '3581', array( 'insuredValue' => '<order_total>', 'minimalAgeCheck' => '18+' ) ),
		);
	}

	/**
	 * @testdox An NL to BE parcel routes to V4 with the mapped services.
	 * @dataProvider cross_border_provider
	 *
	 * @param array  $backend  Raw backend feature flags ('yes' strings).
	 * @param string $code     Legacy product code resolved for that combination.
	 * @param array  $services Expected resolved service flags.
	 */
	public function test_nl_to_be_parcel_is_eligible( array $backend, string $code, array $services ): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'BE', false, $backend, $code );

		$this->assertTrue(
			Eligibility::is_eligible(
				$this->signals(
					array(
						'destination' => 'BE',
						'mapped'      => $mapped,
					)
				)
			),
			'NL to BE combination [' . implode( ',', array_keys( $backend ) ) . '] should route to V4.'
		);
		$this->assertSame( $services, Eligibility::resolve_services( $mapped['services'], 42.0 ) );
	}

	/**
	 * Every NL→BE parcel row that has a V4 equivalent.
	 *
	 * @return array
	 */
	public static function cross_border_provider(): array {
		return array(
			'base'                => array( array(), '4946', array() ),
			'only_home_address'   => array( array( 'only_home_address' => 'yes' ), '4941', array( 'statedAddressOnly' => true ) ),
			'signature'           => array( array( 'signature_on_delivery' => 'yes' ), '4912', array( 'deliveryConfirmation' => 'signature' ) ),
			'insured'             => array( array( 'insured_shipping' => 'yes' ), '4914', array( 'insuredValue' => 42.0 ) ),
			'insured + T&T'       => array( array( 'insured_shipping' => 'yes', 'track_and_trace' => 'yes' ), '4914', array( 'insuredValue' => 42.0 ) ),
			// Signature is implicit on the insured BE parcel and is never sent separately,
			// so a signature selection collapses to the plain insured value.
			'insured + signature' => array( array( 'insured_shipping' => 'yes', 'signature_on_delivery' => 'yes' ), '4914', array( 'insuredValue' => 42.0 ) ),
			'insured + home'      => array( array( 'insured_shipping' => 'yes', 'only_home_address' => 'yes' ), '4914', array( 'insuredValue' => 42.0, 'statedAddressOnly' => true ) ),
			'insured + home + signature' => array( array( 'insured_shipping' => 'yes', 'only_home_address' => 'yes', 'signature_on_delivery' => 'yes' ), '4914', array( 'insuredValue' => 42.0, 'statedAddressOnly' => true ) ),
		);
	}

	/**
	 * @testdox Options set through the bulk "Change shipping options" action route to V4 like the same options set in the order meta box.
	 * @dataProvider bulk_options_provider
	 *
	 * @param string $destination Destination zone.
	 * @param bool   $is_pickup   Whether the order ships to a pickup point.
	 * @param array  $backend     Backend options as the bulk action stores them.
	 * @param string $code        Legacy product code resolved for that combination.
	 */
	public function test_bulk_options_route_to_v4( string $destination, bool $is_pickup, array $backend, string $code ): void {
		$mapped = Eligibility::resolve_mapped( 'NL', $destination, $is_pickup, $backend, $code );

		$this->assertTrue( $mapped['has_v4_equivalent'], 'The bulk action base product marker must not make the combination unknown.' );
		$this->assertSame( $code, $mapped['legacy_product_code'] );
	}

	/**
	 * Default shipping option tokens the bulk action stores, with their legacy product code.
	 *
	 * @return array
	 */
	public static function bulk_options_provider(): array {
		return array(
			'NL standard'          => array( 'NL', false, array( 'standard_shipment' => 'yes' ), '3085' ),
			'NL pickup standard'   => array( 'NL', true, array( '' => 'yes' ), '3533' ),
			'BE standard'          => array( 'BE', false, array( 'standard_belgium' => 'yes' ), '4946' ),
			'BE only home address' => array( 'BE', false, array( 'standard_belgium' => 'yes', 'only_home_address' => 'yes' ), '4941' ),
			'BE signature'         => array( 'BE', false, array( 'standard_belgium' => 'yes', 'signature_on_delivery' => 'yes' ), '4912' ),
			'BE pickup'            => array( 'BE', true, array( 'standard_belgium' => 'yes' ), '4936' ),
			'EU parcel'            => array( 'EU', false, array( 'eu_parcel' => 'yes', 'track_and_trace' => 'yes' ), '4907' ),
			'ROW parcel'           => array( 'ROW', false, array( 'parcel_non_eu' => 'yes', 'track_and_trace' => 'yes' ), '4909' ),
		);
	}

	/**
	 * @testdox is_eligible() rejects orders outside the happy path.
	 * @dataProvider ineligible_provider
	 *
	 * @param array  $overrides Signal overrides that should force a fall-back.
	 * @param string $reason    Human description for the failure message.
	 */
	public function test_ineligible_cases( array $overrides, string $reason ): void {
		$this->assertFalse(
			Eligibility::is_eligible( $this->signals( $overrides ) ),
			"Expected fall-back to legacy for: {$reason}"
		);
	}

	/**
	 * Data provider of signal overrides that must each fall back to legacy.
	 *
	 * @return array
	 */
	public static function ineligible_provider(): array {
		return array(
			'zero collo'                 => array( array( 'num_labels' => 0 ), 'an invalid collo count' ),
			'pickup without a location'  => array( array( 'is_pickup' => true, 'mapped' => array( 'has_v4_equivalent' => true, 'shipmentType' => 'parcel', 'services' => array(), 'deliveryLocation' => array( 'pickupLocationId' => 'x' ) ) ), 'a pickup point with no location code' ),
			'pickup on a home row'       => array( array( 'is_pickup' => true, 'pickup_id' => '176227' ), 'a pickup order mapped to a home-delivery row' ),
			'return involved'            => array( array( 'has_return' => true ), 'a return label' ),
			'morning without a contact'  => array( array( 'delivery_window' => 'morning' ), 'a morning (08:00-12:00) window with no receiver email or phone' ),
			'BE to NL'                   => array( array( 'origin' => 'BE' ), 'a BE origin shipping to NL' ),
			'BE domestic'                => array( array( 'origin' => 'BE', 'destination' => 'BE' ), 'a BE domestic parcel' ),
			// Identical to the eligible happy path except for the one flag under test.
			// Leaving the other mapped keys out would let the shipmentType check reject
			// this row first, so has_v4_equivalent itself would never be exercised.
			'no v4 equivalent'           => array( array( 'mapped' => array( 'has_v4_equivalent' => false, 'shipmentType' => 'parcel', 'services' => array(), 'deliveryLocation' => array() ) ), 'no V4 equivalent' ),
			'unsupported shipment type'  => array( array( 'mapped' => array( 'has_v4_equivalent' => true, 'shipmentType' => 'pallet', 'services' => array() ) ), 'an unsupported shipment type' ),
			'home order on a pickup row' => array( array( 'mapped' => array( 'has_v4_equivalent' => true, 'shipmentType' => 'parcel', 'services' => array(), 'deliveryLocation' => array( 'pickupLocationId' => 'x' ) ) ), 'a home-delivery order mapped to a pickup row' ),
		);
	}

	/**
	 * @testdox resolve_mapped() maps a plain NL base parcel to a V4 parcel with no services.
	 */
	public function test_resolve_mapped_base_parcel(): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, array(), '3085' );

		$this->assertTrue( $mapped['has_v4_equivalent'] );
		$this->assertSame( 'parcel', $mapped['shipmentType'] );
		$this->assertEmpty( $mapped['services'] );
	}

	/**
	 * @testdox resolve_mapped() keeps an insured 3085 parcel off V4 (no silent insurance drop).
	 *
	 * insured_shipping keeps product code 3085 but is not a standalone NL→NL row,
	 * so passing the real option must resolve to no V4 equivalent — proving the
	 * options are fed to the mapper rather than hardcoded empty.
	 */
	public function test_resolve_mapped_insured_parcel_has_no_v4_equivalent(): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, array( 'insured_shipping' => 'yes' ), '3085' );

		$this->assertFalse( $mapped['has_v4_equivalent'], 'An insured 3085 parcel must not resolve to a V4 equivalent.' );
	}

	/**
	 * @testdox An insured 3085 parcel is not eligible end-to-end through resolve_mapped + is_eligible.
	 */
	public function test_insured_parcel_falls_back(): void {
		$mapped   = Eligibility::resolve_mapped( 'NL', 'NL', false, array( 'insured_shipping' => 'yes' ), '3085' );
		$eligible = Eligibility::is_eligible( $this->signals( array( 'mapped' => $mapped ) ) );

		$this->assertFalse( $eligible, 'An insured domestic parcel must fall back to the legacy path.' );
	}

	/**
	 * @testdox A delivery-code + insured 3085 parcel routes to V4 with both services.
	 *
	 * This combination keeps product 3085 but resolves to a V4 services row
	 * (deliveryConfirmation=deliverycode + insuredValue), so it is eligible and
	 * the services carry the delivery-code and insurance that V1 expressed via a
	 * product option and an Amounts block.
	 */
	public function test_delivery_code_insured_is_eligible_with_services(): void {
		$mapped = Eligibility::resolve_mapped(
			'NL',
			'NL',
			false,
			array(
				'delivery_code_at_door' => 'yes',
				'insured_shipping'      => 'yes',
			),
			'3085'
		);

		$this->assertTrue( $mapped['has_v4_equivalent'] );
		$this->assertSame( 'deliverycode', $mapped['services']['deliveryConfirmation'] );
		$this->assertArrayHasKey( 'insuredValue', $mapped['services'] );
		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( array( 'mapped' => $mapped ) ) ),
			'A delivery-code + insured parcel should route to V4 with services.'
		);
	}

	/**
	 * @testdox resolve_mapped() maps a signature-on-delivery parcel to the confirmation service.
	 */
	public function test_resolve_mapped_signature_service(): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, array( 'signature_on_delivery' => 'yes' ), '3189' );

		$this->assertTrue( $mapped['has_v4_equivalent'] );
		$this->assertSame( 'signature', $mapped['services']['deliveryConfirmation'] );
	}

	/**
	 * @testdox resolve_services() replaces the order-total placeholder with the insured amount.
	 */
	public function test_resolve_services_fills_insured_value(): void {
		$resolved = Eligibility::resolve_services(
			array(
				'deliveryConfirmation' => 'signature',
				'insuredValue'         => '<order_total>',
			),
			49.95
		);

		$this->assertSame( 49.95, $resolved['insuredValue'] );
		$this->assertSame( 'signature', $resolved['deliveryConfirmation'], 'Other flags pass through unchanged.' );
	}

	/**
	 * @testdox resolve_services() leaves a services array without insurance untouched.
	 */
	public function test_resolve_services_without_insurance_is_unchanged(): void {
		$services = array( 'statedAddressOnly' => true );

		$this->assertSame( $services, Eligibility::resolve_services( $services, 10.0 ) );
	}

	/**
	 * @testdox Every domestic NL parcel option routes to V4 with the expected services.
	 * @dataProvider domestic_option_provider
	 *
	 * @param array  $backend  Raw backend feature flags ('yes' strings).
	 * @param string $code     Legacy product code resolved for that combination.
	 * @param array  $services Expected resolved service flags (keys/values).
	 */
	public function test_domestic_options_route_to_v4_with_services( array $backend, string $code, array $services ): void {
		$mapped = Eligibility::resolve_mapped( 'NL', 'NL', false, $backend, $code );

		$this->assertTrue(
			Eligibility::is_eligible( $this->signals( array( 'mapped' => $mapped ) ) ),
			"Combination for product {$code} should route to V4."
		);

		$resolved = Eligibility::resolve_services( $mapped['services'], 42.0 );

		foreach ( $services as $key => $value ) {
			$this->assertArrayHasKey( $key, $resolved, "Expected service '{$key}' for product {$code}." );
			$this->assertSame( $value, $resolved[ $key ], "Unexpected value for service '{$key}' on product {$code}." );
		}

		$this->assertSame(
			count( $services ),
			count( $resolved ),
			"Product {$code} produced unexpected extra services: " . implode( ',', array_keys( $resolved ) )
		);
	}

	/**
	 * Every NL→NL delivery_day parcel row that has a V4 equivalent, with its
	 * expected resolved services (insurance resolved to the 42.0 subtotal above).
	 *
	 * @return array
	 */
	public static function domestic_option_provider(): array {
		return array(
			'base'                                   => array( array(), '3085', array() ),
			'only_home_address'                      => array( array( 'only_home_address' => 'yes' ), '3385', array( 'statedAddressOnly' => true ) ),
			'return_no_answer'                       => array( array( 'return_no_answer' => 'yes' ), '3090', array( 'returnWhenNotHome' => true ) ),
			'signature'                              => array( array( 'signature_on_delivery' => 'yes' ), '3189', array( 'deliveryConfirmation' => 'signature' ) ),
			'home + return'                          => array( array( 'only_home_address' => 'yes', 'return_no_answer' => 'yes' ), '3390', array( 'returnWhenNotHome' => true, 'statedAddressOnly' => true ) ),
			'home + signature'                       => array( array( 'only_home_address' => 'yes', 'signature_on_delivery' => 'yes' ), '3089', array( 'deliveryConfirmation' => 'signature', 'statedAddressOnly' => true ) ),
			'insured + signature'                    => array( array( 'insured_shipping' => 'yes', 'signature_on_delivery' => 'yes' ), '3087', array( 'insuredValue' => 42.0 ) ),
			'insured + return + signature'           => array( array( 'insured_shipping' => 'yes', 'return_no_answer' => 'yes', 'signature_on_delivery' => 'yes' ), '3094', array( 'insuredValue' => 42.0, 'returnWhenNotHome' => true ) ),
			'return + signature'                     => array( array( 'return_no_answer' => 'yes', 'signature_on_delivery' => 'yes' ), '3389', array( 'deliveryConfirmation' => 'signature', 'returnWhenNotHome' => true ) ),
			'home + return + signature'              => array( array( 'only_home_address' => 'yes', 'return_no_answer' => 'yes', 'signature_on_delivery' => 'yes' ), '3096', array( 'deliveryConfirmation' => 'signature', 'returnWhenNotHome' => true, 'statedAddressOnly' => true ) ),
			'delivery_code + insured'                => array( array( 'delivery_code_at_door' => 'yes', 'insured_shipping' => 'yes' ), '3085', array( 'deliveryConfirmation' => 'deliverycode', 'insuredValue' => 42.0 ) ),
		);
	}
}
