# PostNL for WooCommerce

* Contributors: PostNL
* Donate link: 
* Tags: PostNL, Shipping
* Requires at least: 4.6
* Requires PHP: 5.6
* Tested up to: 6.2
* Stable tag: 5.2.3
* WC requires at least: 4.0
* WC tested up to: 8.1
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

The official PostNL for WooCommerce plugin allows you to automate your e-commerce order process. Covering shipping services from PostNL Netherlands and Belgium.


## Description

PostNL’s official extension for WooCommerce on WordPress. Manage your national and international shipments easily.

## Features

## Installation & Configuration

1. Upload the downloaded plugin files to your `/wp-content/plugins/postnl-for-woocommerce` directory, **OR** install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce–>Settings->Shipping and Click the "PostNL" to configure the plugin.

## Support

## Additional Information

## Screenshots


## Changelog

### 5.2.3
* Create column for Delivery Date on the order overview page.
* Add Company name instead of the shop name on shipping label.
* Translate street name field placeholder.
* Fix: Delete barcode and tracking number of order when the label is deleted.
* Fix: Choosing insurance + signature on delivery results in uninsured parcel.
* Fix: PHP warnings.

### 5.2.2
* Add new shipping product for international shipments.

### 5.2.1
* Fix: PostNL supported shipping methods in checkout.
* Fix: Ampersands in shop name not copied over to label correctly.
* Fix: Undefined array key warning.

### 5.2.0
* Feature: Add capability to associate shipping methods with PostNL method
* Feature: Add Label printing icons from the order overview
* Feature: Add shipping options by default to all orders
* Fix: Fatal error when trying to create label for order with deleted Product
* Fix: Checkout shipping address validation
* Fix: House number not copied over to invoice address
* Fix: Missing T&T info on order details page when email settings is disabled
* Fix: Delivery Date & Transit Time

### 5.1.4
* Fix merged labels on bulk operation

### 5.1.3
* Fix : Pick-up points not being shown in checkout page

### 5.1.2
* Allow all GlobalPack barcode types usage

### 5.1.1
* Fix shipping cost calculation for shipping classes

### 5.1.0
* Support shipping from BE
* Add morning delivery option
* Add ID check shipping option
* Fix : Make dropoff points optional
* Fix WooCommerce HPOS compatibility
* Fix track and trace URL
* Print company name on the label

### 5.0.0
* First public release

