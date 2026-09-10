<?php

namespace htrxuan\hdpre;

if (!defined('ABSPATH')) {
    exit;
}

class HDPRE_Admin
{
    const NONCE_ACTION = 'hdpre_release';

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
        require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdpre_release', array($this, 'handle_release'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['pre-orders'] = array(
            'label'  => __('Pre-Orders', 'hdwebmobile-pre-orders'),
            'order'  => 44,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    /**
     * The one and only state-changing entry point. It verifies the capability AND the nonce
     * before doing anything, and it reads exactly one value from the request -- an order id.
     * It never accepts a customer id, e-mail, token, or pre-order "status" from the caller:
     * the customer to notify is always taken from the order object itself.
     */
    public function handle_release()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-pre-orders'));
        }
        if (!isset($_GET['hdpre_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['hdpre_nonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'hdwebmobile-pre-orders'));
        }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order    = $order_id ? wc_get_order($order_id) : null;

        if ($order instanceof \WC_Order && HDPRE_Order::order_has_pending_preorder($order)) {
            $order->update_meta_data(HDPRE_Order::META_RELEASED, 'yes');
            $order->save();
            // A customer note is e-mailed to the order's own customer by WooCommerce -- the
            // recipient is derived from the order, never supplied here.
            $order->add_order_note(
                __('Your pre-order is now available and is being prepared for shipment.', 'hdwebmobile-pre-orders'),
                true
            );
        }

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=hdwebmobile&tab=pre-orders');
        }
        wp_safe_redirect(add_query_arg('hdpre_released', '1', $redirect));
        exit;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-pre-orders'));
        }
        ?>
        <p><?php esc_html_e('Mark a product as a pre-order on its Product data panel ("Pre-order" checkbox + "Available from" date). Buyers are charged at checkout and told when the item ships; after the release date the product simply sells as normal.', 'hdwebmobile-pre-orders'); ?></p>

        <?php if (!empty($_GET['hdpre_released'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag. ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Pre-order released and the customer notified.', 'hdwebmobile-pre-orders'); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e('Orders awaiting pre-order release', 'hdwebmobile-pre-orders'); ?></h2>
        <?php
        $orders = wc_get_orders(array(
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
            'status'  => array('wc-processing', 'wc-on-hold', 'wc-completed'),
        ));

        $pending = array_filter($orders, function ($order) {
            return HDPRE_Order::order_has_pending_preorder($order) && 'yes' !== $order->get_meta(HDPRE_Order::META_RELEASED);
        });

        if (empty($pending)) {
            echo '<p>' . esc_html__('No orders are currently awaiting a pre-order release.', 'hdwebmobile-pre-orders') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:820px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'hdwebmobile-pre-orders'); ?></th>
                    <th><?php esc_html_e('Customer', 'hdwebmobile-pre-orders'); ?></th>
                    <th><?php esc_html_e('Estimated availability', 'hdwebmobile-pre-orders'); ?></th>
                    <th><?php esc_html_e('Action', 'hdwebmobile-pre-orders'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $order) : ?>
                    <?php
                    $date = HDPRE_Order::get_pending_release_date($order);
                    $url  = wp_nonce_url(
                        admin_url('admin-post.php?action=hdpre_release&order_id=' . $order->get_id()),
                        self::NONCE_ACTION,
                        'hdpre_nonce'
                    );
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                        <td><?php echo esc_html(trim($order->get_formatted_billing_full_name()) ?: __('Guest', 'hdwebmobile-pre-orders')); ?></td>
                        <td><?php echo esc_html(('9999-12-31' === $date || '' === $date) ? __('No date set', 'hdwebmobile-pre-orders') : HDPRE_Product::format_date($date)); ?></td>
                        <td><a href="<?php echo esc_url($url); ?>" class="button button-small"><?php esc_html_e('Release &amp; notify', 'hdwebmobile-pre-orders'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
