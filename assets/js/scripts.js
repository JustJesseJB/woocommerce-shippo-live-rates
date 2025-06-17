/**
 * WooCommerce Shippo Live Rates Frontend Scripts
 *
 * @package WooCommerce_Shippo_Live_Rates
 */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only run on checkout page
        if (!$('form.woocommerce-checkout').length) {
            return;
        }
        
        // Add loading indicator when shipping calculator is used
        $(document.body).on('updated_shipping_method', function() {
            $('.shipping_method').each(function() {
                if ($(this).val() && $(this).val().indexOf('shippo_live_rates') >= 0) {
                    // Add carrier logos if available
                    const carrierName = $(this).closest('li').find('label').text().toLowerCase();
                    
                    if (carrierName.indexOf('usps') >= 0) {
                        $(this).closest('li').find('label').addClass('wc-shippo-live-rate');
                    } else if (carrierName.indexOf('ups') >= 0) {
                        $(this).closest('li').find('label').addClass('wc-shippo-live-rate');
                    } else if (carrierName.indexOf('fedex') >= 0) {
                        $(this).closest('li').find('label').addClass('wc-shippo-live-rate');
                    }
                }
            });
        });
        
        // Trigger updated_shipping_method to apply the changes
        $(document.body).trigger('updated_shipping_method');
    });
    
    // Handle shipping calculator updates in cart
    $(document.body).on('updated_wc_div', function() {
        if ($('.shipping_method').length) {
            $(document.body).trigger('updated_shipping_method');
        }
    });
})(jQuery);