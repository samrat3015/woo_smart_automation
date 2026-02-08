<?php
namespace WooSmartAutomation\Modules\FakeCustomerDetection;

/**
 * Device Block Module
 * 
 * Adds a "Block Device" action button to the WooCommerce orders table.
 * Blocked devices are shown in a separate admin page with unblock option.
 */
class DeviceBlock {

	/**
	 * Initialize the module
	 */
	public function init() {
		// Check if module is enabled in settings
		if ( \get_option( 'wsa_device_block_enabled', 'yes' ) !== 'yes' ) {
			return;
		}

		// Block checkout for blocked customers - register for ALL contexts
		// (frontend page load, AJAX checkout, and WooCommerce Block Checkout)
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'check_blocked_on_checkout' ], 10, 2 );
		add_action( 'woocommerce_checkout_process', [ $this, 'check_blocked_on_checkout_process' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'check_blocked_on_store_api' ], 10, 2 );

		if ( ! is_admin() ) {
			return;
		}

		// Add "Block" action column to WooCommerce orders table
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_device_block_column' ], 25 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_device_block_column' ], 25 );

		// Populate the column
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'populate_device_block_column' ], 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'populate_device_block_column_hpos' ], 10, 2 );

		// Enqueue admin assets on orders page
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// AJAX handlers
		add_action( 'wp_ajax_wsa_block_device', [ $this, 'ajax_block_device' ] );
		add_action( 'wp_ajax_wsa_unblock_device', [ $this, 'ajax_unblock_device' ] );
		add_action( 'wp_ajax_wsa_get_block_info', [ $this, 'ajax_get_block_info' ] );
	}

	/**
	 * Add Device Block column to orders table
	 */
	public function add_device_block_column( $columns ) {
		$new_columns = [];

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Insert after 'wsa_risk_score' column, or after 'order_total'
			if ( $key === 'wsa_risk_score' || ( ! isset( $columns['wsa_risk_score'] ) && $key === 'order_total' ) ) {
				$new_columns['wsa_device_block'] = __( 'Device Block', 'woo-smart-automation' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate Device Block column for traditional orders
	 */
	public function populate_device_block_column( $column, $post_id ) {
		if ( $column === 'wsa_device_block' ) {
			$this->render_device_block_cell( $post_id );
		}
	}

	/**
	 * Populate Device Block column for HPOS orders
	 */
	public function populate_device_block_column_hpos( $column, $order ) {
		if ( $column === 'wsa_device_block' ) {
			$order_id = is_numeric( $order ) ? $order : $order->get_id();
			$this->render_device_block_cell( $order_id );
		}
	}

	/**
	 * Render the device block action cell
	 */
	private function render_device_block_cell( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo '—';
			return;
		}

		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();
		$ip    = $order->get_customer_ip_address();

		// Check if already blocked
		$is_blocked = $this->is_order_blocked( $order_id, $phone, $email, $ip );

		if ( $is_blocked ) {
			?>
			<div class="wsa-device-block-cell">
				<span class="wsa-blocked-badge" title="<?php esc_attr_e( 'This customer is blocked', 'woo-smart-automation' ); ?>">
					🚫 <?php _e( 'Blocked', 'woo-smart-automation' ); ?>
				</span>
			</div>
			<?php
		} else {
			?>
			<div class="wsa-device-block-cell">
				<button type="button" 
						class="button wsa-block-device-btn" 
						data-order-id="<?php echo esc_attr( $order_id ); ?>"
						data-phone="<?php echo esc_attr( $phone ); ?>"
						data-email="<?php echo esc_attr( $email ); ?>"
						data-ip="<?php echo esc_attr( $ip ); ?>"
						title="<?php esc_attr_e( 'Block this device/customer', 'woo-smart-automation' ); ?>">
					🛡️ <?php _e( 'Block', 'woo-smart-automation' ); ?>
				</button>
			</div>
			<?php
		}
	}

	/**
	 * Normalize phone number for consistent matching.
	 * Strips country code (+880/880), spaces, dashes, etc.
	 * Ensures Bangladesh numbers are in 01XXXXXXXXX format.
	 */
	private function normalize_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}

		// Remove all non-digit characters
		$phone = preg_replace( '/[^\d]/', '', $phone );

		// Handle Bangladesh country code (880)
		// e.g., 8801712345678 (13 digits) → 01712345678 (11 digits)
		if ( strlen( $phone ) >= 13 && substr( $phone, 0, 3 ) === '880' ) {
			$phone = '0' . substr( $phone, 3 );
		}

		// Ensure leading zero for Bangladesh numbers
		if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '1' ) {
			$phone = '0' . $phone;
		}

		return $phone;
	}

	/**
	 * Check if order customer is blocked.
	 * Matches by phone and email ONLY — NOT by IP address.
	 * IP is excluded because multiple different customers often share the same IP
	 * (same network, mobile carrier, office, etc.) which causes false positives.
	 */
	private function is_order_blocked( $order_id, $phone, $email, $ip ) {
		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_device_blocks';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return false;
		}

		$conditions = [];
		$values     = [];

		// Match by phone (normalized) — primary customer identifier
		if ( ! empty( $phone ) ) {
			$normalized_phone = $this->normalize_phone( $phone );
			if ( ! empty( $normalized_phone ) ) {
				$conditions[] = 'phone = %s';
				$values[]     = $normalized_phone;
				// Also match original format for legacy/un-normalized data
				if ( $normalized_phone !== $phone ) {
					$conditions[] = 'phone = %s';
					$values[]     = $phone;
				}
			}
		}

		// Match by email — primary customer identifier
		if ( ! empty( $email ) ) {
			$conditions[] = 'email = %s';
			$values[]     = strtolower( trim( $email ) );
		}

		// NOTE: IP address intentionally NOT used for matching.
		// Multiple different customers share the same IP address,
		// which was causing ALL orders to show as "Blocked" when
		// only one customer was actually blocked.

		if ( empty( $conditions ) ) {
			return false;
		}

		$where = implode( ' OR ', $conditions );
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE status = 'blocked' AND ($where)",
			...$values
		);

		return (int) $wpdb->get_var( $query ) > 0;
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Load on orders page and our blocked devices page
		$load_on = (
			$hook === 'edit.php' ||
			strpos( $hook, 'wc-orders' ) !== false ||
			( isset( $_GET['page'] ) && $_GET['page'] === 'wsa-blocked-devices' )
		);

		if ( ! $load_on ) {
			return;
		}

		wp_enqueue_style(
			'wsa-device-block',
			WSA_URL . 'assets/css/device-block.css',
			[],
			WSA_VERSION
		);

		wp_enqueue_script(
			'wsa-device-block',
			WSA_URL . 'assets/js/device-block-admin.js',
			[ 'jquery' ],
			WSA_VERSION,
			true
		);

		wp_localize_script( 'wsa-device-block', 'wsaDeviceBlock', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wsa_device_block' ),
			'i18n'     => [
				'confirm_title'   => __( 'Block This Customer?', 'woo-smart-automation' ),
				'confirm_message' => __( 'Are you sure you want to block this customer? They will not be able to place orders.', 'woo-smart-automation' ),
				'block_btn'       => __( 'Yes, Block', 'woo-smart-automation' ),
				'cancel_btn'      => __( 'Cancel', 'woo-smart-automation' ),
				'blocking'        => __( 'Blocking...', 'woo-smart-automation' ),
				'unblocking'      => __( 'Unblocking...', 'woo-smart-automation' ),
				'unblock_confirm' => __( 'Are you sure you want to unblock this customer?', 'woo-smart-automation' ),
				'success_blocked' => __( 'Customer has been blocked successfully!', 'woo-smart-automation' ),
				'success_unblock' => __( 'Customer has been unblocked successfully!', 'woo-smart-automation' ),
				'error'           => __( 'An error occurred. Please try again.', 'woo-smart-automation' ),
			],
		] );
	}

	/**
	 * AJAX: Block a device/customer
	 */
	public function ajax_block_device() {
		check_ajax_referer( 'wsa_device_block', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'woo-smart-automation' ) ] );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order ID', 'woo-smart-automation' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found', 'woo-smart-automation' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_device_blocks';

		$phone         = $order->get_billing_phone();
		$email         = $order->get_billing_email();
		$ip            = $order->get_customer_ip_address();
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		// Normalize phone for consistent matching across all orders
		$normalized_phone = $this->normalize_phone( $phone );
		$normalized_email  = strtolower( trim( $email ) );

		// Check if already blocked
		if ( $this->is_order_blocked( $order_id, $normalized_phone, $normalized_email, $ip ) ) {
			wp_send_json_error( [ 'message' => __( 'This customer is already blocked.', 'woo-smart-automation' ) ] );
		}

		$inserted = $wpdb->insert(
			$table,
			[
				'device_fingerprint' => '',
				'ip_address'         => $ip ? $ip : '',
				'phone'              => $normalized_phone ? $normalized_phone : '',
				'email'              => $normalized_email ? $normalized_email : '',
				'customer_name'      => $customer_name,
				'order_id'           => $order_id,
				'reason'             => $reason ? $reason : __( 'Blocked from order table', 'woo-smart-automation' ),
				'blocked_by'         => get_current_user_id(),
				'status'             => 'blocked',
				'blocked_at'         => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			wp_send_json_error( [ 'message' => __( 'Failed to block customer. Database error.', 'woo-smart-automation' ) ] );
		}

		// Add order note
		$order->add_order_note(
			sprintf(
				__( '🚫 Customer blocked by %s. Reason: %s', 'woo-smart-automation' ),
				wp_get_current_user()->display_name,
				$reason ? $reason : 'N/A'
			)
		);

		wp_send_json_success( [
			'message' => __( 'Customer has been blocked successfully!', 'woo-smart-automation' ),
		] );
	}

	/**
	 * AJAX: Unblock a device/customer
	 */
	public function ajax_unblock_device() {
		check_ajax_referer( 'wsa_device_block', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'woo-smart-automation' ) ] );
		}

		$block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

		if ( ! $block_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid block ID', 'woo-smart-automation' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_device_blocks';

		$updated = $wpdb->update(
			$table,
			[
				'status'       => 'unblocked',
				'unblocked_at' => current_time( 'mysql' ),
			],
			[ 'id' => $block_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to unblock. Database error.', 'woo-smart-automation' ) ] );
		}

		// Add order note if order exists
		$block_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $block_id ) );
		if ( $block_row && $block_row->order_id ) {
			$order = wc_get_order( $block_row->order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						__( '✅ Customer unblocked by %s.', 'woo-smart-automation' ),
						wp_get_current_user()->display_name
					)
				);
			}
		}

		wp_send_json_success( [
			'message' => __( 'Customer has been unblocked successfully!', 'woo-smart-automation' ),
		] );
	}

	/**
	 * AJAX: Get block info for popup
	 */
	public function ajax_get_block_info() {
		check_ajax_referer( 'wsa_device_block', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'woo-smart-automation' ) ] );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order ID', 'woo-smart-automation' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found', 'woo-smart-automation' ) ] );
		}

		$phone         = $order->get_billing_phone();
		$email         = $order->get_billing_email();
		$ip            = $order->get_customer_ip_address();
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$risk_score    = get_post_meta( $order_id, '_wsa_risk_score', true );

		wp_send_json_success( [
			'order_id'      => $order_id,
			'customer_name' => $customer_name ? $customer_name : __( 'Guest', 'woo-smart-automation' ),
			'phone'         => $phone,
			'email'         => $email,
			'ip'            => $ip,
			'risk_score'    => $risk_score ? $risk_score : '—',
			'order_total'   => $order->get_total(),
			'order_status'  => wc_get_order_status_name( $order->get_status() ),
		] );
	}

	/**
	 * Block checkout via woocommerce_after_checkout_validation (classic checkout)
	 */
	public function check_blocked_on_checkout( $data, $errors ) {
		$phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';
		$email = isset( $data['billing_email'] ) ? $data['billing_email'] : '';

		if ( $this->is_customer_blocked( $phone, $email ) ) {
			$errors->add( 'wsa_blocked', __( 'Sorry, you are not allowed to place orders. Please contact support.', 'woo-smart-automation' ) );
		}
	}

	/**
	 * Block checkout via woocommerce_checkout_process (classic checkout safety net)
	 */
	public function check_blocked_on_checkout_process() {
		$phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : '';
		$email = isset( $_POST['billing_email'] ) ? sanitize_email( $_POST['billing_email'] ) : '';

		if ( $this->is_customer_blocked( $phone, $email ) ) {
			wc_add_notice( __( 'Sorry, you are not allowed to place orders. Please contact support.', 'woo-smart-automation' ), 'error' );
		}
	}

	/**
	 * Block checkout via WooCommerce Store API (Block Checkout / new checkout)
	 */
	public function check_blocked_on_store_api( $order, $request ) {
		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();

		if ( $this->is_customer_blocked( $phone, $email ) ) {
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'wsa_blocked',
					__( 'Sorry, you are not allowed to place orders. Please contact support.', 'woo-smart-automation' ),
					403
				);
			}
			throw new \Exception( __( 'Sorry, you are not allowed to place orders. Please contact support.', 'woo-smart-automation' ) );
		}
	}

	/**
	 * Check if a customer is blocked by phone or email.
	 * Uses normalized phone and email for consistent matching.
	 * IP address is NOT used to avoid false positives across different customers.
	 */
	private function is_customer_blocked( $phone, $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_device_blocks';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return false;
		}

		$conditions = [];
		$values     = [];

		// Match by normalized phone
		if ( ! empty( $phone ) ) {
			$normalized_phone = $this->normalize_phone( $phone );
			if ( ! empty( $normalized_phone ) ) {
				$conditions[] = 'phone = %s';
				$values[]     = $normalized_phone;
				// Also check original format for legacy data
				if ( $normalized_phone !== $phone ) {
					$conditions[] = 'phone = %s';
					$values[]     = $phone;
				}
			}
		}

		// Match by email (case-insensitive)
		if ( ! empty( $email ) ) {
			$conditions[] = 'email = %s';
			$values[]     = strtolower( trim( $email ) );
		}

		if ( empty( $conditions ) ) {
			return false;
		}

		$where = implode( ' OR ', $conditions );
		$is_blocked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE status = 'blocked' AND ($where)",
				...$values
			)
		);

		return $is_blocked > 0;
	}

	/**
	 * Render the Blocked Devices admin page
	 */
	public static function render_blocked_devices_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_device_blocks';

		// Create table if it doesn't exist
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			\WooSmartAutomation\Core\Database::maybe_install();
		}

		// Get current tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'blocked';

		// Get counts
		$blocked_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'blocked'" );
		$unblocked_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'unblocked'" );
		$total_count     = $blocked_count + $unblocked_count;

		// Get results based on tab
		if ( $current_tab === 'unblocked' ) {
			$results = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'unblocked' ORDER BY unblocked_at DESC" );
		} elseif ( $current_tab === 'all' ) {
			$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY blocked_at DESC" );
		} else {
			$results = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'blocked' ORDER BY blocked_at DESC" );
		}

		?>
		<div class="wrap wsa-blocked-devices-wrap">
			<div class="wsa-db-header">
				<div class="wsa-db-header-content">
					<h1>🛡️ <?php _e( 'Blocked Devices / Customers', 'woo-smart-automation' ); ?></h1>
					<p><?php _e( 'Manage blocked customers who are prevented from placing orders.', 'woo-smart-automation' ); ?></p>
				</div>
			</div>

			<!-- Stats Cards -->
			<div class="wsa-db-stats">
				<a href="<?php echo admin_url( 'admin.php?page=wsa-blocked-devices&tab=all' ); ?>" 
				   class="wsa-db-stat-card <?php echo $current_tab === 'all' ? 'active' : ''; ?>">
					<span class="wsa-db-stat-icon">📊</span>
					<span class="wsa-db-stat-label"><?php _e( 'Total', 'woo-smart-automation' ); ?></span>
					<span class="wsa-db-stat-value"><?php echo $total_count; ?></span>
				</a>
				<a href="<?php echo admin_url( 'admin.php?page=wsa-blocked-devices&tab=blocked' ); ?>" 
				   class="wsa-db-stat-card stat-blocked <?php echo $current_tab === 'blocked' ? 'active' : ''; ?>">
					<span class="wsa-db-stat-icon">🚫</span>
					<span class="wsa-db-stat-label"><?php _e( 'Blocked', 'woo-smart-automation' ); ?></span>
					<span class="wsa-db-stat-value"><?php echo $blocked_count; ?></span>
				</a>
				<a href="<?php echo admin_url( 'admin.php?page=wsa-blocked-devices&tab=unblocked' ); ?>" 
				   class="wsa-db-stat-card stat-unblocked <?php echo $current_tab === 'unblocked' ? 'active' : ''; ?>">
					<span class="wsa-db-stat-icon">✅</span>
					<span class="wsa-db-stat-label"><?php _e( 'Unblocked', 'woo-smart-automation' ); ?></span>
					<span class="wsa-db-stat-value"><?php echo $unblocked_count; ?></span>
				</a>
			</div>

			<!-- Table -->
			<div class="wsa-db-table-card">
				<div class="wsa-db-table-header">
					<h2>
						<?php
						if ( $current_tab === 'unblocked' ) {
							_e( 'Unblocked Customers', 'woo-smart-automation' );
						} elseif ( $current_tab === 'all' ) {
							_e( 'All Records', 'woo-smart-automation' );
						} else {
							_e( 'Currently Blocked', 'woo-smart-automation' );
						}
						?>
					</h2>
				</div>
				<div class="wsa-db-table-wrap">
					<table class="wsa-db-table widefat striped">
						<thead>
							<tr>
								<th><?php _e( '#', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Customer', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Phone', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Email', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'IP Address', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Order', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Reason', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Blocked At', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Status', 'woo-smart-automation' ); ?></th>
								<th><?php _e( 'Action', 'woo-smart-automation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $results ) ) : ?>
								<tr>
									<td colspan="10">
										<div class="wsa-db-empty">
											<span class="wsa-db-empty-icon">🎉</span>
											<h3><?php _e( 'No records found', 'woo-smart-automation' ); ?></h3>
											<p><?php _e( 'No blocked customers to display.', 'woo-smart-automation' ); ?></p>
										</div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $results as $index => $row ) : 
									$blocked_user = $row->blocked_by ? get_userdata( $row->blocked_by ) : null;
									$blocked_by_name = $blocked_user ? $blocked_user->display_name : 'System';
								?>
								<tr class="wsa-db-row-<?php echo esc_attr( $row->status ); ?>">
									<td><strong><?php echo $index + 1; ?></strong></td>
									<td>
										<div class="wsa-db-customer">
											<strong><?php echo esc_html( $row->customer_name ? $row->customer_name : 'N/A' ); ?></strong>
											<small><?php echo esc_html( sprintf( __( 'by %s', 'woo-smart-automation' ), $blocked_by_name ) ); ?></small>
										</div>
									</td>
									<td>
										<?php if ( $row->phone ) : ?>
											<code><?php echo esc_html( $row->phone ); ?></code>
										<?php else : ?>
											<span class="wsa-db-na">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->email ) : ?>
											<code><?php echo esc_html( $row->email ); ?></code>
										<?php else : ?>
											<span class="wsa-db-na">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->ip_address ) : ?>
											<code><?php echo esc_html( $row->ip_address ); ?></code>
										<?php else : ?>
											<span class="wsa-db-na">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->order_id ) : ?>
											<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row->order_id . '&action=edit' ) ); ?>" target="_blank">
												#<?php echo esc_html( $row->order_id ); ?>
											</a>
										<?php else : ?>
											<span class="wsa-db-na">—</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="wsa-db-reason"><?php echo esc_html( $row->reason ? $row->reason : '—' ); ?></span>
									</td>
									<td>
										<div class="wsa-db-datetime">
											<span class="wsa-db-date"><?php echo date( 'M j, Y', strtotime( $row->blocked_at ) ); ?></span>
											<span class="wsa-db-time"><?php echo date( 'h:i A', strtotime( $row->blocked_at ) ); ?></span>
										</div>
										<?php if ( $row->status === 'unblocked' && $row->unblocked_at ) : ?>
											<div class="wsa-db-unblocked-at">
												<?php _e( 'Unblocked:', 'woo-smart-automation' ); ?>
												<?php echo date( 'M j, Y h:i A', strtotime( $row->unblocked_at ) ); ?>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->status === 'blocked' ) : ?>
											<span class="wsa-db-status-badge wsa-db-status-blocked">🚫 <?php _e( 'Blocked', 'woo-smart-automation' ); ?></span>
										<?php else : ?>
											<span class="wsa-db-status-badge wsa-db-status-unblocked">✅ <?php _e( 'Unblocked', 'woo-smart-automation' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->status === 'blocked' ) : ?>
											<button type="button" 
													class="button wsa-unblock-btn" 
													data-block-id="<?php echo esc_attr( $row->id ); ?>"
													data-customer="<?php echo esc_attr( $row->customer_name ); ?>">
												✅ <?php _e( 'Unblock', 'woo-smart-automation' ); ?>
											</button>
										<?php else : ?>
											<span class="wsa-db-na-action">—</span>
										<?php endif; ?>
									</td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}
