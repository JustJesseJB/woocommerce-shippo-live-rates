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
        if (!$('form.woocommerce-checkout').length && !$('.woocommerce-cart-form').length) {
            return;
        }
        
        // Handle shipping method display
        function enhanceShippingMethods() {
            $('.shipping_method').each(function() {
                if ($(this).val() && $(this).val().indexOf('shippo_live_rates') >= 0) {
                    var $label = $(this).closest('li').find('label');
                    
                    // Only add class if not already added
                    if (!$label.hasClass('wc-shippo-live-rate')) {
                        $label.addClass('wc-shippo-live-rate');
                        
                        // Identify carrier for possible styling
                        var labelText = $label.text().toLowerCase();
                        if (labelText.indexOf('usps') >= 0) {
                            $label.addClass('wc-shippo-carrier-usps');
                        } else if (labelText.indexOf('ups') >= 0) {
                            $label.addClass('wc-shippo-carrier-ups');
                        } else if (labelText.indexOf('fedex') >= 0) {
                            $label.addClass('wc-shippo-carrier-fedex');
                        }
                    }
                }
            });
        }
        
        // Listen for shipping calculation events
        $(document.body).on('updated_shipping_method updated_checkout updated_wc_div', function() {
            enhanceShippingMethods();
        });
        
        // Initial enhancement
        enhanceShippingMethods();
    });
})(jQuery);