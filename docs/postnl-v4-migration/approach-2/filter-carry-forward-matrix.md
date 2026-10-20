# Filter / action carry-forward matrix (V4 migration, task 25)

Third-party extension points must keep firing once a flow runs on the V4 SDK
path, with the **same parameter shape** as the legacy path. This documents where
each public filter/action fires in legacy, and how it carries forward to the V4
services (label, returns, smart returns) that exist today.

Two gates still guard every V4 path (a validated New API Key *and* the per-flow
`postnl_sdk_enable_{flow}` flag, both default off), so this changes nothing for
merchants until a flow is turned on.

## Matrix

| Extension point | Legacy fire point | Parameter shape | V4 label | V4 returns | V4 smart returns |
|---|---|---|---|---|---|
| `postnl_shipment_addresses` | `Legacy\Shipping\Client::get_shipment_addresses()` — inherited by `Legacy\Return_Label\Client`, so legacy **return labels fire it too** | `( array $addresses, Shipping\Client $client )` | **Fired (new)** — `V4\Label\Service::filter_shipment_addresses()` reuses the legacy address builder verbatim, then overlays the filtered recipient back onto the request | **Fires (fixed)** — `V4\Returns\Service::filter_shipment_addresses()` reuses `Legacy\Return_Label\Client` (which inherits `get_shipment_addresses()` and the filter), then overlays the filtered AddressType `01` entry — the consumer, who is the sender on a return — back onto the request. Same `( array $addresses, Shipping\Client $client )` shape as legacy. | n/a — legacy smart returns extends `Rest_API\Base`, not `Shipping\Client`, and never fired it |
| `postnl_order_weight` | `Legacy\Shipping\Item_Info::calculate_order_weight()` | `( float $total_weight, WC_Order $order )` | Fires — V4 service builds the shared `Shipping\Item_Info` | Fires — `Return_Label\Item_Info` extends `Shipping\Item_Info` | n/a — `Smart_Returns\Item_Info` extends `Base_Info`; legacy never fired it |
| `postnl_order_meta_box_fields` | `Order\Base::meta_box_fields()` | `( array $fields, string $context )` | Fires — shared admin meta box (not a per-service request step) | Fires — shared | n/a — smart returns is email-only |
| `postnl_logger_write_message` | `Logger::write()` (legacy); `SDK\Logger_Adapter::log()` (every V4 service) | `( string $message )` | **Fires (fixed)** — `Logger_Adapter::log()` applies it to the finished `[postnl-v4] …` line before writing. It did not fire before this task: the adapter wrote to `wc_get_logger()` directly and never passed through `Logger::write()`. | Fires (fixed) — same adapter | Fires (fixed) — same adapter |

## Notes

- **`postnl_shipment_addresses` on the label path.** The V4 `Request_Builder` is a
  pure DTO translator that assembles addresses from a flat field array, so it
  never routed through the legacy `Shipping\Client` where the filter fires. The
  V4 label service now calls the legacy `get_shipment_addresses()` purely to fire
  the filter with the identical `( array $addresses, Shipping\Client $client )`
  shape, and `Request_Builder::apply_filtered_receiver()` maps the (possibly
  modified) recipient entry back onto the receiver so third-party edits reach the
  request. Smart returns never fired this filter on legacy (`Smart_Returns\Client`
  extends `Rest_API\Base`, not `Shipping\Client`), so its V4 counterpart does not
  either — carry-forward means match legacy, not add new surface.

- **`postnl_shipment_addresses` on the returns path now fires.** Legacy return
  labels fire it: `Legacy\Return_Label\Client` extends `Shipping\Client`,
  overrides only `get_customer_address()`, and so inherits `get_shipments()` and
  the `apply_filters()` call, whose AddressType `01` entry is the consumer
  returning the parcel. `V4\Returns\Service::filter_shipment_addresses()` builds
  that same legacy client to fire the filter with the identical
  `( array $addresses, Shipping\Client $client )` shape, and overlays the filtered
  `01` entry back onto the sender — the consumer is the sender on a return — via
  the shared `V4\Label\Request_Builder::apply_filtered_receiver()`. The merchant
  return address travels in the legacy `Customer.Address` (AddressType `02`) block,
  which the filter never exposed on legacy either, so it is not overlaid. Only the
  `01` entry is honoured, matching the label path.

- **Eligibility is re-checked after the filter runs.** `Eligibility::is_eligible()`
  is decided on the unfiltered address, so a filter that rewrites the destination
  country (say NL to DE) would otherwise keep the domestic product code on a
  foreign address, and a code the SDK `Country` enum does not know (`'UK'`, `'NLD'`)
  would be reset to NL by `Request_Builder::country()`. Both the label and the
  return service now compare the country before and after the overlay; when it
  changed they log a warning and fall back to the legacy pipeline, which passes
  the string through and lets PostNL judge it — the pre-V4 behaviour for exactly
  the orders a filter touches in a way V4 does not model.

- **A bad filter return fails loudly on V4.** A `postnl_shipment_addresses`
  callback that forgets to `return` (so the filter yields `null`), or that sets a
  recipient field to an array or object, throws a plain `\Exception` naming the
  filter or the field. The AJAX handlers catch `\Exception` and show it in the
  meta box. Without this the `: array` return type and the `(string)` cast would
  produce a `TypeError`/`\Error` instead, which those handlers do not catch, and
  the merchant would see the label form greyed out with no message. Legacy sends
  the broken value to PostNL and shows PostNL's rejection.

- **`postnl_order_weight` carries forward for free.** Both the V4 label and V4
  returns services construct a `Shipping\Item_Info` (the returns item-info
  subclasses it), and weight is computed — and the filter fired — during that
  construction, before any V4-specific translation. Smart returns uses a
  `Base_Info` subclass that never computed order weight, so legacy never fired
  the filter there and neither does V4.

- **`postnl_order_meta_box_fields` is shared.** It fires from
  `Order\Base::meta_box_fields()`, which every service (legacy and V4) inherits
  unchanged, so it carries forward with no V4-specific work.

- **`postnl_logger_write_message` needed wiring.** Legacy applies it in
  `Logger::write()`. Every V4 service logs through `SDK\Logger_Adapter`, which
  wrote to `wc_get_logger()` directly and never passed through `Logger::write()`,
  so a merchant plugin that redacts or rewrites PostNL log lines saw none of the
  V4 output. The adapter now applies the filter to the finished, tagged line
  right before writing, with the same single-argument shape. It applies the
  filter itself rather than delegating to `Logger::write()` because that method
  writes at a single level with no context, and keeping the PSR-3 level and
  context is the adapter's job. The adapter still does not run
  `Logger::check_pdf_content()`: the SDK redacts label binary before a message
  reaches it, and the legacy `Labels[].Content` JSON shape that scan looks for
  never appears in V4 output.

## Known differences from legacy (in-scope)

- **Only the recipient (AddressType `01`) entry is applied on V4.** V4 currently
  handles only the happy-path domestic parcel — eligibility rejects pickup and
  return — so `apply_filtered_receiver()` reads the first `01` entry and ignores
  any `09` (pickup) / `08` (return) / second `01` entry a filter might add.
  Legacy forwards every entry to the API. This is acceptable for the migrated
  scope; when pickup/return migrate to V4 those entry types must be honoured too.

- **The filter's `$client` argument is a fresh instance, not the request sender.**
  The documented contract is to modify and return `$addresses`. A third party that
  instead mutated the `$client` object would affect the outbound request on legacy
  (same instance composes and sends it) but be a no-op on V4, where the client is
  built only to fire the filter with the identical shape. No known consumer does
  this.

- **An empty house number now travels as `addressLine`.** A filter that folds the
  house number into the street (`'Street' => 'Foo 12', 'HouseNr' => ''`, the usual
  Belgium/Germany pattern) makes legacy send `HouseNr: ""`. On V4,
  `Request_Builder::maybe_null()` would turn `''` into `null` and leave a
  street-only address the endpoint may reject. `Request_Builder::address()` now
  detects the numberless-but-present street and sends the street via the SDK
  `Address.addressLine` field — the combined-form field PostNL documents for
  exactly this case — omitting the split `street` so the two never conflict. A
  normal address (house number present) is unchanged. PostNL's acceptance of the
  `addressLine` form has not been confirmed against the live endpoint, so verify
  it in sandbox before flipping `postnl_sdk_enable_label`.

- **The contact block reads the filtered name on V4, not on legacy — kept by
  design.** Legacy `Contacts` carry only email and phone; the recipient name lives
  in the Addresses `01` block. The V4 `Address` DTO has no first/last-name field,
  so a recipient's name can only travel on `Contact`. `Request_Builder::contact()`
  therefore reads `first_name`, `last_name` and `company` from the same receiver
  array the filtered recipient was overlaid onto. This is deliberate: a filter that
  rewrites the recipient name must reach the printed label, and `Contact` is the
  only V4 field that holds a name. Forcing the contact name to stay unfiltered
  would silently drop legitimate name rewrites on V4, a regression, so this
  difference stands.

## Verification

- `tests/php/integration/FilterCarryForwardTest.php` drives the real
  `V4\Label\Service::create()` against a fake transport (the SDK client is built
  by the production `Client_Factory`, only the PSR-18 HTTP client is replaced)
  and asserts on the captured request body. It proves, for the V4 label path:
  `postnl_shipment_addresses` fires once with `( array, Shipping\Client )` and a
  rewritten street reaches the wire; `postnl_order_weight` fires with
  `( float, WC_Order )` and its return value is the weight sent;
  `postnl_logger_write_message` sees the line the service logs;
  `postnl_order_meta_box_fields` fires from the service with `( array, string )`;
  and a null or nested filter return fails with a plain `\Exception` before
  anything is sent. Deleting the `apply_filtered_receiver()` call in `create()`
  fails the address test; deleting the adapter's `apply_filters()` fails the
  logger test.
- `tests/php/unit/Rest_API/V4/Label/Request_BuilderTest.php` covers
  `apply_filtered_receiver()` — the pure mapping of a filtered recipient address
  back onto the receiver field set — with a fixture that differs from the
  receiver on every mapped field, so each field's mapping is individually pinned.
- `tests/php/unit/Rest_API/SDK/Logger_AdapterTest.php` pins that the adapter
  applies `postnl_logger_write_message` and writes the filter's return value.
- The shared fakes (`Spy_Label_Client_Factory`, `Failing_Http_Client`,
  `Client_Factory_Settings`) live in `tests/php/Support/` so both suites use one
  copy.
