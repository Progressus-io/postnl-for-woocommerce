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
| `postnl_shipment_addresses` | `Legacy\Shipping\Client::get_shipment_addresses()` | `( array $addresses, Shipping\Client $client )` | **Fired (new)** — `V4\Label\Service::filter_shipment_addresses()` reuses the legacy address builder verbatim, then overlays the filtered recipient back onto the request | n/a — legacy returns never fired it | n/a — legacy smart returns never fired it |
| `postnl_order_weight` | `Legacy\Shipping\Item_Info::calculate_order_weight()` | `( float $total_weight, WC_Order $order )` | Fires — V4 service builds the shared `Shipping\Item_Info` | Fires — `Return_Label\Item_Info` extends `Shipping\Item_Info` | n/a — `Smart_Returns\Item_Info` extends `Base_Info`; legacy never fired it |
| `postnl_order_meta_box_fields` | `Order\Base::meta_box_fields()` | `( array $fields, string $context )` | Fires — shared admin meta box (not a per-service request step) | Fires — shared | n/a — smart returns is email-only |
| `postnl_logger_write_message` | `Logger::write()` | `( string $message )` | Fires — shared logger | Fires — shared | Fires — shared |

## Notes

- **`postnl_shipment_addresses` was the only gap.** The V4 `Request_Builder` is a
  pure DTO translator that assembles addresses from a flat field array, so it
  never routed through the legacy `Shipping\Client` where the filter fires. The
  V4 label service now calls the legacy `get_shipment_addresses()` purely to fire
  the filter with the identical `( array $addresses, Shipping\Client $client )`
  shape, and `Request_Builder::apply_filtered_receiver()` maps the (possibly
  modified) recipient entry back onto the receiver so third-party edits reach the
  request. The legacy returns and smart-returns flows never fired this filter, so
  their V4 counterparts intentionally do not either — carry-forward means match
  legacy, not add new surface.

- **`postnl_order_weight` carries forward for free.** Both the V4 label and V4
  returns services construct a `Shipping\Item_Info` (the returns item-info
  subclasses it), and weight is computed — and the filter fired — during that
  construction, before any V4-specific translation. Smart returns uses a
  `Base_Info` subclass that never computed order weight, so legacy never fired
  the filter there and neither does V4.

- **`postnl_order_meta_box_fields` and `postnl_logger_write_message` are shared.**
  They fire from `Order\Base` and `Logger`, which every service (legacy and V4)
  reuses unchanged, so they carry forward with no V4-specific work.

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

## Verification

- `tests/php/integration/FilterCarryForwardTest.php` asserts `postnl_order_weight`
  and `postnl_shipment_addresses` fire from the V4 label service's
  request-construction seam with the documented arguments, and that a third-party
  address modification is honoured rather than fired-and-ignored.
- `tests/php/unit/Rest_API/V4/Label/Request_BuilderTest.php` covers
  `apply_filtered_receiver()` — the pure mapping of a filtered recipient address
  back onto the receiver field set.
