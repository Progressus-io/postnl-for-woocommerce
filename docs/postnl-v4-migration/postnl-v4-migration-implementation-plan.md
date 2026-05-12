# PostNL V4 API Migration — WooCommerce Plugin Implementation Plan

**Version:** 1.0 (preliminary / internal)
**Date:** 2026-05-12
**API reference:** `postnl-v4-sdk-api-reference.md`
**SDK source:** `postnl-sdk-audit/vendor/postnl/api-client-sdk`
**Plugin source:** `postnl-for-woocommerce-org/`

---

## Context

The PostNL SDK (`postnl/api-client-sdk`) covers V4 flows for ShipmentDelivery, ReturnShipment, Barcode, Locations, and TimeFrame. The migration uses a **hybrid approach**: SDK for confirmed V4 flows, existing old REST clients retained as fallback until staging parity is validated per flow. Smart Returns and activatereturn remain on old clients until PostNL confirms V4 equivalence.

All time estimates in this plan are **preliminary and internal only**.
- **Track A** = with SDK (recommended)
- **Track B** = without SDK (custom HTTP client, auth, retry, error parsing written per area)

---

## PHP Version Note

The SDK requires PHP ≥ 8.2. The plugin currently declares PHP ≥ 7.4. This is a **release/build decision**, not a blocker for starting development work.

Options to resolve before any SDK code ships to production:
- Bump plugin PHP minimum to 8.2 in plugin header and `readme.txt`.
- Implement conditional SDK loading that skips SDK calls on PHP < 8.2 with an admin notice.

Agree on this decision before Task 1 merges to production.

---

## SDK Composer Dependency Note

The SDK is installed in `postnl-sdk-audit/` for audit purposes only. Before any SDK code ships:
- Add `postnl/api-client-sdk` to `postnl-for-woocommerce-org/composer.json` with a **pinned version** (not `dev-main`).
- Validate that the resulting `vendor/` tree builds correctly in the plugin's WordPress context.
- Verify `vendor/autoload.php` loads without conflicts with existing Composer dependencies (`clegginabox/pdf-merger` etc.).
- This validation is part of Task 1.

---

## SDK Docs Warning

Several SDK README examples contain wrong method names and namespaces. Always use the source code, not the docs, for imports and method calls. Full mismatch list: `postnl-v4-sdk-api-reference.md §11`.

---

## Proposed ClickUp Task Structure

```
Epic: PostNL V4 API Migration
│
├── [Overview]  Routing table + required inputs         reference only, not a PR
├── Task 1      SDK Client Factory + Router              Ready — no deps
├── Task 2      Barcode (SDK POC)                       Ready — dep: Task 1
├── Task 3      TimeFrame / Delivery Dates (SDK POC)    Ready — dep: Task 1
├── Task 4      Pickup Locations (SDK POC)              Ready — dep: Task 1
├── Task 5      Checkout Aggregation                    Ready — dep: Task 3 + 4 staging validated
├── Task 6      Shipping + Letterbox Labels             BLOCKED: product mapping input
├── Task 7      Return Labels (SDK POC)                 Ready — dep: Task 1
├── Task 8      Smart Returns                           BLOCKED: PostNL confirmation
└── Task 9      activatereturn                          BLOCKED: PostNL decision
```

---

## Routing Table

| Flow | File | Decision | Status |
|---|---|---|---|
| Barcode | `src/Rest_API/Barcode/Client.php` | SDK (POC first) | Ready |
| Checkout delivery days | `src/Rest_API/Checkout/Client.php` | SDK (POC first) | Ready |
| Checkout pickup points | `src/Rest_API/Checkout/Client.php` | SDK (POC first) | Ready |
| Shipping labels | `src/Rest_API/Shipping/Client.php` | SDK | Blocked: product mapping |
| Letterbox labels | `src/Rest_API/Letterbox/Client.php` | SDK | Blocked: product mapping |
| Return labels | `src/Rest_API/Return_Label/Client.php` | SDK (POC first) | Ready |
| Smart Returns | `src/Rest_API/Smart_Returns/Client.php` | Old client | Blocked: PostNL confirmation |
| activatereturn | `src/Rest_API/Shipment_and_Return/Client.php` | Old client | Blocked: PostNL decision |
| Fill In With PostNL | `src/Frontend/Fill_In_With_Postnl.php` | Old client (permanent) | — |
| Postal code check | `src/Rest_API/Postcode_Check/Client.php` | Old client (permanent) | — |

---

## Required Inputs

| Input | Blocks | Status |
|---|---|---|
| Product code / ProductOptions → V4 `shipmentType` + `services` mapping table | Task 6 | Needed |
| Smart Returns V4 equivalence confirmed (`return/generate` vs old `v2_2/label`) | Task 8 | Needed |
| activatereturn decision: SDK extension / old client / drop | Task 9 | Needed |
| `services.adrLq` vs `services.adrlq` API casing confirmed | Task 6 | Needed |
| Guaranteed-before `12:00` bug status (AITS-382) | Task 6 | Needed |
| PHP ≥ 8.2 hosting / release decision | All tasks | Release decision |

---

---

## Task 1 — SDK Client Factory + Router

**Status:** Ready | **Depends on:** Nothing
**Estimate:** Track A 6–8 h | Track B 16–20 h

### Goal
Create two foundational classes in `src/SDK/`:
- `ClientFactory.php` — builds the PostNL SDK client from plugin settings.
- `Router.php` — decides per-flow whether to use the SDK path or the old client; SDK paths are disabled by default and enabled only after staging validation.

All subsequent SDK tasks call the factory through the router. No existing flow is changed.

### Current behavior
- Each `Rest_API/*/Client.php` builds API calls directly using `Rest_API\Base` → `wp_remote_request()`.
- API key and sandbox flag come from `Settings::get_instance()`.
- Error handling via `\Exception` thrown in `Base::check_response_error()`.

### Target behavior
- New `PostNLWooCommerce\SDK\ClientFactory` class at `src/SDK/ClientFactory.php`.
- Provides `get_client(): PostnlClientInterface`.
- Reads API key and sandbox flag from `Settings::get_instance()`.
- **Does not catch runtime SDK/API exceptions** — exception handling belongs at each SDK service wrapper (per-flow client classes, Tasks 2–9), not at the factory.
- New `PostNLWooCommerce\SDK\Router` class at `src/SDK/Router.php`.
- Provides per-flow `use_sdk_for(string $flow): bool` method.
- All SDK paths disabled by default; enabled per flow only after staging validation passes.
- All other plugin behavior unchanged — no runtime behavior change expected.

### Scope
- `src/SDK/ClientFactory.php` — new file, new directory.
- `src/SDK/Router.php` — new file; per-flow SDK/old-client switch.
- `src/Logger.php` — add sanitization to strip label binary content from SDK log entries.
- `postnl-for-woocommerce-org/composer.json` — add SDK dependency, pin version.
- Composer build validation in WordPress plugin context.

### Out of scope
- No changes to any `Rest_API/*/Client.php`.
- No SDK calls made from this task — factory only.
- No changes to frontend, admin, checkout, or label flows.

### Files/classes likely touched
- `src/SDK/ClientFactory.php` — new
- `src/SDK/Router.php` — new
- `src/Logger.php` — minor addition
- `postnl-for-woocommerce-org/composer.json`

### Implementation details

**ClientFactory:**
- Namespace: `PostNLWooCommerce\SDK`
- `src/SDK/` is a new top-level sub-directory under `src/`, consistent with `src/Helper/`, `src/Library/`.
- Use `Postnl::client(Auth::apiKey($apiKey))` for production.
- Use `Postnl::sandboxClient(Auth::apiKey($apiKey))` for sandbox.
- Read settings via `Settings::get_instance()->get_api_key()` and `->is_sandbox()`.
- Factory only builds the client — it does not make API calls and does not catch runtime API exceptions.
- Include `ABSPATH` guard at top of file (WordPress convention — see `agents.md`).

**Router:**
- Class: `PostNLWooCommerce\SDK\Router`
- Method: `use_sdk_for(string $flow): bool` — returns false by default for every flow.
- Flow keys match the routing table (e.g., `barcode`, `timeframe`, `locations`, `return_labels`, `shipping_labels`).
- Enabling a flow in the Router is how a developer activates the SDK path after staging validation; reverting it re-enables the old client immediately.
- Each per-flow client (Tasks 2–9) checks `Router::use_sdk_for($flow)` before deciding which path to take.
- Runtime SDK/API exceptions are caught inside each per-flow client's SDK branch and converted to `\Exception` matching `Base::check_response_error()` behavior.
- Include `ABSPATH` guard at top of file.

### SDK methods/classes involved
- `Postnl\Sdk\Client\Postnl::client()`
- `Postnl\Sdk\Client\Postnl::sandboxClient()`
- `Postnl\Sdk\Auth\Auth::apiKey()`
- `Postnl\Sdk\Client\PostnlClientInterface`
- `Postnl\Sdk\Exception\PostnlExceptionInterface` — caught at each per-flow SDK service wrapper (Tasks 2–9), not at the factory

### Data mapping notes
- None. This task does not map any API fields.

### Fallback/old-client behavior
- All existing `Rest_API/*/Client.php` clients remain fully operational and unchanged.
- Factory is purely additive; it does not replace anything in this task.

### Unit tests
- `ClientFactory::get_client()` returns a `PostnlClientInterface` instance when API key is set.
- `ClientFactory::get_client()` returns a sandbox client when `is_sandbox()` returns true.
- `Router::use_sdk_for('barcode')` returns false by default (SDK paths off unless enabled).
- `Router::use_sdk_for('barcode')` returns true after the barcode flow is enabled.
- Logger does not output API key.
- Logger does not output label binary data.

### Integration/staging tests
- Instantiate `ClientFactory` in staging environment; verify client builds without PHP error.
- Toggle sandbox mode; verify `is_sandbox()` routes to sandbox base URL (check debug log).

### Manual QA checks
- Toggle sandbox mode in plugin settings; verify no PHP error.
- Verify WooCommerce logs after factory call contain no API key.

### Acceptance criteria
- `ClientFactory::get_client()` returns a working SDK client against PostNL sandbox.
- `Router::use_sdk_for()` returns false for all flows by default; SDK paths are off until explicitly enabled.
- No existing plugin functionality changes — old clients are not used only when an SDK path is enabled and validated; they remain available as fallback at all times.
- No API key or label binary in log output.
- Composer build succeeds; no autoload conflicts with existing dependencies.
- No runtime behavior change expected in any existing flow.

### Dependencies/blockers
- PHP ≥ 8.2 required in development environment.
- Composer build must be validated in WordPress plugin context before merging.

### Notes for reviewer
- Confirm `vendor/autoload.php` loads correctly in plugin bootstrap (`postnl-for-woocommerce.php`).
- Confirm no namespace collision between SDK `Postnl\Sdk\` and plugin `PostNLWooCommerce\`.
- SDK version must be pinned; reject `dev-main` or `*` in `composer.json`.
- Verify `src/SDK/` directory is autoloaded by plugin PSR-4 config.
- **Standing rule for all SDK tasks:** Any task enabling an SDK path must either prove fallback behavior in the PR description, or explicitly explain why fallback is intentionally removed. Task 5 (Checkout Aggregation) is the only task currently approved to remove the old checkout monolith, and only after Tasks 3 + 4 are individually staging-validated.

---

## Task 2 — Barcode (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Estimate:** Track A 4–6 h | Track B 10–14 h

### Goal
Migrate barcode generation to the SDK as a proof-of-concept. Validate the end-to-end SDK integration pattern before applying it to higher-risk flows. Old HTTP client remains available as fallback until staging parity is confirmed.

### Current behavior
- `Rest_API\Barcode\Client` calls `GET /shipment/v1_1/barcode` via `wp_remote_request()`.
- Request fields: `CustomerCode`, `CustomerNumber`, `Type`, `Serie` (range string).
- Returns barcode string(s) used in downstream label generation.
- Called from `Order\Base` before label generation.

### Target behavior
- `Rest_API\Barcode\Client` calls `ClientFactory::get_client()->barcode()->generateBarcode(BarcodeRequest)`.
- Old HTTP call preserved in class as fallback, not removed.
- Response barcode string returned to callers with no shape change.
- Old client restored via single-line change if SDK parity fails on staging.

### Scope
- `src/Rest_API/Barcode/Client.php` — replace active HTTP call with SDK call; keep old call as fallback.

### Out of scope
- Label generation, checkout, returns — not touched.
- `src/Rest_API/Barcode/Item_Info.php` — not changed.
- `src/Order/Base.php` caller — not changed.

### Files/classes likely touched
- `src/Rest_API/Barcode/Client.php`

### Implementation details
- Check `Router::use_sdk_for('barcode')` — if false, fall through to old `send_request()` call (default until staging validated).
- When SDK path is active: inject `ClientFactory`, build `BarcodeRequest` from mapped fields, call `$factory->get_client()->barcode()->generateBarcode($request)`.
- Catch `PostnlExceptionInterface` inside the SDK branch; re-throw as `\Exception` matching current `Base::check_response_error()` behavior.
- Old `send_request()` call remains in place as the else branch — not removed, not commented out.

### SDK methods/classes involved
- `Client::barcode()->generateBarcode(BarcodeRequest)` → `GenerateBarcodeResponse`
- `Postnl\Sdk\RequestData\V4\Barcode\BarcodeRequest`
- `Postnl\Sdk\ResponseData\V4\Barcode\GenerateBarcodeResponse`

### Data mapping notes

| Old field | SDK field | Note |
|---|---|---|
| `CustomerCode` | `customerCode` | Direct map |
| `CustomerNumber` | `customerNumber` | Direct map |
| `Type` | Barcode type prefix | Confirm V4 equivalent with PostNL |
| `Serie` (range start) | `serieStart` | Split from old range string |
| `Serie` (range end) | `serieEnd` | Split from old range string |
| Implied count | `numberOfBarcodes` | Derive from request context |

### Fallback/old-client behavior
- Old `GET /shipment/v1_1/barcode` call remains in the class as the else branch of the Router check.
- Old client is not used when the SDK path is enabled and validated; it remains available as fallback at all times.
- Disable the SDK path in `Router` to restore old behavior immediately — no rollback PR needed.
- Old client remains available as fallback until staging parity is confirmed for all barcode types.

### Unit tests
- Mock `ClientFactory`; verify `BarcodeRequest` fields match expected mapped values from `Item_Info`.
- Verify response barcode string returned unchanged to callers.
- `HttpSdkException` caught; surfaces as `\Exception` matching existing error behavior.
- `BarcodeRequest` with missing required fields triggers caught `ValidationException`.

### Integration/staging tests
- Generate a barcode for a test NL domestic order on sandbox.
- Verify barcode format matches expected PostNL pattern (e.g., `3S...` for NL domestic).
- Compare SDK-generated barcode format to old-client-generated barcode for same inputs.

### Manual QA checks
- Generate a barcode for a test order in staging admin.
- Verify barcode is stored correctly in `_postnl_order_metadata`.
- Verify no barcode value or API key appears in WC logs.

### Acceptance criteria
- Barcode generates successfully via SDK on sandbox.
- Barcode format matches PostNL expected pattern.
- Old client remains available as fallback until staging parity confirmed.
- No change to barcode storage or downstream label use.
- Old client is not used when the SDK path is enabled and validated; it remains available as fallback at all times.

### Dependencies/blockers
- Task 1 merged and Composer build validated.
- Confirm V4 barcode `Type` field equivalent with PostNL (can proceed with known mapping first).

### Notes for reviewer
- This is a POC task. If SDK barcode output format differs unexpectedly from old API output, stop and consult PostNL before proceeding with other tasks.
- Verify `numberOfBarcodes` behavior for multicollo orders (multiple barcodes per label request).

---

## Task 3 — TimeFrame / Delivery Dates (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Estimate:** Track A 6–8 h | Track B 14–18 h

### Goal
Migrate checkout delivery-day options to the SDK TimeFrame V4 services as a POC. Validate the response shape against what `Frontend\Delivery_Day` expects. Old checkout client preserved as fallback. Prerequisite for Task 5.

### Current behavior
- `Rest_API\Checkout\Client` calls `POST /shipment/v1/checkout`.
- Single response contains both delivery-day options and pickup-point options.
- Delivery-day portion consumed by `Frontend\Delivery_Day` (classic) and blocks delivery-day component.

### Target behavior
- Delivery-day portion: call `ClientFactory::get_client()->singleTimeframe()->getTimeframe()` or `->multipleTimeframes()->getTimeframes()` based on configured services.
- Response mapped to the same shape expected by `Frontend\Delivery_Day`; adapter added if shapes differ.
- Old checkout call preserved as fallback; pickup-point portion untouched in this PR.

### Scope
- Delivery-date portion of `src/Rest_API/Checkout/Client.php`.
- `src/Frontend/Delivery_Day.php` — read-only check; response adapter added if needed.

### Out of scope
- Pickup locations (Task 4).
- Checkout aggregation (Task 5).
- Classic vs. blocks checkout rendering — no template changes.
- `src/Frontend/Container.php` selection logic and fee calculation — not changed.
- `src/Checkout_Blocks/` — not changed.

### Files/classes likely touched
- `src/Rest_API/Checkout/Client.php`
- `src/Frontend/Delivery_Day.php` (response adapter only if shapes differ)

### Implementation details

- Check `Router::use_sdk_for('timeframe')` — if false, fall through to old `POST /shipment/v1/checkout` call (default until staging validated).
- When SDK path is active: build request from mapped fields, call SDK TimeFrame service, map response, catch `PostnlExceptionInterface` inside the SDK branch.
- Old checkout call remains in the class as the else branch — not removed.

**SDK mismatch — use these exact calls (docs are wrong):**
- `$client->singleTimeframe()->getTimeframe(SingleServiceTimeframeRequest)` → `TimeFrameSingleServiceResponse`
- `$client->multipleTimeframes()->getTimeframes(MultipleServicesTimeframeRequest)` → `TimeframesMultipleServicesResponse`
- Correct namespace: `Postnl\Sdk\Service\SingleServiceTimeframe\V4\Request\SingleServiceTimeframeRequest`
- Correct namespace: `Postnl\Sdk\Service\MultipleServicesTimeframe\V4\Request\MultipleServicesTimeframeRequest`
- SDK docs show `$postnl->checkout()->multipleTimeframes()` — **this method does not exist on the client.**

Choose single vs. multiple services based on plugin settings for daytime/evening options.

### SDK methods/classes involved
- `Client::singleTimeframe()->getTimeframe(SingleServiceTimeframeRequest)`
- `Client::multipleTimeframes()->getTimeframes(MultipleServicesTimeframeRequest)`
- `Postnl\Sdk\ResponseData\V4\TimeFrame\TimeFrameSingleServiceResponse`
- `Postnl\Sdk\ResponseData\V4\TimeFrame\TimeframesMultipleServicesResponse`

### Data mapping notes

| Plugin / order data | SDK field | Source |
|---|---|---|
| Customer address | `receiverAddress` | Session / `Item_Info` |
| Cut-off / handover date | `handoverDate` | Plugin settings |
| Days to show (delivery) | `deliveryDays` / `numberOfDays` | Plugin settings |
| Service (daytime / evening) | `service` / `services[]` | Plugin settings |
| Shipment type | `shipmentType` | `parcel` as default |
| Customer code | `customerCode` | Settings |
| Customer number | `customerNumber` | Settings |

### Fallback/old-client behavior
- Old `POST /shipment/v1/checkout` call remains in the class as the else branch of the Router check.
- Pickup-point portion of old checkout call remains active and unmodified in this PR.
- Old client is not used when the SDK path is enabled and validated; it remains available as fallback at all times.
- Disable `Router::use_sdk_for('timeframe')` to restore old behavior immediately — no rollback PR needed.
- Old client remains available as fallback until staging parity is confirmed.

### Unit tests
- Mock `ClientFactory`; verify `SingleServiceTimeframeRequest` fields populated from plugin/order data.
- Verify `MultipleServicesTimeframeRequest` sends correct `services[]` from plugin settings.
- Verify `TimeFrameSingleServiceResponse` maps to existing delivery-day format (compare shapes explicitly).
- SDK error does not crash checkout; gracefully returns empty slots.

### Integration/staging tests
- Trigger delivery-day load for a NL address on staging.
- Verify daytime and evening slots appear where configured.
- Compare slot list to old API output for same address and settings.

### Manual QA checks
- Classic checkout: enter NL address, verify delivery-day slots load.
- Blocks checkout: enter NL address, verify delivery-day slots load.
- Select a slot, place order; verify `_postnl_order_metadata` saved correctly.

### Acceptance criteria
- Delivery-day options display via SDK on staging.
- Slot content matches old API output for same inputs (parity check).
- Classic and blocks checkout both work.
- Old client remains available as fallback until staging parity is confirmed.
- Old client is not used when the SDK path is enabled and validated.

### Dependencies/blockers
- Task 1 merged.
- Always test both classic and blocks checkout modes (see `agents.md`).

### Notes for reviewer
- `Frontend\Delivery_Day` expects a specific response shape. Any V4 shape difference must be handled by a response adapter — do not change `Delivery_Day` itself.
- Re-confirm `Container.php` tax/fee back-calculation is unaffected (see `agents.md` tax display architecture note — `taxRatio` logic must not change).

---

## Task 4 — Pickup Locations (SDK POC)

**Status:** Ready | **Depends on:** Task 1
**Estimate:** Track A 5–7 h | Track B 12–16 h

### Goal
Migrate checkout pickup-point options to the SDK Locations V4 services as a POC. Validate response shape against `Frontend\Dropoff_Points`. Old checkout client preserved as fallback. Prerequisite for Task 5.

### Current behavior
- `Rest_API\Checkout\Client` calls `POST /shipment/v1/checkout`.
- Pickup-point portion of response consumed by `Frontend\Dropoff_Points` (classic) and blocks dropoff-points component.

### Target behavior
- Pickup-point portion: check `Router::use_sdk_for('locations')` — if true, call SDK Locations service; otherwise fall through to old `POST /shipment/v1/checkout` call.
- Response mapped to the shape expected by `Frontend\Dropoff_Points`; adapter added if shapes differ.
- Old checkout call remains in the class as the else branch; delivery-day portion handled by Task 3.

### Scope
- Pickup-point portion of `src/Rest_API/Checkout/Client.php`.
- `src/Frontend/Dropoff_Points.php` — read-only check; adapter if shapes differ.

### Out of scope
- Delivery-day options (Task 3).
- Checkout aggregation (Task 5).
- `Container.php` selection logic — not changed.
- `client/checkout/postnl-dropoff-points/` JS — not changed.

### Files/classes likely touched
- `src/Rest_API/Checkout/Client.php`
- `src/Frontend/Dropoff_Points.php` (response adapter only if shapes differ)

### Implementation details
- Check `Router::use_sdk_for('locations')` — if false, fall through to old `POST /shipment/v1/checkout` call (default until staging validated).
- When SDK path is active: build request from mapped fields, call SDK Locations service, map response, catch `PostnlExceptionInterface` inside the SDK branch.
- Old checkout call remains in the class as the else branch — not removed.
- By address (primary): `$client->addressLocations()->getNearestByAddress(PickUpNearAddressRequest)` → `PickUpLocationsResponse`
- By coordinates (secondary, if lat/long available): `$client->coordinateLocations()->getNearestByCoordinates(PickUpNearCoordinatesRequest)` → `PickUpLocationsResponse`

### SDK methods/classes involved
- `Client::addressLocations()->getNearestByAddress(PickUpNearAddressRequest)`
- `Client::coordinateLocations()->getNearestByCoordinates(PickUpNearCoordinatesRequest)`
- `Postnl\Sdk\RequestData\V4\Locations\PickUpNearAddressRequest`
- `Postnl\Sdk\RequestData\V4\Locations\PickUpNearCoordinatesRequest`
- `Postnl\Sdk\ResponseData\V4\Locations\PickUpLocationsResponse`

### Data mapping notes

| Plugin / order data | SDK field | Note |
|---|---|---|
| Customer address | `receiverAddress` | Map from session / `Item_Info` |
| Number of locations | `numberOfLocations` | From settings; min 1, max 10. **Currently hardcoded in settings** — map as-is, do not change behavior |
| Location type | `locationType` | `Retail` or `ParcelLocker` from settings |
| Pickup date | `pickUpDate` | Delivery date at pickup location |
| Customer country | `receiverCountryIso` | For coordinates call |
| Customer code / number | `customerCode`, `customerNumber` | Settings |

### Fallback/old-client behavior
- Old `POST /shipment/v1/checkout` call remains in the class as the else branch of the Router check.
- Delivery-day portion of old call handled separately in Task 3.
- Old client is not used when the SDK path is enabled and validated; it remains available as fallback at all times.
- Disable `Router::use_sdk_for('locations')` to restore old behavior immediately — no rollback PR needed.
- Old client remains available as fallback until staging parity confirmed.

### Unit tests
- Mock `ClientFactory`; verify `PickUpNearAddressRequest` built from customer address fields.
- Verify `PickUpLocationsResponse` maps to existing pickup-point frontend format.
- Coordinates-based call falls back to address-based when lat/long unavailable.
- SDK error does not crash checkout; returns empty pickup list gracefully.

### Integration/staging tests
- Trigger pickup-point load for a NL address on staging.
- Verify pickup points appear with correct location data.
- Compare pickup-point list to old API output for same address.

### Manual QA checks
- Classic checkout: enter NL address, verify pickup-point tab/list loads.
- Blocks checkout: verify pickup-point list loads.
- Select a pickup point, place order; verify saved to `_postnl_order_metadata`.

### Acceptance criteria
- Pickup points display via SDK on staging.
- Location list matches old API output for same inputs (parity check).
- Classic and blocks checkout both work.
- Old client remains available as fallback until staging parity confirmed.
- Old client is not used when the SDK path is enabled and validated.

### Dependencies/blockers
- Task 1 merged.
- Always test both classic and blocks checkout modes.

### Notes for reviewer
- `numberOfLocations` is currently hardcoded in `Settings` (see `agents.md` known tech debt note). Do not change this; just map it as-is.
- `Frontend\Dropoff_Points` may expect specific field names in the response. Adapter must preserve those exactly.

---

## Task 5 — Checkout Aggregation

**Status:** Ready after Tasks 3 + 4 staging parity confirmed | **Depends on:** Task 3 + Task 4
**Estimate:** Track A 3–5 h | Track B 3–5 h

### Goal
Remove the last reference to the old `POST /shipment/v1/checkout` call and replace it with an aggregation of the SDK TimeFrame (Task 3) and Locations (Task 4) calls. Merge only after both Tasks 3 and 4 pass staging parity checks independently.

### Current behavior
- `Rest_API\Checkout\Client` makes one `POST /shipment/v1/checkout` call that returns delivery days + pickup points in a single combined response.

### Target behavior
- `Checkout\Client` aggregates: one TimeFrame call + one Locations call → merged response.
- Response shape seen by `Frontend\Container`, `Frontend\Delivery_Day`, `Frontend\Dropoff_Points` is unchanged.
- Old checkout call is removed (Tasks 3 + 4 are the individually validated replacements).

### Scope
- `src/Rest_API/Checkout/Client.php` — remove old call, add aggregation method.
- `src/Frontend/Container.php` — read-only verification only; no changes expected.

### Out of scope
- `src/Rest_API/Postcode_Check/Client.php` — permanent old client; not touched here.
- Delivery-day or pickup-point response adapters — already handled in Tasks 3 + 4.
- Template changes, JS changes.

### Files/classes likely touched
- `src/Rest_API/Checkout/Client.php`

### Implementation details
- Call TimeFrame and Locations sequentially (or with isolated error handling).
- If TimeFrame fails: return empty delivery days; still return pickup points.
- If Locations fails: return empty pickup points; still return delivery days.
- **There is no `checkout()` method on the SDK client.** Do not look for one.
- **There is no standalone `DeliveryDate` V4 SDK service.** Do not look for one.
- Postal-code check (`Postcode_Check/Client.php`) stays on old client; do not call from here.

### SDK methods/classes involved
- Same as Task 3 (TimeFrame) and Task 4 (Locations) — no new SDK methods.

### Data mapping notes
- No new mapping. All mapping handled in Tasks 3 + 4.

### Fallback/old-client behavior
- Old checkout call removed in this PR (individual replacements already validated).
- If a full rollback is needed, revert this PR entirely to restore old call.

### Unit tests
- Aggregated result combines TimeFrame + Locations into expected combined shape.
- TimeFrame failure is isolated: Locations still returns, no fatal error.
- Locations failure is isolated: TimeFrame still returns, no fatal error.
- Both fail: empty response returned gracefully, no PHP fatal.

### Integration/staging tests
- Full checkout end-to-end: address entry → delivery-day options → pickup-point options → select → place order → verify `_postnl_order_metadata`.
- Toggle delivery-day setting off: only pickup points appear.
- Toggle pickup-point setting off: only delivery days appear.

### Manual QA checks
- Classic checkout: complete a full checkout from address entry to order placed.
- Blocks checkout: same.
- Verify delivery date and pickup point saved correctly to order.
- Verify postal-code validation still works (old client — confirm no regression).
- Verify Fill In With PostNL still works (old client — confirm no regression).

### Acceptance criteria
- No call to `POST /shipment/v1/checkout` anywhere in the codebase after this PR.
- Full checkout flow works end-to-end on staging (classic + blocks).
- Postal-code check behavior unchanged.
- No regression in fee calculation or delivery-option display.
- Delivery-day fees and tax display match pre-migration behavior (verify `taxRatio` logic).

### Dependencies/blockers
- Task 3 staging parity confirmed.
- Task 4 staging parity confirmed.
- Both classic and blocks checkout tested.

### Notes for reviewer
- This is a small, clean PR. The aggregation logic is the only real change. If Tasks 3 + 4 are stable, this should be low risk.
- Re-confirm `Container.php` tax/fee back-calculation is unaffected (see `agents.md` tax display architecture — `taxRatio` invariant must hold).

---

## Task 6 — Shipping + Letterbox Labels ⛔ BLOCKED

**Status:** Blocked — requires product/options → V4 field mapping table from PostNL/Joris
**Depends on:** Task 1 + mapping input confirmed in writing
**Estimate:** Track A 16–24 h | Track B 28–40 h

### Goal
Migrate all shipping and letterbox label generation to the SDK `shipmentDelivery()->labelConfirm()`. Covers all product types: NL domestic, BE, EU, ROW, multicollo, insured, signature, age check, ADR LQ, pickup label, guaranteed delivery, evening delivery, letterbox. Old clients remain as fallback per product type until staging parity confirmed.

### Current behavior
- `Rest_API\Shipping\Client` calls `POST /v1/shipment?confirm=true`.
- `Rest_API\Letterbox\Client` extends `Shipping\Client`, same endpoint.
- Product type determined by `Helper\Mapping::products_data()` based on origin, destination, and selected options.
- Request body: old `ProductCodeDelivery`, `ProductOptions`, `Dimension`, `Addresses` structure.

### Target behavior
- Both clients call `ClientFactory::get_client()->shipmentDelivery()->labelConfirm(ShipmentDeliveryRequest)`.
- Old clients preserved as fallback until each product type is individually validated on staging.
- `Helper\Mapping` extended with V4 mapping methods alongside existing old methods (old methods not removed).

### Scope
- `src/Rest_API/Shipping/Client.php`.
- `src/Rest_API/Letterbox/Client.php`.
- `src/Helper/Mapping.php` — new V4 mapping methods added.

### Out of scope
- Return labels (Task 7).
- Smart Returns (Task 8).
- `src/Order/Single.php` and `src/Order/Bulk.php` callers — interface unchanged.
- `_postnl_order_metadata` structure — not changed.

### Files/classes likely touched
- `src/Rest_API/Shipping/Client.php`
- `src/Rest_API/Letterbox/Client.php`
- `src/Helper/Mapping.php`

### Implementation details
- SDK call: `$factory->get_client()->shipmentDelivery()->labelConfirm(ShipmentDeliveryRequest)` → `LabelConfirmResponse`
- V4 request uses `receiver`, `sender`, `items[]`, `labelSettings`, `shipmentType`, `services`, `deliveryWindow`, `returnOptions`.
- Old `Mapping::products_data()` must remain intact — do not remove.
- Add V4 mapping as a new method in `Mapping.php`, following the same structure as existing methods.
- Migrate one product type at a time; validate each on staging before switching next.

### SDK methods/classes involved
- `Client::shipmentDelivery()->labelConfirm(ShipmentDeliveryRequest)` → `LabelConfirmResponse`
- `Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest`
- Supporting V4 request objects: `ShipmentParty`, `Contact`, `Address`, `ShippingItem`, `Dimensions`, `Services`, `LabelSettings`, `ReturnOptions`, `DeliveryWindow`

### Data mapping notes (to be confirmed with PostNL before starting)

| Old option | V4 field | Note |
|---|---|---|
| `ProductCodeDelivery` (domestic parcel) | `shipmentType = parcel` | |
| `ProductCodeDelivery` (letterbox) | `shipmentType = letterbox` | |
| Insured + amount | `services.insuredValue` (float) | |
| Return when not home | `services.returnWhenNotHome` (bool) | |
| Stated address only | `services.statedAddressOnly` (bool) | |
| Signature | `services.deliveryConfirmation = signature` | |
| Delivery code | `services.deliveryConfirmation = deliverycode` | |
| Age check 16+ | `services.minimalAgeCheck = 16+` | |
| Age check 18+ | `services.minimalAgeCheck = 18+` | |
| ADR LQ | `services.adrLq` | **Casing must be confirmed with PostNL** |
| Evening | `services.deliveryWindow.service = evening` | |
| Guaranteed 10:00 / 17:00 | `services.deliveryWindow.guaranteedBefore` | `12:00` has open SDK bug AITS-382 |
| LiB | `returnOptions.labelType = labelinthebox` | |
| `Dimension.Weight` | `items[0].dimensions.weight` (grams) | SDK property: `weightGr`, API key: `weight` |
| Old `Addresses` array | `sender` + `receiver` objects | Restructured in V4 |
| Multicollo | Multiple entries in `items[]` | One `ShippingItem` per parcel |

### Fallback/old-client behavior
- Old `Shipping\Client` and `Letterbox\Client` HTTP calls preserved as fallback.
- Old `Mapping::products_data()` method not removed.
- Each product type validated individually on staging before switching; revert per type if parity fails.
- No full rollback PR needed — fallback available per product type.

### Unit tests
- One test per product type: NL parcel, insured, signature, age check 16+, age check 18+, ADR LQ, evening, guaranteed 10:00, guaranteed 17:00, letterbox, NL-BE, EU, ROW, multicollo (2 items).
- Mock SDK client; verify `ShipmentDeliveryRequest` built correctly per variant.
- Label binary content not logged (sanitize check against `Logger`).
- Multicollo: `items[]` count matches expected number; each item has correct barcode + dimensions.

### Integration/staging tests
- Download label for each product type on PostNL sandbox.
- Compare label content and barcode format to old client output for same order data.
- Multicollo: verify correct number of barcodes generated.
- ADR LQ: verify option reflected on label (after casing confirmed).

### Manual QA checks
- NL domestic parcel: print label, barcode scannable.
- Insured parcel: verify insured option on label.
- Letterbox parcel: verify letterbox label format.
- NL-BE parcel: verify label format.
- Multicollo (2 items): verify two separate barcodes generated.
- Age check 18+: verify option reflected.

### Acceptance criteria
- All product types generate correct labels on PostNL sandbox (confirmed per type, not globally).
- Old HTTP clients preserved as fallback for each product type until per-type parity confirmed.
- Old `Mapping::products_data()` intact.
- Label binary not in log output.

### Dependencies/blockers
- Task 1 merged and Composer build validated.
- **Product code / ProductOptions → V4 `shipmentType` + `services` mapping table received from PostNL/Joris and agreed in writing before implementation starts.**
- `services.adrLq` casing confirmed by PostNL.
- AITS-382 status confirmed (guaranteed-before `12:00`).

### Notes for reviewer
- Highest-risk task in the migration. Do not start without the written mapping table.
- `Mapping.php` is the authoritative product code source per `agents.md`. New V4 methods must follow the same structure as existing methods.
- Per-type staging sign-off is required before any old client code is removed.

---

## Task 7 — Return Labels (SDK POC)

**Status:** Ready (standard NL/BE return + LiB) | **Depends on:** Task 1
**Estimate:** Track A 8–12 h | Track B 18–24 h

### Goal
Migrate standard return label generation to the SDK `returnShipment()->generateReturn()`. Covers NL-NL, NL-BE, BE-NL, BE-BE standard returns and Label-in-Box. Old client remains available as fallback until staging parity is confirmed. Smart Returns client is not touched.

### Current behavior
- `Rest_API\Return_Label\Client` calls `POST /v1/shipment?confirm=true` with return-specific product codes.
- Shares the base shipping endpoint; return behavior determined by product code.
- Return label PDF stored in `wp-content/uploads/postnl/`.

### Target behavior
- `Return_Label\Client` checks `Router::use_sdk_for('return_labels')` — if true, calls SDK; otherwise falls through to old HTTP call.
- Standard NL/BE returns and LiB covered in this task.
- Old client remains available as fallback until staging parity confirmed.
- `Smart_Returns/Client.php` entirely unchanged.

### Scope
- `src/Rest_API/Return_Label/Client.php`.

### Out of scope
- `src/Rest_API/Smart_Returns/Client.php` — not touched (Task 8, blocked).
- `src/Rest_API/Shipment_and_Return/Client.php` — not touched (Task 9, blocked).
- Return label PDF storage path and `_postnl_order_metadata` — not changed.
- `WC_Email_Smart_Return` — not touched.

### Files/classes likely touched
- `src/Rest_API/Return_Label/Client.php`

### Implementation details
- Check `Router::use_sdk_for('return_labels')` — if false, fall through to old HTTP call (default until staging validated).
- When SDK path is active: build `ReturnShipmentRequest` from mapped fields, call `$factory->get_client()->returnShipment()->generateReturn($request)`, catch `PostnlExceptionInterface` inside the SDK branch.
- LiB: set `returnOptions.labelType = labelinthebox`, include `returnOptions.returnBarcode`.
- Print method: map from plugin settings to `returnOptions.printMethod` (`consumerPrint` / `retailPrint`).
- Old HTTP call remains in the class as the else branch — not removed.
- Preserve existing label PDF output path and download behavior.

### SDK methods/classes involved
- `Client::returnShipment()->generateReturn(ReturnShipmentRequest)` → `GenerateReturnResponse`
- `Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest`
- `Postnl\Sdk\ResponseData\V4\ReturnShipment\GenerateReturnResponse`

### Data mapping notes

| Old field | SDK field | Note |
|---|---|---|
| Return address (merchant) | `receiver` | Merchant address for returns |
| Sender (customer) | `sender` | Consumer contact + address |
| Product code (return) | `returnOptions.domestic.returnPeriod` | Map to 20/35/100/200/365 days |
| Valuable return flag | `returnOptions.domestic.valuableReturn` (bool) | |
| LiB product code | `returnOptions.labelType = labelinthebox` | |
| LiB return barcode | `returnOptions.returnBarcode` | String |
| Print method setting | `returnOptions.printMethod` | `consumerPrint` / `retailPrint` |
| Label output type | `labelSettings.outputType` | Match existing settings (PDF, ZPL, etc.) |
| Label resolution | `labelSettings.resolution` | Match settings |
| Page orientation | `labelSettings.pageOrientation` | Match settings |

### Fallback/old-client behavior
- Old return HTTP call remains in the class as the else branch of the Router check.
- Old client is not used when the SDK path is enabled and validated; it remains available as fallback at all times.
- `Smart_Returns/Client.php` entirely unaffected.
- Disable `Router::use_sdk_for('return_labels')` to restore old behavior per type immediately — no rollback PR needed.
- Old client remains available as fallback until staging parity confirmed.

### Unit tests
- NL-NL standard return: `ReturnShipmentRequest` built correctly.
- NL-BE and BE-NL combinations.
- LiB: `returnOptions.labelType` and `returnOptions.returnBarcode` present.
- Valuable return: `returnOptions.domestic.valuableReturn = true`.
- Missing `receiver` address triggers caught `ValidationException`.

### Integration/staging tests
- Generate NL domestic return label on sandbox; verify download and barcode valid.
- Generate LiB return label; verify return barcode present.
- Compare return label output to old client output for same order data.

### Manual QA checks
- Generate return label for a NL domestic order in staging admin; verify PDF downloads.
- Generate LiB return label; verify QR/barcode for customer use.
- Verify return label stored at expected path in `wp-content/uploads/postnl/`.

### Acceptance criteria
- Standard NL/BE return labels generate via SDK on staging.
- LiB return label includes return barcode.
- Old client remains available as fallback until staging parity confirmed.
- Old client is not used when the SDK path is enabled and validated.
- Smart Returns behavior completely unchanged.

### Dependencies/blockers
- Task 1 merged.
- Confirm return period mapping from old product codes to V4 `returnPeriod` values.

### Notes for reviewer
- Verify `Smart_Returns/Client.php` is **not** touched — check diff carefully before approving.
- `WC_Email_Smart_Return` is only for Smart Returns, not standard returns — confirm no side effects.
- Return label PDF path behavior must remain unchanged; verify `_postnl_order_metadata` saves identically.

---

## Task 8 — Smart Returns ⛔ BLOCKED

**Status:** Blocked — PostNL must confirm V4 `return/generate` replaces old `POST /shipment/v2_2/label/`
**Depends on:** Task 1 + PostNL written confirmation
**Estimate:** Track A 4–6 h | Track B 10–14 h

### Goal
Migrate Smart Returns barcode generation to the SDK `returnShipment()->generateReturn()`, replacing the old V2.2 label API call. Only start after PostNL confirms full behavioral equivalence in writing.

### Current behavior
- `Rest_API\Smart_Returns\Client` calls `POST /shipment/v2_2/label/` with Smart Returns product code.
- Returns a barcode (no PDF); barcode is sent to customer via `WC_Email_Smart_Return`.
- Triggered from admin via `postnl_send_smart_return_email` AJAX action.

### Target behavior
- `Smart_Returns\Client` calls `ClientFactory::get_client()->returnShipment()->generateReturn(ReturnShipmentRequest)`.
- Barcode returned from response; customer email behavior preserved.
- Old call preserved as fallback until staging parity confirmed.

### Scope
- `src/Rest_API/Smart_Returns/Client.php`.

### Out of scope
- `src/Emails/WC_Email_Smart_Return.php` — not changed.
- `templates/emails/` — not changed.
- Return labels (Task 7) — separate flow.

### Files/classes likely touched
- `src/Rest_API/Smart_Returns/Client.php`

### Implementation details
- Same SDK call as Task 7: `$factory->get_client()->returnShipment()->generateReturn(ReturnShipmentRequest)` → `GenerateReturnResponse`.
- Smart Returns-specific field mapping to be defined after PostNL confirmation.
- `Router::use_sdk_for('smart_returns')` remains false until PostNL confirmation is received; old call remains active as fallback.

### SDK methods/classes involved
- `Client::returnShipment()->generateReturn(ReturnShipmentRequest)` → `GenerateReturnResponse`

### Data mapping notes
- To be defined after PostNL confirmation.
- Old body: product-code-based Smart Returns request to `v2_2/label`.
- New: `ReturnShipmentRequest` with confirmed Smart Returns fields (print method, return period, etc.).

### Fallback/old-client behavior
- Old `POST /shipment/v2_2/label/` call preserved as active call and remains so until this task is unblocked and staging parity confirmed.
- No change to this client until written PostNL confirmation is received.

### Unit tests (after confirmation)
- Mock SDK `generateReturn()`; verify Smart Returns request fields match confirmed mapping.
- Verify customer barcode extracted correctly from `GenerateReturnResponse`.

### Integration/staging tests
- Generate Smart Returns barcode on PostNL sandbox; verify format.
- Trigger Smart Returns email flow; verify customer receives email with valid barcode.

### Manual QA checks
- Trigger Smart Returns email for a test order on staging.
- Verify customer receives email with valid barcode.
- Verify admin flow (AJAX trigger) works end-to-end.

### Acceptance criteria
- PostNL confirms V4 equivalence in writing before this task starts.
- Smart Returns barcode generates via SDK on staging.
- Customer email flow unchanged.
- Old client preserved as fallback until staging parity confirmed.

### Dependencies/blockers
- Task 1 merged.
- **PostNL must confirm in writing: V4 `POST /shipment/delivery/v4/return/generate` fully replaces `POST /shipment/v2_2/label/` for Smart Returns** — including label format, return period behavior, and customer notification side effects.

### Notes for reviewer
- Do not start implementation until written PostNL confirmation is in hand.
- Verify `WC_Email_Smart_Return` is not changed — only the API call providing the barcode changes.

---

## Task 9 — activatereturn ⛔ BLOCKED

**Status:** Blocked — SDK has no service for V4 activate; PostNL decision required
**Depends on:** Task 1 (Option A only) + PostNL/Joris decision
**Estimate:** Option A (SDK extension) 4–8 h | Option B (old client retained) 0 h | Track B delta for Option A +4–6 h

### Goal
Update the activatereturn flow to V4 (via SDK extension) or explicitly confirm old-client retention. No implementation until the decision is received from PostNL/Joris.

### Current behavior
- `Rest_API\Shipment_and_Return\Client` calls `POST /parcels/v1/shipment/activatereturn`.
- Triggered from `Order\Single` via `postnl_activate_return_function` AJAX action.
- Sets `_postnl_return_activated` order meta on success.

### Target behavior
- **Option A:** New SDK extension class at `src/SDK/Extension/ActivateReturnExtension.php` implementing `ConfigurableAction`; calls `POST /shipment/delivery/v4/return/activate`.
- **Option B:** Old client retained as confirmed permanent fallback; decision documented in code comment.

### Scope (Option A — SDK extension)
- `src/SDK/Extension/ActivateReturnExtension.php` — new file.
- `src/Rest_API/Shipment_and_Return/Client.php` — swap active call.

### Scope (Option B — old client retained)
- `src/Rest_API/Shipment_and_Return/Client.php` — add code comment documenting retention decision.
- No functional change.

### Out of scope
- `src/Order/Single.php` AJAX handler — not changed in either option.
- Return labels (Task 7), Smart Returns (Task 8) — separate.

### Files/classes likely touched (Option A)
- `src/SDK/Extension/ActivateReturnExtension.php` — new
- `src/Rest_API/Shipment_and_Return/Client.php`

### Implementation details (Option A)
- Implement `ConfigurableAction` interface from SDK Extension system.
- Register via `$client->extensions()->register(new ActivateReturnExtension())`.
- Execute via `$client->extensions()->getAs(ActivateReturnExtension::class)->execute($request)`.
- Request body from Postman examples: `barcode`, `sender.customerNumber`, `source`, `label`.
- Reference working SDK extension: `postnl-sdk-audit/vendor/postnl/api-client-sdk/src/Service/Checkout/V1/Extension/PostalCodeCheckExtension.php`.

### SDK methods/classes involved (Option A)
- `Client::extensions()->register()`
- `Client::extensions()->getAs(ActivateReturnExtension::class)->execute()`
- `Postnl\Sdk\Service\Extension\ConfigurableAction` (SDK extension contract)

### Data mapping notes

| Old field | V4 field | Note |
|---|---|---|
| Barcode | `barcode` | Direct map |
| Customer number | `sender.customerNumber` | From settings |
| Source | `source` | Confirm value with PostNL |
| Label flag | `label` | Confirm behavior with PostNL |

### Fallback/old-client behavior
- Old `POST /parcels/v1/shipment/activatereturn` call remains active until decision is made.
- Option B explicitly retains old client as permanent fallback with no functional change.

### Unit tests (Option A)
- Mock SDK extensions; verify `ActivateReturnExtension` executes with correct request fields.
- Verify V4 activate endpoint called, not old parcels endpoint.
- `_postnl_return_activated` order meta set correctly on success.

### Integration/staging tests (Option A)
- Trigger activate return for a test order on PostNL sandbox.
- Verify response behavior (see Postman: `ActivateReturn denied no label`, `ActivateReturn warning Label`).

### Manual QA checks (Option A)
- Activate return for a test order in staging admin.
- Verify activation confirmed in order admin; verify `_postnl_return_activated` meta set.

### Acceptance criteria
- Decision documented and agreed with PostNL/Joris.
- Option A: activatereturn calls V4 endpoint on staging successfully.
- Option B: old client retention explicitly documented in code comment; behavior unchanged.

### Dependencies/blockers
- Task 1 merged (Option A only).
- **PostNL/Joris must answer:** Is `POST /shipment/delivery/v4/return/activate` equivalent to old `POST /parcels/v1/shipment/activatereturn`? Build SDK extension, keep old client, or drop?

### Notes for reviewer
- SDK Extension docs reference a `cache` field in context that does not exist in current code (`ServiceContext`). Do not follow SDK Extension README for this; read `PostalCodeCheckExtension.php` source directly as the reference implementation.
- Postman examples for `return/activate` show `ActivateReturn denied no label` and `ActivateReturn warning Label` responses — review these response shapes before Option A implementation.

---

## Full Staging QA Checklist

Run after Tasks 1–7 are merged. Run again after Task 6 labels are considered stable.

- [ ] Barcode: generate for NL domestic order; verify format
- [ ] Classic checkout: NL address → delivery-day slots appear (daytime + evening)
- [ ] Blocks checkout: NL address → delivery-day slots appear
- [ ] Classic checkout: NL address → pickup-point list appears
- [ ] Blocks checkout: NL address → pickup-point list appears
- [ ] Classic + blocks: select delivery day, place order; verify `_postnl_order_metadata` saved correctly
- [ ] Classic + blocks: select pickup point, place order; verify saved correctly
- [ ] Label — NL domestic parcel: downloads, barcode scannable
- [ ] Label — insured parcel: insurance option reflected
- [ ] Label — signature parcel: signature option reflected
- [ ] Label — letterbox: shipment type correct
- [ ] Label — NL-BE parcel: label format correct
- [ ] Label — multicollo (2+ items): all barcodes generated
- [ ] Return — NL domestic: label downloads, barcode valid
- [ ] Return — LiB: return barcode present in label
- [ ] Error: invalid API key → admin error message, no raw SDK exception output
- [ ] Sandbox toggle: requests route to `api-sandbox.postnl.nl`
- [ ] Logs: no API key, label binary, or PII visible in WC log output
- [ ] Postal-code check: still works (old client — confirm no regression)
- [ ] Fill In With PostNL: still works (old client — confirm no regression)

---

## Overall Acceptance Criteria

1. All flows in the routing table marked SDK are using the SDK. Old-client flows use the old HTTP client.
2. Old clients preserved as fallback for each SDK flow until staging parity confirmed per flow.
3. PHP ≥ 8.2 release/hosting decision resolved; plugin PHP minimum updated if needed.
4. SDK Composer build validated in WordPress plugin context; no autoload conflicts.
5. No API key, label binary, or customer PII in WC log output.
6. Full staging QA checklist passes.
7. Classic and blocks checkout both work end-to-end.
8. SDK requests and old-client requests are distinguishable in log entries.

---

## Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Hosting / plugin PHP < 8.2 | Blocks SDK in production | Resolve PHP decision before Task 1 ships; develop on PHP 8.2 |
| SDK docs / code mismatches | Wrong method names fail at runtime | Use code not docs; mismatch list in reference §11 |
| Product mapping incomplete or wrong | PR6 labels wrong or API rejects | PostNL signs off on mapping table in writing before Task 6 starts |
| Checkout aggregation shape change | Breaks checkout display or fee calculation | Response adapters in Tasks 3+4; verify `taxRatio` logic in `Container.php` |
| Smart Returns V4 differs from V2.2 | Wrong customer return flow | Keep old client; Task 8 blocked until PostNL confirms |
| `adrLq` casing mismatch | ADR LQ option silently ignored | PostNL to confirm casing before Task 6 |
| SDK version unpinned | SDK update breaks plugin at deploy | Pin version in `composer.json`; review SDK changelog on any update |
| Composer dependency conflict | Plugin fails to load on activation | Validate full Composer build in WordPress context as part of Task 1 |
