<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Risk Scoring Engine
 */
class RiskScorer {

	/**
	 * @var bool
	 */
	private $bypass_cache = false;

	/**
	 * Calculate risk score for an order
	 * 
	 * @param int $order_id WooCommerce Order ID
	 * @param bool $bypass_cache Bypass cache for fresh API data
	 * @return array|false Array with score, signals, and summary
	 */
	public function calculate_score( $order_id, $bypass_cache = false ) {
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			return false;
		}

		$score = 50; // Start at neutral
		$negative_signals = [];
		$positive_signals = [];

		// Get order data
		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();
		$customer_id = $order->get_customer_id();
		$ip_address = $order->get_customer_ip_address();

		// Get customer order history
		$customer_orders = $this->get_customer_orders( $email, $customer_id, $order_id );

		// Store bypass_cache for use in check methods
		$this->bypass_cache = $bypass_cache;

		// ❌ NEGATIVE SIGNALS
		
		// 1. Invalid Phone Format
		if ( ! $this->is_valid_phone( $phone ) ) {
			$score += 20;
			$negative_signals[] = 'Invalid phone number format detected';
		}

		// 2. Cancelled/Failed Orders
		$cancelled_count = $this->count_orders_by_status( $customer_orders, [ 'cancelled', 'failed' ] );
		if ( $cancelled_count > 0 ) {
			$points = min( $cancelled_count * 15, 60 );
			$score += $points;
			$negative_signals[] = sprintf( '%d previous cancelled/failed orders found', $cancelled_count );
		}

		// 3. Courier Returns (refunded or specific courier return statuses)
		$return_count = $this->count_courier_returns( $customer_orders );
		if ( $return_count > 0 ) {
			$score += ( $return_count * 30 );
			$negative_signals[] = sprintf( '%d courier returns found (customer refused)', $return_count );
		}

		// 4. IP Reuse on Failures
		if ( $ip_address ) {
			$ip_failures = $this->count_ip_failures( $ip_address, $order_id );
			if ( $ip_failures >= 2 ) {
				$score += 25;
				$negative_signals[] = sprintf( 'Same IP used for %d failed orders', $ip_failures );
			}
		}

		// 5. High Cancellation Rate
		$total_orders = count( $customer_orders );
		if ( $total_orders >= 3 ) {
			$cancellation_rate = $cancelled_count / $total_orders;
			if ( $cancellation_rate > 0.5 ) {
				$score += 20;
				$negative_signals[] = sprintf( 'High cancellation rate: %d%%', round( $cancellation_rate * 100 ) );
			}
		}

		// 6. Duplicate Order Detection
		$duplicate_data = $this->check_for_duplicate_order( $order );
		if ( $duplicate_data ) {
			$score += 40; // High penalty for duplicates
			$negative_signals[] = sprintf( 'Potential duplicate of order #%d found (placed within 1 hour)', $duplicate_data );
			update_post_meta( $order_id, '_wsa_is_potential_duplicate', $duplicate_data );
		} else {
			delete_post_meta( $order_id, '_wsa_is_potential_duplicate' );
		}

		// 7. Identity Mismatch (Billing vs Shipping)
		if ( $this->has_identity_mismatch( $order ) ) {
			$score += 15;
			$negative_signals[] = 'Billing and Shipping identity mismatch detected';
		}

		// 8. Address Quality Check
		if ( $this->is_suspicious_address( $order->get_billing_address_1() ) ) {
			$score += 25;
			$negative_signals[] = 'Suspicious or junk character pattern in address';
		}

		// 9. Night Shift Order (1AM - 5AM)
		if ( $this->is_night_shift_order( $order ) ) {
			$score += 10;
			$negative_signals[] = 'Order placed during high-risk hours (1AM - 5AM)';
		}

		// ✅ POSITIVE SIGNALS
		
		// 1. Successful Deliveries
		$completed_count = $this->count_orders_by_status( $customer_orders, [ 'completed' ] );
		if ( $completed_count > 0 ) {
			$score -= ( $completed_count * 20 );
			$positive_signals[] = sprintf( '%d successful deliveries found', $completed_count );
		}

		// 2. Stable History (active for > 6 months with successful orders)
		if ( $this->has_stable_history( $customer_orders, $completed_count ) ) {
			$score -= 15;
			$positive_signals[] = 'Customer has stable 6+ month history';
		}

		// 3. Verified Phone (phone used in completed order)
		if ( $this->is_verified_phone( $phone, $customer_orders ) ) {
			$score -= 10;
			$positive_signals[] = 'Phone number previously verified in completed order';
		}

		// 4. Low Return Rate
		if ( $return_count === 0 && $total_orders > 0 ) {
			$score -= 10;
			$positive_signals[] = 'No courier returns found';
		}

		// 🆕 5. FraudPeek Multi-Courier Score (Cross-Merchant Data)
		$courier_check = $this->check_fraudpeek_score( $order );
		$score += $courier_check['score'];
		
		// Add courier signals to appropriate array
		if ( ! empty( $courier_check['signals'] ) ) {
			foreach ( $courier_check['signals'] as $signal ) {
				if ( $courier_check['score'] < 0 ) {
					$positive_signals[] = $signal;
				} else {
					$negative_signals[] = $signal;
				}
			}
		}

		// Clamp score between 0 and 100
		$score = max( 0, min( 100, $score ) );

		// Generate summary
		$summary = $this->generate_summary( $score, $negative_signals, $positive_signals );

		return [
			'score'   => $score,
			'signals' => [
				'negative' => $negative_signals,
				'positive' => $positive_signals,
			],
			'summary' => $summary,
		];
	}

	/**
	 * Get customer's previous orders (excluding current order)
	 */
	private function get_customer_orders( $email, $customer_id, $exclude_order_id ) {
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

		return wc_get_orders( $args );
	}

	/**
	 * Count orders by status
	 */
	private function count_orders_by_status( $order_ids, $statuses ) {
		$count = 0;
		
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && in_array( $order->get_status(), $statuses, true ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count courier returns (refunded status or order notes mentioning return)
	 */
	private function count_courier_returns( $order_ids ) {
		$count = 0;
		
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			
			if ( ! $order ) {
				continue;
			}

			// Check if refunded
			if ( $order->get_status() === 'refunded' ) {
				$count++;
				continue;
			}

			// Check order notes for courier return keywords
			$notes = wc_get_order_notes( [ 'order_id' => $order_id ] );
			foreach ( $notes as $note ) {
				if ( stripos( $note->content, 'returned' ) !== false || 
				     stripos( $note->content, 'customer refused' ) !== false ) {
					$count++;
					break;
				}
			}
		}

		return $count;
	}

	/**
	 * Count failed orders from the same IP
	 */
	private function count_ip_failures( $ip_address, $exclude_order_id ) {
		global $wpdb;

		$statuses = [ 'wc-cancelled', 'wc-failed' ];
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}posts p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ($placeholders)
			AND pm.meta_key = '_customer_ip_address'
			AND pm.meta_value = %s
			AND p.ID != %d",
			array_merge( $statuses, [ $ip_address, $exclude_order_id ] )
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Check if phone number is valid
	 */
	private function is_valid_phone( $phone ) {
		// Remove common non-digit characters
		$cleaned = preg_replace( '/[^0-9]/', '', $phone );
		
		// Check if it has at least 10 digits and max 15
		return strlen( $cleaned ) >= 10 && strlen( $cleaned ) <= 15;
	}

	/**
	 * Check if customer has stable history (6+ months, with completed orders)
	 */
	private function has_stable_history( $order_ids, $completed_count ) {
		if ( $completed_count === 0 || empty( $order_ids ) ) {
			return false;
		}

		// Get oldest order date
		$oldest_order_id = end( $order_ids );
		$oldest_order = wc_get_order( $oldest_order_id );

		if ( ! $oldest_order ) {
			return false;
		}

		$oldest_date = $oldest_order->get_date_created();
		$six_months_ago = new \DateTime( '-6 months' );

		return $oldest_date < $six_months_ago;
	}

	/**
	 * Check if phone was used in a completed order
	 */
	private function is_verified_phone( $phone, $order_ids ) {
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			
			if ( $order && 
			     $order->get_status() === 'completed' && 
			     $order->get_billing_phone() === $phone ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate human-readable summary
	 */
	private function generate_summary( $score, $negative_signals, $positive_signals ) {
		$risk_level = $this->get_risk_level( $score );
		$summary = sprintf( '<strong>%s Risk (Score: %d)</strong><br>', $risk_level, $score );

		if ( ! empty( $negative_signals ) ) {
			$summary .= '<span class="wsa-negative-signals">';
			foreach ( array_slice( $negative_signals, 0, 3 ) as $signal ) {
				$summary .= '❌ ' . esc_html( $signal ) . '<br>';
			}
			$summary .= '</span>';
		}

		if ( ! empty( $positive_signals ) ) {
			$summary .= '<span class="wsa-positive-signals">';
			foreach ( array_slice( $positive_signals, 0, 2 ) as $signal ) {
				$summary .= '✅ ' . esc_html( $signal ) . '<br>';
			}
			$summary .= '</span>';
		}

		return $summary;
	}

	/**
	 * Get risk level text based on score
	 */
	private function get_risk_level( $score ) {
		if ( $score <= 20 ) {
			return 'Very Low';
		} elseif ( $score <= 40 ) {
			return 'Low';
		} elseif ( $score <= 60 ) {
			return 'Medium';
		} elseif ( $score <= 80 ) {
			return 'High';
		} else {
			return 'Critical';
		}
	}

	/**
	 * Check for duplicate orders within a 1-hour window
	 */
	private function check_for_duplicate_order( $order ) {
		$args = [
			'limit'         => 1,
			'exclude'       => [ $order->get_id() ],
			'billing_phone' => $order->get_billing_phone(),
			'date_created'  => '>' . ( time() - HOUR_IN_SECONDS ),
			'return'        => 'ids',
		];

		$duplicates = wc_get_orders( $args );

		if ( ! empty( $duplicates ) ) {
			return $duplicates[0];
		}

		return false;
	}

	/**
	 * Check if billing and shipping info differ significantly
	 */
	private function has_identity_mismatch( $order ) {
		$b_first = strtolower( trim( $order->get_billing_first_name() ) );
		$s_first = strtolower( trim( $order->get_shipping_first_name() ) );

		if ( empty( $s_first ) ) {
			return false; // Local pickup or digital
		}

		// Simple check for name differences
		if ( $b_first !== $s_first ) {
			return true;
		}

		return false;
	}

	/**
	 * Check for suspicious patterns in address (junk characters)
	 */
	private function is_suspicious_address( $address ) {
		if ( strlen( $address ) < 5 ) {
			return true;
		}

		// Check for repeating characters (e.g. asdfghjkl or aaaaaa)
		if ( preg_match( '/(.)\1{4,}/', $address ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if order was placed during high-risk hours (1AM - 5AM)
	 */
	private function is_night_shift_order( $order ) {
		$hour = (int) $order->get_date_created()->date( 'H' );
		return ( $hour >= 1 && $hour <= 5 );
	}

	/**
	 * Check FraudPeek multi-courier fraud score (Cross-Merchant Data)
	 *
	 * Uses the FraudPeek API which aggregates delivery history from multiple
	 * couriers (Steadfast, Pathao, RedX, CarryBee, etc.) and applies AI-powered
	 * risk scoring. This replaces the old single-courier Steadfast approach.
	 *
	 * @param \WC_Order $order The order object
	 * @return array Array with 'score' (int, can be negative) and 'signals' (string[])
	 */
	private function check_fraudpeek_score( $order ) {
		$phone        = $order->get_billing_phone();
		$order_id     = $order->get_id();
		$bypass_cache = isset( $this->bypass_cache ) ? $this->bypass_cache : false;

		// Load service
		require_once WSA_PATH . 'includes/Modules/Courier/FraudPeekService.php';
		$service = new \WooSmartAutomation\Modules\Courier\FraudPeekService();

		$fp = $service->get_fraud_data( $phone, $bypass_cache );

		if ( ! $fp ) {
			// API unavailable — store empty marker and return neutral
			update_post_meta( $order_id, '_wsa_fp_available', '0' );
			return [ 'score' => 0, 'signals' => [] ];
		}

		// ── Persist all FraudPeek fields as order meta (for admin display) ──
		update_post_meta( $order_id, '_wsa_fp_available',           '1' );
		update_post_meta( $order_id, '_wsa_fp_risk_score',          $fp['risk_score'] );
		update_post_meta( $order_id, '_wsa_fp_ai_risk_score',       $fp['ai_risk_score'] );
		update_post_meta( $order_id, '_wsa_fp_risk_level',          $fp['risk_level'] );
		update_post_meta( $order_id, '_wsa_fp_risk_message',        $fp['risk_message'] );
		update_post_meta( $order_id, '_wsa_fp_ai_summary',          $fp['ai_summary'] );
		update_post_meta( $order_id, '_wsa_fp_total_parcels',       $fp['total_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_delivered',           $fp['delivered_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_cancelled',           $fp['cancelled_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_returned',            $fp['returned_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_delivery_rate',       $fp['average_delivery_rate'] );
		update_post_meta( $order_id, '_wsa_fp_return_rate',         $fp['average_return_rate'] );
		update_post_meta( $order_id, '_wsa_fp_fraud_alerts',        $fp['fraud_alerts'] );
		update_post_meta( $order_id, '_wsa_fp_report_count',        $fp['report_count'] );
		update_post_meta( $order_id, '_wsa_fp_courier_sources',     $fp['courier_sources'] );
		update_post_meta( $order_id, '_wsa_fp_couriers',            wp_json_encode( $fp['couriers'] ) );
		update_post_meta( $order_id, '_wsa_fp_data_source',         $fp['data_source'] );
		update_post_meta( $order_id, '_wsa_fp_fetched_at',          $fp['fetched_at'] );

		// ── Legacy meta (used by existing admin column display) ──
		update_post_meta( $order_id, '_wsa_courier_total_orders',   $fp['total_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_delivered',      $fp['delivered_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_cancelled',      $fp['cancelled_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_success_rate',   $fp['average_delivery_rate'] );
		update_post_meta( $order_id, '_wsa_courier_data_source',    'fraudpeek' );

		// ── Risk scoring based on FraudPeek data ──
		$score   = 0;
		$signals = [];

		$total         = $fp['total_parcels'];
		$delivery_rate = $fp['average_delivery_rate'];  // 0–100
		$return_rate   = $fp['average_return_rate'];    // 0–100
		$fraud_alerts  = $fp['fraud_alerts'];
		$report_count  = $fp['report_count'];
		$cancelled     = $fp['cancelled_parcels'];
		$returned      = $fp['returned_parcels'];
		$ai_score      = $fp['ai_risk_score'];          // 0–100 (higher = safer)
		$risk_level    = strtolower( $fp['risk_level'] );
		$courier_count = $fp['courier_sources'];

		// ── 1. FraudPeek AI score — primary signal ──────────────────────────
		// FraudPeek's ai_risk_score 100 = perfectly safe, 0 = very dangerous.
		// We convert it to our scale where higher = more risky.
		if ( $total === 0 ) {
			// No delivery history found across any courier
			$score += 5;
			$signals[] = 'No delivery history found across any courier (new customer)';
		} elseif ( $ai_score >= 90 ) {
			$score -= 30;
			$signals[] = sprintf( 'FraudPeek AI: Trusted customer (score %d/100)', $ai_score );
		} elseif ( $ai_score >= 75 ) {
			$score -= 15;
			$signals[] = sprintf( 'FraudPeek AI: Low risk customer (score %d/100)', $ai_score );
		} elseif ( $ai_score >= 50 ) {
			$score += 10;
			$signals[] = sprintf( 'FraudPeek AI: Moderate risk (score %d/100)', $ai_score );
		} elseif ( $ai_score >= 30 ) {
			$score += 30;
			$signals[] = sprintf( 'FraudPeek AI: High risk customer (score %d/100)', $ai_score );
		} else {
			$score += 50;
			$signals[] = sprintf( 'FraudPeek AI: Critical risk customer (score %d/100)', $ai_score );
		}

		// ── 2. Fraud Alerts (direct fraud reports) ───────────────────────────
		if ( $fraud_alerts > 0 ) {
			$penalty = min( $fraud_alerts * 20, 60 );
			$score  += $penalty;
			$signals[] = sprintf( '%d fraud alert(s) on record across couriers', $fraud_alerts );
		}

		// ── 3. Community Reports ─────────────────────────────────────────────
		if ( $report_count > 0 ) {
			$score += min( $report_count * 10, 30 );
			$signals[] = sprintf( '%d fraud report(s) submitted by merchants', $report_count );
		}

		// ── 4. Delivery Rate ─────────────────────────────────────────────────
		if ( $total > 0 ) {
			if ( $delivery_rate >= 95 ) {
				$score   -= 20;
				$signals[] = sprintf( 'Excellent delivery rate: %.0f%% across %d couriers', $delivery_rate, $courier_count );
			} elseif ( $delivery_rate >= 80 ) {
				$score   -= 10;
				$signals[] = sprintf( 'Good delivery rate: %.0f%%', $delivery_rate );
			} elseif ( $delivery_rate >= 60 ) {
				$score   += 10;
				$signals[] = sprintf( 'Average delivery rate: %.0f%%', $delivery_rate );
			} elseif ( $delivery_rate >= 40 ) {
				$score   += 25;
				$signals[] = sprintf( 'Low delivery rate: %.0f%% — high return risk', $delivery_rate );
			} else {
				$score   += 45;
				$signals[] = sprintf( 'Very low delivery rate: %.0f%% — very high return risk', $delivery_rate );
			}
		}

		// ── 5. Return Rate ───────────────────────────────────────────────────
		if ( $return_rate > 20 ) {
			$score   += 20;
			$signals[] = sprintf( 'High return rate: %.0f%%', $return_rate );
		} elseif ( $return_rate > 10 ) {
			$score   += 10;
			$signals[] = sprintf( 'Elevated return rate: %.0f%%', $return_rate );
		} elseif ( $return_rate === 0.0 && $total >= 3 ) {
			$score   -= 10;
			$signals[] = 'Zero return rate — customer accepts all deliveries';
		}

		// ── 6. Cancellations ─────────────────────────────────────────────────
		if ( $cancelled > 10 ) {
			$score   += 25;
			$signals[] = sprintf( '%d parcels cancelled across all couriers', $cancelled );
		} elseif ( $cancelled > 4 ) {
			$score   += 12;
			$signals[] = sprintf( '%d parcels cancelled across all couriers', $cancelled );
		}

		// ── 7. Returned parcels ──────────────────────────────────────────────
		if ( $returned > 5 ) {
			$score   += 20;
			$signals[] = sprintf( '%d parcels returned across couriers', $returned );
		} elseif ( $returned > 2 ) {
			$score   += 10;
			$signals[] = sprintf( '%d parcels returned across couriers', $returned );
		}

		// ── 8. Volume + Trust bonus ───────────────────────────────────────────
		if ( $total >= 30 && $delivery_rate >= 90 ) {
			$score   -= 15;
			$signals[] = sprintf( 'Highly trusted: %d total parcels, %.0f%% delivery rate', $total, $delivery_rate );
		} elseif ( $total >= 10 && $delivery_rate >= 85 ) {
			$score   -= 8;
			$signals[] = sprintf( 'Trusted customer: %d parcels, %.0f%% delivery rate', $total, $delivery_rate );
		}

		// ── 9. Multi-courier coverage ─────────────────────────────────────────
		if ( $courier_count >= 3 ) {
			$signals[] = sprintf( 'Data verified across %d different couriers', $courier_count );
		}

		return [
			'score'   => $score,
			'signals' => $signals,
		];
	}
}
