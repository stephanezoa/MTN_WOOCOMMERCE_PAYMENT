<?php
/**
 * Plugin Name: ROY MTN MoMo for WooCommerce
 * Plugin URI: https://stephanezoa.site
 * Description: Accept payments with MTN MoMo in WooCommerce
 * Version: 1.0.7
 * Author: Stephane Zoa
 * Author URI: https://stephanezoa.site
 * Licence: GPL2 
 * Text Domain: mtn-momo-pay
 * Tested up to: 6.8
 * Stable tag: 1.0.7
 * WC requires at least: 4.2.0
 * WC tested up to: 11.3
 */

defined('ABSPATH') or die("No access please!");

define('MOMOPAY_PLUGIN_VERSION', '1.0.7');

// Declare compatibility with WooCommerce HPOS (High-Performance Order Storage)
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

require_once __DIR__ . '/classes/class_momo_pay_helper.php';
// require_once ___DIR__ . 'config.php';


/**
 * MTN MoMo Payment Gateway
 *
 * @class          WC_Gateway_MomoPay
 * @extends        WC_Payment_Gateway
 * @version        1.0.0
 */


/**
 * Check if WooCommerce is activated
 */
if (!function_exists('is_woocommerce_activated')) {
	function is_woocommerce_activated()
	{
		if (class_exists('woocommerce')) {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('woo_momo_pay_admin_assets')) {
	function woo_momo_pay_admin_assets()
	{
		// load only on the plugin settings screen
		if (!isset($_GET['section']) || sanitize_key(wp_unslash($_GET['section'])) !== 'momopay') {
			return;
		}
		wp_enqueue_script('momo_pay_script', plugin_dir_url(__FILE__) . 'assets/js/wc-mtn-momo-pay.js', array('jquery'), MOMOPAY_PLUGIN_VERSION, true);
		wp_enqueue_style('momo_pay_style', plugin_dir_url(__FILE__) . 'assets/css/wc-mtn-momo-pay.css', array(), MOMOPAY_PLUGIN_VERSION);
	}
}

if (!function_exists('woo_momo_pay_public_assets')) {
	function woo_momo_pay_public_assets()
	{
		// load only on the order-pay page
		if (!function_exists('is_checkout_pay_page') || !is_checkout_pay_page()) {
			return;
		}
		wp_enqueue_script('momo_pay_script_public', plugin_dir_url(__FILE__) . 'assets/js/wc-mtn-momo-pay_public.js', array('jquery'), MOMOPAY_PLUGIN_VERSION, true);
		wp_enqueue_style('momo_pay_style', plugin_dir_url(__FILE__) . 'assets/css/wc-mtn-momo-pay.css', array(), MOMOPAY_PLUGIN_VERSION);
	}
}



function woo_momo_pay_gateway_init()
{
	if (!class_exists('WC_Payment_Gateway'))
		return;

	require_once __DIR__ . '/classes/class_momo_pay_controller.php';
	require_once __DIR__ . '/classes/class_momo_pay_gateway.php';
	require_once __DIR__ . '/classes/class_momo_pay_view.php';

	$p = new WC_MomoPay_Gateway();

	add_action('woocommerce_receipt_' . $p->id, array($p, 'receipt_page'));
	if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
		add_action('woocommerce_update_options_payment_gateways_' . $p->id, array($p, 'process_admin_options'));
	} else {
		add_action('woocommerce_update_options_payment_gateways', array($p, 'process_admin_options'));
	}
}
;

function woo_momopay_add_gateway_class($methods)
{
	$methods[] = 'WC_MomoPay_Gateway';
	return $methods;
}

function wc_momo_pay_settings_link($links)
{
	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=momopay') . '">' . __('Configure', 'mtn-momo-pay') . '</a>'
	);

	return array_merge($plugin_links, $links);
}


function woo_momo_pay_install()
{
	WC_MomoPay_Helper::pluginInit();
}


function woo_momo_pay_remove()
{
	WC_MomoPay_Helper::pluginRemove();
}

register_activation_hook(__FILE__, 'woo_momo_pay_install');
register_uninstall_hook(__FILE__, 'woo_momo_pay_remove');

add_action('admin_enqueue_scripts', 'woo_momo_pay_admin_assets');
add_action('wp_enqueue_scripts', 'woo_momo_pay_public_assets');

add_action('plugins_loaded', 'woo_momo_pay_gateway_init');

if (!add_filter('woocommerce_payment_gateways', 'woo_momopay_add_gateway_class'))
	die;

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_momo_pay_settings_link');

// On cart page only
add_action('woocommerce_check_cart_items', 'show_momo_errors_in_the_cart');
function show_momo_errors_in_the_cart()
{
	if (isset($_GET['momo_payment_id'])) {
		$momoPaymentId = preg_replace('/[^A-Za-z0-9\-]/', '', sanitize_text_field(wp_unslash($_GET['momo_payment_id'])));
		wc_print_notice(sprintf(esc_html__("MTN MoMo payment %s failed. Please try again.", "mtn-momo-pay"), esc_html($momoPaymentId)), 'error');
	}
}

//Handles Wordpress AJAX
function momopay_ajax()
{
	//check nonce, if it is invalid we will get 403 error
	check_ajax_referer("momopay");
	if (isset($_POST['data']) && is_array($_POST['data'])) {
		$data = wp_unslash($_POST['data']);
		echo WC_MomoPay_Controller::processData($data);
	}
	wp_die();
}

add_action('wp_ajax_momopay_ajax', 'momopay_ajax');
add_action('wp_ajax_nopriv_momopay_ajax', 'momopay_ajax');

?>