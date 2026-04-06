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
		$score        = get_post_meta( $order_id, '_wsa_risk_score', true );
		$signals      = get_post_meta( $order_id, '_wsa_risk_signals', true );
		$risk_status  = get_post_meta( $order_id, '_wsa_risk_status', true );
		$is_duplicate = get_post_meta( $order_id, '_wsa_is_potential_duplicate', true );
		$signals_data = $signals ? json_decode( $signals, true ) : [];

		// Show async pending/processing/failed badge (with manual recheck option)
		if ( $score === '' || $risk_status === 'pending' || $risk_status === 'processing' || $risk_status === 'failed' ) {
			if ( $risk_status === 'processing' ) {
				$label = '⚙️ Analyzing…';
				$status_class = 'wsa-status-processing';
			} elseif ( $risk_status === 'failed' ) {
				$label = '⚠️ Error';
				$status_class = 'wsa-status-failed';
			} else {
				$label = '⏳ Queued';
				$status_class = 'wsa-status-queued';
			}
			?>
			<div class="wsa-risk-container" data-order-id="<?php echo esc_attr( $order_id ); ?>">
				<div class="wsa-risk-footer">
					<span class="wsa-risk-async-badge <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $label ); ?>
					</span>
					<button type="button" class="wsa-recheck-risk-btn" title="Manual Recheck" data-order-id="<?php echo esc_attr( $order_id ); ?>">
						<span class="dashicons dashicons-update"></span>
					</button>
				</div>
			</div>
			<?php
			return;
		}

		$risk_class = $this->get_risk_class( $score );
		$risk_level = $this->get_risk_level( $score );

		// Build inline tooltip content
		$tooltip_lines = [];
		if ( ! empty( $signals_data['negative'] ) ) {
			foreach ( array_slice( $signals_data['negative'], 0, 3 ) as $sig ) {
				$tooltip_lines[] = '❌ ' . $sig;
			}
		}
		if ( ! empty( $signals_data['positive'] ) ) {
			foreach ( array_slice( $signals_data['positive'], 0, 2 ) as $sig ) {
				$tooltip_lines[] = '✅ ' . $sig;
			}
		}
		$tooltip_text = implode( '\n', $tooltip_lines );

		?>
		<div class="wsa-risk-container" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<!-- Progress Bar -->
			<div class="wsa-risk-progress-bar wsa-view-details"
				 data-order-id="<?php echo esc_attr( $order_id ); ?>"
				 title="Click to view full details">
				<div class="wsa-progress-fill wsa-risk-<?php echo esc_attr( $risk_class ); ?>" style="width: <?php echo esc_attr( $score ); ?>%;">
					<span class="wsa-score-label"><?php echo esc_html( $score ); ?></span>
				</div>
			</div>

			<!-- Risk Level Badge & Recheck -->
			<div class="wsa-risk-footer">
				<span class="wsa-risk-level-badge wsa-level-<?php echo esc_attr( $risk_class ); ?>">
					<?php echo esc_html( $risk_level ); ?>
				</span>
				<button type="button" class="wsa-recheck-risk-btn" title="Manual Recheck" data-order-id="<?php echo esc_attr( $order_id ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>

			<!-- Inline Tooltip (hover) -->
			<?php if ( ! empty( $tooltip_lines ) ) : ?>
				<div class="wsa-risk-tooltip">
					<div class="wsa-tooltip-header">
						<strong><?php echo esc_html( $risk_level ); ?></strong>
						<span class="wsa-tooltip-score"><?php echo esc_html( $score ); ?>/100</span>
					</div>
					<ul class="wsa-tooltip-signals">
						<?php foreach ( $tooltip_lines as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="wsa-tooltip-hint">Click bar for full details</p>
				</div>
			<?php endif; ?>

			<?php if ( $is_duplicate ) : ?>
				<div class="wsa-duplicate-badge" title="<?php echo esc_attr( sprintf( 'Potential duplicate of order #%d', $is_duplicate ) ); ?>">
					<?php _e( 'DUPLICATE', 'woo-smart-automation' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get CSS class based on risk score (5 levels)
	 */
	private function get_risk_class( $score ) {
		if ( $score <= 20 ) {
			return 'very-low';
		} elseif ( $score <= 40 ) {
			return 'low';
		} elseif ( $score <= 60 ) {
			return 'medium';
		} elseif ( $score <= 80 ) {
			return 'high';
		} else {
			return 'critical';
		}
	}

	/**
	 * Get risk level text (5 levels)
	 */
	private function get_risk_level( $score ) {
		if ( $score <= 20 ) {
			return __( 'Very Low', 'woo-smart-automation' );
		} elseif ( $score <= 40 ) {
			return __( 'Low', 'woo-smart-automation' );
		} elseif ( $score <= 60 ) {
			return __( 'Medium', 'woo-smart-automation' );
		} elseif ( $score <= 80 ) {
			return __( 'High', 'woo-smart-automation' );
		} else {
			return __( 'Critical', 'woo-smart-automation' );
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
	 * AJAX handler to get detailed risk breakdown — Professional Modal
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

		$score          = (int) get_post_meta( $order_id, '_wsa_risk_score', true );
		$signals        = get_post_meta( $order_id, '_wsa_risk_signals', true );
		$signals        = $signals ? json_decode( $signals, true ) : [];
		$calculated_at  = get_post_meta( $order_id, '_wsa_risk_calculated_at', true );

		$risk_class = $this->get_risk_class( $score );
		$risk_level = $this->get_risk_level( $score );

		// FraudPeek meta
		$fp_available     = get_post_meta( $order_id, '_wsa_fp_available', true );
		$fp_risk_level    = get_post_meta( $order_id, '_wsa_fp_risk_level', true );
		$fp_risk_score    = get_post_meta( $order_id, '_wsa_fp_risk_score', true );
		$fp_ai_score      = get_post_meta( $order_id, '_wsa_fp_ai_risk_score', true );
		$fp_risk_msg      = get_post_meta( $order_id, '_wsa_fp_risk_message', true );
		$fp_ai_summary    = get_post_meta( $order_id, '_wsa_fp_ai_summary', true );
		$fp_total         = get_post_meta( $order_id, '_wsa_fp_total_parcels', true );
		$fp_delivered     = get_post_meta( $order_id, '_wsa_fp_delivered', true );
		$fp_cancelled     = get_post_meta( $order_id, '_wsa_fp_cancelled', true );
		$fp_returned      = get_post_meta( $order_id, '_wsa_fp_returned', true );
		$fp_delivery_rate = get_post_meta( $order_id, '_wsa_fp_delivery_rate', true );
		$fp_return_rate   = get_post_meta( $order_id, '_wsa_fp_return_rate', true );
		$fp_fraud_alerts  = get_post_meta( $order_id, '_wsa_fp_fraud_alerts', true );
		$fp_reports       = get_post_meta( $order_id, '_wsa_fp_report_count', true );
		$fp_sources       = get_post_meta( $order_id, '_wsa_fp_courier_sources', true );
		$fp_couriers_raw  = get_post_meta( $order_id, '_wsa_fp_couriers', true );
		$fp_fetched_at    = get_post_meta( $order_id, '_wsa_fp_fetched_at', true );
		$fp_data_source   = get_post_meta( $order_id, '_wsa_fp_data_source', true );

		// ── Build Modal HTML ─────────────────────────────────────────────────
		$html = '<div class="wsa-risk-details-modal wsa-v2">';

		// ── Header with gradient & score ring ──────────────────────────────
		$html .= '<div class="wsa-m-header wsa-hdr-' . esc_attr( $risk_class ) . '">';
		$html .= '<div class="wsa-hdr-inner">';
		$html .= '<div class="wsa-hdr-left">';
		$html .= '<h3 class="wsa-hdr-title">Risk Analysis</h3>';
		$html .= '<p class="wsa-hdr-sub">Order #' . esc_html( $order->get_order_number() ) . ' &middot; ' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</p>';
		if ( $calculated_at ) {
			$html .= '<span class="wsa-hdr-time">Analyzed ' . esc_html( human_time_diff( strtotime( $calculated_at ), current_time( 'timestamp' ) ) ) . ' ago</span>';
		}
		$html .= '</div>';
		$html .= '<div class="wsa-hdr-right">';
		$html .= '<div class="wsa-score-ring wsa-ring-' . esc_attr( $risk_class ) . '">';
		$html .= '<div class="wsa-ring-num">' . esc_html( $score ) . '</div>';
		$html .= '<div class="wsa-ring-lbl">' . esc_html( $risk_level ) . '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<span class="wsa-modal-close">&times;</span>';
		$html .= '</div>';

		// ── Body ────────────────────────────────────────────────────────────
		$html .= '<div class="wsa-m-body">';

		// ── FraudPeek Banner ─────────────────────────────────────────────
		$html .= '<div class="wsa-fp-banner">';
		$html .= '<div class="wsa-fp-banner-left">';
		$html .= '<div class="wsa-fp-banner-icon">🛡️</div>';
		$html .= '<div class="wsa-fp-banner-info">';
		$html .= '<strong>Courier Intelligence</strong>';
		$html .= '<span>Multi-courier fraud analysis across all platforms</span>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<button type="button" class="wsa-fp-btn wsa-recalculate-btn" data-order-id="' . esc_attr( $order_id ) . '">';
		$html .= '<span>⚡</span> Refresh</button>';
		$html .= '</div>';

		// ── FraudPeek Data ───────────────────────────────────────────────
		if ( $fp_available === '1' ) {

			$fp_level_lower = strtolower( $fp_risk_level ?: 'unknown' );
			$fp_cls = 'medium';
			if ( in_array( $fp_level_lower, [ 'low', 'very low', 'very_low' ], true ) ) {
				$fp_cls = 'low';
			} elseif ( $fp_level_lower === 'high' ) {
				$fp_cls = 'high';
			} elseif ( in_array( $fp_level_lower, [ 'critical', 'very high', 'very_high' ], true ) ) {
				$fp_cls = 'critical';
			}

			// Score cards row
			$html .= '<div class="wsa-cards-row">';
			$html .= '<div class="wsa-card wsa-card-' . esc_attr( $fp_cls ) . '">';
			$html .= '<div class="wsa-card-lbl">Risk Level</div>';
			$html .= '<div class="wsa-card-val">' . esc_html( ucfirst( $fp_risk_level ) ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="wsa-card wsa-card-blue">';
			$html .= '<div class="wsa-card-lbl">FP Score</div>';
			$html .= '<div class="wsa-card-val">' . esc_html( $fp_risk_score ) . '<small>/100</small></div>';
			$html .= '</div>';
			$html .= '<div class="wsa-card wsa-card-purple">';
			$html .= '<div class="wsa-card-lbl">AI Score</div>';
			$html .= '<div class="wsa-card-val">' . esc_html( $fp_ai_score ) . '<small>/100</small></div>';
			$html .= '</div>';
			$html .= '</div>';

			// Stats grid
			$html .= '<div class="wsa-sec-title"><span>📊</span> Delivery Statistics</div>';
			$html .= '<div class="wsa-stats-grid">';
			$html .= $this->stat_card( '📦', 'Total Parcels', $fp_total, 'neutral' );
			$html .= $this->stat_card( '✅', 'Delivered', $fp_delivered, 'success' );
			$html .= $this->stat_card( '❌', 'Cancelled', $fp_cancelled, ( (int) $fp_cancelled > 0 ? 'danger' : 'success' ) );
			$html .= $this->stat_card( '↩️', 'Returned', $fp_returned, ( (int) $fp_returned > 0 ? 'warning' : 'success' ) );
			$html .= $this->stat_card( '📈', 'Delivery Rate', $fp_delivery_rate . '%', ( (float) $fp_delivery_rate >= 80 ? 'success' : ( (float) $fp_delivery_rate >= 50 ? 'warning' : 'danger' ) ) );
			$html .= $this->stat_card( '📉', 'Return Rate', $fp_return_rate . '%', ( (float) $fp_return_rate <= 10 ? 'success' : ( (float) $fp_return_rate <= 30 ? 'warning' : 'danger' ) ) );
			$html .= $this->stat_card( '🚨', 'Fraud Alerts', $fp_fraud_alerts, ( (int) $fp_fraud_alerts > 0 ? 'danger' : 'success' ) );
			$html .= $this->stat_card( '📝', 'Reports', $fp_reports, ( (int) $fp_reports > 0 ? 'warning' : 'success' ) );
			$html .= $this->stat_card( '🚚', 'Couriers', $fp_sources, 'neutral' );
			$html .= '</div>';

			// AI Summary
			if ( $fp_ai_summary ) {
				$html .= '<div class="wsa-ai-card">';
				$html .= '<div class="wsa-ai-hdr"><span>🤖</span> AI Analysis</div>';
				$html .= '<div class="wsa-ai-txt">' . esc_html( $fp_ai_summary ) . '</div>';
				$html .= '</div>';
			}

			// Risk Message
			if ( $fp_risk_msg ) {
				$html .= '<div class="wsa-risk-msg wsa-rmsg-' . esc_attr( $fp_cls ) . '">';
				$html .= '<span class="wsa-rmsg-ico">💡</span> ' . esc_html( $fp_risk_msg );
				$html .= '</div>';
			}

			// Courier table
			$fp_couriers = $fp_couriers_raw ? json_decode( $fp_couriers_raw, true ) : [];
			if ( ! empty( $fp_couriers ) ) {
				$html .= '<div class="wsa-sec-title"><span>🚛</span> Courier Breakdown</div>';
				$html .= '<div class="wsa-tbl-wrap">';
				$html .= '<table class="wsa-tbl">';
				$html .= '<thead><tr><th>Courier</th><th>Total</th><th>Delivered</th><th>Cancelled</th><th>Delivery%</th><th>Fraud</th><th>Segment</th></tr></thead><tbody>';

				foreach ( $fp_couriers as $id_key => $c ) {
					$tc = (int) ( $c['total_parcels'] ?? 0 );
					$dr = (float) ( $c['delivery_rate'] ?? 0 );
					$rc = '';
					if ( $tc === 0 ) { $rc = 'wsa-tr-dim'; }
					elseif ( $dr < 60 ) { $rc = 'wsa-tr-warn'; }

					$seg = $c['customer_segment'] ?? ( $tc === 0 ? 'No Data' : '—' );
					$seg_cls = 'wsa-seg-def';
					if ( stripos( $seg, 'Normal' ) !== false ) { $seg_cls = 'wsa-seg-ok'; }
					elseif ( stripos( $seg, 'New' ) !== false ) { $seg_cls = 'wsa-seg-new'; }
					elseif ( stripos( $seg, 'No Data' ) !== false ) { $seg_cls = 'wsa-seg-na'; }

					$html .= '<tr class="' . esc_attr( $rc ) . '">';
					$html .= '<td class="wsa-td-name">' . esc_html( $c['courier'] ?? $id_key ) . '</td>';
					$html .= '<td>' . esc_html( $tc ) . '</td>';
					$html .= '<td>' . esc_html( $c['delivered'] ?? 0 ) . '</td>';
					$html .= '<td>' . esc_html( $c['cancelled'] ?? 0 ) . '</td>';
					$html .= '<td>';
					if ( $tc > 0 ) {
						$pill_cls = $dr >= 80 ? 'wsa-pill-ok' : ( $dr >= 50 ? 'wsa-pill-mid' : 'wsa-pill-bad' );
						$html .= '<span class="wsa-pill ' . $pill_cls . '">' . esc_html( $dr ) . '%</span>';
					} else {
						$html .= '<span class="wsa-pill wsa-pill-na">—</span>';
					}
					$html .= '</td>';
					$html .= '<td>' . esc_html( $c['fraud_count'] ?? 0 ) . '</td>';
					$html .= '<td><span class="wsa-seg ' . esc_attr( $seg_cls ) . '">' . esc_html( $seg ) . '</span></td>';
					$html .= '</tr>';
				}
				$html .= '</tbody></table></div>';
			}

			// Footer
			if ( $fp_fetched_at ) {
				$html .= '<div class="wsa-fp-foot">';
				$html .= '🚚 Courier Intelligence · ' . esc_html( $fp_sources ) . ' couriers aggregated';
				$html .= '<span>' . esc_html( $fp_fetched_at ) . '</span>';
				$html .= '</div>';
			}

		} else {
			// Empty state
			$html .= '<div class="wsa-fp-empty">';
			$html .= '<div class="wsa-fp-empty-ico">🔍</div>';
			$html .= '<h4>No Courier Intelligence Data Yet</h4>';
			$html .= '<p>Click <strong>Refresh</strong> above to fetch multi-courier intelligence.</p>';
			$html .= '</div>';
		}

		// ── Risk Signals ────────────────────────────────────────────────────
		if ( ! empty( $signals['negative'] ) || ! empty( $signals['positive'] ) ) {
			$html .= '<div class="wsa-sec-title"><span>📋</span> Risk Signals</div>';

			if ( ! empty( $signals['negative'] ) ) {
				$html .= '<div class="wsa-sigs wsa-sigs-neg">';
				foreach ( $signals['negative'] as $sig ) {
					$html .= '<div class="wsa-sig"><span class="wsa-sig-dot wsa-dot-neg"></span>' . esc_html( $sig ) . '</div>';
				}
				$html .= '</div>';
			}

			if ( ! empty( $signals['positive'] ) ) {
				$html .= '<div class="wsa-sigs wsa-sigs-pos">';
				foreach ( $signals['positive'] as $sig ) {
					$html .= '<div class="wsa-sig"><span class="wsa-sig-dot wsa-dot-pos"></span>' . esc_html( $sig ) . '</div>';
				}
				$html .= '</div>';
			}
		}

		$html .= '</div>'; // .wsa-m-body
		$html .= '</div>'; // .wsa-risk-details-modal

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Render a single stat card
	 */
	private function stat_card( $icon, $label, $value, $variant = 'neutral' ) {
		return '<div class="wsa-st wsa-st-' . esc_attr( $variant ) . '">'
			. '<div class="wsa-st-ico">' . $icon . '</div>'
			. '<div class="wsa-st-val">' . esc_html( $value ) . '</div>'
			. '<div class="wsa-st-lbl">' . esc_html( $label ) . '</div>'
			. '</div>';
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
				     stripos( $note->content, 'redx' ) !== false ||
				     stripos( $note->content, 'carrybee' ) !== false ||
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
	 * Optimized to prevent 500 errors on live servers
	 */
	public function ajax_recalculate_risk() {
		check_ajax_referer( 'wsa_risk_details', 'nonce' );

		try {
			$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

			if ( ! $order_id ) {
				throw new \Exception( 'Invalid order ID' );
			}

			$order = wc_get_order( $order_id );
			
			// Guard: don't process refund objects (they have no billing_phone)
			if ( ! $order ) {
				throw new \Exception( 'Order not found' );
			}
			if ( $order instanceof \WC_Order_Refund
				|| ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
				// Resolve to parent order automatically
				$parent_id = $order->get_parent_id();
				if ( ! $parent_id ) {
					throw new \Exception( 'This is a refund object, not a real order' );
				}
				$order_id = $parent_id;
				$order    = wc_get_order( $order_id );
				if ( ! $order ) {
					throw new \Exception( 'Parent order not found' );
				}
			}

			// 1. Recalculate risk score for the TARGET order first (with fresh API data)
			require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/RiskScorer.php';
			$scorer = new \WooSmartAutomation\Modules\FakeCustomerDetection\RiskScorer();
			$result = $scorer->calculate_score( $order_id, true ); // bypass_cache = true
			
			if ( ! $result ) {
				throw new \Exception( 'Failed to calculate risk score (API error)' );
			}

			// Store result in target order meta immediately
			update_post_meta( $order_id, '_wsa_risk_score',   $result['score'] );
			update_post_meta( $order_id, '_wsa_risk_signals', wp_json_encode( $result['signals'] ) );
			update_post_meta( $order_id, '_wsa_risk_summary', $result['summary'] );
			update_post_meta( $order_id, '_wsa_risk_status',  'completed' );
			update_post_meta( $order_id, '_wsa_risk_calculated_at', current_time( 'mysql' ) );

			// 2. Identify other orders by same customer to SYNC (not recalculate)
			$customer_id = $order->get_customer_id();
			$email       = $order->get_billing_email();
			$phone       = $order->get_billing_phone();

			$other_order_ids = [];

			// Base query args
			$query_args = [
				'limit'   => 50, // Safety limit for live servers
				'exclude' => [ $order_id ],
				'return'  => 'ids',
			];

			if ( $customer_id > 0 ) {
				$query_args['customer_id'] = $customer_id;
				$res = wc_get_orders( $query_args );
				if ( is_array( $res ) ) $other_order_ids = array_merge( $other_order_ids, $res );
			} elseif ( ! empty( $email ) ) {
				$query_args['billing_email'] = $email;
				$res = wc_get_orders( $query_args );
				if ( is_array( $res ) ) $other_order_ids = array_merge( $other_order_ids, $res );
			}

			// Match by phone using meta_query for maximum compatibility across environments
			if ( ! empty( $phone ) ) {
				$phone_args = [
					'limit'   => 50,
					'exclude' => array_merge( [ $order_id ], $other_order_ids ),
					'return'  => 'ids',
					'meta_query' => [
						[
							'key'     => '_billing_phone',
							'value'   => $phone,
							'compare' => '=',
						],
					],
				];
				$res = wc_get_orders( $phone_args );
				if ( is_array( $res ) ) $other_order_ids = array_merge( $other_order_ids, $res );
			}

			$other_order_ids = array_unique( $other_order_ids );

			// 3. Sync FraudPeek intelligence to related orders (Efficiently)
			if ( ! empty( $other_order_ids ) ) {
				// Fetch FraudPeek keys once to avoid redundant reads in loop
				$fp_meta_keys = [
					'_wsa_fp_available', '_wsa_fp_risk_score', '_wsa_fp_ai_risk_score',
					'_wsa_fp_risk_level', '_wsa_fp_risk_message', '_wsa_fp_ai_summary',
					'_wsa_fp_total_parcels', '_wsa_fp_delivered', '_wsa_fp_cancelled',
					'_wsa_fp_returned', '_wsa_fp_delivery_rate', '_wsa_fp_return_rate',
					'_wsa_fp_fraud_alerts', '_wsa_fp_report_count', '_wsa_fp_courier_sources',
					'_wsa_fp_couriers', '_wsa_fp_data_source', '_wsa_fp_fetched_at',
					'_wsa_courier_total_orders', '_wsa_courier_delivered', '_wsa_courier_cancelled',
					'_wsa_courier_success_rate', '_wsa_courier_data_source',
				];

				$sync_data = [];
				foreach ( $fp_meta_keys as $key ) {
					$val = get_post_meta( $order_id, $key, true );
					if ( $val !== '' && $val !== false ) {
						$sync_data[ $key ] = $val;
					}
				}

				foreach ( $other_order_ids as $other_oid ) {
					foreach ( $sync_data as $key => $val ) {
						update_post_meta( $other_oid, $key, $val );
					}
					// Also ensure they show as calculated
					update_post_meta( $other_oid, '_wsa_risk_status', 'completed' );
				}
			}

			wp_send_json_success( [ 
				'message' => sprintf( 'Intelligence refreshed for #%d and synced to %d other orders.', $order_id, count( $other_order_ids ) ),
				'score'   => $result['score'],
				'level'   => $this->get_risk_level( $result['score'] ),
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}
}
