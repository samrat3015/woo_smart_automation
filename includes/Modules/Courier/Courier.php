<?php
namespace WooSmartAutomation\Modules\Courier;

/**
 * Courier Module Base Class
 */
class Courier {

	public function init() {
		// Only register webhook routes if Courier Webhooks is enabled in settings
		if ( 'yes' === get_option( 'wsa_courier_webhook_enabled', 'yes' ) ) {
			add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		}
		// CourierSettings UI removed — FraudPeek credentials are hardcoded
	}

	public function register_rest_routes() {
		register_rest_route( 'woo-smart-automation/v1', '/webhook/pathao', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_pathao_webhook' ],
			'permission_callback' => '__return_true', // Validation happens inside callback
		] );

		register_rest_route( 'woo-smart-automation/v1', '/webhook/steadfast', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_steadfast_webhook' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function handle_pathao_webhook( $request ) {
		require_once WSA_PATH . 'includes/Modules/Courier/Handlers/PathaoHandler.php';
		$handler = new \WooSmartAutomation\Modules\Courier\Handlers\PathaoHandler();
		return $handler->handle( $request );
	}

	public function handle_steadfast_webhook( $request ) {
		require_once WSA_PATH . 'includes/Modules/Courier/Handlers/SteadfastHandler.php';
		$handler = new \WooSmartAutomation\Modules\Courier\Handlers\SteadfastHandler();
		return $handler->handle( $request );
	}
}
