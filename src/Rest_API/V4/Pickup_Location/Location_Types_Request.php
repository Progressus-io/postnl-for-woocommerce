<?php
/**
 * Class Rest_API\V4\Pickup_Location\Location_Types_Request file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Pickup_Location
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Pickup_Location;

use Postnl\Sdk\Exception\Runtime\LogicSdkException;
use Postnl\Sdk\Service\PickupLocations\Request\PickUpNearAddressRequestInterface;
use Postnl\Sdk\Service\PickupLocations\V4\Request\PickUpNearAddressRequest;
use Postnl\Sdk\Support\Contracts\PayloadMapperInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Location_Types_Request
 *
 * A near-address request that names the location type as `locationTypes`, a list
 * of lower-case values, instead of the single `locationType` SDK 3.0.0 sends.
 *
 * The published contract documents `locationType: "Retail"`, but the sandbox
 * rejects that field as "not part of API contract" and requires
 * `locationTypes: ["retail"]`. Service retries with this shape when the documented
 * one is refused. Drop this class once the SDK and the API agree again.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Pickup_Location
 */
class Location_Types_Request implements PickUpNearAddressRequestInterface {

	/**
	 * `locationTypes` value for each SDK `locationType` value.
	 */
	private const LOCATION_TYPES = array(
		'Retail'       => 'retail',
		'ParcelLocker' => 'parcel_locker',
	);

	/**
	 * The SDK request this one re-shapes.
	 *
	 * @var PickUpNearAddressRequest
	 */
	private $request;

	/**
	 * Location_Types_Request constructor.
	 *
	 * @param PickUpNearAddressRequest $request SDK request to re-shape.
	 */
	public function __construct( PickUpNearAddressRequest $request ) {
		$this->request = $request;
	}

	/**
	 * Build from the SDK request's own array form.
	 *
	 * @param array                       $data   Request data keyed as the SDK request expects.
	 * @param PayloadMapperInterface|null $mapper Payload mapper; the SDK default when omitted.
	 * @return static
	 */
	public static function fromArray( array $data, ?PayloadMapperInterface $mapper = null ): static {
		return new static( PickUpNearAddressRequest::fromArray( $data, $mapper ) );
	}

	/**
	 * The SDK request's payload with `locationType` replaced by `locationTypes`.
	 *
	 * @param PayloadMapperInterface|null $mapper Payload mapper; the SDK default when omitted.
	 * @return array
	 */
	public function toArray( ?PayloadMapperInterface $mapper = null ): array {
		$payload = $this->request->toArray( $mapper );
		$type    = $payload['locationType'] ?? null;

		unset( $payload['locationType'] );

		if ( isset( self::LOCATION_TYPES[ $type ] ) ) {
			$payload['locationTypes'] = array( self::LOCATION_TYPES[ $type ] );
		}

		return $payload;
	}

	/**
	 * Refuse direct JSON encoding, as every SDK payload does.
	 *
	 * @throws LogicSdkException Always.
	 */
	public function jsonSerialize(): never {
		throw LogicSdkException::create( 'Encode $payload->toArray( $mapper ) instead.' );
	}
}
