<?php
namespace WooSmartAutomation\Modules\IncompleteOrder;

class OrderHook {

	public function init() {
		// Runs when a new order is created
		add_action( 'woocommerce_new_order', [ $this, 'link_incomplete_order' ], 10, 2 );
		add_action( 'wsa_cleanup_incomplete_lead', [ $this, 'cleanup_incomplete_lead' ], 10, 2 );
	}

	public function link_incomplete_order( $order_id, $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$phone = sanitize_text_field( $order->get_billing_phone() );
		$email = sanitize_email( $order->get_billing_email() );

		if ( empty( $phone ) && empty( $email ) ) {
			return;
		}

		// Silent async cleanup so checkout performance is not impacted.
		wp_schedule_single_event( time() + 5, 'wsa_cleanup_incomplete_lead', [ $phone, $email ] );
	}

	public function cleanup_incomplete_lead( $phone = '', $email = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_incomplete_orders';

		$phone = sanitize_text_field( $phone );
		$email = sanitize_email( $email );

		if ( empty( $phone ) && empty( $email ) ) {
			return;
		}

		$where_sql = [];
		$where_args = [];

		if ( ! empty( $phone ) ) {
			$where_sql[]  = 'phone = %s';
			$where_args[] = $phone;
		}

		if ( ! empty( $email ) ) {
			$where_sql[]  = 'email = %s';
			$where_args[] = $email;
		}

		if ( empty( $where_sql ) ) {
			return;
		}

		$query = "DELETE FROM $table WHERE status IN ('captured', 'converted') AND (" . implode( ' OR ', $where_sql ) . ')';

		$wpdb->query( $wpdb->prepare( $query, $where_args ) );
	}
}
