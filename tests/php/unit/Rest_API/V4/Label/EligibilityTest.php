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
				'delivery_type'   => 'Standard',
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
			'multi-collo'                => array( array( 'num_labels' => 2 ), 'more than one collo' ),
			'delivery-day selected'      => array( array( 'is_delivery_day' => true ), 'a delivery-day option' ),
			'pickup selected'            => array( array( 'is_pickup' => true ), 'a pickup point' ),
			'return involved'            => array( array( 'has_return' => true ), 'a return label' ),
			'evening delivery'           => array( array( 'delivery_type' => 'Evening' ), 'evening delivery' ),
			'non-NL origin'              => array( array( 'origin' => 'BE' ), 'a non-NL origin' ),
			'non-NL destination'         => array( array( 'destination' => 'BE' ), 'a non-NL destination' ),
			'no v4 equivalent'           => array( array( 'mapped' => array( 'has_v4_equivalent' => false ) ), 'no V4 equivalent' ),
			'non-parcel shipment type'   => array( array( 'mapped' => array( 'has_v4_equivalent' => true, 'shipmentType' => 'letterbox', 'services' => array() ) ), 'a letterbox shipment type' ),
			'mapped with pickup location' => array( array( 'mapped' => array( 'has_v4_equivalent' => true, 'shipmentType' => 'parcel', 'services' => array(), 'deliveryLocation' => array( 'pickupLocationId' => 'x' ) ) ), 'a pickup delivery location' ),
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
}
