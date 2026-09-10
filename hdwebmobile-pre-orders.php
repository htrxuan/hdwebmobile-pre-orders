<?php

/**
 * Plugin Name: HDWebmobile Pre-Orders
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-pre-orders/
 * Description: Sell products before they are released, with a chosen availability date. A pre-order's status is derived from the product and the order itself, never from a request parameter.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-pre-orders
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdpre;

if (!defined('ABSPATH')) {
    exit;
}

define('HDPRE_VERSION', '1.0.0');
define('HDPRE_PLUGIN_FILE', __FILE__);
define('HDPRE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDPRE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-activator.php';

register_activation_hook(__FILE__, array(HDPRE_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDPRE_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDPRE_PLUGIN_DIR . 'includes/class-hdpre-core.php';
    HDPRE_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-pre-orders') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
