<?php
namespace WooSmartAutomation\Modules\IncompleteOrder;

class OrderHook {

	public function init() {
		// Runs when a new order is created
		add_action( 'woocommerce_new_order', [ $this, 'link_incomplete_order' ], 10, 2 );
	}

	public function link_incomplete_order( $order_id, $order ) {
		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_incomplete_orders';

		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();

		if ( empty( $phone ) && empty( $email ) ) {
			return;
		}

		// Find the captured lead
		// Prioritize phone match
		$lead = null;
		if ( ! empty( $phone ) ) {
			$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s AND status = 'captured'", $phone ) );
		}

		if ( ! $lead && ! empty( $email ) ) {
			$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s AND status = 'captured'", $email ) );
		}

		if ( $lead ) {
			// Update status to converted
			$wpdb->update( 
				$table, 
				[ 
					'status' => 'converted', 
					// We could store order_id here if we add a column later
					'updated_at' => current_time( 'mysql' )
				], 
				[ 'id' => $lead->id ] 
			);
		}
	}
}
