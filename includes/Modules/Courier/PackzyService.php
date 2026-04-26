<?php
namespace WooSmartAutomation\Modules\Courier;

/**
 * Packzy API Service
 * 
 * Fetches additional courier intelligence (specifically Steadfast) from 
 * portal.packzy.com API.
 */
class PackzyService {

	private $api_key;
	private $secret_key;
	private $api_url = 'https://portal.packzy.com/api/v1/fraud_check/';

	public function __construct() {
		$this->api_key    = get_option( 'wsa_packzy_api_key' );
		$this->secret_key = get_option( 'wsa_packzy_secret_key' );
	}

	/**
	 * Fetch fraud data from Packzy
	 *
	 * @param string $phone
	 * @return array|false
	 */
	public function get_fraud_data( $phone, $bypass_cache = false ) {
		if ( empty( $this->api_key ) || empty( $this->secret_key ) ) {
			return false;
		}

		// Clean phone to 11 digits
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		if ( strlen( $phone ) > 11 ) {
			$phone = substr( $phone, -11 );
		}

		$cache_key = 'wsa_packzy_' . md5( $phone );
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$response = wp_remote_get( $this->api_url . $phone, [
			'timeout' => 15,
			'headers' => [
				'api-key'      => $this->api_key,
				'secret-key'   => $this->secret_key,
				'content-type' => 'application/json',
				'Accept'       => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return false;
		}

		// Handle Rate Limit Error
		if ( isset( $data['error'] ) && stripos( $data['error'], 'Rate limit' ) !== false ) {
			return [
				'is_error' => true,
				'error_type' => 'rate_limit',
				'message' => $data['error']
			];
		}

		if ( ! isset( $data['total_parcels'] ) ) {
			return false;
		}

		// Cache for 12 hours
		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}
}
