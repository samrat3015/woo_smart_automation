<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder\Providers;

use WooSmartAutomation\Modules\AIFunnelBuilder\AISettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini AI Provider
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder\Providers
 * @since 1.1.0
 */
class GeminiProvider implements AIProviderInterface {

	/**
	 * API base URL
	 */
	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

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
		$this->api_key  = $settings->get_api_key( 'gemini' );
		$this->model    = $settings->get_model( 'gemini' );
	}

	/**
	 * Generate content using Gemini API
	 *
	 * @param string $prompt The prompt to send
	 * @param array $options Generation options
	 * @return array
	 */
	public function generate( $prompt, $options = [] ) {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'Gemini API key is not configured.', 'woo-smart-automation' ),
			];
		}

		$endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

		$temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7;
		$max_tokens  = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 8000;
		$timeout     = isset( $options['timeout'] ) ? (int) $options['timeout'] : 120;

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt ]
					]
				]
			],
			'generationConfig' => [
				'temperature'     => $temperature,
				'maxOutputTokens' => $max_tokens,
				'topP'            => 0.95,
				'topK'            => 40,
			],
			'safetySettings' => [
				[
					'category'  => 'HARM_CATEGORY_HARASSMENT',
					'threshold' => 'BLOCK_ONLY_HIGH'
				],
				[
					'category'  => 'HARM_CATEGORY_HATE_SPEECH',
					'threshold' => 'BLOCK_ONLY_HIGH'
				],
				[
					'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
					'threshold' => 'BLOCK_ONLY_HIGH'
				],
				[
					'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_ONLY_HIGH'
				]
			]
		];

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => $timeout,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( sprintf(
				'[WSA Gemini] API request failed. Model: %s, Timeout: %ds, Error: %s',
				$this->model,
				$timeout,
				$error_msg
			) );
			return [
				'success' => false,
				'error'   => sprintf(
					__( 'Gemini API error: %s (timeout was %ds)', 'woo-smart-automation' ),
					$error_msg,
					$timeout
				),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$error_message = isset( $data['error']['message'] ) 
				? $data['error']['message'] 
				: __( 'Unknown error from Gemini API.', 'woo-smart-automation' );

			// Handle specific error codes
			if ( $response_code === 429 ) {
				$error_message = __( 'Gemini API rate limit exceeded. Please try again later.', 'woo-smart-automation' );
			} elseif ( $response_code === 401 || $response_code === 403 ) {
				$error_message = __( 'Invalid Gemini API key. Please check your API key in settings.', 'woo-smart-automation' );
			}

			return [
				'success' => false,
				'error'   => $error_message,
			];
		}

		// Extract generated content
		$html = '';
		$tokens_used = 0;

		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$html = $data['candidates'][0]['content']['parts'][0]['text'];
		}

		// Get token usage if available
		if ( isset( $data['usageMetadata']['totalTokenCount'] ) ) {
			$tokens_used = (int) $data['usageMetadata']['totalTokenCount'];
		}

		if ( empty( $html ) ) {
			// Check for blocked content
			if ( isset( $data['candidates'][0]['finishReason'] ) && $data['candidates'][0]['finishReason'] === 'SAFETY' ) {
				return [
					'success' => false,
					'error'   => __( 'Content was blocked by safety filters. Please try a different prompt.', 'woo-smart-automation' ),
				];
			}

			return [
				'success' => false,
				'error'   => __( 'Gemini returned empty response. Please try again.', 'woo-smart-automation' ),
			];
		}

		return [
			'success'     => true,
			'html'        => $html,
			'tokens_used' => $tokens_used,
		];
	}

	/**
	 * Test the connection to Gemini API
	 *
	 * @return array
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'error'   => __( 'Gemini API key is not configured.', 'woo-smart-automation' ),
			];
		}

		// Use a minimal prompt for testing
		$endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => 'Say "OK" if you can read this.' ]
					]
				]
			],
			'generationConfig' => [
				'temperature'     => 0.1,
				'maxOutputTokens' => 10,
			]
		];

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type' => 'application/json',
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
		return 'Google Gemini';
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
