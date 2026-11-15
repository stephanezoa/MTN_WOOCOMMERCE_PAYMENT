<?php
defined('ABSPATH') || exit;

class WC_MomoPay_View
{

	protected static function getCurrencyInfo($cfg)
	{
		return array(
			'code' => $cfg->currency['code'],
			'desc' => (($cfg->isSandboxMode() && $cfg->currency['code'] != 'EUR')) ? 'Change to EUR if you want to test in Sandbox' : $cfg->currency['name']
		);
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
