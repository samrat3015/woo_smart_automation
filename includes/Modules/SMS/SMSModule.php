<?php
namespace WooSmartAutomation\Modules\SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMS Module - Order Confirmation Only
 */
class SMSModule {

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Hook into order confirmation (Processing or On-Hold for COD)
		add_action( 'woocommerce_order_status_processing', [ $this, 'send_order_confirmation' ], 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', [ $this, 'send_order_confirmation' ], 10, 1 );
	}

	/**
	 * Send Order Confirmation SMS
	 */
	public function send_order_confirmation( $order_id ) {
		if ( 'yes' !== get_option( 'wsa_sms_order_confirm_enabled', 'no' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Prevent duplicate SMS
		if ( $order->get_meta( '_wsa_confirm_sms_sent' ) ) {
			return;
		}

		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			return;
		}

		if ( file_exists( WSA_PATH . 'includes/Modules/SMS/BulkSMSBDService.php' ) ) {
			require_once WSA_PATH . 'includes/Modules/SMS/BulkSMSBDService.php';
		}
		
		if ( ! class_exists( 'WooSmartAutomation\Modules\SMS\BulkSMSBDService' ) ) {
			return;
		}

		$service = new BulkSMSBDService();
		$template = get_option( 'wsa_sms_order_confirm_template', 'Thank you for your order! Your Order ID is #{order_id}. Total: {order_total}' );
		
		// Get values
		$order_id_val    = $order->get_order_number();
		$order_total_val = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ) );
		$site_name_val   = get_bloginfo( 'name' );
		$first_name_val  = $order->get_billing_first_name();

		// Replace placeholders in template
		$message = str_replace( 
			[ '#{order_id}', '{order_id}', '[order_id]', '{order_total}', '[order_total]', '{site_name}', '[site_name]', '{customer_name}', '[customer_name]' ],
			[ $order_id_val, $order_id_val, $order_id_val, $order_total_val, $order_total_val, $site_name_val, $site_name_val, $first_name_val, $first_name_val ],
			$template
		);

		// Log for debugging
		error_log( 'WSA SMS Template: ' . $template );
		error_log( 'WSA SMS Message: ' . $message );
		error_log( 'WSA SMS Order ID: ' . $order_id_val . ' | Total: ' . $order_total_val );

		$result = $service->send_sms( $phone, $message );

		if ( $result ) {
			$order->update_meta_data( '_wsa_confirm_sms_sent', 'yes' );
			$order->save();
		}
	}
}
