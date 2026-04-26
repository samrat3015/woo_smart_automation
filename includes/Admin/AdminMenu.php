<?php
namespace WooSmartAutomation\Admin;

use WooSmartAutomation\Core\LicenseManager;

class AdminMenu {

	public function add_plugin_admin_menu() {
		$hook = add_menu_page(
			'Woo Smart Shield',
			'Woo Smart Shield',
			'manage_options',
			'woo-smart-automation',
			[ $this, 'display_license_page' ],
			'dashicons-superhero',
			50
		);

		// License page as the main page
		add_submenu_page(
			'woo-smart-automation',
			'License',
			'🔑 License',
			'manage_options',
			'woo-smart-automation',
			[ $this, 'display_license_page' ]
		);

		$incomplete_orders_hook = add_submenu_page(
			'woo-smart-automation',
			'Incomplete Orders',
			'Incomplete Orders',
			'manage_options',
			'wsa-incomplete-orders',
			[ $this, 'display_incomplete_orders_page' ]
		);

		$settings_hook = add_submenu_page(
			'woo-smart-automation',
			'Settings',
			'Settings',
			'manage_options',
			'wsa-settings',
			[ $this, 'display_plugin_setup_page' ]
		);

		$blocked_devices_hook = add_submenu_page(
			'woo-smart-automation',
			'Blocked Devices',
			'🛡️ Blocked Devices',
			'manage_options',
			'wsa-blocked-devices',
			[ $this, 'display_blocked_devices_page' ]
		);

		add_submenu_page(
			'woo-smart-automation',
			'Documentation',
			'Documentation',
			'manage_options',
			'wsa-docs',
			[ $this, 'display_docs_page' ]
		);

		// Enqueue assets for our pages
		add_action( 'admin_print_styles-' . $hook, [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_print_styles-' . $incomplete_orders_hook, [ $this, 'enqueue_incomplete_orders_assets' ] );
		add_action( 'admin_print_styles-' . $settings_hook, [ $this, 'enqueue_settings_assets' ] );
		add_action( 'admin_print_styles-' . $blocked_devices_hook, [ $this, 'enqueue_blocked_devices_assets' ] );
	}

	public function enqueue_admin_assets() {
		\wp_enqueue_style( 'wsa-google-fonts', 'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&display=swap', [], WSA_VERSION );
		\wp_enqueue_style( 'wsa-admin-css', WSA_URL . 'assets/css/admin.css', ['wsa-google-fonts'], WSA_VERSION );
	}

	public function enqueue_incomplete_orders_assets() {
		\wp_enqueue_style( 'wsa-google-fonts', 'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&display=swap', [], WSA_VERSION );
		\wp_enqueue_style( 'wsa-admin-css', WSA_URL . 'assets/css/admin.css', ['wsa-google-fonts'], WSA_VERSION );
		\wp_enqueue_style( 'wsa-incomplete-orders-css', WSA_URL . 'assets/css/incomplete-orders.css', ['wsa-admin-css'], WSA_VERSION );
	}

	public function enqueue_settings_assets() {
		\wp_enqueue_style( 'wsa-google-fonts', 'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&display=swap', [], WSA_VERSION );
		\wp_enqueue_style( 'wsa-admin-css', WSA_URL . 'assets/css/admin.css', ['wsa-google-fonts'], WSA_VERSION );
		\wp_enqueue_style( 'wsa-settings-css', WSA_URL . 'assets/css/settings.css', ['wsa-admin-css'], WSA_VERSION );
	}

	public function enqueue_blocked_devices_assets() {
		\wp_enqueue_style( 'wsa-google-fonts', 'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&display=swap', [], WSA_VERSION );
		\wp_enqueue_style( 'wsa-admin-css', WSA_URL . 'assets/css/admin.css', ['wsa-google-fonts'], WSA_VERSION );
		\wp_enqueue_style( 'wsa-device-block-css', WSA_URL . 'assets/css/device-block.css', ['wsa-admin-css'], WSA_VERSION );
		\wp_enqueue_script( 'wsa-device-block-js', WSA_URL . 'assets/js/device-block-admin.js', ['jquery'], WSA_VERSION, true );
		\wp_localize_script( 'wsa-device-block-js', 'wsaDeviceBlock', [
			'ajax_url' => \admin_url( 'admin-ajax.php' ),
			'nonce'    => \wp_create_nonce( 'wsa_device_block' ),
			'i18n'     => [
				'confirm_title'   => \__( 'Block This Customer?', 'woo-smart-automation' ),
				'confirm_message' => \__( 'Are you sure you want to block this customer?', 'woo-smart-automation' ),
				'block_btn'       => \__( 'Yes, Block', 'woo-smart-automation' ),
				'cancel_btn'      => \__( 'Cancel', 'woo-smart-automation' ),
				'blocking'        => \__( 'Blocking...', 'woo-smart-automation' ),
				'unblocking'      => \__( 'Unblocking...', 'woo-smart-automation' ),
				'unblock_confirm' => \__( 'Are you sure you want to unblock this customer?', 'woo-smart-automation' ),
				'success_blocked' => \__( 'Customer has been blocked successfully!', 'woo-smart-automation' ),
				'success_unblock' => \__( 'Customer has been unblocked successfully!', 'woo-smart-automation' ),
				'error'           => \__( 'An error occurred. Please try again.', 'woo-smart-automation' ),
			],
		] );
	}

	/**
	 * Display License Activation Page
	 */
	public function display_license_page() {
		$is_active = LicenseManager::is_license_active();
		$license_key = LicenseManager::get_license_key();
		$license_data = LicenseManager::get_license_data();
		$status_text = LicenseManager::get_status_text();
		$status_class = LicenseManager::get_status_class();
		$current_domain = LicenseManager::get_current_domain();
		?>
		<div class="wrap wsa-license-wrap">
			<h1>🔑 License Activation</h1>
			<p>Activate your license to unlock all premium features of Woo Smart Shield.</p>

			<div class="wsa-license-container">
				<?php if ( $is_active && $license_data ) : ?>
					<!-- License Active State -->
					<div class="wsa-license-card wsa-license-active">
						<div class="wsa-license-header">
							<span class="wsa-license-icon">✅</span>
							<div>
								<h2>License Activated</h2>
								<span class="wsa-license-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
							</div>
						</div>

						<div class="wsa-license-details">
							<div class="wsa-license-info-grid">
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">License Key</span>
									<span class="wsa-info-value"><code><?php echo esc_html( $license_key ); ?></code></span>
								</div>
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">Product</span>
									<span class="wsa-info-value"><?php echo esc_html( isset( $license_data['product'] ) ? $license_data['product'] : 'N/A' ); ?></span>
								</div>
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">Plan</span>
									<span class="wsa-info-value wsa-plan-badge"><?php echo esc_html( isset( $license_data['plan'] ) ? $license_data['plan'] : 'N/A' ); ?></span>
								</div>
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">License Type</span>
									<span class="wsa-info-value"><?php echo esc_html( isset( $license_data['type'] ) ? ucfirst( $license_data['type'] ) : 'N/A' ); ?></span>
								</div>
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">Domain</span>
									<span class="wsa-info-value"><?php echo esc_html( isset( $license_data['domain'] ) ? $license_data['domain'] : $current_domain ); ?></span>
								</div>
								<div class="wsa-license-info-item">
									<span class="wsa-info-label">Expires</span>
									<span class="wsa-info-value"><?php echo isset( $license_data['expires_at'] ) && $license_data['expires_at'] ? esc_html( $license_data['expires_at'] ) : 'Never (Lifetime)'; ?></span>
								</div>
							</div>

							<?php if ( isset( $license_data['features'] ) && is_array( $license_data['features'] ) ) : ?>
								<div class="wsa-license-features">
									<h3>🎁 Included Features</h3>
									<ul class="wsa-features-list">
										<?php foreach ( $license_data['features'] as $feature ) : ?>
											<li><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( $feature ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						</div>

						<!-- <div class="wsa-license-actions">
							<button type="button" id="wsa-deactivate-license" class="button button-secondary">
								<span class="dashicons dashicons-no"></span> Deactivate License
							</button>
							<button type="button" id="wsa-refresh-license" class="button button-primary">
								<span class="dashicons dashicons-update"></span> Refresh License
							</button>
						</div> -->
					</div>

				<?php else : ?>
					<!-- License Inactive State -->
					<div class="wsa-license-card wsa-license-inactive">
						<div class="wsa-license-header">
							<span class="wsa-license-icon">🔒</span>
							<div>
								<h2>Activate Your License</h2>
								<p>Enter your license key to unlock all features</p>
							</div>
						</div>

						<div class="wsa-license-form">
							<div class="wsa-form-group">
								<label for="wsa-license-key">License Key</label>
								<input type="text" 
									   id="wsa-license-key" 
									   class="wsa-license-input" 
									   placeholder="LIC-XXXX-XXXX-XXXX-XXXX"
									   value="<?php echo esc_attr( $license_key ); ?>"
								/>
								<p class="description">Enter the license key you received after purchase.</p>
							</div>

							<div class="wsa-form-group">
								<label>Domain</label>
								<input type="text" class="wsa-domain-display" value="<?php echo esc_attr( $current_domain ); ?>" disabled />
								<p class="description">Your license will be activated for this domain.</p>
							</div>

							<div id="wsa-license-message" class="wsa-license-message" style="display: none;"></div>

							<button type="button" id="wsa-activate-license" class="button button-primary button-hero">
								<span class="dashicons dashicons-yes"></span> Activate License
							</button>
						</div>

						<div class="wsa-license-notice">
							<p>⚠️ <strong>All features are locked</strong> until you activate a valid license.</p>
							<p>Don't have a license? <a href="https://yourwebsite.com/pricing" target="_blank">Purchase one here</a></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo wp_create_nonce( 'wsa_license_nonce' ); ?>';

			// Activate License
			$('#wsa-activate-license').on('click', function() {
				var $btn = $(this);
				var licenseKey = $('#wsa-license-key').val().trim();
				var $message = $('#wsa-license-message');

				if (!licenseKey) {
					$message.removeClass('success').addClass('error').text('Please enter a license key.').show();
					return;
				}

				$btn.prop('disabled', true).text('Activating...');
				$message.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wsa_activate_license',
						license_key: licenseKey,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							$message.removeClass('error').addClass('success').text(response.data.message).show();
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							$message.removeClass('success').addClass('error').text(response.data.message).show();
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Activate License');
						}
					},
					error: function() {
						$message.removeClass('success').addClass('error').text('Connection error. Please try again.').show();
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Activate License');
					}
				});
			});

			// Deactivate License
			$('#wsa-deactivate-license').on('click', function() {
				if (!confirm('Are you sure you want to deactivate your license? All features will be locked.')) {
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('Deactivating...');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wsa_deactivate_license',
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Failed to deactivate license.');
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Deactivate License');
						}
					},
					error: function() {
						alert('Connection error. Please try again.');
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Deactivate License');
					}
				});
			});

			// Refresh License
			$('#wsa-refresh-license').on('click', function() {
				var $btn = $(this);
				var licenseKey = '<?php echo esc_js( $license_key ); ?>';

				$btn.prop('disabled', true).text('Refreshing...');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wsa_activate_license',
						license_key: licenseKey,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Failed to refresh: ' + response.data.message);
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh License');
						}
					},
					error: function() {
						alert('Connection error. Please try again.');
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh License');
					}
				});
			});
		});
		</script>
		<?php
	}

	public function register_settings() {
		register_setting( 'wsa_settings_group', 'wsa_incomplete_order_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_incomplete_order_retention_days' );
		register_setting( 'wsa_settings_group', 'wsa_meta_capi_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_pixel_id' );
		register_setting( 'wsa_settings_group', 'wsa_meta_access_token' );
		register_setting( 'wsa_settings_group', 'wsa_meta_test_event_code' );
		
		// Meta Events Auto Mapping
		register_setting( 'wsa_settings_group', 'wsa_meta_event_pageview_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_event_viewcontent_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_event_addtocart_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_event_initiatecheckout_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_event_purchase_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_meta_purchase_event_condition' );

		register_setting( 'wsa_settings_group', 'wsa_courier_webhook_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_steadfast_webhook_token' );
		register_setting( 'wsa_settings_group', 'wsa_pathao_webhook_token' );
		register_setting( 'wsa_settings_group', 'wsa_steadfast_status_map', [
			'type' => 'array',
			'sanitize_callback' => [ $this, 'sanitize_steadfast_status_map' ],
		] );
		register_setting( 'wsa_settings_group', 'wsa_pathao_status_map', [
			'type' => 'array',
			'sanitize_callback' => [ $this, 'sanitize_pathao_status_map' ],
		] );
		register_setting( 'wsa_settings_group', 'wsa_product_analytics_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_segmentation_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_fake_detection_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_trust_badges_html' );
		register_setting( 'wsa_settings_group', 'wsa_loyalty_msg_html' );
		register_setting( 'wsa_settings_group', 'wsa_order_trust_badging_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_device_block_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_order_restriction_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_order_restriction_limit' );
		register_setting( 'wsa_settings_group', 'wsa_order_restriction_message' );

		// Auto Action Settings for Fake Detection
		register_setting( 'wsa_settings_group', 'wsa_auto_action_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_auto_action_score' );
		register_setting( 'wsa_settings_group', 'wsa_auto_action_status' );

		// SMS Gateway Settings
		register_setting( 'wsa_settings_group', 'wsa_sms_module_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_bulksmsbd_api_key' );
		register_setting( 'wsa_settings_group', 'wsa_bulksmsbd_sender_id' );
		register_setting( 'wsa_settings_group', 'wsa_sms_order_confirm_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_sms_order_confirm_template' );
		register_setting( 'wsa_settings_group', 'wsa_sms_abandoned_cart_enabled' );
		register_setting( 'wsa_settings_group', 'wsa_sms_abandoned_cart_delay' );
		register_setting( 'wsa_settings_group', 'wsa_sms_abandoned_cart_template' );

		// Packzy API Settings
		register_setting( 'wsa_settings_group', 'wsa_packzy_api_key' );
		register_setting( 'wsa_settings_group', 'wsa_packzy_secret_key' );
	}

	/**
	 * Sanitize Steadfast status map - apply defaults for empty values
	 */
	public function sanitize_steadfast_status_map( $input ) {
		$defaults = [
			'pending'                    => 'pending-payment',
			'delivered'                  => 'completed',
			'partial_delivered'          => 'processing',
			'cancelled'                  => 'cancelled',
			'unknown'                    => '',
			'delivered_approval'         => '',
		];

		$sanitized = [];
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $input[ $key ] ) && $input[ $key ] !== '' ) {
				$sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
			} else {
				// Use default if empty or not set
				$sanitized[ $key ] = $default_value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize Pathao status map - apply defaults for empty values
	 */
	public function sanitize_pathao_status_map( $input ) {
		$defaults = [
			'order.delivered'        => 'completed',
			'order.pickup-cancelled' => 'cancelled',
			'order.returned'         => 'refunded',
			'order.created'          => 'processing',
			'order.picked-up'        => 'processing',
			'order.in-transit'       => 'processing',
		];

		$sanitized = [];
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $input[ $key ] ) && $input[ $key ] !== '' ) {
				$sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
			} else {
				// Use default if empty or not set
				$sanitized[ $key ] = $default_value;
			}
		}

		return $sanitized;
	}

	public function display_plugin_setup_page() {
		// Check if license is active
		$is_licensed = LicenseManager::is_license_active();
		
		if ( ! $is_licensed ) {
			$this->display_license_required_notice();
			return;
		}

		// Get WooCommerce statuses for mapping
		$wc_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
		
		// Pathao defaults and mapping
		$pathao_defaults = [
			'order.delivered'        => 'completed',
			'order.pickup-cancelled' => 'cancelled',
			'order.returned'         => 'refunded',
			'order.created'          => 'processing',
			'order.picked-up'        => 'processing',
			'order.in-transit'       => 'processing'
		];
		$pathao_map = get_option( 'wsa_pathao_status_map', [] );
		$pathao_events = [
			'order.delivered'        => 'Delivered',
			'order.pickup-cancelled' => 'Pickup Cancelled',
			'order.returned'         => 'Returned',
			'order.created'          => 'Order Created',
			'order.picked-up'        => 'Picked Up',
			'order.in-transit'       => 'In Transit'
		];

		// Steadfast defaults and mapping
		$steadfast_defaults = [
			'pending'                    => 'pending-payment',
			'delivered'                  => 'completed',
			'partial_delivered'          => 'processing',
			'cancelled'                  => 'cancelled',
			'unknown'                    => '',
			'delivered_approval'         => '',
		];
		$steadfast_map = get_option( 'wsa_steadfast_status_map', [] );
		$steadfast_statuses = [
			'pending'                    => 'Pending',
			'delivered'                  => 'Delivered',
			'partial_delivered'          => 'Partial Delivered',
			'cancelled'                  => 'Cancelled',
			'unknown'                    => 'Unknown',
			'delivered_approval'         => 'Delivered_approval',
		];

		// Webhook tokens
		$pathao_token = get_option( 'wsa_pathao_webhook_token' );
		if ( ! $pathao_token ) {
			$pathao_token = wp_generate_password( 32, false );
			update_option( 'wsa_pathao_webhook_token', $pathao_token );
		}
		$steadfast_token = get_option( 'wsa_steadfast_webhook_token' );
		if ( ! $steadfast_token ) {
			$steadfast_token = wp_generate_password( 32, false );
			update_option( 'wsa_steadfast_webhook_token', $steadfast_token );
		}

		// SMS Balance
		$sms_balance = 'N/A';
		$sms_service_file = WSA_PATH . 'includes/Modules/SMS/BulkSMSBDService.php';
		if ( file_exists( $sms_service_file ) ) {
			require_once $sms_service_file;
		}

		if ( class_exists( 'WooSmartAutomation\Modules\SMS\BulkSMSBDService' ) ) {
			$sms_service = new \WooSmartAutomation\Modules\SMS\BulkSMSBDService();
			$balance = $sms_service->get_balance();
			if ( $balance !== false ) {
				$sms_balance = $balance . ' BDT';
			}
		}
		?>
		<div class="wsa-settings-page">
			<!-- Header -->
			<div class="settings-header">
				<h1>⚙️ Settings</h1>
				<p>Configure your Woo Smart Shield modules and integrations.</p>
			</div>

			<form method="post" action="options.php" class="settings-form">
				<?php settings_fields( 'wsa_settings_group' ); ?>
				<?php do_settings_sections( 'wsa_settings_group' ); ?>

				<!-- Tab Navigation -->
				<div class="settings-tabs">
					<button type="button" class="settings-tab active" data-tab="modules">📦 Modules</button>
					
					<button type="button" class="settings-tab" data-tab="incomplete" style="<?php echo get_option( 'wsa_incomplete_order_enabled', 'yes' ) !== 'yes' ? 'display:none' : ''; ?>">📋 Incomplete Orders</button>
					<button type="button" class="settings-tab" data-tab="meta" style="<?php echo get_option( 'wsa_meta_capi_enabled', 'yes' ) !== 'yes' ? 'display:none' : ''; ?>">📊 Meta CAPI</button>
					<button type="button" class="settings-tab" data-tab="courier" style="<?php echo get_option( 'wsa_courier_webhook_enabled', 'yes' ) !== 'yes' ? 'display:none' : ''; ?>">🚚 Courier Tracking</button>
					<button type="button" class="settings-tab" data-tab="fake" style="<?php echo get_option( 'wsa_fake_detection_enabled', 'yes' ) !== 'yes' ? 'display:none' : ''; ?>">🛡️ Fake Detection</button>
					<button type="button" class="settings-tab" data-tab="sms" style="<?php echo get_option( 'wsa_sms_module_enabled', 'yes' ) !== 'yes' ? 'display:none' : ''; ?>">💬 SMS Gateway</button>
					<button type="button" class="settings-tab" data-tab="segmentation" style="<?php echo get_option( 'wsa_segmentation_enabled', 'no' ) !== 'yes' ? 'display:none' : ''; ?>">👥 Personalization</button>
					<button type="button" class="settings-tab" data-tab="restriction" style="<?php echo get_option( 'wsa_order_restriction_enabled', 'no' ) !== 'yes' ? 'display:none' : ''; ?>">⏳ Order Restriction</button>
				</div>

				<div class="tab-content">
					<!-- Modules Tab -->
					<div id="tab-modules" class="tab-pane active">

				<!-- Modules Section -->
				<div class="settings-section">
					<div class="settings-section-header">
						<div class="settings-section-icon icon-modules">📦</div>
						<div class="settings-section-title">
							<h2>Modules</h2>
							<p>Enable or disable automation features</p>
						</div>
					</div>
					<div class="settings-section-body">
						<div class="modules-grid">
							<!-- Incomplete Order Capture -->
							<div class="module-card <?php echo get_option( 'wsa_incomplete_order_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">📋</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Incomplete Order Capture</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_incomplete_order_enabled" value="yes" id="wsa_incomplete_order_toggle" <?php checked( get_option( 'wsa_incomplete_order_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Automatically capture customer data when they type in checkout fields.</p>
								</div>
							</div>

							<!-- Meta CAPI -->
							<div class="module-card <?php echo get_option( 'wsa_meta_capi_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">📊</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Meta Conversions API</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_meta_capi_enabled" value="yes" id="wsa_meta_capi_toggle" <?php checked( get_option( 'wsa_meta_capi_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Send server-side events to Meta for better ad tracking.</p>
								</div>
							</div>

							<!-- Courier Webhooks -->
							<div class="module-card <?php echo get_option( 'wsa_courier_webhook_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">🚚</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Courier Webhooks</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_courier_webhook_enabled" value="yes" id="wsa_courier_toggle" <?php checked( get_option( 'wsa_courier_webhook_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Auto-update order status via Pathao & Steadfast webhooks.</p>
								</div>
							</div>

							<!-- Product Analytics -->
							<div class="module-card <?php echo get_option( 'wsa_product_analytics_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">📈</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Product Interest Score</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_product_analytics_enabled" value="yes" <?php checked( get_option( 'wsa_product_analytics_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Calculate engagement scores (View, ATC, Checkout, Purchase).</p>
								</div>
							</div>

							<!-- Customer Segmentation -->
							<div class="module-card <?php echo get_option( 'wsa_segmentation_enabled', 'no' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">👥</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Customer Segmentation</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_segmentation_enabled" value="yes" id="wsa_segmentation_toggle" <?php checked( get_option( 'wsa_segmentation_enabled', 'no' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Show different content for first-time vs returning buyers.</p>
								</div>
							</div>

							<!-- Fake Detection -->
							<div class="module-card <?php echo get_option( 'wsa_fake_detection_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">🛡️</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Fake Customer Detection</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_fake_detection_enabled" value="yes" id="wsa_fake_detection_toggle" <?php checked( get_option( 'wsa_fake_detection_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Calculate risk scores for orders and flag suspicious ones.</p>
								</div>
							</div>

							<!-- SMS Notification -->
							<div class="module-card <?php echo get_option( 'wsa_sms_module_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">💬</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">SMS Notification</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_sms_module_enabled" value="yes" id="wsa_sms_toggle" <?php checked( get_option( 'wsa_sms_module_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Send automated order confirmation SMS to your customers.</p>
								</div>
							</div>

							<!-- Order Trust Badge -->
							<div class="module-card <?php echo \get_option( 'wsa_order_trust_badging_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">✅</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Order Trust Badge</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_order_trust_badging_enabled" value="yes" <?php \checked( \get_option( 'wsa_order_trust_badging_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Display 🛡️ Trust column in WooCommerce order list.</p>
								</div>
							</div>

							<!-- Smart Device Block -->
							<div class="module-card <?php echo \get_option( 'wsa_device_block_enabled', 'yes' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">🚫</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Smart Device Block</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_device_block_enabled" value="yes" <?php \checked( \get_option( 'wsa_device_block_enabled', 'yes' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Manually block suspicious customers by IP, Phone or Email.</p>
								</div>
							</div>

							<!-- Order Frequency Restriction -->
							<div class="module-card <?php echo \get_option( 'wsa_order_restriction_enabled', 'no' ) === 'yes' ? 'active' : ''; ?>">
								<div class="module-icon">⏳</div>
								<div class="module-content">
									<div class="module-header">
										<h3 class="module-name">Order Frequency Restriction</h3>
										<label class="toggle-switch">
											<input type="checkbox" name="wsa_order_restriction_enabled" value="yes" id="wsa_order_restriction_toggle" <?php \checked( \get_option( 'wsa_order_restriction_enabled', 'no' ), 'yes' ); ?> />
											<span class="toggle-slider"></span>
										</label>
									</div>
									<p class="module-description">Prevent same customer from ordering multiple times within a set time limit.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-modules -->

				<!-- Incomplete Order Settings Tab -->
				<div id="tab-incomplete" class="tab-pane">
				<!-- Incomplete Order Settings -->
				<div id="wsa-incomplete-order-settings" class="settings-section settings-conditional <?php echo get_option('wsa_incomplete_order_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">Auto-delete Orders After (Days)</label>
							<div class="field-input">
								<input type="number" name="wsa_incomplete_order_retention_days" value="<?php echo esc_attr( get_option( 'wsa_incomplete_order_retention_days', '30' ) ); ?>" class="settings-input" min="1" placeholder="e.g. 30" />
								<p class="field-description">Incomplete orders older than this will be automatically deleted. Default is 30 days.</p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-incomplete -->

				<!-- Personalization Tab -->
				<div id="tab-segmentation" class="tab-pane">
				<!-- Customer Segmentation Settings -->
				<div id="wsa-segmentation-settings" class="settings-section settings-conditional <?php echo get_option('wsa_segmentation_enabled', 'no') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">First-Time Buyer Content</label>
							<div class="field-input">
								<textarea name="wsa_trust_badges_html" class="settings-textarea" rows="4"><?php echo esc_textarea( get_option( 'wsa_trust_badges_html', '<div class="wsa-trust-box">✅ Verified Store • 🚚 Fast Shipping • 🛡️ Secure Checkout</div>' ) ); ?></textarea>
								<p class="field-description">HTML content to show new visitors (trust badges, guarantees, etc.)</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Returning Buyer Content</label>
							<div class="field-input">
								<textarea name="wsa_loyalty_msg_html" class="settings-textarea" rows="4"><?php echo esc_textarea( get_option( 'wsa_loyalty_msg_html', '<div class="wsa-loyalty-box">Welcome back! ❤️ Thanks for being a loyal customer. Enjoy your shopping!</div>' ) ); ?></textarea>
								<p class="field-description">HTML content to show customers who have purchased before.</p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-segmentation -->

				<!-- Order Restriction Tab -->
				<div id="tab-restriction" class="tab-pane">
				<!-- Order Restriction Settings -->
				<div id="wsa-order-restriction-settings" class="settings-section settings-conditional <?php echo get_option('wsa_order_restriction_enabled', 'no') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">Time Limit (Minutes)</label>
							<div class="field-input">
								<input type="number" name="wsa_order_restriction_limit" value="<?php echo esc_attr( get_option( 'wsa_order_restriction_limit', '30' ) ); ?>" class="settings-input" min="1" placeholder="e.g. 30" />
								<p class="field-description">Minimum time required between two orders from the same customer. Default: 30 minutes.</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Restriction Message</label>
							<div class="field-input">
								<textarea name="wsa_order_restriction_message" class="settings-textarea" rows="4" placeholder="You have already placed an order recently. Please wait {time} minutes."><?php echo esc_textarea( get_option( 'wsa_order_restriction_message', '' ) ); ?></textarea>
								<p class="field-description">Message to show at checkout when restricted. Use <code>{time}</code> to show remaining minutes. Leave empty for default message.</p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-restriction -->

				<!-- Fake Detection Tab -->
				<div id="tab-fake" class="tab-pane">
				<!-- Fake Customer Detection Settings -->
				<div id="wsa-fake-detection-settings" class="settings-section settings-conditional <?php echo get_option('wsa_fake_detection_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">Enable Auto-Action</label>
							<div class="field-input">
								<label class="toggle-switch">
									<input type="checkbox" name="wsa_auto_action_enabled" value="yes" <?php checked( get_option( 'wsa_auto_action_enabled', 'no' ), 'yes' ); ?> />
									<span class="toggle-slider"></span>
								</label>
								<p class="field-description">Automatically change order status based on risk score.</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Risk Score Threshold</label>
							<div class="field-input">
								<input type="number" name="wsa_auto_action_score" value="<?php echo esc_attr( get_option( 'wsa_auto_action_score', '20' ) ); ?>" class="settings-input" min="0" max="100" />
								<p class="field-description">If the customer's delivery score is <strong>BELOW</strong> this number (Low Delivery Rate), the auto-action will trigger.</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Target Order Status</label>
							<div class="field-input">
								<select name="wsa_auto_action_status" class="settings-select">
									<?php foreach ( $wc_statuses as $status_key => $status_label ) : ?>
										<option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>" <?php selected( get_option( 'wsa_auto_action_status', 'on-hold' ), str_replace( 'wc-', '', $status_key ) ); ?>>
											<?php echo esc_html( $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="field-description">The status to set when the delivery score is below the threshold.</p>
							</div>
						</div>

						<!-- Steadfast API Settings -->
						<div class="settings-section-divider"></div>
						<div class="settings-field-row">
							<label class="field-label">Steadfast API Key</label>
							<div class="field-input">
								<input type="password" name="wsa_packzy_api_key" value="<?php echo esc_attr( get_option( 'wsa_packzy_api_key' ) ); ?>" class="settings-input input-password" placeholder="e.g. rh1x4wtsiv..." />
								<p class="field-description">Required for Steadfast courier intelligence data.</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Steadfast Secret Key</label>
							<div class="field-input">
								<input type="password" name="wsa_packzy_secret_key" value="<?php echo esc_attr( get_option( 'wsa_packzy_secret_key' ) ); ?>" class="settings-input input-password" placeholder="e.g. bmbcmaovqx..." />
								<p class="field-description">Your Steadfast Secret Key for API authorization.</p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-fake -->

				<!-- Meta CAPI Tab -->
				<div id="tab-meta" class="tab-pane">
				<!-- Meta CAPI Settings -->
				<div id="wsa-meta-settings" class="settings-section settings-conditional <?php echo get_option('wsa_meta_capi_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">Meta Pixel ID <span class="required">*</span></label>
							<div class="field-input">
								<input type="text" name="wsa_meta_pixel_id" value="<?php echo esc_attr( get_option( 'wsa_meta_pixel_id' ) ); ?>" class="settings-input" placeholder="e.g. 123456789012345" />
								<p class="field-description">Found in Events Manager → Data Sources</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Access Token <span class="required">*</span></label>
							<div class="field-input">
								<input type="password" name="wsa_meta_access_token" value="<?php echo esc_attr( get_option( 'wsa_meta_access_token' ) ); ?>" class="settings-input input-password" placeholder="EAAB..." />
								<p class="field-description">Generate in Meta Events Manager → Settings → Conversions API</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Test Event Code</label>
							<div class="field-input">
								<input type="text" name="wsa_meta_test_event_code" value="<?php echo esc_attr( get_option( 'wsa_meta_test_event_code' ) ); ?>" class="settings-input" placeholder="e.g. TEST12345" />
								<p class="field-description">Optional — Only use when testing with Meta's "Test Events" tool</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Meta Events Settings -->
				<div id="wsa-meta-events" class="settings-section settings-conditional <?php echo get_option('wsa_meta_capi_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-header">
						<div class="settings-section-icon icon-events">✅</div>
						<div class="settings-section-title">
							<h2>Meta Events Tracking</h2>
							<p>Choose which events to send to Meta</p>
						</div>
					</div>
					<div class="settings-section-body">
						<div class="checkbox-group">
							<label class="checkbox-item <?php echo get_option( 'wsa_meta_event_pageview_enabled', 'yes' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_meta_event_pageview_enabled" value="yes" <?php checked( get_option( 'wsa_meta_event_pageview_enabled', 'yes' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">PageView</span>
									<span class="checkbox-desc">Fire on all pages</span>
								</div>
							</label>
							<label class="checkbox-item <?php echo get_option( 'wsa_meta_event_viewcontent_enabled', 'yes' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_meta_event_viewcontent_enabled" value="yes" <?php checked( get_option( 'wsa_meta_event_viewcontent_enabled', 'yes' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">ViewContent</span>
									<span class="checkbox-desc">Fire on product page</span>
								</div>
							</label>
							<label class="checkbox-item <?php echo get_option( 'wsa_meta_event_addtocart_enabled', 'yes' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_meta_event_addtocart_enabled" value="yes" <?php checked( get_option( 'wsa_meta_event_addtocart_enabled', 'yes' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">AddToCart</span>
									<span class="checkbox-desc">Fire when add to cart clicked</span>
								</div>
							</label>
							<label class="checkbox-item <?php echo get_option( 'wsa_meta_event_initiatecheckout_enabled', 'yes' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_meta_event_initiatecheckout_enabled" value="yes" <?php checked( get_option( 'wsa_meta_event_initiatecheckout_enabled', 'yes' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">InitiateCheckout</span>
									<span class="checkbox-desc">Fire on checkout page load</span>
								</div>
							</label>
							<label class="checkbox-item <?php echo get_option( 'wsa_meta_event_purchase_enabled', 'yes' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_meta_event_purchase_enabled" value="yes" <?php checked( get_option( 'wsa_meta_event_purchase_enabled', 'yes' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">Purchase</span>
									<span class="checkbox-desc">Fire on successful purchase</span>
								</div>
							</label>
						</div>

						<div class="settings-field-row" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--settings-gray-200);">
							<label class="field-label">Purchase Event Trigger</label>
							<div class="field-input">
								<select name="wsa_meta_purchase_event_condition" class="settings-select">
									<option value="place" <?php selected( get_option( 'wsa_meta_purchase_event_condition', 'place' ), 'place' ); ?>>Fire when order is placed (Thank you page)</option>
									<option value="completed" <?php selected( get_option( 'wsa_meta_purchase_event_condition', 'place' ), 'completed' ); ?>>Fire when order status is "Completed"</option>
								</select>
								<p class="field-description">Control when the Purchase event should be sent to Meta</p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-meta -->

				<!-- Courier Tracking Tab -->
				<div id="tab-courier" class="tab-pane">
				<!-- Courier Webhook Settings -->
				<div id="wsa-courier-settings" class="settings-section settings-conditional <?php echo get_option('wsa_courier_webhook_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">Pathao Webhook URL</label>
							<div class="field-input">
								<div class="settings-code">
									<code><?php echo get_rest_url( null, 'woo-smart-automation/v1/webhook/pathao' ); ?></code>
									<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo get_rest_url( null, 'woo-smart-automation/v1/webhook/pathao' ); ?>')">Copy</button>
								</div>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Pathao Token</label>
							<div class="field-input">
								<input type="text" name="wsa_pathao_webhook_token" value="<?php echo esc_attr( $pathao_token ); ?>" class="settings-input" />
								<p class="field-description">Copy this to your Pathao webhook configuration</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Steadfast Webhook URL</label>
							<div class="field-input">
								<div class="settings-code">
									<code><?php echo get_rest_url( null, 'woo-smart-automation/v1/webhook/steadfast' ); ?></code>
									<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo get_rest_url( null, 'woo-smart-automation/v1/webhook/steadfast' ); ?>')">Copy</button>
								</div>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Steadfast Token</label>
							<div class="field-input">
								<input type="text" name="wsa_steadfast_webhook_token" value="<?php echo esc_attr( $steadfast_token ); ?>" class="settings-input" />
								<p class="field-description">Copy this to the "Auth Token" field in Steadfast dashboard</p>
							</div>
						</div>

						<div class="settings-alert alert-warning">
							<span class="settings-alert-icon">💡</span>
							<div class="settings-alert-content">
								<strong>Tip</strong>
								<p>Select "-- No Action --" if you don't want the order status to change for a specific event.</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Pathao Status Mapping -->
				<div id="wsa-pathao-mapping" class="settings-section settings-conditional <?php echo get_option('wsa_courier_webhook_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-header">
						<div class="settings-section-icon icon-pathao">🗺️</div>
						<div class="settings-section-title">
							<h2>Pathao Status Mapping</h2>
							<p>Map Pathao events to WooCommerce order statuses</p>
						</div>
					</div>
					<div class="settings-section-body">
						<table class="mapping-table">
							<thead>
								<tr>
									<th>Pathao Event</th>
									<th>WooCommerce Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $pathao_events as $event_key => $event_label ) : 
									$current_value = ( isset( $pathao_map[$event_key] ) && ! empty( $pathao_map[$event_key] ) ) ? $pathao_map[$event_key] : (isset($pathao_defaults[$event_key]) ? $pathao_defaults[$event_key] : '');
								?>
								<tr>
									<td><span class="event-name"><?php echo esc_html( $event_label ); ?></span></td>
									<td>
										<select name="wsa_pathao_status_map[<?php echo esc_attr( $event_key ); ?>]" class="settings-select mapping-select">
											<option value="">-- No Action --</option>
											<?php foreach ( $wc_statuses as $status_key => $status_label ) : ?>
												<option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>" <?php selected( $current_value, str_replace( 'wc-', '', $status_key ) ); ?>>
													<?php echo esc_html( $status_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Steadfast Status Mapping -->
				<div id="wsa-steadfast-mapping" class="settings-section settings-conditional <?php echo get_option('wsa_courier_webhook_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-header">
						<div class="settings-section-icon icon-steadfast">🗺️</div>
						<div class="settings-section-title">
							<h2>Steadfast Status Mapping</h2>
							<p>Map Steadfast statuses to WooCommerce order statuses</p>
						</div>
					</div>
					<div class="settings-section-body">
						<table class="mapping-table">
							<thead>
								<tr>
									<th>Steadfast Status</th>
									<th>WooCommerce Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $steadfast_statuses as $st_key => $st_label ) : 
									$current_value = ( isset( $steadfast_map[$st_key] ) && ! empty( $steadfast_map[$st_key] ) ) ? $steadfast_map[$st_key] : (isset($steadfast_defaults[$st_key]) ? $steadfast_defaults[$st_key] : '');
								?>
								<tr>
									<td><span class="event-name"><?php echo esc_html( $st_label ); ?></span></td>
									<td>
										<select name="wsa_steadfast_status_map[<?php echo esc_attr( $st_key ); ?>]" class="settings-select mapping-select">
											<option value="">-- No Action --</option>
											<?php foreach ( $wc_statuses as $status_key => $status_label ) : ?>
												<option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>" <?php selected( $current_value, str_replace( 'wc-', '', $status_key ) ); ?>>
													<?php echo esc_html( $status_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				</div> <!-- End #tab-courier -->

				<!-- SMS Gateway Tab -->
				<div id="tab-sms" class="tab-pane">
				<!-- SMS Notification Settings -->
				<div id="wsa-sms-settings" class="settings-section settings-conditional <?php echo get_option('wsa_sms_module_enabled', 'yes') !== 'yes' ? 'hidden' : ''; ?>">
					<div class="settings-section-body">
						<div class="settings-field-row">
							<label class="field-label">BulkSMSBD API Key <span class="required">*</span></label>
							<div class="field-input">
								<input type="password" name="wsa_bulksmsbd_api_key" value="<?php echo esc_attr( get_option( 'wsa_bulksmsbd_api_key' ) ); ?>" class="settings-input input-password" placeholder="e.g. 7f8e9d0c..." />
								<p class="field-description">Your API Key from <a href="https://bulksmsbd.net/" target="_blank" style="color: #0073aa; text-decoration: none;">BulkSMSBD.net</a> Dashboard</p>
							</div>
						</div>
						<div class="settings-field-row">
							<label class="field-label">Sender ID</label>
							<div class="field-input">
								<input type="text" name="wsa_bulksmsbd_sender_id" value="<?php echo esc_attr( get_option( 'wsa_bulksmsbd_sender_id' ) ); ?>" class="settings-input" placeholder="e.g. 880123..." />
								<p class="field-description">Approved Sender ID or Name</p>
							</div>
						</div>

						<div class="checkbox-group" style="margin-top: 24px;">
							<label class="checkbox-item <?php echo get_option( 'wsa_sms_order_confirm_enabled', 'no' ) === 'yes' ? 'checked' : ''; ?>">
								<input type="checkbox" name="wsa_sms_order_confirm_enabled" value="yes" <?php checked( get_option( 'wsa_sms_order_confirm_enabled', 'no' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">Order Confirmation SMS</span>
									<span class="checkbox-desc">Send automated SMS after successful order placement</span>
								</div>
							</label>

							<label class="checkbox-item <?php echo get_option( 'wsa_sms_abandoned_cart_enabled', 'no' ) === 'yes' ? 'checked' : ''; ?>" style="margin-top: 10px;">
								<input type="checkbox" name="wsa_sms_abandoned_cart_enabled" value="yes" <?php checked( get_option( 'wsa_sms_abandoned_cart_enabled', 'no' ), 'yes' ); ?> />
								<div class="checkbox-content">
									<span class="checkbox-label">Abandoned Cart Recovery SMS</span>
									<span class="checkbox-desc">Send automated SMS to recover incomplete orders</span>
								</div>
							</label>
						</div>

						<div class="settings-field-row" id="wsa_abandoned_cart_delay_row" style="margin-top: 20px;">
							<label class="field-label">Recovery Delay (Minutes)</label>
							<div class="field-input">
								<input type="number" name="wsa_sms_abandoned_cart_delay" value="<?php echo esc_attr( get_option( 'wsa_sms_abandoned_cart_delay', '30' ) ); ?>" class="settings-input" min="1" />
								<p class="field-description">Wait for this many minutes after customer leaves before sending SMS. (Default: 30)</p>
							</div>
						</div>

						<div class="settings-field-row">
							<label class="field-label">Order Confirmation Template</label>
							<div class="field-input">
								<textarea name="wsa_sms_order_confirm_template" id="wsa_sms_template" class="settings-textarea" placeholder="Order #{order_id} confirmed."><?php echo esc_textarea( get_option( 'wsa_sms_order_confirm_template', 'Thank you for your order! Your Order ID is #{order_id}. Total: {order_total}' ) ); ?></textarea>
								<div class="sms-counter-wrap">
									<div class="sms-counter-left">
										<span class="sms-counter-item">Characters: <span class="sms-counter-value" id="sms-char-count">0</span></span>
									</div>
									<div class="sms-counter-right">
										<span class="sms-counter-item">SMS Parts: <span class="sms-counter-value" id="sms-parts-count">1</span></span>
									</div>
								</div>
								<p class="field-description">Placeholders: <code>{order_id}</code>, <code>{order_total}</code>, <code>{site_name}</code>, <code>{customer_name}</code></p>
							</div>
						</div>

						<div class="settings-field-row">
							<label class="field-label">Abandoned Cart Template</label>
							<div class="field-input">
								<textarea name="wsa_sms_abandoned_cart_template" id="wsa_sms_abandoned_template" class="settings-textarea" placeholder="Hi {customer_name}, you left something in your cart!"><?php echo esc_textarea( get_option( 'wsa_sms_abandoned_cart_template', 'Hi {customer_name}, you left some items in your cart at {site_name}. Complete your order now!' ) ); ?></textarea>
								<div class="sms-counter-wrap">
									<div class="sms-counter-left">
										<span class="sms-counter-item">Characters: <span class="sms-counter-value" id="sms-abandoned-char-count">0</span></span>
									</div>
									<div class="sms-counter-right">
										<span class="sms-counter-item">SMS Parts: <span class="sms-counter-value" id="sms-abandoned-parts-count">1</span></span>
									</div>
								</div>
								<p class="field-description">Placeholders: <code>{site_name}</code>, <code>{customer_name}</code></p>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End #tab-sms -->
				</div> <!-- End .tab-content -->

				<!-- Submit Button -->
				<div class="settings-submit-area">
					<button type="submit" class="settings-submit-btn">
						<span class="dashicons dashicons-saved"></span>
						Save Settings
					</button>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Toggle module cards active state
			$('.module-card input[type="checkbox"]').on('change', function() {
				$(this).closest('.module-card').toggleClass('active', $(this).is(':checked'));
			});

			// Toggle checkbox items checked state
			$('.checkbox-item input[type="checkbox"]').on('change', function() {
				$(this).closest('.checkbox-item').toggleClass('checked', $(this).is(':checked'));
			});

			// Meta CAPI toggle
			$('#wsa_meta_capi_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-meta-settings, #wsa-meta-events').removeClass('hidden').slideDown();
				} else {
					$('#wsa-meta-settings, #wsa-meta-events').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// Incomplete Order toggle
			$('#wsa_incomplete_order_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-incomplete-order-settings').removeClass('hidden').slideDown();
				} else {
					$('#wsa-incomplete-order-settings').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// Courier toggle
			$('#wsa_courier_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-courier-settings, #wsa-pathao-mapping, #wsa-steadfast-mapping').removeClass('hidden').slideDown();
				} else {
					$('#wsa-courier-settings, #wsa-pathao-mapping, #wsa-steadfast-mapping').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// Segmentation toggle
			$('#wsa_segmentation_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-segmentation-settings').removeClass('hidden').slideDown();
				} else {
					$('#wsa-segmentation-settings').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// Fake Detection toggle
			$('input[name="wsa_fake_detection_enabled"]').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-fake-detection-settings').removeClass('hidden').slideDown();
				} else {
					$('#wsa-fake-detection-settings').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// Order Restriction toggle
			$('#wsa_order_restriction_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-order-restriction-settings').removeClass('hidden').slideDown();
				} else {
					$('#wsa-order-restriction-settings').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// SMS toggle
			$('#wsa_sms_toggle').on('change', function() {
				if ($(this).is(':checked')) {
					$('#wsa-sms-settings').removeClass('hidden').slideDown();
				} else {
					$('#wsa-sms-settings').slideUp(function() {
						$(this).addClass('hidden');
					});
				}
			});

			// SMS Character Counter Logic
			function updateSMSCounter(textareaId, charCountId, partsCountId) {
				const $textarea = $(textareaId);
				if (!$textarea.length) return;

				const text = $textarea.val();
				const charCount = text.length;
				
				// Detection for Unicode characters
				const isUnicode = /[^\x00-\x7F]/.test(text);
				
				let limit = isUnicode ? 70 : 160;
				let multiLimit = isUnicode ? 67 : 153;
				
				let parts = 1;
				if (charCount > limit) {
					parts = Math.ceil(charCount / multiLimit);
				}

				$(charCountId).text(charCount);
				$(partsCountId).text(parts);

				// Visual warning
				if (parts > 1) {
					$(partsCountId).closest('.sms-counter-item').addClass('warning');
				} else {
					$(partsCountId).closest('.sms-counter-item').removeClass('warning');
				}
			}

			$('#wsa_sms_template').on('input focus', function() {
				updateSMSCounter('#wsa_sms_template', '#sms-char-count', '#sms-parts-count');
			});
			$('#wsa_sms_abandoned_template').on('input focus', function() {
				updateSMSCounter('#wsa_sms_abandoned_template', '#sms-abandoned-char-count', '#sms-abandoned-parts-count');
			});

			// Initial load
			updateSMSCounter('#wsa_sms_template', '#sms-char-count', '#sms-parts-count');
			updateSMSCounter('#wsa_sms_abandoned_template', '#sms-abandoned-char-count', '#sms-abandoned-parts-count');

			// Tab Switching Logic
			$('.settings-tab').on('click', function() {
				var target = $(this).data('tab');
				
				// Update headers/tabs
				$('.settings-tab').removeClass('active');
				$(this).addClass('active');
				
				// Show/Hide panes
				$('.tab-pane').removeClass('active').hide();
				$('#tab-' + target).addClass('active').fadeIn(200);
				
				// Store active tab if needed (optional)
				localStorage.setItem('wsa_active_tab', target);
			});

			// Real-time tab visibility toggle
			function toggleTabVisibility(checkboxId, tabDataValue) {
				$(checkboxId).on('change', function() {
					var $tab = $('.settings-tab[data-tab="' + tabDataValue + '"]');
					if ($(this).is(':checked')) {
						if ($tab.length === 0) {
							// If tab doesn't exist (because PHP didn't render it), we might need to refresh or handle it.
							// But for now, let's assume we want to hide it if it exists.
						}
						$tab.fadeIn();
					} else {
						$tab.fadeOut();
					}
				});
			}

			toggleTabVisibility('#wsa_incomplete_order_toggle', 'incomplete');
			toggleTabVisibility('#wsa_meta_capi_toggle', 'meta');
			toggleTabVisibility('#wsa_courier_toggle', 'courier');
			toggleTabVisibility('#wsa_fake_detection_toggle', 'fake');
			toggleTabVisibility('#wsa_sms_toggle', 'sms');
			toggleTabVisibility('#wsa_segmentation_toggle', 'segmentation');
			toggleTabVisibility('#wsa_order_restriction_toggle', 'restriction');

			// Restore active tab
			var activeTab = localStorage.getItem('wsa_active_tab');
			if (activeTab && $('.settings-tab[data-tab="' + activeTab + '"]').length) {
				$('.settings-tab[data-tab="' + activeTab + '"]').trigger('click');
			}
		});
		</script>
		<?php
	}

	/**
	 * Display License Required Notice
	 */
	public function display_license_required_notice() {
		?>
		<div class="wrap wsa-license-required">
			<div class="wsa-blocked-notice">
				<div class="wsa-blocked-icon">🔒</div>
				<h1>License Required</h1>
				<p>This feature requires an active license to access.</p>
				<p>Please activate your license to unlock all premium features of Woo Smart Shield.</p>
				<a href="<?php echo admin_url( 'admin.php?page=woo-smart-automation' ); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-admin-network"></span> Activate License
				</a>
			</div>
		</div>
		<?php
	}

	public function display_incomplete_orders_page() {
		// Check if license is active
		$is_licensed = LicenseManager::is_license_active();
		
		if ( ! $is_licensed ) {
			$this->display_license_required_notice();
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		
		// 1. Get Settings & Parameters
		$retention_days = (int) get_option( 'wsa_incomplete_order_retention_days', 30 );
		$status_filter  = isset( $_GET['status_filter'] ) ? sanitize_text_field( $_GET['status_filter'] ) : '';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page       = 20;
		$offset         = ( $paged - 1 ) * $per_page;

		// 2. Build Query
		$where_clauses = [ "created_at >= DATE_SUB(NOW(), INTERVAL $retention_days DAY)" ];
		
		if ( in_array( $status_filter, ['captured', 'converted', 'contacted', 'ignored'] ) ) {
			$where_clauses[] = $wpdb->prepare( "status = %s", $status_filter );
		}

		if ( ! empty( $search_query ) ) {
			$search_like = '%' . $wpdb->esc_like( $search_query ) . '%';
			$where_clauses[] = $wpdb->prepare( 
				"(first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR email LIKE %s)", 
				$search_like, $search_like, $search_like, $search_like 
			);
		}

		$where_sql = "WHERE " . implode( " AND ", $where_clauses );

		// 3. Fetch Data
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where_sql" );
		$total_pages = ceil( $total_items / $per_page );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		// Stats for cards
		$stats_query = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL $retention_days DAY) GROUP BY status" );
		$stats = ['captured' => 0, 'converted' => 0, 'contacted' => 0, 'ignored' => 0];
		$total_leads = 0;
		foreach($stats_query as $s) { 
			if (isset($stats[$s->status])) {
				$stats[$s->status] = $s->count; 
			}
			$total_leads += $s->count;
		}
		?>
		<div class="wsa-incomplete-orders">
			<div class="io-header">
				<div class="io-header-content">
					<h1>Incomplete Orders</h1>
					<p>Track and recover customers who left during checkout (Retention: <?php echo $retention_days; ?> days).</p>
				</div>
				<div class="io-header-actions">
					<form method="get" action="<?php echo admin_url('admin.php'); ?>" class="io-search-form">
						<input type="hidden" name="page" value="wsa-incomplete-orders" />
						<?php if ( $status_filter ) : ?>
							<input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>" />
						<?php endif; ?>
						<div class="io-search-box">
							<input type="text" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search name/phone/email..." />
							<button type="submit" class="button">Search</button>
						</div>
					</form>
					<button type="button" id="wsa-run-recovery-btn" class="button button-secondary">
						<span class="dashicons dashicons-update"></span> Run Recovery Check
					</button>
					<?php if ( ! empty( $status_filter ) || ! empty( $search_query ) ) : ?>
						<a href="<?php echo admin_url('admin.php?page=wsa-incomplete-orders'); ?>" class="io-clear-filter-btn">Clear All Filters</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="io-stats-row">
				<a href="<?php echo admin_url('admin.php?page=wsa-incomplete-orders'); ?>" class="io-stat-card stat-total <?php echo empty($status_filter) ? 'active' : ''; ?>">
					<span class="io-stat-icon">📊</span>
					<span class="io-stat-label">Total Leads</span>
					<span class="io-stat-value"><?php echo number_format($total_leads); ?></span>
				</a>
				<a href="<?php echo add_query_arg('status_filter', 'captured', admin_url('admin.php?page=wsa-incomplete-orders')); ?>" class="io-stat-card stat-captured <?php echo $status_filter === 'captured' ? 'active' : ''; ?>">
					<span class="io-stat-icon">🎯</span>
					<span class="io-stat-label">Captured</span>
					<span class="io-stat-value"><?php echo number_format($stats['captured']); ?></span>
				</a>
				<a href="<?php echo add_query_arg('status_filter', 'converted', admin_url('admin.php?page=wsa-incomplete-orders')); ?>" class="io-stat-card stat-converted <?php echo $status_filter === 'converted' ? 'active' : ''; ?>">
					<span class="io-stat-icon">✅</span>
					<span class="io-stat-label">Converted</span>
					<span class="io-stat-value"><?php echo number_format($stats['converted']); ?></span>
				</a>
				<a href="<?php echo add_query_arg('status_filter', 'contacted', admin_url('admin.php?page=wsa-incomplete-orders')); ?>" class="io-stat-card stat-contacted <?php echo $status_filter === 'contacted' ? 'active' : ''; ?>">
					<span class="io-stat-icon">📞</span>
					<span class="io-stat-label">Contacted</span>
					<span class="io-stat-value"><?php echo number_format($stats['contacted']); ?></span>
				</a>
				<a href="<?php echo add_query_arg('status_filter', 'ignored', admin_url('admin.php?page=wsa-incomplete-orders')); ?>" class="io-stat-card stat-ignored <?php echo $status_filter === 'ignored' ? 'active' : ''; ?>">
					<span class="io-stat-icon">🚫</span>
					<span class="io-stat-label">Ignored</span>
					<span class="io-stat-value"><?php echo number_format($stats['ignored']); ?></span>
				</a>
			</div>

			<div class="io-table-card">
				<div class="io-table-header">
					<h2>
						<?php 
						if ( $search_query ) {
							printf( 'Search Results for "%s"', esc_html( $search_query ) );
						} else {
							echo $status_filter ? ucfirst($status_filter) . ' Leads' : 'Incomplete Leads'; 
						}
						?>
						<span class="io-count-badge"><?php echo number_format( $total_items ); ?></span>
					</h2>
					<div class="io-pagination">
						<?php if ( $total_pages > 1 ) : ?>
							<?php
							echo paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							] );
							?>
						<?php endif; ?>
					</div>
				</div>
				<div class="io-table-wrap">
					<table class="io-table">
						<thead>
							<tr>
								<th>Date & Time</th>
								<th>Customer</th>
								<th>Contact Info</th>
								<th>Cart / Items</th>
								<th>Admin Note</th>
								<th>Action / Status</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $results ) ) : ?>
								<tr>
									<td colspan="6">
										<div class="io-empty-state">
											<span class="io-empty-icon">📭</span>
											<h3 class="io-empty-title">No matching leads found</h3>
											<p class="io-empty-text">Try adjusting your filters or search query.</p>
										</div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $results as $row ) : 
									$cart_items = json_decode( $row->cart_data, true );
									$names = array_filter([$row->first_name, $row->last_name]);
									$customer_name = !empty($names) ? implode(' ', $names) : 'Guest Customer';
									$initials = '';
									if (!empty($row->first_name)) {
										$initials .= strtoupper(substr($row->first_name, 0, 1));
									}
									if (!empty($row->last_name)) {
										$initials .= strtoupper(substr($row->last_name, 0, 1));
									}
									if (empty($initials)) {
										$initials = 'G';
									}
									?>
									<tr class="io-row-<?php echo esc_attr( $row->status ); ?>">
										<td data-label="Date & Time">
											<div class="io-datetime">
												<span class="io-date"><?php echo date( 'M j, Y', strtotime( $row->created_at ) ); ?></span>
												<span class="io-time"><?php echo date( 'h:i A', strtotime( $row->created_at ) ); ?></span>
											</div>
										</td>
										<td data-label="Customer">
											<div class="io-customer">
												<div class="io-customer-avatar"><?php echo esc_html($initials); ?></div>
												<div class="io-customer-details">
													<span class="io-customer-name"><?php echo esc_html( $customer_name ); ?></span>
													<span class="io-customer-badge <?php echo $row->email ? 'io-badge-verified' : 'io-badge-anonymous'; ?>">
														<?php echo $row->email ? '✓ Verified Lead' : 'Anonymous'; ?>
													</span>
												</div>
											</div>
										</td>
										<td data-label="Contact Info">
											<div class="io-contact">
												<?php if ( $row->phone ) : ?>
													<a href="tel:<?php echo esc_attr( $row->phone ); ?>" class="io-contact-item io-contact-phone">
														<span class="io-contact-icon">📞</span>
														<span><?php echo esc_html( $row->phone ); ?></span>
													</a>
												<?php endif; ?>
												<?php if ( $row->email ) : ?>
													<a href="mailto:<?php echo esc_attr( $row->email ); ?>" class="io-contact-item io-contact-email">
														<span class="io-contact-icon">📧</span>
														<span><?php echo esc_html( $row->email ); ?></span>
													</a>
												<?php endif; ?>
											</div>
										</td>
										<td data-label="Cart / Items">
											<div class="io-cart">
												<?php if ( $cart_items ) : ?>
													<?php 
													$total = 0;
													foreach ( $cart_items as $item ) : 
														$product_id = isset($item['product_id']) ? $item['product_id'] : 0;
														if (!$product_id) continue;
														
														$product = wc_get_product( $product_id );
														if (!$product) continue;

														$p_name  = $product->get_name();
														$price   = isset($item['price']) ? $item['price'] : $product->get_price();
														$qty     = isset($item['quantity']) ? $item['quantity'] : 1;
														$total   += ($price * $qty);
														?>
														<div class="io-cart-item">
															<span class="io-cart-product"><?php echo esc_html( $p_name ); ?></span>
															<span class="io-cart-qty">×<?php echo esc_html( $qty ); ?></span>
															<span class="io-cart-price"><?php echo wc_price($price * $qty); ?></span>
														</div>
													<?php endforeach; ?>
													<div class="io-cart-total">
														<span class="io-cart-total-label">Total</span>
														<span class="io-cart-total-value"><?php echo wc_price($total); ?></span>
													</div>
												<?php else : ?>
													<span class="io-cart-empty">Empty Cart</span>
												<?php endif; ?>
											</div>
										</td>
										<td data-label="Admin Note">
											<div class="io-admin-note-cell">
												<div class="io-note-preview" id="note-preview-<?php echo esc_attr( $row->id ); ?>">
													<?php echo $row->admin_note ? esc_html( wp_trim_words( $row->admin_note, 5 ) ) : '<span class="io-no-note">No note</span>'; ?>
												</div>
												<button type="button" class="button button-small io-open-note-btn" 
													data-id="<?php echo esc_attr( $row->id ); ?>" 
													data-note="<?php echo esc_attr( $row->admin_note ); ?>"
													data-customer="<?php echo esc_attr( $customer_name ); ?>">
													<?php echo $row->admin_note ? 'Edit Note' : 'Add Note'; ?>
												</button>
											</div>
										</td>
										<td data-label="Action / Status">
											<div class="io-action">
												<?php if ( isset($row->recovery_sms_sent) && 1 == $row->recovery_sms_sent ) : ?>
													<div class="io-sms-sent-badge" title="Sent at <?php echo esc_attr( $row->recovery_sms_sent_at ); ?>" style="background: #e7f4ff; color: #0073aa; padding: 4px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 8px; display: inline-block; border: 1px solid #c2e0ff;">
														<span>💬 Recovery SMS Sent</span>
													</div>
												<?php endif; ?>
												<?php if ( 'converted' === $row->status ) : ?>
													<span class="io-status-badge io-badge-converted">Converted</span>
												<?php else : ?>
													<select class="io-status-select" data-id="<?php echo esc_attr( $row->id ); ?>" data-status="<?php echo esc_attr( $row->status ); ?>">
														<option value="captured" <?php selected( $row->status, 'captured' ); ?>>Captured</option>
														<option value="converted" <?php selected( $row->status, 'converted' ); ?>>Converted</option>
														<option value="contacted" <?php selected( $row->status, 'contacted' ); ?>>Contacted</option>
														<option value="ignored" <?php selected( $row->status, 'ignored' ); ?>>Ignored</option>
													</select>
													<span class="io-spinner"></span>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div class="io-table-footer">
					<div class="io-pagination">
						<?php if ( $total_pages > 1 ) : ?>
							<?php
							echo paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							] );
							?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Admin Note Modal -->
			<div id="io-note-modal" class="io-modal">
				<div class="io-modal-content">
					<div class="io-modal-header">
						<h3>Admin Note for <span id="io-modal-customer-name"></span></h3>
						<span class="io-modal-close">&times;</span>
					</div>
					<div class="io-modal-body">
						<textarea id="io-modal-note-input" placeholder="Enter administrative note here..."></textarea>
					</div>
					<div class="io-modal-footer">
						<button type="button" class="button io-modal-cancel">Cancel</button>
						<button type="button" id="io-modal-save-btn" class="button button-primary">Save Note</button>
						<span class="io-modal-status">Saving...</span>
					</div>
				</div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				var adminNonce = '<?php echo wp_create_nonce("wsa_admin_nonce"); ?>';
				var currentLeadId = null;
				var $modal = $('#io-note-modal');

				// Open Modal
				$('.io-open-note-btn').on('click', function() {
					var $btn = $(this);
					currentLeadId = $btn.data('id');
					var noteValue = $btn.attr('data-note');
					var customerName = $btn.data('customer');

					$('#io-modal-customer-name').text(customerName);
					$('#io-modal-note-input').val(noteValue);
					$modal.addClass('active');
					$('body').addClass('io-modal-open');
				});

				// Close Modal
				$('.io-modal-close, .io-modal-cancel').on('click', function() {
					$modal.removeClass('active');
					$('body').removeClass('io-modal-open');
				});

				// Save Note in Modal
				$('#io-modal-save-btn').on('click', function() {
					var $btn = $(this);
					var noteValue = $('#io-modal-note-input').val();
					var $status = $('.io-modal-status');

					$btn.prop('disabled', true).text('Saving...');
					$status.addClass('active').text('Saving to database...');

					$.post(ajaxurl, {
						action: 'wsa_update_admin_note',
						lead_id: currentLeadId,
						admin_note: noteValue,
						nonce: adminNonce
					}, function(response) {
						$btn.prop('disabled', false).text('Save Note');
						if (response.success) {
							$status.text('Saved successfully!');
							
							// Update the button data attribute and preview text
							var $btnInTable = $('.io-open-note-btn[data-id="' + currentLeadId + '"]');
							$btnInTable.attr('data-note', noteValue);
							$btnInTable.text(noteValue ? 'Edit Note' : 'Add Note');
							
							var previewText = noteValue;
							if (noteValue.length > 50) {
								previewText = noteValue.substring(0, 50) + '...';
							}
							$('#note-preview-' + currentLeadId).html(noteValue ? previewText : '<span class="io-no-note">No note</span>');

							setTimeout(function() {
								$modal.removeClass('active');
								$('body').removeClass('io-modal-open');
								$status.removeClass('active');
							}, 800);
						} else {
							alert('Error: ' + (response.data || 'Unknown error'));
							$status.text('Error saving note');
						}
					});
				});

				$('#wsa-run-recovery-btn').on('click', function() {
					var $btn = $(this);
					$btn.prop('disabled', true).text('Processing...');
					
					$.post(ajaxurl, {
						action: 'wsa_run_recovery',
						nonce: adminNonce
					}, function(response) {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Run Recovery Check');
						if (response.success) {
							alert('Recovery process triggered!');
							location.reload();
						} else {
							alert('Error: ' + response.data);
						}
					});
				});

				$('.io-status-select').on('change', function() {
					var $select = $(this);
					var $spinner = $select.next('.io-spinner');
					var leadId = $select.data('id');
					var newStatus = $select.val();

					$spinner.addClass('active');
					$select.prop('disabled', true);

					$.post(ajaxurl, {
						action: 'wsa_update_status',
						lead_id: leadId,
						status: newStatus,
						nonce: adminNonce
					}, function(response) {
						$spinner.removeClass('active');
						$select.prop('disabled', false);
						if (response.success) {
							$select.attr('data-status', newStatus);
							if (newStatus === 'converted') {
								location.reload();
							}
						} else {
							alert('Error updating status');
						}
					});
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Display Blocked Devices Page
	 */
	public function display_blocked_devices_page() {
		$is_licensed = LicenseManager::is_license_active();
		
		if ( ! $is_licensed ) {
			$this->display_license_required_notice();
			return;
		}

		require_once WSA_PATH . 'includes/Modules/FakeCustomerDetection/DeviceBlock.php';
		\WooSmartAutomation\Modules\FakeCustomerDetection\DeviceBlock::render_blocked_devices_page();
	}

	public function display_docs_page() {
		// Check if license is active
		$is_licensed = LicenseManager::is_license_active();
		
		if ( ! $is_licensed ) {
			$this->display_license_required_notice();
			return;
		}
		?>
		<div class="wrap">
			<h1>📖 Setup Guide: Meta Conversions API</h1>
			<div class="card" style="max-width: 800px; padding: 25px; margin-top: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
				<div class="wsa-docs-content" style="line-height: 1.6; font-size: 15px;">
					<h2 style="color: #1d2327;">How to get your API credentials?</h2>
					<ol>
						<li>Go to your <strong>Meta Business Suite</strong> -> <strong>Events Manager</strong>.</li>
						<li>Select your <strong>Data Source (Pixel)</strong>.</li>
						<li>Go to the <strong>Settings</strong> tab.</li>
						<li>Scroll down to <strong>Conversions API</strong> section.</li>
						<li>Click on <strong>"Generate access token"</strong> under the "Set up manually" section.</li>
						<li>Copy the token and paste it into the <strong>Settings</strong> page of this plugin.</li>
						<li>Copy your <strong>Pixel ID</strong> from the top of the same page.</li>
					</ol>

					<div style="background: #f0f6fb; padding: 15px; border-left: 4px solid #2271b1; margin-top: 20px;">
						<strong>Pro Tip:</strong> Use the <strong>Test Event Code</strong> from the "Test events" tab in Meta if you want to see the events appearing in real-time while testing.
					</div>
				</div>
			</div>

			<h1>📖 Setup Guide: Courier Webhooks</h1>
			<div class="card" style="max-width: 800px; padding: 25px; margin-top: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
				<div class="wsa-docs-content" style="line-height: 1.6; font-size: 15px;">
					<h2 style="color: #1d2327;">Integration Steps</h2>
					<ol>
						<li>Copy the <strong>Webhook URL</strong> and <strong>Token</strong> from the plugin settings.</li>
						<li>Go to your Courier (Pathao/Steadfast) developer dashboard.</li>
						<li>Paste the URL and provide the Auth Token as required.</li>
						<li>Save the configuration to start receiving automatic status updates.</li>
					</ol>
				</div>
			</div>
			
			<style>
				.wsa-docs-content ol { padding-left: 20px; }
				.wsa-docs-content li { margin-bottom: 10px; }
			</style>
		</div>
		<?php
	}

	public function handle_update_status() {
		check_ajax_referer( 'wsa_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		global $wpdb;
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

		if ( ! $lead_id || ! $status ) {
			wp_send_json_error( 'Invalid data' );
		}

		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		$updated = $wpdb->update(
			$table_name,
			[ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $lead_id ]
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Database update failed' );
		}

		wp_send_json_success();
	}

	public function handle_update_admin_note() {
		check_ajax_referer( 'wsa_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		global $wpdb;
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$note    = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( $_POST['admin_note'] ) : '';

		if ( ! $lead_id ) {
			wp_send_json_error( 'Invalid lead ID' );
		}

		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		$updated = $wpdb->update(
			$table_name,
			[ 'admin_note' => $note, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $lead_id ]
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Database update failed' );
		}

		wp_send_json_success();
	}

	public function handle_run_recovery() {
		check_ajax_referer( 'wsa_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		require_once WSA_PATH . 'includes/Modules/IncompleteOrder/IncompleteOrder.php';
		$incomplete = new \WooSmartAutomation\Modules\IncompleteOrder\IncompleteOrder();
		$incomplete->process_recovery_sms();

		wp_send_json_success( 'Recovery check complete.' );
	}
}
