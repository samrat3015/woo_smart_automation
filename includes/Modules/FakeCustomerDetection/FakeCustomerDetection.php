<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Fake Customer Detection & Trust Scoring Module
 * 
 * Uses async/background processing via WP Cron so that risk score
 * calculation does NOT delay the order placement for the customer.
 */
class FakeCustomerDetection {

	public function init() {
		// Hook into new orders — schedule async background job instead of blocking
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'schedule_risk_calculation' ], 10, 1 );

		// Also recalculate when order status changes (async)
		add_action( 'woocommerce_order_status_changed', [ $this, 'schedule_risk_on_status_change' ], 10, 4 );

		// Register the background WP Cron action handler
		add_action( 'wsa_async_calculate_risk', [ $this, 'process_async_risk_calculation' ], 10, 1 );

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
	 * Schedule async risk calculation for a new order.
	 * Does NOT block checkout — schedules a WP Cron event to run ~5 seconds later.
	 */
	public function schedule_risk_calculation( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		// Skip refund objects — they have no billing data
		$order = wc_get_order( $order_id );
		if ( ! $order || $order instanceof \WC_Order_Refund
			|| ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
			return;
		}

		// Store a "pending" marker so admin sees it is queued
		update_post_meta( $order_id, '_wsa_risk_status', 'pending' );

		// Schedule background calculation (fires ASAP via WP Cron)
		if ( ! wp_next_scheduled( 'wsa_async_calculate_risk', [ $order_id ] ) ) {
			wp_schedule_single_event( time() + 5, 'wsa_async_calculate_risk', [ $order_id ] );
		}
	}

	/**
	 * Schedule async risk recalculation on order status change.
	 * Skips if it was a manual admin override.
	 */
	public function schedule_risk_on_status_change( $order_id, $old_status, $new_status, $order ) {
		// Don't re-schedule if admin manually overrode
		if ( $order && $order->get_meta( '_wsa_risk_manual_override' ) === 'yes' ) {
			return;
		}

		// Skip refunds — they are not real orders
		if ( $order instanceof \WC_Order_Refund
			|| ( $order && method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
			return;
		}

		// Schedule background recalculation
		if ( ! wp_next_scheduled( 'wsa_async_calculate_risk', [ $order_id ] ) ) {
			wp_schedule_single_event( time() + 3, 'wsa_async_calculate_risk', [ $order_id ] );
		}
	}

	/**
	 * Process risk calculation in the background (called by WP Cron).
	 * This is the actual heavy-lifting function, runs async.
	 *
	 * @param int $order_id
	 */
	public function process_async_risk_calculation( $order_id ) {
		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Bail on missing orders AND refund objects (no billing methods)
		if ( ! $order || $order instanceof \WC_Order_Refund
			|| ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
			return;
		}

		try {
			// Mark as processing so we know it's running
			update_post_meta( $order_id, '_wsa_risk_status', 'processing' );

			// Get customer identifier
			$email       = $order->get_billing_email();
			$customer_id = $order->get_customer_id();

			// Calculate risk for current order
			require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
			$scorer = new \WooSmartAutomation\Modules\FakeCustomerDetection\RiskScorer();
			$result = $scorer->calculate_score( $order_id );

			if ( $result ) {
				update_post_meta( $order_id, '_wsa_risk_score',   $result['score'] );
				update_post_meta( $order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
				update_post_meta( $order_id, '_wsa_risk_summary', $result['summary'] );
				update_post_meta( $order_id, '_wsa_risk_status',  'completed' );
				update_post_meta( $order_id, '_wsa_risk_calculated_at', current_time( 'mysql' ) );

				// Auto Action: Hold or Cancel based on score
				$this->maybe_apply_auto_action( $order, $result['score'] );
			} else {
				// No result (could be API error)
				update_post_meta( $order_id, '_wsa_risk_status', 'failed' );
			}
		} catch ( \Exception $e ) {
			error_log( 'WSA Background Risk Error: ' . $e->getMessage() );
			update_post_meta( $order_id, '_wsa_risk_status', 'failed' );
		}

		// Update all other orders from the same customer (background, non-blocking)
		$phone = $order->get_billing_phone();
		$this->update_customer_orders( $email, $customer_id, $order_id, $phone );
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

		$threshold     = (int) get_option( 'wsa_auto_action_score', 20 );
		$target_status = get_option( 'wsa_auto_action_status', 'on-hold' );

		// NEW LOGIC: Trigger action if score is BELOW threshold (Higher is now better)
		if ( $score < $threshold && $score > 0 ) {
			$current_status = $order->get_status();

			// Enforce target status if current status is not target, cancelled, or failed
			if ( $current_status !== $target_status && ! in_array( $current_status, [ 'cancelled', 'failed' ] ) ) {
				// Avoid infinite recursion during status change hook
				remove_action( 'woocommerce_order_status_changed', [ $this, 'schedule_risk_on_status_change' ], 10 );

				$order->update_status( $target_status, sprintf(
					__( 'Low delivery score (%d) detected (Threshold: %d). Enforcing %s status to prevent potential return/fraud.', 'woo-smart-automation' ),
					$score,
					$threshold,
					$target_status
				) );

				add_action( 'woocommerce_order_status_changed', [ $this, 'schedule_risk_on_status_change' ], 10, 4 );
			}
		}
	}

	/**
	 * Mark order as manually overridden when admin changes status
	 */
	public function catch_manual_status_override( $order ) {
		// Detect if request is from admin with order editing permissions
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] || ! empty( $_POST ) || ( isset( $_GET['action'] ) && $_GET['action'] !== 'wsa_get_risk_details' ) ) {
				$order->update_meta_data( '_wsa_risk_manual_override', 'yes' );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Update risk scores for all orders from the same customer (runs in background)
	 */
	private function update_customer_orders( $email, $customer_id, $exclude_order_id, $phone = '' ) {
		$args = [
			'limit'   => -1,
			'exclude' => [ $exclude_order_id ],
			'return'  => 'ids',
			'type'    => 'shop_order', // Explicitly exclude refund objects
		];

		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		} else {
			$args['billing_email'] = $email;
		}

		$customer_orders = wc_get_orders( $args );

		// Also merge by phone if provided
		if ( ! empty( $phone ) ) {
			$phone_args = [
				'limit'         => -1,
				'exclude'       => [ $exclude_order_id ],
				'billing_phone' => $phone,
				'return'        => 'ids',
				'type'          => 'shop_order', // Explicitly exclude refund objects
			];
			$phone_ids = wc_get_orders( $phone_args );
			$customer_orders = array_unique( array_merge( $customer_orders, $phone_ids ) );
		}

		if ( empty( $customer_orders ) ) {
			return;
		}

		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
		$scorer = new RiskScorer();

		foreach ( $customer_orders as $customer_order_id ) {
			$result = $scorer->calculate_score( $customer_order_id );

			if ( $result ) {
				update_post_meta( $customer_order_id, '_wsa_risk_score',            $result['score'] );
				update_post_meta( $customer_order_id, '_wsa_risk_signals',          wp_json_encode( $result['signals'] ) );
				update_post_meta( $customer_order_id, '_wsa_risk_summary',          $result['summary'] );
				update_post_meta( $customer_order_id, '_wsa_risk_status',           'completed' );
				update_post_meta( $customer_order_id, '_wsa_risk_calculated_at',    current_time( 'mysql' ) );
			}
		}
	}
}
