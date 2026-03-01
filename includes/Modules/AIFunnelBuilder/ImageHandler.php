<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Handler
 * 
 * Handles image uploads and builds text-based image placement instructions
 * for AI prompt injection.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class ImageHandler {

	/**
	 * Upload an image to WordPress Media Library
	 *
	 * @param array $file $_FILES array element
	 * @return array|WP_Error Result with attachment_id and url, or error
	 */
	public function upload_image( $file ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new \WP_Error( 
				'invalid_file_type', 
				__( 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.', 'woo-smart-automation' ) 
			);
		}

		// Handle the upload
		$upload_overrides = [ 'test_form' => false ];
		$uploaded = wp_handle_upload( $file, $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new \WP_Error( 'upload_failed', $uploaded['error'] );
		}

		// Create attachment
		$attachment = [
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return [
			'attachment_id' => $attachment_id,
			'url'           => $uploaded['url'],
			'filename'      => basename( $uploaded['file'] ),
		];
	}

	/**
	 * Build image context block for AI prompt injection
	 *
	 * @param array $images Array of image data with url and placement_text
	 * @return string Image context block
	 */
	public function build_image_context_block( $images ) {
		if ( empty( $images ) ) {
			return '';
		}

		$context = "--- IMAGE PLACEMENT INSTRUCTIONS ---\n";
		$context .= "You have been provided the following images. Use them exactly as described.\n";
		$context .= "Return the actual image URLs in the appropriate HTML img tags or CSS background-image properties.\n\n";

		$image_num = 1;
		foreach ( $images as $image ) {
			if ( empty( $image['url'] ) ) {
				continue;
			}

			$url = esc_url( $image['url'] );
			$placement = sanitize_textarea_field( $image['placement_text'] ?? '' );
			$hint = $this->parse_placement_hint( $placement );

			$context .= "Image {$image_num} URL: {$url}\n";
			$context .= "Placement: {$placement}\n";
			$context .= "Usage hint: {$hint}\n\n";

			$image_num++;
		}

		$context .= "IMPORTANT: Only use these exact image URLs. Do not create, modify, or hallucinate any other image URLs.\n";
		$context .= "--- END IMAGE PLACEMENT INSTRUCTIONS ---";

		return $context;
	}

	/**
	 * Parse placement text to generate usage hint
	 *
	 * @param string $placement_text User's placement description
	 * @return string CSS/HTML usage hint
	 */
	public function parse_placement_hint( $placement_text ) {
		$text_lower = strtolower( $placement_text );

		// Background image hints
		if ( $this->text_contains( $text_lower, [ 'background', 'backdrop', 'behind' ] ) ) {
			return "Use as CSS background-image on the relevant container element. Example: background-image: url('...');";
		}

		// Hero/banner hints
		if ( $this->text_contains( $text_lower, [ 'hero', 'banner', 'header image', 'top section' ] ) ) {
			return "Use as CSS background-image on full-width hero/banner section, or as a large <img> tag.";
		}

		// Avatar/circular hints
		if ( $this->text_contains( $text_lower, [ 'avatar', 'circular', 'profile', 'round' ] ) ) {
			return "Use as <img> with border-radius: 50%; width: 60-80px; height: auto;";
		}

		// Icon hints
		if ( $this->text_contains( $text_lower, [ 'icon', 'small image', 'inline' ] ) ) {
			return "Use as small <img> tag (width: 24-48px) inline with text, vertical-align: middle;";
		}

		// Product image hints
		if ( $this->text_contains( $text_lower, [ 'product', 'showcase', 'main image', 'display' ] ) ) {
			return "Use as <img> tag with max-width: 100%; height: auto; centered in its container.";
		}

		// Testimonial hints
		if ( $this->text_contains( $text_lower, [ 'testimonial', 'customer', 'review' ] ) ) {
			return "Use as <img> with border-radius: 50%; width: 50-70px; alongside testimonial text.";
		}

		// Left/right positioning hints
		if ( $this->text_contains( $text_lower, [ 'left side', 'left of' ] ) ) {
			return "Use as <img> floated left or in a flexbox/grid layout on the left.";
		}

		if ( $this->text_contains( $text_lower, [ 'right side', 'right of' ] ) ) {
			return "Use as <img> floated right or in a flexbox/grid layout on the right.";
		}

		// Centered hints
		if ( $this->text_contains( $text_lower, [ 'centered', 'center', 'middle' ] ) ) {
			return "Use as <img> with display: block; margin: 0 auto; for centering.";
		}

		// Default hint
		return "Use as <img> tag placed as described. Ensure responsive sizing with max-width: 100%.";
	}

	/**
	 * Check if text contains any of the given keywords
	 *
	 * @param string $text Text to search
	 * @param array $keywords Keywords to look for
	 * @return bool True if any keyword found
	 */
	private function text_contains( $text, $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate image URLs in AI output against allowed list
	 *
	 * @param string $html HTML content to validate
	 * @param array $allowed_urls List of allowed image URLs
	 * @return array Validation result with errors
	 */
	public function validate_output_image_urls( $html, $allowed_urls ) {
		$errors = [];
		$found_urls = [];

		// Find all img src URLs
		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			$found_urls = array_merge( $found_urls, $matches[1] );
		}

		// Find CSS background-image URLs
		if ( preg_match_all( '/background(?:-image)?\s*:\s*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $html, $matches ) ) {
			$found_urls = array_merge( $found_urls, $matches[1] );
		}

		$found_urls = array_unique( $found_urls );

		// Get site URL for relative path checking
		$site_url = site_url();
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		foreach ( $found_urls as $url ) {
			// Skip data URLs (placeholders)
			if ( strpos( $url, 'data:' ) === 0 ) {
				continue;
			}

			// Skip relative paths (likely theme assets)
			if ( strpos( $url, '/' ) === 0 || strpos( $url, './' ) === 0 ) {
				continue;
			}

			// Check if URL is in allowed list or from our site
			$is_allowed = in_array( $url, $allowed_urls, true ) ||
			              strpos( $url, $site_url ) === 0 ||
			              strpos( $url, $upload_url ) === 0;

			if ( ! $is_allowed ) {
				$errors[] = sprintf(
					__( 'Unauthorized image URL detected: %s', 'woo-smart-automation' ),
					esc_url( $url )
				);
			}
		}

		return [
			'valid'      => empty( $errors ),
			'errors'     => $errors,
			'found_urls' => $found_urls,
		];
	}

	/**
	 * Get image data from attachment ID
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @return array|null Image data or null
	 */
	public function get_image_data( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'filename'      => basename( get_attached_file( $attachment_id ) ),
			'width'         => $metadata['width'] ?? 0,
			'height'        => $metadata['height'] ?? 0,
			'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		];
	}

	/**
	 * Get multiple sizes for an image
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @return array Image URLs by size
	 */
	public function get_image_sizes( $attachment_id ) {
		$sizes = [];
		$available_sizes = get_intermediate_image_sizes();

		foreach ( $available_sizes as $size ) {
			$image = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image ) {
				$sizes[ $size ] = [
					'url'    => $image[0],
					'width'  => $image[1],
					'height' => $image[2],
				];
			}
		}

		// Add full size
		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( $full ) {
			$sizes['full'] = [
				'url'    => $full[0],
				'width'  => $full[1],
				'height' => $full[2],
			];
		}

		return $sizes;
	}

	/**
	 * Delete an uploaded image
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @return bool Success status
	 */
	public function delete_image( $attachment_id ) {
		return wp_delete_attachment( $attachment_id, true ) !== false;
	}

	/**
	 * Optimize image URL for landing page (use appropriate size)
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @param string $placement_text Placement description
	 * @return string Optimized image URL
	 */
	public function get_optimized_url( $attachment_id, $placement_text = '' ) {
		$text_lower = strtolower( $placement_text );

		// Determine best size based on placement
		if ( $this->text_contains( $text_lower, [ 'icon', 'small', 'avatar', 'thumbnail' ] ) ) {
			$size = 'thumbnail';
		} elseif ( $this->text_contains( $text_lower, [ 'background', 'hero', 'banner', 'full' ] ) ) {
			$size = 'full';
		} else {
			$size = 'large';
		}

		$image = wp_get_attachment_image_src( $attachment_id, $size );

		if ( $image ) {
			return $image[0];
		}

		// Fallback to full URL
		return wp_get_attachment_url( $attachment_id );
	}
}
