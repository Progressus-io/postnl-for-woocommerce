# PostNL V4 SDK Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the PostNL for WooCommerce plugin from hand-rolled `wp_remote_request` API calls to the `postnl/api-client-sdk` V4 SDK, one flow at a time, with old-client fallback preserved until each flow passes staging parity.

**Architecture:** A `ClientFactory` builds a `PostnlClientInterface` from plugin settings. A static `Router` decides per-flow whether the SDK or old client runs — all flows are off by default. Each `Rest_API/*/Client.php` overrides `send_request()` to check `Router::use_sdk_for()` first; when off, it falls through to `parent::send_request()` (the old HTTP call). Old clients are never removed until individual staging sign-off. Checkout is a special case: the old single-endpoint call is replaced by two SDK calls (TimeFrame + Locations) aggregated by Task 5.

**Tech Stack:** PHP 8.2+, `postnl/api-client-sdk` via Private Packagist (`https://repo.packagist.com/postnl/`), WordPress/WooCommerce PSR-4 namespace `PostNLWooCommerce\`, PHPCS for linting (`composer check-php`), no PHPUnit — PHP testing is manual staging QA.

---

## ⚠ Scope Note

Tasks 6 (Shipping + Letterbox Labels), 8 (Smart Returns), and 9 (activatereturn) are **externally blocked**. Their implementation structure is documented here so work begins immediately when blockers clear — but no code is written for them until the blocker is resolved in writing. Tasks 0–2 and 7 have no external blockers and can begin right away.

---

## ⚠ Verify These Before Writing Any Code

Open the installed SDK source at `postnl-sdk-audit/vendor/postnl/api-client-sdk/src/` and confirm:

1. **`Client/Client.php`** — Does `checkout()` exist? Or is it `singleTimeframe()` / `multipleTimeframes()`? Does `locations()` exist? Or is it `addressLocations()` / `coordinateLocations()`?
2. **`Service/Barcode/V4/Request/BarcodeRequest.php`** — Is the constructor param `serieStart` or `seriesStart`?
3. **`Enums/Payload/LabelType.php`** — List all enum cases.
4. **`Service/ServiceContext.php`** — Does a `$cache` property exist?
5. **`Service/Checkout/V4/Request/`** — Do `SingleServiceTimeframeRequest` and `MultipleServicesTimeframeRequest` live here, or under `Service/SingleServiceTimeframe/V4/Request/`?
6. **`Client/ClientBuilder.php`** — Is the retry builder method `withRetryPolicy()` or `withRetry()`?

Record findings in `docs/postnl-v4-migration/postnl-v4-sdk-api-reference.md §11` before starting any task. Every `// VERIFY:` comment in the code below refers to one of these points.

---

## File Structure

### New files

| File | Purpose |
|---|---|
| `src/SDK/ClientFactory.php` | Builds `PostnlClientInterface` from plugin settings |
| `src/SDK/Router.php` | Per-flow SDK/old-client switch; all flows off by default |
| `src/SDK/SdkExceptionConverter.php` | Converts `PostnlExceptionInterface` → `\Exception` |
| `src/SDK/Extension/ActivateReturnExtension.php` | Task 9 Option A only |

### Modified files

| File | Change |
|---|---|
| `composer.json` | Add SDK dependency + Private Packagist repo |
| `auth.json` (gitignored) | Private Packagist read-only token |
| `postnl-for-woocommerce.php` | PHP 8.2 admin notice guard |
| `src/Logger.php` | Strip raw base64 PDF binary from log output |
| `src/Rest_API/Barcode/Client.php` | Override `send_request()`; SDK path via `barcode()` |
| `src/Rest_API/Checkout/Client.php` | Override `send_request()`; SDK paths for TimeFrame + Locations |
| `src/Rest_API/Return_Label/Client.php` | Override `send_request()`; SDK path via `returnShipment()` |
| `src/Rest_API/Shipping/Client.php` | Task 6 (blocked): SDK path via `shipmentDelivery()` |
| `src/Rest_API/Letterbox/Client.php` | Task 6 (blocked): SDK path via `shipmentDelivery()` |
| `src/Rest_API/Shipment_and_Return/Client.php` | Task 9: extension or retention comment |

---

## Task 0 — Composer Setup + PHP Guard

**Status:** Required before all other tasks
**Files:** `composer.json`, `auth.json`, `postnl-for-woocommerce.php`

**Context:** The SDK requires PHP ≥ 8.2. The plugin currently declares PHP ≥ 7.4. This plan adds a runtime admin notice on PHP < 8.2 so the plugin continues to load on older PHP — SDK calls are simply never reached. Bumping the plugin's declared PHP minimum is a separate release decision made outside this plan.

- [ ] **Step 1: Confirm `auth.json` is gitignored**

```bash
grep -n "auth.json" .gitignore
```

Expected: `auth.json` appears. If it does not, add it before continuing.

- [ ] **Step 2: Create `auth.json` with read-only Packagist token**

Create `auth.json` in the plugin root (this file must never be committed):

```json
{
    "bearer": {
        "repo.packagist.com": "REPLACE_WITH_READONLY_TOKEN"
    }
}
```

Replace `REPLACE_WITH_READONLY_TOKEN` with the token PostNL provides for `repo.packagist.com`.

- [ ] **Step 3: Add SDK to `composer.json`**

Read `composer.json`, then add the `repositories` entry and the `require` line. The result must include:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.packagist.com/postnl/"
        }
    ],
    "require": {
        "postnl/api-client-sdk": "^1.0"
    }
}
```

Pin to `^1.0`. Do not use `dev-main` or `*`. Confirm the exact published version with PostNL before installing.

- [ ] **Step 4: Install and verify no autoload conflicts**

```bash
composer install
php -r "require 'vendor/autoload.php'; echo 'OK' . PHP_EOL;"
```

Expected: `OK` with no errors. If there are namespace conflicts with `clegginabox/pdf-merger`, open `composer.json` and check for conflicting autoload entries.

- [ ] **Step 5: Add PHP 8.2 admin notice to `postnl-for-woocommerce.php`**

After the plugin header block and before the bootstrap require, add:

```php
add_action(
	'admin_notices',
	function () {
		if ( version_compare( PHP_VERSION, '8.2', '>=' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: current PHP version */
			esc_html__( 'PostNL for WooCommerce: V4 SDK features require PHP 8.2 or higher. Your server runs PHP %s. SDK-based flows are disabled until PHP is upgraded.', 'postnl-for-woocommerce' ),
			esc_html( PHP_VERSION )
		);
		echo '</p></div>';
	}
);
```

- [ ] **Step 6: Run PHPCS**

```bash
composer check-php
```

Expected: no new errors.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock postnl-for-woocommerce.php
git commit -m "feat: add postnl/api-client-sdk dependency and PHP 8.2 admin notice guard"
```

(Do not commit `auth.json` — it must remain gitignored.)

---

## Task 1 — SDK ClientFactory + Router + Logger

**Status:** Ready | **Depends on:** Task 0
**Files:** `src/SDK/ClientFactory.php` (new), `src/SDK/Router.php` (new), `src/SDK/SdkExceptionConverter.php` (new), `src/Logger.php` (modify)

**Context:** `ClientFactory` builds a `PostnlClientInterface` from plugin settings. `Router` decides per-flow which path runs — all flows off by default. `SdkExceptionConverter` converts SDK exceptions to `\Exception` so existing callers see the same error surface. Logger gets a new guard for raw base64 PDF binary that SDK responses may include. No API calls are made in this task.

- [ ] **Step 1: Verify PSR-4 autoload covers `src/SDK/`**

```bash
grep -n "PostNLWooCommerce" composer.json
```

Expected: `"PostNLWooCommerce\\\\": "src/"` is present. The new `src/SDK/` directory is covered by this mapping — no change needed.

- [ ] **Step 2: Create `src/SDK/ClientFactory.php`**

```php
<?php
/**
 * Class SDK\ClientFactory file.
 *
 * @package PostNLWooCommerce\SDK
 */

namespace PostNLWooCommerce\SDK;

use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\Client\Postnl as PostnlSdk;
use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientFactory
 *
 * @package PostNLWooCommerce\SDK
 */
class ClientFactory {

	/**
	 * Build a PostNL SDK client from plugin settings.
	 *
	 * @throws \RuntimeException When PHP < 8.2.
	 * @return PostnlClientInterface
	 */
	public function get_client(): PostnlClientInterface {
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			throw new \RuntimeException( 'PostNL SDK requires PHP 8.2+.' );
		}

		$settings = Settings::get_instance();
		$api_key  = $settings->is_sandbox()
			? $settings->get_api_key_sandbox()
			: $settings->get_api_key();

		$auth = Auth::apiKey( $api_key );

		return $settings->is_sandbox()
			? PostnlSdk::sandboxClient( $auth )
			: PostnlSdk::client( $auth );
	}
}
```

- [ ] **Step 3: Create `src/SDK/Router.php`**

```php
<?php
/**
 * Class SDK\Router file.
 *
 * @package PostNLWooCommerce\SDK
 */

namespace PostNLWooCommerce\SDK;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Router
 *
 * @package PostNLWooCommerce\SDK
 */
class Router {

	/**
	 * Flows where the SDK path is enabled.
	 *
	 * Flow keys: 'barcode', 'timeframe', 'locations', 'return_labels',
	 *            'shipping_labels', 'smart_returns', 'activatereturn'.
	 *
	 * @var string[]
	 */
	private static array $enabled = array();

	/**
	 * Whether to use the SDK for the given flow.
	 *
	 * @param string $flow Flow name.
	 */
	public static function use_sdk_for( string $flow ): bool {
		return in_array( $flow, self::$enabled, true );
	}

	/**
	 * Enable the SDK path for a flow. Call after staging parity is confirmed.
	 *
	 * @param string $flow Flow name.
	 */
	public static function enable( string $flow ): void {
		if ( ! self::use_sdk_for( $flow ) ) {
			self::$enabled[] = $flow;
		}
	}

	/**
	 * Disable the SDK path for a flow, restoring old-client behavior.
	 *
	 * @param string $flow Flow name.
	 */
	public static function disable( string $flow ): void {
		self::$enabled = array_values(
			array_filter(
				self::$enabled,
				static function ( string $f ) use ( $flow ): bool {
					return $f !== $flow;
				}
			)
		);
	}
}
```

- [ ] **Step 4: Create `src/SDK/SdkExceptionConverter.php`**

```php
<?php
/**
 * Class SDK\SdkExceptionConverter file.
 *
 * @package PostNLWooCommerce\SDK
 */

namespace PostNLWooCommerce\SDK;

use Postnl\Sdk\Exception\PostnlExceptionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SdkExceptionConverter
 *
 * @package PostNLWooCommerce\SDK
 */
class SdkExceptionConverter {

	/**
	 * Convert a PostNL SDK exception to a standard \Exception.
	 *
	 * @param PostnlExceptionInterface $e SDK exception.
	 * @return \Exception
	 */
	public static function convert( PostnlExceptionInterface $e ): \Exception {
		return new \Exception( $e->getMessage(), 0, $e );
	}
}
```

- [ ] **Step 5: Add base64 PDF binary guard to `src/Logger.php`**

Read `src/Logger.php`. In the `write()` method, after the `$message = $this->check_pdf_content( $message );` line, add one more call:

```php
$message = $this->check_pdf_content( $message );
$message = $this->strip_binary_content( $message ); // add this line
```

Then add this private method to the class after `check_pdf_content()`:

```php
/**
 * Strip raw binary label content that the SDK may surface as a string.
 *
 * @param mixed $message Log message.
 * @return mixed
 */
private function strip_binary_content( $message ) {
	if ( ! is_string( $message ) ) {
		return $message;
	}
	// JVBERi0 is base64 for %PDF — SDK label content arrives this way.
	if ( 0 === strpos( $message, 'JVBERi0' ) || 0 === strpos( $message, '%PDF' ) ) {
		return '[label binary redacted]';
	}
	return $message;
}
```

- [ ] **Step 6: Run PHPCS**

```bash
composer check-php
```

Expected: no errors in `src/SDK/` or `src/Logger.php`.

- [ ] **Step 7: Smoke-test that classes load**

```bash
php -r "
define('ABSPATH', realpath('../../../..') . '/');
require 'vendor/autoload.php';
echo class_exists('PostNLWooCommerce\\SDK\\ClientFactory') ? 'ClientFactory OK' : 'FAIL';
echo PHP_EOL;
echo class_exists('PostNLWooCommerce\\SDK\\Router') ? 'Router OK' : 'FAIL';
echo PHP_EOL;
"
```

Expected:
```
ClientFactory OK
Router OK
```

- [ ] **Step 8: Commit**

```bash
git add src/SDK/ClientFactory.php src/SDK/Router.php src/SDK/SdkExceptionConverter.php src/Logger.php
git commit -m "feat: add SDK ClientFactory, Router, SdkExceptionConverter; strip binary from logger"
```

---

## Task 2 — Barcode (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Files:** `src/Rest_API/Barcode/Client.php` (modify)

**Context:** `Barcode\Client` calls `GET /shipment/v1_1/barcode` via the inherited `Base::send_request()`. This task overrides `send_request()` to check `Router::use_sdk_for('barcode')` first. When on, it calls `$client->barcode()->generateBarcode()`. The SDK path is off by default; flipping `Router::enable('barcode')` activates it.

**Before starting — verify in installed SDK:**
- `src/Service/Barcode/V4/Request/BarcodeRequest.php` — constructor param name: `serieStart` or `seriesStart`?
- `src/Service/Barcode/V4/Response/GenerateBarcodeResponse.php` — how to extract the barcode string (e.g., `->barcodes()->first()->barcode()`).
- `src/Order/Base.php` — search for the call to `Barcode\Client::send_request()` and the key it reads from the response array (e.g., `$response['Barcode']`). The SDK path must return the same array structure.

The existing `Item_Info` has `$this->item_info->query_args['serie']` as a range string like `'000000000-999999999'`. Split it to get `serieStart` and `serieEnd`.

- [ ] **Step 1: Override `send_request()` in `src/Rest_API/Barcode/Client.php`**

Read the file first. Replace the entire file content with:

```php
<?php
/**
 * Class Rest_API\Barcode\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use Postnl\Sdk\Exception\PostnlExceptionInterface;
use Postnl\Sdk\RequestData\V4\Barcode\BarcodeRequest;
use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\SDK\ClientFactory;
use PostNLWooCommerce\SDK\Router;
use PostNLWooCommerce\SDK\SdkExceptionConverter;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */
class Client extends Base {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/shipment/v1_1/barcode';

	/**
	 * PostnL API Method.
	 *
	 * @var string
	 */
	public $method = 'GET';

	/**
	 * Function for composing API request in the URL for GET request.
	 *
	 * @return array
	 */
	public function compose_url_params() {
		$range = Utils::get_barcode_range( $this->item_info->query_args['barcode_type'], $this->item_info->query_args['globalpack_customer_code'] );

		return array(
			'Type'           => $this->item_info->query_args['barcode_type'],
			'Serie'          => $this->item_info->query_args['serie'],
			'CustomerCode'   => $this->item_info->query_args['customer_code'],
			'CustomerNumber' => $this->item_info->query_args['customer_num'],
			'Range'          => $range,
		);
	}

	/**
	 * Send API request — SDK path when enabled, old client otherwise.
	 *
	 * @throws \Exception On API or SDK error.
	 * @return array
	 */
	public function send_request() {
		if ( ! Router::use_sdk_for( 'barcode' ) ) {
			return parent::send_request();
		}

		$this->logger->write( 'Barcode: using SDK path.' );

		try {
			return $this->send_sdk_barcode_request();
		} catch ( PostnlExceptionInterface $e ) {
			$this->logger->write( 'Barcode SDK error: ' . $e->getMessage() );
			throw SdkExceptionConverter::convert( $e );
		}
	}

	/**
	 * Call the V4 barcode SDK service and return an array matching the old client response shape.
	 *
	 * VERIFY: check src/Order/Base.php to confirm the array key it reads from this response
	 * (e.g., $response['Barcode']). Adjust the return array below to match exactly.
	 *
	 * @throws PostnlExceptionInterface On SDK error.
	 * @return array
	 */
	private function send_sdk_barcode_request(): array {
		$serie_parts = explode( '-', $this->item_info->query_args['serie'], 2 );
		$serie_start = $serie_parts[0] ?? '000000000';
		$serie_end   = $serie_parts[1] ?? '999999999';

		// VERIFY: confirm constructor param names against BarcodeRequest.php.
		// SDK docs show serieStart/serieEnd; fromArray() may use seriesStart/seriesEnd.
		$request = new BarcodeRequest(
			customerNumber:   $this->item_info->query_args['customer_num'],
			customerCode:     $this->item_info->query_args['customer_code'],
			serieStart:       $serie_start,
			serieEnd:         $serie_end,
			numberOfBarcodes: 1,
		);

		$client   = ( new ClientFactory() )->get_client();
		$response = $client->barcode()->generateBarcode( $request );

		// VERIFY: confirm the collection accessor on GenerateBarcodeResponse.
		// Adjust ->barcodes()->first()->barcode() if the API differs.
		$barcode_string = $response->barcodes()->first()->barcode();

		// Return the same shape that Order\Base reads from the old client response.
		// Confirm the key name ('Barcode') against src/Order/Base.php before shipping.
		return array( 'Barcode' => $barcode_string );
	}
}
```

- [ ] **Step 2: Run PHPCS**

```bash
composer check-php
```

Expected: no errors.

- [ ] **Step 3: Verify old path still works (SDK off)**

In a staging WordPress environment with the plugin active (SDK path is off by default):
1. Open any order in WP Admin → PostNL meta box.
2. Generate a label. Confirm a barcode is created.
3. Check WC logs: the line "Barcode: using SDK path." must **not** appear.

- [ ] **Step 4: Enable SDK path and test parity**

Temporarily add to `postnl-for-woocommerce.php` (remove before committing):

```php
add_action(
	'init',
	static function () {
		\PostNLWooCommerce\SDK\Router::enable( 'barcode' );
	}
);
```

1. Reload admin, open an order, generate a label.
2. Check WC log: "Barcode: using SDK path." must appear.
3. Verify the barcode string in `_postnl_order_metadata` matches the format from the old client (e.g., `3SDEVC...` for NL domestic).
4. Test international (EU/ROW) orders if applicable — verify barcode format.

Expected: identical barcode format between SDK and old client for the same inputs.

- [ ] **Step 5: Remove the temporary `Router::enable()` line**

- [ ] **Step 6: Commit**

```bash
git add src/Rest_API/Barcode/Client.php
git commit -m "feat: add SDK barcode path to Barcode\Client (off by default)"
```

---

## Task 3 — TimeFrame / Delivery Dates (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Files:** `src/Rest_API/Checkout/Client.php` (modify)

**Context:** The current `Checkout\Client` calls `POST /shipment/v1/checkout`, which returns delivery days and pickup points combined. This task adds the SDK TimeFrame path (delivery days only). Router key is `'timeframe'`. Pickup points are untouched in this task.

**Before starting:**
1. **Verify `Client::checkout()` exists** in `src/Client/Client.php`. If the method is `singleTimeframe()` / `multipleTimeframes()` instead, update every call below.
2. **Verify `SingleServiceTimeframeRequest` namespace.** SDK docs show `Postnl\Sdk\Service\Checkout\V4\Request\...`; code may show `Postnl\Sdk\Service\SingleServiceTimeframe\V4\Request\...`. Update the `use` statement.
3. **Read `src/Frontend/Delivery_Day.php`** — run the grep in Step 1 to find the exact array keys it reads from the checkout response. The mapper in Step 2 must return those keys.
4. **Read `src/Rest_API/Checkout/Item_Info.php`** — confirm what fields are available in `$this->item_info->body`, `$this->item_info->receiver`, and `$this->item_info->shipper`.

- [ ] **Step 1: Find the response keys `Frontend\Delivery_Day` reads**

```bash
grep -n "DeliveryOptions\|TimeFrames\|ReasonNoTimeframe\|Timeframes\|timeframe\|Date\|From\|To\|Options" src/Frontend/Delivery_Day.php | head -40
```

Write down every array key. You will use them in `map_timeframe_response()` below.

- [ ] **Step 2: Add SDK TimeFrame path to `src/Rest_API/Checkout/Client.php`**

Read the file. Keep all existing methods (`compose_body_request()`, `get_cutoff_times()`, `get_checkout_options()`) unchanged. Add the imports and new methods shown below to the existing class:

```php
// Add these use statements at the top with the existing ones:
use Postnl\Sdk\Exception\PostnlExceptionInterface;
// VERIFY namespace — may be Postnl\Sdk\Service\SingleServiceTimeframe\V4\Request\... in code:
use Postnl\Sdk\Service\Checkout\V4\Request\SingleServiceTimeframeRequest;
use Postnl\Sdk\Service\Checkout\V4\Request\MultipleServicesTimeframeRequest;
use PostNLWooCommerce\SDK\ClientFactory;
use PostNLWooCommerce\SDK\Router;
use PostNLWooCommerce\SDK\SdkExceptionConverter;
use PostNLWooCommerce\Shipping_Method\Settings;
```

Add these methods inside the class after `get_checkout_options()`:

```php
/**
 * Send API request — SDK TimeFrame path when enabled, old client otherwise.
 *
 * @throws \Exception On API or SDK error.
 * @return array
 */
public function send_request() {
    if ( ! Router::use_sdk_for( 'timeframe' ) ) {
        return parent::send_request();
    }

    $this->logger->write( 'Checkout: using SDK TimeFrame path.' );

    try {
        return $this->send_sdk_timeframe_request();
    } catch ( PostnlExceptionInterface $e ) {
        $this->logger->write( 'TimeFrame SDK error: ' . $e->getMessage() );
        throw SdkExceptionConverter::convert( $e );
    }
}

/**
 * Call V4 TimeFrame SDK service; return array shaped for Frontend\Delivery_Day.
 *
 * Pickup options are empty in this response — Task 4 adds them.
 *
 * @throws PostnlExceptionInterface On SDK error.
 * @return array
 */
private function send_sdk_timeframe_request(): array {
    $settings        = Settings::get_instance();
    $customer_code   = $settings->get_field_value( 'customer_code' );
    $customer_number = $settings->get_field_value( 'customer_num' );

    $receiver_address = array(
        'countryIso'  => $this->item_info->receiver['country'],
        'postalCode'  => $this->item_info->receiver['postcode'],
        'city'        => $this->item_info->receiver['city'],
        'street'      => $this->item_info->receiver['address_1'],
        'houseNumber' => $this->item_info->receiver['address_2'],
    );

    $client       = ( new ClientFactory() )->get_client();
    $use_multiple = $this->item_info->body['morning_delivery_enabled']
        || $this->item_info->body['evening_delivery_enabled'];

    if ( $use_multiple ) {
        $services = array( 'daytime' );
        if ( $this->item_info->body['evening_delivery_enabled'] ) {
            $services[] = 'evening';
        }
        // VERIFY: method name. SDK docs show checkout()->getMultipleServicesTimeframe().
        // If Client has multipleTimeframes()->getTimeframes() instead, update below.
        $request  = new MultipleServicesTimeframeRequest(
            handoverDate:    $this->item_info->body['order_date'],
            numberOfDays:    (int) $this->item_info->body['days'],
            receiverAddress: $receiver_address,
            services:        $services,
            shipmentType:    'parcel',
            customerCode:    $customer_code,
            customerNumber:  $customer_number,
        );
        $response = $client->checkout()->getMultipleServicesTimeframe( $request );
    } else {
        // VERIFY: method name. SDK docs show checkout()->getSingleServiceTimeframe().
        $request  = new SingleServiceTimeframeRequest(
            handoverDate:    $this->item_info->body['order_date'],
            deliveryDays:    (int) $this->item_info->body['days'],
            receiverAddress: $receiver_address,
            service:         'daytime',
            shipmentType:    'parcel',
            customerCode:    $customer_code,
            customerNumber:  $customer_number,
        );
        $response = $client->checkout()->getSingleServiceTimeframe( $request );
    }

    return $this->map_timeframe_response( $response );
}

/**
 * Map SDK TimeFrame response to the shape Frontend\Delivery_Day reads.
 *
 * IMPORTANT: The keys below are illustrative. Replace them with the actual
 * keys found in Step 1. Do not ship this task until keys are confirmed.
 *
 * VERIFY: the collection accessor on the response object (timeFrames(), getTimeframes(), etc.)
 * and the slot accessors (date(), from(), to(), options()) against installed SDK source.
 *
 * @param mixed $response SDK response object.
 * @return array
 */
private function map_timeframe_response( $response ): array {
    $delivery_options = array();

    foreach ( $response->timeFrames() as $slot ) {
        $delivery_options[] = array(
            // Replace these keys with what Delivery_Day.php actually reads (Step 1 grep).
            'Date'       => $slot->date(),
            'Timeframes' => array(
                array(
                    'TimeframeTimeRange' => array(
                        'From' => $slot->from(),
                        'To'   => $slot->to(),
                    ),
                    'Options' => array( $slot->service() ),
                ),
            ),
        );
    }

    // Pickup options are empty here; Task 4 populates them.
    return array(
        'DeliveryOptions' => $delivery_options,
        'PickupOptions'   => array(),
    );
}
```

- [ ] **Step 3: Run PHPCS**

```bash
composer check-php
```

Expected: no errors.

- [ ] **Step 4: Verify old path unchanged**

In staging (SDK off for 'timeframe'):
1. Load classic checkout with a NL address.
2. Confirm delivery-day slots appear as before.
3. Check WC log — "Checkout: using SDK TimeFrame path." must NOT appear.

- [ ] **Step 5: Enable TimeFrame SDK path and test parity**

Add temporarily to `postnl-for-woocommerce.php`:

```php
add_action(
	'init',
	static function () {
		\PostNLWooCommerce\SDK\Router::enable( 'timeframe' );
	}
);
```

1. Classic checkout, NL address: verify delivery-day slots load.
2. Blocks checkout, NL address: verify delivery-day slots load.
3. Select a slot, place order; check `_postnl_order_metadata` saved correctly.
4. Compare slot list to old API output for the same address and settings.
5. Check WC log: "Checkout: using SDK TimeFrame path." must appear.

Expected: parity with old `/shipment/v1/checkout` delivery-day output.

- [ ] **Step 6: Remove temporary `Router::enable()` line**

- [ ] **Step 7: Commit**

```bash
git add src/Rest_API/Checkout/Client.php
git commit -m "feat: add SDK TimeFrame path to Checkout\Client (off by default)"
```

---

## Task 4 — Pickup Locations (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Files:** `src/Rest_API/Checkout/Client.php` (modify further)

**Context:** Add the SDK Locations path alongside the TimeFrame path added in Task 3. Router key is `'locations'` (independent of `'timeframe'`). When only one flag is on, the other half supplements from the old client. When both are on, neither falls back to the old client.

**Before starting:**
1. **Verify `Client::locations()` exists** in `src/Client/Client.php`. If the method is `addressLocations()` instead, update calls below.
2. **Verify `PickUpNearAddressRequest` namespace** in installed SDK.
3. **Read `src/Frontend/Dropoff_Points.php`** — run the grep in Step 1 to find exact response keys.

- [ ] **Step 1: Find the response keys `Frontend\Dropoff_Points` reads**

```bash
grep -n "PickupOptions\|PickupOption\|Company\|Address\|LocationCode\|Distance\|Name\|pickup" src/Frontend/Dropoff_Points.php | head -40
```

Write down every array key. Use them in `map_locations_response()`.

- [ ] **Step 2: Update `send_request()` to handle both Router flags**

Read `src/Rest_API/Checkout/Client.php` (modified in Task 3). Replace the `send_request()` method with this version that handles both `'timeframe'` and `'locations'` flags simultaneously:

```php
// Add to use statements:
use Postnl\Sdk\RequestData\V4\Locations\PickUpNearAddressRequest;
```

```php
/**
 * Send API request — SDK paths when enabled, old client otherwise.
 * Timeframe and Locations flags are independent.
 *
 * @throws \Exception On API or SDK error.
 * @return array
 */
public function send_request() {
    $use_timeframe = Router::use_sdk_for( 'timeframe' );
    $use_locations = Router::use_sdk_for( 'locations' );

    if ( ! $use_timeframe && ! $use_locations ) {
        return parent::send_request();
    }

    $result = array(
        'DeliveryOptions' => array(),
        'PickupOptions'   => array(),
    );

    if ( $use_timeframe ) {
        $this->logger->write( 'Checkout: using SDK TimeFrame path.' );
        try {
            $tf                        = $this->send_sdk_timeframe_request();
            $result['DeliveryOptions'] = $tf['DeliveryOptions'];
        } catch ( PostnlExceptionInterface $e ) {
            $this->logger->write( 'TimeFrame SDK error: ' . $e->getMessage() );
        }
    }

    if ( $use_locations ) {
        $this->logger->write( 'Checkout: using SDK Locations path.' );
        try {
            $result['PickupOptions'] = $this->send_sdk_locations_request();
        } catch ( PostnlExceptionInterface $e ) {
            $this->logger->write( 'Locations SDK error: ' . $e->getMessage() );
        }
    }

    // Supplement from old client for whichever flag is still off.
    if ( ! $use_timeframe || ! $use_locations ) {
        try {
            $old                 = parent::send_request();
            if ( ! $use_timeframe ) {
                $result['DeliveryOptions'] = $old['DeliveryOptions'] ?? array();
            }
            if ( ! $use_locations ) {
                $result['PickupOptions'] = $old['PickupOptions'] ?? array();
            }
        } catch ( \Exception $e ) {
            $this->logger->write( 'Checkout old-client fallback error: ' . $e->getMessage() );
        }
    }

    return $result;
}
```

Add these two methods after `map_timeframe_response()`:

```php
/**
 * Call V4 Locations SDK service; return pickup locations shaped for Frontend\Dropoff_Points.
 *
 * @throws PostnlExceptionInterface On SDK error.
 * @return array
 */
private function send_sdk_locations_request(): array {
    $settings        = Settings::get_instance();
    $customer_code   = $settings->get_field_value( 'customer_code' );
    $customer_number = $settings->get_field_value( 'customer_num' );

    $receiver_address = array(
        'countryIso'  => $this->item_info->receiver['country'],
        'postalCode'  => $this->item_info->receiver['postcode'],
        'city'        => $this->item_info->receiver['city'],
        'street'      => $this->item_info->receiver['address_1'],
        'houseNumber' => $this->item_info->receiver['address_2'],
    );

    // VERIFY: method name. SDK docs show locations()->getPickupLocationsByAddress().
    // If Client has addressLocations()->getNearestByAddress() instead, update below.
    $request  = new PickUpNearAddressRequest(
        receiverAddress:   $receiver_address,
        numberOfLocations: (int) $this->item_info->body['locations'],
        locationType:      'Retail',
        pickUpDate:        $this->item_info->body['order_date'],
        customerCode:      $customer_code,
        customerNumber:    $customer_number,
    );
    $client   = ( new ClientFactory() )->get_client();
    $response = $client->locations()->getPickupLocationsByAddress( $request );

    return $this->map_locations_response( $response );
}

/**
 * Map SDK PickUpLocationsResponse to the shape Frontend\Dropoff_Points reads.
 *
 * IMPORTANT: Replace all keys below with the actual keys from Step 1 grep.
 * Do not ship until keys are confirmed against Dropoff_Points.php.
 *
 * VERIFY: the collection accessor on PickUpLocationsResponse (locationsCollection(), etc.)
 * and location object accessors against installed SDK source.
 *
 * @param mixed $response SDK response object.
 * @return array
 */
private function map_locations_response( $response ): array {
    $locations = array();

    foreach ( $response->locationsCollection() as $location ) {
        $addr        = $location->address();
        $locations[] = array(
            // Replace with keys Dropoff_Points.php actually reads.
            'Address' => array(
                'Street'      => $addr->street(),
                'HouseNr'     => $addr->houseNumber(),
                'City'        => $addr->city(),
                'Zipcode'     => $addr->postalCode(),
                'Countrycode' => $addr->countryIso(),
            ),
            'Name'         => $location->name(),
            'Distance'     => $location->distance(),
            'LocationCode' => $location->locationCode(),
        );
    }

    return $locations;
}
```

- [ ] **Step 3: Run PHPCS**

```bash
composer check-php
```

- [ ] **Step 4: Verify old path unchanged**

With both `'timeframe'` and `'locations'` disabled, load classic and blocks checkout. Pickup points must appear as before. Log must show no SDK lines.

- [ ] **Step 5: Enable Locations SDK path and test parity**

Add temporarily:

```php
add_action(
	'init',
	static function () {
		\PostNLWooCommerce\SDK\Router::enable( 'locations' );
	}
);
```

1. Classic checkout, NL address: pickup-point list loads.
2. Blocks checkout, NL address: pickup-point list loads.
3. Select a pickup point, place order; check `_postnl_order_metadata`.
4. Compare location list to old API output for same address.
5. WC log: "Checkout: using SDK Locations path." must appear.

- [ ] **Step 6: Remove temporary enable line**

- [ ] **Step 7: Commit**

```bash
git add src/Rest_API/Checkout/Client.php
git commit -m "feat: add SDK Locations path to Checkout\Client (off by default)"
```

---

## Task 5 — Checkout Aggregation

**Status:** Ready after Tasks 3 + 4 staging parity confirmed | **Depends on:** Tasks 3 + 4
**Files:** `src/Rest_API/Checkout/Client.php` (modify)

**Context:** Remove the last reference to `POST /shipment/v1/checkout`. `send_request()` already handles both SDK flags; this task removes the old-client fallback and makes the SDK paths mandatory. Merge only after both Tasks 3 and 4 pass staging parity independently.

**Do not proceed unless all of these are confirmed:**
- [ ] Task 3 delivery-day parity: NL domestic, NL→BE, BE→NL on staging.
- [ ] Task 4 pickup-location parity: NL domestic on staging.
- [ ] Classic checkout end-to-end tested.
- [ ] Blocks checkout end-to-end tested.

- [ ] **Step 1: Replace `send_request()` with the aggregation-only version**

Read `src/Rest_API/Checkout/Client.php`. Replace `send_request()` with:

```php
/**
 * Send checkout request — SDK aggregation of TimeFrame + Locations.
 *
 * @throws \Exception On SDK error in both sub-calls.
 * @return array
 */
public function send_request() {
    $this->logger->write( 'Checkout: SDK aggregation (TimeFrame + Locations).' );

    $result = array(
        'DeliveryOptions' => array(),
        'PickupOptions'   => array(),
    );

    if ( $this->item_info->body['delivery_days_enabled'] ) {
        try {
            $tf                        = $this->send_sdk_timeframe_request();
            $result['DeliveryOptions'] = $tf['DeliveryOptions'];
        } catch ( PostnlExceptionInterface $e ) {
            $this->logger->write( 'TimeFrame SDK error: ' . $e->getMessage() );
        }
    }

    if ( $this->item_info->body['pickup_points_enabled'] ) {
        try {
            $result['PickupOptions'] = $this->send_sdk_locations_request();
        } catch ( PostnlExceptionInterface $e ) {
            $this->logger->write( 'Locations SDK error: ' . $e->getMessage() );
        }
    }

    return $result;
}
```

Remove the now-unused `$endpoint` property (`/shipment/v1/checkout`), `compose_body_request()`, `get_cutoff_times()`, and `get_checkout_options()` — but only if they are not called from any other class. Confirm with:

```bash
grep -rn "get_cutoff_times\|get_checkout_options\|compose_body_request" src/
```

If they are referenced externally, keep them. If not, remove them.

- [ ] **Step 2: Run PHPCS**

```bash
composer check-php
```

- [ ] **Step 3: Confirm old endpoint is gone**

```bash
grep -rn "shipment/v1/checkout" src/
```

Expected: no results.

- [ ] **Step 4: Full end-to-end checkout test**

Classic checkout:
1. NL address → delivery-day and pickup tabs both appear.
2. Select delivery day → place order → verify `_postnl_order_metadata`.
3. Select pickup point → place order → verify `_postnl_order_metadata`.
4. Disable delivery-day setting → only pickup tab appears.
5. Disable pickup-point setting → only delivery-day tab appears.

Blocks checkout: repeat steps 1–3.

Regression checks:
- Postal-code validation still works (old client — check log).
- Fill In With PostNL still works (old client — check log).
- Delivery fee display and tax `taxRatio` back-calculation unchanged (see `agents.md` tax display architecture).

- [ ] **Step 5: Commit**

```bash
git add src/Rest_API/Checkout/Client.php
git commit -m "feat: replace old checkout endpoint with SDK TimeFrame+Locations aggregation"
```

---

## Task 6 — Shipping + Letterbox Labels ⛔ BLOCKED

**Status:** Blocked — product/options → V4 field mapping table needed from PostNL/Joris
**Files (when unblocked):** `src/Rest_API/Shipping/Client.php`, `src/Rest_API/Letterbox/Client.php`, `src/Helper/Mapping.php`

**Do not write any code until:**
- Written mapping table from PostNL/Joris confirming every `ProductCodeDelivery` + `ProductOptions` → V4 `shipmentType` + `services` combination.
- `services.adrLq` API field casing confirmed (`adrLq` vs `adrlq`).
- AITS-382 (`guaranteedBefore: '12:00'`) status confirmed.

**Implementation pattern (for reference when unblocked):**

In `Shipping\Client`, override `send_request()` and add a `build_label_confirm_request()` method. Key mappings:

| Old field | V4 SDK field | Note |
|---|---|---|
| `$item_info->shipment['shipping_product']['code']` | `shipmentType` | Use new V4 mapping method in `Mapping.php` |
| `$item_info->shipment['product_options']` | `services` object | Map per confirmed table |
| `$item_info->receiver` | `receiver` (ShipmentParty) | Restructure addresses |
| `$item_info->shipper` | `sender` (ShipmentParty) | Restructure addresses |
| `$item_info->shipment['total_weight']` | `items[0].dimensions.weight` | Already in grams; SDK property `weightGr` |
| `$item_info->backend_data['num_labels'] > 1` | Multiple `items[]` entries | One `ShippingItem` per collo |
| `$item_info->shipment['barcodes'][n]` | `items[n].barcode` | One barcode per item |

Add V4 mapping as a new method in `Mapping.php` alongside existing `products_data()`. Do not remove or modify `products_data()`. Old clients stay as fallback per product type until each type is staging-validated.

---

## Task 7 — Return Labels (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Files:** `src/Rest_API/Return_Label/Client.php` (modify)

**Context:** `Return_Label\Client` extends `Shipping\Client` and overrides `get_customer_address()` for return addresses. This task overrides `send_request()` to call `returnShipment()->generateReturn()` when `Router::use_sdk_for('return_labels')` is true. `Smart_Returns\Client` is not touched.

**Before starting:**
1. Read `src/Rest_API/Return_Label/Item_Info.php` — confirm all return-specific fields available in `$this->item_info` (return address, return period, valuable return, LiB barcode).
2. Read `src/Order/Base.php` — find what array key it reads from the return label response. The `map_return_response()` method must return that shape.
3. Confirm `ReturnShipmentRequest` namespace in installed SDK: `Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest`.
4. Confirm `returnPeriod` valid values: SDK docs show only `IN_20_DAYS` (20) and `IN_35_DAYS` (35). Values 100, 200, 365 are **not confirmed** — use 20 or 35 only.

- [ ] **Step 1: Override `send_request()` in `src/Rest_API/Return_Label/Client.php`**

Read the file. Replace the entire file content with:

```php
<?php
/**
 * Class Rest_API\Return_Label\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */

namespace PostNLWooCommerce\Rest_API\Return_Label;

use Postnl\Sdk\Exception\PostnlExceptionInterface;
use Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest;
use PostNLWooCommerce\Rest_API\Shipping;
use PostNLWooCommerce\SDK\ClientFactory;
use PostNLWooCommerce\SDK\Router;
use PostNLWooCommerce\SDK\SdkExceptionConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */
class Client extends Shipping\Client {

	/**
	 * Get customer address information for Rest API (return address).
	 *
	 * @return array
	 */
	public function get_customer_address() {
		return array(
			'AddressType' => '02',
			'City'        => $this->item_info->customer['return_address_city'],
			'CompanyName' => $this->item_info->customer['return_company'],
			'Countrycode' => $this->item_info->shipper['country'],
			'HouseNr'     => $this->item_info->customer['return_address_2'],
			'Street'      => $this->item_info->customer['return_address_1'],
			'Zipcode'     => $this->item_info->customer['return_address_zip'],
		);
	}

	/**
	 * Send API request — SDK return path when enabled, old client otherwise.
	 *
	 * @throws \Exception On API or SDK error.
	 * @return array
	 */
	public function send_request() {
		if ( ! Router::use_sdk_for( 'return_labels' ) ) {
			return parent::send_request();
		}

		$this->logger->write( 'ReturnLabel: using SDK path.' );

		try {
			return $this->send_sdk_return_request();
		} catch ( PostnlExceptionInterface $e ) {
			$this->logger->write( 'ReturnLabel SDK error: ' . $e->getMessage() );
			throw SdkExceptionConverter::convert( $e );
		}
	}

	/**
	 * Call the V4 return SDK service.
	 *
	 * VERIFY: confirm ReturnShipmentRequest constructor parameter names against installed SDK.
	 * VERIFY: check Order\Base.php for the exact response array keys it reads from this client.
	 * Adjust map_return_response() to match before shipping.
	 *
	 * @throws PostnlExceptionInterface On SDK error.
	 * @return array
	 */
	private function send_sdk_return_request(): array {
		$receiver = array(
			'customerNumber' => $this->item_info->customer['customer_num'],
			'customerCode'   => $this->item_info->customer['customer_code'],
			'address'        => array(
				'countryIso'  => $this->item_info->shipper['country'],
				'city'        => $this->item_info->customer['return_address_city'],
				'companyName' => $this->item_info->customer['return_company'],
				'houseNumber' => $this->item_info->customer['return_address_2'],
				'street'      => $this->item_info->customer['return_address_1'],
				'postalCode'  => $this->item_info->customer['return_address_zip'],
			),
		);

		$sender = array(
			'contact' => array(
				'firstName'    => $this->item_info->receiver['first_name'],
				'lastName'     => $this->item_info->receiver['last_name'],
				'email'        => $this->item_info->shipment['email'],
				'mobileNumber' => $this->item_info->shipment['phone'],
			),
			'address' => array(
				'countryIso'  => $this->item_info->receiver['country'],
				'city'        => $this->item_info->receiver['city'],
				'houseNumber' => $this->item_info->receiver['house_number'],
				'street'      => $this->item_info->receiver['address_1'],
				'postalCode'  => $this->item_info->receiver['postcode'],
			),
		);

		// SDK ReturnPeriod: only IN_20_DAYS (20) or IN_35_DAYS (35) are confirmed.
		// Map from old product code → 20 or 35. Default 20 when unmapped.
		$return_period = 20;

		$return_options = array(
			'labelType' => 'Label',
			'domestic'  => array(
				'returnPeriod'   => $return_period,
				'valuableReturn' => ! empty( $this->item_info->shipment['valuable_return'] ),
			),
		);

		if ( ! empty( $this->item_info->shipment['return_barcode'] ) ) {
			$return_options['labelType']     = 'labelinthebox';
			$return_options['returnBarcode'] = $this->item_info->shipment['return_barcode'];
		}

		$label_settings = array(
			'outputType'      => $this->item_info->shipment['printer_type'] ?? 'PDF',
			'resolution'      => 200,
			'pageOrientation' => 'portrait',
		);

		$request = new ReturnShipmentRequest(
			receiver:      $receiver,
			sender:        $sender,
			returnOptions: $return_options,
			labelSettings: $label_settings,
			items:         array(
				array( 'barcode' => $this->item_info->shipment['main_barcode'] ),
			),
		);

		$client   = ( new ClientFactory() )->get_client();
		$response = $client->returnShipment()->generateReturn( $request );

		return $this->map_return_response( $response );
	}

	/**
	 * Map SDK GenerateReturnResponse to the shape Order\Base reads.
	 *
	 * IMPORTANT: Open src/Order/Base.php, find the call to this client's send_request(),
	 * and identify every array key it reads. Replace the keys below with the actual keys.
	 *
	 * VERIFY: the collection accessor on GenerateReturnResponse (shippingItemsCollection(), etc.)
	 * and item accessors against installed SDK source.
	 *
	 * @param mixed $response SDK response.
	 * @return array
	 */
	private function map_return_response( $response ): array {
		$items = array();

		// VERIFY: collection accessor name on GenerateReturnResponse.
		foreach ( $response->shippingItemsCollection() as $item ) {
			$items[] = array(
				'Barcode' => $item->barcode(),
				'Labels'  => array(
					array(
						'Content'   => $item->label()->content(),
						'Labeltype' => $item->label()->labelType(),
					),
				),
			);
		}

		// Replace 'MergedLabels' with the key Order\Base actually reads.
		return array( 'MergedLabels' => $items );
	}
}
```

- [ ] **Step 2: Run PHPCS**

```bash
composer check-php
```

- [ ] **Step 3: Verify old path unchanged**

With `'return_labels'` disabled (default), generate a return label for a test order. Confirm it works. Check log — "ReturnLabel: using SDK path." must NOT appear. Confirm `Smart_Returns\Client` is untouched:

```bash
git diff src/Rest_API/Smart_Returns/
```

Expected: no changes.

- [ ] **Step 4: Enable SDK return path and test parity**

Add temporarily:

```php
add_action(
	'init',
	static function () {
		\PostNLWooCommerce\SDK\Router::enable( 'return_labels' );
	}
);
```

1. Generate NL domestic return label on staging — verify PDF downloads; barcode scannable.
2. Generate LiB return label — verify return barcode present.
3. Generate NL-BE and BE-NL return labels.
4. Verify return label stored at `wp-content/uploads/postnl/`.
5. Compare output to old client output for same order data.

- [ ] **Step 5: Remove temporary enable line**

- [ ] **Step 6: Commit**

```bash
git add src/Rest_API/Return_Label/Client.php
git commit -m "feat: add SDK return label path to Return_Label\Client (off by default)"
```

---

## Task 8 — Smart Returns ⛔ BLOCKED

**Status:** Blocked — PostNL must confirm V4 `return/generate` replaces `POST /shipment/v2_2/label/`
**Files (when unblocked):** `src/Rest_API/Smart_Returns/Client.php`

**Do not write any code until:** PostNL confirms in writing that `POST /shipment/delivery/v4/return/generate` fully replaces the old `POST /shipment/v2_2/label/` for Smart Returns — including barcode format, return period behavior, and customer notification side-effects.

**Pattern when unblocked:** Override `send_request()` in `Smart_Returns\Client`. Check `Router::use_sdk_for('smart_returns')`. When on, call `$client->returnShipment()->generateReturn($request)`, extract the barcode string from the response, return it in the shape `Order\Single` reads. Do not change `WC_Email_Smart_Return`.

---

## Task 9 — activatereturn ⛔ BLOCKED

**Status:** Blocked — PostNL/Joris must decide: SDK extension, old client retention, or drop
**Files (Option A):** `src/SDK/Extension/ActivateReturnExtension.php` (new), `src/Rest_API/Shipment_and_Return/Client.php`
**Files (Option B):** `src/Rest_API/Shipment_and_Return/Client.php` — add retention comment only

**Do not write any code until:** PostNL/Joris answers whether `POST /shipment/delivery/v4/return/activate` is behaviorally equivalent to old `POST /parcels/v1/shipment/activatereturn`.

**Option A pattern (when unblocked):**

Create `src/SDK/Extension/ActivateReturnExtension.php` implementing `ConfigurableAction`. Reference the installed SDK's `PostalCodeCheckExtension.php` (`src/Service/Checkout/V1/Extension/PostalCodeCheckExtension.php`) as the concrete implementation example — it shows the full interface shape.

```php
// Skeleton only — implement from PostalCodeCheckExtension reference:
namespace PostNLWooCommerce\SDK\Extension;

use Postnl\Sdk\Service\Extension\ConfigurableAction;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ActivateReturnExtension implements ConfigurableAction {
    // implement execute($context, $payload) per the interface
    // endpoint: POST /shipment/delivery/v4/return/activate
    // fields: barcode, sender.customerNumber, source, label
}
```

Then in `Shipment_and_Return\Client::send_request()`:
```php
if ( Router::use_sdk_for( 'activatereturn' ) ) {
    $client = ( new ClientFactory() )->get_client();
    $ext    = new ActivateReturnExtension();
    $client->extensions()->register( $ext );
    $response = $client->extensions()->getAs( ActivateReturnExtension::class )->execute( $payload );
    // map response; set _postnl_return_activated
}
```

Note: SDK docs show `$context->cache` in `ServiceContext`. Verify it exists in the installed `ServiceContext.php` before using it.

**Option B:** Add a code comment to `Shipment_and_Return\Client.php` documenting the retention decision. No functional change.

---

## Full Staging QA Checklist

Run after Tasks 1–5 and 7 are all merged. Run again when Task 6 labels are considered stable.

- [ ] Barcode: generate for NL domestic order; verify format (e.g., `3SDEVC...`)
- [ ] Classic checkout: NL address → delivery-day slots appear (daytime + evening)
- [ ] Blocks checkout: NL address → delivery-day slots appear
- [ ] Classic checkout: NL address → pickup-point list appears
- [ ] Blocks checkout: NL address → pickup-point list appears
- [ ] Classic + blocks: select delivery day → place order → verify `_postnl_order_metadata`
- [ ] Classic + blocks: select pickup point → place order → verify `_postnl_order_metadata`
- [ ] Return label — NL domestic: PDF downloads; barcode scannable
- [ ] Return label — LiB: return barcode present
- [ ] Return label — NL-BE and BE-NL: label format correct
- [ ] Error: invalid API key → admin error shown; no raw SDK exception in browser
- [ ] Sandbox toggle: requests route to `api-sandbox.postnl.nl` (check WC log)
- [ ] WC log: no API key, label binary, or customer PII visible in any log entry
- [ ] Postal-code check: still works (old client — confirm no regression)
- [ ] Fill In With PostNL: still works (old client — confirm no regression)
- [ ] Delivery fees and tax display unchanged (verify `taxRatio` logic in `Container.php`)

---

## Self-Review

**Spec coverage:**

| Requirement from migration plan | Task | Covered |
|---|---|---|
| Composer SDK dependency + Private Packagist | 0 | ✓ |
| PHP 8.2 guard / admin notice | 0 | ✓ |
| ClientFactory (API key, sandbox) | 1 | ✓ |
| Router (per-flow, off by default) | 1 | ✓ |
| SdkExceptionConverter | 1 | ✓ |
| Logger binary redaction | 1 | ✓ |
| Barcode SDK POC | 2 | ✓ |
| TimeFrame SDK POC | 3 | ✓ |
| Locations SDK POC | 4 | ✓ |
| Checkout aggregation | 5 | ✓ |
| Shipping + Letterbox labels | 6 | ✓ (blocked) |
| Return labels SDK POC | 7 | ✓ |
| Smart Returns | 8 | ✓ (blocked) |
| activatereturn | 9 | ✓ (blocked, both options) |
| Old client preserved as fallback | 2–5, 7 | ✓ |
| Classic + blocks checkout tested in every checkout task | 3, 4, 5 | ✓ |
| `taxRatio` / fee regression check | 5 | ✓ |
| Staging QA checklist | — | ✓ |

**Type consistency check:**
- `Router::use_sdk_for(string $flow)` — called consistently in Tasks 2, 3, 4, 7.
- `new ClientFactory()` — instantiated identically in Tasks 2, 3, 4, 7.
- `SdkExceptionConverter::convert($e)` — used identically in Tasks 2, 3, 7.
- `$client->barcode()`, `->checkout()`, `->locations()`, `->returnShipment()` — all annotated with VERIFY comments pointing to the same pre-flight check.
- `send_sdk_timeframe_request()` referenced in both Task 3 (step 2) and Task 5 (step 1) — consistent private method name. ✓
- `send_sdk_locations_request()` referenced in both Task 4 (step 2) and Task 5 (step 1) — consistent. ✓
