<?php
defined('ABSPATH') || exit;

class WC_MomoPay_Controller
{

	protected static function getAnswerObject($res)
	{
		$o = new stdClass();
		$o->status = (is_object($res) && isset($res->status)) ? $res->status : '';
		$o->message = (is_object($res) && isset($res->message)) ? $res->message : '';
		$o->data = (is_object($res) && isset($res->data)) ? $res->data : '';
		$o->code = (is_object($res) && isset($res->code)) ? $res->code : 200;

		return wp_json_encode($o);
	}

	protected static function errorAnswer($message, $code = 400)
	{
		$o = new stdClass();
		$o->status = '';
		$o->message = $message;
		$o->data = '';
		$o->code = $code;

		return wp_json_encode($o);
	}

	public static function processData($data)
	{
		if (!is_array($data) || empty($data)) {
			return self::errorAnswer('Input data error!');
		}

		$mode = isset($data['mode']) ? sanitize_key($data['mode']) : '';
		if ($mode !== 'check_payment') {
			return self::errorAnswer('Unknown mode!');
		}

		$payment_id = isset($data['payment_id']) ? sanitize_text_field((string) $data['payment_id']) : '';
		$order_key = isset($data['order_key']) ? sanitize_text_field((string) $data['order_key']) : '';

		// payment ids are UUID-like strings
		if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $payment_id) || $order_key === '') {
			return self::errorAnswer('Invalid payment data!');
		}

		$p = new WC_MomoPay_Gateway();

		// The payment must be known locally and belong to the order the caller proves to own (order key)
		$transaction = $p->getTransactionByPaymentId($payment_id);
		if (!$transaction) {
			return self::errorAnswer('Unknown payment!', 404);
		}

		$order = wc_get_order((int) $transaction->order_id);
		if (!$order || !hash_equals((string) $order->get_order_key(), $order_key)) {
			return self::errorAnswer('Unknown payment!', 404);
		}

		$res = $p->getPaymentStatus($payment_id);
		if (is_object($res) && !empty($res->status)) {
			$p->updateTransaction($payment_id, $res);
		}

		return self::getAnswerObject($res);
	}
}
