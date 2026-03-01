<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder\Providers;

use WooSmartAutomation\Modules\AIFunnelBuilder\AISettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DeepSeek AI Provider
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder\Providers
 * @since 1.1.0
 */
class DeepSeekProvider implements AIProviderInterface {

	/**
	 * API base URL
	 */
	const API_BASE = 'https://api.deepseek.com/v1/chat/completions';

	/**
	 * Settings manager
	 *
	 * @var AISettingsManager
	 */
	private $settings;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Constructor
	 *
	 * @param AISettingsManager $settings Settings manager instance
	 */
	public function __construct( AISettingsManager $settings ) {
		$this->settings = $settings;
		$this->api_key  = $settings->get_api_key( 'deepseek' );
		$this->model    = $settings->get_model( 'deepseek' );
	}

	/**
	 * Generate content using DeepSeek API
	 *
	 * @param string $prompt The prompt to send
	 * @param array $options Generation options
	 * @return array
	 */
	public function generate( $prompt, $options = [] ) {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'DeepSeek API key is not configured.', 'woo-smart-automation' ),
			];
		}

		$temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7;
		$max_tokens  = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 8000;
		$timeout     = isset( $options['timeout'] ) ? (int) $options['timeout'] : 60;

		$body = [
			'model'       => $this->model,
			'messages'    => [
				[
					'role'    => 'system',
					'content' => 'You are a professional web designer who creates high-converting landing pages. You only output valid HTML code without any explanations or markdown.'
				],
				[
					'role'    => 'user',
					'content' => $prompt
				]
			],
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
			'stream'      => false,
		];

		$response = wp_remote_post( self::API_BASE, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => $timeout,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$error_message = isset( $data['error']['message'] ) 
				? $data['error']['message'] 
				: __( 'Unknown error from DeepSeek API.', 'woo-smart-automation' );

			if ( $response_code === 429 ) {
				$error_message = __( 'DeepSeek API rate limit exceeded. Please try again later.', 'woo-smart-automation' );
			} elseif ( $response_code === 401 ) {
				$error_message = __( 'Invalid DeepSeek API key. Please check your API key in settings.', 'woo-smart-automation' );
			}

			return [
				'success' => false,
				'error'   => $error_message,
			];
		}

		// Extract generated content
		$html = '';
		$tokens_used = 0;

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$html = $data['choices'][0]['message']['content'];
		}

		if ( isset( $data['usage']['total_tokens'] ) ) {
			$tokens_used = (int) $data['usage']['total_tokens'];
		}

		if ( empty( $html ) ) {
			return [
				'success' => false,
				'error'   => __( 'DeepSeek returned empty response. Please try again.', 'woo-smart-automation' ),
			];
		}

		return [
			'success'     => true,
			'html'        => $html,
			'tokens_used' => $tokens_used,
		];
	}

	/**
	 * Test the connection to DeepSeek API
	 *
	 * @return array
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'DeepSeek API key is not configured.', 'woo-smart-automation' ),
			];
		}

		$body = [
			'model'       => $this->model,
			'messages'    => [
				[
					'role'    => 'user',
					'content' => 'Say "OK" if you can read this.'
				]
			],
			'temperature' => 0.1,
			'max_tokens'  => 10,
		];

		$response = wp_remote_post( self::API_BASE, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 ) {
			$response_body = wp_remote_retrieve_body( $response );
			$data = json_decode( $response_body, true );

			$error_message = isset( $data['error']['message'] ) 
				? $data['error']['message'] 
				: sprintf( __( 'API returned status code %d', 'woo-smart-automation' ), $response_code );

			return [
				'success' => false,
				'error'   => $error_message,
			];
		}

		return [
			'success' => true,
			'model'   => $this->model,
			'message' => __( 'Connection successful!', 'woo-smart-automation' ),
		];
	}

	/**
	 * Get the provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'DeepSeek';
	}

	/**
	 * Get the current model
	 *
	 * @return string
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}
}
