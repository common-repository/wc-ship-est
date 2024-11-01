=== Ship Estimate for WooCommerce ===
Contributors: rermis
Tags: delivery estimate, ship date, Google reviews, backorder, woocommerce
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 4.6
Tested up to: 6.6
Stable tag: 2.0.17

Add a Delivery Estimate or Shipping Method Description to the WooCommerce Cart with a simple, fast and lightweight plugin.

== Description ==
Add a Delivery Estimate or Shipping Method Description to the WooCommerce Cart with a simple, fast and lightweight plugin.

## Features
&#9745; **Assign Ship Estimates** by WooCommerce shipping method

&#9745; **Supports Additional Rules** by product Variation or Backorder status

&#9745; **WooCommerce Blocks** Compatible

&#9745; **Google Customer Reviews** Compatible 

&#9745; **Supports "estimated_delivery_date"** required by Google Customer Reviews survey opt-in


## PRO Features
&#9989;  **Dynamic Estimates** - Dynamically adjust delivery estimates based on the quantity of products in the shopping cart.

&#9989;  **Single Product Page Display** - Configure Estimates on product pages, and flexibility to include or exclude products.

&#9989;  **Optimize for Sales Backlogs** - Account for varying processing times by factoring in sales backlogs.

&#9989;  **Vacation Mode** - Supports a date range for your absence, along with the flexibility to include or exclude products.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/woo-ship-est` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress.
3. Visit WooCommerce > WC Ship Estimate to add a description, day range, or date range.

== Screenshots ==
1. In Cart Display
2. Order Confirmation Display
3. Admin Options
4. Google Reviews Prompt

== Changelog ==
= 2.0.17 = * Behavior notes for product estimate.
= 2.0.16 = * Custom format support for date display.
= 2.0.15 = * Compatibility with WP 6.6, WC 9.1
= 2.0.14 = * Improved localization.
= 2.0.12 = * Compatibility with WC 8.9, Added sanitization to setting tabs.
= 2.0.9 = * Minor setup improvements.
= 2.0.7 = * Minor improvements and readme updates.
= 2.0.3 = * Improved diagnostics and minor bug fixes.
= 2.0.0 = * Redesigned admin interface. Added dependencies for future options.
= 1.6.3 = * Fix: PHP error on update. Added feature: order-admin page update to est.
= 1.6.0 = * Compatibility fixes for Elementor. Blocks bug fixes & default method fixes. Simplify logic.
= 1.5.5 = * Compatibility with WC 8.5
= 1.5.4 = * Blocks-bug fix when only one shipping method is displayed.
= 1.5.1 = * Allow method description output even if ship est is not calculated.
= 1.5.0 = *  Improvements to block compatibility.
= 1.4.26 = * Config check bug fix. WC 8.4 compatibility.
= 1.4.25 = * Compatibility with WP 6.4 and WC 8.3
= 1.4.24 = * Update setup notification conditionals.
= 1.4.23 = * Compatibility with WP 6.3 and WC 8.0
= 1.4.22 = * Compatibility with WC 7.9 and WC HPOS.
= 1.4.21 = * Fix: check for object before using get_id() in wc email hook.
= 1.4.19 = * Compatibility with WC 7.8.
= 1.4.18 = * Fixed bugs when viewing checkout in admin preview.
= 1.4.17 = * Bug fix to prepended desc in CSS psuedo element.
= 1.4.16 = * Add prepended desc and appended desc to saved est date for each order.
= 1.4.15 = * Compatibility with WP 6.2, WC 7.6.
= 1.4.14 = * Bug fix: Backorder logic not triggered when variation 'manage stock?' is unchecked
= 1.4.12 = * Updated ship method retrieval in admin settings to avoid collation conflicts.
= 1.4.11 = * Potential bug fix: Check if order object exists when called by wc email hook.
= 1.4.9 = * Setup improvements. Fix for php warning for virtual items.
= 1.4.8 = * Fix for email hook when email param is null. Fix when product rules exist but method rules don't exist. Improve performance of product rule fetch. Save and display full length estimate for display in admin, including date ranges. 
= 1.4.7 = * GTIN format improvement for google review popup.
= 1.4.6 = * Potential fix for wp-db deprecation bug for WP<6.1.1.
= 1.4.5 = * Fix for backorder days logic.
= 1.4.3 = * WC Custom orders table compatibility. Additional default backorder options.
= 1.4.2 = * Add setup link when plugin not configured. Rewrite and optimize wc_methods query.
= 1.4.1 = * Update db dependency from wp-db.php to class-wpdb.php. Update method instance separator for improved compatibility with WC Blocks.
= 1.4.0 = * WooCommerce Blocks compatibility. Bug fixes for cart page editor.


== Frequently Asked Questions ==

= How can I customize the text? =
Visit the Options tab in plugin settings and scroll down to Advanced Options.

= How do I sign up for Google Customer Reviews? =
You must have a Google Merchant Center Account with Google Customer Reviews enabled. Add your Google Merchant ID to the plugin settings under Settings > WC Ship Estimate.
Read more about setting up Google Customer Reviews here: https://support.google.com/merchants/answer/7124319

