<?php
/**
 * Class Rest_API\Service_Factory file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API;

use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Postcode_Check_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Return_Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Barcode_Service as Legacy_Barcode_Service;
use PostNLWooCommerce\Rest_API\Legacy\Checkout_Service as Legacy_Checkout_Service;
use PostNLWooCommerce\Rest_API\Legacy\Label_Service as Legacy_Label_Service;
use PostNLWooCommerce\Rest_API\Legacy\Letterbox_Service as Legacy_Letterbox_Service;
use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check_Service as Legacy_Postcode_Check_Service;
use PostNLWooCommerce\Rest_API\Legacy\Return_Label_Service as Legacy_Return_Label_Service;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns_Service as Legacy_Smart_Returns_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Factory
 *
 * Single factory that resolves the correct service implementation per flow.
 * Every method returns the Legacy service unless all three conditions hold:
 *   (a) a V4 API key is present on the settings object,
 *   (b) Router::sdk_enabled_for() returns true for the flow, and
 *   (c) a V4 service has been registered for that flow via inject_v4_service().
 *
 * postcode_check_service() is permanently wired to Legacy because postcode_check
 * is intentionally absent from Router::SUPPORTED_FLOWS.
 *
 * The 'shipment_and_return' SUPPORTED_FLOW has no factory method yet: no service
 * wrapper or interface exists for it on this branch. The S&R method lands with
 * its interface when that flow is migrated.
 *
 * The 'checkout' SUPPORTED_FLOW is never queried directly; the checkout endpoint
 * is split here into the 'timeframe' and 'pickup_location' flows, both backed by
 * the shared Legacy\Checkout_Service.
 *
 * Legacy services are created lazily on first access and memoised so repeated
 * calls within a request are cheap.
 *
 * @since   5.9.6
 * @package PostNLWooCommerce\Rest_API
 */
class Service_Factory {

	/**
	 * Plugin settings instance used to detect a V4 API key.
	 * Null when no settings object is available yet (e.g. early bootstrap).
	 *
	 * @var object|null
	 */
	private $settings;

	/**
	 * V4 service instances keyed by flow name.
	 * Populated via inject_v4_service(); future SDK implementations are registered here.
	 * Also used in unit tests to inject test doubles.
	 *
	 * @var array<string, object>
	 */
	private $v4_services = array();

	/**
	 * Memoised service instances keyed by flow name (or 'checkout' for the shared
	 * Legacy\Checkout_Service used by both timeframe and pickup_location).
	 * May be pre-seeded via set_legacy_service() in unit tests to avoid
	 * instantiating Order\Base-derived classes that require WooCommerce.
	 *
	 * @var array<string, object>
	 */
	private $legacy_memos = array();

	/**
	 * Service_Factory constructor.
	 *
	 * @param object|null $settings Plugin settings instance, or null when unavailable.
	 */
	public function __construct( $settings = null ) {
		$this->settings = $settings;
	}

	/**
	 * Register a V4 service for a specific flow.
	 *
	 * Called during V4 wiring once SDK service classes exist.
	 * Also used in unit tests to inject lightweight test doubles.
	 *
	 * @param string $flow    Flow identifier (should be in Router::SUPPORTED_FLOWS).
	 * @param object $service V4 service instance implementing the flow's interface.
	 * @return void
	 */
	public function inject_v4_service( string $flow, object $service ): void {
		$this->v4_services[ $flow ] = $service;
	}

	/**
	 * Pre-seed the memoisation store with a ready-built service instance.
	 *
	 * Used exclusively in unit tests to avoid instantiating Legacy\Label_Service,
	 * Legacy\Letterbox_Service, and Legacy\Return_Label_Service, which extend
	 * Order\Base and require WooCommerce constants and Settings::get_instance()
	 * in their constructors.  Not intended for production use.
	 *
	 * @param string $flow    Flow identifier.
	 * @param object $service Service instance implementing the flow's interface.
	 * @return void
	 */
	public function set_legacy_service( string $flow, object $service ): void {
		$this->legacy_memos[ $flow ] = $service;
	}

	/**
	 * Return the barcode service for the current configuration.
	 *
	 * @return Barcode_Service_Interface
	 */
	public function barcode_service(): Barcode_Service_Interface {
		if ( $this->should_use_v4( 'barcode' ) && isset( $this->v4_services['barcode'] ) ) {
			return $this->v4_services['barcode'];
		}
		if ( ! isset( $this->legacy_memos['barcode'] ) ) {
			$this->legacy_memos['barcode'] = new Legacy_Barcode_Service();
		}
		return $this->legacy_memos['barcode'];
	}

	/**
	 * Whether the barcode is issued by the label response instead of a standalone
	 * barcode request.
	 *
	 * True only when a V4 label service is actually resolvable for this request —
	 * i.e. the label flow is routed to V4 and a V4 label service has been registered.
	 * On V4 the label call auto-issues the barcode (PostNL confirmed 2026-05-21), so
	 * Order\Base skips the barcode prefetch, generates the label first, and harvests
	 * the barcode(s) from the label response — barcode_service() is never called on
	 * that path.
	 *
	 * Gating on the V4 label service (not merely the barcode flag) keeps the reorder
	 * dormant until the V4 label service exists: barcode_service() therefore stays on
	 * Legacy prefetch until the label is genuinely V4, so a Legacy label is never fed
	 * a missing barcode and the V4 no-op is never asked to prefetch one.
	 *
	 * @return bool
	 */
	public function barcode_from_label(): bool {
		return $this->should_use_v4( 'label' ) && isset( $this->v4_services['label'] );
	}

	/**
	 * Return the timeframe (delivery options) service for the current configuration.
	 *
	 * @return Timeframe_Service_Interface
	 */
	public function timeframe_service(): Timeframe_Service_Interface {
		if ( $this->should_use_v4( 'timeframe' ) && isset( $this->v4_services['timeframe'] ) ) {
			return $this->v4_services['timeframe'];
		}
		return $this->legacy_checkout_service();
	}

	/**
	 * Return the pickup-location service for the current configuration.
	 *
	 * @return Pickup_Location_Service_Interface
	 */
	public function pickup_location_service(): Pickup_Location_Service_Interface {
		if ( $this->should_use_v4( 'pickup_location' ) && isset( $this->v4_services['pickup_location'] ) ) {
			return $this->v4_services['pickup_location'];
		}
		return $this->legacy_checkout_service();
	}

	/**
	 * Return the outbound shipping label service for the current configuration.
	 *
	 * @return Label_Service_Interface
	 */
	public function label_service(): Label_Service_Interface {
		if ( $this->should_use_v4( 'label' ) && isset( $this->v4_services['label'] ) ) {
			return $this->v4_services['label'];
		}
		if ( ! isset( $this->legacy_memos['label'] ) ) {
			$this->legacy_memos['label'] = new Legacy_Label_Service();
		}
		return $this->legacy_memos['label'];
	}

	/**
	 * Return the letterbox label service for the current configuration.
	 *
	 * @return Label_Service_Interface
	 */
	public function letterbox_service(): Label_Service_Interface {
		if ( $this->should_use_v4( 'letterbox' ) && isset( $this->v4_services['letterbox'] ) ) {
			return $this->v4_services['letterbox'];
		}
		if ( ! isset( $this->legacy_memos['letterbox'] ) ) {
			$this->legacy_memos['letterbox'] = new Legacy_Letterbox_Service();
		}
		return $this->legacy_memos['letterbox'];
	}

	/**
	 * Return the return-label service for the current configuration.
	 *
	 * @return Return_Label_Service_Interface
	 */
	public function return_label_service(): Return_Label_Service_Interface {
		if ( $this->should_use_v4( 'return_label' ) && isset( $this->v4_services['return_label'] ) ) {
			return $this->v4_services['return_label'];
		}
		if ( ! isset( $this->legacy_memos['return_label'] ) ) {
			$this->legacy_memos['return_label'] = new Legacy_Return_Label_Service();
		}
		return $this->legacy_memos['return_label'];
	}

	/**
	 * Return the postcode-check service.
	 *
	 * Always returns Legacy — postcode_check is not in Router::SUPPORTED_FLOWS
	 * and is not planned for V4 routing.
	 *
	 * @return Postcode_Check_Service_Interface
	 */
	public function postcode_check_service(): Postcode_Check_Service_Interface {
		if ( ! isset( $this->legacy_memos['postcode_check'] ) ) {
			$this->legacy_memos['postcode_check'] = new Legacy_Postcode_Check_Service();
		}
		return $this->legacy_memos['postcode_check'];
	}

	/**
	 * Return the smart-returns service for the current configuration.
	 *
	 * @return Smart_Returns_Service_Interface
	 */
	public function smart_returns_service(): Smart_Returns_Service_Interface {
		if ( $this->should_use_v4( 'smart_returns' ) && isset( $this->v4_services['smart_returns'] ) ) {
			return $this->v4_services['smart_returns'];
		}
		if ( ! isset( $this->legacy_memos['smart_returns'] ) ) {
			$this->legacy_memos['smart_returns'] = new Legacy_Smart_Returns_Service();
		}
		return $this->legacy_memos['smart_returns'];
	}

	/**
	 * Return the shared Legacy\Checkout_Service instance.
	 *
	 * Both timeframe_service() and pickup_location_service() delegate here because
	 * the PostNL checkout endpoint returns delivery options and pickup locations in
	 * a single response — one service instance covers both flows.
	 *
	 * @return Legacy_Checkout_Service
	 */
	private function legacy_checkout_service(): Legacy_Checkout_Service {
		if ( ! isset( $this->legacy_memos['checkout'] ) ) {
			$this->legacy_memos['checkout'] = new Legacy_Checkout_Service();
		}
		return $this->legacy_memos['checkout'];
	}

	/**
	 * Return whether V4 routing should be used for the given flow.
	 *
	 * Short-circuits on the key check so Router (and its filter) is never consulted
	 * when no V4 key is configured.
	 *
	 * TODO (task 8 spec): product-coded flows (barcode, label, letterbox,
	 * return_label, smart_returns) must additionally gate on
	 * V4_Mapper::has_v4_equivalent(...). V4_Mapper does not exist on this branch,
	 * so the gate is deferred and wired in alongside the V4 services that need it.
	 *
	 * @param string $flow Flow identifier.
	 * @return bool
	 */
	private function should_use_v4( string $flow ): bool {
		return $this->has_v4_key() && Router::sdk_enabled_for( $flow );
	}

	/**
	 * Return whether a non-empty V4 API key is available on the settings object.
	 *
	 * Returns false when: no settings object was injected; the settings object does
	 * not yet expose get_v4_api_key() (the V4 key field is in a separate in-progress
	 * PR); or the key string is empty or whitespace-only.
	 *
	 * @return bool
	 */
	private function has_v4_key(): bool {
		if ( null === $this->settings ) {
			return false;
		}
		if ( ! method_exists( $this->settings, 'get_v4_api_key' ) ) {
			return false;
		}
		$key = $this->settings->get_v4_api_key();
		return is_string( $key ) && '' !== trim( $key );
	}
}
