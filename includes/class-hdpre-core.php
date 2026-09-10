<?php

namespace htrxuan\hdpre;

if (!defined('ABSPATH')) {
    exit;
}

final class HDPRE_Core
{

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
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-product.php';
        require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-order.php';
        require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDPRE_Product::get_instance();
        HDPRE_Order::get_instance();

        // HDPRE_Admin owns the hdwebmobile_hub_tabs registration used by the shared hub
        // page and the capability + nonce-checked "Release now" handler, so it must load
        // unconditionally (not only when is_admin()).
        HDPRE_Admin::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdpre_wc_missing_notice')) {
            return;
        }
        delete_transient('hdpre_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Pre-Orders requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-pre-orders'); ?>
            </p>
        </div>
        <?php
    }
}
