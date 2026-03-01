<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preview Renderer
 * 
 * Handles live preview rendering for landing pages in the admin
 * and iframe-based preview functionality.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class PreviewRenderer {

	/**
	 * Initialize preview renderer
	 */
	public function init() {
		// AJAX handler for live preview
		add_action( 'wp_ajax_wsa_preview_landing_page', [ $this, 'ajax_preview' ] );

		// Preview mode endpoint
		add_action( 'template_redirect', [ $this, 'handle_preview_mode' ] );
	}

	/**
	 * AJAX handler for live preview
	 */
	public function ajax_preview() {
		// Verify nonce
		if ( ! check_ajax_referer( 'wsa_funnel_builder', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'woo-smart-automation' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'woo-smart-automation' ) ] );
		}

		$html = wp_kses_post( $_POST['html'] ?? '' );
		$product_id = absint( $_POST['product_id'] ?? 0 );

		// Wrap in preview template
		$preview_html = $this->wrap_preview( $html, $product_id );

		wp_send_json_success( [ 'html' => $preview_html ] );
	}

	/**
	 * Handle preview mode for landing pages
	 */
	public function handle_preview_mode() {
		if ( ! isset( $_GET['wsa_preview'] ) ) {
			return;
		}

		$page_id = absint( $_GET['wsa_preview'] );
		$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'wsa_preview_' . $page_id ) ) {
			wp_die( __( 'Invalid preview link.', 'woo-smart-automation' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to preview this page.', 'woo-smart-automation' ) );
		}

		// Get landing page
		$builder = new LandingPageBuilder();
		$page = $builder->get_landing_page( $page_id );

		if ( ! $page ) {
			wp_die( __( 'Landing page not found.', 'woo-smart-automation' ) );
		}

		// Render preview
		$this->render_preview_page( $page );
		exit;
	}

	/**
	 * Wrap HTML in preview container
	 *
	 * @param string $html HTML content
	 * @param int $product_id Product ID
	 * @return string Wrapped HTML
	 */
	private function wrap_preview( $html, $product_id = 0 ) {
		// Add preview indicator bar
		$preview_bar = <<<'HTML'
<div style="position: fixed; top: 0; left: 0; right: 0; background: #2271b1; color: #fff; padding: 10px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; z-index: 99999; display: flex; justify-content: space-between; align-items: center;">
    <span>📱 Preview Mode - This is how your landing page will appear to visitors</span>
    <div>
        <button onclick="parent.wsaClosePreview()" style="background: #fff; color: #2271b1; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer; margin-left: 10px;">Close Preview</button>
    </div>
</div>
<div style="height: 50px;"></div>
HTML;

		return $preview_bar . $html;
	}

	/**
	 * Render full preview page
	 *
	 * @param array $page Landing page data
	 */
	private function render_preview_page( $page ) {
		$builder = new LandingPageBuilder();
		$html = $builder->get_rendered_html( $page['id'] );

		// Get device mode from query
		$device = sanitize_key( $_GET['device'] ?? 'desktop' );

		// Output the preview
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<meta name="robots" content="noindex, nofollow">
			<title><?php echo esc_html( $page['title'] ); ?> - Preview</title>
			<style>
				/* Preview mode indicator */
				.wsa-preview-bar {
					position: fixed;
					top: 0;
					left: 0;
					right: 0;
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					color: #fff;
					padding: 10px 20px;
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					font-size: 14px;
					z-index: 99999;
					display: flex;
					justify-content: space-between;
					align-items: center;
					box-shadow: 0 2px 10px rgba(0,0,0,0.2);
				}

				.wsa-preview-bar__left {
					display: flex;
					align-items: center;
					gap: 10px;
				}

				.wsa-preview-bar__status {
					background: rgba(255,255,255,0.2);
					padding: 3px 10px;
					border-radius: 20px;
					font-size: 12px;
				}

				.wsa-preview-bar__buttons {
					display: flex;
					gap: 10px;
				}

				.wsa-preview-bar__btn {
					background: rgba(255,255,255,0.2);
					color: #fff;
					border: none;
					padding: 8px 16px;
					border-radius: 5px;
					cursor: pointer;
					text-decoration: none;
					font-size: 13px;
					transition: background 0.2s;
				}

				.wsa-preview-bar__btn:hover {
					background: rgba(255,255,255,0.3);
				}

				.wsa-preview-bar__btn--primary {
					background: #fff;
					color: #667eea;
				}

				.wsa-preview-bar__btn--primary:hover {
					background: #f0f0f0;
				}

				body {
					padding-top: 50px;
				}

				/* Device simulation */
				<?php if ( $device === 'mobile' ) : ?>
				body {
					max-width: 375px;
					margin: 50px auto 0;
					box-shadow: 0 0 20px rgba(0,0,0,0.1);
				}
				<?php elseif ( $device === 'tablet' ) : ?>
				body {
					max-width: 768px;
					margin: 50px auto 0;
					box-shadow: 0 0 20px rgba(0,0,0,0.1);
				}
				<?php endif; ?>
			</style>
		</head>
		<body>
			<div class="wsa-preview-bar">
				<div class="wsa-preview-bar__left">
					<span>🔍 Preview: <?php echo esc_html( $page['title'] ); ?></span>
					<span class="wsa-preview-bar__status">
						<?php echo $page['status'] === 'publish' ? '✅ Published' : '📝 Draft'; ?>
					</span>
				</div>
				<div class="wsa-preview-bar__buttons">
					<a href="<?php echo esc_url( add_query_arg( 'device', 'mobile', remove_query_arg( 'device' ) ) ); ?>" 
					   class="wsa-preview-bar__btn <?php echo $device === 'mobile' ? 'wsa-preview-bar__btn--primary' : ''; ?>">
						📱 Mobile
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'device', 'tablet', remove_query_arg( 'device' ) ) ); ?>" 
					   class="wsa-preview-bar__btn <?php echo $device === 'tablet' ? 'wsa-preview-bar__btn--primary' : ''; ?>">
						📊 Tablet
					</a>
					<a href="<?php echo esc_url( remove_query_arg( 'device' ) ); ?>" 
					   class="wsa-preview-bar__btn <?php echo $device === 'desktop' ? 'wsa-preview-bar__btn--primary' : ''; ?>">
						🖥️ Desktop
					</a>
					<?php if ( $page['status'] === 'publish' ) : ?>
					<a href="<?php echo esc_url( get_permalink( $page['id'] ) ); ?>" 
					   class="wsa-preview-bar__btn wsa-preview-bar__btn--primary" 
					   target="_blank">
						🔗 View Live
					</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=edit&id=' . $page['id'] ) ); ?>" 
					   class="wsa-preview-bar__btn">
						✏️ Edit
					</a>
				</div>
			</div>
			
			<?php echo $html; // Already sanitized ?>
			
			<!-- Checkout form scripts -->
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var form = document.getElementById('wsa-funnel-checkout-form');
					if (form) {
						form.addEventListener('submit', function(e) {
							e.preventDefault();
							alert('This is a preview. Form submission is disabled.');
						});
					}
				});
			</script>
		</body>
		</html>
		<?php
	}

	/**
	 * Generate preview URL for a landing page
	 *
	 * @param int $page_id Landing page ID
	 * @param string $device Device type (desktop, tablet, mobile)
	 * @return string Preview URL
	 */
	public function get_preview_url( $page_id, $device = 'desktop' ) {
		$url = add_query_arg( [
			'wsa_preview' => $page_id,
			'device'      => $device,
			'_wpnonce'    => wp_create_nonce( 'wsa_preview_' . $page_id ),
		], home_url() );

		return $url;
	}

	/**
	 * Render inline preview iframe
	 *
	 * @param int $page_id Landing page ID
	 * @param string $device Device type
	 * @return string Iframe HTML
	 */
	public function render_preview_iframe( $page_id, $device = 'desktop' ) {
		$url = $this->get_preview_url( $page_id, $device );

		$width = '100%';
		$height = '600px';

		switch ( $device ) {
			case 'mobile':
				$width = '375px';
				$height = '667px';
				break;
			case 'tablet':
				$width = '768px';
				$height = '1024px';
				break;
		}

		return sprintf(
			'<iframe src="%s" width="%s" height="%s" frameborder="0" class="wsa-preview-iframe wsa-preview-iframe--%s"></iframe>',
			esc_url( $url ),
			esc_attr( $width ),
			esc_attr( $height ),
			esc_attr( $device )
		);
	}

	/**
	 * Generate QR code for mobile preview
	 *
	 * @param int $page_id Landing page ID
	 * @return string QR code image URL (using external service)
	 */
	public function get_preview_qr_code( $page_id ) {
		$url = $this->get_preview_url( $page_id, 'mobile' );

		// Using Google Charts API for QR code generation
		$qr_url = add_query_arg( [
			'chs'  => '200x200',
			'cht'  => 'qr',
			'chl'  => urlencode( $url ),
			'choe' => 'UTF-8',
		], 'https://chart.googleapis.com/chart' );

		return $qr_url;
	}

	/**
	 * Get responsive preview sizes
	 *
	 * @return array Device sizes
	 */
	public static function get_device_sizes() {
		return [
			'desktop' => [
				'label'  => __( 'Desktop', 'woo-smart-automation' ),
				'width'  => '100%',
				'height' => '100%',
				'icon'   => '🖥️',
			],
			'tablet' => [
				'label'  => __( 'Tablet', 'woo-smart-automation' ),
				'width'  => '768px',
				'height' => '1024px',
				'icon'   => '📊',
			],
			'mobile' => [
				'label'  => __( 'Mobile', 'woo-smart-automation' ),
				'width'  => '375px',
				'height' => '667px',
				'icon'   => '📱',
			],
		];
	}
}
