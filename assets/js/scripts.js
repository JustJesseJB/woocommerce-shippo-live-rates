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
        
        // Handle shipping method display and grouping
        function enhanceShippingMethods() {
            // First, find all Shippo rates
            var $shippoRates = $('.shipping_method').filter(function() {
                return $(this).val() && $(this).val().indexOf('shippo_live_rates') >= 0;
            });
            
            if ($shippoRates.length === 0) {
                return; // No Shippo rates found
            }
            
            // Group rates by carrier
            var carriers = {};
            var currentCarrier = '';
            
            $shippoRates.each(function() {
                var $label = $(this).closest('li').find('label');
                var labelText = $label.text();
                var carrierMatch = labelText.match(/^(UPS|USPS|FedEx)/i);
                
                if (carrierMatch) {
                    var carrier = carrierMatch[0];
                    
                    if (!carriers[carrier]) {
                        carriers[carrier] = [];
                    }
                    
                    carriers[carrier].push($(this).closest('li'));
                    
                    // Add carrier class to the label
                    $label.addClass('wc-shippo-live-rate');
                    
                    if (labelText.toLowerCase().indexOf('usps') >= 0) {
                        $label.addClass('wc-shippo-carrier-usps');
                    } else if (labelText.toLowerCase().indexOf('ups') >= 0) {
                        $label.addClass('wc-shippo-carrier-ups');
                    } else if (labelText.toLowerCase().indexOf('fedex') >= 0) {
                        $label.addClass('wc-shippo-carrier-fedex');
                    }
                }
            });
            
            // Add carrier headers and organize groups
            Object.keys(carriers).forEach(function(carrier) {
                var $firstItem = carriers[carrier][0];
                var headerHtml = '<li class="wc-shippo-carrier-header"><span>' + carrier + ' Shipping Options</span></li>';
                $firstItem.before(headerHtml);
                
                // Add separator after last item in group (except the last group)
                var $lastItem = carriers[carrier][carriers[carrier].length - 1];
                $lastItem.addClass('wc-shippo-last-in-group');
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