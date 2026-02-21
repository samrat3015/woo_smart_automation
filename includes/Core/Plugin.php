<?php
namespace WooSmartAutomation\Core;

/**
 * The Main Plugin Class
 */
class Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'woo-smart-automation';
		$this->version     = WSA_VERSION;

		// Defer check until all plugins are loaded
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}

	public function on_plugins_loaded() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		$this->load_dependencies();
		$this->check_db_updates(); // Ensure DB is ready
		$this->define_admin_hooks();
		$this->define_cron_hooks();
		
		// Only load modules if license is active
		if ( $this->is_license_active() ) {
			$this->load_modules();
		} else {
			// Show admin notice for inactive license
			add_action( 'admin_notices', [ $this, 'license_inactive_notice' ] );
		}
	}

	private function check_db_updates() {
		\WooSmartAutomation\Core\Database::maybe_install();
	}

	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if license is active
	 * 
	 * @return bool
	 */
	private function is_license_active() {
		return LicenseManager::is_license_active();
	}

	public function woocommerce_missing_notice() {
		?>
		<div class="error">
			<p><?php _e( 'Woo Smart Automation requires WooCommerce to be installed and active.', 'woo-smart-automation' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show notice when license is inactive
	 */
	public function license_inactive_notice() {
		$screen = get_current_screen();
		
		// Don't show on our own license page
		if ( $screen && $screen->id === 'toplevel_page_woo-smart-automation' ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong>Woo Smart Automation:</strong> 
				<?php _e( 'Your license is not activated. All features are currently disabled.', 'woo-smart-automation' ); ?>
				<a href="<?php echo admin_url( 'admin.php?page=woo-smart-automation' ); ?>"><?php _e( 'Activate License', 'woo-smart-automation' ); ?></a>
			</p>
		</div>
		<?php
	}

	private function load_dependencies() {
		require_once WSA_PATH . 'includes/Core/Database.php';
		require_once WSA_PATH . 'includes/Core/Ajax.php';
		require_once WSA_PATH . 'includes/Core/Assets.php';
		require_once WSA_PATH . 'includes/Core/LicenseManager.php';
		
		// Initialize Ajax handlers
		$ajax = new Ajax();
		$ajax->init();

		// Initialize License Manager AJAX handlers
		$license_manager = new LicenseManager();
		$license_manager->init();

		// Initialize central Assets (order table styles etc.)
		$assets = new Assets();
		$assets->init();
	}

	private function define_admin_hooks() {
		// Initialize Admin Menu
		require_once WSA_PATH . 'includes/Admin/AdminMenu.php';
		$admin = new \WooSmartAutomation\Admin\AdminMenu();
		
		add_action( 'admin_menu', [ $admin, 'add_plugin_admin_menu' ] );
		add_action( 'admin_init', [ $admin, 'register_settings' ] );

		// Custom Links on Plugins Page
		add_filter( 'plugin_action_links_' . plugin_basename( WSA_FILE ), [ $this, 'add_settings_link' ] );
		
		// Register Admin-only AJAX handlers
		add_action( 'wp_ajax_wsa_update_status', [ $admin, 'handle_update_status' ] );
		add_action( 'wp_ajax_wsa_update_admin_note', [ $admin, 'handle_update_admin_note' ] );
		add_action( 'wp_ajax_wsa_run_recovery', [ $admin, 'handle_run_recovery' ] );
		
		// Database Creation on Activation/Deactivation
		register_activation_hook( WSA_FILE, [ 'WooSmartAutomation\Core\Database', 'activate' ] );
		register_deactivation_hook( WSA_FILE, [ 'WooSmartAutomation\Core\Database', 'deactivate' ] );
	}

	private function define_cron_hooks() {
		add_action( 'wsa_cleanup_old_orders', [ 'WooSmartAutomation\Core\Database', 'cleanup_old_orders' ] );
	}

	/**
	 * Load Feature Modules
	 */
	private function load_modules() {
		// 1. Incomplete Order Module
		$incomplete_enabled = get_option( 'wsa_incomplete_order_enabled', 'yes' ); // Default to yes
		
		if ( 'yes' === $incomplete_enabled ) {
			require_once WSA_PATH . 'includes/Modules/IncompleteOrder/IncompleteOrder.php';
			$incomplete_order = new \WooSmartAutomation\Modules\IncompleteOrder\IncompleteOrder();
			$incomplete_order->init();
		}

		// 2. Meta Conversions API Module
		$meta_capi_enabled = get_option( 'wsa_meta_capi_enabled', 'yes' );
		
		if ( 'yes' === $meta_capi_enabled ) {
			require_once WSA_PATH . 'includes/Modules/MetaCAPI/MetaCAPI.php';
			$meta_capi = new \WooSmartAutomation\Modules\MetaCAPI\MetaCAPI();
			$meta_capi->init();
		}

		// 3. Courier Webhook Module
		$courier_enabled = get_option( 'wsa_courier_webhook_enabled', 'yes' );
		
		if ( 'yes' === $courier_enabled ) {
			require_once WSA_PATH . 'includes/Modules/Courier/Courier.php';
			$courier = new \WooSmartAutomation\Modules\Courier\Courier();
			$courier->init();
		}

		// 4. Product Analytics Module
		$analytics_enabled = get_option( 'wsa_product_analytics_enabled', 'yes' );
		if ( 'yes' === $analytics_enabled ) {
			require_once WSA_PATH . 'includes/Modules/ProductAnalytics/ProductAnalytics.php';
			$analytics = new \WooSmartAutomation\Modules\ProductAnalytics\ProductAnalytics();
			$analytics->init();
		}

		// 5. Customer Segmentation Module (also handles Order Trust Badge column)
		$segmentation_enabled = get_option( 'wsa_segmentation_enabled', 'no' );
		$trust_badge_enabled = get_option( 'wsa_order_trust_badging_enabled', 'yes' );
		
		// Load module if either feature is enabled
		if ( 'yes' === $segmentation_enabled || 'yes' === $trust_badge_enabled ) {
			require_once WSA_PATH . 'includes/Modules/CustomerSegmentation/CustomerSegmentation.php';
			$segmentation = new \WooSmartAutomation\Modules\CustomerSegmentation\CustomerSegmentation();
			$segmentation->init();
		}

		// 6. Fake Customer Detection Module
		$fake_detection_enabled = \get_option( 'wsa_fake_detection_enabled', 'yes' );
		if ( 'yes' === $fake_detection_enabled ) {
			require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/FakeCustomerDetection.php';
			$fake_detection = new \WooSmartAutomation\Modules\FakeCustomerDetection\FakeCustomerDetection();
			$fake_detection->init();
		}

		// 7. Smart Device Block Module
		$device_block_enabled = \get_option( 'wsa_device_block_enabled', 'yes' );
		if ( 'yes' === $device_block_enabled ) {
			require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/DeviceBlock.php';
			$device_block = new \WooSmartAutomation\Modules\FakeCustomerDetection\DeviceBlock();
			$device_block->init();
		}

		// 8. SMS Notification Module
		$sms_enabled = get_option( 'wsa_sms_module_enabled', 'yes' );
		if ( 'yes' === $sms_enabled ) {
			$sms_file = WSA_PATH . 'includes/Modules/SMS/SMSModule.php';
			if ( file_exists( $sms_file ) ) {
				require_once $sms_file;
				if ( class_exists( 'WooSmartAutomation\Modules\SMS\SMSModule' ) ) {
					$sms_module = new \WooSmartAutomation\Modules\SMS\SMSModule();
					$sms_module->init();
				}
			}
		}

		// 9. Order Restriction Module
		$order_restriction_enabled = get_option( 'wsa_order_restriction_enabled', 'no' );
		if ( 'yes' === $order_restriction_enabled ) {
			require_once WSA_PATH . 'includes/Modules/OrderRestriction/OrderRestriction.php';
			$order_restriction = new \WooSmartAutomation\Modules\OrderRestriction\OrderRestriction();
			$order_restriction->init();
		}

		// Future: License Module
	}

	public function run() {
		// Trigger actions if any specific run logic allows
		// Most things happen in __construct or init() of modules
	}

	/**
	 * Add Settings link to Plugins page
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=wsa-settings">' . __( 'Settings', 'woo-smart-automation' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
