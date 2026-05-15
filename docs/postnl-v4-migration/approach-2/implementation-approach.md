# Approach 2 — Implementation Approach

## TL;DR

Build a thin interface layer above the existing REST clients. Two implementations per flow: `Legacy` (existing HTTP code, unchanged) and `V4` (new SDK-backed). A `Service_Factory` picks one based on V4-key presence plus a per-flow filter gate. Callers stop knowing about transports.

Migration is **opt-in per site** (driven by whether the merchant has filled in the V4 API key field) and **opt-in per flow** (driven by per-flow filters that the dev team controls). Legacy code path is permanent — not transitional.

## Design rationale

The previous plan grafted SDK branches *inside* existing client classes (`if ( Router::use_sdk_for() ) { ... }`). That approach entangles two transports and two payload shapes in the same file, makes testing harder, and makes future deletion impossible.

The chosen alternative — interfaces with two implementations — has these properties:

- **Compile-time contract.** Both implementations satisfy the same interface; return-shape drift becomes an interface violation, not a runtime surprise.
- **Legacy code untouched.** No behavioral changes to current V1 paths. Sites without a V4 key are bit-identical to today.
- **Independent testability.** V4 services can be unit-tested with mocked SDK clients. Legacy clients keep their existing test coverage (or lack thereof) unchanged.
- **Modern PHP for new code.** V4 services can use readonly DTOs, typed properties, enums — without retrofitting legacy code to PHP 8.2 idioms.
- **Clean deletion path.** When V4 reaches 100% adoption, removing the `Legacy/` directory is mechanical.

The cost is one wrapping pass over existing client classes plus seven small interface files. That's Phase 1.2 (~8–12h).

## Phasing

Phases are sequential. Phase 0 must complete before Phase 2 starts; Phase 1 can overlap Phase 0.1 once the interfaces are settled.

### Phase 0 — Mapper extraction (the contract)

Extract the 72 product-code combinations in `src/Helper/Mapping.php` into a `Helper/Product_Mapper/V1_Mapper.php` class with unit tests covering every combination. This becomes the **acceptance criteria for the V4 mapper**: every legacy code must map to a V4 `ShipmentType` + `Services` combination that PostNL confirms produces the same label.

This is the longest-lead-time piece (because PostNL confirmation is part of it) and must finish before Phase 2.6 (Shipping label) can start.

### Phase 1 — Foundations

In order:

1. Define seven interfaces in `src/Rest_API/Contracts/`.
2. Move existing `Client` classes into `src/Rest_API/Legacy/` and add `implements <Flow>_Service_Interface`.
3. Build `Service_Factory`, `Router`, `SDK\Client_Factory`, `SDK\Logger_Adapter`, `SDK\Cache_Adapter`, `SDK\Exception_Converter`.
4. Cut callers (`Order/Base.php`, `Order/Single.php`, AJAX handlers) over to the factory. **At end of Phase 1, all flows still go through the legacy clients** — V4 implementations don't exist yet. This is a refactor PR with zero behavior change.
5. Establish PHPUnit harness if not already in place; integration tests covering each Legacy implementation against the interface contract.

End-of-phase test: turn off legacy entirely and the plugin should error cleanly in every flow. Turn it back on and everything works as today.

### Phase 2 — Per-flow V4 implementations

Each flow is its own PR. Order from lowest to highest risk:

1. **2.1 Barcode** — simplest; pure utility.
2. **2.2 Timeframes** — checkout-critical; caching wired.
3. **2.3 Pickup Locations** — same risk profile as Timeframes.
4. **2.4 Checkout aggregation** — consumer-level refactor; V1 returned both flows in one call, V4 needs two. Affects `Checkout_Blocks/Extend_Block_Core.php` and `Frontend/Container.php`.
5. **2.5 Postcode check** — uses SDK's V1 `PostalCodeCheckExtension`; no real V4 equivalent exists.
6. **2.6 Shipping label** — the big one; depends on Phase 0.1 + PostNL mapping sign-off.
7. **2.7 Return label** — depends on 2.6.
8. **2.8 Smart Returns** — start after PostNL confirms the V4 activation flow.
9. **2.9 Letterbox + Shipment_and_Return** — variants of 2.6; should fall out cleanly.

Each PR adds the V4 service, enables it behind a filter (default off), and ships. Once staging parity is confirmed, the team flips the filter to default on in a separate PR or by updating the default in `Router::sdk_enabled_for()`.

### Phase 3 — Cross-cutting

QA, documentation, filter audit, bug fixes from staging discoveries. Spread across all phases but bucketed for budget.

## Routing model

Two independent gates must both pass for a request to go through the SDK:

```php
public function barcode_service(): Barcode_Service_Interface {
	$v4_key = $this->settings->get_v4_api_key();
	if ( '' !== $v4_key && Router::sdk_enabled_for( 'barcode' ) ) {
		return new V4\Barcode\Service( $this->sdk_client_factory->build( $v4_key, $this->settings->is_sandbox() ) );
	}
	return new Legacy\Barcode\Client( $this->settings );
}
```

### Gate 1 — V4 key presence

The merchant has entered a V4 API key. Until they do, **nothing changes** — every flow still uses the legacy client and the legacy API key. The new key field (added in a separate PR already in flight) exists alongside the legacy key field, not in place of it. Both keys coexist; the legacy key is still used for any flow that isn't routed to V4.

### Gate 2 — Per-flow filter

```php
apply_filters( "postnl_sdk_enable_{$flow}", false );
```

Filter is `false` by default for every flow. The plugin's own bootstrap can flip individual filters to `true` once a flow has reached staging parity:

```php
add_filter( 'postnl_sdk_enable_barcode', '__return_true' );
```

This allows:

- **Staged rollout.** Enable barcode for one site; observe; expand.
- **Per-site override.** A site operator can disable a specific flow by returning `false` from the filter in `functions.php`.
- **Emergency revert.** Set every filter to `false` via a single bootstrap edit — V4 turns off everywhere without a code revert.

### Sandbox vs production

`SDK\Client_Factory::build()` reads the `environment_mode` setting (which already exists for legacy) and passes the matching V4 key. Sandbox/production toggle is orthogonal to V4 routing.

## Backward compatibility

### Legacy-key sites: zero behavioral change

No V4 key → V4 services never instantiated → no SDK code runs. The legacy code paths are exactly the same as before this migration. Risk envelope for these sites is the same as a no-op refactor (the file moves into `Legacy/` and gains an interface declaration; no logic changes).

### V4-key sites: opt-in per flow

The merchant has the V4 key but the per-flow filter is still off → still uses the legacy client with the legacy key (which their new V4 key also works for, since new keys work with both APIs). They're paying nothing for entering the V4 key until the dev team flips a filter.

### Filters and actions

The existing plugin exposes several public filters that third-party extensions depend on:

- `postnl_shipment_addresses` (in `Shipping/Client.php`)
- `postnl_order_weight` (multiple files)
- `postnl_order_meta_box_fields`
- `postnl_logger_write_message`

Each V4 service implementation must fire the equivalent filter at the equivalent point in request construction with the same parameter shape. This is Phase 3.1 work and must be audited per-flow before that flow's V4 implementation is enabled.

### Order metadata

`_postnl_order_metadata` shape stays identical. V4 services map their response back to the existing structure: `barcodes[]`, `labels[]`, `backend{}`, `frontend{}`. Existing orders processed under V1 remain readable; new orders processed under V4 store data in the same keys.

### In-flight orders

An order whose barcode was generated by V1 but whose label generation happens after V4 is enabled: the stored barcode (V1 shape) goes into the V4 `items[].barcode` field. **Confirmation needed from PostNL** (see open-questions doc) that V4 accepts pre-existing V1-format barcodes.

## Test strategy

### Phase 0 — mapper

Unit tests for all 72 product-code combinations against the extracted `V1_Mapper`. These tests establish the contract that the V4 mapper must satisfy.

### Phase 1 — interfaces

Contract tests: a generic suite that, given any implementation of a service interface, verifies it accepts the expected input shape and produces the expected output shape. Both Legacy adapters and V4 services run this suite.

### Phase 2 — per-flow

For each V4 service:

- **Unit tests** against a mocked `PostnlClientInterface`. Verifies request DTO construction is correct and response mapping is correct.
- **Integration tests** against the PostNL sandbox (gated to CI environments with credentials).
- **Staging parity tests.** With V4 enabled, run the same operation (create order → barcode → label) on staging and compare order meta to a baseline produced by V1. Differences must be intentional and documented.

Each flow's filter does not flip to default-on until staging parity is confirmed.

### Phase 3 — manual QA

Classic checkout + Blocks checkout, both. Multicollo, customs declaration, insured shipping, ID check, signature on delivery, letterbox auto-detection, pickup-point selection, smart returns activation.

## Rollout & rollback

### Rollout

1. Ship Phase 1 (refactor only). Zero behavioral change.
2. Ship Phase 2.1 (Barcode V4). Filter default `false`.
3. Enable barcode filter on internal/staging sites with V4 keys.
4. Validate for one week; check support tickets.
5. Flip barcode default to `true` in `Router::sdk_enabled_for()` for next minor release.
6. Repeat for next flow.

### Rollback levels (in order of preference)

1. **Per-site, per-flow:** site operator adds `add_filter( 'postnl_sdk_enable_barcode', '__return_false' )` in `functions.php` — instant.
2. **Per-site, all flows:** site operator clears the V4 API key field in settings — instant.
3. **Per-flow, globally:** flip the default in the plugin's `Router::sdk_enabled_for()` from `true` back to `false`; ship a patch release — minutes to deploy.
4. **All flows, globally:** revert the PR that enabled defaults — minutes.

No rollback requires data migration, schema change, or order re-processing. The Legacy code path stays installed and functional throughout.

## Cross-cutting concerns

### Logging & redaction

- **Legacy path** continues to use `Logger.php::check_pdf_content()` — V1-shaped, scans `Shipments[].Labels[].Content`. Unchanged.
- **V4 path** uses the SDK's built-in `RedactionRegistry::forProduction()` (on by default), which redacts label binary, PII, and credentials before messages reach the PSR-3 adapter. The plugin's `WC_Logger`-backed adapter just writes whatever the SDK gives it.

Both paths log to the same WC log channel with a tag distinguishing them (`[postnl-legacy]` vs `[postnl-v4]`) so support can filter on it.

### Caching

V4 timeframes and pickup-location calls are cached via SDK `CachingPlugin`:

- Backend: WP transients via `SDK\Cache_Adapter` (PSR-16).
- TTL: 600s (configurable via filter).
- Allowlist: only `/timeframe/` and `/locations/` endpoints.
- Key includes the V4 API key prefix to avoid cross-tenant collisions.

Legacy path uses whatever caching exists today (or none); not changed in this migration.

### Error mapping

`SDK\Exception_Converter` translates SDK exception hierarchy → plugin's existing error shape:

- `HttpSdkException::getCode()` carries the HTTP status code — preserved.
- PostNL error details from `ProblemDetails` (including `traceId`) are extracted and included in the converted exception message for support correlation.
- `AuthenticationException` → "Invalid PostNL API credentials" (user-facing).
- `ValidationException` → bubble the field-level errors from `ProblemDetails`.
- `RateLimitException`, `TimeoutException` → "PostNL temporarily unavailable, please try again."

Legacy error handling unchanged.

### Order metadata API-version tagging

Each label/barcode written by a V4 service tags itself in order meta:

```php
$metadata['labels'][0]['api_version'] = 'v4';
$metadata['barcodes'][0]['api_version'] = 'v4';
```

Absent for V1-generated entries (or backfilled as `'v1'` lazily). Allows support to determine, six months from now, which transport produced any given barcode.

## Open items handed off to PostNL

See `postnl-open-questions.md` for the full list. The single most important one is the product-code mapping confirmation — without it, Phase 2.6 cannot start.
