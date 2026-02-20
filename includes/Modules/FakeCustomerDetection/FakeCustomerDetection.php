<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Fake Customer Detection & Trust Scoring Module
 */
class FakeCustomerDetection {

	public function init() {
		// Hook into new orders and order status changes
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'calculate_risk_on_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'recalculate_risk_on_status_change' ], 10, 4 );

		// Detect manual status changes in admin to prevent auto-action loops
		add_action( 'woocommerce_before_order_object_save', [ $this, 'catch_manual_status_override' ], 10, 1 );

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
	public function recalculate_risk_on_status_change( $order_id, $old_status, $new_status, $order ) {
		$this->calculate_and_store_risk( $order_id, $order );
	}

	/**
	 * Calculate and store risk score for an order
	 */
	private function calculate_and_store_risk( $order_id, $order = null ) {
		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
		
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Detect manual override if this is an admin context
		$this->catch_manual_status_override( $order );

		// Get customer identifier
		$email = $order->get_billing_email();
		$customer_id = $order->get_customer_id();

		// Calculate risk for current order
		$scorer = new RiskScorer();
		$result = $scorer->calculate_score( $order_id );

		if ( $result ) {
			// Store in order meta using update_post_meta for immediate visibility in admin columns
			update_post_meta( $order_id, '_wsa_risk_score', $result['score'] );
			update_post_meta( $order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
			update_post_meta( $order_id, '_wsa_risk_summary', $result['summary'] );

			// Auto Action: Hold or Cancel based on score
			$this->maybe_apply_auto_action( $order, $result['score'] );
		}

		// Update all other orders from the same customer
		$this->update_customer_orders( $email, $customer_id, $order_id );
	}

	/**
	 * Automatically change order status if risk score is high
	 * 
	 * @param \WC_Order $order
	 * @param int $score
	 */
	private function maybe_apply_auto_action( $order, $score ) {
		$enabled = get_option( 'wsa_auto_action_enabled', 'no' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		// If admin has manually overridden the status, don't touch it anymore
		if ( $order->get_meta( '_wsa_risk_manual_override' ) === 'yes' ) {
			return;
		}

		// Fail-safe for any back-office request (Quick Edit, Quick View, or Bulk Action)
		if ( is_admin() && ( ! empty( $_POST ) || ! empty( $_REQUEST['action'] ) ) && current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$threshold = (int) get_option( 'wsa_auto_action_score', 80 );
		$target_status = get_option( 'wsa_auto_action_status', 'on-hold' );

		if ( $score >= $threshold ) {
			$current_status = $order->get_status();
			
			// Enforce target status if current status is not target, cancelled, or failed
			if ( $current_status !== $target_status && ! in_array( $current_status, [ 'cancelled', 'failed' ] ) ) {
				// Avoid infinite recursion during status change hook
				remove_action( 'woocommerce_order_status_changed', [ $this, 'recalculate_risk_on_status_change' ], 10 );
				
				$order->update_status( $target_status, sprintf( __( 'High risk (%d) detected. Enforcing %s status to prevent fraud.', 'woo-smart-automation' ), $score, $target_status ) );
				
				add_action( 'woocommerce_order_status_changed', [ $this, 'recalculate_risk_on_status_change' ], 10, 4 );
			}
		}
	}

	/**
	 * Mark order as manually overridden when admin changes status
	 */
	public function catch_manual_status_override( $order ) {
		// Detect if request is from admin with order editing permissions
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			// If it's a POST request or specifically an AJAX action, it's a manual override
			// This catches Quick Edit, Quick View, Bulk Actions, and standard Edit page
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] || ! empty( $_POST ) || ( isset( $_GET['action'] ) && $_GET['action'] !== 'wsa_get_risk_details' ) ) {
				$order->update_meta_data( '_wsa_risk_manual_override', 'yes' );
				$order->save_meta_data(); // Persist immediately as this is often a one-off update in AJAX
			}
		}
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
