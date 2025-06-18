<?php
/**
 * WooCommerce System Status - Shippo Live Rates Info
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="wc_status_table widefat" cellspacing="0" id="wc-shippo-live-rates-status">
    <thead>
        <tr>
            <th colspan="3" data-export-label="Shippo Live Rates">
                <h2><?php esc_html_e('Shippo Live Rates', 'woocommerce-shippo-live-rates'); ?> 
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('This section shows information about the Shippo Live Rates plugin configuration.', 'woocommerce-shippo-live-rates'); ?>"></span>
                </h2>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td data-export-label="Version"><?php esc_html_e('Version', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('The version of the Shippo Live Rates plugin installed on your site.', 'woocommerce-shippo-live-rates')); ?></td>
            <td><?php echo esc_html(WC_SHIPPO_LIVE_RATES_VERSION); ?></td>
        </tr>
        <tr>
            <td data-export-label="Debug Mode"><?php esc_html_e('Debug Mode', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('Whether debug mode is enabled for logging API requests and responses.', 'woocommerce-shippo-live-rates')); ?></td>
            <td>
                <?php
                if (isset($settings['debug_mode']) && 'yes' === $settings['debug_mode']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html__('Enabled', 'woocommerce-shippo-live-rates') . '</mark>';
                } else {
                    echo '<mark class="no"><span class="dashicons dashicons-no"></span> ' . esc_html__('Disabled', 'woocommerce-shippo-live-rates') . '</mark>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td data-export-label="Shipping Zones"><?php esc_html_e('Shipping Zones', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('Shipping zones where Shippo Live Rates is configured.', 'woocommerce-shippo-live-rates')); ?></td>
            <td>
                <?php
                $shipping_zones = WC_Shipping_Zones::get_zones();
                $shippo_zones = array();
                
                foreach ($shipping_zones as $zone) {
                    $shipping_methods = $zone['shipping_methods'];
                    foreach ($shipping_methods as $method) {
                        if ($method->id === 'shippo_live_rates' && $method->is_enabled()) {
                            $shippo_zones[] = $zone['zone_name'];
                            break;
                        }
                    }
                }
                
                if (!empty($shippo_zones)) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html(implode(', ', $shippo_zones)) . '</mark>';
                } else {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Not configured in any shipping zone', 'woocommerce-shippo-live-rates') . '</mark>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td data-export-label="Store Address"><?php esc_html_e('Store Address', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('Whether your store address is properly configured.', 'woocommerce-shippo-live-rates')); ?></td>
            <td>
                <?php
                $store_address = WC()->countries->get_base_address();
                $store_postcode = WC()->countries->get_base_postcode();
                
                if (!empty($store_address) && !empty($store_postcode)) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html__('Properly configured', 'woocommerce-shippo-live-rates') . '</mark>';
                } else {
                    echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Incomplete store address', 'woocommerce-shippo-live-rates') . '</mark>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td data-export-label="Fallback Rate"><?php esc_html_e('Fallback Rate', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('Whether a fallback shipping rate is configured for when Shippo rates are unavailable.', 'woocommerce-shippo-live-rates')); ?></td>
            <td>
                <?php
                if (isset($settings['fallback_enabled']) && 'yes' === $settings['fallback_enabled']) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . 
                         sprintf(
                             /* translators: %1$s: fallback rate title, %2$s: fallback rate amount */
                             esc_html__('Enabled: %1$s (%2$s)', 'woocommerce-shippo-live-rates'),
                             isset($settings['fallback_title']) ? esc_html($settings['fallback_title']) : esc_html__('Flat Rate Shipping', 'woocommerce-shippo-live-rates'),
                             wc_price(isset($settings['fallback_amount']) ? $settings['fallback_amount'] : 0)
                         ) . '</mark>';
                } else {
                    echo '<mark class="no"><span class="dashicons dashicons-no"></span> ' . esc_html__('Disabled', 'woocommerce-shippo-live-rates') . '</mark>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td data-export-label="Logs"><?php esc_html_e('Debug Logs', 'woocommerce-shippo-live-rates'); ?>:</td>
            <td class="help"><?php echo wc_help_tip(__('Recent logs from Shippo Live Rates.', 'woocommerce-shippo-live-rates')); ?></td>
            <td>
                <?php
                $log_path = WC_Log_Handler_File::get_log_file_path('woocommerce-shippo-live-rates');
                if (file_exists($log_path)) {
                    echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . 
                         sprintf(
                             /* translators: %s: URL to logs page */
                             __('Log file exists - <a href="%s">View Logs</a>', 'woocommerce-shippo-live-rates'),
                             esc_url(admin_url('admin.php?page=wc-status&tab=logs'))
                         ) . '</mark>';
                } else {
                    echo '<mark class="no"><span class="dashicons dashicons-no"></span> ' . esc_html__('No log files found', 'woocommerce-shippo-live-rates') . '</mark>';
                }
                ?>
            </td>
        </tr>
    </tbody>
</table>