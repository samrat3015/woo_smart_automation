<?php
/**
 * Plugin Name: Woo Smart Shield
 * Description: All-in-one automation for WooCommerce: Incomplete Order Capture, Courier Webhooks, and License Management.
 * Version: 1.0.3
 * Author: Hasibur rahman samrat
 * Text Domain: woo-smart-automation
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'WSA_VERSION', '1.1.0' );
define( 'WSA_FILE', __FILE__ );
define( 'WSA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSA_URL', plugin_dir_url( __FILE__ ) );

// Autoload Core Classes
require_once WSA_PATH . 'includes/Core/Plugin.php';

/**
 * Activation Hook - Create Database Tables
 */
function wsa_activate_plugin() {
	require_once WSA_PATH . 'includes/Core/Database.php';
	\WooSmartAutomation\Core\Database::activate();
}
register_activation_hook( __FILE__, 'wsa_activate_plugin' );

/**
 * Deactivation Hook
 */
function wsa_deactivate_plugin() {
	require_once WSA_PATH . 'includes/Core/Database.php';
	\WooSmartAutomation\Core\Database::deactivate();
}
register_deactivation_hook( __FILE__, 'wsa_deactivate_plugin' );

/**
 * Main Instance of the Plugin
 */
function run_woo_smart_automation() {
	$plugin = new \WooSmartAutomation\Core\Plugin();
	$plugin->run();
}

run_woo_smart_automation();
