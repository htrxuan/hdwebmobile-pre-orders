<?php

namespace htrxuan\hdpre;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The product-level pre-order settings and the customer-facing "ships from" messaging.
 *
 * The settings live on the Product data panel, so they can only ever be written by a user
 * who can already edit that product (WooCommerce gates the whole panel with the
 * `edit_product` capability) and only through WooCommerce's own product-save flow, which is
 * nonce-verified (`woocommerce_save_data`). Both stored values are plain scalar postmeta -- a
 * 'yes'/'no' string and a strict Y-m-d date string.
 *
 * Nothing about a product's pre-order state is ever read from a front-end request. Whether an
 * item is a pre-order, and the date it releases, is always looked up here from the product's
 * own saved meta.
 */
class HDPRE_Product
{

    const META_ENABLED = '_hdpre_enabled';
    const META_RELEASE = '_hdpre_release_date';

    private static $instance = null;
    private static $block_notice_done = false;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', array($this, 'render_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_fields'));

        // Customer-facing messaging. Classic product templates fire
        // woocommerce_single_product_summary / the add-to-cart-text filters; block-theme
        // product templates (e.g. Twenty Twenty-Five) render the summary as
        // woocommerce/product-* blocks and fire none of those, so the price block's own
        // output is filtered as well.
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_to_cart_text'), 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'add_to_cart_text'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'render_single_notice'), 11);
        add_filter('render_block', array($this, 'append_block_notice'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'cart_item_notice'), 10, 2);
    }

    /* ---------- Reads (the only source of truth for a product's pre-order state) ---------- */

    public static function is_preorder($product_id)
    {
        return 'yes' === get_post_meta($product_id, self::META_ENABLED, true);
    }

    /**
     * @return string 'Y-m-d', or '' if none set.
     */
    public static function get_release_date($product_id)
    {
        $date = (string) get_post_meta($product_id, self::META_RELEASE, true);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    /**
     * A pre-order product is "released" once its release date is today or earlier (or if it
     * is flagged as a pre-order but no date was set -- treated as already available so a
     * misconfiguration never traps a shopper).
     */
    public static function is_released($product_id)
    {
        if (!self::is_preorder($product_id)) {
            return true;
        }
        $date = self::get_release_date($product_id);
        if ('' === $date) {
            return true;
        }
        return strtotime($date . ' 23:59:59') <= current_time('timestamp'); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local (site timezone) date comparison, not a stored timestamp.
    }

    /**
     * True when the product is a pre-order that has NOT been released yet.
     */
    public static function is_pending_preorder($product_id)
    {
        return self::is_preorder($product_id) && !self::is_released($product_id);
    }

    public static function format_date($ymd)
    {
        $ts = strtotime((string) $ymd);
        return $ts ? date_i18n(get_option('date_format'), $ts) : (string) $ymd;
    }

    /* ---------- Admin product panel ---------- */

    public function render_fields()
    {
        global $post;
        echo '<div class="options_group hdpre-fields">';

        woocommerce_wp_checkbox(array(
            'id'          => self::META_ENABLED,
            'label'       => __('Pre-order', 'hdwebmobile-pre-orders'),
            'description' => __('Sell this product before it is available. Buyers are charged now and told when it ships.', 'hdwebmobile-pre-orders'),
            'value'       => get_post_meta($post->ID, self::META_ENABLED, true) ?: 'no',
        ));

        woocommerce_wp_text_input(array(
            'id'                => self::META_RELEASE,
            'label'             => __('Available from', 'hdwebmobile-pre-orders'),
            'type'              => 'date',
            'description'       => __('The date this product is expected to be available. Shown to buyers; after this date it stops being sold as a pre-order.', 'hdwebmobile-pre-orders'),
            'desc_tip'          => true,
            'value'             => get_post_meta($post->ID, self::META_RELEASE, true),
        ));

        echo '</div>';
    }

    public function save_fields($post_id)
    {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        update_post_meta($post_id, self::META_ENABLED, isset($_POST[self::META_ENABLED]) ? 'yes' : 'no');

        $raw  = isset($_POST[self::META_RELEASE]) ? sanitize_text_field(wp_unslash($_POST[self::META_RELEASE])) : '';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && false !== date_create($raw) ? $raw : '';
        update_post_meta($post_id, self::META_RELEASE, $date);
    }

    /* ---------- Storefront messaging ---------- */

    public function add_to_cart_text($text, $product)
    {
        if ($product && self::is_pending_preorder($product->get_id())) {
            return __('Pre-order', 'hdwebmobile-pre-orders');
        }
        return $text;
    }

    public function render_single_notice()
    {
        global $product;
        if (self::$block_notice_done || !$product || !self::is_pending_preorder($product->get_id())) {
            return;
        }
        self::$block_notice_done = true;
        echo '<p class="hdpre-single-notice" style="font-weight:600;">' . esc_html(self::single_notice_text($product->get_id())) . '</p>';
    }

    /**
     * Block-theme product templates render the summary as woocommerce/product-* blocks and
     * never fire woocommerce_single_product_summary, so the price block's own markup is
     * filtered to carry the same notice. Runs once per request, only on a single product
     * page, only for the first woocommerce/product-price block.
     */
    public function append_block_notice($block_content, $block, $instance = null)
    {
        if (self::$block_notice_done) {
            return $block_content;
        }
        $name = is_array($block) && isset($block['blockName']) ? $block['blockName'] : '';
        // The add-to-cart-form block appears exactly once in the single-product template --
        // a stabler single anchor than the price block, which some themes place twice.
        if ('woocommerce/add-to-cart-form' !== $name || !is_singular('product')) {
            return $block_content;
        }

        $product_id = 0;
        if ($instance && !empty($instance->context['postId'])) {
            $product_id = (int) $instance->context['postId'];
        }
        if (!$product_id) {
            $product_id = get_queried_object_id();
        }
        if (!$product_id || !self::is_pending_preorder($product_id)) {
            return $block_content;
        }

        self::$block_notice_done = true;
        return '<p class="hdpre-single-notice" style="font-weight:600;margin:0 0 .75em;">' . esc_html(self::single_notice_text($product_id)) . '</p>' . $block_content;
    }

    private static function single_notice_text($product_id)
    {
        $date = self::get_release_date($product_id);
        return $date
            /* translators: %s: formatted release date */
            ? sprintf(__('Pre-order: ships from %s.', 'hdwebmobile-pre-orders'), self::format_date($date))
            : __('Pre-order: ships when available.', 'hdwebmobile-pre-orders');
    }

    public function cart_item_notice($item_data, $cart_item)
    {
        if (empty($cart_item['product_id']) || !self::is_pending_preorder($cart_item['product_id'])) {
            return $item_data;
        }
        $date = self::get_release_date($cart_item['product_id']);
        $item_data[] = array(
            'key'     => __('Pre-order', 'hdwebmobile-pre-orders'),
            'value'   => $date
                /* translators: %s: formatted release date */
                ? sprintf(__('ships from %s', 'hdwebmobile-pre-orders'), self::format_date($date))
                : __('ships when available', 'hdwebmobile-pre-orders'),
            'display' => '',
        );
        return $item_data;
    }
}
