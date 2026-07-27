<?php
/**
 * Unit tests for the Legacy service/client interface contracts and alias shims.
 *
 * PHP enforces `implements` only at class-load time, so without these assertions
 * a future signature/interface drift on a Legacy client or wrapper would pass CI
 * silently. The alias smoke test guards the backward-compat shims that keep the
 * old Rest_API\<Flow>\* FQCNs resolving after the move into Legacy\.
 *
 * @package PostNLWooCommerce\Tests\Rest_API
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API;

use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @coversNothing
 */
class LegacyServiceContractsTest extends UnitTestCase {

	/**
	 * Each Legacy client/wrapper must implement its declared contract interface.
	 *
	 * @dataProvider contract_provider
	 * @testdox $class implements $interface
	 *
	 * @param string $class     Fully-qualified Legacy class name.
	 * @param string $interface Fully-qualified contract interface it must implement.
	 */
	public function test_legacy_class_implements_contract( string $class, string $interface ): void {
		$this->assertTrue( class_exists( $class ), "Class {$class} should be autoloadable." );
		$this->assertContains(
			$interface,
			class_implements( $class ),
			"{$class} must implement {$interface}."
		);
	}

	/**
	 * Raw clients implement directly; stateless wrappers re-declare the same contract.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function contract_provider(): array {
		$ns        = 'PostNLWooCommerce\\Rest_API\\Legacy\\';
		$contracts = 'PostNLWooCommerce\\Rest_API\\Contracts\\';

		return array(
			// Raw clients that implement their contract directly.
			'Barcode\Client'              => array( $ns . 'Barcode\\Client', $contracts . 'Barcode_Service_Interface' ),
			'Checkout\Client (timeframe)' => array( $ns . 'Checkout\\Client', $contracts . 'Timeframe_Service_Interface' ),
			'Checkout\Client (pickup)'    => array( $ns . 'Checkout\\Client', $contracts . 'Pickup_Location_Service_Interface' ),
			'Postcode_Check\Client'       => array( $ns . 'Postcode_Check\\Client', $contracts . 'Postcode_Check_Service_Interface' ),
			'Smart_Returns\Client'        => array( $ns . 'Smart_Returns\\Client', $contracts . 'Smart_Returns_Service_Interface' ),

			// Stateless service wrappers.
			'Barcode_Service'                => array( $ns . 'Barcode_Service', $contracts . 'Barcode_Service_Interface' ),
			'Checkout_Service (timeframe)'   => array( $ns . 'Checkout_Service', $contracts . 'Timeframe_Service_Interface' ),
			'Checkout_Service (pickup)'      => array( $ns . 'Checkout_Service', $contracts . 'Pickup_Location_Service_Interface' ),
			'Postcode_Check_Service'         => array( $ns . 'Postcode_Check_Service', $contracts . 'Postcode_Check_Service_Interface' ),
			'Smart_Returns_Service'          => array( $ns . 'Smart_Returns_Service', $contracts . 'Smart_Returns_Service_Interface' ),
			'Label_Service'                  => array( $ns . 'Label_Service', $contracts . 'Label_Service_Interface' ),
			'Letterbox_Service'              => array( $ns . 'Letterbox_Service', $contracts . 'Label_Service_Interface' ),
			'Return_Label_Service'           => array( $ns . 'Return_Label_Service', $contracts . 'Return_Label_Service_Interface' ),
		);
	}

	/**
	 * Each original Rest_API\<Flow>\* FQCN must still resolve, via the class_alias()
	 * shim, to its moved Legacy\<Flow>\* implementation.
	 *
	 * @dataProvider alias_provider
	 * @testdox Legacy alias $old_fqcn resolves to its Legacy implementation
	 *
	 * @param string $old_fqcn    The pre-move FQCN that callers still reference.
	 * @param string $legacy_fqcn The moved implementation it must alias to.
	 */
	public function test_legacy_alias_resolves( string $old_fqcn, string $legacy_fqcn ): void {
		// Autoloading the old FQCN loads the shim, which fires class_alias().
		$this->assertTrue( class_exists( $old_fqcn ), "Alias {$old_fqcn} should resolve." );

		$ref = new \ReflectionClass( $old_fqcn );
		$this->assertSame(
			$legacy_fqcn,
			$ref->getName(),
			"{$old_fqcn} must be an alias of {$legacy_fqcn}."
		);
	}

	/**
	 * Every one of the 8 moved flows has a Client + Item_Info alias shim (16 total).
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function alias_provider(): array {
		$old    = 'PostNLWooCommerce\\Rest_API\\';
		$legacy = 'PostNLWooCommerce\\Rest_API\\Legacy\\';
		$flows  = array(
			'Barcode',
			'Checkout',
			'Letterbox',
			'Postcode_Check',
			'Return_Label',
			'Shipment_and_Return',
			'Shipping',
			'Smart_Returns',
		);

		$cases = array();
		foreach ( $flows as $flow ) {
			foreach ( array( 'Client', 'Item_Info' ) as $type ) {
				$cases[ "{$flow}\\{$type}" ] = array(
					$old . $flow . '\\' . $type,
					$legacy . $flow . '\\' . $type,
				);
			}
		}

		return $cases;
	}
}
