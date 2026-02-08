<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Fake Customer Detection & Trust Scoring Module
 */
class FakeCustomerDetection {

	public function init() {
		// Hook into new orders and order status changes
		add_action( 'woocommerce_new_order', [ $this, 'calculate_risk_on_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'recalculate_risk_on_status_change' ], 10, 1 );

		// Initialize admin integration
		if ( is_admin() ) {
			require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/AdminIntegration.php';
			$admin = new AdminIntegration();
			$admin->init();
		}
	}

	/**
	 * Calculate risk score when a new order is created
	 */
	public function calculate_risk_on_order( $order_id ) {
		$this->calculate_and_store_risk( $order_id );
	}

	/**
	 * Recalculate risk score when order status changes
	 */
	public function recalculate_risk_on_status_change( $order_id ) {
		$this->calculate_and_store_risk( $order_id );
	}

	/**
	 * Calculate and store risk score for an order
	 */
	private function calculate_and_store_risk( $order_id ) {
		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get customer identifier
		$email = $order->get_billing_email();
		$customer_id = $order->get_customer_id();

		// Calculate risk for current order
		$scorer = new RiskScorer();
		$result = $scorer->calculate_score( $order_id );

		if ( $result ) {
			// Store in order meta
			update_post_meta( $order_id, '_wsa_risk_score', $result['score'] );
			update_post_meta( $order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
			update_post_meta( $order_id, '_wsa_risk_summary', $result['summary'] );
		}

		// Update all other orders from the same customer
		$this->update_customer_orders( $email, $customer_id, $order_id );
	}

	/**
	 * Update risk scores for all orders from the same customer
	 */
	private function update_customer_orders( $email, $customer_id, $exclude_order_id ) {
		// Get all customer orders
		$args = [
			'limit'   => -1,
			'exclude' => [ $exclude_order_id ],
			'return'  => 'ids',
		];

		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		} else {
			$args['billing_email'] = $email;
		}

		$customer_orders = wc_get_orders( $args );

		if ( empty( $customer_orders ) ) {
			return;
		}

		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
		$scorer = new RiskScorer();

		// Recalculate risk for each order
		foreach ( $customer_orders as $customer_order_id ) {
			$result = $scorer->calculate_score( $customer_order_id );

			if ( $result ) {
				update_post_meta( $customer_order_id, '_wsa_risk_score', $result['score'] );
				update_post_meta( $customer_order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
				update_post_meta( $customer_order_id, '_wsa_risk_summary', $result['summary'] );
			}
		}
	}
}
