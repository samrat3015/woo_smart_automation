<?php
namespace WooSmartAutomation\Core;

class Ajax {
	
	public function init() {
		// Test FraudPeek API connection
		add_action( 'wp_ajax_wsa_test_fraudpeek_api', [ $this, 'test_fraudpeek_api' ] );
	}

	/**
	 * Test FraudPeek API connection
	 */
	public function test_fraudpeek_api() {
		check_ajax_referer( 'wsa_test_api', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access' ] );
		}

		require_once WSA_PATH . 'includes/Modules/Courier/FraudPeekService.php';
		$service = new \WooSmartAutomation\Modules\Courier\FraudPeekService();

		// Test with a known Bangladeshi number (will bypass cache for fresh test)
		$result = $service->get_fraud_data( '01700000000', true );

		if ( $result && isset( $result['total_parcels'] ) ) {
			wp_send_json_success( [
				'message' => sprintf(
					'FraudPeek connected! Risk Level: %s — %d parcels, %.0f%% delivery rate.',
					ucfirst( $result['risk_level'] ),
					$result['total_parcels'],
					$result['average_delivery_rate']
				),
			] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to connect to FraudPeek API. Check server connectivity.' ] );
		}
	}
}
