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

		// FraudPeek data for cell overrides
		$fp_available  = get_post_meta( $order_id, '_wsa_fp_available', true );
		$fp_risk_score = get_post_meta( $order_id, '_wsa_fp_risk_score', true );
		$fp_risk_level = get_post_meta( $order_id, '_wsa_fp_risk_level', true );

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

		// Use FraudPeek score if available
		$display_score = ( $fp_available === '1' ) ? $fp_risk_score : $score;
		$risk_level    = ( $fp_available === '1' && ! empty( $fp_risk_level ) ) ? ucfirst( $fp_risk_level ) : $this->get_risk_level( (int) $display_score );
		
		// Map text level to class for visual consistency
		$risk_class = $this->get_risk_class( (int) $display_score );
		if ( ! empty( $risk_level ) ) {
			$lvl = strtolower( $risk_level );
			if ( strpos( $lvl, 'very low' ) !== false ) $risk_class = 'very-low';
			elseif ( strpos( $lvl, 'low' ) !== false ) $risk_class = 'low';
			elseif ( strpos( $lvl, 'medium' ) !== false ) $risk_class = 'medium';
			elseif ( strpos( $lvl, 'high' ) !== false ) $risk_class = 'high';
			elseif ( strpos( $lvl, 'critical' ) !== false ) $risk_class = 'critical';
		}

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

		?>
		<div class="wsa-risk-container" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<!-- Progress Bar -->
			<div class="wsa-risk-progress-bar wsa-view-details"
				 data-order-id="<?php echo esc_attr( $order_id ); ?>"
				 title="Click to view full details">
				<div class="wsa-progress-fill wsa-risk-<?php echo esc_attr( $risk_class ); ?>" style="width: <?php echo esc_attr( $display_score ); ?>%;">
					<span class="wsa-score-label"><?php echo esc_html( $display_score ); ?></span>
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
						<span class="wsa-tooltip-score"><?php echo esc_html( $display_score ); ?>/100</span>
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
		if ( $score >= 90 ) {
			return 'very-low';
		} elseif ( $score >= 75 ) {
			return 'low';
		} elseif ( $score >= 50 ) {
			return 'medium';
		} elseif ( $score >= 25 ) {
			return 'high';
		} else {
			return 'critical';
		}
	}

	/**
	 * Get risk level text (5 levels)
	 */
	private function get_risk_level( $score ) {
		if ( $score >= 90 ) {
			return __( 'Very Low Risk', 'woo-smart-automation' );
		} elseif ( $score >= 75 ) {
			return __( 'Low Risk', 'woo-smart-automation' );
		} elseif ( $score >= 50 ) {
			return __( 'Medium Risk', 'woo-smart-automation' );
		} elseif ( $score >= 25 ) {
			return __( 'High Risk', 'woo-smart-automation' );
		} else {
			return __( 'Critical Risk', 'woo-smart-automation' );
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
		if ( ! $order_id ) wp_send_json_error( [ 'message' => 'Invalid order ID' ] );

		$order = wc_get_order( $order_id );
		if ( ! $order ) wp_send_json_error( [ 'message' => 'Order not found' ] );

		$score          = (int) get_post_meta( $order_id, '_wsa_risk_score', true );
		$signals        = json_decode( get_post_meta( $order_id, '_wsa_risk_signals', true ), true ) ?: [];
		$calculated_at  = get_post_meta( $order_id, '_wsa_risk_calculated_at', true );
		$risk_class     = $this->get_risk_class( $score );

		// Packzy Data
		$pz_count     = get_post_meta( $order_id, '_wsa_packzy_count', true );
		$pz_delivered = get_post_meta( $order_id, '_wsa_packzy_delivered', true );
		$pz_cancelled = get_post_meta( $order_id, '_wsa_packzy_cancelled', true );
		$pz_reports   = json_decode( get_post_meta( $order_id, '_wsa_packzy_reports', true ), true ) ?: [];
		$pz_error     = get_post_meta( $order_id, '_wsa_packzy_error', true );

		// FraudPeek Data
		$fp_available     = get_post_meta( $order_id, '_wsa_fp_available', true );
		$fp_risk_score    = get_post_meta( $order_id, '_wsa_fp_risk_score', true );
		$fp_ai_summary    = get_post_meta( $order_id, '_wsa_fp_ai_summary', true );
		$fp_total         = get_post_meta( $order_id, '_wsa_fp_total_parcels', true );
		$fp_delivered     = get_post_meta( $order_id, '_wsa_fp_delivered', true );
		$fp_cancelled     = get_post_meta( $order_id, '_wsa_fp_cancelled', true );
		$fp_couriers_raw  = get_post_meta( $order_id, '_wsa_fp_couriers', true );
		$fp_couriers      = $fp_couriers_raw ? json_decode( $fp_couriers_raw, true ) : [];

		$display_score = ( $fp_available === '1' ) ? (int) $fp_risk_score : $score;
		$risk_level    = ( $fp_available === '1' && ! empty( $fp_risk_level ) ) ? ucfirst( $fp_risk_level ) : $this->get_risk_level( (int) $display_score );
		
		$risk_class = $this->get_risk_class( (int) $display_score );
		if ( ! empty( $risk_level ) ) {
			$lvl = strtolower( $risk_level );
			if ( strpos( $lvl, 'very low' ) !== false ) $risk_class = 'very-low';
			elseif ( strpos( $lvl, 'low' ) !== false ) $risk_class = 'low';
			elseif ( strpos( $lvl, 'medium' ) !== false ) $risk_class = 'medium';
			elseif ( strpos( $lvl, 'high' ) !== false ) $risk_class = 'high';
			elseif ( strpos( $lvl, 'critical' ) !== false ) $risk_class = 'critical';
		}
		
		$html = '<div class="wsa-risk-details-modal wsa-v3 wsa-unified">';
		
		// ── Header ──
		$html .= '<div class="wsa-modal-header header-' . esc_attr( $risk_class ) . '">';
		$html .= '<div class="header-main"><h3>Identity Intelligence</h3><p>#' . $order->get_order_number() . ' &middot; ' . esc_html($order->get_billing_phone()) . '</p></div>';
		$html .= '<div class="header-score"><div class="score-pill">' . $display_score . '<span>Trust</span></div><span class="wsa-modal-close">&times;</span></div>';
		$html .= '</div>';

		$html .= '<div class="wsa-modal-content single-view">';
		
		if ( $pz_error ) {
			$html .= '<div class="wsa-alert warning"><strong>Rate Limit:</strong> ' . esc_html( $pz_error ) . '</div>';
		}

		// ── Global Stats Row (4 Colorful Cards) ──
		$total_p    = (int)$fp_total + (int)$pz_count;
		$total_d    = (int)$fp_delivered + (int)$pz_delivered;
		$total_r    = (int)$fp_returned; // Packzy doesn't explicitly return returned vs cancelled, so using FP returned
		$total_f    = count($pz_reports);

		$html .= '<div class="stats-grid grid-4">';
		$html .= '<div class="stat-box primary"><strong>' . $total_p . '</strong><span>Total Parcels</span></div>';
		$html .= '<div class="stat-box success"><strong>' . $total_d . '</strong><span>Delivered</span></div>';
		$html .= '<div class="stat-box warning"><strong>' . $total_r . '</strong><span>Returns</span></div>';
		$html .= '<div class="stat-box danger"><strong>' . $total_f . '</strong><span>Fraud Logs</span></div>';
		$html .= '</div>';

		// ── Courier Table ──
		$html .= '<div class="section-title">National Courier Intelligence</div>';
		$html .= '<div class="table-container"><table class="wsa-minimal-table">';
		$html .= '<thead><tr><th>Courier</th><th>Parcels</th><th>Delivered / Fail</th><th>Success Rate</th></tr></thead><tbody>';
		
		// Major Couriers to always show
		$major_couriers = [
			'Steadfast' => [ 'source' => 'pz', 'label' => 'Steadfast' ],
			'Pathao'    => [ 'source' => 'fp', 'label' => 'Pathao' ],
			'RedX'      => [ 'source' => 'fp', 'label' => 'RedX' ],
			'CarryBee'  => [ 'source' => 'fp', 'label' => 'CarryBee' ],
		];

		foreach ( $major_couriers as $key => $conf ) {
			$label = $conf['label'];
			$total = 0; $del = 0; $can = 0; $rate = '-';

			if ( $conf['source'] === 'pz' ) {
				if ( $pz_error ) {
					$html .= '<tr><td><strong>' . $label . '</strong></td><td colspan="3"><span class="wsa-badge-error">⚠️ Rate Limited</span></td></tr>';
					continue;
				}
				$total = (int)$pz_count;
				$del   = (int)$pz_delivered;
				$can   = (int)$pz_cancelled;
				$rate  = ($total > 0) ? round(($del/$total)*100) . '%' : '0%';
			} else {
				// Find in FP couriers
				$found = false;
				if ( ! empty( $fp_couriers ) ) {
					foreach ( $fp_couriers as $c ) {
						if ( stripos( $c['courier'], $key ) !== false ) {
							$total = (int)($c['total_parcels'] ?? 0);
							$del   = (int)($c['delivered'] ?? $c['delivered_parcels'] ?? 0);
							$can   = (int)($c['cancelled'] ?? $c['cancelled_parcels'] ?? 0);
							$rate  = round($c['delivery_rate'] ?? 0) . '%';
							$found = true;
							break;
						}
					}
				}
				if ( ! $found && empty($fp_couriers) && $fp_available !== '1' ) {
					$rate = 'Checking...';
				} elseif ( ! $found ) {
					$rate = '0%';
				}
			}

			$html .= '<tr>';
			$html .= '<td><strong>' . $label . '</strong></td>';
			$html .= '<td><span class="count-pill">' . $total . '</span></td>';
			$html .= '<td><span class="txt-success">' . $del . '</span> / <span class="txt-danger">' . $can . '</span></td>';
			$html .= '<td><span class="rate-badge">' . $rate . '</span></td>';
			$html .= '</tr>';
		}
		
		// Add other couriers from FP that are not in major list
		if ( ! empty( $fp_couriers ) ) {
			foreach ( $fp_couriers as $c ) {
				$is_major = false;
				foreach ($major_couriers as $mkey => $mval) {
					if ( stripos($c['courier'], $mkey) !== false ) { $is_major = true; break; }
				}
				if ( $is_major ) continue;

				$total_c = $c['total_parcels'] ?? 0;
				if ( $total_c == 0 ) continue;
				$del_c = ($c['delivered'] ?? $c['delivered_parcels'] ?? 0);
				$can_c = ($c['cancelled'] ?? $c['cancelled_parcels'] ?? 0);
				$rate_c = round($c['delivery_rate']) . '%';

				$html .= '<tr><td>' . esc_html($c['courier']) . '</td><td><span class="count-pill">' . $total_c . '</span></td>';
				$html .= '<td><span class="txt-success">' . $del_c . '</span> / <span class="txt-danger">' . $can_c . '</span></td>';
				$html .= '<td><span class="rate-badge">' . $rate_c . '</span></td></tr>';
			}
		}

		$html .= '</tbody></table></div>';

		// ── Signals ──
		$html .= '<div class="section-title">Risk Signals</div>';
		$html .= '<div class="signals-compact">';
		foreach ( ($signals['negative'] ?? []) as $sig ) {
			$html .= '<div class="sig-min neg"><span></span>' . esc_html( $sig ) . '</div>';
		}
		foreach ( ($signals['positive'] ?? []) as $sig ) {
			$html .= '<div class="sig-min pos"><span></span>' . esc_html( $sig ) . '</div>';
		}
		$html .= '</div>';

		// ── Fraud Reports ──
		if ( ! empty( $pz_reports ) ) {
			$html .= '<div class="section-title">Fraud Reports (Stealth)</div>';
			foreach ( $pz_reports as $report ) {
				$html .= '<div class="report-box"><strong>' . esc_html( $report['name'] ?: 'Report' ) . ':</strong> ' . esc_html( $report['details'] ) . '</div>';
			}
		}

		$html .= '</div>'; // content

		$html .= '<div class="wsa-modal-footer">';
		$html .= '<div class="footer-info">Last check: ' . ( $calculated_at ? human_time_diff( strtotime( $calculated_at ), current_time( 'timestamp' ) ) . ' ago' : 'Never' ) . '</div>';
		$html .= '<button class="wsa-recalculate-btn modern" data-order-id="' . $order_id . '">Recalculate Score</button>';
		$html .= '</div>';

		$html .= '</div>';

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
