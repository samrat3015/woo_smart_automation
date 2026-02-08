<?php
namespace WooSmartAutomation\Modules\Courier;

/**
 * SteadFast API Service for Fraud Check
 * 
 * Features:
 * - 30-day cache duration (reduced API usage)
 * - Database permanent storage
 * - Skip repeat customers
 * - Minimum order amount filtering
 */
class SteadfastAPIService {
	
	private $api_key;
	private $secret_key;
	private $cache_duration = 2592000; // 30 days (was 24 hours)
	private $table_name;
	
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'woo_smart_courier_scores';
		$this->api_key = get_option( 'wsa_steadfast_api_key', '' );
		$this->secret_key = get_option( 'wsa_steadfast_secret_key', '' );
	}
	
	/**
	 * Get customer's SteadFast delivery history
	 * 
	 * @param string $phone_number Customer phone (will be cleaned)
	 * @param bool $bypass_cache Skip cache check (for testing)
	 * @param float $order_total Order amount (for minimum check)
	 * @param int $customer_id Customer ID (to skip repeat customers)
	 * @return array|false API response or false on error
	 */
	public function get_customer_courier_score( $phone_number, $bypass_cache = false, $order_total = 0, $customer_id = 0 ) {
		
		// Check if API is enabled
		if ( ! get_option( 'wsa_steadfast_fraud_check_enabled', false ) ) {
			return false;
		}

		// ✅ FEATURE 2: Check minimum order amount
		$min_amount = (float) get_option( 'wsa_steadfast_minimum_order_amount', 1000 );
		if ( $min_amount > 0 && $order_total > 0 && $order_total < $min_amount ) {
			error_log( "WSA: Skipped courier check - Order amount {$order_total} below minimum {$min_amount}" );
			return false;
		}

		// ✅ FEATURE 3: Skip repeat customers (verified users)
		if ( get_option( 'wsa_steadfast_skip_repeat_customers', 1 ) && $customer_id > 0 ) {
			if ( $this->is_verified_customer( $customer_id ) ) {
				error_log( "WSA: Skipped courier check - Customer #{$customer_id} already verified" );
				return false;
			}
		}
		
		// Clean phone number (remove +880, spaces, dashes)
		$cleaned_phone = $this->clean_phone_number( $phone_number );
		
		if ( ! $cleaned_phone ) {
			return false;
		}

		// ✅ FEATURE 4: Check database first (permanent storage)
		if ( ! $bypass_cache ) {
			$db_result = $this->get_from_database( $cleaned_phone );
			if ( $db_result ) {
				error_log( "WSA: Courier score from database for {$cleaned_phone}" );
				return $db_result;
			}
		}
		
		// Check transient cache
		$cache_key = 'wsa_stdf_score_' . md5( $cleaned_phone );
		
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			
			if ( $cached !== false ) {
				error_log( "WSA: Courier score from cache for {$cleaned_phone}" );
				return $cached;
			}
		}

		// Fetch from API
		$data = $this->fetch_via_api( $cleaned_phone );
		
		// If API failed, return false
		if ( ! $data ) {
			return false;
		}

		// Normalize response
		if ( isset( $data['total_parcels'] ) ) {
			$data['status'] = 200;
			if ( ! isset( $data['data_source'] ) ) {
				$data['data_source'] = 'api';
			}
		}

		// ✅ FEATURE 1: Cache for 30 days (transient)
		set_transient( $cache_key, $data, $this->cache_duration );

		// ✅ FEATURE 4: Save to database permanently
		$this->save_to_database( $cleaned_phone, $data );
		
		return $data;
	}
	
	/**
	 * Clean phone number to digits only
	 */
	private function clean_phone_number( $phone ) {
		$cleaned = preg_replace( '/[^0-9]/', '', $phone );
		
		// Remove Bangladesh country code if present
		if ( substr( $cleaned, 0, 3 ) === '880' ) {
			$cleaned = substr( $cleaned, 3 );
		}
		
		// Must be 10-11 digits
		if ( strlen( $cleaned ) < 10 || strlen( $cleaned ) > 11 ) {
			return false;
		}
		
		return $cleaned;
	}
	
	/**
	 * Calculate success rate percentage
	 */
	public function calculate_success_rate( $total_parcels, $total_delivered ) {
		if ( $total_parcels == 0 ) {
			return 0;
		}
		
		return round( ( $total_delivered / $total_parcels ) * 100, 2 );
	}
	
	/**
	 * Clear cache for specific phone number
	 */
	public function clear_cache( $phone_number ) {
		$cleaned_phone = $this->clean_phone_number( $phone_number );
		if ( $cleaned_phone ) {
			delete_transient( 'wsa_stdf_score_' . md5( $cleaned_phone ) );
		}
	}

	/**
	 * ✅ FEATURE 2: Check if customer is already verified (has completed orders)
	 */
	private function is_verified_customer( $customer_id ) {
		$customer_orders = wc_get_orders( [
			'customer_id' => $customer_id,
			'status'      => [ 'wc-completed', 'wc-processing' ],
			'limit'       => 1,
		] );

		return ! empty( $customer_orders );
	}

	/**
	 * ✅ FEATURE 4: Get from database (permanent storage)
	 */
	private function get_from_database( $phone ) {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE phone = %s ORDER BY last_checked DESC LIMIT 1",
			$phone
		), ARRAY_A );

		if ( ! $result ) {
			return false;
		}

		// Check if data is older than 30 days
		$last_checked = strtotime( $result['last_checked'] );
		$age_days = ( time() - $last_checked ) / DAY_IN_SECONDS;

		if ( $age_days > 30 ) {
			error_log( "WSA: Database record for {$phone} is {$age_days} days old, refetching..." );
			return false;
		}

		// Return in API format
		return [
			'total_parcels'   => (int) $result['total_parcels'],
			'total_delivered' => (int) $result['total_delivered'],
			'total_cancelled' => (int) $result['total_cancelled'],
			'success_rate'    => (float) $result['success_rate'],
			'data_source'     => $result['data_source'],
			'status'          => 200,
		];
	}

	/**
	 * ✅ FEATURE 4: Save to database permanently
	 */
	private function save_to_database( $phone, $data ) {
		global $wpdb;

		$success_rate = $this->calculate_success_rate( 
			$data['total_parcels'] ?? 0, 
			$data['total_delivered'] ?? 0 
		);

		$wpdb->replace(
			$this->table_name,
			[
				'phone'           => $phone,
				'total_parcels'   => $data['total_parcels'] ?? 0,
				'total_delivered' => $data['total_delivered'] ?? 0,
				'total_cancelled' => $data['total_cancelled'] ?? 0,
				'success_rate'    => $success_rate,
				'data_source'     => $data['data_source'] ?? 'api',
				'last_checked'    => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%d', '%d', '%f', '%s', '%s' ]
		);
	}

	/**
	 * ✅ Fetch via official API
	 */
	private function fetch_via_api( $phone ) {
		// Validate credentials
		if ( empty( $this->api_key ) || empty( $this->secret_key ) ) {
			error_log( 'WSA: SteadFast API credentials not configured' );
			return false;
		}

		$url = 'https://portal.packzy.com/api/v1/fraud_check/' . $phone;
		
		$response = wp_remote_get( $url, [
			'headers' => [
				'content-type' => 'application/json',
				'api-key'      => sanitize_text_field( $this->api_key ),
				'secret-key'   => sanitize_text_field( $this->secret_key ),
			],
			'timeout' => 15,
		] );
		
		// Error handling
		if ( is_wp_error( $response ) ) {
			error_log( 'WSA SteadFast API Error: ' . $response->get_error_message() );
			return false;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		// Check for API errors
		if ( ! $data || ! isset( $data['total_parcels'] ) ) {
			$error_detail = isset( $data['message'] ) ? $data['message'] : 'Unknown error';
			error_log( 'WSA SteadFast API: Invalid response - ' . $error_detail );
			return false;
		}

		return $data;
	}
}
