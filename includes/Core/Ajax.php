<?php
namespace WooSmartAutomation\Core;

class Ajax {
	
	public function init() {
		// Test SteadFast API connection
		add_action( 'wp_ajax_wsa_test_steadfast_api', [ $this, 'test_steadfast_api' ] );
	}

	/**
	 * Test SteadFast API connection
	 */
	public function test_steadfast_api() {
		check_ajax_referer( 'wsa_test_api', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access' ] );
		}
		
		// Check if settings are saved
		$api_key = get_option( 'wsa_steadfast_api_key' );
		$secret_key = get_option( 'wsa_steadfast_secret_key' );
		$enabled = get_option( 'wsa_steadfast_fraud_check_enabled' );
		
		if ( empty( $api_key ) || empty( $secret_key ) ) {
			wp_send_json_error( [ 'message' => 'Please save your API credentials first before testing.' ] );
		}
		
		if ( ! $enabled ) {
			wp_send_json_error( [ 'message' => 'Please enable fraud check and save settings first.' ] );
		}
		
		require_once WSA_PATH . 'includes/Modules/Courier/SteadfastAPIService.php';
		$service = new \WooSmartAutomation\Modules\Courier\SteadfastAPIService();
		
		// Test with a dummy phone number - bypass cache for fresh test
		$result = $service->get_customer_courier_score( '01700000000', true );
		
		if ( $result && isset( $result['total_parcels'] ) ) {
			$total = $result['total_parcels'];
			$delivered = isset( $result['total_delivered'] ) ? $result['total_delivered'] : 0;
			wp_send_json_success( [ 'message' => 'API connected successfully! Test: ' . $total . ' parcels, ' . $delivered . ' delivered.' ] );
		} else {
			// Check for rate limit error
			if ( $result && isset( $result['error'] ) ) {
				$error_msg = $result['error'];
				// If it's rate limit, still consider it a success (API is working)
				if ( stripos( $error_msg, 'rate limit' ) !== false || stripos( $error_msg, 'maximum allowed' ) !== false ) {
					wp_send_json_success( [ 'message' => '✓ API credentials verified! Note: Daily limit reached. Will reset tomorrow.' ] );
				}
				wp_send_json_error( [ 'message' => 'API Error: ' . $error_msg ] );
			} elseif ( $result && isset( $result['message'] ) ) {
				$error_msg = $result['message'];
				if ( stripos( $error_msg, 'rate limit' ) !== false || stripos( $error_msg, 'maximum allowed' ) !== false ) {
					wp_send_json_success( [ 'message' => '✓ API credentials verified! Note: Daily limit reached. Will reset tomorrow.' ] );
				}
				wp_send_json_error( [ 'message' => 'API Message: ' . $error_msg ] );
			} else {
				wp_send_json_error( [ 'message' => 'Failed to connect. Please check your API credentials or network connection.' ] );
			}
		}
	}
}
