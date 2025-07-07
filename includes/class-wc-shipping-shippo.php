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

// Ensure WooCommerce shipping class exists before extending it
if (!class_exists('WC_Shipping_Method')) {
    return;
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
    protected $cache_enabled;
    
    /**
     * Cache duration in seconds
     *
     * @var int
     */
    protected $cache_duration;
    
    /**
     * API key for Shippo
     *
     * @var string
     */
    protected $api_key;
    
    /**
     * Enabled carriers
     *
     * @var array
     */
    protected $enabled_carriers;
    
    /**
     * Enabled services
     *
     * @var array
     */
    protected $enabled_services;
    
    /**
     * Markup type
     *
     * @var string
     */
    protected $markup_type;
    
    /**
     * Markup amount
     *
     * @var float
     */
    protected $markup_amount;
    
    /**
     * Whether to show delivery time
     *
     * @var bool
     */
    protected $show_delivery_time;
    
    /**
     * Debug mode
     *
     * @var bool
     */
    protected $debug_mode;
    
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
                'ups_ground_saver' => 'Ground Saver',
                'ups_3_day_select' => '3 Day Select',
                'ups_second_day_air' => '2nd Day Air',
                'ups_second_day_air_am' => '2nd Day Air A.M.',
                'ups_next_day_air_saver' => 'Next Day Air Saver',
                'ups_next_day_air' => 'Next Day Air',
                'ups_next_day_air_early' => 'Next Day Air Early',
                'ups_next_day_air_early_am' => 'Next Day Air Early', // Alternate name
            ),
        ),
        'fedex' => array(
            'name' => 'FedEx',
            'services' => array(
                'fedex_ground' => 'Ground',
                'fedex_ground_economy' => 'Ground Economy', // Added this
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
            'fallback_enabled' => array(
                'title'       => __('Enable Fallback Rate', 'woocommerce-shippo-live-rates'),
                'type'        => 'checkbox',
                'description' => __('Show this rate when no Shippo rates are available.', 'woocommerce-shippo-live-rates'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'fallback_title' => array(
                'title'       => __('Fallback Rate Title', 'woocommerce-shippo-live-rates'),
                'type'        => 'text',
                'description' => __('The title for the fallback shipping rate.', 'woocommerce-shippo-live-rates'),
                'default'     => __('Standard Shipping', 'woocommerce-shippo-live-rates'),
                'desc_tip'    => true,
            ),
            'fallback_amount' => array(
                'title'       => __('Fallback Rate Amount', 'woocommerce-shippo-live-rates'),
                'type'        => 'text',
                'description' => __('The cost for the fallback shipping rate.', 'woocommerce-shippo-live-rates'),
                'default'     => '10.00',
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
        // Add test rate for debugging - remove in production
        // $this->add_rate([
        //     'id' => 'shippo_test_rate',
        //     'label' => 'Test Shipping Rate',
        //     'cost' => 10.00,
        // ]);
        
        // Enhanced debugging
        $this->log('=== BEGIN CALCULATE_SHIPPING ===');
        $this->log('API Key: ' . (empty($this->api_key) ? 'EMPTY' : 'SET (Hidden for security)'));
        
        if (isset($package['contents'])) {
            $this->log('Package Contents: ' . count($package['contents']) . ' items');
        }
        
        if (isset($package['destination'])) {
            $this->log('Destination: ' . wp_json_encode($package['destination']));
        }
        
        // Debug API connection
        if (!empty($this->api_key) && isset($this->api)) {
            $test_connection = $this->api->test_connection();
            $this->log('API Connection Test: ' . ($test_connection ? 'SUCCESS' : 'FAILURE'));
        }
        
        // Check if API key is set
        if (empty($this->api_key)) {
            $this->log('API key is not set. Aborting rate calculation.');
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        // Get selected carriers
        $carriers = $this->get_option('carriers', array('usps'));
        if (empty($carriers)) {
            $this->log('No carriers selected. Aborting rate calculation.');
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        $this->log('Selected carriers: ' . implode(', ', $carriers));
        
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
            $this->log('Incomplete address. Missing fields: ' . $this->get_missing_address_fields($package['destination']));
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        // Prepare addresses
        $origin = $this->get_origin_address();
        $destination = $this->get_destination_address($package);
        
        // Prepare packages
        $parcels = $this->prepare_package_data($package);
        
        if (empty($parcels)) {
            $this->log('No valid parcels found. Aborting rate calculation.');
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        // Get rates from API
        $this->log('Calling API->get_rates()');
        $response = $this->api->get_rates($origin, $destination, $parcels, $carriers);
        
        if (!$response || isset($response['error'])) {
            $error_msg = isset($response['error']) ? wp_json_encode($response['error']) : 'Unknown error';
            $this->log('Failed to get rates from API. Error: ' . $error_msg, 'error');
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        // Process rates
        $this->log('Processing shipping rates from response');
        $rates = $this->process_shipping_rates($response);
        
        // If no rates returned, use fallback
        if (empty($rates)) {
            $this->log('No shipping rates found in API response.');
            $this->maybe_add_fallback_rate($package);
            return;
        }
        
        // Save to cache if enabled
        if ($this->cache_enabled && !empty($rates) && !empty($cache_key)) {
            set_transient($cache_key, $rates, $this->cache_duration);
            $this->log('Saved rates to cache with key: ' . $cache_key);
        }
        
        // Add rates to WooCommerce
        $this->log('Adding ' . count($rates) . ' shipping rates to WooCommerce');
        $this->add_rates_to_woocommerce($rates);
        $this->log('=== END CALCULATE_SHIPPING ===');
    }
    
    /**
     * Get missing address fields as a string
     *
     * @param array $address Address data.
     * @return string Missing fields list.
     */
    private function get_missing_address_fields($address) {
        $required_fields = array('address_1', 'city', 'postcode', 'country');
        $missing = array();
        
        foreach ($required_fields as $field) {
            if (empty($address[$field])) {
                $missing[] = $field;
            }
        }
        
        // If country is US, state is required
        if (isset($address['country']) && $address['country'] === 'US' && empty($address['state'])) {
            $missing[] = 'state';
        }
        
        return implode(', ', $missing);
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
        if (empty($response['results']) && empty($response['rates'])) {
            $this->log('No shipping rates found in API response.');
            return $rates;
        }
        
        // Determine where the rates are stored in the response
        $rate_items = !empty($response['results']) ? $response['results'] : $response['rates'];
        
        // Get enabled services
        $enabled_services = $this->get_option('services', array());
        
        // Debug log
        $this->log('Starting to process ' . count($rate_items) . ' shipping rates from Shippo API');
        if (!empty($enabled_services)) {
            $this->log('Enabled services filter is active: ' . implode(', ', $enabled_services));
        }
        
        // Track all services found in API response
        $found_services = array();
        
        foreach ($rate_items as $rate) {
            // Skip if we don't have the necessary data
            if (!isset($rate['servicelevel']) || !isset($rate['amount'])) {
                continue;
            }
            
            // Extract carrier and service code from the rate
            $service_code = isset($rate['servicelevel']['token']) ? $rate['servicelevel']['token'] : '';
            $carrier_code = isset($rate['provider']) ? strtolower($rate['provider']) : '';
            
            // Record this service for debugging
            $found_services[$service_code] = array(
                'carrier' => $carrier_code,
                'name' => isset($rate['servicelevel']['name']) ? $rate['servicelevel']['name'] : '',
                'amount' => $rate['amount']
            );
            
            // Skip if this carrier is not in our enabled carriers
            if (!in_array($carrier_code, $this->enabled_carriers, true)) {
                $this->log("Skipping carrier {$carrier_code} because it's not in enabled carriers list");
                continue;
            }
            
            // NEW CODE: If enabled_services is not empty, check if this service is enabled
            // If it's a known service in our carrier_services array
            $known_service = false;
            foreach ($this->carrier_services as $carrier) {
                if (isset($carrier['services']) && array_key_exists($service_code, $carrier['services'])) {
                    $known_service = true;
                    break;
                }
            }
            
            // If we have specific services enabled and this is a known service that's not enabled, skip it
            if (!empty($enabled_services) && $known_service && !in_array($service_code, $enabled_services, true)) {
                $this->log("Skipping service {$service_code} because it's not in enabled services list");
                continue;
            }
            
            // Calculate cost with markup
            $cost = $this->apply_markup($rate['amount']);
            
            // Create a unique rate ID
            $rate_id = $this->id . ':' . $service_code;
            
            // Get service name
            $service_name = isset($rate['servicelevel']['name']) ? $rate['servicelevel']['name'] : '';
            
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
                'carrier'   => $carrier_code, // Add carrier for sorting
                'meta_data' => array(
                    'service_code'   => $service_code,
                    'carrier'        => $rate['provider'],
                    'delivery_days'  => isset($rate['estimated_days']) ? $rate['estimated_days'] : '',
                    'delivery_date'  => isset($rate['estimated_delivery_date']) ? $rate['estimated_delivery_date'] : '',
                    'shippo_rate_id' => isset($rate['object_id']) ? $rate['object_id'] : '',
                ),
            );
        }
        
        // Log all services found for debugging
        $this->log('All services returned by API: ' . wp_json_encode($found_services));
        
        if (empty($rates)) {
            $this->log('No valid shipping rates found after filtering.');
        } else {
            $this->log('Processed ' . count($rates) . ' shipping rates.');
            
            // Sort rates by carrier and then by cost
            uasort($rates, function($a, $b) {
                // First sort by carrier
                $carrier_compare = strcmp($a['carrier'], $b['carrier']);
                if ($carrier_compare !== 0) {
                    return $carrier_compare;
                }
                
                // Then sort by cost
                return $a['cost'] <=> $b['cost'];
            });
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
        
        $this->log('Adding ' . count($rates) . ' shipping rates to WooCommerce:');
        
        // Group rates by carrier
        $grouped_rates = array();
        foreach ($rates as $rate) {
            $carrier = $rate['meta_data']['carrier'];
            if (!isset($grouped_rates[$carrier])) {
                $grouped_rates[$carrier] = array();
            }
            $grouped_rates[$carrier][] = $rate;
        }
        
        // Sort carriers alphabetically
        ksort($grouped_rates);
        
        // Add rates by carrier group, each sorted by price
        foreach ($grouped_rates as $carrier => $carrier_rates) {
            // Sort by price within each carrier
            usort($carrier_rates, function($a, $b) {
                return $a['cost'] <=> $b['cost'];
            });
            
            // Add the rates
            foreach ($carrier_rates as $rate) {
                $this->log('Adding rate: ' . $rate['label'] . ' - ' . $rate['cost']);
                
                // Remove carrier from rate array before adding to WC
                unset($rate['carrier']);
                
                $this->add_rate($rate);
            }
        }
        
        $this->log('Completed adding shipping rates to WooCommerce.');
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
            
            // Get weight with proper null/empty handling
            $weight = $product->get_weight();
            $weight = (!empty($weight) && is_numeric($weight)) ? wc_get_weight($weight, 'kg') : 0;
            
            // Get dimensions with proper null/empty handling
            $length = $product->get_length();
            $width = $product->get_width();
            $height = $product->get_height();
            
            $length = (!empty($length) && is_numeric($length)) ? wc_get_dimension($length, 'cm') : 0;
            $width = (!empty($width) && is_numeric($width)) ? wc_get_dimension($width, 'cm') : 0;
            $height = (!empty($height) && is_numeric($height)) ? wc_get_dimension($height, 'cm') : 0;
            
            $contents[] = array(
                'id'        => $product->get_id(),
                'quantity'  => $item['quantity'],
                'weight'    => $weight,
                'dimensions' => array(
                    'length' => $length,
                    'width'  => $width,
                    'height' => $height,
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
        // Initialize with empty values to prevent undefined index notices
        $first_name = isset($package['destination']['first_name']) ? $package['destination']['first_name'] : '';
        $last_name = isset($package['destination']['last_name']) ? $package['destination']['last_name'] : '';
        
        $address = array(
            'name'    => trim($first_name . ' ' . $last_name),
            'street1' => isset($package['destination']['address_1']) ? $package['destination']['address_1'] : '',
            'street2' => isset($package['destination']['address_2']) ? $package['destination']['address_2'] : '',
            'city'    => isset($package['destination']['city']) ? $package['destination']['city'] : '',
            'state'   => isset($package['destination']['state']) ? $package['destination']['state'] : '',
            'zip'     => isset($package['destination']['postcode']) ? $package['destination']['postcode'] : '',
            'country' => isset($package['destination']['country']) ? $package['destination']['country'] : '',
            'phone'   => '',
            'email'   => '',
        );
        
        // If name is empty, use a default
        if (empty(trim($address['name']))) {
            $address['name'] = 'Customer';
        }
        
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
        
        // Try to get email from order
        if (empty($address['email']) && WC()->session) {
            $customer = WC()->session->get('customer');
            if (isset($customer['email'])) {
                $address['email'] = $customer['email'];
            }
        }
        
        // If email is still empty, use a placeholder
        if (empty($address['email'])) {
            $address['email'] = 'customer@example.com';
        }
        
        // If phone is still empty, use a placeholder
        if (empty($address['phone'])) {
            $address['phone'] = '5555555555';
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
            
            // Get weight with proper null/empty handling
            $weight = $product->get_weight();
            if (!empty($weight) && is_numeric($weight)) {
                $weight = (float) wc_get_weight($weight, 'kg');
                if ($weight > 0) {
                    $total_weight += $weight * $item['quantity'];
                }
            }
            
            // Get dimensions with proper null/empty handling
            $length = $product->get_length();
            $width = $product->get_width();
            $height = $product->get_height();
            
            // Only process dimensions if all are numeric and not empty
            if (!empty($length) && is_numeric($length) && 
                !empty($width) && is_numeric($width) && 
                !empty($height) && is_numeric($height)) {
                
                $length = (float) wc_get_dimension($length, 'cm');
                $width = (float) wc_get_dimension($width, 'cm');
                $height = (float) wc_get_dimension($height, 'cm');
                
                if ($length > 0 && $width > 0 && $height > 0) {
                    $has_dimensions = true;
                    $max_length = max($max_length, $length);
                    $max_width = max($max_width, $width);
                    $max_height = max($max_height, $height);
                }
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
        
        // Round values to 4 decimal places to prevent API errors
        $parcels[] = array(
            'length'        => round($max_length, 4),
            'width'         => round($max_width, 4),
            'height'        => round($max_height, 4),
            'distance_unit' => 'cm',
            'weight'        => round($total_weight, 4),
            'mass_unit'     => 'kg',
        );
        
        $this->log('Prepared package data: ' . wp_json_encode($parcels));
        
        return $parcels;
    }
    
    /**
     * Add fallback shipping rate if enabled
     *
     * @param array $package The shipping package.
     */
    private function maybe_add_fallback_rate($package) {
        // Get fallback settings from global options
        $options = get_option('wc_shippo_live_rates_options', array());
        $fallback_enabled = isset($options['fallback_enabled']) ? $options['fallback_enabled'] : 'no';
        
        // Also check instance settings for fallback
        $instance_fallback = $this->get_option('fallback_enabled');
        if (!empty($instance_fallback)) {
            $fallback_enabled = $instance_fallback;
        }
        
        if ($fallback_enabled === 'yes') {
            // Try instance settings first, then global
            $fallback_amount = $this->get_option('fallback_amount');
            if (empty($fallback_amount)) {
                $fallback_amount = isset($options['fallback_amount']) ? $options['fallback_amount'] : '10';
            }
            
            $fallback_title = $this->get_option('fallback_title');
            if (empty($fallback_title)) {
                $fallback_title = isset($options['fallback_title']) ? $options['fallback_title'] : __('Flat Rate Shipping', 'woocommerce-shippo-live-rates');
            }
            
            $this->log('Adding fallback shipping rate: ' . $fallback_title . ' - ' . $fallback_amount);
            
            $rate = array(
                'id'        => $this->id . ':fallback',
                'label'     => $fallback_title,
                'cost'      => $fallback_amount,
                'meta_data' => array(
                    'is_fallback' => true,
                ),
            );
            
            $this->add_rate($rate);
        } else {
            // Always add a backup fallback rate if debugging is enabled
            if ($this->debug_mode) {
                $this->log('Adding emergency fallback rate for debugging');
                $this->add_rate(array(
                    'id'        => $this->id . ':emergency_fallback',
                    'label'     => 'Standard Shipping (Fallback)',
                    'cost'      => 10.00,
                    'meta_data' => array(
                        'is_emergency_fallback' => true,
                    ),
                ));
            }
        }
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