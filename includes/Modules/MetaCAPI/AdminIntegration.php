<?php
namespace WooSmartAutomation\Modules\MetaCAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Integration for Meta Conversions API
 */
class AdminIntegration {

	public function init() {
		// Add Meta CAPI column to WooCommerce order list
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_capi_column' ], 30 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'display_capi_column' ], 30, 2 );

		// HPOS Support
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_capi_column' ], 30 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'display_capi_column' ], 30, 2 );

		// Bulk Actions
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'register_bulk_actions' ] );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );

		// AJAX for manual fire
		add_action( 'wp_ajax_wsa_meta_capi_manual_fire', [ $this, 'handle_manual_fire' ] );

		add_action( 'admin_head', [ $this, 'add_admin_styles' ] );
		add_action( 'admin_footer', [ $this, 'add_admin_scripts' ] );

		// Admin Notices for bulk action results
		add_action( 'admin_notices', [ $this, 'bulk_action_admin_notice' ] );
	}

	/**
	 * Add Meta CAPI column to order list
	 */
	public function add_capi_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( $key === 'order_status' ) {
				$new_columns['wsa_meta_capi'] = 'Meta CAPI';
			}
		}

		if ( ! isset( $new_columns['wsa_meta_capi'] ) ) {
			$new_columns['wsa_meta_capi'] = 'Meta CAPI';
		}

		return $new_columns;
	}

	/**
	 * Display Meta CAPI status and fire button
	 */
	public function display_capi_column( $column, $post_or_order ) {
		if ( $column === 'wsa_meta_capi' ) {
			$order = ( $post_or_order instanceof \WC_Order ) ? $post_or_order : wc_get_order( $post_or_order );
			if ( ! $order ) return;

			$order_id = $order->get_id();
			$fired    = $order->get_meta( '_wsa_meta_purchase_fired' );
			$status   = $order->get_status();

			echo '<div class="wsa-capi-status-wrapper" id="wsa-capi-wrapper-' . $order_id . '">';
			
			if ( $fired === 'yes' ) {
				echo '<span class="wsa-status-badge sent" title="Purchase event already sent to Meta"><span class="dashicons dashicons-yes"></span> Sent</span>';
			} elseif ( in_array( $status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
				echo '<span class="wsa-status-badge disabled" title="Cannot send Purchase event for ' . ucfirst( $status ) . ' orders">N/A</span>';
			} else {
				$icon = '<svg class="wsa-meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M400 32H48A48 48 0 0 0 0 80v352a48 48 0 0 0 48 48h137.25V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.27c-30.81 0-40.42 19.12-40.42 38.73V256h68.78l-11 71.69h-57.78V480H400a48 48 0 0 0 48-48V80a48 48 0 0 0-48-48z"/></svg>';
				echo '<button type="button" class="button wsa-fire-capi-btn" data-order-id="' . $order_id . '" title="Manually send Purchase event to Meta">' . $icon . ' Purchase</button>';
			}
			
			echo '</div>';
		}
	}

	/**
	 * Register bulk action
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['wsa_send_to_meta_capi'] = 'Send to Meta CAPI';
		return $bulk_actions;
	}

	/**
	 * Handle bulk action
	 */
	public function handle_bulk_actions( $redirect_to, $action, $order_ids ) {
		if ( $action !== 'wsa_send_to_meta_capi' ) {
			return $redirect_to;
		}

		$sent_count = 0;
		$skipped_count = 0;

		$meta_capi = new MetaCAPI();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) continue;

			// Skip if already fired
			if ( $order->get_meta( '_wsa_meta_purchase_fired' ) === 'yes' ) {
				$skipped_count++;
				continue;
			}

			// Skip if Cancelled, Refunded or Failed
			if ( in_array( $order->get_status(), [ 'cancelled', 'refunded', 'failed' ] ) ) {
				$skipped_count++;
				continue;
			}

			// Fire purchase event
			$meta_capi->track_purchase( $order_id );
			$sent_count++;
		}

		$redirect_to = add_query_arg( [
			'wsa_capi_sent'    => $sent_count,
			'wsa_capi_skipped' => $skipped_count,
		], $redirect_to );

		return $redirect_to;
	}

	/**
	 * Show admin notice after bulk action
	 */
	public function bulk_action_admin_notice() {
		if ( ! empty( $_GET['wsa_capi_sent'] ) || ! empty( $_GET['wsa_capi_skipped'] ) ) {
			$sent    = intval( $_GET['wsa_capi_sent'] ?? 0 );
			$skipped = intval( $_GET['wsa_capi_skipped'] ?? 0 );

			echo '<div class="updated notice is-dismissible">';
			echo '<p>';
			if ( $sent > 0 ) {
				printf( 'Successfully sent Purchase events for %d orders to Meta CAPI. ', $sent );
			}
			if ( $skipped > 0 ) {
				printf( 'Skipped %d orders (already sent). ', $skipped );
			}
			echo '</p>';
			echo '</div>';
		}
	}

	/**
	 * AJAX handler for manual fire
	 */
	public function handle_manual_fire() {
		check_ajax_referer( 'wsa_meta_capi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => 'Invalid Order ID' ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		if ( $order->get_meta( '_wsa_meta_purchase_fired' ) === 'yes' ) {
			wp_send_json_error( [ 'message' => 'Already sent to Meta' ] );
		}

		if ( in_array( $order->get_status(), [ 'cancelled', 'refunded', 'failed' ] ) ) {
			wp_send_json_error( [ 'message' => 'Cannot send Purchase event for ' . $order->get_status() . ' orders' ] );
		}

		$meta_capi = new MetaCAPI();
		$meta_capi->track_purchase( $order_id );

		wp_send_json_success( [ 'message' => 'Sent successfully' ] );
	}

	/**
	 * Admin styles
	 */
	public function add_admin_styles() {
		?>
		<style>
			.column-wsa_meta_capi { width: 110px !important; text-align: center !important; }
			.wsa-capi-status-wrapper { display: flex; justify-content: center; align-items: center; min-height: 30px; }
			.wsa-capi-badge {
				padding: 4px 8px;
				border-radius: 4px;
				font-size: 11px;
				font-weight: 600;
				display: inline-block;
				line-height: 1;
			}
			.wsa-capi-badge.sent {
				background: #dcfce7;
				color: #166534;
				border: 1px solid #bbf7d0;
			}
			.wsa-fire-capi-btn {
				padding: 2px 10px !important;
				font-size: 11px !important;
				height: 26px !important;
				line-height: 24px !important;
				background: #0081fb !important;
				color: #fff !important;
				border: none !important;
				font-weight: 600 !important;
				display: inline-flex !important;
				align-items: center;
				gap: 4px;
				border-radius: 4px !important;
				box-shadow: 0 1px 2px rgba(0,0,0,0.1);
				transition: all 0.2s ease;
			}
			.wsa-fire-capi-btn:hover {
				background: #0073e6 !important;
				color: #fff !important;
				transform: translateY(-1px);
				box-shadow: 0 2px 4px rgba(0,0,0,0.15);
			}
			.wsa-fire-capi-btn .wsa-meta-icon {
				width: 12px;
				height: 12px;
			}
			.wsa-status-badge {
				padding: 4px 8px;
				border-radius: 4px;
				font-size: 11px;
				font-weight: 600;
				display: inline-flex;
				align-items: center;
				gap: 4px;
				line-height: 1;
			}
			.wsa-status-badge.sent {
				background: #dcfce7;
				color: #166534;
				border: 1px solid #bbf7d0;
			}
			.wsa-status-badge.disabled {
				background: #f1f5f9;
				color: #64748b;
				border: 1px solid #e2e8f0;
				cursor: not-allowed;
			}
			.wsa-status-badge .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
			}
		</style>
		<?php
	}

	/**
	 * Admin scripts
	 */
	public function add_admin_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->id !== 'edit-shop_order' && $screen->id !== 'woocommerce_page_wc-orders' ) ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			$(document).on('click', '.wsa-fire-capi-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var orderId = $btn.data('order-id');
				var $wrapper = $('#wsa-capi-wrapper-' + orderId);

				if ($btn.hasClass('disabled')) return;

				if (!$btn.data('original-html')) {
					$btn.data('original-html', $btn.html());
				}

				$btn.addClass('disabled').html('<span class="dashicons dashicons-update spin"></span>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wsa_meta_capi_manual_fire',
						order_id: orderId,
						nonce: '<?php echo wp_create_nonce( "wsa_meta_capi_admin" ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$wrapper.html('<span class="wsa-status-badge sent"><span class="dashicons dashicons-yes"></span> Sent</span>');
						} else {
							alert(response.data.message || 'Error occurred');
							$btn.removeClass('disabled').html($btn.data('original-html'));
						}
					},
					error: function() {
						alert('Connection error');
						$btn.removeClass('disabled').html($btn.data('original-html'));
					}
				});
			});
		});
		</script>
		<?php
	}
}
