<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Generation Engine
 * 
 * Orchestrates AI content generation including prompt assembly,
 * provider selection, API calls, and output validation.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class AIGenerationEngine {

	/**
	 * Settings manager instance
	 *
	 * @var AISettingsManager
	 */
	private $settings;

	/**
	 * Prompt template library
	 *
	 * @var PromptTemplateLibrary
	 */
	private $template_library;

	/**
	 * Image handler
	 *
	 * @var ImageHandler
	 */
	private $image_handler;

	/**
	 * Constructor
	 *
	 * @param AISettingsManager $settings Settings manager instance
	 */
	public function __construct( AISettingsManager $settings ) {
		$this->settings         = $settings;
		$this->template_library = new PromptTemplateLibrary();
		$this->image_handler    = new ImageHandler();
	}

	/**
	 * Generate a landing page
	 *
	 * @param array $params Generation parameters
	 * @return array Result with 'success', 'html', 'provider_used', 'error'
	 */
	public function generate( $params ) {
		// Validate required parameters
		$validation = $this->validate_params( $params );
		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'error'   => $validation['error'],
			];
		}

		// Build the full prompt
		$prompt = $this->build_prompt( $params );

		if ( empty( $prompt ) ) {
			return [
				'success' => false,
				'error'   => __( 'Failed to build generation prompt.', 'woo-smart-automation' ),
			];
		}

		// Try primary provider
		$primary_provider = $this->settings->get_primary_provider_instance();

		if ( ! $primary_provider ) {
			return [
				'success' => false,
				'error'   => __( 'No AI provider configured. Please add an API key in AI Settings.', 'woo-smart-automation' ),
			];
		}

		// Check if provider has API key
		$provider_slug = $this->settings->get_primary_provider();
		if ( ! $this->settings->has_api_key( $provider_slug ) ) {
			return [
				'success' => false,
				'error'   => sprintf(
					__( 'No API key found for %s. Please add your API key in AI Settings.', 'woo-smart-automation' ),
					AISettingsManager::PROVIDERS[ $provider_slug ] ?? $provider_slug
				),
			];
		}

		// Attempt generation with primary provider
		$result = $this->attempt_generation( $primary_provider, $prompt, $params );

		// If failed and fallback is enabled, try fallback provider
		if ( ! $result['success'] && $this->settings->is_fallback_enabled() ) {
			$fallback_provider = $this->settings->get_fallback_provider_instance();

			if ( $fallback_provider ) {
				$fallback_slug = $this->settings->get_fallback_provider();
				
				if ( $this->settings->has_api_key( $fallback_slug ) ) {
					$fallback_result = $this->attempt_generation( $fallback_provider, $prompt, $params );

					if ( $fallback_result['success'] ) {
						$fallback_result['fallback_used'] = true;
						$fallback_result['primary_error'] = $result['error'];
						return $fallback_result;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Validate generation parameters
	 *
	 * @param array $params Parameters to validate
	 * @return array ['valid' => bool, 'error' => string]
	 */
	private function validate_params( $params ) {
		// Required: product_id or custom prompt
		if ( empty( $params['product_id'] ) && empty( $params['custom_prompt'] ) ) {
			return [
				'valid' => false,
				'error' => __( 'Please select a product or provide a custom prompt.', 'woo-smart-automation' ),
			];
		}

		// If product_id provided, verify product exists
		if ( ! empty( $params['product_id'] ) ) {
			$product = wc_get_product( $params['product_id'] );
			if ( ! $product ) {
				return [
					'valid' => false,
					'error' => __( 'Selected product not found.', 'woo-smart-automation' ),
				];
			}
		}

		return [ 'valid' => true, 'error' => '' ];
	}

	/**
	 * Build the complete prompt for AI generation
	 *
	 * @param array $params Generation parameters
	 * @return string Complete prompt
	 */
	private function build_prompt( $params ) {
		$prompt_parts = [];

		// 1. System instructions — ALWAYS include to ensure HTML output
		$prompt_parts[] = $this->get_system_instructions();

		// 2. Template or custom prompt
		if ( ! empty( $params['template_slug'] ) && $params['template_slug'] !== 'custom' ) {
			try {
				$template = $this->template_library->get_template( $params['template_slug'] );
				if ( $template ) {
					$prompt_parts[] = $template['prompt_body'];
				} else {
					// Try hardcoded definitions as fallback
					$builtin_defs = PromptTemplateLibrary::get_builtin_template_definitions();
					foreach ( $builtin_defs as $def ) {
						if ( $def['slug'] === $params['template_slug'] ) {
							$prompt_parts[] = $def['prompt_body'];
							break;
						}
					}
				}
			} catch ( \Exception $e ) {
				error_log( '[WSA AI Funnel] Template fetch error: ' . $e->getMessage() );
			}
		}

		// 3. Custom prompt additions
		if ( ! empty( $params['custom_prompt'] ) ) {
			$prompt_parts[] = sanitize_textarea_field( $params['custom_prompt'] );
		}

		// 4. Product data injection
		if ( ! empty( $params['product_id'] ) ) {
			$product_context = $this->build_product_context( $params['product_id'] );
			$prompt_parts[]  = $product_context;
		}

		// 5. Image placement instructions
		if ( ! empty( $params['images'] ) ) {
			$image_context  = $this->image_handler->build_image_context_block( $params['images'] );
			$prompt_parts[] = $image_context;
		}

		// 6. Funnel/checkout form instructions (always include)
		$funnel_context = $this->build_funnel_context( $params['funnel_id'] ?? 0 );
		if ( $funnel_context ) {
			$prompt_parts[] = $funnel_context;
		}

		// 7. Final output instructions
		$prompt_parts[] = $this->get_output_instructions();

		return implode( "\n\n", array_filter( $prompt_parts ) );
	}

	/**
	 * Get system instructions for strict HTML mode
	 *
	 * @return string
	 */
	private function get_system_instructions() {
		return <<<EOT
CRITICAL SYSTEM INSTRUCTIONS:
You are a professional landing page generator. Follow these rules EXACTLY:

1. Return ONLY valid HTML5 code. No markdown, no explanations, no code fences.
2. Start your response with <!DOCTYPE html> or directly with HTML tags.
3. All CSS must be inside a single <style> tag in the <head> section.
4. Do NOT include any JavaScript code.
5. Do NOT wrap your response in ``` code blocks.
6. Do NOT include any text before or after the HTML code.
7. Use semantic HTML5 elements: <header>, <main>, <section>, <footer>, <article>.
8. Make the design mobile-first and fully responsive.
9. Use the exact image URLs provided - do not modify or create new URLs.
EOT;
	}

	/**
	 * Build product context from WooCommerce product
	 *
	 * @param int $product_id WooCommerce product ID
	 * @return string Product context for prompt
	 */
	private function build_product_context( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		$price = $product->get_price_html();
		$price_plain = wp_strip_all_tags( html_entity_decode( $price ) );

		$description = $product->get_short_description();
		if ( empty( $description ) ) {
			$description = wp_trim_words( $product->get_description(), 100 );
		}
		$description = wp_strip_all_tags( $description );

		$image_url = wp_get_attachment_url( $product->get_image_id() );

		$context = <<<EOT
--- PRODUCT INFORMATION ---
Product Name: {$product->get_name()}
Product Price: {$price_plain}
Product Description: {$description}
Product URL: {$product->get_permalink()}
EOT;

		if ( $image_url ) {
			$context .= "\nProduct Main Image: {$image_url}";
		}

		// Add product attributes if variable product
		if ( $product->is_type( 'variable' ) ) {
			$attributes = $product->get_variation_attributes();
			if ( ! empty( $attributes ) ) {
				$context .= "\nProduct Variations: " . implode( ', ', array_keys( $attributes ) );
			}
		}

		$context .= "\n--- END PRODUCT INFORMATION ---";

		return $context;
	}

	/**
	 * Build funnel context
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return string Funnel context for prompt
	 */
	private function build_funnel_context( $funnel_id ) {
		// Always include checkout form instructions regardless of funnel existence
		// The checkout form will be injected server-side on render
		return <<<EOT
--- CHECKOUT FORM INSTRUCTIONS ---
You MUST include a checkout/order form section in the landing page.
Place this exact HTML placeholder where the checkout form should appear:
<div id="wsa-checkout-form" class="wsa-checkout-container">
    <!-- Checkout form will be injected here automatically -->
</div>

Place this form in a prominent position, typically after showcasing product benefits and before the footer.
Style the container with appropriate padding and a subtle background to make it stand out.
Add a heading above it like "Order Now" or "Place Your Order" to draw attention.
The actual form fields will be injected automatically — DO NOT create your own form inputs inside this div.
--- END CHECKOUT FORM INSTRUCTIONS ---
EOT;
	}

	/**
	 * Get final output instructions
	 *
	 * @return string
	 */
	private function get_output_instructions() {
		return <<<EOT
--- FINAL OUTPUT REQUIREMENTS ---
1. Create a complete, self-contained HTML page
2. Include all styles in a <style> tag within <head>
3. Design must be mobile-responsive (mobile-first approach)
4. Use a clean, modern design with good contrast
5. Include a compelling call-to-action button
6. The page should load fast - no external resources except images provided
7. Add appropriate alt text to all images
8. Use relative units (rem, em, %) for better responsiveness
--- END OUTPUT REQUIREMENTS ---
EOT;
	}

	/**
	 * Attempt generation with a specific provider
	 *
	 * @param object $provider Provider instance
	 * @param string $prompt Full prompt
	 * @param array $params Original parameters
	 * @return array Result array
	 */
	private function attempt_generation( $provider, $prompt, $params ) {
		$options = [
			'temperature' => $this->settings->get_temperature(),
			'max_tokens'  => $this->settings->get_max_tokens(),
			'timeout'     => $this->settings->get_request_timeout(),
		];

		// Override with mode settings if applicable
		$mode = $this->settings->get_generation_mode();
		$mode_settings = $this->settings->get_mode_settings( $mode );
		$options = array_merge( $options, $mode_settings );

		// Ensure timeout is never overridden by mode settings
		$options['timeout'] = $this->settings->get_request_timeout();

		error_log( sprintf(
			'[WSA AI Funnel] Starting generation. Provider: %s, Model: %s, Timeout: %ds, Mode: %s, Prompt length: %d chars',
			$provider->get_provider_name(),
			$provider->get_model(),
			$options['timeout'],
			$mode,
			strlen( $prompt )
		) );

		try {
			$result = $provider->generate( $prompt, $options );

			if ( ! $result['success'] ) {
				error_log( sprintf(
					'[WSA AI Funnel] Generation failed. Provider: %s, Error: %s',
					$provider->get_provider_name(),
					$result['error'] ?? 'Unknown error'
				) );
				return $result;
			}

			// Validate and clean the output
			$allowed_urls = [];
			if ( ! empty( $params['images'] ) ) {
				$allowed_urls = wp_list_pluck( $params['images'], 'url' );
			}

			$validated_html = OutputValidator::validate( $result['html'], $allowed_urls );

			return [
				'success'       => true,
				'html'          => $validated_html,
				'provider_used' => $provider->get_provider_name(),
				'model_used'    => $provider->get_model(),
				'tokens_used'   => $result['tokens_used'] ?? 0,
			];

		} catch ( \Exception $e ) {
			error_log( sprintf(
				'[WSA AI Funnel] Generation exception. Provider: %s, Error: %s',
				$provider->get_provider_name(),
				$e->getMessage()
			) );

			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Test connection to a provider
	 *
	 * @param string $provider_slug Provider slug
	 * @return array Test result
	 */
	public function test_connection( $provider_slug ) {
		$provider = $this->settings->get_provider_instance( $provider_slug );

		if ( ! $provider ) {
			return [
				'success' => false,
				'error'   => __( 'Invalid provider.', 'woo-smart-automation' ),
			];
		}

		if ( ! $this->settings->has_api_key( $provider_slug ) ) {
			return [
				'success' => false,
				'error'   => __( 'No API key configured for this provider.', 'woo-smart-automation' ),
			];
		}

		$start_time = microtime( true );
		$result = $provider->test_connection();
		$end_time = microtime( true );

		$result['latency_ms'] = round( ( $end_time - $start_time ) * 1000 );

		return $result;
	}

	/**
	 * Regenerate a specific section of a landing page
	 *
	 * @param string $html Existing HTML
	 * @param string $section_selector CSS selector for section
	 * @param string $instructions Regeneration instructions
	 * @return array Result with new HTML
	 */
	public function regenerate_section( $html, $section_selector, $instructions ) {
		// This is a placeholder for future section regeneration feature
		// For now, return error indicating feature is not yet available
		return [
			'success' => false,
			'error'   => __( 'Section regeneration is coming in a future update.', 'woo-smart-automation' ),
		];
	}

	/**
	 * Get estimated generation time based on current settings
	 *
	 * @return array ['min' => int, 'max' => int] in seconds
	 */
	public function get_estimated_time() {
		$mode = $this->settings->get_generation_mode();

		$estimates = [
			'fast'     => [ 'min' => 2, 'max' => 5 ],
			'balanced' => [ 'min' => 5, 'max' => 15 ],
			'quality'  => [ 'min' => 15, 'max' => 45 ],
		];

		return $estimates[ $mode ] ?? $estimates['balanced'];
	}

	/**
	 * Get generation history for a landing page
	 *
	 * @param int $landing_page_id Landing page post ID
	 * @return array Generation history
	 */
	public function get_generation_history( $landing_page_id ) {
		$meta = get_post_meta( $landing_page_id, '_wsa_generation_history', true );
		return is_array( $meta ) ? $meta : [];
	}

	/**
	 * Save generation metadata
	 *
	 * @param int $landing_page_id Landing page post ID
	 * @param array $generation_data Generation data to save
	 * @return bool
	 */
	public function save_generation_meta( $landing_page_id, $generation_data ) {
		$meta = [
			'provider'      => $generation_data['provider_used'] ?? '',
			'model'         => $generation_data['model_used'] ?? '',
			'tokens_used'   => $generation_data['tokens_used'] ?? 0,
			'generated_at'  => current_time( 'mysql' ),
			'fallback_used' => $generation_data['fallback_used'] ?? false,
		];

		update_post_meta( $landing_page_id, '_wsa_generation_meta', $meta );

		// Add to history
		$history = $this->get_generation_history( $landing_page_id );
		array_unshift( $history, $meta );
		$history = array_slice( $history, 0, 10 ); // Keep last 10 generations
		update_post_meta( $landing_page_id, '_wsa_generation_history', $history );

		return true;
	}
}
