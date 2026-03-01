<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder\Providers;

use WooSmartAutomation\Modules\AIFunnelBuilder\AISettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HuggingFace AI Provider
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder\Providers
 * @since 1.1.0
 */
class HuggingFaceProvider implements AIProviderInterface {

	/**
	 * API base URL
	 */
	const API_BASE = 'https://api-inference.huggingface.co/models/';

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
		$this->api_key  = $settings->get_api_key( 'huggingface' );
		$this->model    = $settings->get_model( 'huggingface' );
	}

	/**
	 * Generate content using HuggingFace Inference API
	 *
	 * @param string $prompt The prompt to send
	 * @param array $options Generation options
	 * @return array
	 */
	public function generate( $prompt, $options = [] ) {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'HuggingFace API key is not configured.', 'woo-smart-automation' ),
			];
		}

		$temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7;
		$max_tokens  = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 4000;
		$timeout     = isset( $options['timeout'] ) ? (int) $options['timeout'] : 120;

		// HuggingFace has lower token limits typically
		$max_tokens = min( $max_tokens, 4096 );

		$endpoint = self::API_BASE . $this->model;

		// Build the prompt with system instruction
		$full_prompt = "You are a professional web designer. Create a landing page based on this request. Output ONLY valid HTML code with embedded CSS, no explanations.\n\n" . $prompt;

		$body = [
			'inputs'     => $full_prompt,
			'parameters' => [
				'temperature'       => $temperature,
				'max_new_tokens'    => $max_tokens,
				'return_full_text'  => false,
				'do_sample'         => true,
			],
			'options' => [
				'wait_for_model' => true,
				'use_cache'      => false,
			]
		];

		$response = wp_remote_post( $endpoint, [
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
			$error_message = __( 'Unknown error from HuggingFace API.', 'woo-smart-automation' );

			if ( isset( $data['error'] ) ) {
				$error_message = $data['error'];
			}

			if ( $response_code === 429 ) {
				$error_message = __( 'HuggingFace API rate limit exceeded. Please try again later.', 'woo-smart-automation' );
			} elseif ( $response_code === 401 ) {
				$error_message = __( 'Invalid HuggingFace API key. Please check your API key in settings.', 'woo-smart-automation' );
			} elseif ( $response_code === 503 ) {
				$error_message = __( 'HuggingFace model is loading. Please try again in a few moments.', 'woo-smart-automation' );
			}

			return [
				'success' => false,
				'error'   => $error_message,
			];
		}

		// Extract generated content
		$html = '';

		if ( is_array( $data ) && isset( $data[0]['generated_text'] ) ) {
			$html = $data[0]['generated_text'];
		} elseif ( is_array( $data ) && isset( $data['generated_text'] ) ) {
			$html = $data['generated_text'];
		} elseif ( is_string( $data ) ) {
			$html = $data;
		}

		if ( empty( $html ) ) {
			return [
				'success' => false,
				'error'   => __( 'HuggingFace returned empty response. Please try again.', 'woo-smart-automation' ),
			];
		}

		return [
			'success'     => true,
			'html'        => $html,
			'tokens_used' => 0, // HuggingFace doesn't always return token count
		];
	}

	/**
	 * Test the connection to HuggingFace API
	 *
	 * @return array
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'HuggingFace API key is not configured.', 'woo-smart-automation' ),
			];
		}

		$endpoint = self::API_BASE . $this->model;

		$body = [
			'inputs'     => 'Say OK',
			'parameters' => [
				'max_new_tokens' => 5,
			],
			'options' => [
				'wait_for_model' => true,
			]
		];

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
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

			$error_message = isset( $data['error'] ) 
				? $data['error'] 
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
		return 'HuggingFace';
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
