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

/**
 * Main WooCommerce Shippo Live Rates class
 */
class WC_Shippo_Live_Rates {
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '1.0.0';
    
    /**
     * The single instance of the class.
     *
     * @var WC_Shippo_Live_Rates
     */
    protected static $_instance = null;
    
    /**
     * Main WC_Shippo_Live_Rates Instance.
     *
     * Ensures only one instance of WC_Shippo_Live_Rates is loaded or can be loaded.
     *
     * @return WC_Shippo_Live_Rates - Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * WC_Shippo_Live_Rates Constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
        
        do_action('wc_shippo_live_rates_loaded');
    }
    
    /**
     * Define WC_Shippo_Live_Rates Constants.
     */
    private function define_constants() {
        $this->define('WC_SHIPPO_LIVE_RATES_VERSION', $this->version);
        $this->define('WC_SHIPPO_LIVE_RATES_PLUGIN_FILE', __FILE__);
        $this->define('WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME', plugin_basename(__FILE__));
        $this->define('WC_SHIPPO_LIVE_RATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
        $this->define('WC_SHIPPO_LIVE_RATES_PLUGIN_URL', plugin_dir_url(__FILE__));
        $this->define('WC_SHIPPO_LIVE_RATES_PLUGIN_ASSETS_URL', plugin_dir_url(__FILE__) . 'assets/');
    }
    
    /**
     * Define constant if not already set.
     *
     * @param string $name  Constant name.
     * @param mixed  $value Constant value.
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
    
    /**
     * Include required core files.
     */
    public function includes() {
        // Core classes
        include_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-shippo-api.php';
        include_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/class-wc-shipping-shippo.php';
        include_once WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/class-shippo-settings.php';
        
        // Add any additional classes here
    }
    
    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Register shipping method
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Add diagnostic information to WooCommerce system status report
        add_action('woocommerce_system_status_report', array($this, 'add_system_status_info'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    /**
     * Check if WooCommerce is active.
     *
     * @return bool True if WooCommerce is active, false otherwise.
     */
    public function is_woocommerce_active() {
        $active_plugins = (array) get_option('active_plugins', array());
        
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        
        return in_array('woocommerce/woocommerce.php', $active_plugins, true) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
    }
    
    /**
     * Display notice if WooCommerce is not active.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('WooCommerce Shippo Live Rates requires WooCommerce to be installed and active.', 'woocommerce-shippo-live-rates'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add Shippo shipping method to WooCommerce.
     *
     * @param array $methods Shipping methods.
     * @return array
     */
    public function add_shipping_method($methods) {
        $methods['shippo_live_rates'] = 'WC_Shipping_Shippo_Live_Rates';
        return $methods;
    }
    
    /**
     * Load plugin text domain.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('woocommerce-shippo-live-rates', false, dirname(WC_SHIPPO_LIVE_RATES_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page.
     */
    public function admin_scripts($hook) {
        $screen = get_current_screen();
        
        // Only enqueue on our settings page or WooCommerce shipping settings
        if ('woocommerce_page_wc-shippo-live-rates' === $screen->id || 'woocommerce_page_wc-settings' === $screen->id) {
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
     * Add diagnostic information to WooCommerce system status report.
     */
    public function add_system_status_info() {
        $settings = get_option('wc_shippo_live_rates_options', array());
        
        include WC_SHIPPO_LIVE_RATES_PLUGIN_DIR . 'includes/admin/views/html-system-status-info.php';
    }
    
    /**
     * Add settings link on plugin page.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-shippo-live-rates') . '">' . __('Settings', 'woocommerce-shippo-live-rates') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Get the plugin URL.
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', WC_SHIPPO_LIVE_RATES_PLUGIN_FILE));
    }
    
    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(WC_SHIPPO_LIVE_RATES_PLUGIN_FILE));
    }
}

/**
 * Returns the main instance of WC_Shippo_Live_Rates.
 *
 * @return WC_Shippo_Live_Rates
 */
function wc_shippo_live_rates() {
    return WC_Shippo_Live_Rates::instance();
}

// Global for backwards compatibility.
$GLOBALS['wc_shippo_live_rates'] = wc_shippo_live_rates();

/**
 * Installation hooks
 */

// Activation
register_activation_hook(__FILE__, 'wc_shippo_live_rates_activate');

/**
 * Activation function
 */
function wc_shippo_live_rates_activate() {
    // Check if WooCommerce is active
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
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

// Deactivation
register_deactivation_hook(__FILE__, 'wc_shippo_live_rates_deactivate');

/**
 * Deactivation function
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