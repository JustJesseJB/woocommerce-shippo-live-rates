<?php
/**
 * WooCommerce Shippo Shipping Method
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Shipping_Shippo_Live_Rates class
 * 
 * Implements the Shippo shipping method within WooCommerce.
 */
class WC_Shipping_Shippo_Live_Rates extends WC_Shipping_Method {
    /**
     * Shippo API instance
     *
     * @var Shippo_API
     */
    private $api;
    
    /**
     * Whether caching is enabled
     *
     * @var bool
     */
    private $cache_enabled;
    
    /**
     * Cache duration in seconds
     *
     * @var int
     */
    private $cache_duration;
    
    /**
     * Available carrier services
     *
     * @var array
     */
    private $carrier_services = array(
        'usps' => array(
            'name' => 'USPS',
            'services' => array(
                'usps_priority' => 'Priority Mail',
                'usps_priority_express' => 'Priority Mail Express',
                'usps_first_class' => 'First Class Package',
                'usps_ground_advantage' => 'Ground Advantage',
                'usps_parcel_select' => 'Parcel Select',
                'usps_media_mail' => 'Media Mail',
            ),
        ),
        'ups' => array(
            'name' => 'UPS',
            'services' => array(
                'ups_ground' => 'Ground',
                'ups_3_day_select' => '3 Day Select',
                'ups_second_day_air' => '2nd Day Air',
                'ups_next_day_air_saver' => 'Next Day Air Saver',
                'ups_next_day_air' => 'Next Day Air',
                'ups_next_day_air_early' => 'Next Day Air Early',
            ),
        ),
        'fedex' => array(
            'name' => 'FedEx',
            'services' => array(
                'fedex_ground' => 'Ground',
                'fedex_express_saver' => 'Express Saver',
                'fedex_2day' => '2Day',
                'fedex_2day_am' => '2Day A.M.',
                'fedex_priority_overnight' => 'Priority Overnight',
                'fedex_standard_overnight' => 'Standard Overnight',
                'fedex_first_overnight' => 'First Overnight',
            ),
        ),
    );
    
    /**
     * Constructor
     *
     * @param int $instance_id Shipping method instance ID.
     */
    public function __construct($instance_id = 0) {
        $this->id = 'shippo_live_rates';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Shippo Live Rates', 'woocommerce-shippo-live-rates');
        $this->method_description = __('Display live shipping rates from USPS, UPS, and FedEx via Shippo API.', 'woocommerce-shippo-live-rates');
        
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->init();
    }
    
    /**
     * Initialize settings
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user-set variables
        $this->title = $this->get_option('title', $this->method_title);
        $this->api_key = $this->get_option('api_key');
        $this->enabled_carriers = $this->get_option('carriers', array('usps'));
        $this->enabled_services = $this->get_option('services', array());
        $this->markup_type = $this->get_option('markup_type', 'none');
        $this->markup_amount = $this->get_option('markup_amount', 0);
        $this->show_delivery_time = $this->get_option('show_delivery_time') === 'yes';
        $this->cache_enabled = $this->get_option('cache_enabled') === 'yes';
        $this->cache_duration = (int) $this->get_option('cache_duration', 1) * HOUR_IN_SECONDS;
        $this->debug_mode = $this->get_option('debug_mode') === 'yes';
        
        // Initialize API
        $this->api = new Shippo_API($this->api_key, $this->debug_mode);
        
        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        
        // Clear cache when settings are saved
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'clear_cache'));
        
        // Test connection when API key is updated
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'test_api_connection'));
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Method Title', 'woocommerce-shippo-live-rates'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-shippo-live-rates'),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __('Shippo API Key', 'woocommerce-shippo-live-rates'),
                'type'        => 'password',
                'description' => __('Enter your Shippo API key. You can find this in your Shippo dashboard under API section.', 'woocommerce-shippo-live-rates'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'carriers' => array(
                'title'       => __('Carriers', 'woocommerce-shippo-live-rates'),
                'type'        => 'multiselect',
                'description' => __('Select the carriers you want to offer.', 'woocommerce-shippo-live-rates'),
                'options'     => array(
                    'usps'  => __('USPS', 'woocommerce-shippo-live-rates'),
                    'ups'   => __('UPS', 'woocommerce-shippo-live-rates'),
                    'fedex' => __('FedEx', 'woocommerce-shippo-live-rates'),
                ),
                'default'     => array('usps'),
                'class'       => 'wc-enhanced-select',
                'desc_tip'    => true,
            ),
            'services' => array(
                'title'       => __('Services', 'woocommerce-shippo-live-rates'),
                'type'        => 'multiselect',
                'description' => __('Select the specific services you want to offer. Leave empty to offer all available services.', 'woocommerce-shippo-live-rates'),
                'options'     => $this->get_all_services(),
                'default'     => array(),
                'class'       => 'wc-enhanced-select',
                'desc_tip'    => true,
            ),
            'markup_type' => array(
                'title'       => __('Rate Adjustment', 'woocommerce-shippo-live-rates'),
                'type'        => 'select',
                'description' => __('Select how you want to adjust the rates.', 'woocommerce-shippo-live-rates'),
                'options'     => array(
                    'none'       => __('No adjustment', 'woocommerce-shippo-live-rates'),
                    'flat'       => __('Add flat amount', 'woocommerce-shippo-live-rates'),
                    'percentage' => __('Add percentage', 'woocommerce-shippo-live-rates'),
                ),
                'default'     => 'none',
                'desc_tip'    => true,
            ),
            'markup_amount' => array(
                'title'       => __('Adjustment Amount', 'woocommerce-shippo-live-rates'),
                'type'        => 'text',
                'description' => __('Enter the amount to add to each rate. For percentage, enter the percentage without the % sign (e.g., enter 10 for 10%).', 'woocommerce-shippo-live-rates'),
                'default'     => '0',
                'desc_tip'    => true,
            ),
            'show_delivery_time' => array(
                'title'       => __('Show Delivery Time', 'woocommerce-shippo-live-rates'),
                'type'        => 'checkbox',
                'description' => __('Show estimated delivery time with each shipping rate.', 'woocommerce-shippo-live-rates'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'cache_enabled' => array(
                'title'       => __('Enable Caching', 'woocommerce-shippo-live-rates'),
                'type'        => 'checkbox',
                'description' => __('Cache shipping rates to improve checkout performance.', 'woocommerce-shippo-live-rates'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'cache_duration' => array(
                'title'       => __('Cache Duration (hours)', 'woocommerce-shippo-live-rates'),
                'type'        => 'number',
                'description' => __('How long to cache shipping rates. Set to 0 for no caching.', 'woocommerce-shippo-live-rates'),
                'default'     => '1',
                'min'         => '0',
                'step'        => '0.5',
                'desc_tip'    => true,
            ),
            'debug_mode' => array(
                'title'       => __('Debug Mode', 'woocommerce-shippo-live-rates'),
                'type'        => 'checkbox',
                'description' => __('Enable logging of API requests and responses for troubleshooting.', 'woocommerce-shippo-live-rates'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
        );
    }
    
    /**
     * Get all available shipping services for all carriers
     *
     * @return array Array of services
     */
    private function get_all_services() {
        $services = array();
        
        foreach ($this->carrier_services as $carrier_code => $carrier) {
            foreach ($carrier['services'] as $service_code => $service_name) {
                $services[$service_code] = $carrier['name'] . ' - ' . $service_name;
            }
        }
        
        return $services;
    }
    
    /**
     * Calculate shipping rates
     *
     * @param array $package The shipping package data.
     */
    public function calculate_shipping($package = array()) {
        // Check if API key is set
        if (empty($this->api_key)) {
            $this->log('API key is not set. Aborting rate calculation.');
            return;
        }
        
        // Get selected carriers
        $carriers = $this->get_option('carriers', array('usps'));
        if (empty($carriers)) {
            $this->log('No carriers selected. Aborting rate calculation.');
            return;
        }
        
        // Check cache first if enabled
        $rates = false;
        $cache_key = '';
        
        if ($this->cache_enabled) {
            $cache_key = $this->generate_cache_key($package, $carriers);
            $rates = get_transient($cache_key);
            
            if ($rates !== false) {
                $this->log('Using cached rates: ' . wp_json_encode($rates));
                $this->add_rates_to_woocommerce($rates);
                return;
            }
        }
        
        // Cache miss or not using cache, get rates from API
        
        // Verify we have all required address fields
        if (!$this->validate_address_fields($package['destination'])) {
            $this->log('Incomplete address. Cannot calculate shipping rates.');
            return;
        }
        
        // Prepare addresses
        $origin = $this->get_origin_address();
        $destination = $this->get_destination_address($package);
        
        // Prepare packages
        $parcels = $this->prepare_package_data($package);
        
        if (empty($parcels)) {
            $this->log('No valid parcels found. Aborting rate calculation.');
            return;
        }
        
        // Get rates from API
        $response = $this->api->get_rates($origin, $destination, $parcels, $carriers);
        
        if (!$response || isset($response['error'])) {
            $this->log('Failed to get rates from API. ' . (isset($response['error']) ? wp_json_encode($response['error']) : 'Unknown error'), 'error');
            return;
        }
        
        // Process rates
        $rates = $this->process_shipping_rates($response);
        
        // Save to cache if enabled
        if ($this->cache_enabled && !empty($rates) && !empty($cache_key)) {
            set_transient($cache_key, $rates, $this->cache_duration);
            $this->log('Saved rates to cache with key: ' . $cache_key);
        }
        
        // Add rates to WooCommerce
        $this->add_rates_to_woocommerce($rates);
    }
    
    /**
     * Process shipping rates from API response
     *
     * @param array $response API response data.
     * @return array Processed rates.
     */
    private function process_shipping_rates($response) {
        $rates = array();
        
        // Check if we have results
        if (empty($response['rates'])) {
            $this->log('No shipping rates found in API response.');
            return $rates;
        }
        
        // Get enabled services
        $enabled_services = $this->get_option('services', array());
        
        foreach ($response['rates'] as $rate) {
            // Extract carrier and service code from the rate
            $service_code = isset($rate['service_level']['token']) ? $rate['service_level']['token'] : '';
            $carrier_code = isset($rate['provider']) ? strtolower($rate['provider']) : '';
            
            // Skip if this service is not in our enabled services (if we have any specific services enabled)
            if (!empty($enabled_services) && !in_array($service_code, $enabled_services, true)) {
                continue;
            }
            
            // Skip if this carrier is not in our enabled carriers
            if (!in_array($carrier_code, $this->enabled_carriers, true)) {
                continue;
            }
            
            // Calculate cost with markup
            $cost = $this->apply_markup($rate['amount']);
            
            // Create a unique rate ID
            $rate_id = $this->id . ':' . $service_code;
            
            // Get service name
            $service_name = isset($rate['service_level']['name']) ? $rate['service_level']['name'] : '';
            
            // Format the label
            $label = $rate['provider'] . ' - ' . $service_name;
            
            // Add delivery time if enabled and available
            if ($this->show_delivery_time && !empty($rate['estimated_days'])) {
                $label .= ' (' . sprintf(
                    _n('%d day', '%d days', $rate['estimated_days'], 'woocommerce-shippo-live-rates'),
                    $rate['estimated_days']
                ) . ')';
            }
            
            // Store rate data
            $rates[$rate_id] = array(
                'id'        => $rate_id,
                'label'     => $label,
                'cost'      => $cost,
                'meta_data' => array(
                    'service_code'   => $service_code,
                    'carrier'        => $rate['provider'],
                    'delivery_days'  => isset($rate['estimated_days']) ? $rate['estimated_days'] : '',
                    'delivery_date'  => isset($rate['estimated_delivery_date']) ? $rate['estimated_delivery_date'] : '',
                    'shippo_rate_id' => isset($rate['object_id']) ? $rate['object_id'] : '',
                ),
            );
        }
        
        return $rates;
    }
    
    /**
     * Add rates to WooCommerce
     *
     * @param array $rates Shipping rates to add.
     */
    private function add_rates_to_woocommerce($rates) {
        if (empty($rates)) {
            $this->log('No rates to add to WooCommerce.');
            return;
        }
        
        foreach ($rates as $rate) {
            $this->add_rate($rate);
        }
        
        $this->log('Added ' . count($rates) . ' shipping rates to WooCommerce.');
    }
    
    /**
     * Apply markup to shipping rate
     *
     * @param float $amount Original shipping rate amount.
     * @return float Adjusted amount.
     */
    private function apply_markup($amount) {
        $amount = (float) $amount;
        
        switch ($this->markup_type) {
            case 'flat':
                $amount += (float) $this->markup_amount;
                break;
                
            case 'percentage':
                $markup_percentage = (float) $this->markup_amount;
                $amount += ($amount * $markup_percentage / 100);
                break;
                
            case 'none':
            default:
                // No adjustment
                break;
        }
        
        return $amount;
    }
    
    /**
     * Generate cache key based on package and settings
     *
     * @param array $package  Shipping package.
     * @param array $carriers Selected carriers.
     * @return string Cache key.
     */
    private function generate_cache_key($package, $carriers) {
        // Create a unique cache key based on destination and cart contents
        $destination = $package['destination'];
        $address_hash = md5(wp_json_encode($destination));
        
        // Include cart contents hash
        $contents = array();
        foreach ($package['contents'] as $item_id => $item) {
            if (!isset($item['data'])) {
                continue;
            }
            
            $product = $item['data'];
            $contents[] = array(
                'id'        => $product->get_id(),
                'quantity'  => $item['quantity'],
                'weight'    => wc_get_weight($product->get_weight(), 'kg'),
                'dimensions' => array(
                    'length' => wc_get_dimension($product->get_length(), 'cm'),
                    'width'  => wc_get_dimension($product->get_width(), 'cm'),
                    'height' => wc_get_dimension($product->get_height(), 'cm'),
                ),
            );
        }
        $contents_hash = md5(wp_json_encode($contents));
        
        // Include carriers in cache key
        $carriers_hash = md5(wp_json_encode($carriers));
        
        // Include settings that affect rates
        $settings_hash = md5(wp_json_encode(array(
            'markup_type'   => $this->markup_type,
            'markup_amount' => $this->markup_amount,
            'services'      => $this->enabled_services,
        )));
        
        return 'shippo_rates_' . $address_hash . '_' . $contents_hash . '_' . $carriers_hash . '_' . $settings_hash;
    }
    
    /**
     * Get origin address (store address)
     *
     * @return array Address data.
     */
    private function get_origin_address() {
        $address = array(
            'name'    => get_bloginfo('name'),
            'street1' => WC()->countries->get_base_address(),
            'street2' => WC()->countries->get_base_address_2(),
            'city'    => WC()->countries->get_base_city(),
            'state'   => WC()->countries->get_base_state(),
            'zip'     => WC()->countries->get_base_postcode(),
            'country' => WC()->countries->get_base_country(),
            'phone'   => '',
            'email'   => get_bloginfo('admin_email'),
        );
        
        $this->log('Origin address: ' . wp_json_encode($address));
        
        return $address;
    }
    
    /**
     * Get destination address (customer's shipping address)
     *
     * @param array $package Shipping package.
     * @return array Address data.
     */
    private function get_destination_address($package) {
        $address = array(
            'name'    => trim($package['destination']['first_name'] . ' ' . $package['destination']['last_name']),
            'street1' => $package['destination']['address_1'],
            'street2' => $package['destination']['address_2'],
            'city'    => $package['destination']['city'],
            'state'   => $package['destination']['state'],
            'zip'     => $package['destination']['postcode'],
            'country' => $package['destination']['country'],
            'phone'   => '',
            'email'   => '',
        );
        
        // Try to get phone and email from customer data
        $customer_id = get_current_user_id();
        if ($customer_id) {
            $address['email'] = get_user_meta($customer_id, 'billing_email', true);
            $address['phone'] = get_user_meta($customer_id, 'billing_phone', true);
        }
        
        // If phone is still empty, try to get it from the session
        if (empty($address['phone']) && WC()->session) {
            $customer = WC()->session->get('customer');
            if (isset($customer['phone'])) {
                $address['phone'] = $customer['phone'];
            }
        }
        
        $this->log('Destination address: ' . wp_json_encode($address));
        
        return $address;
    }
    
    /**
     * Validate required address fields
     *
     * @param array $address Address data.
     * @return bool Whether address is valid.
     */
    private function validate_address_fields($address) {
        $required_fields = array('address_1', 'city', 'postcode', 'country');
        
        foreach ($required_fields as $field) {
            if (empty($address[$field])) {
                return false;
            }
        }
        
        // If country is US, state is required
        if ($address['country'] === 'US' && empty($address['state'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prepare package data for API request
     *
     * @param array $package Shipping package.
     * @return array Parcel data.
     */
    private function prepare_package_data($package) {
        $parcels = array();
        
        // For simplicity, combine all items into one package
        // In a real implementation, you might want to split items into multiple packages
        $total_weight = 0;
        $max_length = 0;
        $max_width = 0;
        $max_height = 0;
        $has_dimensions = false;
        
        foreach ($package['contents'] as $item_id => $item) {
            if (!isset($item['data'])) {
                continue;
            }
            
            $product = $item['data'];
            
            // Add product weight (convert to kg if needed)
            $weight = wc_get_weight($product->get_weight(), 'kg');
            if ($weight > 0) {
                $total_weight += $weight * $item['quantity'];
            }
            
            // Find largest dimensions
            $length = wc_get_dimension($product->get_length(), 'cm');
            $width = wc_get_dimension($product->get_width(), 'cm');
            $height = wc_get_dimension($product->get_height(), 'cm');
            
            if ($length > 0 && $width > 0 && $height > 0) {
                $has_dimensions = true;
                $max_length = max($max_length, $length);
                $max_width = max($max_width, $width);
                $max_height = max($max_height, $height);
            }
        }
        
        // If no weight found, use default minimum
        if ($total_weight <= 0) {
            $total_weight = 0.1; // 100g minimum
        }
        
        // If no dimensions found, use default minimum
        if (!$has_dimensions) {
            $max_length = 10; // 10cm minimum
            $max_width = 10;
            $max_height = 2;
        }
        
        $parcels[] = array(
            'length'        => $max_length,
            'width'         => $max_width,
            'height'        => $max_height,
            'distance_unit' => 'cm',
            'weight'        => $total_weight,
            'mass_unit'     => 'kg',
        );
        
        $this->log('Prepared package data: ' . wp_json_encode($parcels));
        
        return $parcels;
    }
    
    /**
     * Test API connection when settings are saved
     */
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return;
        }
        
        $test_result = $this->api->test_connection();
        
        if ($test_result) {
            WC_Admin_Settings::add_message(__('Shippo API connection successful!', 'woocommerce-shippo-live-rates'));
        } else {
            WC_Admin_Settings::add_error(__('Shippo API connection failed. Please check your API key.', 'woocommerce-shippo-live-rates'));
        }
    }
    
    /**
     * Clear rates cache
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_shippo_rates_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_timeout_shippo_rates_%'");
        
        WC_Admin_Settings::add_message(__('Shippo rates cache has been cleared.', 'woocommerce-shippo-live-rates'));
    }
    
    /**
     * Log debug messages
     *
     * @param string $message Message to log.
     * @param string $level   Log level (default: 'info').
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode && isset($this->api)) {
            $this->api->log('[Shipping Method] ' . $message, $level);
        }
    }
}