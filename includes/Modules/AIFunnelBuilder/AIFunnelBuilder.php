<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Funnel Builder Module Bootstrap
 * 
 * Creates AI-generated landing pages connected to customizable WooCommerce checkout funnels.
 * Handles module initialization, CPT registration, and component loading.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class AIFunnelBuilder {

	/**
	 * Module version
	 */
	const VERSION = '1.0.0';

	/**
	 * Singleton instance
	 *
	 * @var AIFunnelBuilder|null
	 */
	private static $instance = null;

	/**
	 * Settings manager instance
	 *
	 * @var AISettingsManager
	 */
	private $settings_manager;

	/**
	 * Generation engine instance
	 *
	 * @var AIGenerationEngine
	 */
	private $generation_engine;

	/**
	 * Funnel manager instance
	 *
	 * @var FunnelManager
	 */
	private $funnel_manager;

	/**
	 * Auto cart injector instance
	 *
	 * @var AutoCartInjector
	 */
	private $auto_cart_injector;

	/**
	 * Preview renderer instance
	 *
	 * @var PreviewRenderer
	 */
	private $preview_renderer;

	/**
	 * Get singleton instance
	 *
	 * @return AIFunnelBuilder
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->define_constants();
	}

	/**
	 * Define module constants
	 */
	private function define_constants() {
		if ( ! defined( 'WSA_AI_BUILDER_VERSION' ) ) {
			define( 'WSA_AI_BUILDER_VERSION', self::VERSION );
		}
		if ( ! defined( 'WSA_AI_BUILDER_PATH' ) ) {
			define( 'WSA_AI_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
		}
	}

	/**
	 * Initialize the module
	 */
	public function init() {
		$this->load_dependencies();
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		$base_path = WSA_AI_BUILDER_PATH;

		// Core classes
		require_once $base_path . 'AISettingsManager.php';
		require_once $base_path . 'AIGenerationEngine.php';
		require_once $base_path . 'OutputValidator.php';

		// Provider classes
		require_once $base_path . 'Providers/AIProviderInterface.php';
		require_once $base_path . 'Providers/GeminiProvider.php';
		require_once $base_path . 'Providers/OpenAIProvider.php';
		require_once $base_path . 'Providers/DeepSeekProvider.php';
		require_once $base_path . 'Providers/HuggingFaceProvider.php';

		// Builder classes
		require_once $base_path . 'FunnelManager.php';
		require_once $base_path . 'LandingPageBuilder.php';
		require_once $base_path . 'CheckoutBuilder.php';
		require_once $base_path . 'PromptTemplateLibrary.php';
		require_once $base_path . 'ImageHandler.php';

		// Frontend classes
		require_once $base_path . 'PreviewRenderer.php';
		require_once $base_path . 'AutoCartInjector.php';

		// Admin
		if ( is_admin() ) {
			require_once $base_path . 'AdminIntegration.php';
		}
	}

	/**
	 * Initialize component instances
	 */
	private function init_components() {
		$this->settings_manager   = new AISettingsManager();
		$this->generation_engine  = new AIGenerationEngine( $this->settings_manager );
		$this->funnel_manager     = new FunnelManager();
		$this->auto_cart_injector = new AutoCartInjector();
		$this->preview_renderer   = new PreviewRenderer();

		// Initialize admin if in admin area
		if ( is_admin() ) {
			$admin = new AdminIntegration();
			$admin->init();
		}
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks() {
		// Register Custom Post Types
		add_action( 'init', [ $this, 'register_post_types' ] );

		// Template override for landing pages
		add_filter( 'template_include', [ $this, 'landing_page_template' ] );

		// Initialize frontend components
		$this->auto_cart_injector->init();
		add_action( 'template_redirect', [ $this->preview_renderer, 'handle_preview_mode' ] );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Always register checkout AJAX handler (for both logged-in and guest users)
		$checkout_builder = new CheckoutBuilder();
		$checkout_builder->init();

		// Initialize checkout builder on landing page (for additional hooks)
		add_action( 'wp', [ $this, 'maybe_init_custom_checkout' ] );
	}

	/**
	 * Register Custom Post Types for Funnels and Landing Pages
	 */
	public function register_post_types() {
		// Funnel CPT
		register_post_type( 'wsa_funnel', [
			'labels' => [
				'name'               => __( 'Funnels', 'woo-smart-automation' ),
				'singular_name'      => __( 'Funnel', 'woo-smart-automation' ),
				'add_new'            => __( 'Add New Funnel', 'woo-smart-automation' ),
				'add_new_item'       => __( 'Add New Funnel', 'woo-smart-automation' ),
				'edit_item'          => __( 'Edit Funnel', 'woo-smart-automation' ),
				'new_item'           => __( 'New Funnel', 'woo-smart-automation' ),
				'view_item'          => __( 'View Funnel', 'woo-smart-automation' ),
				'search_items'       => __( 'Search Funnels', 'woo-smart-automation' ),
				'not_found'          => __( 'No funnels found', 'woo-smart-automation' ),
				'not_found_in_trash' => __( 'No funnels found in trash', 'woo-smart-automation' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'custom-fields' ],
			'rewrite'             => false,
		] );

		// Landing Page CPT
		register_post_type( 'wsa_landing_page', [
			'labels' => [
				'name'               => __( 'Landing Pages', 'woo-smart-automation' ),
				'singular_name'      => __( 'Landing Page', 'woo-smart-automation' ),
				'add_new'            => __( 'Add New Landing Page', 'woo-smart-automation' ),
				'add_new_item'       => __( 'Add New Landing Page', 'woo-smart-automation' ),
				'edit_item'          => __( 'Edit Landing Page', 'woo-smart-automation' ),
				'new_item'           => __( 'New Landing Page', 'woo-smart-automation' ),
				'view_item'          => __( 'View Landing Page', 'woo-smart-automation' ),
				'search_items'       => __( 'Search Landing Pages', 'woo-smart-automation' ),
				'not_found'          => __( 'No landing pages found', 'woo-smart-automation' ),
				'not_found_in_trash' => __( 'No landing pages found in trash', 'woo-smart-automation' ),
			],
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'query_var'           => true,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'custom-fields' ],
			'rewrite'             => [ 'slug' => 'lp', 'with_front' => false ],
		] );

		// Flush rewrite rules if needed
		if ( get_option( 'wsa_ai_funnel_flush_rewrite' ) !== 'done' ) {
			flush_rewrite_rules();
			update_option( 'wsa_ai_funnel_flush_rewrite', 'done' );
		}
	}

	/**
	 * Override template for landing pages
	 *
	 * @param string $template Current template path
	 * @return string Modified template path
	 */
	public function landing_page_template( $template ) {
		if ( is_singular( 'wsa_landing_page' ) ) {
			$custom_template = WSA_PATH . 'templates/landing-page.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Enqueue frontend assets for landing pages
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_singular( 'wsa_landing_page' ) ) {
			return;
		}

		// Landing page specific styles
		wp_enqueue_style(
			'wsa-landing-page',
			WSA_URL . 'assets/css/landing-page.css',
			[],
			WSA_AI_BUILDER_VERSION
		);

		// Checkout form handler
		wp_enqueue_script(
			'wsa-funnel-checkout',
			WSA_URL . 'assets/js/funnel-checkout.js',
			[ 'jquery' ],
			WSA_AI_BUILDER_VERSION,
			true
		);

		wp_localize_script( 'wsa-funnel-checkout', 'wsa_funnel', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wsa_funnel_checkout' ),
		] );
	}

	/**
	 * Initialize custom checkout if on funnel checkout page
	 */
	public function maybe_init_custom_checkout() {
		if ( ! is_singular( 'wsa_landing_page' ) ) {
			return;
		}

		$post_id   = get_the_ID();
		$funnel_id = get_post_meta( $post_id, '_wsa_funnel_id', true );

		if ( ! $funnel_id ) {
			return;
		}

		$checkout_builder = new CheckoutBuilder( $funnel_id );
		$checkout_builder->init();
	}

	/**
	 * Get settings manager instance
	 *
	 * @return AISettingsManager
	 */
	public function get_settings_manager() {
		return $this->settings_manager;
	}

	/**
	 * Get generation engine instance
	 *
	 * @return AIGenerationEngine
	 */
	public function get_generation_engine() {
		return $this->generation_engine;
	}

	/**
	 * Get funnel manager instance
	 *
	 * @return FunnelManager
	 */
	public function get_funnel_manager() {
		return $this->funnel_manager;
	}
}
