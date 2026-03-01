<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Settings Manager
 * 
 * Handles AI provider configuration, API key encryption/decryption,
 * model selection, and generation parameters.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class AISettingsManager {

	/**
	 * Supported AI providers
	 */
	const PROVIDERS = [
		'gemini'      => 'Google Gemini',
		'openai'      => 'OpenAI',
		'deepseek'    => 'DeepSeek',
		'huggingface' => 'HuggingFace',
	];

	/**
	 * Available models per provider
	 */
	const MODELS = [
		'gemini' => [
			'gemini-2.5-flash'      => [ 'name' => 'Gemini 2.5 Flash', 'speed' => 'Fast', 'quality' => 'High' ],
			'gemini-2.5-flash-lite' => [ 'name' => 'Gemini 2.5 Flash-Lite', 'speed' => 'Fastest', 'quality' => 'Good' ],
			'gemini-2.5-pro'        => [ 'name' => 'Gemini 2.5 Pro', 'speed' => 'Medium', 'quality' => 'Highest' ],
		],
		'openai' => [
			'gpt-4o-mini'        => [ 'name' => 'GPT-4o Mini', 'speed' => 'Fast', 'quality' => 'Medium' ],
			'gpt-4.1-mini'       => [ 'name' => 'GPT-4.1 Mini', 'speed' => 'Fast', 'quality' => 'Good' ],
			'gpt-4.1'            => [ 'name' => 'GPT-4.1', 'speed' => 'Medium', 'quality' => 'High' ],
			'gpt-4o'             => [ 'name' => 'GPT-4o', 'speed' => 'Medium', 'quality' => 'High' ],
		],
		'deepseek' => [
			'deepseek-chat'      => [ 'name' => 'DeepSeek Chat', 'speed' => 'Fast', 'quality' => 'High' ],
			'deepseek-reasoner'  => [ 'name' => 'DeepSeek Reasoner', 'speed' => 'Slow', 'quality' => 'Highest' ],
		],
		'huggingface' => [
			'mistralai/Mistral-7B-Instruct-v0.2' => [ 'name' => 'Mistral 7B', 'speed' => 'Fast', 'quality' => 'Good' ],
			'meta-llama/Llama-2-70b-chat-hf'     => [ 'name' => 'Llama 2 70B', 'speed' => 'Slow', 'quality' => 'High' ],
		],
	];

	/**
	 * Default settings
	 */
	const DEFAULTS = [
		'primary_provider'   => 'gemini',
		'fallback_provider'  => 'openai',
		'fallback_enabled'   => 'yes',
		'gemini_model'       => 'gemini-2.5-flash',
		'openai_model'       => 'gpt-4.1-mini',
		'deepseek_model'     => 'deepseek-chat',
		'huggingface_model'  => 'mistralai/Mistral-7B-Instruct-v0.2',
		'temperature'        => 0.7,
		'max_tokens'         => 8000,
		'generation_mode'    => 'balanced',
		'strict_html_mode'   => 'yes',
		'request_timeout'    => 120,
		'max_retries'        => 2,
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize default settings if not set
		$this->maybe_init_defaults();
	}

	/**
	 * Initialize default settings if not already set
	 */
	private function maybe_init_defaults() {
		foreach ( self::DEFAULTS as $key => $value ) {
			$option_name = 'wsa_ai_' . $key;
			if ( get_option( $option_name ) === false ) {
				update_option( $option_name, $value );
			}
		}

		// Migrate deprecated model names to current ones
		$this->maybe_migrate_models();
	}

	/**
	 * Migrate deprecated model names to current valid ones
	 */
	private function maybe_migrate_models() {
		$deprecated_models = [
			'gemini' => [
				'gemini-1.5-flash'     => 'gemini-2.5-flash',
				'gemini-1.5-pro'       => 'gemini-2.5-pro',
				'gemini-2.0-flash'     => 'gemini-2.5-flash',
				'gemini-2.0-flash-lite' => 'gemini-2.5-flash-lite',
			],
		];

		foreach ( $deprecated_models as $provider => $model_map ) {
			$option_name = 'wsa_ai_' . $provider . '_model';
			$current_model = get_option( $option_name, '' );

			if ( isset( $model_map[ $current_model ] ) ) {
				update_option( $option_name, $model_map[ $current_model ] );
			}
		}
	}

	/**
	 * Get encryption key based on WordPress salts
	 *
	 * @return string
	 */
	private static function get_encryption_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wsa-default-key';
		$secure_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'wsa-secure-key';
		return hash( 'sha256', $auth_key . $secure_key );
	}

	/**
	 * Encrypt an API key
	 *
	 * @param string $raw_key Plain text API key
	 * @return string Encrypted key
	 */
	public static function encrypt_key( $raw_key ) {
		if ( empty( $raw_key ) ) {
			return '';
		}

		$key = self::get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $raw_key, 'AES-256-CBC', $key, 0, $iv );
		
		if ( $encrypted === false ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt an API key
	 *
	 * @param string $encrypted_key Encrypted API key
	 * @return string Plain text API key
	 */
	public static function decrypt_key( $encrypted_key ) {
		if ( empty( $encrypted_key ) ) {
			return '';
		}

		$key = self::get_encryption_key();
		$decoded = base64_decode( $encrypted_key );
		
		if ( $decoded === false || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Save an API key (encrypted)
	 *
	 * @param string $provider Provider slug
	 * @param string $api_key Plain text API key
	 * @return bool Success status
	 */
	public function save_api_key( $provider, $api_key ) {
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return false;
		}

		$encrypted = self::encrypt_key( $api_key );
		return update_option( 'wsa_ai_' . $provider . '_api_key', $encrypted );
	}

	/**
	 * Get decrypted API key for a provider
	 *
	 * @param string $provider Provider slug
	 * @return string Decrypted API key
	 */
	public function get_api_key( $provider ) {
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return '';
		}

		$encrypted = get_option( 'wsa_ai_' . $provider . '_api_key', '' );
		return self::decrypt_key( $encrypted );
	}

	/**
	 * Check if a provider has a valid API key
	 *
	 * @param string $provider Provider slug
	 * @return bool
	 */
	public function has_api_key( $provider ) {
		return ! empty( $this->get_api_key( $provider ) );
	}

	/**
	 * Check if AI is configured (has at least one provider with API key)
	 *
	 * @return bool
	 */
	public function is_configured() {
		$provider = $this->get_provider();
		return ! empty( $this->get_api_key( $provider ) );
	}

	/**
	 * Check if a provider is enabled
	 *
	 * @param string $provider Provider slug
	 * @return bool
	 */
	public function is_provider_enabled( $provider ) {
		return get_option( 'wsa_ai_' . $provider . '_enabled', 'no' ) === 'yes';
	}

	/**
	 * Enable or disable a provider
	 *
	 * @param string $provider Provider slug
	 * @param bool $enabled Enable status
	 * @return bool
	 */
	public function set_provider_enabled( $provider, $enabled ) {
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return false;
		}

		return update_option( 'wsa_ai_' . $provider . '_enabled', $enabled ? 'yes' : 'no' );
	}

	/**
	 * Get the primary AI provider
	 *
	 * @return string Provider slug
	 */
	public function get_primary_provider() {
		return get_option( 'wsa_ai_primary_provider', self::DEFAULTS['primary_provider'] );
	}

	/**
	 * Get the current AI provider (alias for get_primary_provider)
	 *
	 * @return string Provider slug
	 */
	public function get_provider() {
		return $this->get_primary_provider();
	}

	/**
	 * Set the primary AI provider
	 *
	 * @param string $provider Provider slug
	 * @return bool
	 */
	public function set_primary_provider( $provider ) {
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return false;
		}

		return update_option( 'wsa_ai_primary_provider', $provider );
	}

	/**
	 * Get the fallback AI provider
	 *
	 * @return string Provider slug or 'none'
	 */
	public function get_fallback_provider() {
		return get_option( 'wsa_ai_fallback_provider', self::DEFAULTS['fallback_provider'] );
	}

	/**
	 * Set the fallback AI provider
	 *
	 * @param string $provider Provider slug or 'none'
	 * @return bool
	 */
	public function set_fallback_provider( $provider ) {
		if ( $provider !== 'none' && ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return false;
		}

		return update_option( 'wsa_ai_fallback_provider', $provider );
	}

	/**
	 * Check if fallback is enabled
	 *
	 * @return bool
	 */
	public function is_fallback_enabled() {
		return get_option( 'wsa_ai_fallback_enabled', self::DEFAULTS['fallback_enabled'] ) === 'yes';
	}

	/**
	 * Get the selected model for a provider
	 *
	 * @param string|null $provider Provider slug (null = use current provider)
	 * @return string Model identifier
	 */
	public function get_model( $provider = null ) {
		if ( $provider === null ) {
			$provider = $this->get_provider();
		}
		$default = self::DEFAULTS[ $provider . '_model' ] ?? '';
		return get_option( 'wsa_ai_' . $provider . '_model', $default );
	}

	/**
	 * Set the model for a provider
	 *
	 * @param string $provider Provider slug
	 * @param string $model Model identifier
	 * @return bool
	 */
	public function set_model( $provider, $model ) {
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			return false;
		}

		if ( empty( $model ) ) {
			return false;
		}

		return update_option( 'wsa_ai_' . $provider . '_model', $model );
	}

	/**
	 * Get available models for a provider
	 *
	 * @param string $provider Provider slug
	 * @return array
	 */
	public function get_available_models( $provider ) {
		return self::MODELS[ $provider ] ?? [];
	}

	/**
	 * Get temperature setting
	 *
	 * @return float
	 */
	public function get_temperature() {
		return (float) get_option( 'wsa_ai_temperature', self::DEFAULTS['temperature'] );
	}

	/**
	 * Set temperature
	 *
	 * @param float $temperature Value between 0.0 and 1.0
	 * @return bool
	 */
	public function set_temperature( $temperature ) {
		$temperature = max( 0.0, min( 1.0, (float) $temperature ) );
		return update_option( 'wsa_ai_temperature', $temperature );
	}

	/**
	 * Get max tokens setting
	 *
	 * @return int
	 */
	public function get_max_tokens() {
		return (int) get_option( 'wsa_ai_max_tokens', self::DEFAULTS['max_tokens'] );
	}

	/**
	 * Set max tokens
	 *
	 * @param int $max_tokens Value between 1000 and 32000
	 * @return bool
	 */
	public function set_max_tokens( $max_tokens ) {
		$max_tokens = max( 1000, min( 32000, (int) $max_tokens ) );
		return update_option( 'wsa_ai_max_tokens', $max_tokens );
	}

	/**
	 * Get generation mode
	 *
	 * @return string 'fast', 'balanced', or 'quality'
	 */
	public function get_generation_mode() {
		return get_option( 'wsa_ai_generation_mode', self::DEFAULTS['generation_mode'] );
	}

	/**
	 * Set generation mode
	 *
	 * @param string $mode 'fast', 'balanced', or 'quality'
	 * @return bool
	 */
	public function set_generation_mode( $mode ) {
		if ( ! in_array( $mode, [ 'fast', 'balanced', 'quality' ], true ) ) {
			return false;
		}

		return update_option( 'wsa_ai_generation_mode', $mode );
	}

	/**
	 * Get mode-based settings overrides
	 *
	 * @param string $mode Generation mode
	 * @return array Settings for this mode
	 */
	public function get_mode_settings( $mode = null ) {
		$mode = $mode ?: $this->get_generation_mode();

		$settings = [
			'fast' => [
				'temperature' => 0.5,
				'max_tokens'  => 4000,
				'model_tier'  => 'flash',
			],
			'balanced' => [
				'temperature' => 0.7,
				'max_tokens'  => 8000,
				'model_tier'  => 'standard',
			],
			'quality' => [
				'temperature' => 0.8,
				'max_tokens'  => 16000,
				'model_tier'  => 'pro',
			],
		];

		return $settings[ $mode ] ?? $settings['balanced'];
	}

	/**
	 * Check if strict HTML mode is enabled
	 *
	 * @return bool
	 */
	public function is_strict_html_mode() {
		return get_option( 'wsa_ai_strict_html_mode', self::DEFAULTS['strict_html_mode'] ) === 'yes';
	}

	/**
	 * Get request timeout in seconds
	 *
	 * @return int
	 */
	public function get_request_timeout() {
		$timeout = (int) get_option( 'wsa_ai_request_timeout', self::DEFAULTS['request_timeout'] );
		// Enforce minimum 90s for landing page generation (Gemini can be slow)
		return max( $timeout, 90 );
	}

	/**
	 * Get maximum retry attempts
	 *
	 * @return int
	 */
	public function get_max_retries() {
		return (int) get_option( 'wsa_ai_max_retries', self::DEFAULTS['max_retries'] );
	}

	/**
	 * Get all settings as array
	 *
	 * @return array
	 */
	public function get_all_settings() {
		$settings = [];

		foreach ( self::DEFAULTS as $key => $default ) {
			$settings[ $key ] = get_option( 'wsa_ai_' . $key, $default );
		}

		// Add provider statuses
		foreach ( self::PROVIDERS as $slug => $name ) {
			$settings['providers'][ $slug ] = [
				'name'        => $name,
				'enabled'     => $this->is_provider_enabled( $slug ),
				'has_api_key' => $this->has_api_key( $slug ),
				'model'       => $this->get_model( $slug ),
			];
		}

		return $settings;
	}

	/**
	 * Save multiple settings at once
	 *
	 * @param array $settings Key-value pairs of settings
	 * @return bool
	 */
	public function save_settings( $settings ) {
		$success = true;

		foreach ( $settings as $key => $value ) {
			// Skip API keys - they should use save_api_key method
			if ( strpos( $key, '_api_key' ) !== false ) {
				continue;
			}

			$option_name = 'wsa_ai_' . $key;
			if ( ! update_option( $option_name, $value ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get provider instance for the primary provider
	 *
	 * @return object|null Provider instance or null
	 */
	public function get_primary_provider_instance() {
		$provider = $this->get_primary_provider();
		return $this->get_provider_instance( $provider );
	}

	/**
	 * Get provider instance for the fallback provider
	 *
	 * @return object|null Provider instance or null
	 */
	public function get_fallback_provider_instance() {
		if ( ! $this->is_fallback_enabled() ) {
			return null;
		}

		$provider = $this->get_fallback_provider();
		if ( $provider === 'none' ) {
			return null;
		}

		return $this->get_provider_instance( $provider );
	}

	/**
	 * Get a provider instance by slug
	 *
	 * @param string $provider Provider slug
	 * @return object|null Provider instance
	 */
	public function get_provider_instance( $provider ) {
		$namespace = __NAMESPACE__ . '\\Providers\\';

		$class_map = [
			'gemini'      => 'GeminiProvider',
			'openai'      => 'OpenAIProvider',
			'deepseek'    => 'DeepSeekProvider',
			'huggingface' => 'HuggingFaceProvider',
		];

		if ( ! isset( $class_map[ $provider ] ) ) {
			return null;
		}

		$class_name = $namespace . $class_map[ $provider ];

		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		return new $class_name( $this );
	}

	/**
	 * Get all supported providers
	 *
	 * @return array
	 */
	public static function get_providers() {
		return self::PROVIDERS;
	}
}
