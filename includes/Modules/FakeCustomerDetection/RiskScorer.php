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

		// ❌ NEGATIVE SIGNALS (Now Subtracting Points)
		
		// 1. Invalid Phone Format
		if ( ! $this->is_valid_phone( $phone ) ) {
			$score -= 20;
			$negative_signals[] = 'Invalid phone number format detected';
		}

		// 2. Cancelled/Failed Orders (on your website)
		$cancelled_count = $this->count_orders_by_status( $identity_orders, [ 'cancelled', 'failed' ] );
		if ( $cancelled_count > 0 ) {
			$points = min( $cancelled_count * 25, 75 ); // Increased from 10 to 25
			$score -= $points;
			$negative_signals[] = sprintf( '%d previous cancelled/failed orders on this store (High impact)', $cancelled_count );
		}

		// 3. Courier Returns
		$return_count = $this->count_courier_returns( $identity_orders );
		if ( $return_count > 0 ) {
			$score -= ( $return_count * 30 );
			$negative_signals[] = sprintf( '%d courier returns found (customer refused)', $return_count );
		}

		// 4. IP Reuse on Failures
		if ( $ip_address ) {
			$ip_failures = $this->count_ip_failures( $ip_address, $order_id );
			if ( $ip_failures >= 2 ) {
				$score -= 25;
				$negative_signals[] = sprintf( 'Same IP used for %d failed orders', $ip_failures );
			}
		}

		// 5. High Cancellation Rate
		$total_identity_orders = count( $identity_orders );
		if ( $total_identity_orders >= 5 ) {
			$cancellation_rate = $cancelled_count / $total_identity_orders;
			if ( $cancellation_rate > 0.7 ) {
				$score -= 15;
				$negative_signals[] = sprintf( 'Very high store cancellation rate: %d%%', round( $cancellation_rate * 100 ) );
			}
		}

		// 6. Duplicate Order Detection
		$duplicate_data = $this->check_for_duplicate_order( $order );
		if ( $duplicate_data ) {
			$score -= 40; 
			$negative_signals[] = sprintf( 'Potential duplicate of order #%d found (placed within 1 hour)', $duplicate_data );
			update_post_meta( $order_id, '_wsa_is_potential_duplicate', $duplicate_data );
		} else {
			delete_post_meta( $order_id, '_wsa_is_potential_duplicate' );
		}

		// 7. Identity Mismatch (Billing vs Shipping)
		if ( $this->has_identity_mismatch( $order ) ) {
			$score -= 15;
			$negative_signals[] = 'Billing and Shipping identity mismatch detected';
		}

		// 8. Address Quality Check
		if ( $this->is_suspicious_address( $order->get_billing_address_1() ) ) {
			$score -= 25;
			$negative_signals[] = 'Suspicious or junk character pattern in address';
		}

		// 9. Night Shift Order (1AM - 5AM)
		if ( $this->is_night_shift_order( $order ) ) {
			$score -= 10;
			$negative_signals[] = 'Order placed during high-risk hours (1AM - 5AM)';
		}

		// ✅ POSITIVE SIGNALS (Now Adding Points)

		// 1. Successful Deliveries
		$completed_count = $this->count_orders_by_status( $identity_orders, [ 'completed' ] );
		if ( $completed_count > 0 ) {
			$score += min( $completed_count * 20, 50 ); // cap at +50
			$positive_signals[] = sprintf( '%d successful order(s) on this store', $completed_count );
		}

		// 2. Stable History
		if ( $this->has_stable_history( $identity_orders, $completed_count ) ) {
			$score += 15;
			$positive_signals[] = 'Customer has stable 6+ month history on this store';
		}

		// 3. Verified Phone
		if ( $this->is_verified_phone( $phone, $identity_orders ) || $this->is_verified_phone( $phone, $phone_orders ) ) {
			$score += 10;
			$positive_signals[] = 'Phone number verified in a previous completed order';
		}

		// 4. Low Return Rate
		if ( $return_count === 0 && $total_identity_orders > 0 ) {
			$score += 10;
			$positive_signals[] = 'No courier returns on this store';
		}


		// 5. Courier Intelligence (Cross-Merchant Data)
		$courier_check = $this->check_courier_intelligence( $order );
		$score += $courier_check['score'];
		
		foreach ( $courier_check['positive_signals'] as $sig ) { $positive_signals[] = $sig; }
		foreach ( $courier_check['negative_signals'] as $sig ) { $negative_signals[] = $sig; }

		// 6. Packzy Intelligence (Specific Steadfast Data)
		$packzy_check = $this->check_packzy_intelligence( $order );
		$score += $packzy_check['score'];

		foreach ( $packzy_check['positive_signals'] as $sig ) { $positive_signals[] = $sig; }
		foreach ( $packzy_check['negative_signals'] as $sig ) { $negative_signals[] = $sig; }

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
		if ( $score >= 80 ) {
			return 'Very Low Risk';
		} elseif ( $score >= 60 ) {
			return 'Low Risk';
		} elseif ( $score >= 40 ) {
			return 'Medium Risk';
		} elseif ( $score >= 20 ) {
			return 'High Risk';
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
		$phone    = $order->get_billing_phone();
		$order_id = $order->get_id();

		// 1. Check local DB cache first (unless bypassing)
		if ( ! $this->bypass_cache ) {
			$existing_id_data = $this->find_recent_intelligence_by_phone( $phone, '_wsa_fp_available' );
			if ( $existing_id_data ) {
				$this->sync_intelligence_meta( $order_id, $existing_id_data['id'], 'fp' );
				
				$available = get_post_meta( $order_id, '_wsa_fp_available', true );
				if ( $available === '1' ) {
					return $this->calculate_fp_score_from_meta( $order_id );
				}
			}
		}

		// Fresh fetch
		require_once WSA_PATH . 'includes/Modules/Courier/FraudPeekService.php';
		$service = new \WooSmartAutomation\Modules\Courier\FraudPeekService();
		$fp = $service->get_fraud_data( $phone, $this->bypass_cache );

		if ( ! $fp ) {
			update_post_meta( $order_id, '_wsa_fp_available', '0' );
			return [ 'score' => 0, 'positive_signals' => [], 'negative_signals' => [] ];
		}

		// Persist all fields
		update_post_meta( $order_id, '_wsa_fp_available',           '1' );
		update_post_meta( $order_id, '_wsa_fp_risk_score',          $fp['risk_score'] );
		update_post_meta( $order_id, '_wsa_fp_ai_risk_score',       $fp['ai_risk_score'] );
		update_post_meta( $order_id, '_wsa_fp_risk_level',          $fp['risk_level'] );
		update_post_meta( $order_id, '_wsa_fp_ai_summary',          $fp['ai_summary'] );
		update_post_meta( $order_id, '_wsa_fp_total_parcels',       $fp['total_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_delivered',           $fp['delivered_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_cancelled',           $fp['cancelled_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_returned',            $fp['returned_parcels'] );
		update_post_meta( $order_id, '_wsa_fp_delivery_rate',       $fp['average_delivery_rate'] );
		update_post_meta( $order_id, '_wsa_fp_fraud_alerts',        $fp['fraud_alerts'] );
		update_post_meta( $order_id, '_wsa_fp_couriers',            wp_json_encode( $fp['couriers'] ) );
		update_post_meta( $order_id, '_wsa_fp_fetched_at',          $fp['fetched_at'] );

		return $this->calculate_fp_score_from_meta( $order_id );
	}

	private function check_packzy_intelligence( $order ) {
		$phone    = $order->get_billing_phone();
		$order_id = $order->get_id();

		// 1. Try DB cache (unless bypassing)
		if ( ! $this->bypass_cache ) {
			$existing_pz_data = $this->find_recent_intelligence_by_phone( $phone, '_wsa_packzy_count' );
			if ( $existing_pz_data ) {
				$this->sync_intelligence_meta( $order_id, $existing_pz_data['id'], 'packzy' );
				
				$count = get_post_meta( $order_id, '_wsa_packzy_count', true );
				if ( $count !== '' ) {
					return $this->calculate_pz_score_from_meta( $order_id );
				}
			}
		}

		require_once WSA_PATH . 'includes/Modules/Courier/PackzyService.php';
		$service = new \WooSmartAutomation\Modules\Courier\PackzyService();
		$data = $service->get_fraud_data( $phone, $this->bypass_cache );

		if ( ! $data ) {
			return [ 'score' => 0, 'positive_signals' => [], 'negative_signals' => [] ];
		}

		if ( isset( $data['is_error'] ) && $data['is_error'] ) {
			update_post_meta( $order_id, '_wsa_packzy_error', $data['message'] );
			return [ 'score' => 0, 'positive_signals' => [], 'negative_signals' => [ '⚠️ Couriers Intelligence (Steadfast) is currently unavailable.' ] ];
		}

		delete_post_meta( $order_id, '_wsa_packzy_error' );
		update_post_meta( $order_id, '_wsa_packzy_count',     $data['total_parcels'] );
		update_post_meta( $order_id, '_wsa_packzy_delivered', $data['total_delivered'] );
		update_post_meta( $order_id, '_wsa_packzy_cancelled', $data['total_cancelled'] );
		update_post_meta( $order_id, '_wsa_packzy_reports',   wp_json_encode( $data['total_fraud_reports'] ) );

		return $this->calculate_pz_score_from_meta( $order_id );
	}

	/**
	 * Sync meta from one order to another
	 */
	private function sync_intelligence_meta( $target_id, $source_id, $type ) {
		$keys = ( $type === 'fp' ) ? [
			'_wsa_fp_available', '_wsa_fp_risk_score', '_wsa_fp_ai_risk_score',
			'_wsa_fp_risk_level', '_wsa_fp_risk_message', '_wsa_fp_ai_summary',
			'_wsa_fp_total_parcels', '_wsa_fp_available',
			'_wsa_fp_delivered', '_wsa_fp_cancelled', '_wsa_fp_returned',
			'_wsa_fp_delivery_rate', '_wsa_fp_report_count', '_wsa_fp_courier_sources',
			'_wsa_fp_couriers', '_wsa_fp_fetched_at'
		] : [
			'_wsa_packzy_count', '_wsa_packzy_delivered', '_wsa_packzy_cancelled',
			'_wsa_packzy_reports'
		];

		foreach ( $keys as $key ) {
			$val = get_post_meta( $source_id, $key, true );
			if ( $val !== '' ) {
				update_post_meta( $target_id, $key, $val );
			}
		}
	}

	/**
	 * Find a recent order with the same phone that has intelligence data
	 */
	private function find_recent_intelligence_by_phone( $phone, $check_meta_key ) {
		if ( empty( $phone ) ) return false;

		global $wpdb;
		// Find orders with this phone number from last 24 hours that have the data
		$recent_order = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_billing_phone' AND pm1.meta_value = %s
			JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
			WHERE p.post_type = 'shop_order' 
			AND p.post_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
			ORDER BY p.ID DESC LIMIT 1",
			$phone,
			$check_meta_key
		) );

		return $recent_order ? [ 'id' => $recent_order->ID ] : false;
	}

	/**
	 * Recalculate FP score from meta (Helper for caching)
	 */
	private function calculate_fp_score_from_meta( $order_id ) {
		$ai_score = (int) get_post_meta( $order_id, '_wsa_fp_ai_risk_score', true );
		$total    = (int) get_post_meta( $order_id, '_wsa_fp_total_parcels', true );
		$alerts   = (int) get_post_meta( $order_id, '_wsa_fp_fraud_alerts', true );

		$score = 0;
		$pos = []; $neg = [];

		if ( $total === 0 ) {
			$score -= 5;
			$neg[] = 'No delivery history found across any courier (new customer)';
		} elseif ( $ai_score >= 90 ) {
			$score += 40;
			$pos[] = sprintf( 'AI Score: Highly trusted customer (%d/100)', $ai_score );
		} elseif ( $ai_score >= 75 ) {
			$score += 25;
			$pos[] = sprintf( 'AI Score: Low risk customer (%d/100)', $ai_score );
		} elseif ( $ai_score < 40 ) {
			$score -= 30;
			$neg[] = sprintf( 'AI Score: Risky customer profile (%d/100)', $ai_score );
		}

		if ( $alerts > 0 ) {
			$score -= ( $alerts * 40 );
			$neg[] = sprintf( '%d Multi-Courier Fraud alert(s) found (FraudPeek)', $alerts );
		}

		return [ 'score' => $score, 'positive_signals' => $pos, 'negative_signals' => $neg ];
	}

	/**
	 * Recalculate PZ score from meta (Helper for caching)
	 */
	private function calculate_pz_score_from_meta( $order_id ) {
		$total     = (int) get_post_meta( $order_id, '_wsa_packzy_count', true );
		$delivered = (int) get_post_meta( $order_id, '_wsa_packzy_delivered', true );
		$reports   = json_decode( get_post_meta( $order_id, '_wsa_packzy_reports', true ), true ) ?: [];

		$score = 0;
		$pos = []; $neg = [];

		if ( $total > 0 ) {
			$rate = ( $delivered / $total ) * 100;
			if ( $rate >= 90 ) {
				$score += 15;
				$pos[] = sprintf( 'Steadfast: Good delivery rate (%.0f%%)', $rate );
			} elseif ( $rate < 60 ) {
				$score -= 20;
				$neg[] = sprintf( 'Steadfast: Low delivery rate (%.0f%%)', $rate );
			}
		}

		if ( count( $reports ) > 0 ) {
			$score -= ( count( $reports ) * 50 );
			$neg[] = sprintf( '%d Fraud Report(s) found on Steadfast', count( $reports ) );
		}

		return [ 'score' => $score, 'positive_signals' => $pos, 'negative_signals' => $neg ];
	}
}
