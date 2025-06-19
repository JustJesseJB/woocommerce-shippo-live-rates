/**
 * WooCommerce Shippo Live Rates Admin Scripts
 */
(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Copy to clipboard functionality
        if ($('#wc-shippo-copy-debug').length) {
            $('#wc-shippo-copy-debug').on('click', function() {
                var debugText = document.getElementById('wc-shippo-debug-info');
                debugText.select();
                document.execCommand('copy');
                
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text(wc_shippo_params && wc_shippo_params.i18n ? wc_shippo_params.i18n.success : 'Copied!');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            });
        }
        
        // Clear cache button in admin
        if ($('#wc-shippo-clear-cache').length) {
            $('#wc-shippo-clear-cache').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                
                $button.prop('disabled', true);
                $spinner.css('visibility', 'visible');
                
                // Get the current nonce from the data attribute or from the global variable
                var nonce = '';
                
                // Try to get nonce from data attribute first
                if ($button.data('nonce')) {
                    nonce = $button.data('nonce');
                } 
                // Then try the global variable
                else if (typeof wc_shippo_params !== 'undefined' && wc_shippo_params.nonce) {
                    nonce = wc_shippo_params.nonce;
                }
                // Finally, try to get it from a hidden field if present
                else {
                    var $nonceField = $('input[name="_wpnonce"]').first();
                    if ($nonceField.length) {
                        nonce = $nonceField.val();
                    }
                }
                
                $.ajax({
                    url: ajaxurl || wc_shippo_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_shippo_clear_cache',
                        security: nonce  // Use 'security' instead of 'nonce'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data);
                        } else {
                            alert(response.data || (wc_shippo_params && wc_shippo_params.i18n ? wc_shippo_params.i18n.error : 'An error occurred.'));
                        }
                    },
                    error: function() {
                        alert(wc_shippo_params && wc_shippo_params.i18n ? wc_shippo_params.i18n.error : 'An error occurred.');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.css('visibility', 'hidden');
                    }
                });
            });
        }
        
        // Dynamic fields visibility based on selections
        function toggleFieldVisibility() {
            // Toggle fallback rate fields
            var $fallbackEnabled = $('input[name="wc_shippo_live_rates_options[fallback_enabled]"]');
            var $fallbackFields = $('.wc-shippo-fallback-fields');
            
            if ($fallbackEnabled.length) {
                if ($fallbackEnabled.is(':checked')) {
                    $fallbackFields.show();
                } else {
                    $fallbackFields.hide();
                }
                
                $fallbackEnabled.on('change', function() {
                    if ($(this).is(':checked')) {
                        $fallbackFields.slideDown();
                    } else {
                        $fallbackFields.slideUp();
                    }
                });
            }
            
            // Toggle cache duration field
            var $cacheEnabled = $('input[name$="[cache_enabled]"]');
            var $cacheDuration = $('.cache-duration-field');
            
            if ($cacheEnabled.length) {
                if ($cacheEnabled.is(':checked')) {
                    $cacheDuration.show();
                } else {
                    $cacheDuration.hide();
                }
                
                $cacheEnabled.on('change', function() {
                    if ($(this).is(':checked')) {
                        $cacheDuration.slideDown();
                    } else {
                        $cacheDuration.slideUp();
                    }
                });
            }
        }
        
        // Initialize dynamic fields
        toggleFieldVisibility();
    });
})(jQuery);