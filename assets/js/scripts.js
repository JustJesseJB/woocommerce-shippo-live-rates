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
        
        // Global variable to track if we've already processed a group
        var processedCarriers = {};
        
        // Handle shipping method display and grouping
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
})(jQuery);