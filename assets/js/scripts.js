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
            var carrierOrder = []; // Track order of appearance
            
            $('.shipping_method').each(function() {
                var $input = $(this);
                var inputValue = $input.val();
                
                // Skip if not a Shippo rate
                if (!inputValue || inputValue.indexOf('shippo_live_rates') < 0) {
                    return;
                }
                
                var $li = $(this).closest('li');
                var $label = $li.find('label');
                var labelText = $label.text().trim();
                
                // More robust carrier detection
                var carrier = null;
                var carrierClass = '';
                
                // Check the input value first for carrier info
                if (inputValue.match(/usps/i)) {
                    carrier = 'USPS';
                    carrierClass = 'wc-shippo-carrier-usps';
                } else if (inputValue.match(/ups/i)) {
                    carrier = 'UPS';
                    carrierClass = 'wc-shippo-carrier-ups';
                } else if (inputValue.match(/fedex/i)) {
                    carrier = 'FedEx';
                    carrierClass = 'wc-shippo-carrier-fedex';
                } else {
                    // Fallback to checking label text
                    if (labelText.match(/^USPS\s/i) || labelText.match(/\sUSPS\s/i)) {
                        carrier = 'USPS';
                        carrierClass = 'wc-shippo-carrier-usps';
                    } else if (labelText.match(/^UPS\s/i) || labelText.match(/\sUPS\s/i)) {
                        carrier = 'UPS';
                        carrierClass = 'wc-shippo-carrier-ups';
                    } else if (labelText.match(/^FedEx\s/i) || labelText.match(/\sFedEx\s/i)) {
                        carrier = 'FedEx';
                        carrierClass = 'wc-shippo-carrier-fedex';
                    }
                }
                
                if (carrier) {
                    if (!carriers[carrier]) {
                        carriers[carrier] = [];
                        carrierOrder.push(carrier);
                    }
                    
                    carriers[carrier].push($li);
                    
                    // Add carrier class to label
                    $label.addClass('wc-shippo-live-rate ' + carrierClass);
                    
                    // Hide the item initially - we'll show it in the right order later
                    $li.hide();
                }
            });
            
            // Process carriers in a specific order: FedEx, UPS, USPS
            var preferredOrder = ['FedEx', 'UPS', 'USPS'];
            var sortedCarriers = [];
            
            // First add carriers in preferred order if they exist
            preferredOrder.forEach(function(carrierName) {
                if (carriers[carrierName]) {
                    sortedCarriers.push(carrierName);
                }
            });
            
            // Add any remaining carriers not in preferred order
            carrierOrder.forEach(function(carrierName) {
                if (sortedCarriers.indexOf(carrierName) === -1) {
                    sortedCarriers.push(carrierName);
                }
            });
            
            // Now rebuild the display in the correct order
            var $shippingMethods = $('#shipping_method, .shipping_method').first().closest('ul');
            var $insertPoint = $shippingMethods.find('li').last(); // Find last non-Shippo item
            
            // Find the position to insert Shippo rates (before local pickup if it exists)
            $shippingMethods.find('li').each(function() {
                var $input = $(this).find('input[type="radio"]');
                if ($input.length && $input.val() && $input.val().indexOf('local_pickup') >= 0) {
                    $insertPoint = $(this);
                    return false; // break the loop
                }
            });
            
            // Add carrier groups in order
            sortedCarriers.forEach(function(carrier) {
                if (carriers[carrier] && carriers[carrier].length > 0) {
                    // Create and insert header
                    var $header = $('<li class="wc-shippo-carrier-header"><span>' + carrier + ' Shipping Options</span></li>');
                    $header.insertBefore($insertPoint);
                    
                    // Sort rates by price within each carrier
                    carriers[carrier].sort(function(a, b) {
                        var priceA = parseFloat($(a).find('label').text().replace(/[^0-9.]/g, '').match(/\d+\.\d+$/));
                        var priceB = parseFloat($(b).find('label').text().replace(/[^0-9.]/g, '').match(/\d+\.\d+$/));
                        return priceA - priceB;
                    });
                    
                    // Insert sorted rates
                    carriers[carrier].forEach(function($item, index) {
                        $item.insertBefore($insertPoint).show();
                        
                        // Add separator class to last item in group
                        if (index === carriers[carrier].length - 1) {
                            $item.addClass('wc-shippo-last-in-group');
                        }
                    });
                }
            });
            
            // Ensure local pickup is not selected by default if other options exist
            var $firstShippoRate = $shippingMethods.find('input[value*="shippo_live_rates"]:first');
            if ($firstShippoRate.length && !$shippingMethods.find('input[type="radio"]:checked').length) {
                $firstShippoRate.prop('checked', true).trigger('change');
            }
        }
        
        // Initial enhancement with a small delay to ensure all elements are loaded
        setTimeout(enhanceShippingMethods, 300);
        
        // Listen for shipping calculation events
        $(document.body).on('updated_shipping_method updated_checkout updated_wc_div', function() {
            // Small delay to ensure DOM is updated
            setTimeout(enhanceShippingMethods, 300);
        });
        
        // Also trigger when shipping methods are loaded via AJAX
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && (settings.url.indexOf('update_order_review') > -1 || settings.url.indexOf('wc-ajax') > -1)) {
                setTimeout(enhanceShippingMethods, 500);
            }
        });
    });
})(jQuery);