<?php
/**
 * Plugin Name: WooCommerce Shippo Live Rates
 * Plugin URI: https://wooshippo.com/
 * Description: Display live shipping rates from USPS, UPS, and FedEx at checkout using Shippo API.
 * Version: 1.0.0
 * Author: Ambition Amplified, LLC
 * Author URI: https://ambitionamplified.com/
 * Text Domain: woocommerce-shippo-live-rates
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_SHIPPO_LIVE_RATES_VERSION', '1.0.0');
define('WC_SHIPPO_LIVE_RATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_SHIPPO_LIVE_RATES_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function wc_shippo_live_rates_is_woocommerce_active() {
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

/**
 * Initialize the plugin
 */
function wc_shippo_live_rates_init() {
    // WooCommerce is required
    if (!wc_shippo_live_rates_is_woocommerce_active()) {
        add_action('admin_notices', 'wc_shippo_live_rates_woocommerce_missing_notice');
        return;
    }

    // Load plugin files
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-shippo-api.php';
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-wc-shipping-shippo.php';
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/class-shippo-settings.php';
    
    // Register shipping method
    add_filter('woocommerce_shipping_methods', 'wc_shippo_live_rates_add_shipping_method');
    
    // Load text domain for translations
    add_action('plugins_loaded', 'wc_shippo_live_rates_load_textdomain');
}
add_action('plugins_loaded', 'wc_shippo_live_rates_init');

/**
 * Display notice if WooCommerce is not active
 */
function wc_shippo_live_rates_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Shippo Live Rates requires WooCommerce to be installed and active.', 'woocommerce-shippo-live-rates'); ?></p>
    </div>
    <?php
}

/**
 * Add Shippo shipping method to WooCommerce
 */
function wc_shippo_live_rates_add_shipping_method($methods) {
    $methods['shippo_live_rates'] = 'WC_Shipping_Shippo_Live_Rates';
    return $methods;
}

/**
 * Load plugin text domain
 */
function wc_shippo_live_rates_load_textdomain() {
    load_plugin_textdomain('woocommerce-shippo-live-rates', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Activation hook
 */
function wc_shippo_live_rates_activate() {
    // Activation tasks (if any)
}
register_activation_hook(__FILE__, 'wc_shippo_live_rates_activate');

/**
 * Deactivation hook
 */
function wc_shippo_live_rates_deactivate() {
    // Deactivation tasks (if any)
}
register_deactivation_hook(__FILE__, 'wc_shippo_live_rates_deactivate');