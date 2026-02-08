<?php
namespace WooSmartAutomation\Modules\CustomerSegmentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-Time vs Returning Buyer Differentiation Module
 */
class CustomerSegmentation {

	public function init() {
		// Only add segmentation features if segmentation module is enabled
		$segmentation_enabled = get_option( 'wsa_segmentation_enabled', 'no' );
		
		if ( 'yes' === $segmentation_enabled ) {
			// Set cookie on successful purchase
			add_action( 'woocommerce_thankyou', [ $this, 'set_buyer_cookie' ], 10, 1 );

			// Display UI on Product Page and Checkout
			add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'display_segment_content' ] );
			add_action( 'woocommerce_before_checkout_form', [ $this, 'display_segment_content' ] );

			// Add frontend styles
			add_action( 'wp_head', [ $this, 'add_frontend_styles' ] );
		}

		// User Recognition in Order Table (independent of segmentation)
		if ( 'yes' === get_option( 'wsa_order_trust_badging_enabled', 'yes' ) ) {
			// Legacy Post-based
			add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_order_trust_column' ], 25 );
			add_action( 'manage_shop_order_posts_custom_column', [ $this, 'display_order_trust_column' ], 25, 2 );

			// New HPOS system
			add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_order_trust_column' ], 25 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'display_order_trust_column' ], 25, 2 );
			
			add_action( 'admin_head', [ $this, 'add_admin_styles' ] );
		}
	}

	/**
	 * Set a persistent cookie after a purchase
	 */
	public function set_buyer_cookie( $order_id ) {
		if ( ! $order_id ) return;
		
		// Set cookie for 1 year
		setcookie( 'wsa_past_buyer', 'yes', time() + ( YEAR_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Detect if visitor is a returning customer
	 */
	private function is_returning_customer() {
		// 1. Check if logged in and has orders
		if ( is_user_logged_in() ) {
			$customer_id = get_current_user_id();
			$order_count = wc_get_customer_order_count( $customer_id );
			if ( $order_count > 0 ) {
				return true;
			}
		}

		// 2. Check for the persistent cookie (for guests)
		if ( isset( $_COOKIE['wsa_past_buyer'] ) && $_COOKIE['wsa_past_buyer'] === 'yes' ) {
			return true;
		}

		return false;
	}

	/**
	 * Output the segmented content
	 */
	public function display_segment_content() {
		if ( $this->is_returning_customer() ) {
			$content = get_option( 'wsa_loyalty_msg_html', '' );
			if ( empty( $content ) ) {
				$content = '<div class="wsa-loyalty-box">Welcome back! ❤️ Thanks for being a loyal customer. Enjoy your shopping!</div>';
			}
		} else {
			$content = get_option( 'wsa_trust_badges_html', '' );
			if ( empty( $content ) ) {
				$content = '<div class="wsa-trust-box">✅ Verified Store • 🚚 Fast Shipping • 🛡️ Secure Checkout</div>';
			}
		}

		echo '<div class="wsa-segmented-content">' . $content . '</div>';
	}

	public function add_order_trust_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column ) {
			$new_columns[$key] = $column;
			// Insert after Order Number or Date
			if ( $key === 'order_number' || $key === 'order_date' ) {
				$new_columns['wsa_customer_trust'] = '🛡️ Trust';
			}
		}
		
		// Fallback if keys not found
		if ( ! isset( $new_columns['wsa_customer_trust'] ) ) {
			$new_columns['wsa_customer_trust'] = '🛡️ Trust';
		}

		return $new_columns;
	}

	public function display_order_trust_column( $column, $post_or_order ) {
		if ( $column === 'wsa_customer_trust' ) {
			// Handle HPOS (Order Object) vs Legacy (Post ID)
			$order = ( $post_or_order instanceof \WC_Order ) ? $post_or_order : wc_get_order( $post_or_order );
			
			if ( ! $order ) return;

			$order_id    = $order->get_id();
			$email       = $order->get_billing_email();
			$customer_id = $order->get_customer_id();

			// Logic: Count past orders (Completed or Processing) excluding current order
			$args = [
				'exclude' => [ $order_id ],
				'status'  => [ 'wc-completed', 'wc-processing' ],
				'return'  => 'ids',
				'limit'   => -1,
			];

			if ( $customer_id ) {
				$args['customer_id'] = $customer_id;
			} else {
				$args['billing_email'] = $email;
			}

			$past_orders = wc_get_orders( $args );
			$count = count( $past_orders );

			if ( $count > 0 ) {
				echo '<span class="wsa-trust-badge wsa-returning-buyer" title="' . $count . ' previous successful orders">Returning (' . $count . ')</span>';
			} else {
				echo '<span class="wsa-trust-badge wsa-new-buyer">New</span>';
			}
		}
	}

	public function add_admin_styles() {
		?>
		<style>
			.column-wsa_customer_trust { width: 90px !important; }
			.wsa-trust-badge {
				padding: 2px 6px;
				border-radius: 20px;
				font-size: 9px;
				font-weight: 700;
				display: inline-block;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				line-height: normal;
				white-space: nowrap;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
			}
			.wsa-returning-buyer { 
				background: #dcfce7; 
				color: #166534; 
				border: 1px solid #bbf7d0; 
			}
			.wsa-new-buyer { 
				background: #dbeafe; 
				color: #1e40af; 
				border: 1px solid #bfdbfe; 
			}
		</style>
		<?php
	}

	public function add_frontend_styles() {
		?>
		<style>
			.wsa-segmented-content { margin-bottom: 20px; }
			.wsa-trust-box {
				background: #f8fafc;
				border: 1px dashed #cbd5e1;
				padding: 12px;
				border-radius: 8px;
				color: #475569;
				font-size: 14px;
				text-align: center;
				font-weight: 500;
			}
			.wsa-loyalty-box {
				background: #f0fdf4;
				border: 1px solid #bbf7d0;
				padding: 12px;
				border-radius: 8px;
				color: #166534;
				font-size: 14px;
				text-align: center;
				font-weight: 600;
			}
		</style>
		<?php
	}
}
