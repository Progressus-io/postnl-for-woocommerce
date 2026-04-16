# PostNL for WooCommerce — Agent Context Document

> Auto-generated repository context for AI-assisted development.
> Generated: 2026-03-20 | Repo: Progressus-io/postnl-for-woocommerce | Commits analyzed: 1041

## Project Overview

PostNL for WooCommerce is the official WooCommerce plugin for PostNL, the primary postal/parcel carrier in the Netherlands and Belgium. It integrates WooCommerce order management with the PostNL API to automate shipping label generation, barcode creation, return label handling, and delivery option display at checkout. The plugin is distributed on the WordPress.org plugin repository as `woo-postnl`.

The plugin supports shipments originating from either the Netherlands (NL) or Belgium (BE), with destinations ranging from domestic NL/BE to EU and non-EU (global). It requires the store's base country to be NL or BE — this is enforced at initialization and shown as an admin notice if violated.

The current version is **5.9.4** (package.json, plugin header, readme.txt must all match for deployment). It requires PHP ≥ 7.4, WordPress ≥ 6.7, and WooCommerce ≥ 10.2. The plugin supports both the classic (shortcode) WooCommerce checkout and the newer WooCommerce Cart/Checkout Blocks.

## Architecture

### Tech Stack

- **PHP** — PSR-4 autoloaded from `src/`, WordPress/WooCommerce coding standards (PHPCS)
- **JavaScript/React** — WooCommerce Blocks checkout components in `client/`, compiled with `@wordpress/scripts` (webpack)
- **Classic checkout JS** — vanilla/jQuery in `assets/js/`, not bundled through webpack
- **CSS** — plain CSS in `assets/css/`, SCSS in `client/` compiled via webpack
- **i18n** — `.pot`/`.po`/`.mo` for PHP; `.json` for JS (generated via `wp i18n make-json`)
- **External library** — `clegginabox/pdf-merger` (PHP PDF merging via Composer)
- **CI/CD** — GitHub Actions (`.github/workflows/deploy.yml` for WordPress.org SVN release, `release-pr.yml` for automated PR creation)
- **Node.js** — version pinned in `.nvmrc` (20.18.0)
- **Testing** — Jest for JS (`tests/js/`); PHPCS for PHP linting (no PHPUnit test suite)

### Directory Structure

```
postnl-for-woocommerce/
├── postnl-for-woocommerce.php   # Plugin header + bootstrap entrypoint
├── uninstall.php                # Cleanup on plugin deletion
├── src/                         # All PHP classes (PSR-4, namespace PostNLWooCommerce\)
│   ├── Main.php                 # Singleton orchestrator — initializes all subsystems
│   ├── Utils.php                # Static utility helpers (country checks, shipping cost, etc.)
│   ├── Logger.php               # Wraps WC logger; enabled via settings
│   ├── Address_Utils.php        # Address parsing/validation helpers
│   ├── User.php                 # PostNL user/account helpers
│   ├── Shipping_Method/
│   │   ├── PostNL.php           # WC_Shipping_Method implementation
│   │   ├── Settings.php         # All plugin settings (extends WC_Settings_API)
│   │   └── Fill_In_With_PostNL_Settings.php  # Settings for Fill In With feature
│   ├── Rest_API/                # Clients for each PostNL API endpoint
│   │   ├── Base.php             # Abstract base: API key, sandbox/prod URL, request builder
│   │   ├── Base_Info.php        # Abstract base for item info objects
│   │   ├── Barcode/             # Barcode generation
│   │   ├── Checkout/            # Delivery options API (/shipment/v1/checkout)
│   │   ├── Shipping/            # Shipping label generation
│   │   ├── Return_Label/        # Return label generation
│   │   ├── Letterbox/           # Letterbox label generation
│   │   ├── Shipment_and_Return/ # Combined S&R label
│   │   ├── Smart_Returns/       # Printer-less smart return barcodes
│   │   └── Postcode_Check/      # NL postcode validation
│   ├── Order/
│   │   ├── Base.php             # Abstract: shared label/barcode logic, PDF merging
│   │   ├── Single.php           # Single-order meta box, AJAX handlers, label download
│   │   ├── Bulk.php             # Bulk action label generation
│   │   └── OrdersList.php       # Orders list column enhancements (delivery date, print icon)
│   ├── Frontend/
│   │   ├── Container.php        # Main checkout widget: delivery options, fees, address validation
│   │   ├── Delivery_Day.php     # Delivery day UI in classic checkout
│   │   ├── Dropoff_Points.php   # Pickup point UI in classic checkout
│   │   ├── Checkout_Fields.php  # House number field injection for NL addresses
│   │   ├── Fill_In_With_Postnl.php         # REST API route for Fill In With PostNL
│   │   └── Fill_In_With_Postnl_Handler.php # AJAX handler for Fill In With PostNL
│   ├── Checkout_Blocks/
│   │   ├── Blocks_Integration.php    # WC IntegrationInterface — registers block scripts
│   │   ├── Extend_Block_Core.php     # Block checkout AJAX data handler
│   │   └── Extend_Store_Endpoint.php # Extends WC Store API checkout endpoint (namespace: postnl)
│   ├── Product/
│   │   ├── Single.php           # Classic product meta fields (HS code, weight, adult flag)
│   │   └── Product_Editor.php   # New WC Product Block Editor compatibility
│   ├── Helper/
│   │   └── Mapping.php          # Central product code/option mapping per origin→destination
│   ├── Library/
│   │   ├── CustomizedPDFMerger.php  # Extends clegginabox/pdf-merger
│   │   └── PDF_Rotate.php           # PDF rotation for mixed portrait/landscape A6→A4
│   ├── Emails/
│   │   └── WC_Email_Smart_Return.php  # Custom WooCommerce email for Smart Return
│   └── Updater/
│       └── Order.php            # Order data migration helpers
├── client/                      # React/JS source for WooCommerce Blocks checkout
│   ├── checkout/
│   │   ├── index.js             # Block registration entrypoint
│   │   ├── postnl-container/    # Main container block (orchestrates all checkout options)
│   │   ├── postnl-delivery-day/ # Delivery day selector block
│   │   ├── postnl-dropoff-points/ # Pickup point selector block
│   │   └── postnl-fill-in-with/ # Fill In With PostNL block
│   └── utils/
│       ├── session-manager.js       # Centralized session storage (key: postnl_checkout_data)
│       └── extension-data-helper.js # WC Store API extension data helpers + fee management
├── assets/                      # Static JS/CSS for classic checkout (not webpack-bundled)
│   ├── js/                      # admin-order-single, admin-order-bulk, admin-settings,
│   │                            # fe-checkout, fill_in_with_postnl, admin-shipment-track-trace
│   └── css/                     # admin-order-single, admin-order-bulk, admin-settings,
│                                # fe-checkout, postnl-fill-in-button
├── build/                       # webpack output (gitignored; generated by npm run build)
├── templates/
│   ├── checkout/                # Classic checkout template overrides
│   │   ├── postnl-container.php      # Outer wrapper for delivery options
│   │   ├── postnl-delivery-day.php   # Delivery day options
│   │   ├── postnl-dropoff-points.php # Pickup point options
│   │   ├── postnl-fill-in-with-button.php
│   │   └── postnl-letterbox-message.php
│   └── emails/
│       ├── smart-return-email.php       # HTML email template
│       └── plain/smart-return-email.php # Plain text variant
├── languages/                   # .pot, .po, .mo, and JSON translation files
├── tests/js/                    # Jest test files
│   ├── checkout-blocks.test.js
│   ├── extension-data-helper.test.js
│   ├── fe-checkout.test.js
│   ├── postnl-container.test.js
│   ├── postnl-delivery-day.test.js
│   ├── postnl-dropoff-points.test.js
│   ├── postnl-fill-in-with.test.js
│   ├── session-manager.test.js
│   ├── setup.js
│   └── __mocks__/               # Mocks for axios, @wordpress/*, @woocommerce/settings
├── .github/
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── workflows/
│       ├── deploy.yml           # WordPress.org SVN deployment
│       └── release-pr.yml       # Automated release PR creation
└── .wordpress-org/              # Store assets (banners, icons, screenshots) for WP.org listing
```

### Entrypoints

**Plugin bootstrap** (`postnl-for-woocommerce.php`):
- Defines `POSTNL_WC_PLUGIN_FILE` and `POSTNL_WC_PLUGIN_BASENAME`
- Requires `vendor/autoload.php`
- Calls `Main::instance()` on `plugins_loaded` hook via `PostNLWooCommerce\postnl()`

**Main initialization** (`src/Main.php`):
1. `plugins_loaded` → `Main::__construct()` → defines constants, checks WooCommerce active, checks NL/BE base country
2. `init` (priority 1) → `load_plugin()` → `init_hooks()` + `checkout_blocks()`
3. `init` (priority 5) → `init()` → instantiates Order\Single, Order\Bulk, Order\OrdersList, Product\Single, Frontend\*, Fill_In_With_PostNL_Settings, Product\Product_Editor
4. `woocommerce_shipping_methods` filter → registers `PostNLWooCommerce\Shipping_Method\PostNL`

**Blocks checkout** (only when `Utils::is_blocks_checkout()` is true):
- `Main::checkout_blocks()` → `Extend_Block_Core`, `Extend_Store_Endpoint::init()`, `Blocks_Integration` registered on `woocommerce_blocks_checkout_block_registration`

### Data Flow

**Classic checkout delivery options:**
1. Customer enters address → JS posts to `wp-ajax` with action `postnl_get_delivery_options` (called from `assets/js/fe-checkout.js`)
2. PHP calls PostNL Checkout API (`/shipment/v1/checkout`) via `Rest_API\Checkout\Client`
3. Response rendered into `templates/checkout/postnl-container.php`
4. Customer selects option → stored in WooCommerce session
5. `woocommerce_cart_calculate_fees` → `Frontend\Container::add_cart_fees()` injects PostNL fees
6. On order creation → fee data and selected option stored as order meta `_postnl_order_metadata`

**Blocks checkout delivery options:**
1. Container block (`client/checkout/postnl-container/block.js`) calls `postnl_set_checkout_post_data` AJAX endpoint when address changes
2. `Extend_Block_Core` handles the call, returns delivery options
3. Block stores selection via WC Store API `setExtensionData` (namespace: `postnl`)
4. `Extend_Store_Endpoint` reads the data on order submission and saves to order meta

**Label generation (single order):**
1. Admin opens order → meta box rendered by `Order\Single`
2. Admin configures options + clicks generate → AJAX `postnl_order_save_form`
3. `Order\Single` calls `Rest_API\Barcode\Client` → gets barcode
4. Then calls appropriate label client (Shipping, Return_Label, Letterbox, Shipment_and_Return)
5. Label PDF saved to `POSTNL_UPLOADS_DIR` (`wp-content/uploads/postnl/`)
6. Barcode + tracking data saved to `_postnl_order_metadata`

**Bulk label generation:**
1. Admin selects orders → bulk action → AJAX `postnl_create_label`
2. `Order\Bulk` iterates orders, generates individual labels
3. PDFs merged via `Library\CustomizedPDFMerger`, output as combined download

### Data Layer

**Order meta keys** (stored on WooCommerce orders):
- `_postnl_order_metadata` — all PostNL shipping options, barcodes, tracking numbers, label paths
- `_postnl_return_activated` — boolean flag: return label activated for this order

**Plugin settings** (WooCommerce Settings API):
- Option key: `woocommerce_postnl_settings` (all main settings as associative array)
- Option key: `postnl_merchant_codes` — array of merchant customs codes for non-EU shipments

**Session data** (classic checkout):
- WooCommerce session: `postnl_option` — selected delivery option

**Session data** (blocks checkout):
- `postnl_checkout_data` — single key storing all checkout state (delivery day, dropoff point, address) — centralized in Jan 2026 to prevent fragmentation

**File storage**:
- Labels: `wp-content/uploads/postnl/` (defined as `POSTNL_UPLOADS_DIR`)
- Logs: `wp-content/uploads/wc-logs/` (defined as `POSTNL_WC_LOG_DIR`)

**External APIs**:
- Production: `https://api.postnl.nl`
- Sandbox: `https://api-sandbox.postnl.nl`
- Switched via `environment_mode` setting; API key is separate for each mode

### Key Dependencies

| Dependency | Purpose |
|---|---|
| `clegginabox/pdf-merger` | Merge multiple label PDFs into a single A4 sheet |
| `@wordpress/scripts` | webpack build pipeline for blocks JS/CSS |
| `@woocommerce/dependency-extraction-webpack-plugin` | Externalizes WC/WP packages from bundles |
| `@wordpress/data` | State management in blocks checkout (useSelect/useDispatch) |
| `axios` | HTTP requests in blocks checkout JS to WP AJAX endpoint |
| `wp-coding-standards/wpcs` | PHP code style enforcement |
| `woocommerce/woocommerce-sniffs` | WooCommerce-specific PHPCS rules |

## Public API Surface

### WordPress AJAX Endpoints

All AJAX actions fire on both `wp_ajax_*` and `wp_ajax_nopriv_*` (accessible to guests at checkout).

| Action | Handler | Purpose |
|---|---|---|
| `postnl_order_save_form` | `Order\Single::save_meta_box_ajax()` | Save shipping options + generate label for a single order |
| `postnl_order_delete_data` | `Order\Single::delete_meta_data_ajax()` | Delete label/barcode data from an order |
| `postnl_activate_return_function` | `Order\Single::postnl_activate_return_function()` | Activate return label for an order |
| `postnl_send_smart_return_email` | `Order\Single::postnl_send_smart_return_email()` | Send Smart Return barcode email to customer |
| `postnl_create_label` | `Order\Bulk::postnl_create_label_ajax()` | Bulk label generation |
| `get_postnl_user_info` | `Frontend\Fill_In_With_Postnl_Handler::handle_postnl_user_info()` | Fetch PostNL user address data |
| `postnl_set_checkout_post_data` | `Checkout_Blocks\Extend_Block_Core::handle_set_checkout_post_data()` | Blocks checkout: fetch delivery options for address |

### WP REST API Endpoints

| Route | Method | Handler | Purpose |
|---|---|---|---|
| `/wp-json/postnl/v1/fill-in` | POST | `Frontend\Fill_In_With_Postnl::register_rest_routes()` | Fill In With PostNL address autofill |

### WooCommerce Store API Extension

- **Namespace**: `postnl`
- **Endpoint extended**: `CheckoutSchema` (on order submission)
- **Handler**: `Checkout_Blocks\Extend_Store_Endpoint`
- **Purpose**: Receive selected delivery option data from blocks checkout and save to order meta

### Hooks / Filters

**Filters (output)**:
- `woocommerce_shipping_methods` — registers PostNL shipping method
- `woocommerce_locate_template` — overrides WC templates with plugin templates
- `woocommerce_email_classes` — adds `WC_Smart_Return_Email`
- `woocommerce_admin_shipping_fields` / `woocommerce_admin_billing_fields` — adds house number field
- `woocommerce_order_formatted_shipping_address` / `_billing_address` — displays house number
- `woocommerce_update_order_review_fragments` — returns validated address data (classic checkout)
- `woocommerce_cart_shipping_method_full_label` — adds PostNL logo to shipping method label
- `woocommerce_package_rates` — injects PostNL fees into shipping rates (classic checkout only)
- `woocommerce_cart_shipping_packages` — adds PostNL delivery option to package data
- `woocommerce_default_address_fields` — adds house number field for NL (classic, when enabled)
- `woocommerce_get_country_locale` / `woocommerce_country_locale_field_selectors` — NL address field ordering
- `block_categories_all` — registers `postnl` block category
- `postnl_shipment_addresses` — allows third parties to modify shipping addresses (added v5.7.0)

**Actions (output)**:
- `woocommerce_review_order_after_shipping` — renders PostNL delivery options widget (classic checkout)
- `woocommerce_cart_calculate_fees` — adds tab-based delivery option fees
- `woocommerce_blocks_checkout_block_registration` — registers blocks integration
- `add_meta_boxes` — registers PostNL meta box on order edit page
- `before_woocommerce_init` — declares HPOS and Product Block Editor compatibility

### Exported Functions / Classes

Global accessor function (callable anywhere in WP):
```php
PostNLWooCommerce\postnl() // Returns Main::instance()
```

Key singleton accessors:
```php
PostNLWooCommerce\Shipping_Method\Settings::get_instance() // Plugin settings
PostNLWooCommerce\Main::get_logger()                       // Logger instance
```

## Conventions & Patterns

### Code Style

- **PHP**: WordPress Coding Standards (WPCS). Tabs for indentation. Yoda conditions. `snake_case` functions/variables, `Class_Name` classes, `class-my-class.php` filenames.
- **JS**: WordPress JavaScript Coding Standards. Tabs for indentation. Single quotes. JSDoc on all functions.
- **File naming**: PHP files use `class-name.php` lowercase with hyphens (WordPress convention) but source files in `src/` use `Class_Name.php` (PSR-4 requirement) — `src/` uses PSR-4 class-name format, not WP file-name convention.
- **Namespace**: Always `PostNLWooCommerce\` as root. Sub-namespaces match directory names.
- **ABSPATH guard**: Every PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

### Settings Pattern

Settings are stored via `Shipping_Method\Settings` which extends `WC_Settings_API`. All settings read via `$this->settings->get_field_value('field_name')` or named getters (e.g., `is_sandbox()`, `get_api_key()`). The Settings class is a singleton accessed via `Settings::get_instance()`.

### Rest_API Pattern

Every external PostNL API call follows this pattern:
1. `Item_Info` class: prepares/validates request data from WC order/session
2. `Client` class: extends `Rest_API\Base`, defines endpoint and `compose_body_request()`
3. `Base::send_request()` performs the `wp_remote_post/get` call with API key header
4. Results are returned to the calling `Order\*` or `Frontend\*` class

### Error Handling

- API errors are logged via `Logger` class (WC logger wrapper)
- Admin-facing errors are returned as WP_Error or displayed via WC admin notices
- Frontend errors (checkout) are returned as JSON responses from AJAX handlers
- Fatal guards: `if ( ! class_exists('WooCommerce') ) { return; }` at plugin level

### Validation & Sanitization

- All AJAX handlers verify nonce before processing (nonce key: `create-postnl-label`)
- Input sanitized with `sanitize_text_field()`, `wp_unslash()`, `absint()`
- Output escaped with `esc_html()`, `esc_url()`, `esc_attr()` as appropriate
- API keys never exposed in frontend JS; passed server-side only

### Testing Patterns

JS tests use Jest with `@testing-library/react`. Mocks in `tests/js/__mocks__/` cover:
- `axios` — manual mock for API calls
- `@wordpress/data` (useSelect/useDispatch)
- `@wordpress/i18n`
- `@wordpress/components`
- `@woocommerce/settings`

PHP has no unit test suite — only PHPCS linting. All PHP testing is manual.

## Decision Log

### 2024-07 — WooCommerce HPOS Compatibility
**Context:** WooCommerce introduced High-Performance Order Storage (custom order tables), breaking plugins that use `$wpdb` directly.
**Decision:** Declared HPOS compatibility via `FeaturesUtil::declare_compatibility('custom_order_tables')` and updated order storage access patterns.
**Impact:** `Order\*` classes use WC-provided APIs, not direct `$wpdb` calls. `src/Updater/Order.php` handles data migration.

### 2024-08 — WooCommerce Blocks Cart/Checkout Support (v5.7.0)
**Context:** WooCommerce's new Cart/Checkout Blocks replaced the classic shortcode checkout. Delivery options widget and address fields needed a parallel implementation.
**Decision:** Added a full `Checkout_Blocks/` subsystem using WC `IntegrationInterface`, `StoreApi` endpoint extension, and React components under `client/`. Classic checkout code was kept intact to support both modes.
**Impact:** All frontend code now has two paths: classic (shortcode) and blocks. `Utils::is_blocks_checkout()` gates block-specific behavior. Always test both when modifying checkout.

### 2025-12 — Move Blocks Components to `client/` Folder
**Context:** Block components were initially mixed into other directories. JSON translation files were being committed to git.
**Decision:** Moved all blocks source to `client/`. Stopped committing JSON translation files to git; they're now generated at build time via `npm run makejson`.
**Impact:** All block React source lives in `client/`. The `build/` directory is gitignored; never commit it.

### 2026-01 — Centralized Session Manager (blocks checkout)
**Context:** Each checkout component was managing its own session key, causing fragmented state and race conditions that led to "fluctuating prices" and re-render issues.
**Decision:** Centralized all blocks checkout state under a single session key `postnl_checkout_data` via `client/utils/session-manager.js`. Added clearing logic when checkout is complete, letterbox is detected, or container is hidden.
**Impact:** `session-manager.js` is the single source of truth for blocks checkout state. Use `getDeliveryDay()` / `clearSessionData()` from it rather than direct `setExtensionData` calls.

### 2026-01 — Conditional JS/CSS Loading (v5.9.2)
**Context:** Plugin was loading JS/CSS on all pages, causing performance impact.
**Decision:** Block checkout JS loaded only if checkout page uses blocks (`is_blocks_checkout()`). Classic checkout assets loaded only on cart/checkout pages. Fill In With assets loaded only when feature is enabled.
**Impact:** `Container::enqueue_scripts_styles()` has `if ( ! is_checkout() && ! is_cart() ) return;`. Adding new assets requires matching this conditional pattern.

### 2026-02 — SVN Release Automation
**Context:** Releases to WordPress.org SVN were manual.
**Decision:** Added `deploy.yml` with three modes: `dry` (build preview only), `dev` (pre-release tag, no trunk), `production` (full trunk + tag). Version must match across `postnl-for-woocommerce.php`, `readme.txt`, and the workflow input.
**Impact:** Releasing requires: bump version in `postnl-for-woocommerce.php`, `src/Main.php`, `package.json`, `readme.txt` (Stable tag), and `README.md` changelog. All three files must agree or validation fails.

## Feature Catalog

### Shipping Label Generation
**Purpose:** Generate PostNL shipping labels for WooCommerce orders.
**Location:** `src/Order/Single.php`, `src/Order/Base.php`, `src/Rest_API/Shipping/`
**How it works:** Admin meta box on order page. Admin configures product type, options (signature, insured, morning delivery, etc.). On save, barcode is generated first, then label. PDF saved to `wp-content/uploads/postnl/`. Supports A4 (4-up) and A6 formats, and ZPL/GIF/JPG printer types.
**Known issues:** Multicollo (multi-package) label merging had a bug with single-label start position (fixed in v5.7.2).

### Return Labels (Standard + Shipment & Return)
**Purpose:** Generate return labels, optionally combined with outbound label on one carrier sheet.
**Location:** `src/Rest_API/Return_Label/`, `src/Rest_API/Shipment_and_Return/`, `src/Order/Single.php`
**How it works:** Activated separately per-order via AJAX `postnl_activate_return_function`. Creates return barcode + label. S&R uses a single combined API call. When return option is "None," no return label is generated.

### Smart Returns
**Purpose:** Printer-less returns — customer receives a barcode via email that they show at a PostNL location.
**Location:** `src/Rest_API/Smart_Returns/`, `src/Emails/WC_Email_Smart_Return.php`, `templates/emails/`
**How it works:** Admin triggers `postnl_send_smart_return_email`. Plugin calls Smart Returns API to get barcode, sends it to customer via custom WC email class `WC_Smart_Return_Email`.

### Letterbox Parcels
**Purpose:** Automatically route eligible small orders through the Letterbox product (no pickup required at home).
**Location:** `src/Rest_API/Letterbox/`, `src/Product/Single.php`, `src/Frontend/Container.php`
**How it works:** Products can be marked as "letterbox eligible." Order is eligible if all products fit + combined weight ≤ 2 kg + NL origin only. When auto-letterbox is enabled and order qualifies, letterbox product code is used automatically. An extra fee setting exists for letterbox. Digital products are excluded from letterbox eligibility.
**Known issues:** Letterbox fee must not be added when order is eligible for free shipping (multiple fixes in early 2026).

### Delivery Day Options (Checkout)
**Purpose:** Show customers available delivery days (morning/standard/evening) and pickup points at checkout.
**Location:** `src/Frontend/Container.php`, `src/Frontend/Delivery_Day.php`, `src/Frontend/Dropoff_Points.php`, `src/Rest_API/Checkout/`, `client/checkout/postnl-*/`
**How it works:** When customer enters a NL/BE address, plugin queries PostNL Checkout API with address, cut-off times, and enabled options. Results shown in a tabbed widget. Selected option is stored in session, applied as cart fee.
**Known issues:** Fees must respect WooCommerce tax settings (display incl/excl tax). Free shipping threshold must zero out all fees. "Fluctuating prices" was a key 2026 regression caused by session fragmentation (see Decision Log).

**Tax display architecture — blocks vs classic:**
- *Classic checkout:* PHP renders tax-adjusted values (`get_fee_total_price()`) directly into `data-base-fee` / `data-price-display` HTML attributes on every `updated_checkout` event. The JS reads these pre-baked values — no tax logic in JS.
- *Blocks checkout:* The PostNL AJAX endpoint returns `carrier_base_cost`, `delivery_day_fee_display`, and `pickup_fee_display` as tax-adjusted amounts. However, `selectedShippingFee` in the JS back-calculation reads `chosen.price` from the WC Store API, which is the **ex-tax** rate cost (`$rate->cost`). To reconcile this mismatch, the AJAX response also includes `tax_ratio = get_fee_total_price(1.0)` (equals `1 + shipping_tax_rate` when `woocommerce_tax_display_cart = incl`, otherwise `1`). The back-calculation multiplies `selectedShippingFee` by this ratio before subtracting the incl-tax PostNL fees: `carrierBaseCost = max(0, selectedShippingFee × taxRatio − injectedFeesInclTax)`. This is the correct formula when `woocommerce_tax_display_cart = incl`; when `excl`, `taxRatio = 1` and the formula reduces to the simple subtraction.

### Pickup Points (Dropoff Points)
**Purpose:** Allow customers to choose a PostNL pickup location instead of home delivery.
**Location:** `src/Frontend/Dropoff_Points.php`, `client/checkout/postnl-dropoff-points/`
**How it works:** Displayed as a tab alongside delivery day options. Uses the same Checkout API response. Selected pickup point ID/name stored in session and order meta.

### Fill In With PostNL
**Purpose:** Auto-fill checkout address fields using the customer's PostNL account address.
**Location:** `src/Frontend/Fill_In_With_Postnl.php`, `src/Frontend/Fill_In_With_Postnl_Handler.php`, `src/Shipping_Method/Fill_In_With_PostNL_Settings.php`, `client/checkout/postnl-fill-in-with/`, `assets/js/fill_in_with_postnl.js`
**How it works:** Adds a "Fill in with PostNL" button at checkout. Customer authenticates with PostNL account (OAuth). Address returned via REST API `/wp-json/postnl/v1/fill-in` and filled into checkout fields.

### Product Metadata (HS Codes, Adult Flag)
**Purpose:** Per-product PostNL metadata needed for customs declarations and age verification.
**Location:** `src/Product/Single.php`, `src/Product/Product_Editor.php`
**How it works:** Adds fields to product edit page: HS Tariff Code, customs description, adult/18+ flag. New WC Product Block Editor supported via `Product_Editor` (compatibility added v5.5.0). The adult flag triggers automatic ID Check product code for orders containing the product.

### Barcode Management
**Purpose:** Generate and store PostNL barcodes (tracking numbers) per order.
**Location:** `src/Rest_API/Barcode/`, `src/Order/Base.php`
**How it works:** Barcodes generated via PostNL Barcode API before label creation. Stored in `_postnl_order_metadata`. Deleting a label also deletes the barcode (via `postnl_order_delete_data` AJAX). Barcode type changed from RI to LA for international registered packets (v5.9.3).

### Orders List Enhancements
**Purpose:** Add PostNL-specific columns and actions to WooCommerce orders list.
**Location:** `src/Order/OrdersList.php`
**How it works:** Adds delivery date column (sortable), print label icon, and track & trace link. Works with both HPOS and legacy storage.

## Test Scenarios & Expected Behaviors

### Session Manager (blocks checkout)
- `getDeliveryDay()` returns persisted delivery day from session
- `clearSessionData()` clears all PostNL checkout data
- Session cleared when: checkout complete, order is letterbox, container hidden, country changes to unsupported

### Extension Data Helper (blocks checkout)
- `batchSetExtensionData()` sets multiple extension data keys in one call
- `clearDropoffPointExtensionData()` clears only pickup point data
- `clearAllExtensionData()` clears all PostNL extension data + fees
- `isCountrySupported(country)` returns false for non-NL/BE destinations

### PostNL Container Block
- `isEmpty(undefined|null|'')` returns true; non-empty values return false
- `isAddressEqual()` compares country, postcode, address_1, and house_number
- API call made when address changes; not made when address is incomplete
- Container hidden for letterbox orders; all session data cleared on hide

### Delivery Day Block
- Shows available time slots from API response
- Correctly applies fees for selected morning/evening/standard delivery
- Fees display including/excluding tax per WooCommerce settings

### Classic Checkout (fe-checkout.js)
- Delivery options load on address change for NL destinations
- Options hidden for non-NL/BE addresses
- Selected option persists through page refresh via WC session

## Known Tech Debt & Risks

- **No PHP unit tests** — all PHP testing is manual. High risk when refactoring shipping/label logic.
- **Hardcoded pickup location count** — `/* Temporarily hardcoded in Settings::get_number_pickup_points() */` in `Rest_API/Checkout/Client.php:42`
- **Dual checkout support** — Every checkout change must be tested in both classic (shortcode) and blocks modes. This is flagged in the PR template.
- **Session fragmentation risk** — Prior to Jan 2026, individual session keys caused inconsistent state. The centralized `session-manager.js` solves this for blocks but classic checkout still uses individual WC session keys.
- **`get_orders_list()` bug in Main.php** — `get_orders_list()` assigns to `$this->shipping_order_bulk` instead of `$this->orders_list` (line 297 of `src/Main.php`). This means `$this->orders_list` is always null. The `OrdersList` is instantiated but its reference is lost.

## Development Workflow

### Build & Run

```bash
# Install PHP dependencies (with dev)
composer install

# Install JS dependencies
npm ci

# Build webpack bundles (outputs to build/)
npm run build

# Dev mode with file watching
npm start

# Local WordPress environment (requires Docker)
npm run env start
```

### Testing

```bash
# Run JS tests
npm test

# Run JS tests in watch mode
npm run test:watch

# Run JS tests with coverage
npm run test:coverage

# PHP coding standards check
composer check-php

# PHP auto-fix
composer check-php:fix

# PHP security check
composer check-security

# i18n standards check
composer check-l18n
```

### Translation

```bash
# Regenerate .pot from PHP source
npm run makepot

# Update .po from .pot
npm run updatepo

# Generate JS JSON translation files
npm run makejson
```

### Deployment

Releases deploy to WordPress.org SVN via GitHub Actions (`deploy.yml`). Triggered manually with:
- `version`: must match plugin header + readme.txt Stable tag
- `release_type`: `dry` (preview only), `dev` (pre-release), `production` (full release to trunk)

**Pre-release checklist:**
1. Bump version in `postnl-for-woocommerce.php` (header + `$this->version`)
2. Bump version in `src/Main.php` (`$this->version`)
3. Bump version in `package.json`
4. Update `readme.txt` Stable tag + add changelog section
5. Update `README.md` changelog
6. Update `changelog.txt`
7. Commit, push, then trigger `deploy.yml` workflow

### PR & Review Process

PRs should follow `.github/PULL_REQUEST_TEMPLATE.md` which requires:
- WooCommerce and WordPress coding standards compliance
- Testing both blocks and classic checkout modes
- Testing as both logged-in user and guest
- Successful order placement tested
- Query Monitor active during testing
- Changelog entry included

Commit messages follow WordPress plugin convention. PRs reference issue numbers with `Closes #`.

## Agent Guidelines

- **Always test both checkout modes.** When modifying checkout behavior, delivery option display, fees, or session handling, changes must work in both classic (shortcode) checkout and WooCommerce Blocks checkout. The `Utils::is_blocks_checkout()` flag gates block-specific code.
- **Version bumps must be synchronized.** The version string in `postnl-for-woocommerce.php`, `src/Main.php`, `package.json`, and `readme.txt` (Stable tag) must all match. Deployment fails if they don't.
- **The `build/` directory is gitignored** and generated at build time. Never reference or commit files from `build/`. The `client/` directory is the source of truth for blocks JS.
- **JSON translation files are gitignored** and generated at build time via `npm run makejson`. Do not commit files matching `languages/*.json` — this was a deliberate change in Dec 2025.
- **Settings use `Settings::get_instance()`** — never instantiate `Settings` directly with `new Settings()` in feature code. The singleton ensures one settings object is used throughout the request.
- **Order meta key is `_postnl_order_metadata`** — all PostNL data for an order is stored in this single array key. When reading/writing order data, always use the existing getters/setters in `Order\Base` rather than accessing meta directly.
- **Letterbox is NL-only.** `Utils::get_available_country_for_letterbox()` returns only `['NL']`. Belgium support is not planned for letterbox. Do not add letterbox logic for BE shipments.
- **Adults-only (18+) shipping is NL-only.** `Utils::get_adults_only_shipping_countries()` returns only `['NL']`.
- **The PostNL API has sandbox and production environments.** Always use `$settings->is_sandbox()` to determine which URL to use. Never hardcode `https://api.postnl.nl`.
- **AJAX nonce for label operations** is `create-postnl-label`. Verify nonce in any new AJAX handler for order operations.
- **Product code mapping is in `Helper\Mapping::products_data()`** — this is the authoritative source for which PostNL product code to use for a given origin/destination/option combination. When adding new product codes or options, add them here first.
- **No PHP unit tests exist.** When fixing bugs in PHP label/API code, add manual test notes in the PR description. Be especially careful with multicollo, barcode type selection, and fee calculation changes.
- **The `get_orders_list()` method in `Main.php` has a bug** (assigns to wrong property). Do not rely on `postnl()->orders_list` — it will be null. Access `OrdersList` directly if needed.
- **WC Store API shipping rate `price` is ex-tax.** The `chosen.price` value read from `CART_STORE_KEY.getCartData().shippingRates` corresponds to `$rate->cost` (cost before tax). All PostNL display fees from the AJAX are incl-tax (via `get_fee_total_price()`). The blocks container back-calculation compensates by multiplying `selectedShippingFee × taxRatio` before subtracting incl-tax fees. If you change how fee amounts are passed between PHP and JS, you must maintain this invariant or the tab labels will show ex-tax totals when `woocommerce_tax_display_cart = incl`.
