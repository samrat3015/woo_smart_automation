<?php
namespace WooSmartAutomation\Modules\OrderRestriction;

/**
 * Order Restriction Module
 * 
 * Prevents customers from placing multiple orders within a specific time window.
 */
class OrderRestriction {

	/**
	 * Initialize the module
	 */
	public function init() {
		// Check if module is enabled in settings
		if ( \get_option( 'wsa_order_restriction_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		// Register checkout validation hook
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_order_frequency' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'validate_store_api_order_frequency' ], 10, 2 );
	}

	/**
	 * Validate order frequency for classic checkout
	 */
	public function validate_order_frequency( $data, $errors ) {
		$phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';
		$email = isset( $data['billing_email'] ) ? $data['billing_email'] : '';

		$restriction = $this->check_restriction( $phone, $email );

		if ( $restriction['is_restricted'] ) {
			$errors->add( 'order_restricted', $restriction['message'] );
		}
	}

	/**
	 * Validate order frequency for Block Checkout (Store API)
	 */
	public function validate_store_api_order_frequency( $order, $request ) {
		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();

		$restriction = $this->check_restriction( $phone, $email );

		if ( $restriction['is_restricted'] ) {
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'order_restricted',
					$restriction['message'],
					403
				);
			}
			throw new \Exception( $restriction['message'] );
		}
	}

	/**
	 * Check if a customer is restricted based on their last order time
	 */
	private function check_restriction( $phone, $email ) {
		$limit_minutes = (int) \get_option( 'wsa_order_restriction_limit', 30 );
		$custom_message = \get_option( 'wsa_order_restriction_message', '' );
		
		if ( $limit_minutes <= 0 ) {
			return [ 'is_restricted' => false ];
		}

		$normalized_phone = $this->normalize_phone( $phone );
		
		// 1. Check by Email
		$last_order = $this->get_last_order( $email, '' );

		// 2. If not found or if we want to check phone too (stronger restriction)
		if ( ! $last_order && ! empty( $normalized_phone ) ) {
			$last_order = $this->get_last_order( '', $normalized_phone );
		}

		if ( $last_order ) {
			$order_time = strtotime( $last_order->get_date_created()->date( 'Y-m-d H:i:s' ) );
			$current_time = current_time( 'timestamp' );
			$diff_minutes = round( ( $current_time - $order_time ) / 60 );

			if ( $diff_minutes < $limit_minutes ) {
				$remaining = $limit_minutes - $diff_minutes;
				
				if ( empty( $custom_message ) ) {
					$message = sprintf( 
						__( 'You have already placed an order recently. Please wait %d minutes before placing another order.', 'woo-smart-automation' ),
						$remaining
					);
				} else {
					$message = str_replace( '{time}', $remaining, $custom_message );
				}

				return [
					'is_restricted' => true,
					'message'       => $message
				];
			}
		}

		return [ 'is_restricted' => false ];
	}

	/**
	 * Get the last order for a specific email or phone
	 */
	private function get_last_order( $email, $phone ) {
		$args = [
			'limit'    => 1,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => [ 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ], // Exclude failed/cancelled
		];

		if ( ! empty( $email ) ) {
			$args['billing_email'] = $email;
		} elseif ( ! empty( $phone ) ) {
			$args['meta_key']   = '_billing_phone';
			$args['meta_value'] = $phone;
			$args['meta_compare'] = 'LIKE'; // Catch partial matches if normalization differs slightly
		} else {
			return false;
		}

		$orders = wc_get_orders( $args );

		return ! empty( $orders ) ? $orders[0] : false;
	}

	/**
	 * Normalize phone number for matching
	 */
	private function normalize_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}

		// Remove all non-digit characters
		$phone = preg_replace( '/[^\d]/', '', $phone );

		// Handle Bangladesh country code (880)
		if ( strlen( $phone ) >= 13 && substr( $phone, 0, 3 ) === '880' ) {
			$phone = '0' . substr( $phone, 3 );
		}

		// Ensure leading zero for Bangladesh numbers
		if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '1' ) {
			$phone = '0' . $phone;
		}

		return $phone;
	}
}
