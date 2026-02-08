<?php
namespace WooSmartAutomation\Modules\SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BulkSMSBD.net API Service
 */
class BulkSMSBDService {

	private $api_key;
	private $sender_id;
	private $api_url = 'https://bulksmsbd.net/api/smsapi';
	private $balance_url = 'https://bulksmsbd.net/api/getBalanceApi';

	public function __construct() {
		$this->api_key   = get_option( 'wsa_bulksmsbd_api_key', '' );
		$this->sender_id = get_option( 'wsa_bulksmsbd_sender_id', '' );
	}

	/**
	 * Send SMS
	 * 
	 * @param string $number Phone number
	 * @param string $message Message content
	 * @return array|bool API response or false on failure
	 */
	public function send_sms( $number, $message ) {
		if ( empty( $this->api_key ) || empty( $number ) || empty( $message ) ) {
			error_log( 'WSA SMS Error: Missing API Key, Number, or Message.' );
			return false;
		}

		// Clean phone number
		$number = $this->clean_phone_number( $number );

		$params = [
			'api_key'  => $this->api_key,
			'type'     => 'text',
			'number'   => $number,
			'senderid' => $this->sender_id,
			'message'  => $message,
		];

		// BulkSMSBD supports both GET and POST. Using POST for better reliability.
		$response = wp_remote_post( $this->api_url, [
			'body'    => $params,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'WSA SMS WP_Error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( isset( $result['response_code'] ) && 202 == $result['response_code'] ) {
			return $result;
		}

		$error_msg = $this->get_error_message( isset( $result['response_code'] ) ? $result['response_code'] : '' );
		error_log( 'WSA SMS API Failure: ' . $error_msg . ' | Response: ' . $body );
		return false;
	}

	/**
	 * Get Account Balance
	 * 
	 * @return string|bool Balance string or false on failure
	 */
	public function get_balance() {
		if ( empty( $this->api_key ) ) {
			return false;
		}

		$url = add_query_arg( [ 'api_key' => $this->api_key ], $this->balance_url );
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			error_log( 'WSA SMS Balance API WP_Error: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( isset( $result['balance'] ) ) {
			return $result['balance'];
		}

		$error_msg = $this->get_error_message( isset( $result['response_code'] ) ? $result['response_code'] : '' );
		error_log( 'WSA SMS Balance API failure: ' . $error_msg . ' | Response: ' . $body );
		return false;
	}

	/**
	 * Map API Error Codes to Meaning
	 */
	private function get_error_message( $code ) {
		$codes = [
			'202'  => 'SMS Submitted Successfully',
			'1001' => 'Invalid Number',
			'1002' => 'Sender ID not correct/disabled',
			'1003' => 'Please Required all fields',
			'1005' => 'Internal Error',
			'1006' => 'Balance Validity Not Available',
			'1007' => 'Balance Insufficient',
			'1011' => 'User ID not found',
			'1012' => 'Masking SMS must be sent in Bengali',
			'1013' => 'Sender ID has not found Gateway by api key',
			'1014' => 'Sender Type Name not found using this sender by api key',
			'1015' => 'Sender Id has not found Any Valid Gateway by api key',
			'1016' => 'Sender Type Name Active Price Info not found by this sender id',
			'1017' => 'Sender Type Name Price Info not found by this sender id',
			'1018' => 'The Owner of this Account is disabled',
			'1019' => 'The Price of this Account is disabled',
			'1020' => 'The parent of this account is not found',
			'1021' => 'The parent active price of this account is not found',
			'1031' => 'Your Account Not Verified',
			'1032' => 'IP Not whitelisted'
		];

		return isset( $codes[$code] ) ? $codes[$code] : 'Unknown Error (Code: ' . $code . ')';
	}

	/**
	 * Clean phone number for Bangladesh
	 */
	private function clean_phone_number( $number ) {
		// Remove all non-numeric characters
		$number = preg_replace( '/[^0-9]/', '', $number );

		// If it starts with 880, keep it or normalize
		if ( strlen( $number ) == 10 && strpos( $number, '1' ) === 0 ) {
			$number = '880' . $number;
		} elseif ( strlen( $number ) == 11 && strpos( $number, '01' ) === 0 ) {
			$number = '88' . $number;
		}

		return $number;
	}
}
