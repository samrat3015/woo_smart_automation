<?php
namespace WooSmartAutomation\Modules\Courier\Handlers;

/**
 * Handle Steadfast Webhooks
 *
 * Security: Requires Bearer token to match wsa_steadfast_webhook_token option.
 * Status mapping: ONLY uses admin-configured mappings (wsa_steadfast_status_map).
 * No built-in defaults that could silently cancel or change orders.
 */
class SteadfastHandler {

	public function handle( $request ) {

		// ── Security: Enforce Bearer Token ───────────────────────────────────
		// We REJECT the request if the token doesn't match.
		// Previously this was logged but never actually blocked — that was the bug.
		$stored_token = get_option( 'wsa_steadfast_webhook_token', '' );

		if ( ! empty( $stored_token ) ) {
			$auth_header = $request->get_header( 'authorization' );

			if ( ! $auth_header || 'Bearer ' . $stored_token !== $auth_header ) {
				error_log( 'WSA Steadfast Webhook: Unauthorized request rejected. Auth: ' . $auth_header );
				return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Unauthorized' ], 401 );
			}
		}

		// ── Also guard against the toggle being turned off at runtime ─────────
		if ( 'yes' !== get_option( 'wsa_courier_webhook_enabled', 'yes' ) ) {
			return new \WP_REST_Response( [ 'status' => 'ignored', 'message' => 'Courier Webhooks are disabled.' ], 200 );
		}

		$params = $request->get_params();

		error_log( 'WSA Steadfast Webhook Received: ' . print_r( $params, true ) );

		// Steadfast uses 'status' and 'invoice' (sometimes with prefix)
		$status_slug = isset( $params['status'] ) ? sanitize_text_field( strtolower( $params['status'] ) ) : '';
		$invoice     = isset( $params['invoice'] ) ? sanitize_text_field( $params['invoice'] ) : '';

		if ( ! $invoice ) {
			return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Missing Invoice ID' ], 400 );
		}

		// Try to extract Order ID from Invoice (e.g., "220101-123" -> "123")
		$order_id = $invoice;
		if ( strpos( $invoice, '-' ) !== false ) {
			$parts    = explode( '-', $invoice );
			$order_id = end( $parts );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Order not found: ' . $order_id ], 404 );
		}

		// Guard: never process refund objects
		if ( $order instanceof \WC_Order_Refund
			|| ( method_exists( $order, 'get_type' ) && $order->get_type() === 'shop_order_refund' ) ) {
			return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Cannot update a refund object.' ], 400 );
		}

		// ── Map status using ONLY admin-configured mappings ───────────────────
		// No built-in defaults — admin must explicitly configure each mapping.
		// This prevents Steadfast sending "pending" or "cancelled" from silently
		// changing WooCommerce order statuses without the admin's knowledge.
		$new_status = $this->map_status( $status_slug );

		if ( $new_status ) {
			$order->update_status(
				$new_status,
				sprintf(
					__( 'Status updated via Steadfast Webhook: %s → %s', 'woo-smart-automation' ),
					$status_slug,
					$new_status
				)
			);
			error_log( "WSA Steadfast: Order #{$order_id} status changed from {$status_slug} → {$new_status}" );
			return new \WP_REST_Response( [ 'status' => 'success', 'message' => 'Order updated.' ], 200 );
		}

		// Status received but no mapping configured — log and ignore safely
		error_log( "WSA Steadfast: No mapping configured for status '{$status_slug}' on order #{$order_id}. No change made." );
		return new \WP_REST_Response( [
			'status'  => 'ignored',
			'message' => 'No mapping configured for status: ' . $status_slug,
		], 200 );
	}

	/**
	 * Map a Steadfast status slug to a WooCommerce status.
	 *
	 * ONLY uses the admin-configured dynamic map (wsa_steadfast_status_map).
	 * No built-in defaults that could auto-cancel or change orders unexpectedly.
	 *
	 * @param  string $steadfast_status
	 * @return string|false WC status string (without "wc-" prefix), or false if not mapped
	 */
	private function map_status( $steadfast_status ) {
		$dynamic_map      = get_option( 'wsa_steadfast_status_map', [] );
		$steadfast_status = strtolower( trim( $steadfast_status ) );

		error_log( 'WSA Steadfast map_status: received="' . $steadfast_status . '" map=' . print_r( $dynamic_map, true ) );

		// Only honour admin-configured mappings
		if ( ! empty( $dynamic_map ) && isset( $dynamic_map[ $steadfast_status ] ) && $dynamic_map[ $steadfast_status ] !== '' ) {
			$mapped = $dynamic_map[ $steadfast_status ];
			error_log( 'WSA Steadfast: mapped "' . $steadfast_status . '" → "' . $mapped . '"' );
			return $mapped;
		}

		// No mapping found — do NOT apply any built-in defaults.
		// Admin must configure mappings explicitly in the plugin settings.
		error_log( 'WSA Steadfast: No admin mapping for "' . $steadfast_status . '" — ignoring.' );
		return false;
	}
}
