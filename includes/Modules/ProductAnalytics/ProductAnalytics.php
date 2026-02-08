<?php
namespace WooSmartAutomation\Modules\ProductAnalytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart Product Interest Score Module
 */
class ProductAnalytics {

	private $weights = [
		'view'     => 1,
		'atc'      => 5,
		'checkout' => 10,
		'purchase' => 50
	];

	public function init() {
		// Track Views
		add_action( 'wp_head', [ $this, 'track_product_view' ] );

		// Track Add to Cart
		add_action( 'woocommerce_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 6 );

		// Track Initiate Checkout (when arriving at checkout page)
		add_action( 'woocommerce_before_checkout_form', [ $this, 'track_checkout' ] );

		// Track Purchase
		add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ], 10, 1 );

		// Admin Product List Columns
		if ( is_admin() ) {
			add_filter( 'manage_edit-product_columns', [ $this, 'add_interest_column' ] );
			add_action( 'manage_product_posts_custom_column', [ $this, 'display_interest_column' ], 10, 2 );
			add_filter( 'manage_edit-product_sortable_columns', [ $this, 'make_interest_column_sortable' ] );
			add_action( 'pre_get_posts', [ $this, 'interest_column_sort_logic' ] );
			add_action( 'restrict_manage_posts', [ $this, 'add_interest_filter' ] );
			add_action( 'admin_head', [ $this, 'add_admin_styles' ] );
		}
	}

	public function track_product_view() {
		if ( ! is_product() ) return;
		global $post;
		$this->increment_score( $post->ID, 'view' );
	}

	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$this->increment_score( $product_id, 'atc' );
	}

	public function track_checkout() {
		// Only track if we are on checkout page and it's a real request
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) return;
		
		$cart = WC()->cart->get_cart();
		foreach ( $cart as $cart_item ) {
			$this->increment_score( $cart_item['product_id'], 'checkout' );
		}
	}

	public function track_purchase( $order_id ) {
		if ( ! $order_id ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// Prevent duplicate tracking for the same order
		if ( $order->get_meta( '_wsa_analytics_tracked' ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$this->increment_score( $item->get_product_id(), 'purchase' );
		}

		$order->update_meta_data( '_wsa_analytics_tracked', 'yes' );
		$order->save();
	}

	private function increment_score( $product_id, $type ) {
		$meta_key = "_wsa_{$type}_count";
		$count    = (int) get_post_meta( $product_id, $meta_key, true );
		$count++;
		update_post_meta( $product_id, $meta_key, $count );

		// Re-calculate Total Score
		$views    = (int) get_post_meta( $product_id, '_wsa_view_count', true );
		$atc      = (int) get_post_meta( $product_id, '_wsa_atc_count', true );
		$checkout = (int) get_post_meta( $product_id, '_wsa_checkout_count', true );
		$purchase = (int) get_post_meta( $product_id, '_wsa_purchase_count', true );

		$total_score = ( $views * $this->weights['view'] ) + 
		               ( $atc * $this->weights['atc'] ) + 
		               ( $checkout * $this->weights['checkout'] ) + 
		               ( $purchase * $this->weights['purchase'] );

		update_post_meta( $product_id, '_wsa_total_interest_score', $total_score );
	}

	// --- Admin UI ---

	public function add_interest_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			if ( $key === 'sku' ) { // Add before SKU to avoid overlap with narrow columns
				$new_columns['wsa_interest'] = '🔥 Interest';
			}
			$new_columns[$key] = $value;
		}
		
		// If SKU column not found, add to end
		if ( ! isset( $new_columns['wsa_interest'] ) ) {
			$new_columns['wsa_interest'] = '🔥 Interest';
		}
		
		return $new_columns;
	}

	public function display_interest_column( $column, $post_id ) {
		if ( $column === 'wsa_interest' ) {
			$score = (int) get_post_meta( $post_id, '_wsa_total_interest_score', true );
			
			echo '<div class="wsa-interest-cell">';
			if ( $score >= 500 ) {
				echo '<span class="wsa-badge wsa-high-interest" title="Score: ' . $score . '">🔥 High</span>';
			} elseif ( $score >= 100 ) {
				echo '<span class="wsa-badge wsa-medium-interest" title="Score: ' . $score . '">⚠ Medium</span>';
			} else {
				echo '<span class="wsa-badge wsa-low-interest" title="Score: ' . $score . '">❄ Low</span>';
			}
			echo '<div class="wsa-score-text">Score: ' . number_format($score) . '</div>';
			echo '</div>';
		}
	}

	public function make_interest_column_sortable( $columns ) {
		$columns['wsa_interest'] = 'wsa_interest';
		return $columns;
	}

	public function add_interest_filter() {
		global $typenow;
		if ( 'product' !== $typenow ) return;

		$current_v = isset( $_GET['wsa_interest_filter'] ) ? $_GET['wsa_interest_filter'] : '';
		?>
		<select name="wsa_interest_filter">
			<option value=""><?php _e( 'All Interest Levels', 'woo-smart-automation' ); ?></option>
			<option value="high" <?php selected( $current_v, 'high' ); ?>>🔥 High Interest</option>
			<option value="medium" <?php selected( $current_v, 'medium' ); ?>>⚠ Medium Interest</option>
			<option value="low" <?php selected( $current_v, 'low' ); ?>>❄ Low Interest</option>
		</select>
		<?php
	}

	public function interest_column_sort_logic( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;

		// Sorting Logic
		$orderby = $query->get( 'orderby' );
		if ( 'wsa_interest' === $orderby ) {
			$query->set( 'meta_key', '_wsa_total_interest_score' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		// Filtering Logic
		if ( isset( $_GET['wsa_interest_filter'] ) && ! empty( $_GET['wsa_interest_filter'] ) ) {
			$filter = sanitize_text_field( $_GET['wsa_interest_filter'] );
			$meta_query = (array) $query->get( 'meta_query' );

			if ( $filter === 'high' ) {
				$meta_query[] = [ 'key' => '_wsa_total_interest_score', 'value' => 500, 'compare' => '>=', 'type' => 'NUMERIC' ];
			} elseif ( $filter === 'medium' ) {
				$meta_query[] = [
					'relation' => 'AND',
					[ 'key' => '_wsa_total_interest_score', 'value' => 100, 'compare' => '>=', 'type' => 'NUMERIC' ],
					[ 'key' => '_wsa_total_interest_score', 'value' => 500, 'compare' => '<', 'type' => 'NUMERIC' ],
				];
			} elseif ( $filter === 'low' ) {
				$meta_query[] = [ 'key' => '_wsa_total_interest_score', 'value' => 100, 'compare' => '<', 'type' => 'NUMERIC' ];
			}

			$query->set( 'meta_query', $meta_query );
		}
	}

	public function add_admin_styles() {
		?>
		<style>
			.column-wsa_interest { width: 110px; }
			.wsa-interest-cell { display: block; line-height: 1.2; }
			.wsa-badge {
				padding: 3px 8px;
				border-radius: 4px;
				font-size: 10px;
				font-weight: 700;
				display: inline-block;
				margin-bottom: 2px;
				text-transform: uppercase;
				letter-spacing: 0.02em;
				width: 100%;
				box-sizing: border-box;
				text-align: center;
			}
			.wsa-high-interest { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
			.wsa-medium-interest { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
			.wsa-low-interest { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
			.wsa-score-text { font-size: 10px; color: #888; text-align: center; margin-top: 2px; }
		</style>
		<?php
		}
}
