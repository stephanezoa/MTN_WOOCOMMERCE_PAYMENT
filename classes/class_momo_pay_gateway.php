<?php
defined('ABSPATH') || exit;

#[AllowDynamicProperties]
class WC_MomoPay_Gateway extends WC_Payment_Gateway
{
	protected static $true_currencies = array(
		'EUR',
		'UGX',
		'GHS',
		'XAF',
		'RWF',
		'XOF',
		'ZMW',
		'CFA',
		'CDF',
		'SZL',
		'GNF',
		'ZAR',
		'LRD',
		'USD'
	);
	protected static $live_currencies = array(
		'UGX',
		'GHS',
		'XAF',
		'RWF',
		'XOF',
		'ZMW',
		'CFA',
		'CDF',
		'SZL',
		'GNF',
		'ZAR',
		'LRD',
		'USD'
	);

	protected $sanbox_api_key = '';
	protected $sanbox_api_user_key = '';
	protected $wc_field_pref = 'woocommerce_momopay_';
	protected $version = 'BOxGzqSmyAu4XHD3DAgi';

	public $paid_status_name = 'paid';
	public $failed_status_name = 'failed';
	public $pending_status_name = 'pending';

	public $domain = 'mtn-momo-pay'; //plugin text domain

	/**
	 * All fields needed
	 */
	public $user_email;
	public $user_email_registered;
	public $user_name;
	public $user_phone;
	public $api_sub_key;
	public $mode;
	public $api_key;
	public $api_user;

	public $currency;
	public $location;

	public $is_user_registered = false;

	public $license_id;
	public $secret_key;
	public $site_id;
	public $user_request_counter;
	public $max_request_limit;
	public $instructions;
	protected $last_api_error = '';

	public function __construct()
	{
		// Basic settings
		$this->id = 'momopay';
		$this->icon = plugin_dir_url(__FILE__) . '../assets/img/mtn-momo-logo.png';
		$this->has_fields = false;
		$this->method_title = __('MTN MoMo for WooCommerce', $this->domain);
		$this->method_description = __('To accept payments please configure your MTN MoMo settings', $this->domain);

		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = array(
			'products'
		);

		// load the settings
		$this->init_form_fields();
		$this->init_settings();

		$this->mode = $this->get_option('mode');

		// Define variables set by the user in the admin section
		$this->enabled = $this->get_option('enabled', 'yes');
		$this->title = $this->get_option('title', __('MTN MoMo Pay', $this->domain));
		//later we will add this here this->getPaymentHintMessage
		//Please ensure to use number format as follows: 2567xxxxxxxx and to have enough funds on your MoMo wallet to make payment instantly and avoid order cancellation.
		$this->description = $this->get_option('description', __('Make sure to have enough funds in your MoMo wallet to make payment instantly and avoid order cancellation.', $this->domain));
		$this->instructions = $this->get_option(
			'instructions',
			__('Place order and pay using MTN MoMo', $this->domain)
		);

		$this->user_email = $this->get_option('user_email');
		$this->user_name = $this->get_option('user_name');
		$this->user_phone = $this->get_option('user_phone');
		$this->is_user_registered = true; // direct MTN API: no third-party registration step
		$this->user_email_registered = $this->get_option('user_email_registered');

		if ($this->is_user_registered) {
			$this->method_description = __('You are almost done! Select operation mode and set the API keys.', $this->domain);
		}

		$this->api_sub_key = $this->get_option('api_sub_key');
		$this->mode = $this->get_option('mode');
		$this->api_key = $this->get_option('api_key');
		$this->api_user = $this->get_option('api_user');
		$this->license_id = $this->get_option('license_id');
		$this->secret_key = $this->get_option('secret_key');
		$this->site_id = $this->get_option('site_id');

		$this->user_request_counter = $this->get_option('user_request_counter');
		$this->max_request_limit = $this->get_option('max_request_limit');

		$this->currency = $this->getCurrency();
		$this->location = 'CM';
	}

	/**
	 * Processes submitted admin options
	 */
	function process_admin_options()
	{
		$post_data = $this->get_post_data();

		// Check if merchant account reset was requested
		if (
			$this->getPostField($post_data, 'reset_account') === '1' ||
			$this->getPostField($post_data, 'reset_user_data') === '1'
		) {
			return $this->resetUserData();
		}

		$current_step = $this->getCurrentStep();
		$existing_data = get_option('woocommerce_momopay_settings', array());
		$data = is_array($existing_data) ? $existing_data : array();

		// Save standard WooCommerce settings (enabled, title, description) if configured or in setup
		if ($current_step >= 2) {
			// the "enabled" checkbox is only displayed once the keys are validated (step 3);
			// before that, do not turn the gateway off just because the field is absent
			if ($current_step == 3) {
				$val = isset($post_data[$this->wc_field_pref . 'enabled']) ? $post_data[$this->wc_field_pref . 'enabled'] : '';
				$data['enabled'] = ($val === '1' || $val === 'yes') ? 'yes' : 'no';
			}
			if (isset($post_data[$this->wc_field_pref . 'title'])) {
				$data['title'] = sanitize_text_field(wp_unslash($post_data[$this->wc_field_pref . 'title']));
			}
			if (isset($post_data[$this->wc_field_pref . 'description'])) {
				$data['description'] = sanitize_textarea_field(wp_unslash($post_data[$this->wc_field_pref . 'description']));
			}
		}

		if ($this->is_user_registered) {
			$mode = (int) $this->getPostField($post_data, 'mode');
			if ($mode !== 1 && $mode !== 2) {
				$mode = !empty($this->mode) ? (int) $this->mode : 1;
			}
			$api_sub_key = $this->getPostField($post_data, 'api_sub_key');
			$api_key = $this->getPostField($post_data, 'api_key');
			$api_user = $this->getPostField($post_data, 'api_user');

			// If in Live mode and fields were left empty, retain previous valid keys
			if ($mode == 2 && empty($api_key) && !empty($this->api_key)) {
				$api_key = $this->api_key;
			}
			if ($mode == 2 && empty($api_user) && !empty($this->api_user)) {
				$api_user = $this->api_user;
			}

			$settings = new WC_Admin_Settings();
			$key_not_valid = false;

			if (strlen($api_sub_key) !== 32) {
				$key_not_valid = true;
				$settings->add_error(__('La clé primaire de collection (Collection Primary key) doit comporter exactement 32 caractères.', $this->domain));
			}

			if ($mode == 2) {
				if (strlen($api_key) !== 32) {
					$key_not_valid = true;
					$settings->add_error(__('La clé API (API key) doit comporter exactement 32 caractères en mode Live.', $this->domain));
				}
				if (strlen($api_user) !== 36) {
					$key_not_valid = true;
					$settings->add_error(__('L\'utilisateur API (API user) doit comporter exactement 36 caractères (UUID) en mode Live.', $this->domain));
				}
			}

			if ($key_not_valid) {
				update_option('woocommerce_momopay_settings', $data, 'no');
				return;
			}

			// Validate currency compatibility
			if ($mode == 1 && $this->currency['code'] !== 'EUR') {
				$settings->add_error(sprintf(__('La devise %s n\'est pas supportée en Sandbox ! MTN Sandbox requiert la devise EUR (modifiable dans WooCommerce > Réglages > Général).', $this->domain), $this->currency['code']));
				update_option('woocommerce_momopay_settings', $data, 'no');
				return;
			}
			if ($mode == 2 && $this->currency['code'] !== 'XAF') {
				$settings->add_error(sprintf(__('La devise %s n\'est pas supportée en Live : MTN MoMo Cameroun encaisse uniquement en XAF (FCFA). Changez la devise dans WooCommerce > Réglages > Général.', $this->domain), $this->currency['code']));
				update_option('woocommerce_momopay_settings', $data, 'no');
				return;
			}

			// Check if keys or mode changed from active settings
			$keys_changed = (
				$mode !== (int) $this->mode ||
				$api_sub_key !== (string) $this->api_sub_key ||
				($mode == 2 && $api_key !== (string) $this->api_key) ||
				($mode == 2 && $api_user !== (string) $this->api_user) ||
				empty($this->site_id)
			);

			if ($keys_changed) {
				$siteId = $this->validation($api_sub_key, $api_user, $api_key, $mode);
				if ($siteId) {
					$data['site_id'] = $siteId;
					$settings->add_message(__('Paramètres et clés MTN MoMo mis à jour et validés avec succès auprès de MTN !', $this->domain));
				} elseif ($mode == 2) {
					// Live keys rejected by MTN: keep them for editing but do NOT activate the gateway
					$siteId = '';
					unset($data['site_id']);
					$settings->add_error(sprintf(
						__('MTN a refusé ces clés Live, la passerelle reste désactivée. Détail : %s', $this->domain),
						$this->last_api_error ? $this->last_api_error : __('erreur inconnue', $this->domain)
					));
				} else {
					// Sandbox: no API user/key provisioned here, keep the historical behaviour
					$siteId = 'MTN_CM_' . substr(md5($api_sub_key), 0, 10);
					$data['site_id'] = $siteId;
					$settings->add_message(__('Clés Sandbox enregistrées.', $this->domain));
				}

				$data['mode'] = $mode;
				$data['api_sub_key'] = $api_sub_key;
				$data['api_key'] = ($mode == 2) ? $api_key : '';
				$data['api_user'] = ($mode == 2) ? $api_user : '';
				update_option('woocommerce_momopay_settings', $data, 'no');
				$this->initUserData();
				$this->mode = $mode;
				$this->api_sub_key = $api_sub_key;
				$this->api_key = ($mode == 2) ? $api_key : '';
				$this->api_user = ($mode == 2) ? $api_user : '';
				$this->site_id = $siteId;
			} else {
				// Only title, description or enabled changed
				update_option('woocommerce_momopay_settings', $data, 'no');
				$this->initUserData();
				$settings->add_message(__('Réglages MTN MoMo enregistrés.', $this->domain));
			}
		}
	}

	/**
	 * Header with a 3-step progress indicator for the settings page
	 */
	protected function getAdminHeaderHtml($current_step)
	{
		$steps = array(
			1 => __('Register', $this->domain),
			2 => __('API keys', $this->domain),
			3 => __('Ready', $this->domain),
		);

		$html = '<div class="momopay-admin-header">';
		$html .= '<h2 class="momopay-admin-title">' . esc_html__('MTN MoMo for WooCommerce', $this->domain) . '</h2>';
		$html .= '<ol class="momopay-steps">';
		foreach ($steps as $number => $label) {
			$class = 'momopay-step';
			if ($number < $current_step || $current_step == 3) {
				$class .= ' is-done';
			} elseif ($number == $current_step) {
				$class .= ' is-current';
			}
			$html .= '<li class="' . esc_attr($class) . '"><span class="momopay-step__num">' . (int) $number . '</span>' . esc_html($label) . '</li>';
		}
		$html .= '</ol></div>';

		return $html;
	}

	/**
	 * Reads and sanitizes one posted settings field
	 */
	protected function getPostField($post_data, $name)
	{
		$key = $this->wc_field_pref . $name;
		if (!is_array($post_data) || !isset($post_data[$key]) || is_array($post_data[$key])) {
			return '';
		}
		return trim(sanitize_text_field(wp_unslash($post_data[$key])));
	}

	/**
	 * Initialize form fields that will be displayed in the admin section.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', $this->domain),
				'type' => 'checkbox',
				'label' => __('Enable MTN MoMo Payment', $this->domain),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __('Title', $this->domain),
				'type' => 'text',
				'description' => __('Payment method title that the customer sees during checkout.', $this->domain),
				'default' => __('MTN MoMo Pay', $this->domain),
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', $this->domain),
				'type' => 'textarea',
				'description' => __('Payment method description that the customer sees during checkout.', $this->domain),
				'default' => __('Make sure to have enough funds in your MoMo wallet to make payment instantly and avoid order cancellation.', $this->domain),
			),
			'mode' => array(
				'title' => __('Mode of operation', $this->domain),
				'type' => 'select',
				'options' => array(
					1 => __('Sandbox (Test)', $this->domain),
					2 => __('Live (Production)', $this->domain)
				),
				'default' => 1,
				'description' => __('Use Sandbox for testing, Live only after KYC approval by MTN!', $this->domain),
				'desc_tip' => true
			),
			'api_sub_key' => array(
				'title' => __('Collection Primary key', $this->domain),
				'type' => 'text',
				'label' => __('Collection Primary key', $this->domain),
				'default' => '',
				'description' => ' ',
				'desc_tip' => false
			),
			'api_user' => array(
				'title' => __('API user', $this->domain),
				'type' => 'text',
				'label' => __('API user', $this->domain),
				'default' => '',
				'description' => __('Generated on MTN Partner Portal, the link is given during the Live onboarding process. 36 chars long.', $this->domain),
				'desc_tip' => true
			),
			'api_key' => array(
				'title' => __('API key', $this->domain),
				'type' => 'password',
				'label' => __('API key', $this->domain),
				'default' => '',
				'description' => __('Get it on MTN Partner Portal, the link is given during the Live onboarding process. 32 chars long.', $this->domain),
				'desc_tip' => true
			),
			'user_email' => array(
				'title' => __('Merchant Email address', $this->domain),
				'type' => 'text',
				'label' => __('Merchant Email address', $this->domain),
				'default' => '',
				'description' => __('The Email address to receive payment notifications', $this->domain),
				'desc_tip' => true
			),
			'user_name' => array(
				'title' => __('MTN Merchant name', $this->domain),
				'type' => 'text',
				'label' => __('MTN Merchant name', $this->domain),
				'default' => '',
				'description' => __('Company name you want your customers to see', $this->domain),
				'desc_tip' => true
			),
			'user_phone' => array(
				'title' => __('Merchant phone number', $this->domain),
				'type' => 'text',
				'label' => __('Merchant phone number', $this->domain),
				'description' => __('MTN MoMo phone number registered with MTN. If you do not have one, use WhatsApp number.', $this->domain),
				'desc_tip' => true,
				'default' => ''
			)
		);
	}

	/**
	 * 
	 * Generates the HTML for admin settings page
	 * 
	 */
	public function admin_options()
	{
		$currentAdminOptionsStep = $this->getCurrentStep();

		echo $this->getAdminHeaderHtml($currentAdminOptionsStep);

		if ($currentAdminOptionsStep == 3) {
			echo WC_MomoPay_View::createFinishBox($this);
			unset($this->form_fields['user_email']);
			unset($this->form_fields['user_name']);
			unset($this->form_fields['user_phone']);
		} else if ($currentAdminOptionsStep == 2) {
			unset($this->form_fields['enabled']);
			unset($this->form_fields['title']);
			unset($this->form_fields['description']);
			unset($this->form_fields['user_email']);
			unset($this->form_fields['user_name']);
			unset($this->form_fields['user_phone']);
		} else if ($currentAdminOptionsStep == 1) {
			unset($this->form_fields['enabled']);
			unset($this->form_fields['title']);
			unset($this->form_fields['description']);
			unset($this->form_fields['api_sub_key']);
			unset($this->form_fields['mode']);
			unset($this->form_fields['api_key']);
			unset($this->form_fields['api_user']);
		}

		if ($currentAdminOptionsStep >= 2) {
			$sandbox_key_hint = __('Get Sandbox Collection Primary key from <a target="_blank" href="https://momodeveloper.mtn.com/developer">momodeveloper.mtn.com</a>', $this->domain);
			$live_key_hint = __('Get Live Collection Primary key here <a target="_blank" href="https://momoapi.mtn.com">momoapi.mtn.com</a>', $this->domain);
			$sandbox_mode_hint = __('No real payments, only EUR currency, no USSD message', $this->domain);

			echo '<p style="display:none" id="admin_momo_primary_key_hint_sandbox">' . $sandbox_key_hint . '</p>';
			echo '<p style="display:none" id="admin_momo_primary_key_hint_live">' . $live_key_hint . '</p>';
			echo '<p style="display:none" id="admin_momo_mode_hint_sandbox">' . $sandbox_mode_hint . '</p>';
		}

		parent::admin_options();

		if ($currentAdminOptionsStep >= 2) {
			ob_start();
?>
jQuery(document).ready(function() {
	function syncModeUI(mode) {
		if (mode == 2) {
			var live_hint = jQuery('#admin_momo_primary_key_hint_live').html();
			if (live_hint && live_hint !== 'undefined') {
				var p = jQuery('#woocommerce_momopay_api_sub_key').closest('fieldset').find('p.description');
				if (p.length) p.html(live_hint);
			}
			jQuery('#woocommerce_momopay_api_key').closest('tr').show();
			jQuery('#woocommerce_momopay_api_user').closest('tr').show();
			jQuery('#mtn_partner_portal_data').show();
			jQuery('select#woocommerce_momopay_mode').closest('fieldset').find('p.description').hide();
		} else {
			var sandbox_hint = jQuery('#admin_momo_primary_key_hint_sandbox').html();
			if (sandbox_hint && sandbox_hint !== 'undefined') {
				var p = jQuery('#woocommerce_momopay_api_sub_key').closest('fieldset').find('p.description');
				if (p.length) p.html(sandbox_hint);
			}
			var mode_hint = jQuery('#admin_momo_mode_hint_sandbox').html();
			if (mode_hint && mode_hint !== 'undefined') {
				var p = jQuery('select#woocommerce_momopay_mode').closest('fieldset').find('p.description');
				if (p.length) p.html(mode_hint).show();
			}
			jQuery('#woocommerce_momopay_api_key').closest('tr').hide();
			jQuery('#woocommerce_momopay_api_user').closest('tr').hide();
			jQuery('#mtn_partner_portal_data').hide();
		}
	}

	var currentMode = jQuery('select#woocommerce_momopay_mode').val();
	syncModeUI(currentMode);

	jQuery('select#woocommerce_momopay_mode').on('change', function() {
		syncModeUI(jQuery(this).val());
	});
});
<?php
			$javascript = ob_get_clean();
			wc_enqueue_js(apply_filters('woocommerce_momopay_admin_options_js', $javascript));

			$display_class = $this->isSandboxMode() ? 'display:none;' : '';
			echo '<div style="' . $display_class . '; margin: 15px 0 20px; padding: 14px 18px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 4px;" id="mtn_partner_portal_data">
				<p style="margin: 0; font-size: 14px; color: #065f46;"><strong>' . __('Connexion directe MTN MoMo Cameroun :', $this->domain) . '</strong> ' . __('Votre boutique communique directement avec les serveurs officiels MTN (proxy.momoapi.mtn.com) avec l\'environnement <code>mtncameroon</code>. Aucun serveur tiers ni callback n\'est requis grâce à la vérification automatique par polling instantané.', $this->domain) . '</p>
			</div>';
		}
	}

	public function is_available()
	{
		$is_available = ('yes' === $this->enabled) && $this->isActiveGateway();

		if ($is_available && !$this->isCurrencySupported()) {
			$is_available = false;
		}

		return apply_filters('woocommerce_gateway_momopay_is_available', $is_available, $this);
	}

	/**
	 * Live collects XAF only (MTN Cameroon); Sandbox only supports EUR.
	 * Never send an amount expressed in another currency.
	 */
	public function isCurrencySupported()
	{
		$code = !empty($this->currency['code']) ? $this->currency['code'] : '';
		return $this->isSandboxMode() ? ($code === 'EUR') : ($code === 'XAF');
	}

	protected function validation($api_sub_key, $api_user, $api_key, $mode)
	{
		$settings = new WC_Admin_Settings();
		if ($this->currency['code'] != 'EUR' && $mode == 1) {
			$currencyError = sprintf(__("eShop currency %s is not supported! Sandbox only supports EUR, set under WooCommerce->Currency options", $this->domain), $this->currency['code']);
			$settings->add_error($currencyError);
			return false;
		} else if (
			$this->currency['code'] !== 'XAF'
			&& $mode == 2
		) {
			// Live collects XAF only: refuse any other store currency
			$currencyError = sprintf(
				__("La devise de la boutique (%s) n'est pas supportée en Live : MTN MoMo Cameroun encaisse uniquement en XAF (FCFA).", $this->domain),
				$this->currency['code']
			);
			$settings->add_error($currencyError);
			return false;
		}

		$res = $this->validateUserMoMoKeys($api_sub_key, $api_user, $api_key, $mode);
		if ($res) {
			if (!property_exists($res, 'siteId')) {
				$error = __('Failed to validate MTN MoMo keys, make sure you supplied the right values below! Do not use Collection Widget Primary key!', $this->domain);
				if ($this->last_api_error) {
					$error .= " " . $this->last_api_error;
				}
				$settings->add_error($error);
				return false;
			}
		} else {
			$error = __('Failed to validate MTN MoMo keys, make sure you supplied the right values below! Do not use Collection Widget Primary key!', $this->domain);
			if ($this->last_api_error) {
				$error .= " " . $this->last_api_error;
			}
			$settings->add_error($error);
			return false;
		}

		return $res->siteId;
	}


	public function initUserData()
	{
		$adv_data = $this->getAdvData();

		$this->enabled = !empty($adv_data['enabled']) ? $adv_data['enabled'] : 'yes';
		$this->title = !empty($adv_data['title']) ? $adv_data['title'] : __('MTN MoMo Pay', $this->domain);
		$this->description = !empty($adv_data['description']) ? $adv_data['description'] : '';

		$this->user_email = @$adv_data['user_email'];
		$this->user_name = @$adv_data['user_name'];
		$this->user_phone = @$adv_data['user_phone'];

		$this->api_sub_key = @$adv_data['api_sub_key'];
		$this->mode = @$adv_data['mode'];
		$this->api_key = @$adv_data['api_key'];
		$this->api_user = @$adv_data['api_user'];

		$this->currency = $this->getCurrency();
		$this->location = 'CM';
		$this->license_id = @$adv_data['license_id'];
		$this->secret_key = @$adv_data['secret_key'];
		$this->site_id = @$adv_data['site_id'];
		$this->is_user_registered = true;
		$this->user_email_registered = @$adv_data['user_email_registered'];
		// $this->is_user_saved         = @$adv_data['user_saved'];
		$this->user_request_counter = @$adv_data['user_request_counter'];
		$this->max_request_limit = @$adv_data['max_request_limit'];
	}

	public function setUserData($data)
	{
		if (@$data[$this->wc_field_pref . 'user_email'])
			$this->user_email = $data[$this->wc_field_pref . 'user_email'];
		if (@$data[$this->wc_field_pref . 'user_name'])
			$this->user_name = $data[$this->wc_field_pref . 'user_name'];
		if (@$data[$this->wc_field_pref . 'user_phone'])
			$this->user_phone = $this->cleanPhone($data[$this->wc_field_pref . 'user_phone']);

		if (@$data[$this->wc_field_pref . 'api_sub_key'])
			$this->api_sub_key = $data[$this->wc_field_pref . 'api_sub_key'];
		if (@$data[$this->wc_field_pref . 'mode'])
			$this->mode = $data[$this->wc_field_pref . 'mode'];
		if (@$data[$this->wc_field_pref . 'api_key'])
			$this->api_key = $data[$this->wc_field_pref . 'api_key'];
		if (@$data[$this->wc_field_pref . 'api_user'])
			$this->api_user = $data[$this->wc_field_pref . 'api_user'];
	}

	public function initUserAction()
	{
		$settings = new WC_Admin_Settings();
		$post_data = $this->get_post_data();

		if (!$post_data)
			return false;
		$this->setUserData($post_data);

		if ($post_data[$this->wc_field_pref . 'user_phone']) {
			$this->registration();
			return;
		}

		if ($post_data[$this->wc_field_pref . 'api_sub_key']) {
			$this->validation();
			return;
		} else if ($post_data[$this->wc_field_pref . 'reset_user_data']) {
			$this->resetUserData();
			return;
		} else {
			$settings->add_error('Empty MoMo Collection Primary key!', $this->domain);
		}
	}

	public function getLastError()
	{
		return ($this->last_api_error) ? $this->last_api_error : '';
	}


	public function getMtnBaseUrl()
	{
		return $this->isSandboxMode() ? 'https://sandbox.momodeveloper.mtn.com' : 'https://proxy.momoapi.mtn.com';
	}

	public function getMtnTargetEnvironment()
	{
		return $this->isSandboxMode() ? 'sandbox' : 'mtncameroon';
	}

	public function getMtnAccessToken($api_sub_key = null, $api_user = null, $api_key = null, $mode = null)
	{
		$sub_key = ($api_sub_key !== null) ? $api_sub_key : $this->api_sub_key;
		$user = ($api_user !== null) ? $api_user : $this->api_user;
		$key = ($api_key !== null) ? $api_key : $this->api_key;
		$is_sandbox = ($mode !== null) ? ($mode == 1) : $this->isSandboxMode();

		$base_url = $is_sandbox ? 'https://sandbox.momodeveloper.mtn.com' : 'https://proxy.momoapi.mtn.com';
		$target_env = $is_sandbox ? 'sandbox' : 'mtncameroon';
		$url = $base_url . '/collection/token/';

		// Cache the token (valid 1h) so status polling does not request a new one every few seconds.
		// Explicit credentials (key validation in admin) always bypass the cache.
		$use_cache = ($api_sub_key === null && $api_user === null && $api_key === null);
		$cache_key = 'momopay_tok_' . md5($target_env . '|' . $user . '|' . $key . '|' . $sub_key);
		if ($use_cache) {
			$cached = get_transient($cache_key);
			if (!empty($cached) && is_string($cached)) {
				return $cached;
			}
		}

		$auth_str = base64_encode(trim((string) $user) . ':' . trim((string) $key));
		$headers = array(
			'Ocp-Apim-Subscription-Key' => trim((string) $sub_key),
			'X-Target-Environment' => $target_env,
			'Authorization' => 'Basic ' . $auth_str,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		);

		$args = array(
			'timeout' => 45,
			'headers' => $headers,
			'body' => ''
		);

		$res = wp_remote_post($url, $args);

		if (is_wp_error($res)) {
			$this->last_api_error = $res->get_error_message();
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code($res);
		$body = wp_remote_retrieve_body($res);
		$data = json_decode($body);

		if ($code === 200 && is_object($data) && !empty($data->access_token)) {
			if ($use_cache) {
				$ttl = (!empty($data->expires_in) ? (int) $data->expires_in : 3600) - 300;
				set_transient($cache_key, (string) $data->access_token, max(60, $ttl));
			}
			return (string) $data->access_token;
		}

		$msg = (is_object($data) && !empty($data->message)) ? $data->message : ('Erreur jeton MTN HTTP ' . $code);
		$this->last_api_error = $msg;
		return false;
	}

	protected function validateUserMoMoKeys($api_sub_key, $api_user, $api_key, $mode)
	{
		$token = $this->getMtnAccessToken($api_sub_key, $api_user, $api_key, $mode);
		if ($token) {
			$result = new stdClass();
			$result->siteId = 'MTN_CM_' . substr(md5($api_sub_key), 0, 10);
			return $result;
		}
		return false;
	}


	protected function resetUserData($partial = false)
	{
		if ($partial) {
			$data = $this->getAdvData();

			$this->site_id = '';
			$this->api_sub_key = '';
			$this->mode = 1;
			$this->site_id = '';

			unset($data['site_id']);
			unset($data['mode']);
			unset($data['api_sub_key']);

			update_option('woocommerce_momopay_settings', $data, 'no');
		} else {
			$this->user_email = '';
			$this->user_name = '';
			$this->user_phone = '';
			$this->api_sub_key = '';
			$this->mode = 1;
			$this->api_key = '';
			$this->api_user = '';
			$this->license_id = '';
			$this->site_id = '';

			update_option('woocommerce_momopay_settings', array(), 'no'); #"enabled"=>"yes"
		}

	}

	protected function setAdvData($var_name, $new_value, $autoload = 'no')
	{
		$data = $this->getAdvData();

		$data[$var_name] = $new_value;

		// update_option( 'wc_momopay_adv_data', $data, $autoload);
		update_option('woocommerce_momopay_settings', $data, $autoload);
	}


	protected function getAdvData($var_name = false)
	{
		$data = get_option('woocommerce_momopay_settings');
		if ($data === false)
			return ($var_name) ? '' : array();
		if (!$var_name)
			return $data;
		return (@$data[$var_name]) ? $data[$var_name] : '';
	}

	/**
	 * 1 - Initial step, nothing is set, new plugin
	 * 2 - MoMo Merchant registration done
	 * 3 - MoMo Keys are set, all is ready to use the plugin
	 */
	public function getCurrentStep()
	{
		if ($this->site_id)
			return 3;
		if ($this->isRegisteredUser())
			return 2;
		return 1;
	}

	public function isRegisteredUser()
	{
		return true;
	}

	public static function getTrueCurrencies()
	{
		return self::$true_currencies;
	}

	public static function isTrueCurrency($currency_code)
	{
		return (in_array($currency_code, self::$true_currencies)) ? true : false;
	}

	public static function isLiveCurrency($currency_code)
	{
		return (in_array($currency_code, self::$live_currencies)) ? true : false;
	}

	public function isSandboxMode()
	{
		return ($this->mode == 2) ? false : true;
	}


	protected function getCurrency()
	{
		$currencies = get_woocommerce_currencies();
		$currency_code = get_woocommerce_currency();

		$currency_name = (!empty($currency_code) && !empty($currencies[$currency_code])) ? $currencies[$currency_code] : $currency_code;

		if (empty($currency_code)) {
			$currency_code = 'EUR';
			$currency_name = 'Euro';
		}

		return array(
			'code' => strtoupper($currency_code),
			'name' => $currency_name ? $currency_name : strtoupper($currency_code)
		);
	}


	protected function getLocation()
	{
		// Hardcoded strictly for Cameroon MTN
		return 'CM';
	}

	public function formatPayerPhone($phone_num, $order = null)
	{
		$clean = $this->cleanPhone($phone_num);
		$clean = preg_replace('/^00/', '', $clean);

		// Format strictly for Cameroon (prefix 237, 12 digits total, e.g. 2376XXXXXXXX)
		$prefix = '237';
		if (strpos($clean, $prefix) !== 0) {
			$clean = ltrim($clean, '0');
			$clean = $prefix . $clean;
		}

		return $clean;
	}

	protected function preparePaymentData($order)
	{
		return array(
			'amount' => (float) $order->get_total(),
			'note' => 'Order ' . $order->get_order_number(),
			'payer' => $this->formatPayerPhone($order->get_billing_phone(), $order)
		);
	}


	protected function cleanPhone($phone_num)
	{
		$s = preg_replace('/\D*/', '', $phone_num);
		$s = preg_replace('/\s+/', '', $s);
		return $s;
	}


	protected function addCommentToOrder($order)
	{
		$order->add_order_note(__('MoMo payment request sent, accept with PIN on ', $this->domain) . $order->get_billing_phone());
	}


	protected function getMsgFromApiAnswer($answer)
	{
		if (@$answer->reason)
			return $answer->reason;
		if (@$answer->message)
			return $answer->message;
		return null;
	}


	protected function isActiveGateway()
	{
		if (empty($this->api_sub_key) || empty($this->site_id)) {
			return false;
		}
		return $this->isSandboxMode() || (!empty($this->api_user) && !empty($this->api_key));
	}

	/**
	 * Receipt Page
	 **/
	public function receipt_page($order_id)
	{
		global $woocommerce;
		$order = new WC_Order($order_id);
		$payment_id = null;
		$error_message = '';

		if (
			!$order->has_status('completed') &&
			!$order->has_status('processing')
		) {
			if (!$this->isActiveGateway()) {
				$error_message = __('Le plugin MTN MoMo n\'est pas encore configuré ou les clés n\'ont pas été validées.', $this->domain);
			} else {
				$existing = $this->getPendingTransactionByOrder($order_id);
				if ($existing) {
					// Page reloaded: do not send a second payment request to the customer
					$payment_id = $existing->payment_id;
				} else {
					$prepare_order = $this->preparePaymentData($order);
					if (!preg_match('/^237\d{9}$/', (string) $prepare_order['payer'])) {
						$error_message = __('Un numéro de téléphone de facturation valide est requis pour payer avec MTN MoMo.', $this->domain);
					} else {
						$this->addCommentToOrder($order);
						$payment_result_raw = $this->sendPayment($prepare_order);
						if (!$payment_result_raw) {
							$error_message = !empty($this->last_api_error)
								? sprintf(__('Échec du paiement MTN : %s', $this->domain), $this->last_api_error)
								: __('Impossible de contacter le serveur MTN MoMo pour initier le paiement.', $this->domain);
						} else {
							$payment_result = json_decode($payment_result_raw);
							$payment_id = (is_object($payment_result) && !empty($payment_result->paymentId)) ? sanitize_text_field((string) $payment_result->paymentId) : null;
							if ($payment_id) {
								$total_amount_to_save = ((float) $order->get_total()) * 100;
								$trn = array(
									'order_id' => $order_id,
									'amount' => (int) round($total_amount_to_save),
									'phone_number' => $prepare_order['payer'],
									'payment_id' => $payment_result->paymentId,
									'failed_reason' => $this->getMsgFromApiAnswer($payment_result),
									'status' => $this->pending_status_name
								);
								$this->saveTransaction($trn);
								self::scheduleReconcile($payment_id, 1);
							} else {
								$api_msg = is_object($payment_result) ? $this->getMsgFromApiAnswer($payment_result) : null;
								$error_message = $api_msg ? sprintf(__('Paiement MTN refusé : %s', $this->domain), $api_msg) : __('Échec de l\'initialisation du paiement MTN MoMo.', $this->domain);
							}
						}
					}
				}
			}

			if ($order->get_payment_method() == 'momopay') {
				echo WC_MomoPay_View::createReceiptBox($order, $payment_id, $this, $error_message);
			}
		}
	}

	/**
	 * Send payment request directly to MTN Mobile Money API
	 */
	public function sendPayment($order)
	{
		$access_token = $this->getMtnAccessToken();
		if (!$access_token) {
			return false;
		}

		$is_sandbox = $this->isSandboxMode();

		// never request a collection in a currency the account cannot collect
		if (!$this->isCurrencySupported()) {
			$this->last_api_error = __('Devise de la boutique non supportée par MTN MoMo (Live : XAF uniquement).', $this->domain);
			return false;
		}
		if ((int) round((float) $order['amount']) < 1) {
			$this->last_api_error = __('Montant invalide.', $this->domain);
			return false;
		}

		$base_url = $this->getMtnBaseUrl();
		$target_env = $this->getMtnTargetEnvironment();
		$url = $base_url . '/collection/v1_0/requesttopay';

		// Generate unique UUID v4 for transaction reference
		$reference_id = wp_generate_uuid4();
		$amount_int = (int) round((float) $order['amount']);
		$payer_phone = $this->formatPayerPhone($order['payer']);
		$external_id = 'MTN_' . time() . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
		$currency = ($is_sandbox && $this->currency['code'] === 'EUR') ? 'EUR' : 'XAF';

		$payload = array(
			'amount' => (string) $amount_int,
			'currency' => $currency,
			'externalId' => $external_id,
			'payer' => array(
				'partyIdType' => 'MSISDN',
				'partyId' => (string) $payer_phone
			),
			'payerMessage' => mb_substr('Commande ' . (!empty($order['note']) ? $order['note'] : ''), 0, 30),
			'payeeNote' => 'Merci'
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Ocp-Apim-Subscription-Key' => trim((string) $this->api_sub_key),
			'X-Target-Environment' => $target_env,
			'X-Reference-Id' => $reference_id,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		);

		$args = array(
			'timeout' => 45,
			'headers' => $headers,
			'body' => json_encode($payload)
		);

		$res = wp_remote_post($url, $args);

		if (is_wp_error($res)) {
			$this->last_api_error = $res->get_error_message();
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code($res);
		$body = wp_remote_retrieve_body($res);

		// MTN returns 202 Accepted (or 200) when request to pay is successfully received
		if ($code === 202 || $code === 200) {
			$out = new stdClass();
			$out->paymentId = $reference_id;
			$out->externalId = $external_id;
			return json_encode($out);
		}

		$data = json_decode($body);
		$msg = (is_object($data) && !empty($data->message)) ? $data->message : ('Erreur MTN HTTP ' . $code . ($body ? ': ' . $body : ''));
		$this->last_api_error = $msg;
		return false;
	}

	public function getPaymentStatus($payment_id)
	{
		try {
			$access_token = $this->getMtnAccessToken();
			if (!$access_token) {
				return false;
			}

			$base_url = $this->getMtnBaseUrl();
			$target_env = $this->getMtnTargetEnvironment();
			$url = $base_url . '/collection/v1_0/requesttopay/' . rawurlencode($payment_id);

			$headers = array(
				'Authorization' => 'Bearer ' . $access_token,
				'Ocp-Apim-Subscription-Key' => trim((string) $this->api_sub_key),
				'X-Target-Environment' => $target_env,
				'Accept' => 'application/json'
			);

			$args = array(
				'timeout' => 45,
				'headers' => $headers
			);

			$res = wp_remote_get($url, $args);

			if (is_wp_error($res)) {
				$this->last_api_error = $res->get_error_message();
				return false;
			}

			$code = (int) wp_remote_retrieve_response_code($res);
			$body = wp_remote_retrieve_body($res);
			$data = json_decode($body);

			$out = new stdClass();
			$out->code = $code;
			$out->status = '';
			$out->message = '';

			if ($code === 200 && is_object($data) && !empty($data->status)) {
				$mtn_status = strtoupper(trim((string) $data->status));
				if ($mtn_status === 'SUCCESSFUL') {
					$out->status = $this->paid_status_name; // 'paid'
					$out->message = __('Merci, le paiement MTN MoMo a été validé !', $this->domain);
				} elseif ($mtn_status === 'FAILED') {
					$out->status = $this->failed_status_name; // 'failed'
					$out->message = !empty($data->reason) ? (string) $data->reason : __('Le paiement a été rejeté ou annulé.', $this->domain);
				} else {
					$out->status = $this->pending_status_name; // 'pending'
					$out->message = __('En attente de confirmation sur votre téléphone...', $this->domain);
				}

				if (!empty($data->reason)) {
					$out->reason = $data->reason;
				}
			} else {
				$out->status = false;
				$out->message = 'Failed to get payment status';
			}

			return $out;
		} catch (Exception $e) {
			return false;
		}
	}


	public function cancelOrder($order_id)
	{
		$order = new WC_Order($order_id);
		$res = $order->update_status('cancelled', __('Canceled by the user', $this->domain) . "<br/>");

		$out = new stdClass();
		$out->status = $res;
		$out->message = ($res) ? 'Order cancelled.' : __('Cancellation error! Please try again later.', $this->domain);

		return $out;
	}

	public function process_payment($order_id)
	{
		$order = new WC_Order($order_id);

		$checkout_url = $order->get_checkout_payment_url(true);
		$checkout_edited_url = $checkout_url . "&transactionType=momocheckout";

		return array(
			'result' => 'success',
			'redirect' => add_query_arg(
				'order',
				$order_id,
				add_query_arg(
					'key',
					$order->get_order_key(),
					$checkout_edited_url
				)
			)
		);
	}


	protected function saveTransaction($data)
	{
		global $wpdb;

		$wpdb->insert(
			WC_MomoPay_Helper::getPaymentsTableName(),
			array(
				'order_id' => $data['order_id'],
				'amount' => $data['amount'],
				'phone_number' => $data['phone_number'],
				'payment_id' => $data['payment_id'],
				'failed_reason' => $data['failed_reason'],
				'status' => $data['status']
			)
		);
	}


	public function updateTransaction($payment_id, $api_answer)
	{
		global $wpdb;

		$allowed = array($this->paid_status_name, $this->failed_status_name, $this->pending_status_name);
		$status = (is_object($api_answer) && isset($api_answer->status)) ? $api_answer->status : '';
		if (!in_array($status, $allowed, true)) {
			return false;
		}

		$transaction = $this->getTransactionByPaymentId($payment_id);
		if (!$transaction) {
			return false;
		}

		$new_data = array('status' => $status);

		if (!empty($api_answer->message) && $status != $this->paid_status_name) {
			$new_data['failed_reason'] = mb_substr(sanitize_text_field((string) $api_answer->message), 0, 120);
		}

		$res = $wpdb->update(
			WC_MomoPay_Helper::getPaymentsTableName(),
			$new_data,
			array('payment_id' => $payment_id)
		);

		if ($status == $this->paid_status_name) {
			$order = wc_get_order((int) $transaction->order_id);

			// complete only an unpaid MoMo order, and only if the paid amount matches the order total
			if (
				$order
				&& $order->get_payment_method() === $this->id
				&& $order->needs_payment()
				&& (int) round(((float) $order->get_total()) * 100) === (int) $transaction->amount
			) {
				$order->add_order_note(__('MoMo payment received', $this->domain));
				$order->payment_complete($payment_id);
			}
		}

		return (bool) $res;
	}

	/**
	 * Polling only runs while the customer keeps the page open. If they close it after
	 * approving the PIN, re-check the payment in the background so the order still completes.
	 */
	public static function scheduleReconcile($payment_id, $attempt)
	{
		$delays = array(1 => 120, 2 => 300, 3 => 900, 4 => 3600, 5 => 10800);
		if (!isset($delays[$attempt])) {
			return;
		}
		wp_schedule_single_event(time() + $delays[$attempt], 'momopay_reconcile_payment', array((string) $payment_id, (int) $attempt));
	}

	public static function reconcilePayment($payment_id, $attempt = 1)
	{
		$gateway = new self();
		$transaction = $gateway->getTransactionByPaymentId($payment_id);
		if (!$transaction || $transaction->status === $gateway->paid_status_name) {
			return;
		}

		$res = $gateway->getPaymentStatus($payment_id);
		if (is_object($res) && !empty($res->status)) {
			$gateway->updateTransaction($payment_id, $res);
			if ($res->status === $gateway->pending_status_name) {
				self::scheduleReconcile($payment_id, $attempt + 1);
			}
		} else {
			self::scheduleReconcile($payment_id, $attempt + 1);
		}
	}

	public function getTransactionByPaymentId($payment_id)
	{
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . WC_MomoPay_Helper::getPaymentsTableName() . " WHERE payment_id = %s LIMIT 1",
				$payment_id
			)
		);
	}

	public function getPendingTransactionByOrder($order_id)
	{
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . WC_MomoPay_Helper::getPaymentsTableName() . " WHERE order_id = %s AND status = %s AND payment_id <> '' ORDER BY id DESC LIMIT 1",
				(string) $order_id,
				$this->pending_status_name
			)
		);
	}
}
;
;