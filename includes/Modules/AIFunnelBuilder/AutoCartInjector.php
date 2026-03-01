<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto Cart Injector
 * 
 * Automatically adds products to cart when landing page is visited.
 * Handles product association with landing pages.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class AutoCartInjector {

	/**
	 * Initialize auto cart injector
	 */
	public function init() {
		// Hook into template_redirect to inject products
		add_action( 'template_redirect', [ $this, 'maybe_add_to_cart' ], 5 );

		// Add funnel data to checkout
		add_action( 'woocommerce_checkout_create_order', [ $this, 'add_funnel_data_to_order' ], 10, 2 );
	}

	/**
	 * Maybe add product to cart when visiting landing page
	 */
	public function maybe_add_to_cart() {
		// Check if we're on a landing page
		if ( ! is_singular( 'wsa_landing_page' ) ) {
			return;
		}

		$page_id = get_the_ID();
		$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );

		if ( ! $product_id ) {
			return;
		}

		// Check if product is valid
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		// Get quantity and variation
		$quantity = get_post_meta( $page_id, '_wsa_funnel_quantity', true ) ?: 1;
		$variation_id = get_post_meta( $page_id, '_wsa_funnel_variation_id', true );

		// Store landing page reference in session
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'wsa_landing_page_id', $page_id );
			WC()->session->set( 'wsa_funnel_id', get_post_meta( $page_id, '_wsa_funnel_id', true ) );
		}

		// Check auto-add setting (could be configured per funnel)
		$funnel_id = get_post_meta( $page_id, '_wsa_funnel_id', true );
		$auto_add = $this->should_auto_add_to_cart( $funnel_id );

		if ( ! $auto_add ) {
			return;
		}

		// Check if product already in cart from this landing page
		$cart_item_key = $this->get_cart_item_key( $product_id, $variation_id, $page_id );

		if ( $cart_item_key ) {
			// Product already in cart, optionally update quantity
			return;
		}

		// Clear cart if configured to do so
		if ( $this->should_clear_cart( $funnel_id ) ) {
			WC()->cart->empty_cart();
		}

		// Add to cart
		$cart_data = [
			'wsa_landing_page_id' => $page_id,
			'wsa_funnel_id'       => $funnel_id,
		];

		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$variation_attributes = $variation->get_variation_attributes();
				WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attributes, $cart_data );
			}
		} else {
			WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_data );
		}
	}

	/**
	 * Check if auto-add to cart is enabled for funnel
	 *
	 * @param int $funnel_id Funnel ID
	 * @return bool
	 */
	private function should_auto_add_to_cart( $funnel_id ) {
		// Default to true for funnel landing pages
		// Can be customized with funnel meta
		$auto_add = get_post_meta( $funnel_id, '_wsa_auto_add_to_cart', true );
		
		// Default to true if not set
		return $auto_add !== 'no';
	}

	/**
	 * Check if cart should be cleared before adding
	 *
	 * @param int $funnel_id Funnel ID
	 * @return bool
	 */
	private function should_clear_cart( $funnel_id ) {
		$clear_cart = get_post_meta( $funnel_id, '_wsa_clear_cart', true );
		
		// Default to true for funnel purchases
		return $clear_cart !== 'no';
	}

	/**
	 * Get cart item key for product
	 *
	 * @param int $product_id Product ID
	 * @param int $variation_id Variation ID
	 * @param int $page_id Landing page ID
	 * @return string|false Cart item key or false
	 */
	private function get_cart_item_key( $product_id, $variation_id, $page_id ) {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$item_product_id = $cart_item['product_id'];
			$item_variation_id = $cart_item['variation_id'] ?? 0;
			$item_page_id = $cart_item['wsa_landing_page_id'] ?? 0;

			if ( $item_product_id == $product_id && 
			     $item_variation_id == $variation_id && 
			     $item_page_id == $page_id ) {
				return $cart_item_key;
			}
		}

		return false;
	}

	/**
	 * Add funnel data to order during checkout
	 *
	 * @param WC_Order $order Order object
	 * @param array $data Checkout data
	 */
	public function add_funnel_data_to_order( $order, $data ) {
		// Get from session
		if ( function_exists( 'WC' ) && WC()->session ) {
			$page_id = WC()->session->get( 'wsa_landing_page_id' );
			$funnel_id = WC()->session->get( 'wsa_funnel_id' );

			if ( $page_id ) {
				$order->update_meta_data( '_wsa_landing_page_id', $page_id );
			}

			if ( $funnel_id ) {
				$order->update_meta_data( '_wsa_funnel_id', $funnel_id );
				$order->update_meta_data( '_wsa_funnel_order', 'yes' );
			}

			// Clear session data
			WC()->session->set( 'wsa_landing_page_id', null );
			WC()->session->set( 'wsa_funnel_id', null );
		}

		// Also check cart items for funnel data
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['wsa_funnel_id'] ) && ! $order->get_meta( '_wsa_funnel_id' ) ) {
				$order->update_meta_data( '_wsa_funnel_id', $cart_item['wsa_funnel_id'] );
				$order->update_meta_data( '_wsa_funnel_order', 'yes' );
			}

			if ( ! empty( $cart_item['wsa_landing_page_id'] ) && ! $order->get_meta( '_wsa_landing_page_id' ) ) {
				$order->update_meta_data( '_wsa_landing_page_id', $cart_item['wsa_landing_page_id'] );
			}
		}
	}

	/**
	 * Get add to cart URL for landing page
	 *
	 * @param int $page_id Landing page ID
	 * @return string URL
	 */
	public function get_add_to_cart_url( $page_id ) {
		$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );
		$quantity = get_post_meta( $page_id, '_wsa_funnel_quantity', true ) ?: 1;
		$variation_id = get_post_meta( $page_id, '_wsa_funnel_variation_id', true );

		if ( ! $product_id ) {
			return '';
		}

		$args = [
			'add-to-cart'          => $product_id,
			'quantity'             => $quantity,
			'wsa_landing_page_id'  => $page_id,
		];

		if ( $variation_id ) {
			$args['variation_id'] = $variation_id;
		}

		return add_query_arg( $args, wc_get_checkout_url() );
	}

	/**
	 * Get direct checkout URL for landing page
	 *
	 * @param int $page_id Landing page ID
	 * @return string Checkout URL
	 */
	public function get_direct_checkout_url( $page_id ) {
		$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );

		if ( ! $product_id ) {
			return wc_get_checkout_url();
		}

		return add_query_arg( [
			'wsa_funnel_checkout' => $page_id,
		], wc_get_checkout_url() );
	}

	/**
	 * Render product info in landing page
	 *
	 * @param int $page_id Landing page ID
	 * @return string Product info HTML
	 */
	public function render_product_info( $page_id ) {
		$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );

		if ( ! $product_id ) {
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		ob_start();
		?>
		<div class="wsa-product-info">
			<h3 class="wsa-product-name"><?php echo esc_html( $product->get_name() ); ?></h3>
			<div class="wsa-product-price">
				<?php echo $product->get_price_html(); ?>
			</div>
			<?php if ( $product->get_short_description() ) : ?>
			<div class="wsa-product-description">
				<?php echo wp_kses_post( $product->get_short_description() ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if current page is a funnel landing page
	 *
	 * @return bool
	 */
	public static function is_funnel_landing_page() {
		return is_singular( 'wsa_landing_page' );
	}

	/**
	 * Get current funnel context
	 *
	 * @return array|null Funnel context or null
	 */
	public static function get_current_funnel_context() {
		if ( ! self::is_funnel_landing_page() ) {
			return null;
		}

		$page_id = get_the_ID();

		return [
			'landing_page_id' => $page_id,
			'funnel_id'       => get_post_meta( $page_id, '_wsa_funnel_id', true ),
			'product_id'      => get_post_meta( $page_id, '_wsa_funnel_product_id', true ),
			'variation_id'    => get_post_meta( $page_id, '_wsa_funnel_variation_id', true ),
			'quantity'        => get_post_meta( $page_id, '_wsa_funnel_quantity', true ) ?: 1,
		];
	}
}
