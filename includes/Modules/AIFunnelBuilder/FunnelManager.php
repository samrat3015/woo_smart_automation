<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Funnel Manager
 * 
 * Handles CRUD operations for funnels including
 * checkout configuration and landing page associations.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class FunnelManager {

	/**
	 * Create a new funnel
	 *
	 * @param array $data Funnel data
	 * @return int|WP_Error Funnel ID or error
	 */
	public function create_funnel( $data ) {
		$funnel_data = [
			'post_type'   => 'wsa_funnel',
			'post_title'  => sanitize_text_field( $data['title'] ?? __( 'New Funnel', 'woo-smart-automation' ) ),
			'post_status' => 'publish',
		];

		$funnel_id = wp_insert_post( $funnel_data );

		if ( is_wp_error( $funnel_id ) ) {
			return $funnel_id;
		}

		// Save funnel meta
		$this->save_funnel_meta( $funnel_id, $data );

		return $funnel_id;
	}

	/**
	 * Update an existing funnel
	 *
	 * @param int $funnel_id Funnel post ID
	 * @param array $data Updated data
	 * @return bool|WP_Error True on success, error otherwise
	 */
	public function update_funnel( $funnel_id, $data ) {
		$funnel = get_post( $funnel_id );

		if ( ! $funnel || $funnel->post_type !== 'wsa_funnel' ) {
			return new \WP_Error( 'invalid_funnel', __( 'Invalid funnel ID.', 'woo-smart-automation' ) );
		}

		// Update title if provided
		if ( isset( $data['title'] ) ) {
			wp_update_post( [
				'ID'         => $funnel_id,
				'post_title' => sanitize_text_field( $data['title'] ),
			] );
		}

		// Save meta
		$this->save_funnel_meta( $funnel_id, $data );

		return true;
	}

	/**
	 * Save funnel meta data
	 *
	 * @param int $funnel_id Funnel post ID
	 * @param array $data Data to save
	 */
	private function save_funnel_meta( $funnel_id, $data ) {
		// Product IDs
		if ( isset( $data['products'] ) ) {
			$products = array_map( 'absint', (array) $data['products'] );
			update_post_meta( $funnel_id, '_wsa_funnel_products', $products );
		}

		// Primary product
		if ( isset( $data['primary_product'] ) ) {
			update_post_meta( $funnel_id, '_wsa_funnel_primary_product', absint( $data['primary_product'] ) );
		}

		// Checkout fields configuration
		if ( isset( $data['checkout_fields'] ) ) {
			$fields_json = is_string( $data['checkout_fields'] ) 
				? $data['checkout_fields'] 
				: wp_json_encode( $data['checkout_fields'] );
			update_post_meta( $funnel_id, '_wsa_checkout_fields_json', $fields_json );
		}

		// Thank you page
		if ( isset( $data['thank_you_page_id'] ) ) {
			update_post_meta( $funnel_id, '_wsa_thank_you_page_id', absint( $data['thank_you_page_id'] ) );
		}

		// Active status
		if ( isset( $data['active'] ) ) {
			update_post_meta( $funnel_id, '_wsa_funnel_active', $data['active'] ? 'yes' : 'no' );
		}

		// Checkout button settings
		if ( isset( $data['button_text'] ) ) {
			update_post_meta( $funnel_id, '_wsa_button_text', sanitize_text_field( $data['button_text'] ) );
		}

		if ( isset( $data['button_color'] ) ) {
			update_post_meta( $funnel_id, '_wsa_button_color', sanitize_hex_color( $data['button_color'] ) );
		}

		if ( isset( $data['button_text_color'] ) ) {
			update_post_meta( $funnel_id, '_wsa_button_text_color', sanitize_hex_color( $data['button_text_color'] ) );
		}
	}

	/**
	 * Get a funnel by ID
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return array|null Funnel data or null
	 */
	public function get_funnel( $funnel_id ) {
		$funnel = get_post( $funnel_id );

		if ( ! $funnel || $funnel->post_type !== 'wsa_funnel' ) {
			return null;
		}

		return [
			'id'               => $funnel->ID,
			'title'            => $funnel->post_title,
			'status'           => $funnel->post_status,
			'products'         => get_post_meta( $funnel_id, '_wsa_funnel_products', true ) ?: [],
			'primary_product'  => get_post_meta( $funnel_id, '_wsa_funnel_primary_product', true ),
			'checkout_fields'  => $this->get_checkout_fields( $funnel_id ),
			'thank_you_page_id'=> get_post_meta( $funnel_id, '_wsa_thank_you_page_id', true ),
			'active'           => get_post_meta( $funnel_id, '_wsa_funnel_active', true ) === 'yes',
			'button_text'      => get_post_meta( $funnel_id, '_wsa_button_text', true ) ?: __( 'Order Now', 'woo-smart-automation' ),
			'button_color'     => get_post_meta( $funnel_id, '_wsa_button_color', true ) ?: '#e74c3c',
			'button_text_color'=> get_post_meta( $funnel_id, '_wsa_button_text_color', true ) ?: '#ffffff',
			'landing_pages'    => $this->get_funnel_landing_pages( $funnel_id ),
			'created_at'       => $funnel->post_date,
			'modified_at'      => $funnel->post_modified,
		];
	}

	/**
	 * Get checkout fields for a funnel
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return array Checkout fields configuration
	 */
	public function get_checkout_fields( $funnel_id ) {
		$fields_json = get_post_meta( $funnel_id, '_wsa_checkout_fields_json', true );

		if ( empty( $fields_json ) ) {
			return $this->get_default_checkout_fields();
		}

		$fields = json_decode( $fields_json, true );

		return is_array( $fields ) ? $fields : $this->get_default_checkout_fields();
	}

	/**
	 * Get default checkout fields configuration
	 *
	 * @return array Default fields
	 */
	public function get_default_checkout_fields() {
		return [
			'fields' => [
				[
					'id'          => 'billing_first_name',
					'label'       => __( 'Full Name', 'woo-smart-automation' ),
					'placeholder' => __( 'Enter your full name', 'woo-smart-automation' ),
					'required'    => true,
					'visible'     => true,
					'order'       => 1,
					'width'       => 'full',
				],
				[
					'id'          => 'billing_phone',
					'label'       => __( 'Phone Number', 'woo-smart-automation' ),
					'placeholder' => '01XXXXXXXXX',
					'required'    => true,
					'visible'     => true,
					'order'       => 2,
					'width'       => 'full',
				],
				[
					'id'          => 'billing_address_1',
					'label'       => __( 'Address', 'woo-smart-automation' ),
					'placeholder' => __( 'Enter your full address', 'woo-smart-automation' ),
					'required'    => true,
					'visible'     => true,
					'order'       => 3,
					'width'       => 'full',
				],
				[
					'id'          => 'billing_city',
					'label'       => __( 'City', 'woo-smart-automation' ),
					'placeholder' => __( 'Enter your city', 'woo-smart-automation' ),
					'required'    => true,
					'visible'     => true,
					'order'       => 4,
					'width'       => 'half',
				],
				[
					'id'          => 'billing_state',
					'label'       => __( 'District', 'woo-smart-automation' ),
					'placeholder' => __( 'Select district', 'woo-smart-automation' ),
					'required'    => true,
					'visible'     => true,
					'order'       => 5,
					'width'       => 'half',
					'type'        => 'select',
					'options'     => $this->get_bangladesh_districts(),
				],
			],
			'submit_button' => [
				'text'       => __( 'Order Now', 'woo-smart-automation' ),
				'color'      => '#e74c3c',
				'text_color' => '#ffffff',
				'size'       => 'large',
			],
			'layout' => 'single_column',
		];
	}

	/**
	 * Get Bangladesh districts for dropdown
	 *
	 * @return array Districts
	 */
	private function get_bangladesh_districts() {
		return [
			'Dhaka'        => 'Dhaka',
			'Chittagong'   => 'Chittagong',
			'Rajshahi'     => 'Rajshahi',
			'Khulna'       => 'Khulna',
			'Sylhet'       => 'Sylhet',
			'Barisal'      => 'Barisal',
			'Rangpur'      => 'Rangpur',
			'Mymensingh'   => 'Mymensingh',
			'Comilla'      => 'Comilla',
			'Gazipur'      => 'Gazipur',
			'Narayanganj'  => 'Narayanganj',
			'Bogra'        => 'Bogra',
			'Cox\'s Bazar' => 'Cox\'s Bazar',
			'Jessore'      => 'Jessore',
			'Dinajpur'     => 'Dinajpur',
			'Brahmanbaria' => 'Brahmanbaria',
			'Tangail'      => 'Tangail',
			'Narsingdi'    => 'Narsingdi',
			'Savar'        => 'Savar',
			'Other'        => __( 'Other', 'woo-smart-automation' ),
		];
	}

	/**
	 * Get all landing pages for a funnel
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return array Landing page IDs and titles
	 */
	public function get_funnel_landing_pages( $funnel_id ) {
		$landing_pages = get_posts( [
			'post_type'      => 'wsa_landing_page',
			'posts_per_page' => -1,
			'post_status'    => [ 'publish', 'draft' ],
			'meta_query'     => [
				[
					'key'   => '_wsa_funnel_id',
					'value' => $funnel_id,
				],
			],
		] );

		$results = [];
		foreach ( $landing_pages as $page ) {
			$results[] = [
				'id'     => $page->ID,
				'title'  => $page->post_title,
				'status' => $page->post_status,
				'url'    => get_permalink( $page->ID ),
			];
		}

		return $results;
	}

	/**
	 * Get all funnels
	 *
	 * @param array $args Query arguments
	 * @return array Funnels
	 */
	public function get_funnels( $args = [] ) {
		$defaults = [
			'posts_per_page' => 20,
			'paged'          => 1,
			'post_status'    => [ 'publish', 'draft' ],
		];

		$args = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'wsa_funnel';

		$query = new \WP_Query( $args );
		$funnels = [];

		foreach ( $query->posts as $post ) {
			$funnels[] = $this->get_funnel( $post->ID );
		}

		return [
			'funnels'     => $funnels,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		];
	}

	/**
	 * Delete a funnel
	 *
	 * @param int $funnel_id Funnel post ID
	 * @param bool $force Force delete (bypass trash)
	 * @return bool Success status
	 */
	public function delete_funnel( $funnel_id, $force = false ) {
		$funnel = get_post( $funnel_id );

		if ( ! $funnel || $funnel->post_type !== 'wsa_funnel' ) {
			return false;
		}

		$result = wp_delete_post( $funnel_id, $force );

		return $result !== false;
	}

	/**
	 * Duplicate a funnel
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return int|WP_Error New funnel ID or error
	 */
	public function duplicate_funnel( $funnel_id ) {
		$original = $this->get_funnel( $funnel_id );

		if ( ! $original ) {
			return new \WP_Error( 'invalid_funnel', __( 'Original funnel not found.', 'woo-smart-automation' ) );
		}

		$original['title'] = sprintf( __( '%s (Copy)', 'woo-smart-automation' ), $original['title'] );
		unset( $original['id'] );
		unset( $original['landing_pages'] );

		return $this->create_funnel( $original );
	}

	/**
	 * Get available checkout field types
	 *
	 * @return array Field types
	 */
	public function get_available_field_types() {
		return [
			'billing_first_name' => [
				'label'    => __( 'First Name', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => true,
			],
			'billing_last_name' => [
				'label'    => __( 'Last Name', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => false,
			],
			'billing_phone' => [
				'label'    => __( 'Phone Number', 'woo-smart-automation' ),
				'type'     => 'tel',
				'required' => true,
			],
			'billing_email' => [
				'label'    => __( 'Email', 'woo-smart-automation' ),
				'type'     => 'email',
				'required' => false,
			],
			'billing_address_1' => [
				'label'    => __( 'Address Line 1', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => true,
			],
			'billing_address_2' => [
				'label'    => __( 'Address Line 2', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => false,
			],
			'billing_city' => [
				'label'    => __( 'City', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => true,
			],
			'billing_state' => [
				'label'    => __( 'District/State', 'woo-smart-automation' ),
				'type'     => 'select',
				'required' => true,
			],
			'billing_postcode' => [
				'label'    => __( 'Postcode', 'woo-smart-automation' ),
				'type'     => 'text',
				'required' => false,
			],
			'order_comments' => [
				'label'    => __( 'Order Notes', 'woo-smart-automation' ),
				'type'     => 'textarea',
				'required' => false,
			],
			'_wsa_quantity' => [
				'label'    => __( 'Quantity', 'woo-smart-automation' ),
				'type'     => 'number',
				'required' => false,
			],
		];
	}

	/**
	 * Get funnel statistics
	 *
	 * @param int $funnel_id Funnel post ID
	 * @return array Statistics
	 */
	public function get_funnel_stats( $funnel_id ) {
		// Get landing pages for this funnel
		$landing_pages = $this->get_funnel_landing_pages( $funnel_id );
		$landing_page_ids = wp_list_pluck( $landing_pages, 'id' );

		// Count orders from this funnel
		$order_count = 0;
		$revenue = 0;

		if ( ! empty( $landing_page_ids ) ) {
			$orders = wc_get_orders( [
				'limit'      => -1,
				'status'     => [ 'processing', 'completed' ],
				'meta_key'   => '_wsa_funnel_id',
				'meta_value' => $funnel_id,
			] );

			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				$revenue += $order->get_total();
			}
		}

		return [
			'landing_pages' => count( $landing_pages ),
			'orders'        => $order_count,
			'revenue'       => $revenue,
			'currency'      => get_woocommerce_currency_symbol(),
		];
	}
}
