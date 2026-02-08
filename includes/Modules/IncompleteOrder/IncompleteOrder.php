<?php
namespace WooSmartAutomation\Modules\IncompleteOrder;

class IncompleteOrder {

	public function init() {
		// Enqueue Scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// AJAX Handlers
		add_action( 'wp_ajax_woo_smart_capture', [ $this, 'handle_capture' ] );
		add_action( 'wp_ajax_nopriv_woo_smart_capture', [ $this, 'handle_capture' ] );

		// Scheduled Recovery SMS
		add_filter( 'cron_schedules', [ $this, 'add_custom_cron_intervals' ] );
		add_action( 'wsa_recovery_sms_cron', [ $this, 'process_recovery_sms' ] );

		add_action( 'wsa_recovery_sms_cron', [ $this, 'process_recovery_sms' ] );

		if ( wp_next_scheduled( 'wsa_recovery_sms_cron' ) !== false && wp_get_schedule( 'wsa_recovery_sms_cron' ) !== 'wsa_one_minute' ) {
			wp_clear_scheduled_hook( 'wsa_recovery_sms_cron' );
		}

		if ( ! wp_next_scheduled( 'wsa_recovery_sms_cron' ) ) {
			wp_schedule_event( time(), 'wsa_one_minute', 'wsa_recovery_sms_cron' );
		}

		// Order Hook (Conversion)
		require_once __DIR__ . '/OrderHook.php';
		$order_hook = new OrderHook();
		$order_hook->init();
	}

	public function enqueue_scripts() {
		if ( is_checkout() && ! is_order_received_page() ) {
			// Ensure session is started for guests so the nonce is valid
			if ( function_exists('WC') && ! is_user_logged_in() && WC()->session && ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			wp_enqueue_script(
				'wsa-capture',
				WSA_URL . 'assets/js/capture.js',
				[ 'jquery' ],
				WSA_VERSION,
				true
			);

			wp_localize_script( 'wsa-capture', 'wsa_ajax', [
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wsa_capture_nonce' )
			]);
		}
	}

	public function handle_capture() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WooSmartAutomation: AJAX request received. Data: ' . print_r( $_POST, true ) );
		}

		if ( ! check_ajax_referer( 'wsa_capture_nonce', 'nonce', false ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WooSmartAutomation AJAX Error: Nonce verification failed. Expected: ' . wp_create_nonce( 'wsa_capture_nonce' ) . ' Received: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'none' ) );
			}
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		require_once __DIR__ . '/CaptureService.php';
		$result = CaptureService::handle();
		
		// Optional: Log result for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			global $wpdb;
			if ( $wpdb->last_error ) {
				error_log( 'WooSmartAutomation DB Error: ' . $wpdb->last_error );
			} else {
				$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : 'N/A';
				$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : 'N/A';
				error_log( 'WooSmartAutomation: Data successfully processed for ' . $phone . ' / ' . $email );
			}
		}

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WooSmartAutomation CaptureService Error: ' . $result->get_error_message() );
			}
			wp_send_json_error( $result->get_error_message(), $result->get_error_code() );
		}
		
		wp_send_json_success();
	}

	public function add_custom_cron_intervals( $schedules ) {
		$schedules['wsa_one_minute'] = [
			'interval' => 60,
			'display'  => esc_html__( 'Every 1 Minute', 'woo-smart-automation' ),
		];
		$schedules['wsa_fifteen_minutes'] = [
			'interval' => 15 * 60,
			'display'  => esc_html__( 'Every 15 Minutes', 'woo-smart-automation' ),
		];
		return $schedules;
	}

	public function process_recovery_sms() {
		if ( 'yes' !== get_option( 'wsa_sms_abandoned_cart_enabled', 'no' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'woo_smart_incomplete_orders';
		$delay = (int) get_option( 'wsa_sms_abandoned_cart_delay', 30 );
		$delay_seconds = $delay * 60;

		$threshold_time = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $delay_seconds );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "WSA Debug: Running Recovery SMS Cron. Delay: $delay mins ($delay_seconds sec). Threshold: $threshold_time" );
		}

		// Fetch candidates: status = captured, not sent yet, updated_at < threshold
		$query = $wpdb->prepare(
			"SELECT * FROM $table 
			 WHERE status = 'captured' 
			 AND recovery_sms_sent = 0 
			 AND phone != ''
			 AND updated_at < %s
			 LIMIT 50",
			$threshold_time
		);

		$leads = $wpdb->get_results( $query );

		if ( empty( $leads ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "WSA Debug: No leads found for recovery SMS." );
			}
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "WSA Debug: Found " . count($leads) . " leads for recovery SMS." );
		}

		if ( file_exists( WSA_PATH . 'includes/Modules/SMS/BulkSMSBDService.php' ) ) {
			require_once WSA_PATH . 'includes/Modules/SMS/BulkSMSBDService.php';
		}

		if ( ! class_exists( 'WooSmartAutomation\Modules\SMS\BulkSMSBDService' ) ) {
			return;
		}

		$service = new \WooSmartAutomation\Modules\SMS\BulkSMSBDService();
		$template = get_option( 'wsa_sms_abandoned_cart_template', 'Hi {customer_name}, you left some items in your cart at {site_name}. Complete your order now!' );
		$site_name = get_bloginfo( 'name' );

		foreach ( $leads as $lead ) {
			$customer_name = ! empty( $lead->first_name ) ? $lead->first_name : 'Customer';
			$message = str_replace(
				[ '{site_name}', '[site_name]', '{customer_name}', '[customer_name]' ],
				[ $site_name, $site_name, $customer_name, $customer_name ],
				$template
			);

			$result = $service->send_sms( $lead->phone, $message );

			if ( $result ) {
				$wpdb->update(
					$table,
					[
						'recovery_sms_sent'    => 1,
						'recovery_sms_sent_at' => current_time( 'mysql' )
					],
					[ 'id' => $lead->id ]
				);
			}
		}
	}
}
