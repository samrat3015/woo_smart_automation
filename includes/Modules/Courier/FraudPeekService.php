<?php
namespace WooSmartAutomation\Modules\Courier;

/**
 * FraudPeek API Service
 *
 * Calls https://fraudpeek.com/api/fraud-lookup to get comprehensive
 * multi-courier delivery and fraud data for a customer phone number.
 *
 * Features:
 * - Hardcoded credentials (no admin UI needed)
 * - 12-hour transient cache (matches FraudPeek cache_ttl_hours)
 * - Permanent DB storage for historical reference
 */
class FraudPeekService {

	/** Hardcoded API credentials */
	private const CLIENT_ID = 'fp_00000I';
	private const API_KEY   = '112276e3383a2c998c454873ebfc614261e6cbc3bb0ad20b6c02e9bff5a0b97b';
	private const API_URL   = 'https://fraudpeek.com/api/fraud-lookup';

	/** Cache duration: 12 hours (aligns with FraudPeek cache_ttl_hours) */
	private const CACHE_TTL = 43200;

	/** @var string */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'woo_smart_courier_scores';
	}

	/**
	 * Get fraud data for a phone number.
	 *
	 * Returns a normalized array on success, or false on failure.
	 *
	 * @param string $phone       Raw phone number (will be cleaned)
	 * @param bool   $bypass_cache Skip cache and DB; force fresh API call
	 * @return array|false
	 */
	public function get_fraud_data( $phone, $bypass_cache = false ) {
		$cleaned_phone = $this->clean_phone( $phone );

		if ( ! $cleaned_phone ) {
			error_log( 'WSA FraudPeek: Invalid phone number: ' . $phone );
			return false;
		}

		// 1. Try DB (permanent storage, up to 12 h old)
		if ( ! $bypass_cache ) {
			$db_result = $this->get_from_db( $cleaned_phone );
			if ( $db_result ) {
				return $db_result;
			}
		}

		// 2. Try transient cache
		$cache_key = 'wsa_fp_' . md5( $cleaned_phone );
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// 3. Fetch from API
		$raw = $this->fetch_from_api( $cleaned_phone );
		if ( ! $raw ) {
			return false;
		}

		// 4. Normalize
		$normalized = $this->normalize( $raw, $cleaned_phone );

		// 5. Store in transient + DB
		set_transient( $cache_key, $normalized, self::CACHE_TTL );
		$this->save_to_db( $cleaned_phone, $normalized );

		return $normalized;
	}

	/**
	 * Clear cache for a phone number (force fresh fetch next time).
	 *
	 * @param string $phone
	 */
	public function clear_cache( $phone ) {
		$cleaned = $this->clean_phone( $phone );
		if ( $cleaned ) {
			delete_transient( 'wsa_fp_' . md5( $cleaned ) );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Call the FraudPeek API.
	 *
	 * @param  string $phone Cleaned phone number
	 * @return array|false   Decoded JSON body or false on error
	 */
	private function fetch_from_api( $phone ) {
		$response = wp_remote_post( self::API_URL, [
			'timeout' => 30,
			'headers' => [
				'X-FP-Client-Id' => self::CLIENT_ID,
				'X-FP-API-Key'   => self::API_KEY,
				'Accept'         => 'application/json',
			],
			'body' => [
				'phone' => $phone,
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'WSA FraudPeek API error: ' . $response->get_error_message() );
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code !== 200 || ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['data'] ) ) {
			error_log( 'WSA FraudPeek API error (' . $http_code . '): ' . substr( $body, 0, 300 ) );
			return false;
		}

		return $data['data'];
	}

	/**
	 * Normalize the raw FraudPeek data into a flat, consistent array.
	 *
	 * @param  array  $data          Raw data from API (data key)
	 * @param  string $cleaned_phone Cleaned phone
	 * @return array
	 */
	private function normalize( $data, $cleaned_phone ) {
		$summary = $data['summary'] ?? [];
		$couriers = $data['couriers'] ?? [];
		$reports  = $data['reports']  ?? [];
		$errors   = $data['errors']   ?? [];

		// Build per-courier breakdown
		$courier_breakdown = [];
		foreach ( $couriers as $c ) {
			$identifier = $c['identifier'] ?? $c['courier'] ?? 'unknown';
			$courier_breakdown[ $identifier ] = [
				'courier'          => $c['courier']          ?? $identifier,
				'total_parcels'    => (int)   ( $c['total_parcels']    ?? 0 ),
				'delivered'        => (int)   ( $c['delivered_parcels'] ?? 0 ),
				'cancelled'        => (int)   ( $c['cancelled_parcels'] ?? 0 ),
				'delivery_rate'    => (float) ( $c['delivery_rate']    ?? 0 ),
				'return_rate'      => (float) ( $c['return_rate']      ?? 0 ),
				'fraud_count'      => (int)   ( $c['fraud_count']      ?? 0 ),
				'customer_segment' => $c['customer_segment'] ?? null,
				'message'          => $c['message']          ?? null,
			];
		}

		return [
			// Summary fields
			'phone'                => $cleaned_phone,
			'risk_score'           => (int)   ( $summary['risk_score']           ?? 50 ),
			'ai_risk_score'        => (int)   ( $summary['ai_risk_score']         ?? 50 ),
			'risk_level'           => (string) ( $summary['risk_level']           ?? 'unknown' ),
			'risk_message'         => (string) ( $summary['risk_message']         ?? '' ),
			'ai_summary'           => (string) ( $summary['ai_summary']           ?? '' ),
			'total_parcels'        => (int)   ( $summary['total_parcels']         ?? 0 ),
			'delivered_parcels'    => (int)   ( $summary['delivered_parcels']     ?? 0 ),
			'cancelled_parcels'    => (int)   ( $summary['cancelled_parcels']     ?? 0 ),
			'returned_parcels'     => (int)   ( $summary['returned_parcels']      ?? 0 ),
			'average_delivery_rate'=> (float) ( $summary['average_delivery_rate'] ?? 0 ),
			'average_return_rate'  => (float) ( $summary['average_return_rate']   ?? 0 ),
			'fraud_alerts'         => (int)   ( $summary['fraud_alerts']          ?? 0 ),
			'report_count'         => (int)   ( $summary['report_count']          ?? 0 ),
			'comment_count'        => (int)   ( $summary['comment_count']         ?? 0 ),
			'courier_sources'      => (int)   ( $summary['courier_sources']       ?? 0 ),
			'last_reported_at'     => $summary['last_reported_at'] ?? null,
			'last_fraud_at'        => $summary['last_fraud_at']    ?? null,
			'last_lookup_at'       => $summary['last_lookup_at']   ?? null,
			// Per-courier breakdown
			'couriers'             => $courier_breakdown,
			// Raw reports
			'reports'              => $reports,
			// Errors from API (partial failures for individual couriers)
			'api_errors'           => $errors,
			// Source info
			'data_source'          => 'fraudpeek',
			'fetched_at'           => current_time( 'mysql' ),
		];
	}

	/**
	 * Clean a raw phone number to the local Bangladesh format (01xxxxxxxxx).
	 * Accepts: +8801XXXXXXXXX, 8801XXXXXXXXX, 01XXXXXXXXX, 1XXXXXXXXX (10 digits)
	 *
	 * @param  string $phone
	 * @return string|false Returns the cleaned phone on success, false on failure
	 */
	private function clean_phone( $phone ) {
		if ( empty( $phone ) ) {
			return false;
		}

		// Strip everything except digits
		$digits = preg_replace( '/[^0-9]/', '', $phone );

		// Strip Bangladesh country code prefix (880 or 0880)
		if ( substr( $digits, 0, 4 ) === '0880' ) {
			$digits = substr( $digits, 4 );
		} elseif ( substr( $digits, 0, 3 ) === '880' ) {
			$digits = substr( $digits, 3 );
		}

		// If 10 digits and starts with 1 (e.g. 1XXXXXXXXX), prepend 0
		if ( strlen( $digits ) === 10 && $digits[0] === '1' ) {
			$digits = '0' . $digits;
		}

		// Must be 11 digits now
		if ( strlen( $digits ) !== 11 ) {
			error_log( 'WSA FraudPeek: Could not clean phone "' . $phone . '" -> "' . $digits . '" (len ' . strlen( $digits ) . ')' );
			return false;
		}

		return $digits;
	}

	/**
	 * Retrieve from DB if data is fresh (< 12 h old).
	 *
	 * @param  string $phone Cleaned phone
	 * @return array|false
	 */
	private function get_from_db( $phone ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE phone = %s ORDER BY last_checked DESC LIMIT 1",
				$phone
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		$age_seconds = time() - strtotime( $row['last_checked'] );
		if ( $age_seconds > self::CACHE_TTL ) {
			return false;
		}

		// Decode stored JSON columns
		$couriers = json_decode( $row['couriers_json'] ?? '{}', true ) ?: [];
		$reports  = json_decode( $row['reports_json']  ?? '[]', true ) ?: [];

		return [
			'phone'                 => $row['phone'],
			'risk_score'            => (int)   $row['risk_score'],
			'ai_risk_score'         => (int)   $row['ai_risk_score'],
			'risk_level'            => (string) $row['risk_level'],
			'risk_message'          => (string) $row['risk_message'],
			'ai_summary'            => (string) $row['ai_summary'],
			'total_parcels'         => (int)   $row['total_parcels'],
			'delivered_parcels'     => (int)   $row['delivered_parcels'],
			'cancelled_parcels'     => (int)   $row['cancelled_parcels'],
			'returned_parcels'      => (int)   $row['returned_parcels'],
			'average_delivery_rate' => (float) $row['average_delivery_rate'],
			'average_return_rate'   => (float) $row['average_return_rate'],
			'fraud_alerts'          => (int)   $row['fraud_alerts'],
			'report_count'          => (int)   $row['report_count'],
			'comment_count'         => (int)   $row['comment_count'],
			'courier_sources'       => (int)   $row['courier_sources'],
			'last_reported_at'      => $row['last_reported_at'],
			'last_fraud_at'         => $row['last_fraud_at'],
			'last_lookup_at'        => $row['last_lookup_at'],
			'couriers'              => $couriers,
			'reports'               => $reports,
			'api_errors'            => [],
			'data_source'           => 'fraudpeek_db',
			'fetched_at'            => $row['last_checked'],
		];
	}

	/**
	 * Persist normalized data to DB (upsert on phone).
	 *
	 * @param string $phone
	 * @param array  $data Normalized data
	 */
	private function save_to_db( $phone, $data ) {
		global $wpdb;

		$result = $wpdb->replace(
			$this->table_name,
			[
				'phone'                 => $phone,
				'risk_score'            => $data['risk_score'],
				'ai_risk_score'         => $data['ai_risk_score'],
				'risk_level'            => $data['risk_level'],
				'risk_message'          => $data['risk_message'],
				'ai_summary'            => $data['ai_summary'],
				'total_parcels'         => $data['total_parcels'],
				'delivered_parcels'     => $data['delivered_parcels'],
				'cancelled_parcels'     => $data['cancelled_parcels'],
				'returned_parcels'      => $data['returned_parcels'],
				'average_delivery_rate' => $data['average_delivery_rate'],
				'average_return_rate'   => $data['average_return_rate'],
				'fraud_alerts'          => $data['fraud_alerts'],
				'report_count'          => $data['report_count'],
				'comment_count'         => $data['comment_count'],
				'courier_sources'       => $data['courier_sources'],
				'last_reported_at'      => $data['last_reported_at'],
				'last_fraud_at'         => $data['last_fraud_at'],
				'last_lookup_at'        => $data['last_lookup_at'],
				'couriers_json'         => wp_json_encode( $data['couriers'] ),
				'reports_json'          => wp_json_encode( $data['reports'] ),
				'data_source'           => 'fraudpeek',
				'last_checked'          => current_time( 'mysql' ),
			],
			[
				'%s', '%d', '%d', '%s', '%s', '%s',
				'%d', '%d', '%d', '%d',
				'%f', '%f',
				'%d', '%d', '%d', '%d',
				'%s', '%s', '%s',
				'%s', '%s',
				'%s', '%s',
			]
		);

		if ( false === $result ) {
			error_log( 'WSA FraudPeek DB save FAILED for ' . $phone . ': ' . $wpdb->last_error );
		}
	}
}
