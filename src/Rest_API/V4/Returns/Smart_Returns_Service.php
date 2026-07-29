<?php
/**
 * Class Rest_API\V4\Returns\Smart_Returns_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Returns;

use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\ResponseData\V4\Label;
use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Item_Info;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns_Service as Legacy_Smart_Returns_Service;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Smart_Returns_Service
 *
 * V4 SDK implementation of the printer-less Smart Return flow. It generates the
 * return through the same /shipment/delivery/v4/return/generate endpoint as the
 * outbound return-label service (task 22) with printMethod = retailPrint, and
 * returns the auto-issued barcode together with the retail printcode image
 * (PNG/JPG) so Order\Single can show it in the customer email instead of the
 * legacy PrintcodeLabel PDF attachment.
 *
 * Scope: the NL retailPrint flow. PostNL confirmed (2026-05-21) the return/generate
 * endpoint replaces the legacy V2.2 /shipment/v2_2/label/ flow, with no separate
 * activation call and — for the consumer — "only the printcode, not a PDF". A
 * non-NL origin (consumerPrint is a BE-origin follow-on) falls back to the
 * untouched legacy Smart Returns pipeline. Because both gates (a validated V4 key
 * and the per-flow flag) default off, merging this changes nothing for merchants.
 *
 * Unlike the outbound return-label service this does not extend Order\Base or
 * write to order meta: a Smart Return produces an email-only printcode, exactly
 * as the legacy Smart Returns path did (it wrote a throwaway printcode file just
 * to attach it).
 *
 * @since   5.9.10
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */
class Smart_Returns_Service implements Smart_Returns_Service_Interface {

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
		$this->client_factory = $client_factory;
	}

	/**
	 * Generate a Smart Return printcode for the given order.
	 *
	 * Routes an NL order to the V4 return/generate endpoint (retailPrint) and
	 * returns a normalized array carrying the printcode image; a non-NL order runs
	 * the untouched legacy pipeline, whose raw response shape Order\Single already
	 * handles.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return array Normalized V4 result (api_version = v4) or the legacy response array.
	 *
	 * @throws \Exception If the SDK request fails (converted to the legacy error shape).
	 */
	public function generate( \WC_Order $order ): array {
		if ( 'NL' !== $order->get_shipping_country() ) {
			return ( new Legacy_Smart_Returns_Service() )->generate( $order );
		}

		$item_info = new Item_Info( $order );
		$fields    = self::map_fields(
			$item_info->customer,
			$item_info->store,
			array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
			(string) $order->get_order_number()
		);

		$request = Request_Builder::build( $fields );

		try {
			$response = $this->build_client()->returns()->generateReturn( $request );
		} catch ( \Throwable $exception ) {
			$error = Exception_Converter::convert( $exception );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception_Converter returns an already-escaped, translated message.
			throw $error;
		}

		$item = Response_Mapper::first_return_item( $response );

		if ( null === $item ) {
			throw new \Exception(
				esc_html__( 'Cannot create the Smart Return. No shipment was returned by PostNL.', 'postnl-for-woocommerce' )
			);
		}

		foreach ( Response_Mapper::get_labels( $item ) as $label ) {
			$content = Response_Mapper::decode_content( $label );

			if ( '' === $content ) {
				continue;
			}

			$output_type = self::label_output_type( $label );

			return array(
				'api_version' => 'v4',
				'barcode'     => Response_Mapper::get_barcode( $item ),
				'content'     => $content,
				'output_type' => $output_type,
				'mime'        => self::output_type_to_mime( $output_type ),
			);
		}

		throw new \Exception(
			esc_html__( 'Cannot create the Smart Return. Printcode content is missing', 'postnl-for-woocommerce' )
		);
	}

	/**
	 * Translate the parsed Smart Returns item info into Request_Builder fields.
	 *
	 * The sender is the consumer returning the item (the order's shipping
	 * recipient); the receiver is the merchant return address the legacy Item_Info
	 * already resolved to the reply-number (Antwoordnummer) or return-to-home
	 * variant. printMethod is always retailPrint and the output is forced to PNG so
	 * the printcode can be shown inline in the customer email.
	 *
	 * Kept pure (no WooCommerce or settings access) so the retailPrint mapping and
	 * both addressing variants can be asserted in isolation.
	 *
	 * @param array  $customer  Legacy Smart_Returns\Item_Info::$customer (consumer + return address).
	 * @param array  $store     Legacy Smart_Returns\Item_Info::$store (merchant return company/country).
	 * @param array  $consumer  Consumer contact: first_name, last_name, email, phone.
	 * @param string $reference Merchant shipment reference (order number).
	 * @return array Request_Builder input.
	 */
	public static function map_fields( array $customer, array $store, array $consumer, string $reference ): array {
		return array(
			'sender'       => array(
				'company'          => $customer['company'] ?? '',
				'first_name'       => $consumer['first_name'] ?? '',
				'last_name'        => $consumer['last_name'] ?? '',
				'street'           => $customer['address_1'] ?? '',
				'house_number'     => $customer['house_number'] ?? '',
				'house_number_ext' => $customer['address_2'] ?? '',
				'postcode'         => $customer['postcode'] ?? '',
				'city'             => $customer['city'] ?? '',
				'country'          => $customer['country'] ?? '',
				'email'            => $consumer['email'] ?? '',
				'phone'            => $consumer['phone'] ?? '',
			),
			'receiver'     => array(
				'company'          => $store['company'] ?? '',
				'street'           => $customer['return_address_1'] ?? '',
				'house_number'     => $customer['return_address_2'] ?? '',
				'house_number_ext' => $customer['return_address_house_noext'] ?? '',
				'postcode'         => $customer['return_address_zip'] ?? '',
				'city'             => $customer['return_address_city'] ?? '',
				'country'          => $store['country'] ?? 'NL',
			),
			'print_method' => 'retailPrint',
			'barcode'      => '',
			'reference'    => $reference,
			'weight_gr'    => 0,
			'label'        => array(
				// PostNL advises PNG/JPG for retailPrint; PNG lets the printcode render inline in the email.
				'output_type' => 'png',
			),
		);
	}

	/**
	 * Resolve a label's output type, defaulting to png when the response omits it.
	 *
	 * @param Label $label Label object from the response.
	 * @return string
	 */
	private static function label_output_type( Label $label ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		return null !== $label->outputType ? $label->outputType->value : 'png';
	}

	/**
	 * Map a label output type to the image MIME type used to embed it in the email.
	 *
	 * @param string $output_type One of png|jpg|gif|pdf.
	 * @return string
	 */
	private static function output_type_to_mime( string $output_type ): string {
		$map = array(
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'pdf' => 'application/pdf',
		);

		return $map[ strtolower( $output_type ) ] ?? 'image/png';
	}

	/**
	 * Build the configured SDK client for the current environment.
	 *
	 * @return PostnlClientInterface
	 */
	private function build_client(): PostnlClientInterface {
		$settings = Settings::get_instance();
		$factory  = $this->client_factory ?? new Client_Factory( $settings );

		$v4_key = method_exists( $settings, 'get_api_key_new' )
			? (string) $settings->get_api_key_new()
			: '';

		return $factory->build( $v4_key, (bool) $settings->is_sandbox() );
	}
}
