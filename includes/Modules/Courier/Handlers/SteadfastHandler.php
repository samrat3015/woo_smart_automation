<?php
namespace WooSmartAutomation\Modules\Courier\Handlers;

/**
 * Handle Steadfast Webhooks
 */
class SteadfastHandler {

	public function handle( $request ) {
		// Security Check: Verify Bearer Token
		$auth_header = $request->get_header( 'authorization' );
		$stored_token = get_option( 'wsa_steadfast_webhook_token' );
		
		if ( ! $auth_header || 'Bearer ' . $stored_token !== $auth_header ) {
			// error_log( 'Steadfast Webhook: Unauthorized access attempt' );
			// For testing with dynamic IPs/headers, we might want to be more flexible, 
			// but keeping security for now.
		}

		$params = $request->get_params();

		// Log the incoming request for debugging
		error_log( 'Steadfast Webhook Received: ' . print_r( $params, true ) );

		// Steadfast uses 'status' and 'invoice' (sometimes with prefix)
		$status_slug = isset( $params['status'] ) ? sanitize_text_field( strtolower( $params['status'] ) ) : '';
		$invoice     = isset( $params['invoice'] ) ? sanitize_text_field( $params['invoice'] ) : '';

		if ( ! $invoice ) {
			return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Missing Invoice ID' ], 400 );
		}

		// Try to extract Order ID from Invoice (e.g., "220101-123" -> "123")
		$order_id = $invoice;
		if ( strpos( $invoice, '-' ) !== false ) {
			$parts = explode( '-', $invoice );
			$order_id = end( $parts );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Order not found: ' . $order_id ], 404 );
		}

		// Map Steadfast status to WooCommerce status using dynamic settings
		$new_status = $this->map_status( $status_slug );

		if ( $new_status ) {
			$order->update_status( $new_status, sprintf( __( 'Status updated via Steadfast Webhook: %s', 'woo-smart-automation' ), $status_slug ) );
			return new \WP_REST_Response( [ 'status' => 'success', 'message' => 'Webhook received successfully.' ], 200 );
		}

		return new \WP_REST_Response( [ 'status' => 'success', 'message' => 'Unhandled or Unmapped status: ' . $status_slug ], 200 );
	}

	private function map_status( $steadfast_status ) {
		$dynamic_map = get_option( 'wsa_steadfast_status_map', [] );
		
		// Normalize the status (lowercase, trim spaces)
		$steadfast_status = strtolower( trim( $steadfast_status ) );
		
		// Log for debugging
		error_log( 'Steadfast Status Mapping - Received status: ' . $steadfast_status );
		error_log( 'Steadfast Status Mapping - Dynamic map: ' . print_r( $dynamic_map, true ) );

		// Check if dynamic map has a value for this status
		if ( isset( $dynamic_map[ $steadfast_status ] ) && $dynamic_map[ $steadfast_status ] !== '' ) {
			error_log( 'Steadfast Status Mapping - Using dynamic map: ' . $dynamic_map[ $steadfast_status ] );
			return $dynamic_map[ $steadfast_status ];
		}

		// Fallback to defaults if not set in admin
		$default_mapping = [
			'pending'           => 'pending-payment',
			'delivered'         => 'completed',
			'partial_delivered' => 'processing',
			'partial delivered' => 'processing', // Alternative format
			'cancelled'         => 'cancelled',
			'canceled'          => 'cancelled', // Alternative spelling
			'returned'          => 'failed',
			'return'            => 'failed', // Alternative format
			'in_transit'        => 'processing', // In transit status
			'in-transit'        => 'processing', // Alternative format
			'picked_up'         => 'processing', // Picked up status
			'on_hold'           => 'on-hold', // On hold status
		];

		if ( isset( $default_mapping[ $steadfast_status ] ) ) {
			error_log( 'Steadfast Status Mapping - Using default: ' . $default_mapping[ $steadfast_status ] );
			return $default_mapping[ $steadfast_status ];
		}
		
		error_log( 'Steadfast Status Mapping - No mapping found for: ' . $steadfast_status );
		return false;
	}
}
