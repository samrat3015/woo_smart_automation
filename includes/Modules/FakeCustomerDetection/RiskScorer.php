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
	/**
	 * Check that an order is a real WC_Order (not a WC_Order_Refund).
	 * Refund objects don't have billing methods and will cause fatal errors.
	 *
	 * @param mixed $order
	 * @return bool
	 */
	private function is_real_order( $order ) {
		if ( ! $order ) {
			return false;
		}
		// Refund objects are a subclass — they have no billing phone/email
		if ( $order instanceof \WC_Order_Refund ) {
			return false;
		}
		// Double-check using get_type() which is available on all order objects
		if ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) {
			return false;
		}
		return true;
	}

	public function calculate_score( $order_id, $bypass_cache = false ) {
		$order = wc_get_order( $order_id );
		
		if ( ! $this->is_real_order( $order ) ) {
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

		// Get customer order history:
		// $identity_orders — only orders matched by customer_id OR email (reliable identity)
		// $phone_orders   — orders matched by phone number only (for phone verification check)
		// We intentionally keep them SEPARATE to avoid falsely attributing other
		// guest customers' cancellations/completions when they share the same phone.
		$identity_orders = $this->get_orders_by_identity( $email, $customer_id, $order_id );
		$phone_orders    = $this->get_orders_by_phone( $phone, $order_id, $identity_orders );
		// Combined set only used for full de-duplication purposes (e.g., duplicate detection)
		$customer_orders = array_unique( array_merge( $identity_orders, $phone_orders ) );

		// Store bypass_cache for use in check methods
		$this->bypass_cache = $bypass_cache;

		// ❌ NEGATIVE SIGNALS
		
		// 1. Invalid Phone Format
		if ( ! $this->is_valid_phone( $phone ) ) {
			$score += 20;
			$negative_signals[] = 'Invalid phone number format detected';
		}

		// 2. Cancelled/Failed Orders (on your website)
		// IMPORTANT: Only count identity-verified orders (by customer_id / email).
		// Phone-matched orders may belong to different people sharing the same number.
		$cancelled_count = $this->count_orders_by_status( $identity_orders, [ 'cancelled', 'failed' ] );
		if ( $cancelled_count > 0 ) {
			$points = min( $cancelled_count * 10, 40 );
			$score += $points;
			$negative_signals[] = sprintf( '%d previous cancelled/failed orders on this store', $cancelled_count );
		}

		// 3. Courier Returns (refunded or specific courier return statuses)
		// Only on identity-verified orders for the same reason.
		$return_count = $this->count_courier_returns( $identity_orders );
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

		// 5. High Cancellation Rate (identity-verified orders only)
		$total_identity_orders = count( $identity_orders );
		if ( $total_identity_orders >= 5 ) {
			$cancellation_rate = $cancelled_count / $total_identity_orders;
			if ( $cancellation_rate > 0.7 ) {
				$score += 15;
				$negative_signals[] = sprintf( 'Very high store cancellation rate: %d%%', round( $cancellation_rate * 100 ) );
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

		// 1. Successful Deliveries (identity-verified orders only)
		$completed_count = $this->count_orders_by_status( $identity_orders, [ 'completed' ] );
		if ( $completed_count > 0 ) {
			$score -= min( $completed_count * 20, 60 ); // cap at -60
			$positive_signals[] = sprintf( '%d successful order(s) on this store', $completed_count );
		}

		// 2. Stable History (identity-verified orders, active for > 6 months)
		if ( $this->has_stable_history( $identity_orders, $completed_count ) ) {
			$score -= 15;
			$positive_signals[] = 'Customer has stable 6+ month history on this store';
		}

		// 3. Verified Phone:
		// Check identity orders first (stronger signal), then phone orders as fallback
		if ( $this->is_verified_phone( $phone, $identity_orders ) || $this->is_verified_phone( $phone, $phone_orders ) ) {
			$score -= 10;
			$positive_signals[] = 'Phone number verified in a previous completed order';
		}

		// 4. Low Return Rate (identity-verified orders)
		if ( $return_count === 0 && $total_identity_orders > 0 ) {
			$score -= 10;
			$positive_signals[] = 'No courier returns on this store';
		}

		// 5. Courier Intelligence (Multi-courier Cross-Merchant Data)
		$courier_check = $this->check_courier_intelligence( $order );
		$score += $courier_check['score'];

		// Merge courier signals into correct positive/negative buckets
		foreach ( $courier_check['positive_signals'] as $sig ) {
			$positive_signals[] = $sig;
		}
		foreach ( $courier_check['negative_signals'] as $sig ) {
			$negative_signals[] = $sig;
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
	 * Matches by Customer ID, Email, or Phone Number
	 */
	/**
	 * Get orders matched by the customer's registered identity (customer_id or email).
	 * These are authoritative — they definitely belong to this customer.
	 *
	 * @param string $email
	 * @param int    $customer_id
	 * @param int    $exclude_order_id
	 * @return int[]
	 */
	private function get_orders_by_identity( $email, $customer_id, $exclude_order_id ) {
		$args = [
			'limit'   => -1,
			'exclude' => [ $exclude_order_id ],
			'return'  => 'ids',
			'type'    => 'shop_order',
		];

		if ( $customer_id > 0 ) {
			$args['customer_id'] = $customer_id;
		} elseif ( ! empty( $email ) ) {
			$args['billing_email'] = $email;
		} else {
			// No reliable identity — return empty
			return [];
		}

		$ids = wc_get_orders( $args );
		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Get orders matched by phone number ONLY (not overlapping with identity orders).
	 * Phone is NOT a unique identifier — multiple people can share a number.
	 * Only use this for phone-verification checks, NOT for history signals.
	 *
	 * @param string $phone
	 * @param int    $exclude_order_id
	 * @param int[]  $already_found IDs already found via identity — exclude from this set
	 * @return int[]
	 */
	private function get_orders_by_phone( $phone, $exclude_order_id, $already_found = [] ) {
		if ( empty( $phone ) ) {
			return [];
		}

		$exclude = array_merge( [ $exclude_order_id ], $already_found );

		$phone_args = [
			'limit'         => -1,
			'exclude'       => $exclude,
			'billing_phone' => $phone,
			'return'        => 'ids',
			'type'          => 'shop_order',
		];

		$ids = wc_get_orders( $phone_args );
		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Count orders by status
	 */
	private function count_orders_by_status( $order_ids, $statuses ) {
		$count = 0;
		
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $this->is_real_order( $order ) && in_array( $order->get_status(), $statuses, true ) ) {
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
			
			if ( ! $this->is_real_order( $order ) ) {
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
			
			if ( $this->is_real_order( $order ) && 
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
	 * Check multi-courier intelligence score (Cross-Merchant Data)
	 *
	 * Aggregates delivery history from multiple couriers and applies AI-powered
	 * risk scoring. Returns separate positive and negative signal arrays.
	 *
	 * @param \WC_Order $order The order object
	 * @return array Array with 'score', 'positive_signals', 'negative_signals'
	 */
	private function check_courier_intelligence( $order ) {
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
			return [ 'score' => 0, 'positive_signals' => [], 'negative_signals' => [] ];
		}

		// ── Persist all fields as order meta (for admin display) ──
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

		// Legacy meta
		update_post_meta( $order_id, '_wsa_courier_total_orders',   $fp['total_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_delivered',      $fp['delivered_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_cancelled',      $fp['cancelled_parcels'] );
		update_post_meta( $order_id, '_wsa_courier_success_rate',   $fp['average_delivery_rate'] );
		update_post_meta( $order_id, '_wsa_courier_data_source',    'courier_intelligence' );

		// ── Risk scoring ──
		$score            = 0;
		$positive_signals = [];
		$negative_signals = [];

		$total         = $fp['total_parcels'];
		$delivery_rate = $fp['average_delivery_rate'];  // 0–100
		$return_rate   = $fp['average_return_rate'];    // 0–100
		$fraud_alerts  = $fp['fraud_alerts'];
		$report_count  = $fp['report_count'];
		$cancelled     = $fp['cancelled_parcels'];
		$returned      = $fp['returned_parcels'];
		$ai_score      = $fp['ai_risk_score'];          // 0–100 (higher = safer)
		$courier_count = $fp['courier_sources'];

		// ── 1. AI score — primary signal ─────────────────────────────────────
		if ( $total === 0 ) {
			$score += 5;
			$negative_signals[] = 'No delivery history found across any courier (new customer)';
		} elseif ( $ai_score >= 90 ) {
			$score -= 40;
			$positive_signals[] = sprintf( 'AI Score: Highly trusted customer (%d/100)', $ai_score );
		} elseif ( $ai_score >= 75 ) {
			$score -= 25;
			$positive_signals[] = sprintf( 'AI Score: Low risk customer (%d/100)', $ai_score );
		} elseif ( $ai_score >= 50 ) {
			$score += 10;
			$negative_signals[] = sprintf( 'AI Score: Moderate risk (%d/100)', $ai_score );
		} elseif ( $ai_score >= 30 ) {
			$score += 30;
			$negative_signals[] = sprintf( 'AI Score: High risk customer (%d/100)', $ai_score );
		} else {
			$score += 50;
			$negative_signals[] = sprintf( 'AI Score: Critical risk customer (%d/100)', $ai_score );
		}

		// ── 2. Fraud Alerts ───────────────────────────────────────────────────
		if ( $fraud_alerts > 0 ) {
			$penalty = min( $fraud_alerts * 20, 60 );
			$score  += $penalty;
			$negative_signals[] = sprintf( '%d fraud alert(s) on record across couriers', $fraud_alerts );
		}

		// ── 3. Community Reports ──────────────────────────────────────────────
		if ( $report_count > 0 ) {
			$score += min( $report_count * 10, 30 );
			$negative_signals[] = sprintf( '%d fraud report(s) submitted by merchants', $report_count );
		}

		// ── 4. Delivery Rate ──────────────────────────────────────────────────
		if ( $total > 0 ) {
			if ( $delivery_rate >= 95 ) {
				$score -= 20;
				$positive_signals[] = sprintf( 'Excellent delivery rate: %.0f%% across %d couriers', $delivery_rate, $courier_count );
			} elseif ( $delivery_rate >= 80 ) {
				$score -= 10;
				$positive_signals[] = sprintf( 'Good delivery rate: %.0f%%', $delivery_rate );
			} elseif ( $delivery_rate >= 60 ) {
				$score += 10;
				$negative_signals[] = sprintf( 'Average delivery rate: %.0f%%', $delivery_rate );
			} elseif ( $delivery_rate >= 40 ) {
				$score += 25;
				$negative_signals[] = sprintf( 'Low delivery rate: %.0f%% — high return risk', $delivery_rate );
			} else {
				$score += 45;
				$negative_signals[] = sprintf( 'Very low delivery rate: %.0f%% — very high return risk', $delivery_rate );
			}
		}

		// ── 5. Return Rate ────────────────────────────────────────────────────
		if ( $return_rate > 20 ) {
			$score += 20;
			$negative_signals[] = sprintf( 'High return rate: %.0f%%', $return_rate );
		} elseif ( $return_rate > 10 ) {
			$score += 10;
			$negative_signals[] = sprintf( 'Elevated return rate: %.0f%%', $return_rate );
		} elseif ( $return_rate === 0.0 && $total >= 3 ) {
			$score -= 10;
			$positive_signals[] = 'Zero return rate — customer accepts all deliveries';
		}

		// ── 6. Cancellations ──────────────────────────────────────────────────
		if ( $cancelled > 10 ) {
			$score += 25;
			$negative_signals[] = sprintf( '%d parcels cancelled across all couriers', $cancelled );
		} elseif ( $cancelled > 4 ) {
			$score += 12;
			$negative_signals[] = sprintf( '%d parcels cancelled across all couriers', $cancelled );
		}

		// ── 7. Returned parcels ───────────────────────────────────────────────
		if ( $returned > 5 ) {
			$score += 20;
			$negative_signals[] = sprintf( '%d parcels returned across couriers', $returned );
		} elseif ( $returned > 2 ) {
			$score += 10;
			$negative_signals[] = sprintf( '%d parcels returned across couriers', $returned );
		}

		// ── 8. Volume + Trust bonus ───────────────────────────────────────────
		if ( $total >= 30 && $delivery_rate >= 90 ) {
			$score -= 15;
			$positive_signals[] = sprintf( 'Highly trusted: %d total parcels, %.0f%% delivery rate', $total, $delivery_rate );
		} elseif ( $total >= 10 && $delivery_rate >= 85 ) {
			$score -= 8;
			$positive_signals[] = sprintf( 'Trusted customer: %d parcels, %.0f%% delivery rate', $total, $delivery_rate );
		}

		// ── 9. Multi-courier coverage ─────────────────────────────────────────
		if ( $courier_count >= 3 ) {
			$positive_signals[] = sprintf( 'Data verified across %d different couriers', $courier_count );
		}

		return [
			'score'            => $score,
			'positive_signals' => $positive_signals,
			'negative_signals' => $negative_signals,
		];
	}
}
