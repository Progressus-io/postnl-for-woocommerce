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
use Postnl\Sdk\Service\ReturnShipment\V4\Request\ReturnShipmentRequest;
use Postnl\Sdk\Service\ReturnShipment\Response\ReturnShipmentResponseInterface;
use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Item_Info;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns_Service as Legacy_Smart_Returns_Service;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Shipping_Method\Settings;
use Psr\Log\LoggerInterface;

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
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */
class Smart_Returns_Service implements Smart_Returns_Service_Interface {

	/**
	 * Output types the printcode email can embed, mapped to their MIME types.
	 *
	 * Deliberately image-only: the consumer email renders the printcode with an
	 * <img> tag, and PostNL's instruction for this flow is "only the printcode,
	 * not a PDF". A response in any other format is rejected loudly in
	 * create_printcode() instead of being mailed as an unusable attachment.
	 *
	 * @var array<string, string>
	 */
	private const PRINTCODE_MIME_TYPES = array(
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
	);

	/**
	 * SDK client factory.
	 *
	 * @var Client_Factory
	 */
	private $client_factory;

	/**
	 * PostNL V4 API key.
	 *
	 * @var string
	 */
	private $v4_key;

	/**
	 * PSR-3 logger for request failures and suspect responses.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Service constructor.
	 *
	 * The key is marked SensitiveParameter so PHP redacts it from stack traces,
	 * matching Client_Factory::build().
	 *
	 * @since 6.0.0 Requires the resolved API key and a PSR-3 logger.
	 *
	 * @param Client_Factory  $client_factory SDK client factory.
	 * @param string          $v4_key         PostNL V4 API key.
	 * @param LoggerInterface $logger         PSR-3 logger; wiring passes a Logger_Adapter.
	 */
	public function __construct(
		Client_Factory $client_factory,
		#[\SensitiveParameter]
		string $v4_key,
		LoggerInterface $logger
	) {
		$this->client_factory = $client_factory;
		$this->v4_key         = $v4_key;
		$this->logger         = $logger;
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
	 * @throws \Exception If the SDK request fails (converted to the legacy error shape),
	 *                    or the response carries no usable printcode image.
	 */
	public function generate( \WC_Order $order ): array {
		if ( 'NL' !== $order->get_shipping_country() ) {
			return $this->legacy_service()->generate( $order );
		}

		$item_info = $this->item_info( $order );
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

		return $this->create_printcode( $request, (string) $order->get_order_number() );
	}

	/**
	 * Send the request and normalize the response into the printcode array.
	 *
	 * The response is only accepted when it carries an image the consumer email
	 * can embed (see PRINTCODE_MIME_TYPES): PostNL's instruction for retailPrint
	 * is to send the consumer the printcode, never a PDF, so an unexpected format
	 * fails loudly here instead of reaching the customer as a broken image with
	 * the document attached.
	 *
	 * @param ReturnShipmentRequest $request      Built return/generate request.
	 * @param string                $order_number Merchant order number, used in log entries.
	 *
	 * @return array Normalized printcode result (api_version = v4).
	 *
	 * @throws \Exception If the SDK request fails or the response has no usable image.
	 */
	protected function create_printcode( ReturnShipmentRequest $request, string $order_number ): array {
		$response = $this->generate_smart_return( $request, $order_number );

		$item = Response_Mapper::first_return_item( $response );

		if ( null === $item ) {
			throw new \Exception(
				esc_html__( 'Cannot create the Smart Return. No shipment was returned by PostNL.', 'postnl-for-woocommerce' )
			);
		}

		$candidates = array();
		$rejected   = array();

		foreach ( Response_Mapper::get_labels( $item ) as $label ) {
			if ( '' === Response_Mapper::decode_content( $label ) ) {
				continue;
			}

			$output_type = self::label_output_type( $label );

			if ( isset( self::PRINTCODE_MIME_TYPES[ $output_type ] ) ) {
				$candidates[] = $label;
			} else {
				$rejected[] = $output_type;
			}
		}

		if ( empty( $candidates ) ) {
			if ( ! empty( $rejected ) ) {
				$this->logger->error(
					sprintf(
						'V4 smart return for order "%1$s" answered with no embeddable printcode image; returned output type(s): %2$s. The consumer email was not sent.',
						$order_number,
						implode( ', ', $rejected )
					)
				);
				throw new \Exception(
					esc_html__( 'Cannot create the Smart Return. PostNL returned the printcode in a format that cannot be shown in the customer email. Check the PostNL logs for details.', 'postnl-for-woocommerce' )
				);
			}

			throw new \Exception(
				esc_html__( 'Cannot create the Smart Return. Printcode content is missing.', 'postnl-for-woocommerce' )
			);
		}

		if ( count( $candidates ) > 1 || ! empty( $rejected ) ) {
			// Which document combinations return/generate can produce is still a
			// sandbox question (flip checklist); record what arrived so a wrong
			// pick is traceable instead of invisible.
			$this->logger->warning(
				sprintf(
					'V4 smart return for order "%1$s" returned %2$d usable label(s) (types: %3$s)%4$s; the first usable one was emailed.',
					$order_number,
					count( $candidates ),
					implode( ', ', array_map( array( __CLASS__, 'describe_label' ), $candidates ) ),
					empty( $rejected ) ? '' : ' plus rejected type(s): ' . implode( ', ', $rejected )
				)
			);
		}

		$chosen      = $candidates[0];
		$output_type = self::label_output_type( $chosen );
		$barcode     = Response_Mapper::get_barcode( $item );

		if ( '' === $barcode ) {
			$this->logger->warning(
				sprintf(
					'V4 smart return for order "%1$s" returned no barcode number; the email will carry the printcode image without the text fallback.',
					$order_number
				)
			);
		}

		return array(
			'api_version' => 'v4',
			'barcode'     => $barcode,
			'content'     => Response_Mapper::decode_content( $chosen ),
			'output_type' => $output_type,
			'mime'        => self::PRINTCODE_MIME_TYPES[ $output_type ],
		);
	}

	/**
	 * Send the return/generate request, logging and converting any failure.
	 *
	 * The converted message is merchant-safe and one of its variants tells the
	 * reader to check these very logs, so the original SDK failure has to be
	 * written here — nothing else reads getPrevious(). The order number is the
	 * merchant's own reference: it makes the entry traceable to an order and is
	 * store-internal rather than customer-identifying.
	 *
	 * @param ReturnShipmentRequest $request      Built return/generate request.
	 * @param string                $order_number Merchant order number, used in log entries.
	 *
	 * @return ReturnShipmentResponseInterface
	 *
	 * @throws \Exception The converted, merchant-facing error.
	 */
	protected function generate_smart_return( ReturnShipmentRequest $request, string $order_number ): ReturnShipmentResponseInterface {
		try {
			return $this->build_client()->returns()->generateReturn( $request );
		} catch ( \Throwable $exception ) {
			$error = Exception_Converter::convert( $exception );

			$this->logger->error(
				sprintf(
					'V4 smart return creation failed for order "%1$s": %2$s (cause: %3$s: %4$s)',
					$order_number,
					$error->getMessage(),
					get_class( $exception ),
					$exception->getMessage()
				)
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception_Converter returns an already-escaped, translated message.
			throw $error;
		}
	}

	/**
	 * Build the legacy fallback service for a non-NL order.
	 *
	 * @return Smart_Returns_Service_Interface
	 */
	protected function legacy_service(): Smart_Returns_Service_Interface {
		return new Legacy_Smart_Returns_Service();
	}

	/**
	 * Parse the order into the legacy Smart Returns item info.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return Item_Info
	 */
	protected function item_info( \WC_Order $order ): Item_Info {
		return new Item_Info( $order );
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
		return null !== $label->outputType ? strtolower( $label->outputType->value ) : 'png';
	}

	/**
	 * Describe a label for a log entry: its labelType, or its output type when
	 * the response does not name the document.
	 *
	 * @param Label $label Label object from the response.
	 * @return string
	 */
	private static function describe_label( Label $label ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		if ( null !== $label->labelType && '' !== $label->labelType ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
			return $label->labelType;
		}

		return self::label_output_type( $label );
	}

	/**
	 * Build the configured SDK client for the current environment.
	 *
	 * @return PostnlClientInterface
	 */
	private function build_client(): PostnlClientInterface {
		return $this->client_factory->build( $this->v4_key, (bool) Settings::get_instance()->is_sandbox() );
	}
}
