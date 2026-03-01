<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout Builder
 * 
 * Handles the rendering and processing of custom checkout forms
 * on landing pages.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class CheckoutBuilder {

	/**
	 * Funnel ID
	 *
	 * @var int
	 */
	private $funnel_id;

	/**
	 * Funnel manager instance
	 *
	 * @var FunnelManager
	 */
	private $funnel_manager;

	/**
	 * Constructor
	 *
	 * @param int $funnel_id Funnel post ID
	 */
	public function __construct( $funnel_id = 0 ) {
		$this->funnel_id = absint( $funnel_id );
		$this->funnel_manager = new FunnelManager();
	}

	/**
	 * Initialize checkout builder hooks
	 */
	public function init() {
		// AJAX handler for form submission
		add_action( 'wp_ajax_wsa_funnel_checkout_submit', [ $this, 'handle_checkout_submit' ] );
		add_action( 'wp_ajax_nopriv_wsa_funnel_checkout_submit', [ $this, 'handle_checkout_submit' ] );

		// Hook into WooCommerce order creation if needed
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_funnel_meta_to_order' ], 10, 3 );
	}

	/**
	 * Handle AJAX checkout form submission
	 */
	public function handle_checkout_submit() {
		// Verify nonce
		if ( ! check_ajax_referer( 'wsa_funnel_checkout', 'wsa_checkout_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'woo-smart-automation' ) ] );
		}

		// Get posted data
		$funnel_id       = absint( $_POST['wsa_funnel_id'] ?? 0 );
		$landing_page_id = absint( $_POST['wsa_landing_page_id'] ?? 0 );

		if ( ! $landing_page_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid landing page.', 'woo-smart-automation' ) ] );
		}

		// Get funnel data (or use defaults)
		$funnel = null;
		if ( $funnel_id ) {
			$funnel = $this->funnel_manager->get_funnel( $funnel_id );
		}

		// Build checkout fields (from funnel or defaults)
		$checkout_fields = [];
		if ( $funnel ) {
			$checkout_fields = $funnel['checkout_fields']['fields'] ?? [];
		}
		if ( empty( $checkout_fields ) ) {
			$defaults = $this->funnel_manager->get_default_checkout_fields();
			$checkout_fields = $defaults['fields'] ?? [];
		}

		// Validate required fields
		$validation = $this->validate_checkout_fields( $_POST, $checkout_fields );

		if ( ! $validation['valid'] ) {
			wp_send_json_error( [ 
				'message' => $validation['error'],
				'field'   => $validation['field'] ?? '',
			] );
		}

		// Ensure product is in cart
		$product_id = get_post_meta( $landing_page_id, '_wsa_funnel_product_id', true );
		$quantity = get_post_meta( $landing_page_id, '_wsa_funnel_quantity', true ) ?: 1;

		// Custom quantity from form
		if ( isset( $_POST['_wsa_quantity'] ) && absint( $_POST['_wsa_quantity'] ) > 0 ) {
			$quantity = absint( $_POST['_wsa_quantity'] );
		}

		if ( $product_id ) {
			// Clear cart and add only the funnel product
			WC()->cart->empty_cart();

			$variation_id = get_post_meta( $landing_page_id, '_wsa_funnel_variation_id', true );

			if ( $variation_id ) {
				WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
			} else {
				WC()->cart->add_to_cart( $product_id, $quantity );
			}
		}

		// Build funnel array for create_order (use defaults if no funnel)
		if ( ! $funnel ) {
			$funnel = [
				'id'               => $funnel_id,
				'checkout_fields'  => [ 'fields' => $checkout_fields ],
				'thank_you_page_id'=> 0,
			];
		}

		// Create the order
		$order_result = $this->create_order( $_POST, $funnel, $landing_page_id );

		if ( is_wp_error( $order_result ) ) {
			wp_send_json_error( [ 'message' => $order_result->get_error_message() ] );
		}

		// Clear cart after order creation
		WC()->cart->empty_cart();

		// Get thank you page URL
		$thank_you_url = $this->get_thank_you_url( $funnel, $order_result );

		wp_send_json_success( [
			'message'   => __( 'Order placed successfully!', 'woo-smart-automation' ),
			'order_id'  => $order_result,
			'redirect'  => $thank_you_url,
		] );
	}

	/**
	 * Validate checkout form fields
	 *
	 * @param array $posted Posted data
	 * @param array $fields Field configuration
	 * @return array Validation result
	 */
	private function validate_checkout_fields( $posted, $fields ) {
		foreach ( $fields as $field ) {
			if ( ! $field['visible'] ) {
				continue;
			}

			$field_id = $field['id'];
			$value = isset( $posted[ $field_id ] ) ? trim( $posted[ $field_id ] ) : '';

			// Check required
			if ( $field['required'] && empty( $value ) ) {
				return [
					'valid' => false,
					'error' => sprintf( __( '%s is required.', 'woo-smart-automation' ), $field['label'] ),
					'field' => $field_id,
				];
			}

			// Validate phone format (Bangladesh)
			if ( $field_id === 'billing_phone' && ! empty( $value ) ) {
				$phone = preg_replace( '/[^0-9]/', '', $value );
				if ( strlen( $phone ) < 10 || strlen( $phone ) > 15 ) {
					return [
						'valid' => false,
						'error' => __( 'Please enter a valid phone number.', 'woo-smart-automation' ),
						'field' => $field_id,
					];
				}
			}

			// Validate email format
			if ( $field_id === 'billing_email' && ! empty( $value ) ) {
				if ( ! is_email( $value ) ) {
					return [
						'valid' => false,
						'error' => __( 'Please enter a valid email address.', 'woo-smart-automation' ),
						'field' => $field_id,
					];
				}
			}
		}

		return [ 'valid' => true ];
	}

	/**
	 * Create WooCommerce order from checkout data
	 *
	 * @param array $posted Posted form data
	 * @param array $funnel Funnel data
	 * @param int $landing_page_id Landing page ID
	 * @return int|WP_Error Order ID or error
	 */
	private function create_order( $posted, $funnel, $landing_page_id ) {
		try {
			// Create new order
			$order = wc_create_order();

			if ( is_wp_error( $order ) ) {
				return $order;
			}

			// Add cart items to order
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = $cart_item['data'];
				$quantity = $cart_item['quantity'];

				$order->add_product(
					$product,
					$quantity,
					[
						'variation' => $cart_item['variation'] ?? [],
						'totals'    => [
							'subtotal'     => $cart_item['line_subtotal'],
							'subtotal_tax' => $cart_item['line_subtotal_tax'],
							'total'        => $cart_item['line_total'],
							'tax'          => $cart_item['line_tax'],
						],
					]
				);
			}

			// Set billing address
			$order->set_billing_first_name( sanitize_text_field( $posted['billing_first_name'] ?? '' ) );
			$order->set_billing_last_name( sanitize_text_field( $posted['billing_last_name'] ?? '' ) );
			$order->set_billing_phone( sanitize_text_field( $posted['billing_phone'] ?? '' ) );
			$order->set_billing_email( sanitize_email( $posted['billing_email'] ?? '' ) );
			$order->set_billing_address_1( sanitize_text_field( $posted['billing_address_1'] ?? '' ) );
			$order->set_billing_address_2( sanitize_text_field( $posted['billing_address_2'] ?? '' ) );
			$order->set_billing_city( sanitize_text_field( $posted['billing_city'] ?? '' ) );
			$order->set_billing_state( sanitize_text_field( $posted['billing_state'] ?? '' ) );
			$order->set_billing_postcode( sanitize_text_field( $posted['billing_postcode'] ?? '' ) );
			$order->set_billing_country( 'BD' ); // Default to Bangladesh

			// Copy billing to shipping
			$order->set_shipping_first_name( $order->get_billing_first_name() );
			$order->set_shipping_last_name( $order->get_billing_last_name() );
			$order->set_shipping_address_1( $order->get_billing_address_1() );
			$order->set_shipping_address_2( $order->get_billing_address_2() );
			$order->set_shipping_city( $order->get_billing_city() );
			$order->set_shipping_state( $order->get_billing_state() );
			$order->set_shipping_postcode( $order->get_billing_postcode() );
			$order->set_shipping_country( 'BD' );

			// Add order notes
			if ( ! empty( $posted['order_comments'] ) ) {
				$order->set_customer_note( sanitize_textarea_field( $posted['order_comments'] ) );
			}

			// Set payment method (COD default for funnel orders)
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( __( 'Cash on Delivery', 'woo-smart-automation' ) );

			// Calculate totals
			$order->calculate_totals();

			// Add funnel meta
			$order->update_meta_data( '_wsa_funnel_id', $funnel['id'] );
			$order->update_meta_data( '_wsa_landing_page_id', $landing_page_id );
			$order->update_meta_data( '_wsa_funnel_order', 'yes' );

			// Set order status
			$order->set_status( 'processing', __( 'Order placed via AI Funnel Builder landing page.', 'woo-smart-automation' ) );

			// Save order
			$order->save();

			// Trigger WooCommerce actions
			do_action( 'woocommerce_checkout_order_processed', $order->get_id(), $posted, $order );
			do_action( 'woocommerce_new_order', $order->get_id(), $order );

			return $order->get_id();

		} catch ( \Exception $e ) {
			return new \WP_Error( 'order_creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Get thank you page URL
	 *
	 * @param array $funnel Funnel data
	 * @param int $order_id Order ID
	 * @return string Thank you URL
	 */
	private function get_thank_you_url( $funnel, $order_id ) {
		// Check for custom thank you page
		$thank_you_page_id = $funnel['thank_you_page_id'] ?? 0;

		if ( $thank_you_page_id ) {
			return add_query_arg( 'order_id', $order_id, get_permalink( $thank_you_page_id ) );
		}

		// Fall back to WooCommerce thank you page
		$order = wc_get_order( $order_id );

		if ( $order ) {
			return $order->get_checkout_order_received_url();
		}

		return wc_get_checkout_url();
	}

	/**
	 * Add funnel meta to order during checkout
	 *
	 * @param int $order_id Order ID
	 * @param array $posted_data Posted checkout data
	 * @param WC_Order $order Order object
	 */
	public function add_funnel_meta_to_order( $order_id, $posted_data, $order ) {
		$funnel_id = absint( $posted_data['wsa_funnel_id'] ?? 0 );
		$landing_page_id = absint( $posted_data['wsa_landing_page_id'] ?? 0 );

		if ( $funnel_id ) {
			$order->update_meta_data( '_wsa_funnel_id', $funnel_id );
		}

		if ( $landing_page_id ) {
			$order->update_meta_data( '_wsa_landing_page_id', $landing_page_id );
		}

		if ( $funnel_id || $landing_page_id ) {
			$order->update_meta_data( '_wsa_funnel_order', 'yes' );
			$order->save();
		}
	}

	/**
	 * Get checkout form HTML for rendering
	 *
	 * @param int $landing_page_id Landing page ID
	 * @return string Checkout form HTML
	 */
	public function render_checkout_form( $landing_page_id = 0 ) {
		if ( ! $this->funnel_id ) {
			return '';
		}

		$funnel = $this->funnel_manager->get_funnel( $this->funnel_id );

		if ( ! $funnel ) {
			return '';
		}

		$landing_page_builder = new LandingPageBuilder();
		return $landing_page_builder->generate_checkout_form_html( $this->funnel_id, $landing_page_id );
	}

	/**
	 * Get available field types for the builder
	 *
	 * @return array Field types with metadata
	 */
	public static function get_field_types() {
		return [
			'text' => [
				'label' => __( 'Text', 'woo-smart-automation' ),
				'icon'  => 'dashicons-editor-textcolor',
			],
			'email' => [
				'label' => __( 'Email', 'woo-smart-automation' ),
				'icon'  => 'dashicons-email',
			],
			'tel' => [
				'label' => __( 'Phone', 'woo-smart-automation' ),
				'icon'  => 'dashicons-phone',
			],
			'textarea' => [
				'label' => __( 'Text Area', 'woo-smart-automation' ),
				'icon'  => 'dashicons-editor-paragraph',
			],
			'select' => [
				'label' => __( 'Dropdown', 'woo-smart-automation' ),
				'icon'  => 'dashicons-arrow-down-alt2',
			],
			'number' => [
				'label' => __( 'Number', 'woo-smart-automation' ),
				'icon'  => 'dashicons-editor-ol',
			],
		];
	}

	/**
	 * Get pre-built field configurations
	 *
	 * @return array Pre-built fields
	 */
	public static function get_prebuilt_fields() {
		return [
			'billing_first_name' => [
				'id'          => 'billing_first_name',
				'label'       => __( 'First Name', 'woo-smart-automation' ),
				'type'        => 'text',
				'placeholder' => __( 'Enter your first name', 'woo-smart-automation' ),
				'required'    => true,
				'width'       => 'half',
			],
			'billing_last_name' => [
				'id'          => 'billing_last_name',
				'label'       => __( 'Last Name', 'woo-smart-automation' ),
				'type'        => 'text',
				'placeholder' => __( 'Enter your last name', 'woo-smart-automation' ),
				'required'    => false,
				'width'       => 'half',
			],
			'billing_phone' => [
				'id'          => 'billing_phone',
				'label'       => __( 'Phone Number', 'woo-smart-automation' ),
				'type'        => 'tel',
				'placeholder' => '01XXXXXXXXX',
				'required'    => true,
				'width'       => 'full',
			],
			'billing_email' => [
				'id'          => 'billing_email',
				'label'       => __( 'Email Address', 'woo-smart-automation' ),
				'type'        => 'email',
				'placeholder' => __( 'email@example.com', 'woo-smart-automation' ),
				'required'    => false,
				'width'       => 'full',
			],
			'billing_address_1' => [
				'id'          => 'billing_address_1',
				'label'       => __( 'Address', 'woo-smart-automation' ),
				'type'        => 'text',
				'placeholder' => __( 'Enter your full address', 'woo-smart-automation' ),
				'required'    => true,
				'width'       => 'full',
			],
			'billing_city' => [
				'id'          => 'billing_city',
				'label'       => __( 'City', 'woo-smart-automation' ),
				'type'        => 'text',
				'placeholder' => __( 'Enter your city', 'woo-smart-automation' ),
				'required'    => true,
				'width'       => 'half',
			],
			'billing_state' => [
				'id'          => 'billing_state',
				'label'       => __( 'District', 'woo-smart-automation' ),
				'type'        => 'select',
				'placeholder' => __( 'Select district', 'woo-smart-automation' ),
				'required'    => true,
				'width'       => 'half',
			],
			'order_comments' => [
				'id'          => 'order_comments',
				'label'       => __( 'Order Notes', 'woo-smart-automation' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Special instructions for your order', 'woo-smart-automation' ),
				'required'    => false,
				'width'       => 'full',
			],
			'_wsa_quantity' => [
				'id'          => '_wsa_quantity',
				'label'       => __( 'Quantity', 'woo-smart-automation' ),
				'type'        => 'number',
				'placeholder' => '1',
				'required'    => false,
				'width'       => 'half',
				'min'         => 1,
				'max'         => 10,
				'default'     => 1,
			],
		];
	}
}
