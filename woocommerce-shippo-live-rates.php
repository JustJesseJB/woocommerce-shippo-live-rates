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
 * Requires PHP: 5.6
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WC_SHIPPO_LIVE_RATES_VERSION', '1.0.0');
define('WC_SHIPPO_LIVE_RATES_PLUGIN_FILE', __FILE__);
define('WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC_SHIPPO_LIVE_RATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_SHIPPO_LIVE_RATES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL', plugin_dir_url(__FILE__) . 'assets/');

/**
 * Check if WooCommerce is active
 */
function wc_shippo_live_rates_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins, true) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wc_shippo_live_rates_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('WooCommerce Shippo Live Rates requires WooCommerce to be installed and active.', 'woocommerce-shippo-live-rates'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wc_shippo_live_rates_init() {
    // Check if WooCommerce is active
    if (!wc_shippo_live_rates_is_woocommerce_active()) {
        add_action('admin_notices', 'wc_shippo_live_rates_woocommerce_missing_notice');
        return;
    }
    
    // Load plugin text domain
    load_plugin_textdomain('woocommerce-shippo-live-rates', false, dirname(WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME) . '/languages');
    
    // Include files that don't directly depend on WooCommerce classes
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-shippo-api.php';
    
    // Initialize plugin after WooCommerce is fully loaded
    add_action('woocommerce_init', 'wc_shippo_live_rates_after_wc_init');
    
    // Enqueue admin scripts and styles
    add_action('admin_enqueue_scripts', 'wc_shippo_live_rates_admin_scripts');
    
    // Add settings link on plugin page
    add_filter('plugin_action_links_' . WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME, 'wc_shippo_live_rates_plugin_action_links');
}

/**
 * Initialize components that depend on WooCommerce
 */
function wc_shippo_live_rates_after_wc_init() {
    // Now it's safe to include WooCommerce-dependent files
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-wc-shipping-shippo.php';
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/class-shippo-settings.php';
    
    // Register shipping method
    add_filter('woocommerce_shipping_methods', 'wc_shippo_live_rates_add_shipping_method');
    
    // Add diagnostic information to WooCommerce system status report
    add_action('woocommerce_system_status_report', 'wc_shippo_live_rates_add_system_status_info');
    
    // Initialize the settings class
    $settings = new Shippo_Settings();
}

/**
 * Add Shippo shipping method to WooCommerce
 */
function wc_shippo_live_rates_add_shipping_method($methods) {
    $methods['shippo_live_rates'] = 'WC_Shipping_Shippo_Live_Rates';
    return $methods;
}

/**
 * Enqueue admin scripts and styles
 */
function wc_shippo_live_rates_admin_scripts($hook) {
    $screen = get_current_screen();
    
    // Only enqueue on our settings page or WooCommerce shipping settings
    if (isset($screen->id) && ('woocommerce_page_wc-shippo-live-rates' === $screen->id || 'woocommerce_page_wc-settings' === $screen->id)) {
        wp_enqueue_style('wc-shippo-admin-styles', WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL . 'css/admin.css', array(), WC_SHIPPO_LIVE_RATES_VERSION);
        wp_enqueue_script('wc-shippo-admin-scripts', WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL . 'js/admin.js', array('jquery'), WC_SHIPPO_LIVE_RATES_VERSION, true);
        
        wp_localize_script('wc-shippo-admin-scripts', 'wc_shippo_params', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('wc-shippo-admin'),
            'i18n'       => array(
                'error'   => __('An error occurred.', 'woocommerce-shippo-live-rates'),
                'success' => __('Success!', 'woocommerce-shippo-live-rates'),
            ),
        ));
    }
}

/**
 * Add system status info
 */
function wc_shippo_live_rates_add_system_status_info() {
    $settings = get_option('wc_shippo_live_rates_options', array());
    
    if (file_exists(WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/views/html-system-status-info.php')) {
        include WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/views/html-system-status-info.php';
    }
}

/**
 * Add plugin action links
 */
function wc_shippo_live_rates_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-shippo-live-rates') . '">' . __('Settings', 'woocommerce-shippo-live-rates') . '</a>',
    );
    
    return array_merge($plugin_links, $links);
}

// Initialize plugin on plugins_loaded to ensure WooCommerce detection works
add_action('plugins_loaded', 'wc_shippo_live_rates_init');

// Activation hook
register_activation_hook(__FILE__, 'wc_shippo_live_rates_activate');

/**
 * Plugin activation
 */
function wc_shippo_live_rates_activate() {
    // Check if WooCommerce is active
    if (!wc_shippo_live_rates_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce Shippo Live Rates requires WooCommerce to be installed and active.', 'woocommerce-shippo-live-rates'));
    }
    
    // Create log files
    if (class_exists('WC_Logger')) {
        $logger = new WC_Logger();
        $logger->add('woocommerce-shippo-live-rates', 'Plugin activated at ' . current_time('mysql'));
    }
    
    // Add default settings
    if (!get_option('wc_shippo_live_rates_options')) {
        update_option('wc_shippo_live_rates_options', array(
            'debug_mode'       => 'yes',
            'package_strategy' => 'single',
            'fallback_enabled' => 'no',
            'fallback_amount'  => '10',
            'fallback_title'   => __('Flat Rate Shipping', 'woocommerce-shippo-live-rates'),
        ));
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wc_shippo_live_rates_deactivate');

/**
 * Plugin deactivation
 */
function wc_shippo_live_rates_deactivate() {
    // Log deactivation
    if (class_exists('WC_Logger')) {
        $logger = new WC_Logger();
        $logger->add('woocommerce-shippo-live-rates', 'Plugin deactivated at ' . current_time('mysql'));
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}