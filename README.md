# ROY MTN MoMo for WooCommerce

Accept payments with MTN MoMo in WooCommerce.

## Features

- MTN MoMo payment gateway integration for WooCommerce
- Sandbox and Live modes
- Admin configuration UI
- Payment status tracking
- Custom error handling

## Installation

1. Copy the plugin folder to your WordPress `wp-content/plugins` directory.
2. Activate the plugin from the WordPress admin panel.
3. Go to WooCommerce > Settings > Payments > MTN MoMo Pay to configure.

## Configuration

- Enter your merchant details and API keys in the plugin settings.
- Choose Sandbox for testing (only EUR currency supported).
- Switch to Live mode after KYC approval by MTN.

## Usage

- Customers can select MTN MoMo Pay at checkout.
- Payment status is tracked and updated automatically.
- Failed payments show an error on the cart page.

## Development

- Main PHP entry: [`mtn-momo-pay.php`](mtn-momo-pay.php)
- JS/CSS assets: [`wc-mtn-momo-pay.js`](wc-mtn-momo-pay.js), [`wc-mtn-momo-pay_public.js`](wc-mtn-momo-pay_public.js), [`wc-mtn-momo-pay.css`](wc-mtn-momo-pay.css)
- Core classes: [`classes/class_momo_pay_gateway.php`](classes/class_momo_pay_gateway.php), [`classes/class_momo_pay_controller.php`](classes/class_momo_pay_controller.php), [`classes/class_momo_pay_helper.php`](classes/class_momo_pay_helper.php), [`classes/class_momo_pay_view.php`](classes/class_momo_pay_view.php)

## Support

For issues, please contact [Stephane Zoa](https://STephanezoa.com) {[stephanezoa2310@gmail.com]} or visit [https://www.hr-skills.com](https://www.hr-skills.com).

## License

GPL2
