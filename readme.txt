=== PostNL for WooCommerce ===
Contributors: PostNL, shadim, abdalsalaam
Tags: woocommerce, PostNL, Labels, Shipping
Requires Plugins: woocommerce
Requires PHP: 7.4
Requires at least: 6.7
Tested up to: 6.9
WC requires at least: 10.2
WC tested up to: 10.5
Stable tag: 5.9.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The official PostNL plugin allows you to automate your e-commerce order process. Covering shipping services from PostNL Netherlands and Belgium.

== Description ==

With this extension, you can register shipments with PostNL and print the shipping labels with one push of a button. Moreover, your customers choose how they want to receive the order.
**Online Manual (in Dutch):** https://postnl.github.io/woocommerce/new-manual

= Main features =
- Submit shipments easily with PostNL, single orders or in a batch.
- Easily print shipping labels.
- Your customers can choose whether they want to receive the parcel at home or collect it from a PostNL point nearby.
- Use PostNL’s various shipping methods (mailbox parcel, insured shipping, etc.).
- Easily send your parcels to Belgium, Europe and the rest of the world.
- Follow orders via Track & Trace.
- Create multiple shipments for the same order.
- Modify the PostNL shipping options per order before exporting
- NEW! Free address validation for addresses in the Netherlands.
- NEW! Easily share the return label with your customers.
- NEW! Merchants based in Belgium can make use of this plug-in as well.

A PostNL API account is required for this plugin! Check out your API key in the Mijn PostNL business portal or contact your account manager.

== Installation ==

= Automatic installation =
Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of PostNL for WooCommerce, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type “PostNL for WooCommerce” and click Search Plugins. You can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation.

= Manual installation via the WordPress interface =
1. Download the plugin zip file to your computer
2. Go to the WordPress admin panel menu Plugins > Add New
3. Choose upload
4. Upload the plugin zip file, the plugin will now be installed
5. After installation has finished, click the ‘activate plugin’ link

= Manual installation via FTP =
1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.

= Setting up the plugin =
1. Go to the menu `WooCommerce > Settings > Shipping methods > PostNL`.
2. Fill in your API key and Customer details. If you don’t have an API key request it in your Mijn PostNL Business Portal. Check out the instructions here.
3. The plugin is ready to be used!

= Testing =
We advise you to test the whole checkout procedure once to see if everything works as it should. Pay special attention to the following:

The PostNL plugin adds extra fields to the checkout of your webshop, to make it possible for the client to add street name, number and optional additions separately. This way you can be sure that everything is entered correctly. Because not all checkouts are configured alike, it’s possible that the positioning/alignment of these extra fields have to be adjusted.

Moreover, after a label is created, a Track & Trace code is added to the order. When the order is completed from WooCommerce, this Track & Trace code is added to the email (when this is enabled in the settings). Check that the code is correctly displayed in your template. You can read how to change the text in the FAQ section.

== Frequently Asked Questions ==

**Online Manual (in Dutch):** [https://postnl.github.io/woocommerce/new-manual] (https://postnl.github.io/woocommerce/new-manual)

= How do I get an API key? =
Follow these instructions (https://www.postnl.nl/Images/aanvragen-api-key-stappenkaart_tcm10-200445.pdf?version=2) to request an API key or to look it up.

== Screenshots ==

1. Export or print PostNL label per order
2. Bulk export or print PostNL labels
3. Change the shipment options for an order
4. PostNL actions on the order overview page
5. PostNL information on the order details page

== Changelog ==

= 5.9.5 (2026-02-21) =
* Fix: Update translations.

= 5.9.4 (2026-02-06) =
* Fix: Missing styles from the cart page.
* Tweak: WooCommerce 10.5 compatibility.

= 5.9.3 (2026-02-05) =
* Fix: House number stripped from address when "Use PostNL address-field" is disabled in blocks checkout.
* Fix: Changed barcode type for international registered packets from RI to LA.
* Fix: Ensure ID Check products always trigger correctly when Signature on Delivery is selected.
* Add: Ability to select Id check with insured shipping for pickup options.
* Tweak: Change "Global Pack" name to "Parcels non-EU".

= 5.9.2 (2026-02-03) =
* Fix: Load plugin assets only on cart and checkout pages where they're needed to improve performance.
* Fix: Delivery options menu not loading after switching between countries with different delivery support.
* Fix: Delivery Days/Pickup Point fees persisting when changing to a destination that does not support them.

= 5.9.1 (2025-12-18) =
* Fix: Delivery options display prices including/excluding tax based on WooCommerce tax settings.
* Fix: Removed default empty merchant customs code fields to prevent validation errors when saving settings without adding codes.
* Fix: Load the PostNl shipping method fields data correctly.

= 5.9.0 (2025-12-09) =
* Add: Ability for marking products as 18+ and automatically apply ID Check to orders containing them.
* Add: Validation and TrustedShipperID support for merchant customs codes in the non-EU shipping settings.
* Add: “Delivery code at door” shipping option.
* Fix: PostNL delivery options block duplicated in the mobile checkout order summary.
* Fix: delivery-day date format to follow the WordPress General Settings configuration.
* Fix: the HS Tariff Code field not saving for product variations.
* Fix: letterbox logic for variable products.
* Tweak: WordPress 6.9 and WooCommerce 10.4 compatibility.

= 5.8.1 (2025-09-16) =
* Add: New email settings field for shipping confirmation.
* Fix: Allow admin to dismiss the survey/reward notice.
* Fix: Style conflict with other frameworks.

= 5.8.0 (2025-09-08) =
* Add: Fill In With / Invullen met PostNL.
* Add: ContactType 02 for digital proof of shipping.
* Add: Allow different fees for home / pick-up delivery.
* Fix: Label & Tracking menu fixed for NL>BE shipments.
* Fix: Delivery menu loading while addresschecker is disabled.
* Fix: WC Rest API issue.
* Fix: PHP waring `Function _load_textdomain_just_in_time was called incorrectly`.
* Tweak: WooCommerce 10.2 compatibility.

= 5.7.3 (2025-05-06) =
* Tweak: Use `plugins_loaded` hook to add the shipping method for Flexible shipping and Polylang plugins compatibility.

= 5.7.2 (2025-04-29) =
* Fix: Single label now printed according to the selected start position.
* Fix: Checkout not working properly with PostNL Address Fields disabled.
* Fix: Cut Off time default value to prevent "Wrong format for cut off time!" checkout error for new installations.

= 5.7.1 (2025-03-19) =
* Fix: Fatal error when editing pages with certain themes.
* Fix: Required house number for non-NL destinations in blocks checkout.

= 5.7.0 (2025-03-17) =
* Add: Cart/Checkout blocks compatibility.
* Fix: Improved error messages for Shipping & Return label activation.
* Fix: Postcode and city fields were incorrectly applied to both Freepost and home addresses in Smart Return shipments.
* Fix: Adjusted shipping classification for the Canary Islands to use the correct product code and country code.
* Fix: Ensure home delivery option is always visible at checkout, even if Delivery Days are disabled.
* Fix: Labels now always include a delivery date, even for "as soon as possible" orders.
* Fix: Merging EU Parcel product labels into a single A4 sheet with four A6 labels per page.
* Add: postnl_shipment_addresses filter to allow third parties to modify shipping addresses and improve compatibility.
* Tweak: WooCommerce 9.7 compatibility.

= 5.6.4 (2025-02-04) =
* Fix: Add Standard Shipping to Default Shipping Pickup options.

= 5.6.3 (2025-01-06) =
* Tweak: WordPress 6.7 and WooCommerce 9.5 compatibility.
* Add: ID check as a shipping option for pick-up point deliveries.
* Add: "ID Check + Insured Shipping" option for domestic orders.
* Fix: Enabled performing the same bulk action for generating combined PDF labels multiple times.
* Fix: Removed 6-character limit for Shipping Postcode to support longer postcodes like in Portugal and Brazil.
* Fix: Corrected order item values on commercial invoices to show the actual paid amount excluding tax.

= 5.6.2 (2024-09-24) =
* Fix: Ensured that when the return option is set to "None," no return labels are generated for orders.

= 5.6.1 (2024-09-18) =
* Fix: Preventing bulk label downloads when label format is set to A6.

= 5.6.0 (2024-09-17) =
* Add: Shipment & Return labels feature, allowing customers to use a single label for both shipping and returning parcels.
* Add: New "Printer Types" setting with support for PDF, GIF, JPG, and ZPL.
* Add: "Return to Home Address" option to the Return Settings.
* Add: Smart Return feature allowing merchants to generate and email return barcodes for printer-less returns at PostNL locations.
* Fix: Ability to merge Portrait and Landscape A6 labels into A4 PDF file.
* Add: Display the selected Pickup-Point in the confirmation email to clarify the chosen delivery option.
* Fix: PHP warnings.

= 5.5.0 (2024-08-27) =
* Add: Compatibility with the new WooCommerce Product Editor.
* Fix: HPOS declaration path.
* Fix: Item Value is fed by price after discount.
* Fix: Chosen delivery options jumps back to Evening Delivery.
* Fix: Update PostNL corporate identity.
* Fix: Automatic letterbox doesn't work in combination with digital product.
* Fix: Multiple return labels are printed when try to print the label for an order with existed label using bulk actions.

= 5.4.2 (2024-07-03) =
* Fix: Error "Invalid nonce" when trying to delete labels.
* Fix: Orders list fatal error if order have deleted product.

= 5.4.1 (2024-06-11) =
* Fix: Display shipping options within the order list for legacy orders storage.

= 5.4.0 (2024-06-10) =
* Add: Assign Letterbox Parcels automatically based on purchased products.
* Add: Ability to assign default shipping product options for every shipping zone, per settings and bulk actions.
* Fix: Checkout delivery options display on the Checkout page for Belgium merchants.
* Added and fixed Dutch translations.

= 5.3.6 (2024-04-24) =
* Fix: Correct CustomerCode in non-EU calls, in Shipping call.

= 5.3.5 (2024-04-22) =
* Fix: Correct CustomerCode in non-EU calls.

= 5.3.4 (2024-01-30) =
* Added: Logic to apply default shipping options when not explicitly set in post data.

= 5.3.3 (2024-01-30) =
* Updated: Function to check insurance amount limits for EU and non-EU shipments.
* Updated: Currency utility to include all WooCommerce currencies.

= 5.3.2 (2023-12-20) =
* Fix: Bulk action does not work in HPOS.

= 5.3.1 (2023-11-21) =
* Fix: Change store address error text.
* Add: Made the delivery date sortable by date.
* Added Dutch translations in the PostNL settings screen in WooCommerce shipping settings.

= 5.3.0 (2023-10-03) =
* Add: New product codes for shipping from Belgium to Netherlands.
* Add: Decide start position when printing label is set to A4.
* Add: Automatically change status to Completed once an order has been pre-alerted and printed.
* Fix: Check Insurance amount limit.
* Fix: Update Netherlands translation.

= 5.2.5 (2023-07-31) =
* Fix multi-collo barcodes call.

= 5.2.4 (2023-07-25) =
* Fix bug in Bulk actions menu.
* Add weight limit for Mailbox.

= 5.2.3 (2023-07-03) =
* Create column for Delivery Date on the order overview page.
* Add Company name instead of the shop name on shipping label.
* Translate street name field placeholder.
* Fix: Delete barcode and tracking number of order when the label is deleted.
* Fix: Choosing insurance + signature on delivery results in uninsured parcel.
* Fix: PHP warnings.

= 5.2.2 (2023-05-30) =
* Add new shipping product for international shipments.

= 5.2.1 (2023-05-23) =
* Fix: PostNL supported shipping methods in checkout.
* Fix: Ampersands in shop name not copied over to label correctly.
* Fix: Undefined array key warning.

= 5.2.0 (2023-05-02) =
* Feature: Add capability to associate shipping methods with PostNL method
* Feature: Add Label printing icons from the order overview
* Feature: Add shipping options by default to all orders
* Fix: Fatal error when trying to create label for order with deleted Product
* Fix: Checkout shipping address validation
* Fix: House number not copied over to invoice address
* Fix: Missing T&T info on order details page when email settings is disabled
* Fix: Delivery Date & Transit Time

= 5.1.4 (2023-03-21) =
* Fix merged labels on bulk operation

= 5.1.3 (2023-03-16) =
* Fix : Pick-up points not being shown in checkout page

= 5.1.2 (2023-02-20) =
* Allow all GlobalPack barcode types usage

= 5.1.1 (2023-02-13) =
* Fix shipping cost calculation for shipping classes

= 5.1.0 (2023-02-06) =
* Support shipping from BE
* Add morning delivery option
* Add ID check shipping option
* Fix : Make dropoff points optional
* Fix WooCommerce HPOS compatibility
* Fix track and trace URL
* Print company name on the label

= 5.0.0 (2023-02-06) =
* MAJOR RELEASE
* Reconfigure the plug-in.

[See changelog for all versions](https://raw.githubusercontent.com/Progressus-io/postnl-for-woocommerce/main/changelog.txt).

== Upgrade Notice ==
= 2.1 =
**Important!** Version 2.0 was a big update for this plugin, we recommend testing in a test environment first, before updating on a live site!

= 4.0.0 =
**Important!** Version 4.0.0 was a big update for this plugin, we recommend testing in a test environment first, before updating on a live site!