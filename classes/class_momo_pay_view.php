<?php
defined('ABSPATH') || exit;

class WC_MomoPay_View
{

	protected static $pro_plugin_link = 'https://www.clickon.ch/mtn-momo-pay';

	protected static function createRegistrationBox($cfg)
	{
		return '<table class="form-table" data-momopay-step="1">
				<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_user_email">' . __('Merchant Email address', $cfg->domain) . '</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Merchant Email address</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_user_email" id="woocommerce_momopay_user_email" value="' . esc_attr($cfg->user_email) . '" placeholder="">
							<p class="description">The Email address to receive payment notifications</p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_user_name">MTN Merchant name</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>MTN Merchant name</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_user_name" id="woocommerce_momopay_user_name" value="' . esc_attr($cfg->user_name) . '" placeholder="">
							<p class="description">Company name you want your customers to see</p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_user_phone">Merchant phone number</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Merchant phone number Legend</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_user_phone" id="woocommerce_momopay_user_phone" value="' . esc_attr($cfg->user_phone) . '" placeholder="">
							<p class="description">MTN MoMo phone number registered with MTN</p>
						</fieldset>
					</td>
				</tr>
				</tbody>
			</table>
			<input type="hidden" name="woocommerce_momopay_register_user_data" value="1" >
			<input type="hidden" name="woocommerce_momopay_save_user_data" value="0" >
			<input type="hidden" name="woocommerce_momopay_reset_user_data" value="0" >';
	}


	protected static function getCurrencyInfo($cfg)
	{
		return array(
			'code' => $cfg->currency['code'],
			'desc' => (($cfg->isSandboxMode() && $cfg->currency['code'] != 'EUR')) ? 'Change to EUR if you want to test in Sandbox' : $cfg->currency['name']
		);
	}

	protected static function createSelectBox($active_item)
	{
		$items = array('Sandbox', 'Live');

		$html = '<select class="select" name="woocommerce_momopay_mode" id="woocommerce_momopay_mode">';

		foreach ($items as $k => $item_name) {
			$i = $k + 1;
			$selected = ($active_item == $i) ? 'selected="selected"' : '';
			$html .= '<option value="' . $i . '" ' . $selected . '>' . $item_name . '</option>';
		}
		;

		$html .= '</select>';

		$hide_class = ($active_item == 2) ? 'hide' : '';

		$html .= '<p class="description ' . $hide_class . '" data-momoadv-box="sandbox">No real payments, only EUR currency, no USSD message</p>';

		return $html;
	}


	protected static function createLiveModeBox($cfg)
	{
		$html = '<table class="form-table ' . self::hideIfUsedSandboxMode($cfg->isSandboxMode()) . '" data-momoadv-box="live">
				<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">Provider Callback Host</th>
					<td class="forminp">
						<p class="description">mtn.momopay.ch</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">Payment Server URL</th>
					<td class="forminp">
						<p class="description">https://mtn.momopay.ch/doPaymentCallback/' . esc_html($cfg->license_id) . '</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_api_key">API Key</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>API Key</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_api_key" id="woocommerce_momopay_api_key" value="' . esc_attr($cfg->api_key) . '" placeholder="">
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_api_user_key">API user </label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>API user</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_api_user_key" id="woocommerce_momopay_api_user_key" value="' . esc_attr($cfg->api_user_key) . '" placeholder="">
						</fieldset>
						<p class="description">You can get the Live keys on MTN Partner Portal</p>
					</td>
				</tr>
				</tbody>
			</table>
			<input type="hidden" name="woocommerce_momopay_register_user_data" value="0" >
			<input type="hidden" name="woocommerce_momopay_save_user_data" value="1" >
			<input type="hidden" name="woocommerce_momopay_reset_user_data" value="0" >';

		return $html;
	}


	protected static function hideIfUsedSandboxMode($used_sandbox_mode)
	{
		return ($used_sandbox_mode) ? 'hide' : '';
	}


	protected static function createValidateBox($cfg)
	{
		$currency = self::getCurrencyInfo($cfg);

		$html = '<table class="form-table" data-momopay-step="2">
				<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">MoMo Merchant Email</th>
					<td class="forminp">
						<p class="description">' . esc_html($cfg->user_email) . '</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">MoMo Merchant ID</th>
					<td class="forminp">
						<p class="description">' . esc_html($cfg->license_id) . '</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">Currency</th>
					<td class="forminp">
						<p class="description"><strong>' . $currency['code'] . '</strong> ' . $currency['desc'] . '</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_api_sub_key">Collection Primary key </label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Collection Primary key</span></legend>
							<input class="input-text regular-input " type="text" name="woocommerce_momopay_api_sub_key" id="woocommerce_momopay_api_sub_key" style="" value="' . esc_attr($cfg->api_sub_key) . '" placeholder="">
							<p class="description ' . self::hideIfUsedSandboxMode($cfg->isSandboxMode()) . '" data-momoadv-box="live">
								Get Live Collection Primary key here <a target="_blank" href="https://momoapi.mtn.com">https://momoapi.mtn.com</a>
							</p>
							<p class="description ' . self::hideIfUsedSandboxMode(!$cfg->isSandboxMode()) . '" data-momoadv-box="sandbox">
								Get Sandbox Collection Primary key from <a target="_blank" href="https://momodeveloper.mtn.com/developer">https://momodeveloper.mtn.com</a>
							</p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="woocommerce_momopay_mode">Mode of operation</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Mode of operation</span></legend>'
			. self::createSelectBox($cfg->mode)
			. '</fieldset>
					</td>
				</tr>
				</tbody>
			</table>';

		$html .= self::createLiveModeBox($cfg);

		return $html;
	}


	public static function createFinishBox($cfg)
	{
		$currency = self::getCurrencyInfo($cfg);

		$mode_badge = $cfg->isSandboxMode()
			? '<span class="momopay-mode-badge momopay-mode-sandbox">' . esc_html__('Sandbox (Test)', $cfg->domain) . '</span>'
			: '<span class="momopay-mode-badge momopay-mode-live">' . esc_html__('Live (Production)', $cfg->domain) . '</span>';

		$status_badge = '<span class="momopay-status-badge momopay-status-active">&#10003; ' . esc_html__('Actif & Configuré', $cfg->domain) . '</span>';

		$merchant_email = !empty($cfg->user_email_registered) ? $cfg->user_email_registered : $cfg->user_email;

		$html = '<div class="momopay-status-card" data-momopay-step="3">';
		$html .= '<div class="momopay-status-card-header">';
		$html .= '<div class="momopay-status-card-title">';
		$html .= '<h3>' . esc_html__('Passerelle de paiement MTN MoMo', $cfg->domain) . '</h3>';
		$html .= '<div class="momopay-badges">' . $status_badge . ' ' . $mode_badge . '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<table class="momopay-account-table"><tbody>';
		$html .= '<tr><th>' . esc_html__('Réseau cible :', $cfg->domain) . '</th><td><strong>' . esc_html__('MTN MoMo Cameroun (XAF - 237)', $cfg->domain) . '</strong></td></tr>';
		$html .= '<tr><th>' . esc_html__('Connexion API :', $cfg->domain) . '</th><td><span style="color:#059669; font-weight:600;">' . esc_html__('Directe (proxy.momoapi.mtn.com - mtncameroon)', $cfg->domain) . '</span></td></tr>';
		$html .= '<tr><th>' . esc_html__('Vérification paiement :', $cfg->domain) . '</th><td>' . esc_html__('Polling instantané (aucun callback requis)', $cfg->domain) . '</td></tr>';
		$html .= '<tr><th>' . esc_html__('Devise de la boutique :', $cfg->domain) . '</th><td><strong>' . esc_html($currency['code']) . '</strong></td></tr>';
		$html .= '</tbody></table>';

		$html .= '<div class="momopay-status-actions">';
		$html .= '<div class="momopay-edit-keys-notice"><p><strong>' . esc_html__('Modification des clés à volonté :', $cfg->domain) . '</strong> ' . esc_html__('Vous pouvez modifier votre mode (Sandbox / Live), votre clé primaire de collection, votre clé API et votre utilisateur API dans le formulaire ci-dessous à tout moment, puis cliquer sur Enregistrer les modifications.', $cfg->domain) . '</p></div>';
		$html .= '<div class="momopay-reset-box">';
		$html .= '<button type="submit" name="woocommerce_momopay_reset_account" value="1" class="button button-secondary momopay-btn-reset-account" id="momopay_reset_account_btn" onclick="return confirm(\'' . esc_js(__('Attention : Voulez-vous vraiment réinitialiser ce compte marchand ? Toutes les clés devront être ressaisies.', $cfg->domain)) . '\');">' . esc_html__('Réinitialiser / Changer de compte marchand', $cfg->domain) . '</button>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}


	public static function createMainBox($cfg)
	{
		$step = $cfg->getCurrentStep();

		$html = '<h3>MTN MoMo Configuration</h3>
				<p>To accept payments please configure your MoMo settings, if you do not have MTN number, enter your WhatsApp phone number.</p>
				<div class="mtn-momo-pay_main">';

		if ($step == 1) {
			$html .= self::createRegistrationBox($cfg);
		} else {
			$html .= '<div id="momopay_leftSide" class="mtn-momo-pay_left">';
			$html .= (($step < 3) ? self::createValidateBox($cfg) : self::createFinishBox($cfg));
			$html .= '</div>';
		}

		$html .= '</div><div class="mtn-momo-pay_error-box" id="momopay_errorBox">' . $cfg->getLastError() . '</div>';
		return $html;
	}


	public static function createReceiptBox($order, $payment_id, $cfg, $error_message = '')
	{
		$is_error = empty($payment_id);
		if ($is_error) {
			$process_desc = !empty($error_message)
				? esc_html($error_message)
				: esc_html__('Échec du paiement : Le plugin MTN MoMo n\'est pas configuré ou la connexion au serveur MTN a échoué.', $cfg->domain);
		} else {
			$process_desc = esc_html__('Accept payment on ', $cfg->domain) . esc_html($order->get_billing_phone()) . esc_html__(' with a PIN', $cfg->domain);
		}

		$orderCancelUrl = $order->get_cancel_order_url('');
		$endpointUrl = admin_url('admin-ajax.php');

		$cart_page_id = wc_get_page_id('cart');
		$cartUrl = $cart_page_id ? get_permalink($cart_page_id) : '';

		$orderReceivedUrl = $order->get_checkout_order_received_url();
		$cartPaymentWithIdUrl = add_query_arg('momo_payment_id', rawurlencode((string) $payment_id), $cartUrl);

		$sandboxLine = '';
		if ($cfg->isSandboxMode() && !$is_error) {
			$sandboxLine = '<h3 class="momopay-sandbox-badge-checkout">' . esc_html__('Mode Sandbox - le paiement sera accepté automatiquement après quelques secondes', $cfg->domain) . '</h3>';
		}

		$boxClass = 'momopay-checkout-box' . ($is_error ? ' momopay-has-error' : ' momopay-waiting-pin');
		$descClass = $is_error ? 'momopay-error-desc' : 'momopay-waiting-desc';

		$html = '<div class="' . esc_attr($boxClass) . '">' . $sandboxLine . '
			<h3 id="momopay_processDescBox" class="' . esc_attr($descClass) . '">' . $process_desc . '</h3>
			<img class="momopay-checkout-logo" src="' . esc_url(plugin_dir_url(__FILE__) . '../assets/img/mtn-momo-logo.png') . '" alt="MTN MoMoPay payments">
			<div class="mtn-momo-pay_error-box" id="momopay_errorBox">' . ($is_error ? esc_html($process_desc) : '') . '</div>
			<input type="hidden" id="currentOrderId" value="' . esc_attr($order->get_id()) . '" />
			<input type="hidden" id="orderKey" value="' . esc_attr($order->get_order_key()) . '" />
			<input type="hidden" id="paymentId" value="' . esc_attr($payment_id) . '" />
			<input type="hidden" id="orderCancelUrl" value="' . esc_url($orderCancelUrl) . '" />
			<input type="hidden" id="endpointUrl" value="' . esc_url($endpointUrl) . '" />
			<input type="hidden" id="momononce" value="' . esc_attr(wp_create_nonce('momopay')) . '" />
			<input type="hidden" id="isSandbox" value="' . ($cfg->isSandboxMode() ? '1' : '0') . '" />
			<input type="hidden" id="cartUrl" value="' . esc_url($cartUrl) . '" />
			<input type="hidden" id="orderReceivedUrl" value="' . esc_url($orderReceivedUrl) . '" />
			<input type="hidden" id="cartPaymentWithIdUrl" value="' . esc_url($cartPaymentWithIdUrl) . '" />
			<div class="momopay-checkout-actions">
				<a href="' . esc_url($orderCancelUrl) . '" class="button button-secondary" id="cancelCurrentOrder">' . esc_html__('Annuler la commande', $cfg->domain) . '</a>
			</div>
		</div>';

		return $html;
	}
}
?>
