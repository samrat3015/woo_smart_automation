<?php
namespace WooSmartAutomation\Modules\Courier\Handlers;

/**
 * Handle Pathao Webhooks
 *
 * Security: Requires Bearer token to match wsa_pathao_webhook_token option.
 * Status mapping: ONLY uses admin-configured mappings (wsa_pathao_status_map).
 * No built-in defaults that could silently cancel or change orders.
 */
class PathaoHandler {

	private function create_response( $data, $status = 200 ) {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'X-Pathao-Merchant-Webhook-Integration-Secret', 'f3992ecc-59da-4cbe-a049-a13da2018d51' );
		return $response;
	}

	public function handle( $request ) {
		$params = $request->get_params();

		// ── Handle Pathao Integration Verification handshake ─────────────────
		if ( isset( $params['event'] ) && 'webhook_integration' === $params['event'] ) {
			return $this->create_response( [ 'success' => true ], 202 );
		}

		// ── Guard: Courier Webhooks toggle ─────────────────────────────────
		if ( 'yes' !== get_option( 'wsa_courier_webhook_enabled', 'yes' ) ) {
			return $this->create_response( [ 'status' => 'ignored', 'message' => 'Courier Webhooks are disabled.' ], 200 );
		}

		// ── Security: Enforce Bearer Token ─────────────────────────────────
		$stored_token = get_option( 'wsa_pathao_webhook_token', '' );
		if ( ! empty( $stored_token ) ) {
			$auth_header = $request->get_header( 'authorization' );
			if ( ! $auth_header || 'Bearer ' . $stored_token !== $auth_header ) {
				error_log( 'WSA Pathao Webhook: Unauthorized request rejected. Auth: ' . $auth_header );
				return $this->create_response( [ 'success' => false, 'message' => 'Unauthorized' ], 401 );
			}
		}

		error_log( 'WSA Pathao Webhook Received: ' . print_r( $params, true ) );

		$order_id = isset( $params['merchant_order_id'] ) ? sanitize_text_field( $params['merchant_order_id'] ) : '';
		$event    = isset( $params['event'] ) ? sanitize_text_field( $params['event'] ) : '';

		if ( ! $order_id ) {
			return $this->create_response( [ 'success' => false, 'message' => 'Missing Order ID' ], 400 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->create_response( [ 'success' => false, 'message' => 'Order not found: ' . $order_id ], 404 );
		}

		// Guard: never process refund objects
		if ( $order instanceof \WC_Order_Refund
			|| ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
			return $this->create_response( [ 'success' => false, 'message' => 'Cannot update a refund object.' ], 400 );
		}

		// ── Map event using ONLY admin-configured mappings ────────────────────
		// No built-in defaults — admin must explicitly configure each mapping.
		$new_status = $this->map_status( $event );

		if ( $new_status ) {
			// Mark order as managed by courier to prevent WC auto-cancellation
			$order->update_meta_data( '_wsa_managed_by_courier', 'yes' );
			$order->save_meta_data();

			$order->update_status(
				$new_status,
				sprintf(
					__( 'Status updated via Pathao Webhook: %s → %s', 'woo-smart-automation' ),
					$event,
					$new_status
				)
			);
			error_log( "WSA Pathao: Order #{$order_id} status changed from {$event} → {$new_status}" );
			return $this->create_response( [ 'success' => true ], 200 );
		}

		error_log( "WSA Pathao: No mapping configured for event '{$event}' on order #{$order_id}. No change made." );
		return $this->create_response( [
			'success' => false,
			'message' => 'No mapping configured for event: ' . $event,
		], 200 );
	}

	/**
	 * Map a Pathao event to a WooCommerce status.
	 *
	 * ONLY uses the admin-configured dynamic map (wsa_pathao_status_map).
	 * No built-in defaults that could auto-cancel or change orders unexpectedly.
	 *
	 * @param  string $pathao_event
	 * @return string|false WC status string, or false if not mapped
	 */
	private function map_status( $pathao_event ) {
		$dynamic_map = get_option( 'wsa_pathao_status_map', [] );

		error_log( 'WSA Pathao map_status: event="' . $pathao_event . '" map=' . print_r( $dynamic_map, true ) );

		if ( ! empty( $dynamic_map ) && isset( $dynamic_map[ $pathao_event ] ) && $dynamic_map[ $pathao_event ] !== '' ) {
			$mapped = $dynamic_map[ $pathao_event ];
			error_log( 'WSA Pathao: mapped "' . $pathao_event . '" → "' . $mapped . '"' );
			return $mapped;
		}

		// No mapping found — do NOT apply any built-in defaults.
		error_log( 'WSA Pathao: No admin mapping for "' . $pathao_event . '" — ignoring.' );
		return false;
	}
}
