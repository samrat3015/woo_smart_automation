<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Provider Interface
 * 
 * All AI providers must implement this interface.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder\Providers
 * @since 1.1.0
 */
interface AIProviderInterface {

	/**
	 * Generate content using the AI provider
	 *
	 * @param string $prompt The prompt to send
	 * @param array $options Generation options (temperature, max_tokens, etc.)
	 * @return array ['success' => bool, 'html' => string, 'error' => string, 'tokens_used' => int]
	 */
	public function generate( $prompt, $options = [] );

	/**
	 * Test the connection to the AI provider
	 *
	 * @return array ['success' => bool, 'error' => string, 'model' => string]
	 */
	public function test_connection();

	/**
	 * Get the provider name
	 *
	 * @return string Human-readable provider name
	 */
	public function get_provider_name();

	/**
	 * Get the current model being used
	 *
	 * @return string Model identifier
	 */
	public function get_model();

	/**
	 * Check if the provider is properly configured
	 *
	 * @return bool
	 */
	public function is_configured();
}
