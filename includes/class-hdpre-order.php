<?php

namespace htrxuan\hdpre;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Everything about an order's pre-order status is DERIVED here from the order's own line
 * items and the products' admin-only settings -- it is never read from, or set by, a
 * front-end request.
 *
 * CVE-2026-84849 (CWE-290, Authentication Bypass by Spoofing, CVSS 6.5) in a competing
 * "Pre-Orders for WooCommerce" plugin let an unauthenticated request reach pre-order data
 * (customer names, addresses, payment details) and create or alter orders. This plugin has
 * no AJAX or REST endpoint of its own at all: a customer only ever sees their own
 * pre-orders inside WooCommerce's native My Account area, which WooCommerce already scopes
 * to the logged-in user, and every identity used here is $order->get_customer_id() /
 * get_current_user_id() -- never an id, e-mail, or token taken from the request. The single
 * state-changing action ("Release now", in HDPRE_Admin) is an admin_post handler behind a
 * manage_woocommerce check and a nonce, and it accepts only an order id.
 *
 * The one piece of per-order state stored is `_hdpre_released` = 'yes', written ONLY by that
 * admin action, to record that the "your pre-order is on its way" note has been sent.
 */
final class HDPRE_Order
{

    const META_RELEASED = '_hdpre_released';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_order_details_after_order_table', array($this, 'render_customer_notice'));
        add_action('woocommerce_email_order_meta', array($this, 'render_email_notice'), 20, 3);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_admin_box'));
    }

    /* ---------- Derivation ---------- */

    /**
     * The latest release date (Y-m-d) among this order's line items that are still pending
     * pre-orders, or '' if the order has no pending pre-order item.
     */
    public static function get_pending_release_date($order)
    {
        if (!$order instanceof \WC_Order) {
            return '';
        }
        $latest = '';
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id || !HDPRE_Product::is_pending_preorder($product_id)) {
                continue;
            }
            $date = HDPRE_Product::get_release_date($product_id);
            if ('' === $date) {
                // A pending pre-order with no date -- represent as "unknown", which sorts
                // after any real date.
                $latest = $latest ?: '9999-12-31';
                continue;
            }
            if ($date > $latest) {
                $latest = $date;
            }
        }
        return $latest;
    }

    public static function order_has_pending_preorder($order)
    {
        return '' !== self::get_pending_release_date($order);
    }

    public static function is_released($order)
    {
        if (!$order instanceof \WC_Order) {
            return false;
        }
        if ('yes' === $order->get_meta(self::META_RELEASED)) {
            return true;
        }
        // No admin release recorded, but nothing is pending any more (every pre-order date
        // has since passed) -- effectively released.
        return !self::order_has_pending_preorder($order);
    }

    /* ---------- Customer-facing ---------- */

    public function render_customer_notice($order)
    {
        if (is_string($order)) {
            $order = wc_get_order($order);
        }
        if (!$order instanceof \WC_Order || !self::order_has_pending_preorder($order)) {
            return;
        }
        echo '<p class="hdpre-order-notice">' . esc_html($this->notice_text($order)) . '</p>';
    }

    public function render_email_notice($order, $sent_to_admin, $plain_text)
    {
        if (!$order instanceof \WC_Order || !self::order_has_pending_preorder($order)) {
            return;
        }
        $text = $this->notice_text($order);
        if ($plain_text) {
            echo "\n" . esc_html($text) . "\n";
        } else {
            echo '<p style="margin:0 0 16px;">' . esc_html($text) . '</p>';
        }
    }

    private function notice_text($order)
    {
        $date = self::get_pending_release_date($order);
        if ('' === $date || '9999-12-31' === $date) {
            return __('This order contains a pre-order item, which will ship as soon as it becomes available.', 'hdwebmobile-pre-orders');
        }
        /* translators: %s: formatted release date */
        return sprintf(__('This order contains a pre-order item. Estimated availability: %s. The rest of your order (if any) ships as normal.', 'hdwebmobile-pre-orders'), HDPRE_Product::format_date($date));
    }

    /* ---------- Admin order screen ---------- */

    public function render_admin_box($order)
    {
        if (!$order instanceof \WC_Order) {
            return;
        }
        $pending  = self::order_has_pending_preorder($order);
        $released = 'yes' === $order->get_meta(self::META_RELEASED);

        if (!$pending && !$released) {
            return; // Not a pre-order at all.
        }

        echo '<div class="hdpre-admin-box" style="clear:both;padding-top:1em;">';
        echo '<h4 style="margin:.5em 0;">' . esc_html__('Pre-order', 'hdwebmobile-pre-orders') . '</h4>';

        if ($released) {
            echo '<p>' . esc_html__('Released — the customer has been notified.', 'hdwebmobile-pre-orders') . '</p>';
        } elseif ($pending) {
            $date = self::get_pending_release_date($order);
            echo '<p>' . esc_html(
                ('9999-12-31' === $date || '' === $date)
                    ? __('Awaiting release (no date set).', 'hdwebmobile-pre-orders')
                    /* translators: %s: formatted release date */
                    : sprintf(__('Awaiting release. Estimated availability: %s.', 'hdwebmobile-pre-orders'), HDPRE_Product::format_date($date))
            ) . '</p>';

            $url = wp_nonce_url(
                admin_url('admin-post.php?action=hdpre_release&order_id=' . $order->get_id()),
                HDPRE_Admin::NONCE_ACTION,
                'hdpre_nonce'
            );
            echo '<a href="' . esc_url($url) . '" class="button button-primary">' . esc_html__('Release now &amp; notify customer', 'hdwebmobile-pre-orders') . '</a>';
        }

        echo '</div>';
    }
}
