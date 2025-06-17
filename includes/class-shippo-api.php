<?php
/**
 * Shippo API Handler
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Shippo API requests
 */
class Shippo_API {
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_url = 'https://api.goshippo.com/';
    
    /**
     * Constructor
     *
     * @param string $api_key    Shippo API key.
     * @param bool   $debug_mode Whether to enable debug logging.
     */
    public function __construct($api_key, $debug_mode = false) {
        $this->api_key = $api_key;
        $this->debug_mode = $debug_mode;
        
        if ($this->debug_mode) {
            $this->setup_logger();
        }
    }
    
    /**
     * Set up logging functionality
     *
     * @return void
     */
    private function setup_logger() {
        if (class_exists('WC_Logger')) {
            $this->logger = new WC_Logger();
        }
    }
    
    /**
     * Log messages when debug mode is on
     *
     * @param string $message Message to log.
     * @param string $level   Log level (default: 'info').
     * @return void
     */
    public function log($message, $level = 'info') {
        if ($this->debug_mode && $this->logger) {
            $this->logger->log($level, $message, array('source' => 'woocommerce-shippo-live-rates'));
        }
    }
    
    /**
     * Get shipping rates from Shippo
     *
     * @param array $origin      Origin address.
     * @param array $destination Destination address.
     * @param array $packages    Package details.
     * @param array $carriers    Selected carriers.
     * @return array|false       Rates response or false on failure.
     */
    public function get_rates($origin, $destination, $packages, $carriers) {
        // Log the request data
        $this->log('Requesting rates from Shippo with data: ' . wp_json_encode([
            'origin' => $origin,
            'destination' => $destination,
            'packages' => $packages,
            'carriers' => $carriers
        ]));
        
        // Prepare the API request for shipment
        $shipment_data = [
            'address_from' => $origin,
            'address_to' => $destination,
            'parcels' => $packages,
            'async' => false
        ];
        
        // Create a shipment object first
        $shipment = $this->request('shipments/', 'POST', $shipment_data);
        
        if (!$shipment || isset($shipment['error'])) {
            $error_message = isset($shipment['error']['detail']) ? $shipment['error']['detail'] : 'Unknown error';
            $this->log('Failed to create shipment: ' . $error_message, 'error');
            return false;
        }
        
        // Prepare the API request for rates with specific carriers
        $rates_data = [
            'shipment' => $shipment['object_id'],
            'carriers' => $carriers,
        ];
        
        // Get rates for the shipment
        $response = $this->request('shipment-rates/', 'POST', $rates_data);
        
        if (!$response || isset($response['error'])) {
            $error_message = isset($response['error']['detail']) ? $response['error']['detail'] : 'Unknown error';
            $this->log('Failed to get rates: ' . $error_message, 'error');
            return false;
        }
        
        $this->log('Received rates from Shippo: ' . wp_json_encode($response));
        
        return $response;
    }
    
    /**
     * Make an API request to Shippo
     *
     * @param string $endpoint API endpoint.
     * @param string $method   HTTP method (GET, POST, etc.).
     * @param array  $data     Request data.
     * @return array|false     Response data or false on failure.
     */
    private function request($endpoint, $method = 'GET', $data = []) {
        $url = $this->api_url . ltrim($endpoint, '/');
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'ShippoToken ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30,
            'sslverify' => true,
        ];
        
        if (!empty($data) && in_array($method, ['POST', 'PUT'], true)) {
            // Ensure all numeric values are rounded to 4 decimal places
            $data = $this->round_numeric_values($data, 4);
            $args['body'] = wp_json_encode($data);
        }
        
        $this->log("Making {$method} request to {$url}");
        
        // Make the API request
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $this->log('API request error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code >= 400) {
            $this->log("API error ({$response_code}): " . wp_json_encode($data), 'error');
            return ['error' => $data];
        }
        
        return $data;
    }
    
    /**
     * Rounds all numeric values in an array to a specific precision
     *
     * @param array $data      The data array to process
     * @param int   $precision The decimal precision to round to
     * @return array           The processed data array
     */
    private function round_numeric_values($data, $precision = 4) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->round_numeric_values($value, $precision);
            } elseif (is_numeric($value)) {
                $data[$key] = round((float)$value, $precision);
            }
        }
        return $data;
    }
    
    /**
     * Test connection to Shippo API
     *
     * @return bool Whether connection was successful.
     */
    public function test_connection() {
        // Make a simple API request to verify credentials
        $response = $this->request('user/');
        
        if ($response && !isset($response['error'])) {
            $this->log('Connection test successful');
            return true;
        } else {
            $this->log('Connection test failed', 'error');
            return false;
        }
    }
    
    /**
     * Validate address with Shippo
     *
     * @param array $address Address data.
     * @return array|false Validated address or false on failure.
     */
    public function validate_address($address) {
        $response = $this->request('addresses/', 'POST', [
            'name' => isset($address['name']) ? $address['name'] : '',
            'street1' => isset($address['street1']) ? $address['street1'] : '',
            'street2' => isset($address['street2']) ? $address['street2'] : '',
            'city' => isset($address['city']) ? $address['city'] : '',
            'state' => isset($address['state']) ? $address['state'] : '',
            'zip' => isset($address['zip']) ? $address['zip'] : '',
            'country' => isset($address['country']) ? $address['country'] : '',
            'validate' => true,
        ]);
        
        if (!$response || isset($response['error'])) {
            $this->log('Address validation failed: ' . wp_json_encode($response), 'error');
            return false;
        }
        
        return $response;
    }
    
    /**
     * Get available carrier accounts
     *
     * @return array|false Carrier accounts or false on failure.
     */
    public function get_carrier_accounts() {
        $response = $this->request('carrier_accounts/');
        
        if (!$response || isset($response['error'])) {
            $this->log('Failed to get carrier accounts: ' . wp_json_encode($response), 'error');
            return false;
        }
        
        return $response;
    }
}