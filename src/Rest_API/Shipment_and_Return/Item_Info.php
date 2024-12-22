<?php
/**
 * Class Rest_API\Shipping\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipment_and_Return;

use PostNLWooCommerce\Rest_API\Base_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */
class Item_Info extends Base_Info {

	/**
	 * Body of the item info.
	 *
	 * @var body
	 */
	public $body;

	/**
	 * Prefix for meta box fields.
	 *
	 * @var string
	 */
	protected $prefix = POSTNL_SETTINGS_ID;

	/**
	 * Meta name for saved fields.
	 *
	 * @var meta_name
	 */
	protected $meta_name;

	/**
	 * Order Id.
	 *
	 * @var int
	 */
	protected $order_id;

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param int $post_data Order Id.
	 */
	public function convert_data_to_args( $post_data ) {
		$this->meta_name = '_' . $this->prefix . '_order_metadata';
		$this->order_id  = $post_data;
	}

	/**
	 * Parses the arguments and sets the instance's properties.
	 */
	public function parse_args() {
		$this->body = array(
			'CustomerNumber' => $this->settings->get_customer_num(),
			'CustomerCode'   => $this->settings->get_customer_code(),
			'Barcode'        => $this->get_barcode(),
			'ReturnBarcode'  => $this->get_barcode(),
		);
	}

	/**
	 * Get barcode saved within Order meta.
	 *
	 * @return string.
	 */
	protected function get_barcode() {
		try {
			$order = wc_get_order( $this->order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				throw new \Exception( esc_html__( 'Given id is not an Order', 'postnl-for-woocommerce' ) );
			}
			$data = $order->get_meta( $this->meta_name );
			if ( empty( $data ) || ! isset( $data['labels']['label']['barcode'] ) ) {
				throw new \Exception( esc_html__( 'Missing barcode', 'postnl-for-woocommerce' ) );
			}
			return $data['labels']['label']['barcode'];
		} catch ( \Exception $e ) {
			return '';
		}
	}
}
