=== HDWebmobile Pre-Orders ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, pre-order, preorder, release date, coming soon
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell products before they are released, with a chosen availability date. Buyers are charged now and told when the item ships.

== Description ==

HDWebmobile Pre-Orders lets you sell a product before it is available. Tick "Pre-order" on the product, set an "Available from" date, and buyers can order it right away: the add-to-cart button becomes "Pre-order", the product page and cart show when it ships, and the order confirmation and emails carry an estimated availability date. When you are ready, one click on the order marks it released and notifies the customer. After the release date, the product simply sells as normal — no cleanup needed.

= Why this plugin exists =
A competing "Pre-Orders for WooCommerce" plugin (versions 2.3 and earlier) shipped CVE-2026-84849 — an authentication-bypass-by-spoofing flaw (CWE-290, CVSS 6.5) that let an **unauthenticated** request reach pre-order data (customer names, addresses, payment details) and create or alter orders. This plugin is built so that whole class of bug has nowhere to live:

* **No custom AJAX or REST endpoint.** This plugin adds none. A customer only ever sees their own pre-orders inside WooCommerce's own My Account area, which WooCommerce already restricts to the logged-in account.
* **Identity is never taken from a request.** Every customer reference is `$order->get_customer_id()` or `get_current_user_id()` — never an id, email address, or token supplied by the caller. There is nothing to spoof.
* **Pre-order status is derived, not declared.** Whether an order contains a pre-order, and the date it releases, is computed from the products actually purchased and their admin-only settings. There is no request parameter that says "this is a pre-order" or "change this pre-order's status".
* **One guarded state change.** The only action that changes anything — "Release now & notify customer" — is an authenticated admin request that checks the `manage_woocommerce` capability **and** a nonce, and reads exactly one value: an order ID. The customer it emails is taken from that order.

= Key Features =
* Per-product "Pre-order" toggle and "Available from" date — no separate pre-order product type to manage
* Add-to-cart button becomes "Pre-order"; product page, cart, checkout, order confirmation and emails all show the availability date
* One-click "Release now" on the order marks it shipped-ready and sends the customer a note
* A hub list of every order still awaiting a pre-order release
* Automatically reverts to a normal sale once the release date passes
* Works for guests and logged-in shoppers, on classic and block-based Checkout (all display is on order-object and email hooks, which fire for both)

= Limitations (please read before installing) =
* "Charge now" only — this version does not support "charge on release" / tokenized later payment
* No pre-order price adjustment (surcharge or discount) — the pre-order price is just the product price
* No per-customer pre-order limits or pre-order stock cap
* The "Available from" date is a single global date per product, not per-variation

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-pre-orders` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Edit a product, tick **Pre-order** in the Product data panel, and set an **Available from** date.

== How to Use ==

= 1. Mark a product as a pre-order =
On the product's Product data panel (General tab), tick "Pre-order" and choose the "Available from" date.

= 2. Customers pre-order it =
The add-to-cart button reads "Pre-order", and the product page, cart and checkout show when the item ships. Checkout works exactly as normal and the customer is charged now.

= 3. Release it =
On the order (or from **WooCommerce > HDWebmobile > Pre-Orders**), click "Release now & notify customer". The customer gets a note that their pre-order is on its way. Once the "Available from" date has passed, the product sells normally with no further action.

== Screenshots ==

1. The Pre-order toggle and Available-from date on the Product data panel.
2. The "Pre-order: ships from ..." notice and "Pre-order" button on the product page.
3. The Pre-Orders tab under WooCommerce > HDWebmobile, listing orders awaiting release.

== Changelog ==

= 1.0.0 =
* Initial release: per-product pre-order toggle and availability date, storefront and email messaging, one-click release with customer notification, and a pre-order status that is always derived from the order rather than accepted from a request.
