<?php
/**
 * Plugin Name: WooCommerce Shippo Live Rates
 * Plugin URI: https://wooshippo.com/
 * Description: Display live shipping rates from USPS, UPS, and FedEx at checkout using Shippo API.
 * Version: 1.0.3
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
define('WC_SHIPPO_LIVE_RATES_VERSION', '1.0.3';
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
    
    // Enqueue frontend scripts and styles
    add_action('wp_enqueue_scripts', 'wc_shippo_live_rates_frontend_scripts');
    
    // Add settings link on plugin page
    add_filter('plugin_action_links_' . WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME, 'wc_shippo_live_rates_plugin_action_links');
}

/**
 * Initialize components that depend on WooCommerce
 */
function wc_shippo_live_rates_after_wc_init() {
    // Check if WooCommerce shipping is initialized
    if (!class_exists('WC_Shipping_Method')) {
        // If not ready yet, wait for shipping init
        add_action('woocommerce_shipping_init', 'wc_shippo_live_rates_load_shipping_method');
        return;
    }
    
    // Load immediately if shipping is ready
    wc_shippo_live_rates_load_shipping_method();
}

/**
 * Load the shipping method when WooCommerce shipping is ready
 */
function wc_shippo_live_rates_load_shipping_method() {
    // Only load once
    if (class_exists('WC_Shipping_Shippo_Live_Rates')) {
        return;
    }
    
    // Now it's safe to include WooCommerce-dependent files
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-wc-shipping-shippo.php';
    require_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/class-shippo-settings.php';
    
    // Register shipping method
    add_filter('woocommerce_shipping_methods', 'wc_shippo_live_rates_add_shipping_method');
    
    // Add diagnostic information to WooCommerce system status report
    add_action('woocommerce_system_status_report', 'wc_shippo_live_rates_add_system_status_info');
    
    // Initialize the settings class
    if (class_exists('Shippo_Settings')) {
        new Shippo_Settings();
    }
}

/**
 * Add Shippo shipping method to WooCommerce
 */
function wc_shippo_live_rates_add_shipping_method($methods) {
    $methods['shippo_live_rates'] = 'WC_Shipping_Shippo_Live_Rates';
    return $methods;
}

/**
 * Enqueue frontend scripts and styles
 */
function wc_shippo_live_rates_frontend_scripts() {
    // Only load on cart and checkout pages
    if (is_cart() || is_checkout()) {
        // Enqueue styles
        wp_enqueue_style(
            'wc-shippo-frontend-styles',
            WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL . 'css/styles.css',
            array(),
            WC_SHIPPO_LIVE_RATES_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'wc-shippo-frontend-scripts',
            WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL . 'js/scripts.js',
            array('jquery'),
            WC_SHIPPO_LIVE_RATES_VERSION,
            true
        );
    }
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

/**
 * Add inline styling and script as fallback
 * This ensures our shipping carrier separators work even if the CSS/JS files fail to load
 */
function wc_shippo_live_rates_inline_fallback() {
    // Only add on cart and checkout pages
    if (is_cart() || is_checkout()) {
        ?>
        <style type="text/css">
            /* Carrier headers */
            .wc-shippo-carrier-header {
                margin: 15px 0 5px !important;
                padding-bottom: 5px !important;
                border-bottom: 1px solid #e0e0e0;
            }
            .wc-shippo-carrier-header span {
                font-weight: bold;
                font-size: 0.95em;
                color: #333;
            }
            /* Last item in carrier group */
            .wc-shippo-last-in-group {
                margin-bottom: 15px !important;
                padding-bottom: 15px !important;
                border-bottom: 1px dashed #e5e5e5;
            }
            /* Remove border from last carrier group */
            .wc-shippo-last-in-group:last-of-type {
                border-bottom: none;
            }
            /* Carrier-specific styling */
            .wc-shippo-carrier-usps {
                font-weight: bold;
                color: #333366;
            }
            .wc-shippo-carrier-ups {
                font-weight: bold;
                color: #351c15;
            }
            .wc-shippo-carrier-fedex {
                font-weight: bold;
                color: #4d148c;
            }
            
            /* Ensure uniform font for all shipping methods */
            .shipping_method + label {
                font-weight: normal;
            }
            
            /* Custom styling for shipping method list items */
            ul#shipping_method li {
                margin-bottom: 5px;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Global variable to track if we've already processed a group
                var processedCarriers = {};
                
                function enhanceShippingMethods() {
                    // Remove any existing headers and classes first to avoid duplication
                    $('.wc-shippo-carrier-header').remove();
                    $('.wc-shippo-last-in-group').removeClass('wc-shippo-last-in-group');
                    $('.wc-shippo-live-rate').removeClass('wc-shippo-live-rate wc-shippo-carrier-usps wc-shippo-carrier-ups wc-shippo-carrier-fedex');
                    
                    // Reset the processed carriers tracking
                    processedCarriers = {};
                    
                    // Group rates by carrier
                    var carriers = {};
                    
                    $('.shipping_method').each(function() {
                        var $input = $(this);
                        if ($input.val() && $input.val().indexOf('shippo_live_rates') >= 0) {
                            var $label = $(this).closest('li').find('label');
                            var labelText = $label.text();
                            var carrierMatch = labelText.match(/^(UPS|USPS|FedEx)/i);
                            
                            if (carrierMatch) {
                                var carrier = carrierMatch[0];
                                
                                if (!carriers[carrier]) {
                                    carriers[carrier] = [];
                                }
                                
                                carriers[carrier].push($(this).closest('li'));
                                
                                // Add carrier class to label
                                $label.addClass('wc-shippo-live-rate');
                                if (carrier.toLowerCase() === 'usps') {
                                    $label.addClass('wc-shippo-carrier-usps');
                                } else if (carrier.toLowerCase() === 'ups') {
                                    $label.addClass('wc-shippo-carrier-ups');
                                } else if (carrier.toLowerCase() === 'fedex') {
                                    $label.addClass('wc-shippo-carrier-fedex');
                                }
                            }
                        }
                    });
                    
                    // Add carrier headers and organize groups
                    Object.keys(carriers).forEach(function(carrier) {
                        if (carriers[carrier].length > 0 && !processedCarriers[carrier]) {
                            processedCarriers[carrier] = true;
                            var $firstItem = carriers[carrier][0];
                            var headerHtml = '<li class="wc-shippo-carrier-header"><span>' + carrier + ' Shipping Options</span></li>';
                            $firstItem.before(headerHtml);
                            
                            // Add separator after last item in group
                            var $lastItem = carriers[carrier][carriers[carrier].length - 1];
                            $lastItem.addClass('wc-shippo-last-in-group');
                        }
                    });
                }
                
                // Initial enhancement with a small delay to ensure all elements are loaded
                setTimeout(enhanceShippingMethods, 300);
                
                // Listen for shipping calculation events
                $(document.body).on('updated_shipping_method updated_checkout updated_wc_div', function() {
                    // Small delay to ensure DOM is updated
                    setTimeout(enhanceShippingMethods, 300);
                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'wc_shippo_live_rates_inline_fallback');

// Initialize plugin on plugins_loaded to ensure WooCommerce detection works
add_action('plugins_loaded', 'wc_shippo_live_rates_init', 20); // Priority 20 to load after WooCommerce

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
    
    // Check WooCommerce version
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce Shippo Live Rates requires WooCommerce 3.0 or higher.', 'woocommerce-shippo-live-rates'));
    }
    
    // Create log files if WooCommerce logger is available
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