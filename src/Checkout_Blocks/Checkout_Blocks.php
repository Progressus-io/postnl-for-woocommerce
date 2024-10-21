<?php
/**
 * Class Checkout_Blocks/Checkout_Blocks file.
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */

namespace PostNLWooCommerce\Checkout_Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout_Blocks
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */

class Checkout_Blocks {

	public function __construct() {

		// Initialize endpoints and core functionality.
		$extend_store = new Extend_Store_Endpoint();
		$extend_core  = new Extend_Block_Core(); // Assuming this is the renamed class.

		$extend_store->init();
		$extend_core->init(); // Uncomment if needed.

		// Register the blocks integration with WooCommerce blocks.
		/*add_action( 'woocommerce_blocks_checkout_block_registration', function( $integration_registry ) {
			$integration_registry->register( new Blocks_Integration() );
		});*/

		// Register the block category.
		add_action( 'block_categories_all', array( $this, 'register_postnl_block_category' ), 10, 2 );

	}

	/**
	 * Registers the slug as a block category with WordPress.
	 *
	 * @param array $categories Existing categories.
	 * @return array Modified categories.
	 */
	public function register_postnl_block_category( $categories ) {
		return array_merge(
			$categories,
			[
				[
					'slug'  => 'postnl',
					'title' => __( 'Postnl Checkout Blocks', 'postnl-for-woocommerce' ),
				],
			]
		);
	}
}
