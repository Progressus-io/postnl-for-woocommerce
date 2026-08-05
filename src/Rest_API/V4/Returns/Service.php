<?php
/**
 * Class Rest_API\V4\Returns\Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Returns;

use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\Service\ReturnShipment\V4\Response\GenerateReturnResponseInterface;
use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Rest_API\Contracts\Return_Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Return_Label\Item_Info;
use PostNLWooCommerce\Rest_API\Legacy\Return_Label_Service as Legacy_Return_Label_Service;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service
 *
 * V4 SDK implementation of the return-label flow. It generates a return label
 * through the /shipment/delivery/v4/return/generate endpoint and stores the
 * result in the same _postnl_order_metadata['labels']['return-label'] shape as
 * the legacy path, tagged api_version = v4.
 *
 * Scope: the retailPrint NL flow (the live NL return mechanism). A non-NL origin
 * — consumerPrint is a BE-origin follow-on — falls back to the untouched legacy
 * pipeline. activate() is unchanged: it still calls the V1
 * /parcels/v1/shipment/activatereturn endpoint via the legacy service, since
 * that operation is not part of the return/generate migration. Because both
 * gates (a validated V4 key and the per-flow flag) default off, merging this
 * changes nothing for merchants.
 *
 * Extends Order\Base to reuse the put/merge helpers and the legacy pipeline for
 * the fallback, mirroring Legacy\Return_Label_Service and V4\Label\Service.
 *
 * @since   5.9.10
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */
class Service extends Order_Base implements Return_Label_Service_Interface {

	/**
	 * SDK client factory. Injected in tests; built from settings otherwise.
	 *
	 * @var Client_Factory|null
	 */
	private $client_factory;

	/**
	 * Service constructor.
	 *
	 * @param Client_Factory|null $client_factory Optional SDK client factory.
	 */
	public function __construct( ?Client_Factory $client_factory = null ) {
		parent::__construct();
		$this->client_factory = $client_factory;
	}

	/**
	 * No WP hooks are registered by this service class.
	 *
	 * Required by Order\Base but intentionally a no-op here.
	 */
	public function init_hooks() {
		// Intentionally empty — this service does not register WordPress hooks.
	}

	/**
	 * Create a return label and return the normalized label record.
	 *
	 * Routes the NL retailPrint return to the V4 SDK; a non-NL origin runs the
	 * untouched legacy pipeline.
	 *
	 * @param array $post_data Context needed to build and send the return request.
	 *
	 * @return array Normalized label record keyed by 'return-label'.
	 *
	 * @throws \Exception If the SDK request fails (converted to the legacy error shape).
	 */
	public function create( array $post_data ): array {
		$item_info = new Item_Info( $post_data );

		// Mirror the legacy pipeline guard: a return label is only created when the
		// merchant selected it. The pipeline returns an empty array in that case.
		if ( 'yes' !== ( $post_data['saved_data']['backend']['create_return_label'] ?? '' )
			|| ! $this->is_eligible( $item_info ) ) {
			return $this->maybe_create_return_label_pipeline( $post_data );
		}

		$fields  = $this->extract_fields( $item_info, $post_data );
		$request = Request_Builder::build( $fields );

		try {
			$response = $this->build_client()->returns()->generateReturn( $request );
		} catch ( \Throwable $exception ) {
			$error = Exception_Converter::convert( $exception );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception_Converter returns an already-escaped, translated message.
			throw $error;
		}

		return $this->store_labels( $response, $post_data['order'], (string) $fields['barcode'] );
	}

	/**
	 * Activate the return function on an existing outbound shipment.
	 *
	 * Unchanged by the return/generate migration — delegates to the legacy
	 * service, which calls the V1 /parcels/v1/shipment/activatereturn endpoint.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array JSON-decoded PostNL activatereturn response body.
	 * @throws \Exception If the API request fails at transport level.
	 */
	public function activate( int $order_id ): array {
		return ( new Legacy_Return_Label_Service() )->activate( $order_id );
	}

	/**
	 * Decide whether an order's return is the NL retailPrint flow this service handles.
	 *
	 * Only NL-origin returns use retailPrint; a BE origin (consumerPrint) is not
	 * yet implemented and falls back to legacy.
	 *
	 * @param Item_Info $item_info Parsed legacy return item info.
	 * @return bool
	 */
	private function is_eligible( Item_Info $item_info ): bool {
		return 'NL' === (string) ( $item_info->shipper['country'] ?? '' );
	}

	/**
	 * Flatten the parsed item info into the field array Request_Builder consumes.
	 *
	 * The sender is the consumer returning the item (the order's shipping
	 * recipient); the receiver is the merchant return address, already resolved by
	 * Item_Info to the reply-number (Antwoordnummer) or return-to-home variant per
	 * Settings::is_return_to_home_enabled().
	 *
	 * @param Item_Info $item_info Parsed legacy return item info.
	 * @param array     $post_data Original return post data.
	 * @return array
	 */
	private function extract_fields( Item_Info $item_info, array $post_data ): array {
		return array(
			'sender'       => array(
				'company'          => $item_info->receiver['company'] ?? '',
				'first_name'       => $item_info->receiver['first_name'] ?? '',
				'last_name'        => $item_info->receiver['last_name'] ?? '',
				'street'           => $item_info->receiver['address_1'] ?? '',
				'house_number'     => $item_info->receiver['house_number'] ?? '',
				'house_number_ext' => $item_info->receiver['address_2'] ?? '',
				'postcode'         => $item_info->receiver['postcode'] ?? '',
				'city'             => $item_info->receiver['city'] ?? '',
				'country'          => $item_info->receiver['country'] ?? '',
				'email'            => $item_info->shipment['email'] ?? '',
				'phone'            => $item_info->shipment['phone'] ?? '',
			),
			'receiver'     => array(
				'company'          => $item_info->customer['return_company'] ?? '',
				'street'           => $item_info->customer['return_address_1'] ?? '',
				'house_number'     => $item_info->customer['return_address_2'] ?? '',
				'house_number_ext' => '',
				'postcode'         => $item_info->customer['return_address_zip'] ?? '',
				'city'             => $item_info->customer['return_address_city'] ?? '',
				'country'          => $item_info->shipper['country'] ?? 'NL',
			),
			'print_method' => 'retailPrint',
			'barcode'      => (string) ( $post_data['return_barcode'] ?? '' ),
			'reference'    => (string) ( $item_info->shipment['order_number'] ?? '' ),
			'weight_gr'    => (int) ( $item_info->shipment['total_weight'] ?? 0 ),
			'label'        => array(
				'output_type' => $this->resolve_output_type( (string) ( $item_info->shipment['printer_type'] ?? '' ) ),
			),
		);
	}

	/**
	 * Resolve the merchant's combined printer-type string to a discrete output type.
	 *
	 * PostNL advises PNG/JPG for retailPrint, but the merchant's configured format
	 * is respected; PDF is the fallback.
	 *
	 * @param string $printer_type Legacy combined printer-type string.
	 * @return string
	 */
	private function resolve_output_type( string $printer_type ): string {
		foreach ( array( 'zpl', 'jpg', 'gif', 'png', 'pdf' ) as $candidate ) {
			if ( false !== stripos( $printer_type, $candidate ) ) {
				return $candidate;
			}
		}

		return 'pdf';
	}

	/**
	 * Build the configured SDK client for the current environment.
	 *
	 * @return PostnlClientInterface
	 */
	private function build_client(): PostnlClientInterface {
		$factory = $this->client_factory ?? new Client_Factory( $this->settings );

		$v4_key = method_exists( $this->settings, 'get_api_key_new' )
			? (string) $this->settings->get_api_key_new()
			: '';

		return $factory->build( $v4_key, (bool) $this->settings->is_sandbox() );
	}

	/**
	 * Write the return/generate response label(s) to disk and normalize them.
	 *
	 * Reuses Order\Base::maybe_merge_labels() so the A4/A6 handling matches the
	 * legacy path, then re-tags the merged record as V4.
	 *
	 * @param GenerateReturnResponseInterface $response         return/generate response.
	 * @param \WC_Order                       $order            WooCommerce order.
	 * @param string                          $fallback_barcode Barcode to use if the response omits one.
	 * @return array
	 * @throws \Exception When the response carries no return item or no label content.
	 */
	private function store_labels( GenerateReturnResponseInterface $response, $order, string $fallback_barcode ): array {
		$item = Response_Mapper::first_return_item( $response );

		if ( null === $item ) {
			throw new \Exception(
				esc_html__( 'Cannot create the return label. No shipment was returned by PostNL.', 'postnl-for-woocommerce' )
			);
		}

		$barcode = Response_Mapper::get_barcode( $item, $fallback_barcode );
		$records = array();

		foreach ( Response_Mapper::get_labels( $item ) as $label ) {
			$content = Response_Mapper::decode_content( $label );

			if ( '' === $content ) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
			$output_type = null !== $label->outputType ? $label->outputType->value : 'pdf';

			$filename = Utils::generate_label_name( $order->get_id(), 'return-label', $barcode, 'A6', $output_type );
			$filepath = trailingslashit( POSTNL_UPLOADS_DIR ) . $filename;

			$this->write_label_file( $filepath, $content );

			// Only record a label that actually exists on disk, so a failed write
			// never persists a meta entry pointing at a missing file.
			if ( ! is_file( $filepath ) ) {
				continue;
			}

			$records[] = Response_Mapper::to_label_record( $barcode, $filepath );
		}

		if ( empty( $records ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the return label. Label content is missing', 'postnl-for-woocommerce' )
			);
		}

		$labels = $this->maybe_merge_labels( $records, $order, $barcode, 'return-label' );

		foreach ( array_keys( $labels ) as $key ) {
			$labels[ $key ]['api_version'] = 'v4';
		}

		return $labels;
	}

	/**
	 * Write a decoded label document to disk, creating the uploads dir as needed.
	 *
	 * A pre-existing file is left untouched. Failure is intentionally silent —
	 * store_labels() verifies the file exists afterwards and skips the record if
	 * it does not.
	 *
	 * @param string $filepath Absolute destination path.
	 * @param string $content  Raw (decoded) label bytes.
	 * @return void
	 */
	private function write_label_file( string $filepath, string $content ): void {
		if ( is_file( $filepath ) ) {
			return;
		}

		if ( ! wp_mkdir_p( POSTNL_UPLOADS_DIR ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Binary label bytes; mirrors Order\Base::put_label_content().
		file_put_contents( $filepath, $content );
	}
}
