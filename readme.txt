=== PostNL for WooCommerce ===
Contributors: PostNL, shadim, abdalsalaam
Tags: woocommerce, PostNL, Labels, Shipping
Requires Plugins: woocommerce
Requires PHP: 7.4
Requires at least: 6.6
Tested up to: 6.8
WC requires at least: 9.6
WC tested up to: 9.8
Stable tag: 5.7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The official PostNL for WooCommerce plugin allows you to automate your e-commerce order process. Covering shipping services from PostNL Netherlands and Belgium.

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

### 5.8.0 (2025-xx-xx) =
* Add: Ability for marking products as 18+ and automatically apply ID Check to orders containing them.
* Add: A new contact type 02 with sender email to the shipping API request.

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

= 4.4.8 (2022-11-15) =

* Fix: change post data sanitization for shipping method
* Fix: process print queue after exporting

= 4.4.7 (2022-10-31) =

* Fix: correct track trace link

= 4.4.6 (2022-10-06) =

* Fix: sanitize posted array

= 4.4.5 (2022-09-29) =

* Fix: sanitize request variables

= 4.4.4 (2022-09-29) =

* Fix: fix modal in order grid

= 4.4.3 (2022-09-27) =

* Fix: escape output

= 4.4.2 (2022-09-20) =

* Fix: change name

= 4.4.1 (2022-02-01) =
* Fix: fix warning cannot modify header information #83

= 4.4.0 (2022-01-03) =
* Improvement: add option to disable insurance for shipments to Belgium

= 4.3.3 (2021-06-02) =
* Fix: email for printing evening delivery

= 4.3.2 (2021-03-30) =
* Improvement: wpm-config.json included (support WP-Multilang plugin)
* Improvement: add translation files fr_FR
* Improvement: Deactivate delivery date
* Improvement: Option for automatic order status after exporting or printing
* Fix: Saving options in the order grid model
* Fix: Validation for sending to other address on checkout page

= 4.3.1 (2021-03-19) =
* Improvement: Export bulk order shipments although there is a wrong shipment
* Improvement: Add option to show prices as surcharge
* Improvement: Support WP Desk Flexible Shipping plugin
* Fix: Calculate weight from grams to kilos during the migration
* Fix: Set PostNL response cookie with 20 sec expire
* Fix: Use saturday cutoff time
* Fix: Use country codes from the PostNL SDK
* Fix: Translation files not being generated properly

= 4.2.0 (2021-01-21) =
* Fix: Rename `WCPN`, `WCPN()`, `WCPN_Admin` and `WCPN_Settings`
* Fix: Weight calculation for all shipment types
* Fix: Delivery options after woocommerce subtotal for solving conflicts with multiple themes
* Fix: Error array_replace_recursive second parameter must be array
* Fix: Show correct delivery type in orders grid
* Fix: Package type from shipping method/class not shown in order grid
* Fix: Unable to send return email
* Fix: Send return email modal
* Fix: Show delivery date in order grid for any order that has one
* Fix: Don't load checkout scripts on order received page
* Fix: Multicollo voor Dutch shipments and for international shipments can you create multiple labels
* Fix: Missing barcode in emails of automatically processed orders
* Fix: Properly add/remove cart fees when using delivery options
* Fix: Error on checkout when using custom address fields
* Fix: Maximum label description length of 45 characters
* Fix: Multiple barcode in order note
* Fix: Saving the correct days in the setting drop off days
* Fix: Save the correct shipping class
* Fix: Check if shipping address is selected on checkout page en use the correct address
* Fix: Order confirmation on the thank you page, confirmation email and on the customer account
* Fix: Do not save address in address book
* Fix: Correct package type for international shipments
* Fix: Only add empty parcel weight to packages
* Fix: Export via actions at the bottom of the order-grid
* Improvement: Set correct UserAgent
* Improvement: More options for age verification at product level
* Improvement: Better country of origin selection
* Improvement: Improve shipment options dialog
* Improvement: Spinner for order grid bulk actions
* Improvement: Update icons
* Improvement: Use base price for delivery options
* Improvement: Error handling after exporting and printing a label
* Improvement: Stabilizer code for opening a label in a new tab
* Improvement: New status for letter and DPZ and show them on the barcode column
* Improvement: Use gulp to allow es6 javascript and use sass.
* Improvement: Use customer note for label description.
* Improvement: Use the latest PostNL SDK.
* Improvement: Handle translations in gulp

= 4.1.5 (2020-12-15) =
* Fix: select box for country of origin
* Fix: delivery options for not logged in users
* Improvement: prices from the shipping method inside the delivery options

= 4.1.4 (2020-11-24) =
* Fix: Shipping classes not saving
* Fix: Drop off days
* Fix: WooCommerce PDF Invoices & Packing Slips placeholders compatibility
* Fix: Calculate DPZ weight
* Fix: Error delivery_date is too early
* Fix: Multiple barcode in order note
* Fix: Maximum label description lenght of 45 characters
* Improvement: support WP-Multilang


= 4.1.3 (2020-11-13) =
* Fix: Error on checkout when using custom address fields

= 4.1.2 (2020-11-12) =
* Fix: Crack down on invalid package types

= 4.1.1 (2020-11-11) =
* Fix: 4.1.0 migration fatal errors
* Fix: PHP Fatal error: Uncaught TypeError: Return value of WCPN_Export::getPackageTypeFromOrder()

= 4.1.0 (2020-11-11) =
* Improvement: All enabled/disabled dropdowns replaced with clickable toggles.
* Improvement: Show package type and delivery date instead of "details".
* Improvement: Add label description for individual shipments.
* Improvement: Loading speed/experience.
* Improvement: Spinner for order grid bulk actions.
* Improvement: make default export settings show up in shipment options.
* Improvement: show delivery date in order grid for any order that has one (only when "show delivery day" setting is enabled).
* Fix: Wrong label for "show delivery day" setting.
* Fix: Error on sending return email.
* Fix: Allow split address field for Belgium as well.
* Fix: Add options that were missing in 4.0.0
* Fix: Rename at_home_delivery to delivery_title
* Fix: Monday delivery

= 4.0.6 (2020-10-14) =
* Fix: Free_shipping linked to package then you should also see the delivery options
* Fix: If you have a shipping method with flatrate: 181 and the method gives flatrate: 18 then you should not see the delivery options
* Fix: Error CRITICAL Uncaught TypeError: Return value of WCPN_Export::getShippingMethod()

= 4.0.5 (2020-10-05) =
* Fix: Disable order status delivered
* Fix: Package type not being recognized
* Fix: migrate all package types in export defaults settings

= 4.0.4 (2020-10-01) =
* Fix: Failed opening class-wcmypa-settings.php

= 4.0.3 (2020-10-01) =
* Fix: Old settings non existent error
* Fix: Class naming for theme compatibility

= 4.0.2 (2020-08-21) =
* Fix:  Show delivery options with a shipping class and with tablerates
* Improvement: Automatic insurance

= 4.0.1 (2020-07-29) =
* Fix: Wrong meta variable country of origin
* Fix: Html layout of shipment summary settings and searching in WooCommerce orders overview
* Fix: Translations
* Fix: Export pickup locations
* Fix: When deliveryType is empty use default package
* Fix: Html layout of shipment summary and searching in WooCommerce orders overview
* Improvement: Add empty parcel weight option
* Improvement: Add multicollo option

= 4.0.0 (2020-06-24) =
* Fix: HS code
* Fix: Delete options keep old shipments
* Fix: Insurance possibilities
* Fix: Barcode in orderview
* Fix: Housenumber and suffix
* Improvement: Country of origin
* Improvement: New checkout and SDK
* Improvement: Automatic export after payment
* Improvement: V2 shipment endpoint
* Improvement: HS code for variable product

= 3.2.1 (2020-02-04) =
* Fix: The recursive delivery date loop and full cache

= 3.2.0 (2020-01-27) =
* Fix: Since November is it no longer possible to use pickup express.
* Fix: Warning: invalid argument supplied .... class-wc-shipping-flat-rate.php.

= 3.1.8 (2019-11-12) =
* Fix: Check if there is connection with PostNL

= 3.1.7 (2019-07-16) =
* Fix: Search in order grid PostNL shipment
* Fix: More than 5 products for World shipments

= 3.1.6 (2019-07-04) =
* Fix: Use constants for delivery_type
* Fix: Saturday cutoff time
* Fix: Shipping method issue with pickup

= 3.1.5 (2019-05-14) =
* Improvement: Add the link for the personalized Track & Trace page (portal)
* Improvement: Show deliverday only for NL shipments
* Improvement: Cut the product title after 50 characters
* Improvement: Barcode in order grid
* Fix: Translation house number again button
* Fix: Set default to 0 if there's no tax rate set up
* Fix: fix issue with shipping class term id
* Fix: trying to get property of non-object
* Fix: Shipment validation error (PakjeGemak)

= 3.1.4 (2019-03-18) =
* Fix: Delivery date when deliveryday window is 0
* Fix: Change `afgevinkt` to `uitgevinkt`
* Preparation: Move Great Britain to world shipment for the Brexit

= 3.1.3 (2019-02-26) =
* Fix: Showing delivery date in the order when consumer using safari
* Fix: Scrolling of the order overview when an input is clicked.

= 3.1.2 (2019-02-19) =
* Improvement: 18+ check
* Fix: Standard delivery text
* Fix: showing checkout

= 3.1.1 (2019-01-30) =
* Fix: Remove some styling code
* Fix: Text changes
* Fix: Hide delivery options
* Fix: Get the total weight on a later moment
* Fix: Unset weight by mailbox package
* Fix: Since WooCommerce 3.0, logging can be grouped by context (error code 0 when exporting / printing)
* Fix: The checkout is still loading when change the country.

* Improvement: Add maxlength to number suffix field
* Improvement: Translate all text inside the checkout
* Improvement: The option to give a discount on the shipping method ( negative amounts)

= 3.1.0 (2018-12-12) =
* Hotfix: Show delivery options when checkout form already filled in.

= 3.0.10 (2018-12-05) =
* Hotfix: Flashing of the order summary.

= 3.0.9 (2018-12-04) =
* Hotfix: Get mailbox delivery option and save it into the order.

= 3.0.8 (2018-12-04) =
* Fix: The multiple calls that are made to retrieve the shipping data.
* Fix: The option for Pick up extra early
* Fix: Wrong house number / postcode message and the possibility to adjust the address in the PostNL checkout
* Fix: Woocommerce tabel rates
* Improvement: Better support the default WooCommerce checkout address fields

= 3.0.7 (2018-11-20) =
* Fix: Set default values for dropoff days and delivery days window

= 3.0.6 (2018-11-16) =
* Fix: Remove concatenation from constant (causes an error on php version < 5.6)
* Fix: No more double address fields with delivery options disabled

= 3.0.5 (2018-11-15) =
* Fix: Error message about money_format
* Fix: Add the priority to the checkout field for support WooCommerce 3.5.1
* Fix: The PostNL logo is not visible with all browsers
* Improvement: Support Channel Engine
* Improvement: Information inside the checkout and the translations
* Improvement: Support WooCommerce default shipping fields (_address_1 and _address_2)

= 3.0.4 (2018-10-23) =
* Fix: mollie payments
* Improvement: Check for minimum php version (php 5.4)
* Improvement: Hide automatic pickup express if pickup is not enabled

= 3.0.3 (2018-10-09) =
* Fix: Problem with WooCommerce PDF Invoices & Packing Slips
* Fix: error about "Bewaar barcode in een notitie" size
* Fix: Turn of the option allow Pickup Express
* Fix: Save settings with a new update
* Improvement: PostNL delivery header titel
* Improvement: Support WooCommerce 3.5.0
* Improvement: add preliminary support for "digitale postzegel"

= 3.0.2 (2018-10-09) =
* Fix: Error a non-numeric value encountered in class-wcpn-frontend-settings.php
* Fix: Notice Undefined index: checkout_position
* Fix: Add version number after the nl-checkout.css call

= 3.0.0 (2018-10-09) =
* Changes: The whole checkout has a new look. A choice has been made to go back to the basic checkout. The checkout is designed so that he will take the styling of the website.

These are the biggest changes:
* No use of libraries (only jQuery)
* No iframe is used
* The checkout is more stable
* Easier to implement improvements

* Fix: Use street and house number fields for export a Belgium order
* Fix: The at home or at work delivery title inside the checkout
* Fix: The default settings
* Improvement: The option to change the position of the checkout (edited)

= 3.0.0-beta.2 (2018-09-08) =
* Fix: at home delivery title
* Fix: Export Belgium delivery, use the street/number input fields

= 2.4.14 (2018-07-03) =
* Fix: Select the correct package type inside admin when there is one shipping used.

= 2.4.13 (2018-07-26) =
* Fix: Tabel rate shipping witch WooCommerce Table Rate Shipping by Automattic / Bolder Elements 4.0 / Bolder Elements 4.1.3
* Fix: The option to show the checkout only when he is linked to package

= 2.4.12 (2018-07-09) =
* Fix: #102 change Iceland to world shipping
* Fix: #106 tabel rates shipping
* Improvement: #94 support legacy consignment and tracktrace data
* Improvement: #95 Speed up order list view
* Improvement: #104 Add reference identifier, that is always the order id
= 2.4.11 (2018-04-30) =
* Fix: Export shipment labels

= 2.4.10 (2018-04-26) =
* Improvement: Support Effect Connect, you can place the barcode inside a note of the order

= 2.4.9 (2018-04-03) =
* Fix: Scrolling when changing package type in orderview
* Fix: Select the correct delivery methode inside the checkout
* Improvement: Support Cloudflare

= 2.4.8 (2018-02-27) =
* Fix: The array error from the userAgent (https://wordpress.org/support/topic/parse-error-syntax-error-unexpected-in-wp-content-plugins-woocommerce-mypa/)
* Fix: The countries Norway, Turkey, Switzerland changed to world country
* Fix: Changing Type from Order List (https://wordpress.org/support/topic/changing-type-from-order-list/#post-10020043)

= 2.4.7 (2018-02-07) =
* Improvement: WooCommerce 3.3.1 compatibility

= 2.4.6 (2018-02-01) =
* Improvement: WooCommerce 3.3 compatibility
* Feature: The option to print the label on A4 and A6 format

= 2.4.5 (2018-01-10) =
* Fix: Export an order with an old delivery date
* Refactor: Error about rest api (https://wordpress.org/support/topic/error-in-woocommerce/)
                  ```des/class-wcpn-rest-api-integration.php): failed to open stream```

= 2.4.4 (2018-01-09) =
* Fix:Error about rest api (https://wordpress.org/support/topic/error-in-woocommerce/)
      ```des/class-wcpn-rest-api-integration.php): failed to open stream```

= 2.4.3 (2018-01-05) =
* Fix: Add PostNL fields to REST api to create order request
* Fix: Hide days when the pickup delivery is selected

= 2.4.2 (2017-10-29) =
* Fix: Price changes for 2018


= 2.4.1 (2017-10-12) =
* Fix: WooCommerce 3.2 compatibility

= 2.4.0 (2017-09-25) =
* Feature: Export world shipments + customs declaration form
* Feature: Show delivery options on thank you page
* Feature: Use WC logger when possible
* Fix: Return shipment error
* Fix: Order details layout for pickup location
* Fix: Delete cache of admin notices
* Fix: Display of negative delivery options price
* Fix: Improved tax handling on delivery options fees

= 2.3.3 (2017-06-27) =
* Fix: Pickup locations in Safari

= 2.3.2 (2017-06-26) =
* Fix: Delivery options header order
* Feature: Support for region (=state) in international addresses
* Feature: Hide Delivery options if PostNL service is unavailable

= 2.3.1 (2017-06-12) =
* Fix: Table Rate Shipping + WooCommerce 2.6 (error in settings)

= 2.3.0 (2017-06-12) =
* Feature: WooCommerce Table Rate Shipping support (woocommerce.com & Bolder Elements 4.0)
* Feature: Support for monday delivery
* Feature: Start print position
* Feature: Individual label printing from the order details page
* Fix: Delivery options checkout in Edge browser
* Fix: HTTPS issue with google fonts
* Fix: Multi-colli printing
* Fix: Delivery options tax in WC3.0
* Fix: Disable 'signature on delivery' & 'recipient only' when switching to pickup location in checkout
* Fix: Improve order-based calculation of highest shipping class

= 2.2.0 (2017-04-03) =
* WooCommerce 3.0 compatible
* **Requires PHP version 5.3 or higher**
* Feature: Validate NL postcodes
* Fix: Multistep checkout
* Fix: Email text translation typo
* Fix: Remove spin button (arrows) for house number checkout field
* Fix: Issues creating return shipments
* Fix: Clear delivery options (&costs) when no longer available or deselected
* Fix: Error exporting foreign addresses & PayPal Express checkout

= 2.1.3 =
* Feature: Option to autoload google fonts in delivery options
* Feature: [DELIVERY_DATE] placeholder on label
* Various minor fixes

= 2.1.2 =
* Fix: Script error on the Thank You page (interfered with Facebook/Google tracking)
* Fix: Don't show delivery date (backend/emails) if delivery days window is 0 (=disabled)
* Tweak: Notice for BE shop owners
* Tweak: Sanity check for delivery options

= 2.1.1 =
* Fix: Delivery options iPad/iPhone issues
* Fix: Ignore badly formatted delivery options data
* Fix: Don't show delivery options when cart doesn't need shipping (downloads/virtual items)
* Fix: Delivery options container not found (explicitly uses window scope)
* Tweak: Shipping column width/float in order backend
* Tweak: Page reloading & print dialogue flow optimizations

= 2.1.0 =
* Feature: Select combinations of Flat Rate & Shipping Class to link parcel settings & delivery options display
* Feature: Option to show delivery options for all shipping methods (except foreign addresses)
* Feature: Pick colors for the delivery options
* Feature: Set custom styles (CSS) for delivery options
* Feature: Enter '0' for the delivery days window to hide dates in the delivery options
* Fix: Don't apply 'only recipient' fee for morning & night delivery (already included)
* Fix: Order search issues
* Fix: 404 header on delivery options
* Tweak: Several delivery options style adjustments
* Tweak: Reload page after exporting

= 2.0.5 =
* Fix default insurance selection
* Tweak: Show shipping 'method title' instead of 'title' in settings (with fallback to title)
* Tweak: added `$order` object to `wcpostnl_email_text` filter

= 2.0.4 =
* Improved theme compatibility

= 2.0.3 =
* Fix: Checkout option fees tax
* Fix: Settings page conditional options display
* Improved settings migration from previous versions

= 2.0.2 =
* Fix order search
* Default delivery options background to white

= 2.0.1 =
* Completely revamped settings & export interface
* New delivery options replaces old 'Pakjegemak':
	* Postponed delivery (pick a delivery date)
	* Home address only option
	* Signature on delivery option
	* Evening or morning delivery option
	* PostNL Pickup & Early PostNL Pickup
	* Possibility to assign cost to the above delivery options
* Create return labels from the WooCommerce backend
* Uses new PostNL API

= 1.5.6 =
* Fix: Disable pakjegemak if 'ship to different address' is disabled after selecting Pakjegemak location
* Fix: Use billing postcode for Pakjegemak Track & Trace

= 1.5.5 =
* Fix: Foreign postcodes validation fix.

= 1.5.4 =
* Fix: Various Pakjegemak related issues (now saves & sends pakjegemak address separately to PostNL)
* Fix: Postcode validation issues with Portugal

= 1.5.3 =
* Feature: Edit PostNL address fields on user profile page
* Fix: Bug with automatic order completion

= 1.5.2 =
* Feature: Option to keep old consignments when re-exporting
* Feature: Use billing name for pakjegemak (when empty)
* Feature: store pakjegemak choice
* Fix: prevent illegal export settings/combinations
* Tweak: Better error reporting
* Tweak: Small text changes (backend)

= 1.5.1 =
* Tweak: Added error when no consignments available when trying to print labels
* Tweak: Tip for direct processing of labels (when not enabled)
* Tweak: admin styles

= 1.5.0 =
* Feature: Shipment type setting (Pakket/Brievenbuspakje/Ongefrankeerd label)
* Feature: Multi-colli support
* Feature: More advanced insurance options
* Feature: Allow overriding pakjegemak passdata file via child theme (place in /woocommerce/)
* Fix: Backend address formatting/styles
* Fix: Unexpected output at first activation
* Tweak: Hide parcel settings for other shipment types
* Tweak: Remove deprecated comments field
* Tweak: Settings now under WooCommerce top menu
* Tweak: better error logging
* Dev: Code refactor

= 1.4.6 =
* Fix: Foreign Track & Trace link updated

= 1.4.5 =
* Tweak: Prevent label creation if direct processing is disabled. NOTE! If you had this setting disabled and were used to downloading the labels directly, you need to change this in the settings.
* Tweak: Remove required tags in checkout for disabled fields.

= 1.4.4 =
* Fix: error for missing shipping fields

= 1.4.3 =
* Fix: WooCommerce 2.2+ compatibility

= 1.4.2 =
* Fix: weight unit is now properly taken into account
* Tweak: different bulk action hook (for better compatibility)

= 1.4.1 =
* Fix: Broken special characters (ë, û, à etc.)
* Tweak: different API communication mode for secure configuration

= 1.4.0 =
* Feature: Print order number on label
* Feature: PakjeGemak integration
* Feature: Option to autocomplete order after successful export to PostNL
* Feature: Option to display Track & Trace link on my account page

= 1.3.8 =
* Fix: Big exports now run without any warnings/problems (was limited by the server)
* Fix: Names, cities etc. with quotes (')
* Fix: Error on combined foreign & Dutch exports
* Fix: IE9 compatibility

= 1.3.7 =
* Fix: Checkout placeholder data was being saved in older versions of Internet Explorer

= 1.3.6 =
* Feature: Option to download PDF or display in browser
* Fix: warnings when debug set to true & downloading labels directly after exporting
* Fix: WooCommerce 2.1 bug with copying foreign address data

= 1.3.5 =
* Fix: Errors when trashing & restoring trashed orders

= 1.3.4 =
* Fix: Errors on foreign country export
* Fix: legacy address data is now also displayed properly
* Tweak: background scrolling locked when exporting

= 1.3.3 =
* Fix: Checks for required fields
* Tweak: Improved address formatting
* Tweak: Removed placeholders on house number & suffix for better compatibility with old browsers

= 1.3.2 =
* Fix: Description labels for Custom ID ('Eigen kenmerk') & Message ('Optioneel bericht')

= 1.3.1 =
* Fix: button image width

= 1.3.0 =
* New PostNL icons
* Export & PDF buttons compatible with WC2.1 / MP6 styles
* Button styles are now in CSS instead of inline

= 1.2.0 =
* Feature: The PostNL checkout fields (street name / house number) can now also be modified on the my account page
* Fix: WooCommerce 2.1 compatibility (checkout field localisation is now in WC core)
* Updated PostNL tariffs

= 1.1.1 =
* Fix: Labels for Custom id ('Eigen kenmerk') & Message ('Optioneel bericht') in the export window were reversed
* Fix: Removed depricated functions for better WooCommerce 2.1 compatibility

= 1.1.0 =
* Made extra checkout fields exclusive for dutch customers.
* Show process indicator during export.
* Various bugfixes.

= 1.0.0 =
* First release.

== Upgrade Notice ==
= 2.1 =
**Important!** Version 2.0 was a big update for this plugin, we recommend testing in a test environment first, before updating on a live site!

= 4.0.0 =
**Important!** Version 4.0.0 was a big update for this plugin, we recommend testing in a test environment first, before updating on a live site!
