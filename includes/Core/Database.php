<?php
namespace WooSmartAutomation\Core;

class Database {

	public static function activate() {
		self::create_incomplete_orders_table();
		self::migrate_incomplete_orders_table();
		self::create_courier_score_table();
		self::create_device_blocks_table();
		add_option( 'wsa_db_version', WSA_VERSION );
		
		if ( ! wp_next_scheduled( 'wsa_cleanup_old_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'wsa_cleanup_old_orders' );
		}

		if ( ! wp_next_scheduled( 'wsa_recovery_sms_cron' ) ) {
			wp_schedule_event( time(), 'wsa_fifteen_minutes', 'wsa_recovery_sms_cron' );
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'wsa_cleanup_old_orders' );
		wp_clear_scheduled_hook( 'wsa_recovery_sms_cron' );
		flush_rewrite_rules();
	}

	public static function maybe_install() {
		if ( get_option( 'wsa_db_version' ) !== WSA_VERSION ) {
			self::create_incomplete_orders_table();
			self::create_courier_score_table();
			self::create_device_blocks_table();
			update_option( 'wsa_db_version', WSA_VERSION );
		}

		// Ensure admin_note column exists (Migration Support)
		self::migrate_incomplete_orders_table();
	}

	private static function migrate_incomplete_orders_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table_name' AND COLUMN_NAME = 'admin_note'" );
		
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN admin_note text DEFAULT NULL AFTER recovery_sms_sent_at" );
		}
	}

	private static function create_incomplete_orders_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_token varchar(100) NOT NULL,
			phone varchar(50) NOT NULL,
			email varchar(100) DEFAULT '',
			first_name varchar(100) DEFAULT '',
			last_name varchar(100) DEFAULT '',
			cart_data longtext DEFAULT NULL,
			status varchar(20) DEFAULT 'captured',
			recovery_sms_sent tinyint(1) DEFAULT 0,
			recovery_sms_sent_at datetime DEFAULT NULL,
			admin_note text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY email (email),
			KEY session_token (session_token)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Create courier score cache table for permanent storage
	 */
	private static function create_courier_score_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_smart_courier_scores';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			phone varchar(50) NOT NULL,
			total_parcels int(11) DEFAULT 0,
			total_delivered int(11) DEFAULT 0,
			total_cancelled int(11) DEFAULT 0,
			success_rate decimal(5,2) DEFAULT 0,
			data_source varchar(20) DEFAULT 'api',
			last_checked datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY last_checked (last_checked)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 * Create device blocks table for storing blocked device fingerprints
	 */
	private static function create_device_blocks_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'woo_smart_device_blocks';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			device_fingerprint varchar(255) NOT NULL DEFAULT '',
			ip_address varchar(100) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			email varchar(100) NOT NULL DEFAULT '',
			customer_name varchar(200) NOT NULL DEFAULT '',
			order_id bigint(20) NOT NULL DEFAULT 0,
			reason varchar(255) NOT NULL DEFAULT '',
			blocked_by bigint(20) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'blocked',
			blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
			unblocked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY device_fingerprint (device_fingerprint),
			KEY ip_address (ip_address),
			KEY phone (phone),
			KEY email (email),
			KEY status (status)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public static function cleanup_old_orders() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_smart_incomplete_orders';
		
		$retention_days = (int) get_option( 'wsa_incomplete_order_retention_days', 30 );
		if ( $retention_days < 1 ) {
			$retention_days = 30;
		}

		$wpdb->query( $wpdb->prepare( 
			"DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$retention_days
		) );
	}
}
