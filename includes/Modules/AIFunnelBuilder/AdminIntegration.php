<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Integration
 * 
 * Handles all admin UI components for the AI Funnel Builder.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class AdminIntegration {

	/**
	 * Settings manager instance
	 *
	 * @var AISettingsManager
	 */
	private $settings_manager;

	/**
	 * Funnel manager instance
	 *
	 * @var FunnelManager
	 */
	private $funnel_manager;

	/**
	 * Landing page builder instance
	 *
	 * @var LandingPageBuilder
	 */
	private $landing_page_builder;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = new AISettingsManager();
		$this->funnel_manager = new FunnelManager();
		$this->landing_page_builder = new LandingPageBuilder();
	}

	/**
	 * Initialize admin integration
	 */
	public function init() {
		// Note: Menu registration is handled by AdminMenu.php

		// Note: Asset enqueuing is handled by AdminMenu.php via enqueue_funnel_builder_assets()

		// Register AJAX handlers
		$this->register_ajax_handlers();

		// Add admin notices
		add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
	}

	/**
	 * Register admin menu (kept for backward compatibility but not called by default)
	 */
	public function register_menu() {
		add_submenu_page(
			'woo-smart-automation',
			__( 'AI Funnel Builder', 'woo-smart-automation' ),
			__( 'AI Funnel Builder', 'woo-smart-automation' ),
			'manage_woocommerce',
			'wsa-funnel-builder',
			[ $this, 'render_main_page' ]
		);

		add_submenu_page(
			'woo-smart-automation',
			__( 'AI Settings', 'woo-smart-automation' ),
			__( 'AI Settings', 'woo-smart-automation' ),
			'manage_woocommerce',
			'wsa-ai-settings',
			[ $this, 'render_ai_settings_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our pages
		if ( strpos( $hook, 'wsa-funnel-builder' ) === false && 
		     strpos( $hook, 'wsa-ai-settings' ) === false ) {
			return;
		}

		// Enqueue WordPress media uploader
		wp_enqueue_media();

		// Enqueue our CSS
		wp_enqueue_style(
			'wsa-funnel-builder',
			WSA_URL . 'assets/css/funnel-builder.css',
			[],
			WSA_VERSION
		);

		// Enqueue our JS
		wp_enqueue_script(
			'wsa-funnel-builder',
			WSA_URL . 'assets/js/funnel-builder.js',
			[ 'jquery', 'jquery-ui-sortable', 'wp-util' ],
			WSA_VERSION,
			true
		);

		// Localize script
		wp_localize_script( 'wsa-funnel-builder', 'wsaFunnelBuilder', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wsa_funnel_builder' ),
			'i18n'      => [
				'generating'     => __( 'Generating landing page...', 'woo-smart-automation' ),
				'saving'         => __( 'Saving...', 'woo-smart-automation' ),
				'saved'          => __( 'Saved successfully!', 'woo-smart-automation' ),
				'error'          => __( 'An error occurred.', 'woo-smart-automation' ),
				'confirmDelete'  => __( 'Are you sure you want to delete this?', 'woo-smart-automation' ),
				'testSuccess'    => __( 'Connection successful!', 'woo-smart-automation' ),
				'testFailed'     => __( 'Connection failed.', 'woo-smart-automation' ),
				'uploadImage'    => __( 'Upload Image', 'woo-smart-automation' ),
				'removeImage'    => __( 'Remove', 'woo-smart-automation' ),
			],
			'providers' => AISettingsManager::PROVIDERS,
			'models'    => AISettingsManager::MODELS,
		] );
	}

	/**
	 * Register AJAX handlers
	 */
	private function register_ajax_handlers() {
		// AI Settings
		add_action( 'wp_ajax_wsa_save_ai_settings', [ $this, 'ajax_save_ai_settings' ] );
		add_action( 'wp_ajax_wsa_test_ai_connection', [ $this, 'ajax_test_ai_connection' ] );

		// Funnel operations
		add_action( 'wp_ajax_wsa_create_funnel', [ $this, 'ajax_create_funnel' ] );
		add_action( 'wp_ajax_wsa_update_funnel', [ $this, 'ajax_update_funnel' ] );
		add_action( 'wp_ajax_wsa_delete_funnel', [ $this, 'ajax_delete_funnel' ] );
		add_action( 'wp_ajax_wsa_duplicate_funnel', [ $this, 'ajax_duplicate_funnel' ] );

		// Landing page operations
		add_action( 'wp_ajax_wsa_create_landing_page', [ $this, 'ajax_create_landing_page' ] );
		add_action( 'wp_ajax_wsa_generate_landing_page', [ $this, 'ajax_generate_landing_page' ] );
		add_action( 'wp_ajax_wsa_save_landing_page', [ $this, 'ajax_save_landing_page' ] );
		add_action( 'wp_ajax_wsa_delete_landing_page', [ $this, 'ajax_delete_landing_page' ] );
		add_action( 'wp_ajax_wsa_publish_landing_page', [ $this, 'ajax_publish_landing_page' ] );

		// Image upload
		add_action( 'wp_ajax_wsa_upload_image', [ $this, 'ajax_upload_image' ] );
	}

	/**
	 * Render main funnel builder page
	 */
	public function render_main_page() {
		$action = sanitize_key( $_GET['action'] ?? 'list' );

		switch ( $action ) {
			case 'create':
				$this->render_funnel_create_page();
				break;

			case 'edit':
				$this->render_funnel_edit_page();
				break;

			case 'landing-page':
				$this->render_landing_page_builder();
				break;

			default:
				$this->render_funnel_list_page();
		}
	}

	/**
	 * Render funnel list page
	 */
	private function render_funnel_list_page() {
		$funnels = $this->funnel_manager->get_funnels();
		include WSA_PATH . 'templates/admin/funnel-list.php';
	}

	/**
	 * Render funnel create page
	 */
	private function render_funnel_create_page() {
		$products = $this->get_products_for_dropdown();
		include WSA_PATH . 'templates/admin/funnel-create.php';
	}

	/**
	 * Render funnel edit page
	 */
	private function render_funnel_edit_page() {
		$funnel_id = absint( $_GET['id'] ?? 0 );
		$funnel = $this->funnel_manager->get_funnel( $funnel_id );

		if ( ! $funnel ) {
			wp_die( __( 'Funnel not found.', 'woo-smart-automation' ) );
		}

		$products = $this->get_products_for_dropdown();
		include WSA_PATH . 'templates/admin/funnel-edit.php';
	}

	/**
	 * Render landing page builder
	 */
	private function render_landing_page_builder() {
		$page_id = absint( $_GET['id'] ?? 0 );
		$funnel_id = absint( $_GET['funnel_id'] ?? 0 );

		$page = null;
		if ( $page_id ) {
			$page = $this->landing_page_builder->get_landing_page( $page_id );
			$funnel_id = $page['funnel_id'] ?? $funnel_id;
		}

		$funnel = $this->funnel_manager->get_funnel( $funnel_id );
		$products = $this->get_products_for_dropdown();

		// Get templates
		$template_library = new PromptTemplateLibrary();
		$templates = $template_library->get_templates();

		// Auto-seed built-in templates if none exist
		if ( empty( $templates ) ) {
			try {
				\WooSmartAutomation\Core\Database::maybe_install();
				PromptTemplateLibrary::seed_builtin_templates();
				$templates = $template_library->get_templates();
			} catch ( \Exception $e ) {
				// Ignore DB errors
			}
		}

		// Final fallback: use hardcoded definitions if DB still empty
		if ( empty( $templates ) ) {
			$templates = PromptTemplateLibrary::get_builtin_template_definitions();
		}

		include WSA_PATH . 'templates/admin/landing-page-builder.php';
	}

	/**
	 * Render AI settings page
	 */
	public function render_ai_settings_page() {
		$current_provider = $this->settings_manager->get_provider();
		$current_model = $this->settings_manager->get_model();
		$providers = AISettingsManager::PROVIDERS;
		$models = AISettingsManager::MODELS;

		// Check which providers have API keys configured
		$configured_providers = [];
		$provider_api_keys = [];
		foreach ( array_keys( $providers ) as $provider ) {
			$api_key = $this->settings_manager->get_api_key( $provider );
			$configured_providers[ $provider ] = ! empty( $api_key );
			$provider_api_keys[ $provider ] = $api_key;
		}

		// Current provider's API key for pre-filling the form
		$current_api_key = $provider_api_keys[ $current_provider ] ?? '';

		include WSA_PATH . 'templates/admin/ai-settings.php';
	}

	/**
	 * Get products for dropdown
	 *
	 * @return array Products
	 */
	private function get_products_for_dropdown() {
		$products = wc_get_products( [
			'limit'  => 100,
			'status' => 'publish',
		] );

		$options = [];
		foreach ( $products as $product ) {
			$options[ $product->get_id() ] = $product->get_name() . ' (' . wc_price( $product->get_price() ) . ')';
		}

		return $options;
	}

	/**
	 * AJAX: Save AI settings
	 */
	public function ajax_save_ai_settings() {
		$this->verify_ajax_request();

		$provider = sanitize_key( $_POST['provider'] ?? '' );
		$model = sanitize_text_field( $_POST['model'] ?? '' );
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

		if ( empty( $provider ) ) {
			wp_send_json_error( [ 'message' => __( 'Provider is required.', 'woo-smart-automation' ) ] );
		}

		// Save settings
		if ( ! empty( $api_key ) ) {
			$this->settings_manager->save_api_key( $provider, $api_key );
		}

		$this->settings_manager->set_primary_provider( $provider );

		if ( ! empty( $model ) ) {
			$this->settings_manager->set_model( $provider, $model );
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved successfully.', 'woo-smart-automation' ) ] );
	}

	/**
	 * AJAX: Test AI connection
	 */
	public function ajax_test_ai_connection() {
		$this->verify_ajax_request();

		$provider = sanitize_key( $_POST['provider'] ?? '' );
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		$model = sanitize_text_field( $_POST['model'] ?? '' );

		if ( empty( $provider ) ) {
			wp_send_json_error( [ 'message' => __( 'Provider is required.', 'woo-smart-automation' ) ] );
		}

		// Use provided API key, or fall back to saved key
		if ( ! empty( $api_key ) ) {
			$this->settings_manager->save_api_key( $provider, $api_key );
		} else {
			$api_key = $this->settings_manager->get_api_key( $provider );
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'API key is required. Please enter your API key and save first.', 'woo-smart-automation' ) ] );
		}

		// Save model selection
		if ( ! empty( $model ) ) {
			$this->settings_manager->set_model( $provider, $model );
		}

		// Get provider instance and test connection
		$provider_instance = $this->settings_manager->get_provider_instance( $provider );

		if ( ! $provider_instance ) {
			wp_send_json_error( [ 'message' => __( 'Invalid provider.', 'woo-smart-automation' ) ] );
		}

		try {
			$result = $provider_instance->test_connection();

			if ( $result['success'] ) {
				wp_send_json_success( [ 'message' => $result['message'] ?? __( 'Connection successful!', 'woo-smart-automation' ) ] );
			} else {
				wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Connection failed.', 'woo-smart-automation' ) ] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX: Create funnel
	 */
	public function ajax_create_funnel() {
		$this->verify_ajax_request();

		$data = [
			'title'           => sanitize_text_field( $_POST['title'] ?? '' ),
			'products'        => array_map( 'absint', $_POST['products'] ?? [] ),
			'primary_product' => absint( $_POST['primary_product'] ?? 0 ),
			'active'          => isset( $_POST['active'] ),
		];

		$funnel_id = $this->funnel_manager->create_funnel( $data );

		if ( is_wp_error( $funnel_id ) ) {
			wp_send_json_error( [ 'message' => $funnel_id->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'   => __( 'Funnel created successfully.', 'woo-smart-automation' ),
			'funnel_id' => $funnel_id,
			'redirect'  => admin_url( 'admin.php?page=wsa-funnel-builder&action=edit&id=' . $funnel_id ),
		] );
	}

	/**
	 * AJAX: Update funnel
	 */
	public function ajax_update_funnel() {
		$this->verify_ajax_request();

		$funnel_id = absint( $_POST['funnel_id'] ?? 0 );

		if ( ! $funnel_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid funnel ID.', 'woo-smart-automation' ) ] );
		}

		$data = [
			'title'            => sanitize_text_field( $_POST['title'] ?? '' ),
			'products'         => array_map( 'absint', $_POST['products'] ?? [] ),
			'primary_product'  => absint( $_POST['primary_product'] ?? 0 ),
			'checkout_fields'  => $_POST['checkout_fields'] ?? null,
			'button_text'      => sanitize_text_field( $_POST['button_text'] ?? '' ),
			'button_color'     => sanitize_hex_color( $_POST['button_color'] ?? '' ),
			'button_text_color'=> sanitize_hex_color( $_POST['button_text_color'] ?? '' ),
			'active'           => isset( $_POST['active'] ),
		];

		$result = $this->funnel_manager->update_funnel( $funnel_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'Funnel updated successfully.', 'woo-smart-automation' ) ] );
	}

	/**
	 * AJAX: Delete funnel
	 */
	public function ajax_delete_funnel() {
		$this->verify_ajax_request();

		$funnel_id = absint( $_POST['funnel_id'] ?? 0 );

		if ( ! $funnel_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid funnel ID.', 'woo-smart-automation' ) ] );
		}

		$result = $this->funnel_manager->delete_funnel( $funnel_id );

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete funnel.', 'woo-smart-automation' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Funnel deleted successfully.', 'woo-smart-automation' ) ] );
	}

	/**
	 * AJAX: Duplicate funnel
	 */
	public function ajax_duplicate_funnel() {
		$this->verify_ajax_request();

		$funnel_id = absint( $_POST['funnel_id'] ?? 0 );

		if ( ! $funnel_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid funnel ID.', 'woo-smart-automation' ) ] );
		}

		$new_funnel_id = $this->funnel_manager->duplicate_funnel( $funnel_id );

		if ( is_wp_error( $new_funnel_id ) ) {
			wp_send_json_error( [ 'message' => $new_funnel_id->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'   => __( 'Funnel duplicated successfully.', 'woo-smart-automation' ),
			'funnel_id' => $new_funnel_id,
			'redirect'  => admin_url( 'admin.php?page=wsa-funnel-builder&action=edit&id=' . $new_funnel_id ),
		] );
	}

	/**
	 * AJAX: Generate landing page content
	 */
	public function ajax_generate_landing_page() {
		$this->verify_ajax_request();

		// Allow long execution for AI generation
		@set_time_limit( 300 );
		@ini_set( 'max_execution_time', 300 );

		// Ensure WordPress allows long HTTP requests
		add_filter( 'http_request_timeout', function() { return 150; } );

		$product_id    = absint( $_POST['product_id'] ?? 0 );
		$template_slug = sanitize_key( $_POST['template'] ?? ( $_POST['template_id'] ?? '' ) );
		$custom_prompt = sanitize_textarea_field( $_POST['custom_prompt'] ?? '' );
		$images        = $_POST['images'] ?? [];
		$funnel_id     = absint( $_POST['funnel_id'] ?? 0 );

		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Please select a product.', 'woo-smart-automation' ) ] );
		}

		try {
			// Generate using AI engine with settings manager
			$engine = new AIGenerationEngine( $this->settings_manager );
			$result = $engine->generate( [
				'product_id'    => $product_id,
				'template_slug' => $template_slug,
				'custom_prompt' => $custom_prompt,
				'images'        => $images,
				'funnel_id'     => $funnel_id,
			] );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			}

			if ( ! $result['success'] ) {
				wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Generation failed.', 'woo-smart-automation' ) ] );
			}

			wp_send_json_success( [
				'html'     => $result['html'] ?? '',
				'meta'     => $result['meta'] ?? [],
				'provider' => $result['provider_used'] ?? '',
				'model'    => $result['model'] ?? '',
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX: Save landing page
	 */
	public function ajax_save_landing_page() {
		$this->verify_ajax_request();

		$page_id    = absint( $_POST['page_id'] ?? 0 );
		$funnel_id  = absint( $_POST['funnel_id'] ?? 0 );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$title      = sanitize_text_field( $_POST['title'] ?? '' );
		$status     = sanitize_key( $_POST['status'] ?? 'draft' );

		// Auto-create a funnel if product is set but no funnel exists
		if ( ! $funnel_id && $product_id ) {
			$product = wc_get_product( $product_id );
			$funnel_title = $product
				? sprintf( __( 'Funnel: %s', 'woo-smart-automation' ), $product->get_name() )
				: ( $title ?: __( 'Auto Funnel', 'woo-smart-automation' ) );

			$funnel_id = $this->funnel_manager->create_funnel( [
				'title'           => $funnel_title,
				'products'        => [ $product_id ],
				'primary_product' => $product_id,
				'active'          => true,
			] );

			if ( is_wp_error( $funnel_id ) ) {
				$funnel_id = 0;
			}
		}

		$data = [
			'title'        => $title,
			'slug'         => sanitize_title( $_POST['slug'] ?? '' ),
			'status'       => $status,
			'funnel_id'    => $funnel_id,
			'product_id'   => $product_id,
			'variation_id' => absint( $_POST['variation_id'] ?? 0 ),
			'quantity'     => absint( $_POST['quantity'] ?? 1 ),
			'html'         => $_POST['html'] ?? '',
			'prompt'       => sanitize_textarea_field( $_POST['prompt'] ?? '' ),
			'template'     => sanitize_key( $_POST['template'] ?? '' ),
			'images'       => $_POST['images'] ?? [],
		];

		if ( $page_id ) {
			$result = $this->landing_page_builder->update_landing_page( $page_id, $data );
		} else {
			$page_id = $this->landing_page_builder->create_landing_page( $data );
			$result = ! is_wp_error( $page_id );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Failed to save landing page.', 'woo-smart-automation' );
			wp_send_json_error( [ 'message' => $message ] );
		}

		// If publishing, also set status
		if ( $status === 'publish' ) {
			$this->landing_page_builder->publish( $page_id );
		}

		$page = $this->landing_page_builder->get_landing_page( $page_id );

		wp_send_json_success( [
			'message'   => __( 'Landing page saved successfully.', 'woo-smart-automation' ),
			'page_id'   => $page_id,
			'funnel_id' => $funnel_id,
			'url'       => $page['url'] ?? '',
		] );
	}

	/**
	 * AJAX: Delete landing page
	 */
	public function ajax_delete_landing_page() {
		$this->verify_ajax_request();

		$page_id = absint( $_POST['page_id'] ?? 0 );

		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid page ID.', 'woo-smart-automation' ) ] );
		}

		$result = $this->landing_page_builder->delete_landing_page( $page_id );

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete landing page.', 'woo-smart-automation' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Landing page deleted successfully.', 'woo-smart-automation' ) ] );
	}

	/**
	 * AJAX: Publish landing page
	 */
	public function ajax_publish_landing_page() {
		$this->verify_ajax_request();

		$page_id = absint( $_POST['page_id'] ?? 0 );

		if ( ! $page_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid page ID.', 'woo-smart-automation' ) ] );
		}

		$result = $this->landing_page_builder->publish( $page_id );

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to publish landing page. Make sure content is generated.', 'woo-smart-automation' ) ] );
		}

		$page = $this->landing_page_builder->get_landing_page( $page_id );

		wp_send_json_success( [
			'message' => __( 'Landing page published successfully.', 'woo-smart-automation' ),
			'url'     => $page['url'] ?? '',
		] );
	}

	/**
	 * AJAX: Upload image
	 */
	public function ajax_upload_image() {
		$this->verify_ajax_request();

		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No image uploaded.', 'woo-smart-automation' ) ] );
		}

		$image_handler = new ImageHandler();
		$result = $image_handler->upload_image( $_FILES['image'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Show admin notices
	 */
	public function show_admin_notices() {
		$screen = get_current_screen();

		if ( strpos( $screen->id, 'wsa-funnel-builder' ) === false ) {
			return;
		}

		// Check if AI is configured
		if ( ! $this->settings_manager->is_configured() ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						__( 'AI Funnel Builder requires an AI provider to be configured. <a href="%s">Configure AI Settings</a>', 'woo-smart-automation' ),
						admin_url( 'admin.php?page=wsa-ai-settings' )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Verify AJAX request
	 */
	private function verify_ajax_request() {
		if ( ! check_ajax_referer( 'wsa_funnel_builder', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'woo-smart-automation' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'woo-smart-automation' ) ] );
		}
	}
}
