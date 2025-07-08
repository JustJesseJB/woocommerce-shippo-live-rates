# WooCommerce Shippo Live Rates

A WooCommerce plugin for displaying live shipping rates from USPS, UPS, and FedEx at checkout using the Shippo API.

## Features
- Live shipping rates at checkout
- Support for USPS, UPS, and FedEx  
- Easy configuration
- Intelligent carrier grouping and sorting
- Rate caching for improved performance
- Fallback shipping rate option
- Debug mode for troubleshooting

## Requirements
- WordPress 5.6 or higher
- WooCommerce 3.0 or higher
- PHP 5.6 or higher
- Shippo API key (free or paid account)

## Installation
1. Upload the plugin files to `/wp-content/plugins/woocommerce-shippo-live-rates`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce → Settings → Shipping and add "Shippo Live Rates" to your shipping zones
4. Enter your Shippo API key and configure the settings

## Configuration
1. **API Key**: Enter your Shippo API key from your Shippo dashboard
2. **Carriers**: Select which carriers to offer (USPS, UPS, FedEx)
3. **Services**: Choose specific services or leave empty for all available
4. **Rate Adjustment**: Add flat fee or percentage markup if desired
5. **Caching**: Enable to improve checkout performance
6. **Fallback Rate**: Configure a backup rate when Shippo is unavailable

## Changelog

### 1.0.3 - 2024-12-20
- **Fix**: Fatal error when products have empty dimensions during checkout
- **Fix**: Cache key generation now handles missing product dimensions gracefully
- **Improve**: Added proper null/empty checks before calling wc_get_dimension()
- **Improve**: Prevents checkout crashes for products without dimensions

### 1.0.2 - 2024-12-20
- **Fix**: Shipping carrier grouping display issues
- **Fix**: Incorrect carrier categorization on some themes
- **Fix**: Local pickup being selected by default
- **Improve**: Carrier detection logic for better compatibility
- **Improve**: Enforced carrier display order (FedEx → UPS → USPS)

### 1.0.1 - 2024-12-20
- **Fix**: WooCommerce compatibility issue causing fatal errors on some installations
- **Fix**: Improved plugin initialization to prevent conflicts with WooCommerce shipping classes
- **Fix**: Handle products without dimensions or weight gracefully
- **Fix**: Carrier grouping and sorting display issues on checkout
- **Add**: WooCommerce version compatibility check during activation
- **Add**: Better error handling for empty product dimensions
- **Improve**: More robust carrier detection in JavaScript

### 1.0.0 - 2024-12-01
- Initial release
- Live shipping rates from USPS, UPS, and FedEx
- Shippo API integration
- Rate caching system
- Admin settings interface
- Shipping zone support

## Support
For issues or questions, please use the GitHub issues page or contact support.

## License
This plugin is licensed under the GPL v2 or later.

## Credits
Developed by Ambition Amplified, LLC