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

		// 🆕 5. SteadFast Courier Score (Cross-Merchant Data)
		$courier_check = $this->check_steadfast_courier_score( $order );
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
		if ( $score <= 30 ) {
			return 'Low';
		} elseif ( $score <= 70 ) {
			return 'Medium';
		} else {
			return 'High';
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
	 * Check SteadFast courier success rate (Cross-Merchant Data)
	 * 
	 * @param WC_Order $order The order object
	 * @return array Array with score and signals
	 */
	private function check_steadfast_courier_score( $order ) {
		$phone = $order->get_billing_phone();
		
		// Initialize SteadFast API service
		require_once WSA_PATH . 'includes/Modules/Courier/SteadfastAPIService.php';
		$api_service = new \WooSmartAutomation\Modules\Courier\SteadfastAPIService();
		
		// Use bypass_cache if set (for recalculation)
		$bypass_cache = isset( $this->bypass_cache ) ? $this->bypass_cache : false;
		
		// ✅ Pass order total and customer ID for smart filtering
		$order_total = (float) $order->get_total();
		$customer_id = $order->get_customer_id();
		
		$courier_data = $api_service->get_customer_courier_score( $phone, $bypass_cache, $order_total, $customer_id );
		
		// If API failed or disabled, return neutral
		if ( ! $courier_data ) {
			return [
				'score'   => 0,
				'signals' => []
			];
		}
		
		$score = 0;
		$signals = [];
		
		$total_parcels = isset( $courier_data['total_parcels'] ) ? (int) $courier_data['total_parcels'] : 0;
		$total_delivered = isset( $courier_data['total_delivered'] ) ? (int) $courier_data['total_delivered'] : 0;
		$total_cancelled = isset( $courier_data['total_cancelled'] ) ? (int) $courier_data['total_cancelled'] : 0;
		
		// Store data for admin display
		update_post_meta( $order->get_id(), '_wsa_courier_total_orders', $total_parcels );
		update_post_meta( $order->get_id(), '_wsa_courier_delivered', $total_delivered );
		update_post_meta( $order->get_id(), '_wsa_courier_cancelled', $total_cancelled );
		update_post_meta( $order->get_id(), '_wsa_courier_data_source', $courier_data['data_source'] ?? 'api' );
		
		// Calculate success rate
		$success_rate = $api_service->calculate_success_rate( $total_parcels, $total_delivered );
		update_post_meta( $order->get_id(), '_wsa_courier_success_rate', $success_rate );
		
		// SCORING LOGIC
		
		// 1. New customer (no history) - slight penalty for unknown
		if ( $total_parcels === 0 ) {
			$score += 5;
			$signals[] = 'No SteadFast delivery history (new customer)';
		} else {
			// 2. Success Rate Scoring
			if ( $success_rate >= 90 ) {
				$score -= 25; // REWARD trusted customers heavily
				$signals[] = sprintf( 'Excellent courier success rate: %.1f%%', $success_rate );
			} elseif ( $success_rate >= 70 ) {
				$score -= 10; // Small reward for good customers
				$signals[] = sprintf( 'Good courier success rate: %.1f%%', $success_rate );
			} elseif ( $success_rate >= 50 ) {
				$score += 15; // Medium risk
				$signals[] = sprintf( 'Moderate courier success rate: %.1f%%', $success_rate );
			} elseif ( $success_rate >= 30 ) {
				$score += 35; // High risk
				$signals[] = sprintf( 'Low courier success rate: %.1f%%', $success_rate );
			} else {
				$score += 60; // VERY HIGH RISK
				$signals[] = sprintf( 'Very low courier success rate: %.1f%%', $success_rate );
			}
			
			// 3. Total cancelled orders penalty
			if ( $total_cancelled > 10 ) {
				$score += 30;
				$signals[] = sprintf( '%d cancelled courier deliveries found', $total_cancelled );
			} elseif ( $total_cancelled > 5 ) {
				$score += 15;
				$signals[] = sprintf( '%d cancelled courier deliveries found', $total_cancelled );
			} elseif ( $total_cancelled > 2 ) {
				$score += 8;
				$signals[] = sprintf( '%d cancelled courier deliveries found', $total_cancelled );
			}
			
			// 4. Cancellation ratio
			if ( $total_parcels >= 3 ) {
				$cancel_ratio = ( $total_cancelled / $total_parcels ) * 100;
				if ( $cancel_ratio > 50 ) {
					$score += 25;
					$signals[] = sprintf( 'High cancellation ratio: %.0f%%', $cancel_ratio );
				}
			}
			
			// 5. Volume trust factor (high volume + good rate = very trusted)
			if ( $total_parcels >= 20 && $success_rate >= 85 ) {
				$score -= 15;
				$signals[] = sprintf( 'Trusted customer: %d orders with %.1f%% success', $total_parcels, $success_rate );
			}
		}
		
		return [
			'score'   => $score,
			'signals' => $signals
		];
	}
}
