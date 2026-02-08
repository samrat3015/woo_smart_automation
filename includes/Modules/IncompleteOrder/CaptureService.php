<?php
namespace WooSmartAutomation\Modules\IncompleteOrder;

class CaptureService {

	public static function handle() {
		global $wpdb;

		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';

		if ( empty( $phone ) && empty( $email ) ) {
			return new \WP_Error( 'empty_fields', 'Both phone and email are empty' );
		}

		// Ensure WooCommerce is fully loaded for this AJAX request
		if ( ! function_exists( 'WC' ) ) {
			return new \WP_Error( 'wc_missing', 'WooCommerce is not loaded' );
		}

		// Load cart if not already loaded
		if ( is_null( WC()->cart ) ) {
			wc_load_cart();
		}

		// Try to get WC session ID or fallback to cookie logic
		$session_token = ( WC()->session && method_exists( WC()->session, 'get_customer_id' ) ) 
			? WC()->session->get_customer_id() 
			: '';

		if ( empty( $session_token ) ) {
			$session_token = 'guest_' . md5( $_SERVER['REMOTE_ADDR'] . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) );
		}

		$table = $wpdb->prefix . 'woo_smart_incomplete_orders';

		// Strategy: Check if phone exists first (Strongest signal)
		$existing = null;
		if ( ! empty( $phone ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s AND status = 'captured'", $phone ) );
		}

		// If not found by phone, check by session/email? 
		if ( ! $existing && ! empty( $email ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND status = 'captured'", $email ) );
		}
		
		$cart_items = [];
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product = $cart_item['data'];
				$cart_items[] = [
					'product_id' => $cart_item['product_id'],
					'quantity'   => $cart_item['quantity'],
					'price'      => $product->get_price(),
					'name'       => $product->get_name(),
				];
			}
		}
		$cart_data = json_encode( $cart_items );

		$data = [
			'session_token' => $session_token,
			'phone'         => $phone,
			'email'         => $email,
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'cart_data'     => $cart_data,
			'updated_at'    => current_time( 'mysql' )
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => $existing->id ] );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$data['status']     = 'captured';
			$wpdb->insert( $table, $data );
		}

		if ( $wpdb->last_error ) {
			return new \WP_Error( 'db_error', $wpdb->last_error );
		}

		return true;
	}
}
