<?php
namespace WooSmartAutomation\Modules\MetaCAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sending events to Meta Graph API
 */
class EventSender {

	private $pixel_id;
	private $access_token;
	private $test_event_code;

	public function __construct() {
		// These would ideally come from the settings page
		$this->pixel_id        = get_option( 'wsa_meta_pixel_id', '' );
		$this->access_token    = get_option( 'wsa_meta_access_token', '' );
		$this->test_event_code = get_option( 'wsa_meta_test_event_code', '' );
	}

	public function send( $event_data ) {
		if ( empty( $this->pixel_id ) || empty( $this->access_token ) ) {
			return;
		}

		$url = "https://graph.facebook.com/v18.0/{$this->pixel_id}/events?access_token={$this->access_token}";

		// Basic User Data (Required for matching)
		$default_user_data = [
			'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
			'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'fbp'               => $_COOKIE['_fbp'] ?? '',
			'fbc'               => $_COOKIE['_fbc'] ?? '',
		];

		$payload = [
			'data' => [
				array_merge( [
					'event_time'   => time(),
					'action_source' => 'website',
					'user_data'    => array_merge( $default_user_data, $event_data['user_data'] ?? [] ),
				], $event_data )
			]
		];

		if ( ! empty( $this->test_event_code ) ) {
			$payload['test_event_code'] = $this->test_event_code;
		}

		$response = wp_remote_post( $url, [
			'body'    => json_encode( $payload ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'WSA Meta CAPI Connection Error: ' . $response->get_error_message() );
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );
			
			if ( $status_code !== 200 ) {
				error_log( "WSA Meta CAPI API Error ($status_code): " . $body );
			} elseif ( ! empty( $this->test_event_code ) ) {
				// Only log success during testing to avoid filling up logs
				error_log( "WSA Meta CAPI Success: Event sent successfully with Test Code " . $this->test_event_code );
			}
		}
	}
}
