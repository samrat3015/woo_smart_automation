<?php
/**
 * Frontend Template: Landing Page
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get landing page data
$page_id = get_the_ID();
$landing_page_builder = new \WooSmartAutomation\Modules\AIFunnelBuilder\LandingPageBuilder();
$html = $landing_page_builder->get_rendered_html( $page_id );

// Get product data for structured data
$product_id = get_post_meta( $page_id, '_wsa_funnel_product_id', true );
$product = $product_id ? wc_get_product( $product_id ) : null;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php the_title(); ?> - <?php bloginfo( 'name' ); ?></title>
	
	<?php if ( $product ) : ?>
	<!-- Product Schema -->
	<script type="application/ld+json">
	{
		"@context": "https://schema.org/",
		"@type": "Product",
		"name": "<?php echo esc_js( $product->get_name() ); ?>",
		"description": "<?php echo esc_js( wp_strip_all_tags( $product->get_short_description() ) ); ?>",
		<?php if ( $product->get_image_id() ) : ?>
		"image": "<?php echo esc_url( wp_get_attachment_url( $product->get_image_id() ) ); ?>",
		<?php endif; ?>
		"offers": {
			"@type": "Offer",
			"url": "<?php echo esc_url( get_permalink() ); ?>",
			"priceCurrency": "<?php echo esc_js( get_woocommerce_currency() ); ?>",
			"price": "<?php echo esc_js( $product->get_price() ); ?>",
			"availability": "<?php echo $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>"
		}
	}
	</script>
	<?php endif; ?>
	
	<!-- Facebook Pixel (if Meta CAPI is configured) -->
	<?php
	$pixel_id = get_option( 'wsa_meta_pixel_id' );
	if ( $pixel_id ) :
	?>
	<script>
	!function(f,b,e,v,n,t,s)
	{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};
	if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
	n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];
	s.parentNode.insertBefore(t,s)}(window, document,'script',
	'https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', '<?php echo esc_js( $pixel_id ); ?>');
	fbq('track', 'PageView');
	<?php if ( $product ) : ?>
	fbq('track', 'ViewContent', {
		content_name: '<?php echo esc_js( $product->get_name() ); ?>',
		content_ids: ['<?php echo esc_js( $product->get_id() ); ?>'],
		content_type: 'product',
		value: <?php echo esc_js( $product->get_price() ); ?>,
		currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
	});
	<?php endif; ?>
	</script>
	<?php endif; ?>

	<!-- Landing Page Checkout Styles (CartFlows-Style) -->
	<style>
	/* ===== Order Summary ===== */
	.wsa-order-summary {
		background: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 12px;
		padding: 24px;
		margin-bottom: 24px;
	}

	.wsa-order-summary-title {
		font-size: 18px;
		font-weight: 700;
		margin: 0 0 16px;
		padding-bottom: 12px;
		border-bottom: 2px solid #e9ecef;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.wsa-order-product {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 12px 0;
	}

	.wsa-order-product-image img {
		width: 64px;
		height: 64px;
		object-fit: cover;
		border-radius: 8px;
		border: 1px solid #dee2e6;
	}

	.wsa-order-product-image .wsa-no-image {
		width: 64px;
		height: 64px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #e9ecef;
		border-radius: 8px;
		font-size: 28px;
	}

	.wsa-order-product-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.wsa-order-product-name {
		font-weight: 600;
		font-size: 15px;
		color: #212529;
	}

	.wsa-order-product-qty {
		font-size: 13px;
		color: #6c757d;
	}

	.wsa-order-product-price {
		font-weight: 700;
		font-size: 16px;
		color: #212529;
		white-space: nowrap;
	}

	.wsa-order-product-price del {
		color: #adb5bd;
		font-weight: 400;
		font-size: 13px;
	}

	.wsa-order-total {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-top: 16px;
		margin-top: 12px;
		border-top: 2px solid #dee2e6;
	}

	.wsa-order-total-label {
		font-size: 17px;
		font-weight: 700;
		color: #212529;
	}

	.wsa-order-total-price {
		font-size: 22px;
		font-weight: 800;
		color: #e74c3c;
	}

	.wsa-order-savings {
		background: #d4edda;
		color: #155724;
		padding: 8px 16px;
		border-radius: 8px;
		text-align: center;
		margin-top: 12px;
		font-weight: 600;
		font-size: 14px;
	}

	/* ===== Checkout Form ===== */
	.wsa-checkout-container {
		max-width: 100%;
		margin: 0 auto;
	}

	.wsa-checkout-form {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.wsa-checkout-form-title {
		font-size: 18px;
		font-weight: 700;
		margin: 0 0 8px;
		display: flex;
		align-items: center;
		gap: 8px;
		color: #212529;
	}

	.wsa-checkout-fields {
		display: flex;
		flex-wrap: wrap;
		gap: 14px;
	}

	.wsa-field {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.wsa-field-full {
		flex: 1 1 100%;
	}

	.wsa-field-half {
		flex: 1 1 calc(50% - 7px);
		min-width: 140px;
	}

	.wsa-field label {
		font-weight: 600;
		font-size: 14px;
		color: #495057;
	}

	.wsa-field .required {
		color: #e74c3c;
	}

	.wsa-field input,
	.wsa-field select,
	.wsa-field textarea {
		padding: 12px 16px;
		border: 1.5px solid #dee2e6;
		border-radius: 8px;
		font-size: 15px;
		background: #fff;
		transition: border-color 0.2s, box-shadow 0.2s;
		font-family: inherit;
	}

	.wsa-field input:focus,
	.wsa-field select:focus,
	.wsa-field textarea:focus {
		border-color: #e74c3c;
		outline: none;
		box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.12);
	}

	.wsa-field input::placeholder,
	.wsa-field textarea::placeholder {
		color: #adb5bd;
	}

	/* ===== Payment Method ===== */
	.wsa-payment-method {
		margin-top: 8px;
	}

	.wsa-payment-title {
		font-size: 16px;
		font-weight: 700;
		margin: 0 0 10px;
		display: flex;
		align-items: center;
		gap: 8px;
		color: #212529;
	}

	.wsa-payment-option {
		display: flex;
		align-items: center;
		padding: 14px 16px;
		border: 1.5px solid #dee2e6;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.2s;
	}

	.wsa-payment-option.wsa-payment-selected,
	.wsa-payment-option:hover {
		border-color: #e74c3c;
		background: #fff5f5;
	}

	.wsa-payment-option input[type="radio"] {
		margin-right: 12px;
		accent-color: #e74c3c;
	}

	.wsa-payment-option label {
		display: flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		font-weight: 600;
		font-size: 15px;
	}

	.wsa-payment-icon {
		font-size: 20px;
	}

	/* ===== Submit ===== */
	.wsa-checkout-submit {
		margin-top: 12px;
		text-align: center;
	}

	.wsa-submit-btn {
		width: 100%;
		padding: 16px 32px;
		font-size: 18px;
		font-weight: 700;
		border: none;
		border-radius: 10px;
		cursor: pointer;
		transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
		letter-spacing: 0.5px;
		position: relative;
		overflow: hidden;
	}

	.wsa-submit-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 20px rgba(231, 76, 60, 0.35);
	}

	.wsa-submit-btn:active {
		transform: translateY(0);
	}

	.wsa-btn-large {
		font-size: 20px;
		padding: 18px 40px;
	}

	.wsa-secure-notice {
		font-size: 13px;
		color: #6c757d;
		margin-top: 10px;
	}

	/* ===== Messages ===== */
	.wsa-checkout-message {
		padding: 16px;
		border-radius: 8px;
		margin-top: 16px;
		text-align: center;
		font-weight: 600;
	}

	.wsa-checkout-message.success {
		background: #d4edda;
		color: #155724;
		border: 1px solid #c3e6cb;
	}

	.wsa-checkout-message.error {
		background: #f8d7da;
		color: #721c24;
		border: 1px solid #f5c6cb;
	}

	/* ===== Loading state ===== */
	.wsa-checkout-form.loading .wsa-submit-btn {
		pointer-events: none;
		opacity: 0.7;
	}

	.wsa-checkout-form.loading .wsa-submit-btn::after {
		content: '';
		position: absolute;
		top: 0;
		left: -100%;
		width: 200%;
		height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
		animation: wsa-shimmer 1.5s infinite;
	}

	@keyframes wsa-shimmer {
		0% { transform: translateX(-100%); }
		100% { transform: translateX(100%); }
	}

	/* ===== Responsive ===== */
	@media (max-width: 600px) {
		.wsa-field-half {
			flex: 1 1 100%;
		}

		.wsa-submit-btn {
			font-size: 16px;
			padding: 15px 25px;
		}

		.wsa-order-product {
			flex-wrap: wrap;
		}

		.wsa-order-product-price {
			flex: 1 1 100%;
			text-align: right;
		}
	}

	/* ===== Icon helper ===== */
	.wsa-icon {
		font-style: normal;
		line-height: 1;
	}
	</style>

	<?php wp_head(); ?>
</head>
<body <?php body_class( 'wsa-landing-page' ); ?>>

<?php echo $html; // Already sanitized in get_rendered_html ?>

<!-- Checkout Form JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('wsa-funnel-checkout-form');
	
	if (!form) return;

	form.addEventListener('submit', function(e) {
		e.preventDefault();
		
		var submitBtn = form.querySelector('.wsa-submit-btn');
		var messageDiv = form.querySelector('.wsa-checkout-message');
		var originalText = submitBtn.textContent;
		
		// Show loading
		form.classList.add('loading');
		submitBtn.textContent = '<?php _e( 'Processing...', 'woo-smart-automation' ); ?>';
		
		// Collect form data
		var formData = new FormData(form);
		formData.append('action', 'wsa_funnel_checkout_submit');
		
		// Submit via AJAX
		fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
			method: 'POST',
			body: formData
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			form.classList.remove('loading');
			submitBtn.textContent = originalText;
			
			if (data.success) {
				// Show success message
				messageDiv.className = 'wsa-checkout-message success';
				messageDiv.textContent = data.data.message || '<?php _e( 'Order placed successfully!', 'woo-smart-automation' ); ?>';
				messageDiv.style.display = 'block';
				
				// Redirect after delay
				if (data.data.redirect) {
					setTimeout(function() {
						window.location.href = data.data.redirect;
					}, 1500);
				}
			} else {
				// Show error
				messageDiv.className = 'wsa-checkout-message error';
				messageDiv.textContent = data.data.message || '<?php _e( 'An error occurred. Please try again.', 'woo-smart-automation' ); ?>';
				messageDiv.style.display = 'block';
				
				// Highlight error field if specified
				if (data.data.field) {
					var field = document.getElementById(data.data.field);
					if (field) {
						field.focus();
						field.style.borderColor = '#dc3545';
					}
				}
			}
		})
		.catch(function(error) {
			form.classList.remove('loading');
			submitBtn.textContent = originalText;
			
			messageDiv.className = 'wsa-checkout-message error';
			messageDiv.textContent = '<?php _e( 'Network error. Please check your connection and try again.', 'woo-smart-automation' ); ?>';
			messageDiv.style.display = 'block';
		});
	});

	// Clear error styling on input
	var inputs = form.querySelectorAll('input, select, textarea');
	inputs.forEach(function(input) {
		input.addEventListener('input', function() {
			this.style.borderColor = '';
		});
	});
});
</script>

<?php wp_footer(); ?>
</body>
</html>
