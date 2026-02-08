<?php
namespace WooSmartAutomation\Core;

class Assets {

	/**
	 * Initialize asset hooks
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_order_table_styles' ] );
	}

	/**
	 * Enqueue custom styles on WooCommerce Order list pages
	 * Supports both legacy (post-type) and HPOS (wc-orders) screens
	 */
	public function enqueue_order_table_styles( $hook ) {
		// Legacy orders: edit.php?post_type=shop_order
		$is_legacy_orders = ( $hook === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'shop_order' );

		// HPOS orders: woocommerce_page_wc-orders
		$is_hpos_orders = ( strpos( $hook, 'wc-orders' ) !== false );

		if ( ! $is_legacy_orders && ! $is_hpos_orders ) {
			return;
		}

		// Load Jost font
		\wp_enqueue_style(
			'wsa-google-fonts-orders',
			'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&display=swap',
			[],
			WSA_VERSION
		);

		// Load order table custom CSS
		\wp_enqueue_style(
			'wsa-wc-order-table',
			WSA_URL . 'assets/css/wc-order-table.css',
			[ 'wsa-google-fonts-orders' ],
			WSA_VERSION
		);
	}
}
