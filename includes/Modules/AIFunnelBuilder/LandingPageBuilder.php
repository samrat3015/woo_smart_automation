<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Landing Page Builder
 * 
 * Handles landing page CRUD, storage, and retrieval.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class LandingPageBuilder {

	/**
	 * Create a new landing page
	 *
	 * @param array $data Landing page data
	 * @return int|WP_Error Landing page ID or error
	 */
	public function create_landing_page( $data ) {
		$page_data = [
			'post_type'   => 'wsa_landing_page',
			'post_title'  => sanitize_text_field( $data['title'] ?? __( 'New Landing Page', 'woo-smart-automation' ) ),
			'post_name'   => sanitize_title( $data['slug'] ?? '' ),
			'post_status' => $data['status'] ?? 'draft',
		];

		$page_id = wp_insert_post( $page_data );

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		// Save landing page meta
		$this->save_landing_page_meta( $page_id, $data );

		return $page_id;
	}

	/**
	 * Update an existing landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @param array $data Updated data
	 * @return bool|WP_Error True on success, error otherwise
	 */
	public function update_landing_page( $page_id, $data ) {
		$page = get_post( $page_id );

		if ( ! $page || $page->post_type !== 'wsa_landing_page' ) {
			return new \WP_Error( 'invalid_page', __( 'Invalid landing page ID.', 'woo-smart-automation' ) );
		}

		// Update post data
		$update_data = [ 'ID' => $page_id ];

		if ( isset( $data['title'] ) ) {
			$update_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['slug'] ) ) {
			$update_data['post_name'] = sanitize_title( $data['slug'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['post_status'] = sanitize_key( $data['status'] );
		}

		wp_update_post( $update_data );

		// Save meta
		$this->save_landing_page_meta( $page_id, $data );

		return true;
	}

	/**
	 * Save landing page meta data
	 *
	 * @param int $page_id Landing page post ID
	 * @param array $data Data to save
	 */
	private function save_landing_page_meta( $page_id, $data ) {
		// Funnel ID
		if ( isset( $data['funnel_id'] ) ) {
			update_post_meta( $page_id, '_wsa_funnel_id', absint( $data['funnel_id'] ) );
		}

		// Product ID
		if ( isset( $data['product_id'] ) ) {
			update_post_meta( $page_id, '_wsa_funnel_product_id', absint( $data['product_id'] ) );
		}

		// Variation ID
		if ( isset( $data['variation_id'] ) ) {
			update_post_meta( $page_id, '_wsa_funnel_variation_id', absint( $data['variation_id'] ) );
		}

		// Quantity
		if ( isset( $data['quantity'] ) ) {
			update_post_meta( $page_id, '_wsa_funnel_quantity', absint( $data['quantity'] ) );
		}

		// Generated HTML
		if ( isset( $data['html'] ) ) {
			update_post_meta( $page_id, '_wsa_generated_html', $data['html'] );
		}

		// Generation meta (provider, model, timestamp)
		if ( isset( $data['generation_meta'] ) ) {
			update_post_meta( $page_id, '_wsa_generation_meta', $data['generation_meta'] );
		}

		// Images array
		if ( isset( $data['images'] ) ) {
			$images_json = is_string( $data['images'] ) 
				? $data['images'] 
				: wp_json_encode( $data['images'] );
			update_post_meta( $page_id, '_wsa_images', $images_json );
		}

		// Prompt used
		if ( isset( $data['prompt'] ) ) {
			update_post_meta( $page_id, '_wsa_prompt_used', $data['prompt'] );
		}

		// Template used
		if ( isset( $data['template'] ) ) {
			update_post_meta( $page_id, '_wsa_template_used', sanitize_key( $data['template'] ) );
		}
	}

	/**
	 * Get a landing page by ID
	 *
	 * @param int $page_id Landing page post ID
	 * @return array|null Landing page data or null
	 */
	public function get_landing_page( $page_id ) {
		$page = get_post( $page_id );

		if ( ! $page || $page->post_type !== 'wsa_landing_page' ) {
			return null;
		}

		$images_json = get_post_meta( $page_id, '_wsa_images', true );
		$images = $images_json ? json_decode( $images_json, true ) : [];

		return [
			'id'              => $page->ID,
			'title'           => $page->post_title,
			'slug'            => $page->post_name,
			'status'          => $page->post_status,
			'url'             => get_permalink( $page->ID ),
			'funnel_id'       => get_post_meta( $page_id, '_wsa_funnel_id', true ),
			'product_id'      => get_post_meta( $page_id, '_wsa_funnel_product_id', true ),
			'variation_id'    => get_post_meta( $page_id, '_wsa_funnel_variation_id', true ),
			'quantity'        => get_post_meta( $page_id, '_wsa_funnel_quantity', true ) ?: 1,
			'html'            => get_post_meta( $page_id, '_wsa_generated_html', true ),
			'generation_meta' => get_post_meta( $page_id, '_wsa_generation_meta', true ),
			'images'          => is_array( $images ) ? $images : [],
			'prompt'          => get_post_meta( $page_id, '_wsa_prompt_used', true ),
			'template'        => get_post_meta( $page_id, '_wsa_template_used', true ),
			'created_at'      => $page->post_date,
			'modified_at'     => $page->post_modified,
		];
	}

	/**
	 * Get all landing pages
	 *
	 * @param array $args Query arguments
	 * @return array Landing pages with pagination info
	 */
	public function get_landing_pages( $args = [] ) {
		$defaults = [
			'posts_per_page' => 20,
			'paged'          => 1,
			'post_status'    => [ 'publish', 'draft' ],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'wsa_landing_page';

		$query = new \WP_Query( $args );
		$pages = [];

		foreach ( $query->posts as $post ) {
			$pages[] = $this->get_landing_page( $post->ID );
		}

		return [
			'pages'       => $pages,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		];
	}

	/**
	 * Delete a landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @param bool $force Force delete (bypass trash)
	 * @return bool Success status
	 */
	public function delete_landing_page( $page_id, $force = false ) {
		$page = get_post( $page_id );

		if ( ! $page || $page->post_type !== 'wsa_landing_page' ) {
			return false;
		}

		$result = wp_delete_post( $page_id, $force );

		return $result !== false;
	}

	/**
	 * Duplicate a landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @return int|WP_Error New landing page ID or error
	 */
	public function duplicate_landing_page( $page_id ) {
		$original = $this->get_landing_page( $page_id );

		if ( ! $original ) {
			return new \WP_Error( 'invalid_page', __( 'Original landing page not found.', 'woo-smart-automation' ) );
		}

		$data = [
			'title'           => sprintf( __( '%s (Copy)', 'woo-smart-automation' ), $original['title'] ),
			'slug'            => $original['slug'] . '-copy',
			'status'          => 'draft',
			'funnel_id'       => $original['funnel_id'],
			'product_id'      => $original['product_id'],
			'variation_id'    => $original['variation_id'],
			'quantity'        => $original['quantity'],
			'html'            => $original['html'],
			'images'          => $original['images'],
			'prompt'          => $original['prompt'],
			'template'        => $original['template'],
		];

		return $this->create_landing_page( $data );
	}

	/**
	 * Get the rendered HTML for a landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @return string HTML content
	 */
	public function get_rendered_html( $page_id ) {
		$html = get_post_meta( $page_id, '_wsa_generated_html', true );

		if ( empty( $html ) ) {
			return '';
		}

		// Get landing page data
		$funnel_id  = get_post_meta( $page_id, '_wsa_funnel_id', true );
		$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );

		// Generate checkout form HTML (works with or without funnel)
		$checkout_html = $this->generate_checkout_form_html( $funnel_id, $page_id, $product_id );

		if ( ! empty( $checkout_html ) ) {
			$has_placeholder = false;

			// Replace placeholder with actual form
			if ( strpos( $html, 'id="wsa-checkout-form"' ) !== false ) {
				$html = str_replace(
					'<div id="wsa-checkout-form" class="wsa-checkout-container">',
					'<div id="wsa-checkout-form" class="wsa-checkout-container">' . $checkout_html,
					$html
				);
				$has_placeholder = true;
			}

			// Also try alternative placeholder comment
			if ( strpos( $html, '<!-- Checkout form will be injected here automatically -->' ) !== false ) {
				$html = str_replace(
					'<!-- Checkout form will be injected here automatically -->',
					$checkout_html,
					$html
				);
				$has_placeholder = true;
			}

			// If no placeholder found, append checkout form before </body> or at end
			if ( ! $has_placeholder ) {
				$checkout_section = '<div id="wsa-checkout-form" class="wsa-checkout-container" style="max-width:680px;margin:40px auto;padding:30px;">' . $checkout_html . '</div>';

				if ( strpos( $html, '</body>' ) !== false ) {
					$html = str_replace( '</body>', $checkout_section . '</body>', $html );
				} else {
					$html .= $checkout_section;
				}
			}
		}

		return $html;
	}

	/**
	 * Generate CartFlows-style checkout form HTML with order summary
	 *
	 * @param int $funnel_id Funnel post ID (can be 0)
	 * @param int $page_id Landing page post ID
	 * @param int $product_id Product ID (fallback if no funnel)
	 * @return string Checkout form HTML
	 */
	public function generate_checkout_form_html( $funnel_id = 0, $page_id = 0, $product_id = 0 ) {
		$funnel_manager = new FunnelManager();
		$funnel = null;
		$fields = [];
		$button = [];

		// Try to get funnel configuration
		if ( $funnel_id ) {
			$funnel = $funnel_manager->get_funnel( $funnel_id );
		}

		if ( $funnel ) {
			$fields = $funnel['checkout_fields']['fields'] ?? [];
			$button = $funnel['checkout_fields']['submit_button'] ?? [];
		}

		// Fallback to default checkout fields if no funnel or empty fields
		if ( empty( $fields ) ) {
			$defaults = $funnel_manager->get_default_checkout_fields();
			$fields = $defaults['fields'] ?? [];
			$button = $defaults['submit_button'] ?? [];
		}

		// Get product info for order summary
		if ( ! $product_id && $page_id ) {
			$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;
		$quantity = $page_id ? ( get_post_meta( $page_id, '_wsa_funnel_quantity', true ) ?: 1 ) : 1;

		ob_start();
		?>
		<!-- CartFlows-Style Order Summary -->
		<?php if ( $product ) : ?>
		<div class="wsa-order-summary">
			<h3 class="wsa-order-summary-title">
				<span class="wsa-icon">🛒</span>
				<?php _e( 'Your Order', 'woo-smart-automation' ); ?>
			</h3>
			<div class="wsa-order-product">
				<div class="wsa-order-product-image">
					<?php if ( $product->get_image_id() ) : ?>
						<img src="<?php echo esc_url( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>">
					<?php else : ?>
						<div class="wsa-no-image">📦</div>
					<?php endif; ?>
				</div>
				<div class="wsa-order-product-info">
					<span class="wsa-order-product-name"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="wsa-order-product-qty"><?php printf( __( 'Qty: %d', 'woo-smart-automation' ), $quantity ); ?></span>
				</div>
				<div class="wsa-order-product-price">
					<?php 
					if ( $product->is_on_sale() ) {
						echo '<del>' . wc_price( $product->get_regular_price() ) . '</del> ';
					}
					echo wc_price( $product->get_price() * $quantity );
					?>
				</div>
			</div>
			<div class="wsa-order-total">
				<span class="wsa-order-total-label"><?php _e( 'Total', 'woo-smart-automation' ); ?></span>
				<span class="wsa-order-total-price"><?php echo wc_price( $product->get_price() * $quantity ); ?></span>
			</div>
			<?php if ( $product->is_on_sale() ) : ?>
			<div class="wsa-order-savings">
				<?php
				$savings = ( $product->get_regular_price() - $product->get_price() ) * $quantity;
				printf( __( '🎉 You save %s!', 'woo-smart-automation' ), wc_price( $savings ) );
				?>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- Checkout Form -->
		<form id="wsa-funnel-checkout-form" class="wsa-checkout-form" method="post">
			<?php wp_nonce_field( 'wsa_funnel_checkout', 'wsa_checkout_nonce' ); ?>
			<input type="hidden" name="wsa_funnel_id" value="<?php echo esc_attr( $funnel_id ); ?>">
			<input type="hidden" name="wsa_landing_page_id" value="<?php echo esc_attr( $page_id ); ?>">

			<h3 class="wsa-checkout-form-title">
				<span class="wsa-icon">📋</span>
				<?php _e( 'Delivery Information', 'woo-smart-automation' ); ?>
			</h3>

			<div class="wsa-checkout-fields">
				<?php foreach ( $fields as $field ) : ?>
					<?php if ( empty( $field['visible'] ) ) continue; ?>
					<div class="wsa-field wsa-field-<?php echo esc_attr( $field['width'] ?? 'full' ); ?>">
						<label for="<?php echo esc_attr( $field['id'] ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
							<?php if ( ! empty( $field['required'] ) ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
						</label>

						<?php if ( isset( $field['type'] ) && $field['type'] === 'select' ) : ?>
							<select 
								name="<?php echo esc_attr( $field['id'] ); ?>" 
								id="<?php echo esc_attr( $field['id'] ); ?>"
								<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
							>
								<option value=""><?php echo esc_html( $field['placeholder'] ?? '' ); ?></option>
								<?php foreach ( ( $field['options'] ?? [] ) as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>">
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( isset( $field['type'] ) && $field['type'] === 'textarea' ) : ?>
							<textarea 
								name="<?php echo esc_attr( $field['id'] ); ?>" 
								id="<?php echo esc_attr( $field['id'] ); ?>"
								placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
								rows="3"
								<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
							></textarea>
						<?php else : ?>
							<input 
								type="<?php echo esc_attr( $field['type'] ?? 'text' ); ?>" 
								name="<?php echo esc_attr( $field['id'] ); ?>" 
								id="<?php echo esc_attr( $field['id'] ); ?>"
								placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
								<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
							>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Payment Method -->
			<div class="wsa-payment-method">
				<h4 class="wsa-payment-title">
					<span class="wsa-icon">💳</span>
					<?php _e( 'Payment Method', 'woo-smart-automation' ); ?>
				</h4>
				<div class="wsa-payment-option wsa-payment-selected">
					<input type="radio" name="payment_method" value="cod" id="payment_cod" checked>
					<label for="payment_cod">
						<span class="wsa-payment-icon">🚚</span>
						<span class="wsa-payment-label"><?php _e( 'Cash on Delivery', 'woo-smart-automation' ); ?></span>
					</label>
				</div>
			</div>

			<div class="wsa-checkout-submit">
				<button 
					type="submit" 
					class="wsa-submit-btn wsa-btn-<?php echo esc_attr( $button['size'] ?? 'large' ); ?>"
					style="background-color: <?php echo esc_attr( $button['color'] ?? '#e74c3c' ); ?>; color: <?php echo esc_attr( $button['text_color'] ?? '#ffffff' ); ?>;"
				>
					<?php echo esc_html( $button['text'] ?? __( '🛒 Place Order Now', 'woo-smart-automation' ) ); ?>
				</button>
				<p class="wsa-secure-notice">🔒 <?php _e( 'Your information is 100% secure', 'woo-smart-automation' ); ?></p>
			</div>

			<div class="wsa-checkout-message" style="display: none;"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save HTML content for a landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @param string $html HTML content
	 * @param array $meta Generation metadata
	 * @return bool Success status
	 */
	public function save_html( $page_id, $html, $meta = [] ) {
		$page = get_post( $page_id );

		if ( ! $page || $page->post_type !== 'wsa_landing_page' ) {
			return false;
		}

		update_post_meta( $page_id, '_wsa_generated_html', $html );

		if ( ! empty( $meta ) ) {
			$meta['saved_at'] = current_time( 'mysql' );
			update_post_meta( $page_id, '_wsa_generation_meta', $meta );
		}

		return true;
	}

	/**
	 * Publish a landing page
	 *
	 * @param int $page_id Landing page post ID
	 * @return bool Success status
	 */
	public function publish( $page_id ) {
		$page = get_post( $page_id );

		if ( ! $page || $page->post_type !== 'wsa_landing_page' ) {
			return false;
		}

		// Check if HTML exists
		$html = get_post_meta( $page_id, '_wsa_generated_html', true );

		if ( empty( $html ) ) {
			return false;
		}

		wp_update_post( [
			'ID'          => $page_id,
			'post_status' => 'publish',
		] );

		return true;
	}

	/**
	 * Get landing page statistics
	 *
	 * @param int $page_id Landing page post ID
	 * @return array Statistics
	 */
	public function get_stats( $page_id ) {
		// Count orders from this landing page
		$orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => [ 'processing', 'completed' ],
			'meta_key'   => '_wsa_landing_page_id',
			'meta_value' => $page_id,
		] );

		$revenue = 0;
		foreach ( $orders as $order ) {
			$revenue += $order->get_total();
		}

		return [
			'orders'   => count( $orders ),
			'revenue'  => $revenue,
			'currency' => get_woocommerce_currency_symbol(),
		];
	}
}
