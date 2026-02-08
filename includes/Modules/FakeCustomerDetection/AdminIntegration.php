<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Admin Integration for Risk Score Display
 */
class AdminIntegration {

	public function init() {
		// Add column to WooCommerce Orders table (supports both traditional and HPOS)
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_risk_score_column' ], 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_risk_score_column' ], 20 );

		// Populate the column
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'populate_risk_score_column' ], 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'populate_risk_score_column_hpos' ], 10, 2 );

		// Enqueue admin styles
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Add detailed view modal/tooltip via AJAX
		add_action( 'wp_ajax_wsa_get_risk_details', [ $this, 'ajax_get_risk_details' ] );
		
		// Add recalculate risk AJAX handler
		add_action( 'wp_ajax_wsa_recalculate_risk', [ $this, 'ajax_recalculate_risk' ] );
	}

	/**
	 * Add Risk Score column to orders table
	 */
	public function add_risk_score_column( $columns ) {
		$new_columns = [];

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			
			// Insert after 'order_status' column
			if ( $key === 'order_status' ) {
				$new_columns['wsa_risk_score'] = __( 'Risk Score', 'woo-smart-automation' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate Risk Score column for traditional orders
	 */
	public function populate_risk_score_column( $column, $post_id ) {
		if ( $column === 'wsa_risk_score' ) {
			$this->render_risk_score_cell( $post_id );
		}
	}

	/**
	 * Populate Risk Score column for HPOS orders
	 */
	public function populate_risk_score_column_hpos( $column, $order ) {
		if ( $column === 'wsa_risk_score' ) {
			$order_id = is_numeric( $order ) ? $order : $order->get_id();
			$this->render_risk_score_cell( $order_id );
		}
	}

	/**
	 * Render the risk score cell content
	 */
	private function render_risk_score_cell( $order_id ) {
		$score = get_post_meta( $order_id, '_wsa_risk_score', true );
		$summary = get_post_meta( $order_id, '_wsa_risk_summary', true );
		$is_duplicate = get_post_meta( $order_id, '_wsa_is_potential_duplicate', true );

		if ( $score === '' ) {
			echo '<span class="wsa-risk-pending">—</span>';
			return;
		}

		$risk_class = $this->get_risk_class( $score );

		?>
		<div class="wsa-risk-container">
			<div class="wsa-risk-progress-bar wsa-view-details" data-order-id="<?php echo esc_attr( $order_id ); ?>" title="Click to view details">
				<div class="wsa-progress-fill wsa-risk-<?php echo esc_attr( $risk_class ); ?>" style="width: <?php echo esc_attr( $score ); ?>%;">
					<span class="wsa-score-label"><?php echo esc_html( $score ); ?></span>
				</div>
			</div>
			<?php if ( $is_duplicate ) : ?>
				<div class="wsa-duplicate-badge" title="<?php echo esc_attr( sprintf( 'Potential duplicate of order #%d', $is_duplicate ) ); ?>">
					<?php _e( 'DUPLICATE', 'woo-smart-automation' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get CSS class based on risk score
	 */
	private function get_risk_class( $score ) {
		if ( $score <= 30 ) {
			return 'low';
		} elseif ( $score <= 70 ) {
			return 'medium';
		} else {
			return 'high';
		}
	}

	/**
	 * Get risk level text
	 */
	private function get_risk_level( $score ) {
		if ( $score <= 30 ) {
			return __( 'Low Risk', 'woo-smart-automation' );
		} elseif ( $score <= 70 ) {
			return __( 'Medium Risk', 'woo-smart-automation' );
		} else {
			return __( 'High Risk', 'woo-smart-automation' );
		}
	}

	/**
	 * Enqueue admin styles and scripts
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on orders page
		if ( $hook !== 'edit.php' && strpos( $hook, 'wc-orders' ) === false ) {
			return;
		}

		wp_enqueue_style( 
			'wsa-risk-score', 
			WSA_URL . 'assets/css/risk-score.css', 
			[], 
			WSA_VERSION 
		);

		wp_enqueue_script( 
			'wsa-risk-score', 
			WSA_URL . 'assets/js/risk-score.js', 
			[ 'jquery' ], 
			WSA_VERSION, 
			true 
		);

		wp_localize_script( 'wsa-risk-score', 'wsaRiskScore', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wsa_risk_details' ),
		] );
	}

	/**
	 * AJAX handler to get detailed risk breakdown
	 */
	public function ajax_get_risk_details() {
		check_ajax_referer( 'wsa_risk_details', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => 'Invalid order ID' ] );
		}

		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		$score = get_post_meta( $order_id, '_wsa_risk_score', true );
		$signals = get_post_meta( $order_id, '_wsa_risk_signals', true );
		$signals = json_decode( $signals, true );

		$risk_class = $this->get_risk_class( $score );
		$risk_level = $this->get_risk_level( $score );

		// Get courier history
		$courier_history = $this->get_courier_history( $order );

		$html = '<div class="wsa-risk-details-modal">';
		$html .= '<div class="wsa-modal-header">';
		$html .= '<h3>Risk Analysis for Order #' . esc_html( $order->get_order_number() ) . '</h3>';
		$html .= '<p><strong>Risk Score:</strong> <span class="wsa-modal-score-badge ' . esc_attr( $risk_class ) . '">' . esc_html( $score ) . '/100 - ' . esc_html( $risk_level ) . '</span></p>';
		$html .= '<span class="wsa-modal-close">&times;</span>';
		$html .= '</div>';

		$html .= '<div class="wsa-modal-body">';

		// Fraud Check button at TOP - always visible
		$html .= '<div class="wsa-fraud-check-banner">';
		$html .= '<button type="button" class="button button-primary wsa-recalculate-btn" data-order-id="' . esc_attr( $order_id ) . '">🔍 Fraud Check</button>';
		$html .= '<p>Fetch SteadFast data & update risk score for all customer orders</p>';
		$html .= '</div>';

		// SteadFast Cross-Merchant Data - Show at TOP position
		$courier_total = get_post_meta( $order_id, '_wsa_courier_total_orders', true );
		$courier_delivered = get_post_meta( $order_id, '_wsa_courier_delivered', true );
		$courier_cancelled = get_post_meta( $order_id, '_wsa_courier_cancelled', true );
		$courier_success_rate = get_post_meta( $order_id, '_wsa_courier_success_rate', true );
		
		if ( $courier_total !== '' && $courier_total !== false ) {
			$badge_class = 'low';
			if ( $courier_success_rate < 70 ) {
				$badge_class = 'medium';
			}
			if ( $courier_success_rate < 50 ) {
				$badge_class = 'high';
			}
			
			$html .= '<h4>📊 SteadFast Courier Score</h4>';
			$html .= '<div class="wsa-courier-info-card success">';
			$html .= '<p><strong>Success Rate:</strong> <span class="wsa-modal-score-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $courier_success_rate ) . '%</span></p>';
			$html .= '<p><strong>📦 Total Orders:</strong> ' . esc_html( $courier_total ) . '</p>';
			$html .= '<p><strong>✅ Delivered:</strong> ' . esc_html( $courier_delivered ) . '</p>';
			$html .= '<p><strong>❌ Cancelled:</strong> ' . esc_html( $courier_cancelled ) . '</p>';
			$html .= '<p class="wsa-card-footer">📡 Data from SteadFast across all merchants</p>';
			$html .= '</div>';
		} else {
			// Show warning if no SteadFast data
			$html .= '<h4>📊 SteadFast Courier Score</h4>';
			$html .= '<div class="wsa-courier-info-card warning">';
			$html .= '<p class="wsa-warning-text">⚠️ SteadFast data not available for this order.</p>';
			$html .= '<p class="wsa-card-footer">Use the button below to fetch courier data.</p>';
			$html .= '</div>';
		}

		if ( ! empty( $signals['negative'] ) ) {
			$html .= '<h4>⚠️ Negative Signals</h4>';
			$html .= '<div class="wsa-signals-card negative">';
			$html .= '<ul>';
			foreach ( $signals['negative'] as $signal ) {
				$html .= '<li>' . esc_html( $signal ) . '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		if ( ! empty( $signals['positive'] ) ) {
			$html .= '<h4>✅ Positive Signals</h4>';
			$html .= '<div class="wsa-signals-card positive">';
			$html .= '<ul>';
			foreach ( $signals['positive'] as $signal ) {
				$html .= '<li>' . esc_html( $signal ) . '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		if ( ! empty( $courier_history ) ) {
			$html .= '<h4>📦 Courier History</h4>';
			$html .= '<div class="wsa-signals-card neutral">';
			$html .= '<ul>';
			foreach ( $courier_history as $event ) {
				$html .= '<li>' . esc_html( $event ) . '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		$html .= '</div>'; // .wsa-modal-body
		$html .= '</div>'; // .wsa-risk-details-modal

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Get courier history for customer
	 */
	private function get_courier_history( $order ) {
		$history = [];
		$customer_id = $order->get_customer_id();
		$email = $order->get_billing_email();

		// Get customer's previous orders
		$args = [
			'limit'  => -1,
			'return' => 'ids',
		];

		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		} else {
			$args['billing_email'] = $email;
		}

		$order_ids = wc_get_orders( $args );

		foreach ( $order_ids as $order_id ) {
			$notes = wc_get_order_notes( [ 'order_id' => $order_id ] );
			
			foreach ( $notes as $note ) {
				// Look for courier-related keywords
				if ( stripos( $note->content, 'pathao' ) !== false || 
				     stripos( $note->content, 'steadfast' ) !== false ||
				     stripos( $note->content, 'delivered' ) !== false ||
				     stripos( $note->content, 'returned' ) !== false ) {
					$history[] = sprintf( 
						'Order #%s: %s', 
						$order_id, 
						wp_strip_all_tags( $note->content ) 
					);
				}
			}
		}

		return $history;
	}

	/**
	 * AJAX handler to recalculate risk score for an order and all customer orders
	 */
	public function ajax_recalculate_risk() {
		check_ajax_referer( 'wsa_risk_details', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => 'Invalid order ID' ] );
		}

		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		// Get customer identifier
		$customer_id = $order->get_customer_id();
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();

		// Find all orders from this customer
		$args = [
			'limit'  => -1,
			'return' => 'ids',
		];

		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		} else {
			$args['billing_email'] = $email;
		}

		$customer_order_ids = wc_get_orders( $args );

		// Also get orders by phone number
		$phone_orders = wc_get_orders( [
			'limit'         => -1,
			'return'        => 'ids',
			'billing_phone' => $phone,
		] );

		// Merge and unique
		$all_order_ids = array_unique( array_merge( $customer_order_ids, $phone_orders ) );

		// Recalculate risk score with fresh API data (bypass cache)
		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
		$scorer = new RiskScorer();
		
		// First, calculate for the current order (with bypass_cache to get fresh SteadFast data)
		$result = $scorer->calculate_score( $order_id, true );
		
		if ( ! $result ) {
			wp_send_json_error( [ 'message' => 'Failed to calculate risk score' ] );
		}

		// Store current order meta
		update_post_meta( $order_id, '_wsa_risk_score', $result['score'] );
		update_post_meta( $order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
		update_post_meta( $order_id, '_wsa_risk_summary', $result['summary'] );

		// Get the cached SteadFast data from current order to apply to all
		$courier_total = get_post_meta( $order_id, '_wsa_courier_total_orders', true );
		$courier_delivered = get_post_meta( $order_id, '_wsa_courier_delivered', true );
		$courier_cancelled = get_post_meta( $order_id, '_wsa_courier_cancelled', true );
		$courier_success_rate = get_post_meta( $order_id, '_wsa_courier_success_rate', true );

		$updated_count = 1; // Already updated current order

		// Now update all other customer orders
		foreach ( $all_order_ids as $other_order_id ) {
			if ( $other_order_id == $order_id ) {
				continue; // Skip current order
			}

			// Recalculate (cache will be used now since we already fetched)
			$other_result = $scorer->calculate_score( $other_order_id, false );
			
			if ( $other_result ) {
				update_post_meta( $other_order_id, '_wsa_risk_score', $other_result['score'] );
				update_post_meta( $other_order_id, '_wsa_risk_signals', wp_json_encode( $other_result['signals'] ) );
				update_post_meta( $other_order_id, '_wsa_risk_summary', $other_result['summary'] );

				// Copy SteadFast data to this order too
				if ( $courier_total !== '' && $courier_total !== false ) {
					update_post_meta( $other_order_id, '_wsa_courier_total_orders', $courier_total );
					update_post_meta( $other_order_id, '_wsa_courier_delivered', $courier_delivered );
					update_post_meta( $other_order_id, '_wsa_courier_cancelled', $courier_cancelled );
					update_post_meta( $other_order_id, '_wsa_courier_success_rate', $courier_success_rate );
				}

				$updated_count++;
			}
		}

		wp_send_json_success( [ 
			'message' => sprintf( 'Risk score recalculated for %d orders!', $updated_count ),
			'score'   => $result['score'],
			'updated' => $updated_count
		] );
	}
}
