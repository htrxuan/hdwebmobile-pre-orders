# HDWebmobile Pre-Orders

Sell products before they are released, with a chosen availability date. Buyers are charged now and told when the item ships. A pre-order's status is always derived from the order and the product — never accepted from a request.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-pre-orders/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Tick "Pre-order" on a product, set an "Available from" date, and buyers can order it right away: the add-to-cart button becomes "Pre-order", the product page and cart show when it ships, and the order confirmation and emails carry an estimated availability date. One click on the order marks it released and notifies the customer. After the release date, the product sells as normal.

## Why this plugin exists

A competing "Pre-Orders for WooCommerce" plugin (≤ 2.3) shipped CVE-2026-84849 — authentication bypass by spoofing (CWE-290, CVSS 6.5) — letting an unauthenticated request read pre-order data (names, addresses, payment details) and create or alter orders. This plugin gives that class of bug nowhere to live:

* **No custom AJAX or REST endpoint.** Customers see their pre-orders only in WooCommerce's own My Account area, already scoped to the logged-in user.
* **Identity is never taken from a request** — always `$order->get_customer_id()` / `get_current_user_id()`, never an id / email / token from the caller.
* **Pre-order status is derived, not declared** — computed from the products purchased and their admin-only settings. No request parameter sets or changes it.
* **One guarded state change** — "Release now" is an authenticated admin request checking `manage_woocommerce` **and** a nonce, reading only an order ID; the customer it notifies comes from that order.

## Features

* Per-product "Pre-order" toggle and "Available from" date
* Add-to-cart button becomes "Pre-order"; availability date shown on product, cart, checkout, order confirmation and emails
* One-click "Release now & notify customer" on the order
* Hub list of orders awaiting release
* Reverts to a normal sale once the release date passes
* Works for guests and logged-in shoppers, on classic and block Checkout

## Limitations

* "Charge now" only — no "charge on release" / tokenized later payment
* No pre-order price surcharge or discount
* No per-customer pre-order limits or pre-order stock cap
* One global "Available from" date per product, not per-variation

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-pre-orders`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Edit a product, tick **Pre-order** in the Product data panel, set an **Available from** date.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
