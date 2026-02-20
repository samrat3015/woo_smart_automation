<?php
namespace WooSmartAutomation\Modules\MetaCAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta Conversions API Module
 * Supports both Browser Pixel and Server-Side CAPI with deduplication
 */
class MetaCAPI {
	private $options;
	private $pixel_id;

	public function __construct() {
		$this->pixel_id = get_option( 'wsa_meta_pixel_id', '' );
		$this->options = [
			'pageview'         => get_option( 'wsa_meta_event_pageview_enabled', 'yes' ) === 'yes',
			'viewcontent'      => get_option( 'wsa_meta_event_viewcontent_enabled', 'yes' ) === 'yes',
			'addtocart'        => get_option( 'wsa_meta_event_addtocart_enabled', 'yes' ) === 'yes',
			'initiatecheckout' => get_option( 'wsa_meta_event_initiatecheckout_enabled', 'yes' ) === 'yes',
			'purchase'         => get_option( 'wsa_meta_event_purchase_enabled', 'yes' ) === 'yes',
			'purchase_trigger' => get_option( 'wsa_meta_purchase_event_condition', 'place' ),
		];
	}

	public function init() {
		// Skip if no pixel ID configured
		if ( empty( $this->pixel_id ) ) {
			return;
		}

		// Inject Meta Pixel base code in header
		add_action( 'wp_head', [ $this, 'inject_pixel_base_code' ], 1 );

		// PageView
		if ( $this->options['pageview'] ) {
			add_action( 'wp_head', [ $this, 'track_page_view' ], 20 );
		}

		// ViewContent
		if ( $this->options['viewcontent'] ) {
			add_action( 'woocommerce_before_single_product', [ $this, 'track_view_content' ] );
		}

		// AddToCart
		if ( $this->options['addtocart'] ) {
			add_action( 'woocommerce_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 6 );
		}

		// InitiateCheckout - Use wp_footer to support BOTH Classic and Block Checkout
		if ( $this->options['initiatecheckout'] ) {
			add_action( 'wp_footer', [ $this, 'track_initiate_checkout' ] );
		}

		// Purchase
		if ( $this->options['purchase'] ) {
			if ( $this->options['purchase_trigger'] === 'place' ) {
				add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ], 10, 1 );
			} else {
				add_action( 'woocommerce_order_status_completed', [ $this, 'track_purchase' ], 10, 1 );
			}
		}

		// Admin Integration
		if ( is_admin() ) {
			require_once WSA_PATH . 'includes/Modules/MetaCAPI/AdminIntegration.php';
			$admin_integration = new AdminIntegration();
			$admin_integration->init();
		}
	}

	/**
	 * Inject Meta Pixel base code
	 */
	public function inject_pixel_base_code() {
		if ( empty( $this->pixel_id ) ) return;
		?>
		<!-- Meta Pixel Code - WSA -->
		<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js( $this->pixel_id ); ?>');
		</script>
		<noscript><img height="1" width="1" style="display:none"
		src="https://www.facebook.com/tr?id=<?php echo esc_attr( $this->pixel_id ); ?>&ev=PageView&noscript=1"
		/></noscript>
		<!-- End Meta Pixel Code - WSA -->
		<?php
	}

	/**
	 * Generate unique event ID for deduplication
	 */
	private function generate_event_id( $prefix = 'wsa' ) {
		return $prefix . '_' . uniqid() . '_' . time();
	}

	public function track_page_view() {
		$event_id = $this->generate_event_id( 'pv' );
		
		// Browser Pixel
		?>
		<script>fbq('track', 'PageView', {}, {eventID: '<?php echo esc_js( $event_id ); ?>'});</script>
		<?php
		
		// Server CAPI
		$event_data = [
			'event_name' => 'PageView',
			'event_id'   => $event_id,
			'event_source_url' => home_url( add_query_arg( null, null ) ),
			'user_data' => $this->get_current_user_data(),
		];
		$this->send_event( $event_data );
	}

	public function track_view_content() {
		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) return;

		$event_id = $this->generate_event_id( 'vc' );
		$content_id = (string) $product->get_id();
		$content_name = $product->get_name();
		$value = (float) $product->get_price();
		$currency = get_woocommerce_currency();
		
		// Browser Pixel
		?>
		<script>
		fbq('track', 'ViewContent', {
			content_ids: ['<?php echo esc_js( $content_id ); ?>'],
			content_name: '<?php echo esc_js( $content_name ); ?>',
			content_type: 'product',
			value: <?php echo esc_js( $value ); ?>,
			currency: '<?php echo esc_js( $currency ); ?>'
		}, {eventID: '<?php echo esc_js( $event_id ); ?>'});
		</script>
		<?php
		
		// Server CAPI
		$event_data = [
			'event_name' => 'ViewContent',
			'event_id'   => $event_id,
			'custom_data' => [
				'content_ids' => [ $content_id ],
				'content_name' => $content_name,
				'content_type' => 'product',
				'value' => $value,
				'currency' => $currency,
			],
			'user_data' => $this->get_current_user_data(),
		];
		$this->send_event( $event_data );
	}

	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$event_id = $this->generate_event_id( 'atc' );
		$value = (float) get_post_meta( $product_id, '_price', true );
		$currency = get_woocommerce_currency();
		
		// Store event for footer output (since this hook runs during AJAX/form processing)
		WC()->session->set( 'wsa_atc_event', [
			'event_id' => $event_id,
			'product_id' => $product_id,
			'value' => $value,
			'currency' => $currency,
		]);
		
		// Server CAPI (fires immediately)
		$event_data = [
			'event_name' => 'AddToCart',
			'event_id'   => $event_id,
			'custom_data' => [
				'content_ids' => [ (string) $product_id ],
				'content_type' => 'product',
				'value' => $value,
				'currency' => $currency,
			],
			'user_data' => $this->get_current_user_data(),
		];
		$this->send_event( $event_data );
	}

	public function track_initiate_checkout() {
		// Only fire on checkout page, NOT on thank you page
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) return;

		// Prevent duplicate firing on same page load
		static $fired = false;
		if ( $fired ) return;
		$fired = true;

		$event_id = $this->generate_event_id( 'ic' );
		
		// Get cart product IDs
		$content_ids = [];
		$content_ids_js = [];
		foreach ( $cart->get_cart() as $cart_item ) {
			$pid = (string) $cart_item['product_id'];
			$content_ids[] = $pid;
			$content_ids_js[] = "'" . esc_js( $pid ) . "'";
		}
		
		$value = (float) $cart->get_total( 'edit' );
		$currency = get_woocommerce_currency();
		$num_items = $cart->get_cart_contents_count();
		
		// Browser Pixel
		?>
		<script>
		if (typeof fbq !== 'undefined') {
			fbq('track', 'InitiateCheckout', {
				content_ids: [<?php echo implode( ',', $content_ids_js ); ?>],
				content_type: 'product',
				value: <?php echo esc_js( $value ); ?>,
				currency: '<?php echo esc_js( $currency ); ?>',
				num_items: <?php echo esc_js( $num_items ); ?>
			}, {eventID: '<?php echo esc_js( $event_id ); ?>'});
			console.log('WSA: InitiateCheckout event fired', '<?php echo esc_js( $event_id ); ?>');
		}
		</script>
		<?php
		
		// Server CAPI
		$event_data = [
			'event_name' => 'InitiateCheckout',
			'event_id'   => $event_id,
			'event_source_url' => wc_get_checkout_url(),
			'custom_data' => [
				'content_ids' => $content_ids,
				'content_type' => 'product',
				'value' => $value,
				'currency' => $currency,
				'num_items' => $num_items,
			],
			'user_data' => $this->get_current_user_data(),
		];
		$this->send_event( $event_data );
	}

	public function track_purchase( $order_id ) {
		if ( ! $order_id ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// Prevent duplicate firing
		if ( $order->get_meta( '_wsa_meta_purchase_fired' ) === 'yes' ) {
			return;
		}

		// Skip automatic Purchase event for high-risk orders on the frontend (Thank You page)
		// These should only be sent manually from the admin order list after verification
		if ( ! is_admin() ) {
			$order_id_raw = $order->get_id();
			$risk_score   = (int) get_post_meta( $order_id_raw, '_wsa_risk_score', true );
			$threshold    = (int) get_option( 'wsa_auto_action_score', 80 );
			
			if ( $risk_score >= $threshold && $risk_score > 0 ) {
				return; // Block automated event for high-risk orders
			}
			
			// Also skip if order is explicitly on-hold (likely due to our auto-action)
			if ( $order->get_status() === 'on-hold' ) {
				return;
			}
		}

		$event_id = 'purchase_' . $order_id . '_' . time();
		$value = (float) $order->get_total();
		$currency = $order->get_currency();
		
		$content_ids = [];
		$content_ids_js = [];
		foreach ( $order->get_items() as $item ) {
			$pid = (string) $item->get_product_id();
			$content_ids[] = $pid;
			$content_ids_js[] = "'" . esc_js( $pid ) . "'";
		}
		
		// Browser Pixel (only on frontend thank you page)
		if ( ! is_admin() && ( is_wc_endpoint_url( 'order-received' ) || isset( $_GET['key'] ) ) ) {
			?>
			<script>
			if (typeof fbq !== 'undefined') {
				fbq('track', 'Purchase', {
					content_ids: [<?php echo implode( ',', $content_ids_js ); ?>],
					content_type: 'product',
					value: <?php echo esc_js( $value ); ?>,
					currency: '<?php echo esc_js( $currency ); ?>'
				}, {eventID: '<?php echo esc_js( $event_id ); ?>'});
			}
			</script>
			<?php
		}

		// Server CAPI
		$event_data = [
			'event_name' => 'Purchase',
			'event_id'   => $event_id,
			'custom_data' => [
				'value' => $value,
				'currency' => $currency,
				'content_ids' => $content_ids,
				'content_type' => 'product',
			],
			'user_data' => array_merge( $this->get_current_user_data(), [
				'em' => [ hash( 'sha256', strtolower( trim( $order->get_billing_email() ) ) ) ],
				'ph' => [ hash( 'sha256', $order->get_billing_phone() ) ],
				'fn' => [ hash( 'sha256', strtolower( trim( $order->get_billing_first_name() ) ) ) ],
				'ln' => [ hash( 'sha256', strtolower( trim( $order->get_billing_last_name() ) ) ) ],
				'ct' => [ hash( 'sha256', strtolower( trim( $order->get_billing_city() ) ) ) ],
				'st' => [ hash( 'sha256', strtolower( trim( $order->get_billing_state() ) ) ) ],
				'zp' => [ hash( 'sha256', strtolower( trim( $order->get_billing_postcode() ) ) ) ],
				'country' => [ hash( 'sha256', strtolower( trim( $order->get_billing_country() ) ) ) ],
			] )
		];
		
		$this->send_event( $event_data );
		
		// Mark as fired
		$order->update_meta_data( '_wsa_meta_purchase_fired', 'yes' );
		$order->save();
	}

	private function get_current_user_data() {
		$user_data = [];
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$user_data['em'] = [ hash( 'sha256', strtolower( trim( $user->user_email ) ) ) ];
			$user_data['fn'] = [ hash( 'sha256', strtolower( trim( $user->first_name ) ) ) ];
			$user_data['ln'] = [ hash( 'sha256', strtolower( trim( $user->last_name ) ) ) ];
		}
		return $user_data;
	}

	private function send_event( $data ) {
		require_once WSA_PATH . 'includes/Modules/MetaCAPI/EventSender.php';
		$sender = new EventSender();
		$sender->send( $data );
	}
}
