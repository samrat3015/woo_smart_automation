<?php
/**
 * Admin Template: Create Funnel Page
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap wsa-funnel-create-page">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-plus-alt2"></span>
		<?php _e( 'Create New Funnel', 'woo-smart-automation' ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder' ) ); ?>" class="page-title-action">
		<span class="dashicons dashicons-arrow-left-alt"></span>
		<?php _e( 'Back to Funnels', 'woo-smart-automation' ); ?>
	</a>

	<hr class="wp-header-end">

	<div class="wsa-form-container">
		<form id="wsa-create-funnel-form" method="post">
			<?php wp_nonce_field( 'wsa_funnel_builder', 'nonce' ); ?>

			<div class="wsa-card">
				<h2 class="wsa-card-title"><?php _e( 'Funnel Details', 'woo-smart-automation' ); ?></h2>

				<div class="wsa-field-row">
					<label for="funnel_title"><?php _e( 'Funnel Name', 'woo-smart-automation' ); ?> <span class="required">*</span></label>
					<input type="text" id="funnel_title" name="title" class="regular-text" required
						   placeholder="<?php _e( 'e.g., Weight Loss Supplement Funnel', 'woo-smart-automation' ); ?>">
					<p class="description"><?php _e( 'A descriptive name for this sales funnel.', 'woo-smart-automation' ); ?></p>
				</div>

				<div class="wsa-field-row">
					<label for="primary_product"><?php _e( 'Primary Product', 'woo-smart-automation' ); ?> <span class="required">*</span></label>
					<select id="primary_product" name="primary_product" class="regular-text" required>
						<option value=""><?php _e( 'Select primary product...', 'woo-smart-automation' ); ?></option>
						<?php foreach ( $products as $product_id => $product_label ) : ?>
							<option value="<?php echo esc_attr( $product_id ); ?>">
								<?php echo esc_html( $product_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php _e( 'The main product to sell through this funnel.', 'woo-smart-automation' ); ?></p>
				</div>

				<div class="wsa-field-row">
					<label>
						<input type="checkbox" name="active" value="1" checked>
						<?php _e( 'Activate funnel immediately', 'woo-smart-automation' ); ?>
					</label>
				</div>
			</div>

			<div class="wsa-form-actions">
				<button type="submit" class="button button-primary button-large">
					<span class="dashicons dashicons-yes"></span>
					<?php _e( 'Create Funnel', 'woo-smart-automation' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder' ) ); ?>" class="button button-large">
					<?php _e( 'Cancel', 'woo-smart-automation' ); ?>
				</a>
			</div>
		</form>
	</div>
</div>

<style>
.wsa-funnel-create-page .page-title-action {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.wsa-form-container {
	max-width: 600px;
	margin-top: 20px;
}

.wsa-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.wsa-card-title {
	margin: 0 0 20px;
	padding-bottom: 15px;
	border-bottom: 1px solid #eee;
}

.wsa-field-row {
	margin-bottom: 20px;
}

.wsa-field-row:last-child {
	margin-bottom: 0;
}

.wsa-field-row label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
}

.wsa-field-row .required {
	color: #dc3545;
}

.wsa-field-row input[type="text"],
.wsa-field-row select {
	width: 100%;
}

.wsa-field-row input[type="checkbox"] {
	margin-right: 5px;
}

.wsa-form-actions {
	display: flex;
	gap: 10px;
}

.wsa-form-actions .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('#wsa-create-funnel-form').on('submit', function(e) {
		e.preventDefault();
		
		var $form = $(this);
		var $btn = $form.find('button[type="submit"]');
		
		$btn.prop('disabled', true);
		
		$.post(wsaFunnelBuilder.ajaxUrl, {
			action: 'wsa_create_funnel',
			nonce: wsaFunnelBuilder.nonce,
			title: $('#funnel_title').val(),
			primary_product: $('#primary_product').val(),
			products: [$('#primary_product').val()],
			active: $('input[name="active"]').is(':checked') ? 1 : 0
		}, function(response) {
			if (response.success) {
				window.location.href = response.data.redirect;
			} else {
				alert(response.data.message || 'Error creating funnel');
				$btn.prop('disabled', false);
			}
		}).fail(function() {
			alert('Request failed. Please try again.');
			$btn.prop('disabled', false);
		});
	});
});
</script>
