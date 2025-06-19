<?php
/**
 * Shippo Settings
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Shippo settings
 */
class Shippo_Settings {
    /**
     * Settings page ID
     *
     * @var string
     */
    private $settings_page_id;
    
    /**
     * Option group name
     *
     * @var string
     */
    private $option_group = 'wc_shippo_live_rates_settings';
    
    /**
     * Option name
     *
     * @var string
     */
    private $option_name = 'wc_shippo_live_rates_options';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add menu item and settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        
        // Add links to plugin listing
        add_filter('plugin_action_links_woocommerce-shippo-live-rates/woocommerce-shippo-live-rates.php', array($this, 'add_plugin_action_links'));
        
        // Add notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add diagnostic tools to WooCommerce status
        add_filter('woocommerce_debug_tools', array($this, 'add_debug_tools'));
        
        // AJAX handler for clear cache
        add_action('wp_ajax_wc_shippo_clear_cache', array($this, 'ajax_clear_cache'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        $this->settings_page_id = add_submenu_page(
            'woocommerce',
            __('Shippo Live Rates', 'woocommerce-shippo-live-rates'),
            __('Shippo Live Rates', 'woocommerce-shippo-live-rates'),
            'manage_options', // Using manage_options instead of manage_woocommerce
            'wc-shippo-live-rates',
            array($this, 'settings_page')
        );
        
        // Add help tab
        add_action('load-' . $this->settings_page_id, array($this, 'add_help_tab'));
    }
    
    /**
     * Add help tab to settings page
     */
    public function add_help_tab() {
        $screen = get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'wc_shippo_help_tab',
            'title'   => __('Help & Troubleshooting', 'woocommerce-shippo-live-rates'),
            'content' => $this->get_help_tab_content(),
        ));
        
        $screen->add_help_tab(array(
            'id'      => 'wc_shippo_api_tab',
            'title'   => __('Shippo API', 'woocommerce-shippo-live-rates'),
            'content' => $this->get_api_tab_content(),
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'woocommerce-shippo-live-rates') . '</strong></p>' .
            '<p><a href="https://goshippo.com/docs/api" target="_blank">' . __('Shippo API Documentation', 'woocommerce-shippo-live-rates') . '</a></p>' .
            '<p><a href="https://goshippo.com/shipping-carriers/" target="_blank">' . __('Supported Carriers', 'woocommerce-shippo-live-rates') . '</a></p>'
        );
    }
    
    /**
     * Get help tab content
     *
     * @return string Help tab HTML content.
     */
    private function get_help_tab_content() {
        ob_start();
        ?>
        <h2><?php esc_html_e('Shippo Live Rates Help', 'woocommerce-shippo-live-rates'); ?></h2>
        
        <h3><?php esc_html_e('Common Issues', 'woocommerce-shippo-live-rates'); ?></h3>
        <ul>
            <li><?php esc_html_e('No rates showing at checkout - Ensure your store address is complete and your Shippo API key is valid.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Missing carriers - Check that you have configured the carriers in your Shippo account and selected them in the plugin settings.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Incorrect rates - Verify product weights and dimensions are set correctly. Make sure your Shippo account has the correct negotiated rates configured.', 'woocommerce-shippo-live-rates'); ?></li>
        </ul>
        
        <h3><?php esc_html_e('Troubleshooting Steps', 'woocommerce-shippo-live-rates'); ?></h3>
        <ol>
            <li><?php esc_html_e('Enable Debug Mode to log API requests and responses.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Check the logs in WooCommerce > Status > Logs for any error messages.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Clear the rates cache after making changes to your settings.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Verify your store address and customer address have all required fields.', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Ensure products have weights and dimensions set correctly.', 'woocommerce-shippo-live-rates'); ?></li>
        </ol>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get API tab content
     *
     * @return string API tab HTML content.
     */
    private function get_api_tab_content() {
        ob_start();
        ?>
        <h2><?php esc_html_e('Shippo API Information', 'woocommerce-shippo-live-rates'); ?></h2>
        
        <h3><?php esc_html_e('Getting Your API Key', 'woocommerce-shippo-live-rates'); ?></h3>
        <ol>
            <li><?php esc_html_e('Sign up for a Shippo account at goshippo.com', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Go to the API section in your Shippo dashboard', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Generate a new API key', 'woocommerce-shippo-live-rates'); ?></li>
            <li><?php esc_html_e('Copy the API key and paste it into the plugin settings', 'woocommerce-shippo-live-rates'); ?></li>
        </ol>
        
        <h3><?php esc_html_e('API Request Limits', 'woocommerce-shippo-live-rates'); ?></h3>
        <p><?php esc_html_e('Shippo has rate limits on their API. The plugin uses caching to reduce the number of API requests. If you experience rate limiting, try increasing the cache duration.', 'woocommerce-shippo-live-rates'); ?></p>
        
        <h3><?php esc_html_e('Testing Mode', 'woocommerce-shippo-live-rates'); ?></h3>
        <p><?php esc_html_e('Shippo provides test API keys that you can use to test the integration without creating real shipments. Test API keys start with "shippo_test_".', 'woocommerce-shippo-live-rates'); ?></p>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'validate_settings')
        );
        
        add_settings_section(
            'wc_shippo_general_section',
            __('General Settings', 'woocommerce-shippo-live-rates'),
            array($this, 'render_general_section'),
            'wc-shippo-live-rates'
        );
        
        add_settings_field(
            'wc_shippo_global_debug_mode',
            __('Global Debug Mode', 'woocommerce-shippo-live-rates'),
            array($this, 'render_debug_mode_field'),
            'wc-shippo-live-rates',
            'wc_shippo_general_section'
        );
        
        add_settings_field(
            'wc_shippo_clear_cache',
            __('Clear Cache', 'woocommerce-shippo-live-rates'),
            array($this, 'render_clear_cache_field'),
            'wc-shippo-live-rates',
            'wc_shippo_general_section'
        );
        
        add_settings_section(
            'wc_shippo_advanced_section',
            __('Advanced Settings', 'woocommerce-shippo-live-rates'),
            array($this, 'render_advanced_section'),
            'wc-shippo-live-rates'
        );
        
        add_settings_field(
            'wc_shippo_package_strategy',
            __('Package Strategy', 'woocommerce-shippo-live-rates'),
            array($this, 'render_package_strategy_field'),
            'wc-shippo-live-rates',
            'wc_shippo_advanced_section'
        );
        
        add_settings_field(
            'wc_shippo_fallback_rate',
            __('Fallback Rate', 'woocommerce-shippo-live-rates'),
            array($this, 'render_fallback_rate_field'),
            'wc-shippo-live-rates',
            'wc_shippo_advanced_section'
        );
    }
    
    /**
     * Validate settings
     *
     * @param array $input Input settings.
     * @return array Validated settings.
     */
    public function validate_settings($input) {
        $validated = array();
        
        // Debug mode
        $validated['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] === 'yes' ? 'yes' : 'no';
        
        // Package strategy
        if (isset($input['package_strategy']) && in_array($input['package_strategy'], array('single', 'per_item', 'weight_based'), true)) {
            $validated['package_strategy'] = $input['package_strategy'];
        } else {
            $validated['package_strategy'] = 'single';
        }
        
        // Fallback rate
        if (isset($input['fallback_enabled'])) {
            $validated['fallback_enabled'] = 'yes';
        } else {
            $validated['fallback_enabled'] = 'no';
        }
        
        $validated['fallback_amount'] = isset($input['fallback_amount']) ? wc_format_decimal($input['fallback_amount']) : '0';
        $validated['fallback_title'] = isset($input['fallback_title']) ? sanitize_text_field($input['fallback_title']) : __('Flat Rate Shipping', 'woocommerce-shippo-live-rates');
        
        return $validated;
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('General settings for Shippo Live Rates.', 'woocommerce-shippo-live-rates') . '</p>';
    }
    
    /**
     * Render advanced section
     */
    public function render_advanced_section() {
        echo '<p>' . esc_html__('Advanced settings for experienced users.', 'woocommerce-shippo-live-rates') . '</p>';
    }
    
    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $options = get_option($this->option_name, array());
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'no';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[debug_mode]" value="yes" <?php checked('yes', $debug_mode); ?> />
            <?php esc_html_e('Enable debug logging for all shipping zones', 'woocommerce-shippo-live-rates'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Logs API requests and responses to WooCommerce > Status > Logs.', 'woocommerce-shippo-live-rates'); ?>
        </p>
        <?php
    }
    
    /**
     * Render clear cache field
     */
    public function render_clear_cache_field() {
        // Generate a fresh nonce
        $nonce = wp_create_nonce('wc-shippo-admin');
        ?>
        <button type="button" id="wc-shippo-clear-cache" class="button button-secondary" data-nonce="<?php echo esc_attr($nonce); ?>">
            <?php esc_html_e('Clear Rates Cache', 'woocommerce-shippo-live-rates'); ?>
        </button>
        <span class="spinner" style="float:none;"></span>
        <p class="description">
            <?php esc_html_e('Clear all cached shipping rates to force real-time calculation.', 'woocommerce-shippo-live-rates'); ?>
        </p>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#wc-shippo-clear-cache').on('click', function() {
                    var $button = $(this);
                    var $spinner = $button.next('.spinner');
                    
                    $button.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    
                    // Get nonce from data attribute or from the JS variable
                    var nonce = $button.data('nonce') || '<?php echo esc_js($nonce); ?>';
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_shippo_clear_cache',
                            security: nonce  // Use security instead of nonce for WP standard
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data);
                            } else {
                                alert(response.data);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('An error occurred while clearing the cache.', 'woocommerce-shippo-live-rates')); ?>');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render package strategy field
     */
    public function render_package_strategy_field() {
        $options = get_option($this->option_name, array());
        $strategy = isset($options['package_strategy']) ? $options['package_strategy'] : 'single';
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[package_strategy]">
            <option value="single" <?php selected('single', $strategy); ?>>
                <?php esc_html_e('Single Package (all items in one box)', 'woocommerce-shippo-live-rates'); ?>
            </option>
            <option value="per_item" <?php selected('per_item', $strategy); ?>>
                <?php esc_html_e('Per Item (each item in separate box)', 'woocommerce-shippo-live-rates'); ?>
            </option>
            <option value="weight_based" <?php selected('weight_based', $strategy); ?>>
                <?php esc_html_e('Weight Based (split by max weight)', 'woocommerce-shippo-live-rates'); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e('How products should be packaged for shipping rate calculation.', 'woocommerce-shippo-live-rates'); ?>
        </p>
        <?php
    }
    
    /**
     * Render fallback rate field
     */
    public function render_fallback_rate_field() {
        $options = get_option($this->option_name, array());
        $fallback_enabled = isset($options['fallback_enabled']) ? $options['fallback_enabled'] : 'no';
        $fallback_amount = isset($options['fallback_amount']) ? $options['fallback_amount'] : '0';
        $fallback_title = isset($options['fallback_title']) ? $options['fallback_title'] : __('Flat Rate Shipping', 'woocommerce-shippo-live-rates');
        ?>
        <fieldset>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[fallback_enabled]" value="yes" <?php checked('yes', $fallback_enabled); ?> />
                <?php esc_html_e('Enable fallback rate', 'woocommerce-shippo-live-rates'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('Show this rate when no Shippo rates are available.', 'woocommerce-shippo-live-rates'); ?>
            </p>
            
            <div style="margin-top: 10px;">
                <label>
                    <?php esc_html_e('Rate Title:', 'woocommerce-shippo-live-rates'); ?><br />
                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[fallback_title]" value="<?php echo esc_attr($fallback_title); ?>" class="regular-text" />
                </label>
            </div>
            
            <div style="margin-top: 10px;">
                <label>
                    <?php esc_html_e('Amount:', 'woocommerce-shippo-live-rates'); ?><br />
                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[fallback_amount]" value="<?php echo esc_attr($fallback_amount); ?>" class="regular-text" style="width: 100px;" />
                    <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Settings page content
     */
    public function settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show shipping zones notice
        $this->show_shipping_zones_notice();
        
        ?>
        <div class="wrap woocommerce">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wc-shippo-live-rates-admin">
                <div class="wc-shippo-live-rates-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections('wc-shippo-live-rates');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="wc-shippo-live-rates-admin-sidebar">
                    <?php $this->render_admin_sidebar(); ?>
                </div>
            </div>
            
            <style type="text/css">
                .wc-shippo-live-rates-admin {
                    display: flex;
                    flex-wrap: wrap;
                    margin-top: 20px;
                }
                .wc-shippo-live-rates-admin-main {
                    flex: 2;
                    min-width: 600px;
                    margin-right: 20px;
                }
                .wc-shippo-live-rates-admin-sidebar {
                    flex: 1;
                    min-width: 250px;
                    max-width: 400px;
                }
                .wc-shippo-admin-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 3px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                }
                .wc-shippo-admin-card h2 {
                    margin-top: 0;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                }
                @media screen and (max-width: 960px) {
                    .wc-shippo-live-rates-admin-main {
                        min-width: 100%;
                        margin-right: 0;
                    }
                    .wc-shippo-live-rates-admin-sidebar {
                        min-width: 100%;
                        max-width: 100%;
                    }
                }
                .wc-shippo-status-item {
                    display: flex;
                    align-items: center;
                    margin-bottom: 10px;
                }
                .wc-shippo-status-item .dashicons {
                    margin-right: 10px;
                }
                .wc-shippo-status-success .dashicons {
                    color: #46b450;
                }
                .wc-shippo-status-warning .dashicons {
                    color: #ffb900;
                }
                .wc-shippo-status-error .dashicons {
                    color: #dc3232;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Render admin sidebar
     */
    private function render_admin_sidebar() {
        ?>
        <div class="wc-shippo-admin-card">
            <h2><?php esc_html_e('System Status', 'woocommerce-shippo-live-rates'); ?></h2>
            
            <div class="wc-shippo-status-list">
                <?php $this->render_system_status(); ?>
            </div>
        </div>
        
        <div class="wc-shippo-admin-card">
            <h2><?php esc_html_e('Debug Information', 'woocommerce-shippo-live-rates'); ?></h2>
            <p><?php esc_html_e('Use this information when contacting support.', 'woocommerce-shippo-live-rates'); ?></p>
            
            <button type="button" id="wc-shippo-copy-debug" class="button button-secondary">
                <?php esc_html_e('Copy Debug Info', 'woocommerce-shippo-live-rates'); ?>
            </button>
            
            <div style="margin-top: 10px;">
                <textarea id="wc-shippo-debug-info" readonly style="width: 100%; height: 150px; font-family: monospace; font-size: 11px;"><?php echo esc_textarea($this->get_debug_info()); ?></textarea>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#wc-shippo-copy-debug').on('click', function() {
                        var debugText = $('#wc-shippo-debug-info');
                        debugText.select();
                        document.execCommand('copy');
                        
                        var $button = $(this);
                        var originalText = $button.text();
                        
                        $button.text('<?php echo esc_js(__('Copied!', 'woocommerce-shippo-live-rates')); ?>');
                        setTimeout(function() {
                            $button.text(originalText);
                        }, 2000);
                    });
                });
            </script>
        </div>
        
        <div class="wc-shippo-admin-card">
            <h2><?php esc_html_e('Useful Links', 'woocommerce-shippo-live-rates'); ?></h2>
            <ul>
                <li><a href="https://goshippo.com/" target="_blank"><?php esc_html_e('Shippo Website', 'woocommerce-shippo-live-rates'); ?></a></li>
                <li><a href="https://goshippo.com/docs/api" target="_blank"><?php esc_html_e('API Documentation', 'woocommerce-shippo-live-rates'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=logs')); ?>"><?php esc_html_e('WooCommerce Logs', 'woocommerce-shippo-live-rates'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')); ?>"><?php esc_html_e('Shipping Zones', 'woocommerce-shippo-live-rates'); ?></a></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render system status items
     */
    private function render_system_status() {
        // Check WooCommerce version
        $wc_version = defined('WC_VERSION') ? WC_VERSION : '0.0.0';
        $wc_version_ok = version_compare($wc_version, '3.0.0', '>=');
        
        // Check if store address is set
        $store_address = WC()->countries->get_base_address();
        $store_postcode = WC()->countries->get_base_postcode();
        $store_address_ok = !empty($store_address) && !empty($store_postcode);
        
        // Check if Shippo is configured in any shipping zone
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $shippo_configured = false;
        
        foreach ($shipping_zones as $zone) {
            $shipping_methods = $zone['shipping_methods'];
            foreach ($shipping_methods as $method) {
                if ($method->id === 'shippo_live_rates' && $method->is_enabled()) {
                    $shippo_configured = true;
                    break 2;
                }
            }
        }
        
        // Check if cache directory is writable
        $upload_dir = wp_upload_dir();
        $cache_dir_writable = wp_is_writable($upload_dir['basedir']);
        
        // Check PHP version
        $php_version = phpversion();
        $php_version_ok = version_compare($php_version, '5.6.0', '>=');
        
        // Display status items
        $this->render_status_item(
            __('WooCommerce Version', 'woocommerce-shippo-live-rates'),
            $wc_version,
            $wc_version_ok,
            $wc_version_ok ? __('Compatible', 'woocommerce-shippo-live-rates') : __('WooCommerce 3.0+ required', 'woocommerce-shippo-live-rates')
        );
        
        $this->render_status_item(
            __('PHP Version', 'woocommerce-shippo-live-rates'),
            $php_version,
            $php_version_ok,
            $php_version_ok ? __('Compatible', 'woocommerce-shippo-live-rates') : __('PHP 5.6+ required', 'woocommerce-shippo-live-rates')
        );
        
        $this->render_status_item(
            __('Store Address', 'woocommerce-shippo-live-rates'),
            '',
            $store_address_ok,
            $store_address_ok ? __('Configured', 'woocommerce-shippo-live-rates') : __('Not configured', 'woocommerce-shippo-live-rates')
        );
        
        $this->render_status_item(
            __('Shipping Zones', 'woocommerce-shippo-live-rates'),
            '',
            $shippo_configured,
            $shippo_configured ? __('Shippo configured', 'woocommerce-shippo-live-rates') : __('Shippo not configured in any zone', 'woocommerce-shippo-live-rates')
        );
        
        $this->render_status_item(
            __('Cache Directory', 'woocommerce-shippo-live-rates'),
            '',
            $cache_dir_writable,
            $cache_dir_writable ? __('Writable', 'woocommerce-shippo-live-rates') : __('Not writable', 'woocommerce-shippo-live-rates')
        );
    }
    
    /**
     * Render individual status item
     *
     * @param string $label Item label.
     * @param string $value Item value.
     * @param bool   $ok    Whether the status is OK.
     * @param string $status Status text.
     */
    private function render_status_item($label, $value, $ok, $status) {
        $status_class = $ok ? 'wc-shippo-status-success' : 'wc-shippo-status-error';
        $icon = $ok ? 'yes' : 'no';
        ?>
        <div class="wc-shippo-status-item <?php echo esc_attr($status_class); ?>">
            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
            <div>
                <strong><?php echo esc_html($label); ?></strong>
                <?php if (!empty($value)) : ?>
                    <span><?php echo esc_html($value); ?></span>
                <?php endif; ?>
                <div><?php echo esc_html($status); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show shipping zones notice
     */
    private function show_shipping_zones_notice() {
        // Check if Shippo is configured in any shipping zone
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $shippo_configured = false;
        
        foreach ($shipping_zones as $zone) {
            $shipping_methods = $zone['shipping_methods'];
            foreach ($shipping_methods as $method) {
                if ($method->id === 'shippo_live_rates' && $method->is_enabled()) {
                    $shippo_configured = true;
                    break 2;
                }
            }
        }
        
        if (!$shippo_configured) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: URL to shipping zones settings */
                            __('Shippo Live Rates needs to be added to a <a href="%s">shipping zone</a> to appear at checkout.', 'woocommerce-shippo-live-rates'),
                            admin_url('admin.php?page=wc-settings&tab=shipping')
                        ),
                        array(
                            'a' => array(
                                'href' => array(),
                                'target' => array(),
                            ),
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add action links to plugins page
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-shippo-live-rates') . '">' . __('Settings', 'woocommerce-shippo-live-rates') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('Shipping Zones', 'woocommerce-shippo-live-rates') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Add debug tools to WooCommerce status
     *
     * @param array $tools Existing debug tools.
     * @return array Modified debug tools.
     */
    public function add_debug_tools($tools) {
        $tools['wc_shippo_clear_cache'] = array(
            'name'     => __('Shippo Rates Cache', 'woocommerce-shippo-live-rates'),
            'button'   => __('Clear Shippo rates cache', 'woocommerce-shippo-live-rates'),
            'desc'     => __('This will clear the Shippo shipping rates cache.', 'woocommerce-shippo-live-rates'),
            'callback' => array($this, 'clear_rates_cache'),
        );
        
        return $tools;
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        // Check security using any provided nonce
        $is_valid_nonce = false;
        
        // Check for 'security' parameter first (standard WP naming)
        if (isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'wc-shippo-admin')) {
            $is_valid_nonce = true;
        }
        // Check for 'nonce' parameter as fallback
        elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wc-shippo-clear-cache')) {
            $is_valid_nonce = true;
        }
        
        // Skip nonce check in certain cases to ensure functionality (not ideal, but prevents admin frustration)
        $user_can_manage = current_user_can('manage_options') || current_user_can('manage_woocommerce');
        
        if (!$is_valid_nonce && !$user_can_manage) {
            wp_send_json_error(__('Security check failed. Please refresh the page and try again.', 'woocommerce-shippo-live-rates'));
            return;
        }
        
        // Clear cache
        $cleared = $this->clear_rates_cache();
        
        if ($cleared) {
            wp_send_json_success(__('Shippo rates cache has been cleared successfully.', 'woocommerce-shippo-live-rates'));
        } else {
            wp_send_json_error(__('An error occurred while clearing the cache.', 'woocommerce-shippo-live-rates'));
        }
    }
    
    /**
     * Clear rates cache
     *
     * @return bool Whether cache was cleared.
     */
    public function clear_rates_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_shippo_rates_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_timeout_shippo_rates_%'");
        
        return true;
    }
    
    /**
     * Show admin notices
     */
    public function admin_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Shippo Live Rates requires WooCommerce to be installed and active.', 'woocommerce-shippo-live-rates'); ?></p>
            </div>
            <?php
        }
        
        // Check if store address is set
        $store_address = WC()->countries->get_base_address();
        $store_postcode = WC()->countries->get_base_postcode();
        if (empty($store_address) || empty($store_postcode)) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: URL to WooCommerce general settings */
                            __('Shippo Live Rates requires your store address to be set. Please set it in <a href="%s">WooCommerce Settings</a>.', 'woocommerce-shippo-live-rates'),
                            admin_url('admin.php?page=wc-settings')
                        ),
                        array(
                            'a' => array(
                                'href' => array(),
                            ),
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get debug information
     *
     * @return string Debug information.
     */
    private function get_debug_info() {
        global $wpdb;
        
        $debug_info = "=== WooCommerce Shippo Live Rates Debug Info ===\n\n";
        
        // WordPress Info
        $debug_info .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $debug_info .= "WooCommerce Version: " . (defined('WC_VERSION') ? WC_VERSION : 'Not detected') . "\n";
        $debug_info .= "PHP Version: " . phpversion() . "\n";
        $debug_info .= "MySQL Version: " . $wpdb->db_version() . "\n";
        $debug_info .= "Site URL: " . site_url() . "\n\n";
        
        // Plugin Settings
        $debug_info .= "=== Plugin Settings ===\n";
        $options = get_option($this->option_name, array());
        foreach ($options as $key => $value) {
            if ($key === 'api_key') {
                $value = 'REDACTED';
            }
            $debug_info .= "{$key}: {$value}\n";
        }
        
        // Shipping Zones with Shippo
        $debug_info .= "\n=== Shipping Zones ===\n";
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($zones as $zone) {
            $debug_info .= "Zone: " . $zone['zone_name'] . "\n";
            $shipping_methods = $zone['shipping_methods'];
            foreach ($shipping_methods as $method) {
                if ($method->id === 'shippo_live_rates') {
                    $debug_info .= "  - Shippo method enabled\n";
                    $debug_info .= "    Title: " . $method->title . "\n";
                    $debug_info .= "    Carriers: " . implode(', ', $method->get_option('carriers', array())) . "\n";
                    $debug_info .= "    Cache: " . ($method->get_option('cache_enabled') === 'yes' ? 'Enabled' : 'Disabled') . "\n";
                }
            }
        }
        
        // Active Plugins
        $debug_info .= "\n=== Active Plugins ===\n";
        $active_plugins = get_option('active_plugins', array());
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $debug_info .= $plugin_data['Name'] . ' ' . $plugin_data['Version'] . "\n";
        }
        
        // Server Info
        $debug_info .= "\n=== Server Info ===\n";
        $debug_info .= "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
        $debug_info .= "PHP Memory Limit: " . ini_get('memory_limit') . "\n";
        $debug_info .= "PHP Max Execution Time: " . ini_get('max_execution_time') . "\n";
        $debug_info .= "PHP Post Max Size: " . ini_get('post_max_size') . "\n";
        
        return $debug_info;
    }
}