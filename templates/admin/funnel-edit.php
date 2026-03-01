<?php
/**
 * Admin Template: Edit Funnel Page
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkout_fields = $funnel['checkout_fields'] ?? [];
?>

<div class="wrap wsa-funnel-edit-page">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-edit"></span>
		<?php printf( __( 'Edit Funnel: %s', 'woo-smart-automation' ), esc_html( $funnel['title'] ) ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder' ) ); ?>" class="page-title-action">
		<span class="dashicons dashicons-arrow-left-alt"></span>
		<?php _e( 'Back to Funnels', 'woo-smart-automation' ); ?>
	</a>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=landing-page&funnel_id=' . $funnel['id'] ) ); ?>" class="page-title-action button-primary">
		<span class="dashicons dashicons-plus"></span>
		<?php _e( 'Add Landing Page', 'woo-smart-automation' ); ?>
	</a>

	<hr class="wp-header-end">

	<div class="wsa-edit-container">
		<div class="wsa-edit-main">
			<form id="wsa-edit-funnel-form" method="post">
				<?php wp_nonce_field( 'wsa_funnel_builder', 'nonce' ); ?>
				<input type="hidden" name="funnel_id" value="<?php echo esc_attr( $funnel['id'] ); ?>">

				<!-- Basic Settings -->
				<div class="wsa-card">
					<h2 class="wsa-card-title"><?php _e( 'Funnel Settings', 'woo-smart-automation' ); ?></h2>

					<div class="wsa-field-row">
						<label for="funnel_title"><?php _e( 'Funnel Name', 'woo-smart-automation' ); ?></label>
						<input type="text" id="funnel_title" name="title" value="<?php echo esc_attr( $funnel['title'] ); ?>" class="regular-text">
					</div>

					<div class="wsa-field-row">
						<label for="primary_product"><?php _e( 'Primary Product', 'woo-smart-automation' ); ?></label>
						<select id="primary_product" name="primary_product" class="regular-text">
							<option value=""><?php _e( 'Select product...', 'woo-smart-automation' ); ?></option>
							<?php foreach ( $products as $product_id => $product_label ) : ?>
								<option value="<?php echo esc_attr( $product_id ); ?>" <?php selected( $product_id, $funnel['primary_product'] ); ?>>
									<?php echo esc_html( $product_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="wsa-field-row">
						<label>
							<input type="checkbox" name="active" value="1" <?php checked( $funnel['active'] ); ?>>
							<?php _e( 'Funnel is active', 'woo-smart-automation' ); ?>
						</label>
					</div>
				</div>

				<!-- Checkout Form Builder (Drag & Drop) -->
				<div class="wsa-card">
					<h2 class="wsa-card-title">
						<?php _e( 'Checkout Form Builder', 'woo-smart-automation' ); ?>
						<span class="wsa-badge-info"><?php _e( 'Drag to reorder', 'woo-smart-automation' ); ?></span>
					</h2>

					<div class="wsa-field-row">
						<label for="button_text"><?php _e( 'Button Text', 'woo-smart-automation' ); ?></label>
						<input type="text" id="button_text" name="button_text" 
							   value="<?php echo esc_attr( $funnel['button_text'] ); ?>" 
							   placeholder="<?php _e( 'Order Now', 'woo-smart-automation' ); ?>">
					</div>

					<div class="wsa-field-row wsa-color-fields">
						<div>
							<label for="button_color"><?php _e( 'Button Color', 'woo-smart-automation' ); ?></label>
							<input type="color" id="button_color" name="button_color" 
								   value="<?php echo esc_attr( $funnel['button_color'] ?: '#e74c3c' ); ?>">
						</div>
						<div>
							<label for="button_text_color"><?php _e( 'Text Color', 'woo-smart-automation' ); ?></label>
							<input type="color" id="button_text_color" name="button_text_color" 
								   value="<?php echo esc_attr( $funnel['button_text_color'] ?: '#ffffff' ); ?>">
						</div>
					</div>

					<!-- Sortable Fields List -->
					<div class="wsa-checkout-builder">
						<h4><?php _e( 'Form Fields', 'woo-smart-automation' ); ?></h4>
						<p class="description"><?php _e( 'Drag fields to reorder. Toggle visibility and required status. Click field name to edit label.', 'woo-smart-automation' ); ?></p>

						<ul id="wsa-fields-sortable" class="wsa-sortable-fields">
							<?php 
							$fields = $checkout_fields['fields'] ?? [];
							if ( empty( $fields ) ) {
								// Load defaults
								$fields = ( new \WooSmartAutomation\Modules\AIFunnelBuilder\FunnelManager() )->get_default_checkout_fields()['fields'] ?? [];
							}
							foreach ( $fields as $index => $field ) : 
							?>
							<li class="wsa-sortable-field <?php echo empty( $field['visible'] ) ? 'wsa-field-hidden' : ''; ?>" data-field-id="<?php echo esc_attr( $field['id'] ); ?>">
								<span class="wsa-drag-handle" title="<?php _e( 'Drag to reorder', 'woo-smart-automation' ); ?>">☰</span>
								
								<div class="wsa-field-main">
									<input type="text" 
										   class="wsa-field-label-input" 
										   value="<?php echo esc_attr( $field['label'] ); ?>" 
										   data-original="<?php echo esc_attr( $field['label'] ); ?>"
										   title="<?php _e( 'Click to edit label', 'woo-smart-automation' ); ?>">
									<span class="wsa-field-id-badge"><?php echo esc_html( $field['id'] ); ?></span>
								</div>

								<div class="wsa-field-controls">
									<!-- Placeholder edit -->
									<input type="text" 
										   class="wsa-field-placeholder-input" 
										   value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" 
										   placeholder="<?php _e( 'Placeholder...', 'woo-smart-automation' ); ?>"
										   title="<?php _e( 'Edit placeholder text', 'woo-smart-automation' ); ?>">

									<!-- Width toggle -->
									<select class="wsa-field-width" title="<?php _e( 'Field width', 'woo-smart-automation' ); ?>">
										<option value="full" <?php selected( ( $field['width'] ?? 'full' ), 'full' ); ?>>Full</option>
										<option value="half" <?php selected( ( $field['width'] ?? 'full' ), 'half' ); ?>>Half</option>
									</select>

									<!-- Required toggle -->
									<label class="wsa-toggle-label" title="<?php _e( 'Required', 'woo-smart-automation' ); ?>">
										<input type="checkbox" class="wsa-field-required" <?php checked( ! empty( $field['required'] ) ); ?>>
										<span class="wsa-toggle-text"><?php _e( 'Required', 'woo-smart-automation' ); ?></span>
									</label>

									<!-- Visible toggle -->
									<label class="wsa-toggle-label wsa-toggle-visible" title="<?php _e( 'Show/Hide field', 'woo-smart-automation' ); ?>">
										<input type="checkbox" class="wsa-field-visible" <?php checked( isset( $field['visible'] ) ? $field['visible'] : true ); ?>>
										<span class="wsa-toggle-icon"><?php echo ( isset( $field['visible'] ) && ! $field['visible'] ) ? '👁️‍🗨️' : '👁️'; ?></span>
									</label>
								</div>
							</li>
							<?php endforeach; ?>
						</ul>

						<!-- Add Field Button -->
						<div class="wsa-add-field-section">
							<h4><?php _e( 'Add More Fields', 'woo-smart-automation' ); ?></h4>
							<div class="wsa-available-fields">
								<?php
								$available = [
									'billing_last_name' => __( 'Last Name', 'woo-smart-automation' ),
									'billing_email'     => __( 'Email', 'woo-smart-automation' ),
									'billing_address_2' => __( 'Address Line 2', 'woo-smart-automation' ),
									'billing_postcode'  => __( 'Postcode', 'woo-smart-automation' ),
									'order_comments'    => __( 'Order Notes', 'woo-smart-automation' ),
									'_wsa_quantity'     => __( 'Quantity', 'woo-smart-automation' ),
								];
								$existing_ids = wp_list_pluck( $fields, 'id' );
								foreach ( $available as $field_id => $field_label ) :
									if ( in_array( $field_id, $existing_ids ) ) continue;
								?>
								<button type="button" class="button wsa-add-field-btn" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-field-label="<?php echo esc_attr( $field_label ); ?>">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php echo esc_html( $field_label ); ?>
								</button>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Live Preview -->
					<div class="wsa-form-preview">
						<h4><?php _e( 'Form Preview', 'woo-smart-automation' ); ?></h4>
						<div id="wsa-checkout-preview" class="wsa-checkout-preview-box">
							<!-- JS will render preview here -->
						</div>
					</div>
				</div>

				<div class="wsa-form-actions">
					<button type="submit" class="button button-primary button-large">
						<span class="dashicons dashicons-yes"></span>
						<?php _e( 'Save Changes', 'woo-smart-automation' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Sidebar: Landing Pages -->
		<div class="wsa-edit-sidebar">
			<div class="wsa-card">
				<h3 class="wsa-card-title">
					<?php _e( 'Landing Pages', 'woo-smart-automation' ); ?>
					<span class="wsa-count"><?php echo count( $funnel['landing_pages'] ); ?></span>
				</h3>

				<?php if ( empty( $funnel['landing_pages'] ) ) : ?>
					<p class="wsa-empty-message"><?php _e( 'No landing pages yet.', 'woo-smart-automation' ); ?></p>
				<?php else : ?>
					<ul class="wsa-landing-pages-list">
						<?php foreach ( $funnel['landing_pages'] as $page ) : ?>
							<li>
								<div class="wsa-page-info">
									<strong><?php echo esc_html( $page['title'] ); ?></strong>
									<span class="wsa-page-status <?php echo $page['status']; ?>">
										<?php echo $page['status'] === 'publish' ? __( 'Published', 'woo-smart-automation' ) : __( 'Draft', 'woo-smart-automation' ); ?>
									</span>
								</div>
								<div class="wsa-page-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=landing-page&id=' . $page['id'] ) ); ?>" title="<?php _e( 'Edit', 'woo-smart-automation' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</a>
									<?php if ( $page['status'] === 'publish' ) : ?>
										<a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank" title="<?php _e( 'View', 'woo-smart-automation' ); ?>">
											<span class="dashicons dashicons-external"></span>
										</a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=landing-page&funnel_id=' . $funnel['id'] ) ); ?>" class="button button-secondary button-block">
					<span class="dashicons dashicons-plus"></span>
					<?php _e( 'Create New Page', 'woo-smart-automation' ); ?>
				</a>
			</div>

			<!-- Stats -->
			<div class="wsa-card">
				<h3 class="wsa-card-title"><?php _e( 'Statistics', 'woo-smart-automation' ); ?></h3>
				<div class="wsa-stats-grid">
					<div class="wsa-stat-box">
						<span class="wsa-stat-value"><?php echo count( $funnel['landing_pages'] ); ?></span>
						<span class="wsa-stat-label"><?php _e( 'Pages', 'woo-smart-automation' ); ?></span>
					</div>
					<div class="wsa-stat-box">
						<span class="wsa-stat-value">0</span>
						<span class="wsa-stat-label"><?php _e( 'Orders', 'woo-smart-automation' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Info -->
			<div class="wsa-card wsa-info-card">
				<p><strong><?php _e( 'Created:', 'woo-smart-automation' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $funnel['created_at'] ) ); ?></p>
				<p><strong><?php _e( 'Modified:', 'woo-smart-automation' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $funnel['modified_at'] ) ); ?></p>
			</div>
		</div>
	</div>
</div>

<style>
.wsa-funnel-edit-page .page-title-action {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.wsa-edit-container {
	display: flex;
	gap: 20px;
	margin-top: 20px;
	align-items: flex-start;
}

.wsa-edit-main {
	flex: 1;
	max-width: 750px;
}

.wsa-edit-sidebar {
	width: 300px;
	flex-shrink: 0;
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
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.wsa-badge-info {
	background: #e8f0fe;
	color: #1a73e8;
	font-size: 11px;
	font-weight: 500;
	padding: 3px 10px;
	border-radius: 12px;
}

.wsa-count {
	background: #f0f6fc;
	color: #2271b1;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 12px;
}

.wsa-field-row {
	margin-bottom: 20px;
}

.wsa-field-row label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
}

.wsa-field-row input[type="text"],
.wsa-field-row select {
	width: 100%;
}

.wsa-color-fields {
	display: flex;
	gap: 20px;
}

.wsa-color-fields > div {
	flex: 1;
}

.wsa-color-fields input[type="color"] {
	width: 60px;
	height: 36px;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	cursor: pointer;
	padding: 2px;
}

/* Checkout Builder */
.wsa-checkout-builder {
	margin-top: 25px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.wsa-checkout-builder h4 {
	margin: 0 0 5px;
	font-size: 14px;
}

.wsa-checkout-builder > .description {
	margin: 0 0 15px;
	font-style: italic;
	color: #666;
}

/* Sortable Fields */
.wsa-sortable-fields {
	list-style: none;
	margin: 0;
	padding: 0;
}

.wsa-sortable-field {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	margin-bottom: 4px;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 6px;
	cursor: default;
	transition: box-shadow 0.2s, border-color 0.2s, opacity 0.3s;
}

.wsa-sortable-field:hover {
	border-color: #2271b1;
	box-shadow: 0 1px 4px rgba(34,113,177,0.12);
}

.wsa-sortable-field.wsa-field-hidden {
	opacity: 0.45;
	background: #f9f9f9;
}

.wsa-sortable-field.ui-sortable-helper {
	box-shadow: 0 4px 16px rgba(0,0,0,0.18);
	border-color: #2271b1;
	z-index: 100;
}

.wsa-sortable-field.ui-sortable-placeholder {
	visibility: visible !important;
	background: #e8f0fe;
	border: 2px dashed #2271b1;
	height: 44px;
}

.wsa-drag-handle {
	cursor: grab;
	color: #999;
	font-size: 16px;
	flex-shrink: 0;
	user-select: none;
	width: 20px;
	text-align: center;
}

.wsa-drag-handle:active {
	cursor: grabbing;
}

.wsa-field-main {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.wsa-field-label-input {
	border: 1px solid transparent !important;
	background: transparent !important;
	font-weight: 600;
	font-size: 13px;
	padding: 3px 6px !important;
	border-radius: 4px !important;
	max-width: 160px;
	transition: border-color 0.2s, background 0.2s;
}

.wsa-field-label-input:hover,
.wsa-field-label-input:focus {
	border-color: #c3c4c7 !important;
	background: #fff !important;
	outline: none;
}

.wsa-field-id-badge {
	font-size: 10px;
	color: #888;
	background: #f0f0f0;
	padding: 1px 6px;
	border-radius: 3px;
	white-space: nowrap;
}

.wsa-field-controls {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;
}

.wsa-field-placeholder-input {
	width: 120px !important;
	font-size: 12px !important;
	padding: 3px 6px !important;
	border-color: #ddd !important;
	border-radius: 4px !important;
}

.wsa-field-width {
	width: 65px !important;
	font-size: 12px !important;
	padding: 2px 4px !important;
	border-radius: 4px !important;
}

.wsa-toggle-label {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 11px;
	cursor: pointer;
	white-space: nowrap;
	color: #555;
	user-select: none;
}

.wsa-toggle-label input[type="checkbox"] {
	width: 14px;
	height: 14px;
	margin: 0;
}

.wsa-toggle-visible {
	font-size: 16px;
}

.wsa-toggle-visible .wsa-toggle-icon {
	cursor: pointer;
}

/* Add Field Section */
.wsa-add-field-section {
	margin-top: 20px;
	padding-top: 15px;
	border-top: 1px dashed #ddd;
}

.wsa-add-field-section h4 {
	margin: 0 0 10px;
	font-size: 13px;
	color: #555;
}

.wsa-available-fields {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.wsa-add-field-btn {
	display: inline-flex !important;
	align-items: center;
	gap: 4px;
	font-size: 12px !important;
	padding: 4px 10px !important;
}

.wsa-add-field-btn .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
	line-height: 14px;
}

/* Form Preview */
.wsa-form-preview {
	margin-top: 25px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.wsa-form-preview h4 {
	margin: 0 0 10px;
	font-size: 14px;
}

.wsa-checkout-preview-box {
	background: #fafafa;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 20px;
	min-height: 100px;
}

.wsa-preview-field {
	margin-bottom: 12px;
}

.wsa-preview-field.wsa-half {
	display: inline-block;
	width: calc(50% - 6px);
	vertical-align: top;
}

.wsa-preview-field.wsa-half:nth-child(even) {
	margin-left: 10px;
}

.wsa-preview-label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 4px;
	color: #333;
}

.wsa-preview-label .wsa-required-star {
	color: #e74c3c;
	margin-left: 2px;
}

.wsa-preview-input {
	width: 100%;
	padding: 8px 10px;
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fff;
	font-size: 13px;
	color: #999;
	box-sizing: border-box;
}

.wsa-preview-btn {
	display: block;
	width: 100%;
	padding: 12px;
	border: none;
	border-radius: 6px;
	color: #fff;
	font-size: 16px;
	font-weight: 700;
	text-align: center;
	margin-top: 15px;
	cursor: default;
}

.wsa-form-actions {
	margin-top: 20px;
}

.wsa-form-actions .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

/* Sidebar */
.wsa-empty-message {
	color: #666;
	text-align: center;
	padding: 20px;
}

.wsa-landing-pages-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.wsa-landing-pages-list li {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	background: #f9f9f9;
	border-radius: 4px;
	margin-bottom: 8px;
}

.wsa-page-info {
	display: flex;
	flex-direction: column;
	gap: 3px;
}

.wsa-page-status {
	font-size: 11px;
}

.wsa-page-status.publish {
	color: #155724;
}

.wsa-page-status.draft {
	color: #856404;
}

.wsa-page-actions {
	display: flex;
	gap: 5px;
}

.wsa-page-actions a {
	color: #666;
}

.wsa-page-actions a:hover {
	color: #2271b1;
}

.button-block {
	display: block;
	text-align: center;
	margin-top: 15px;
}

.wsa-stats-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 15px;
}

.wsa-stat-box {
	text-align: center;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
}

.wsa-stat-value {
	display: block;
	font-size: 24px;
	font-weight: 700;
	color: #2271b1;
}

.wsa-stat-label {
	font-size: 12px;
	color: #666;
}

.wsa-info-card {
	font-size: 13px;
}

.wsa-info-card p {
	margin: 5px 0;
}

@media (max-width: 782px) {
	.wsa-edit-container {
		flex-direction: column;
	}
	
	.wsa-edit-main,
	.wsa-edit-sidebar {
		width: 100%;
		max-width: none;
	}

	.wsa-sortable-field {
		flex-wrap: wrap;
	}

	.wsa-field-controls {
		width: 100%;
		padding-left: 30px;
		margin-top: 6px;
	}
}
</style>

<script>
jQuery(document).ready(function($) {

	/* ── Sortable drag-and-drop ─────────────────────── */
	$('#wsa-fields-sortable').sortable({
		handle: '.wsa-drag-handle',
		placeholder: 'ui-sortable-placeholder',
		tolerance: 'pointer',
		axis: 'y',
		cursor: 'grabbing',
		opacity: 0.85,
		update: function() {
			renderPreview();
		}
	});

	/* ── Visibility toggle ──────────────────────────── */
	$(document).on('change', '.wsa-field-visible', function() {
		var $li = $(this).closest('.wsa-sortable-field');
		if ($(this).is(':checked')) {
			$li.removeClass('wsa-field-hidden');
			$li.find('.wsa-toggle-icon').text('👁️');
		} else {
			$li.addClass('wsa-field-hidden');
			$li.find('.wsa-toggle-icon').text('👁️‍🗨️');
		}
		renderPreview();
	});

	/* ── Label / Placeholder / Width / Required changes → preview ── */
	$(document).on('input change', '.wsa-field-label-input, .wsa-field-placeholder-input, .wsa-field-width, .wsa-field-required', function() {
		renderPreview();
	});

	/* ── Button color / text → preview ──────────────── */
	$('#button_color, #button_text_color, #button_text').on('input change', function() {
		renderPreview();
	});

	/* ── Add Field ──────────────────────────────────── */
	$(document).on('click', '.wsa-add-field-btn', function() {
		var fieldId    = $(this).data('field-id');
		var fieldLabel = $(this).data('field-label');
		
		var html = '<li class="wsa-sortable-field" data-field-id="' + fieldId + '">' +
			'<span class="wsa-drag-handle" title="Drag to reorder">☰</span>' +
			'<div class="wsa-field-main">' +
				'<input type="text" class="wsa-field-label-input" value="' + fieldLabel + '" data-original="' + fieldLabel + '">' +
				'<span class="wsa-field-id-badge">' + fieldId + '</span>' +
			'</div>' +
			'<div class="wsa-field-controls">' +
				'<input type="text" class="wsa-field-placeholder-input" value="" placeholder="Placeholder...">' +
				'<select class="wsa-field-width"><option value="full">Full</option><option value="half">Half</option></select>' +
				'<label class="wsa-toggle-label"><input type="checkbox" class="wsa-field-required"> <span class="wsa-toggle-text">Required</span></label>' +
				'<label class="wsa-toggle-label wsa-toggle-visible"><input type="checkbox" class="wsa-field-visible" checked> <span class="wsa-toggle-icon">👁️</span></label>' +
				'<button type="button" class="button-link wsa-remove-field" title="Remove field" style="color:#b32d2e;font-size:16px;">✕</button>' +
			'</div>' +
		'</li>';
		
		$('#wsa-fields-sortable').append(html);
		$('#wsa-fields-sortable').sortable('refresh');
		
		// Hide the add button
		$(this).fadeOut(200);
		renderPreview();
	});

	/* ── Remove Field ───────────────────────────────── */
	$(document).on('click', '.wsa-remove-field', function() {
		var $li = $(this).closest('.wsa-sortable-field');
		var fieldId = $li.data('field-id');
		$li.slideUp(200, function() {
			$(this).remove();
			renderPreview();
			// Show add button again
			$('.wsa-add-field-btn[data-field-id="' + fieldId + '"]').fadeIn(200);
		});
	});

	/* ── Collect field config JSON ─────────────────── */
	function collectCheckoutFields() {
		var fields = [];
		$('#wsa-fields-sortable .wsa-sortable-field').each(function() {
			fields.push({
				id:          $(this).data('field-id'),
				label:       $(this).find('.wsa-field-label-input').val(),
				placeholder: $(this).find('.wsa-field-placeholder-input').val(),
				type:        getFieldType($(this).data('field-id')),
				width:       $(this).find('.wsa-field-width').val() || 'full',
				required:    $(this).find('.wsa-field-required').is(':checked'),
				visible:     $(this).find('.wsa-field-visible').is(':checked')
			});
		});

		return {
			fields: fields,
			submit_button: {
				text:       $('#button_text').val() || 'Order Now',
				color:      $('#button_color').val() || '#e74c3c',
				text_color: $('#button_text_color').val() || '#ffffff'
			},
			layout: 'single_column'
		};
	}

	function getFieldType(fieldId) {
		var typeMap = {
			'billing_email': 'email',
			'billing_phone': 'tel',
			'order_comments': 'textarea',
			'billing_state': 'select',
			'_wsa_quantity': 'number'
		};
		return typeMap[fieldId] || 'text';
	}

	/* ── Render Live Preview ────────────────────────── */
	function renderPreview() {
		var config = collectCheckoutFields();
		var html = '';

		config.fields.forEach(function(f) {
			if (!f.visible) return;
			var widthClass = (f.width === 'half') ? ' wsa-half' : '';
			var reqStar    = f.required ? '<span class="wsa-required-star">*</span>' : '';
			var inputHtml;

			if (f.type === 'textarea') {
				inputHtml = '<div class="wsa-preview-input" style="height:60px;"></div>';
			} else if (f.type === 'select') {
				inputHtml = '<div class="wsa-preview-input">' + (f.placeholder || 'Select...') + ' ▾</div>';
			} else {
				inputHtml = '<div class="wsa-preview-input">' + (f.placeholder || f.label) + '</div>';
			}

			html += '<div class="wsa-preview-field' + widthClass + '">' +
				'<span class="wsa-preview-label">' + f.label + reqStar + '</span>' +
				inputHtml +
			'</div>';
		});

		// Submit button preview
		html += '<div class="wsa-preview-btn" style="background:' + config.submit_button.color + ';color:' + config.submit_button.text_color + ';">' +
			config.submit_button.text +
		'</div>';

		$('#wsa-checkout-preview').html(html);
	}

	// Initial preview render
	renderPreview();

	/* ── Form Submit ─────────────────────────────────── */
	$('#wsa-edit-funnel-form').on('submit', function(e) {
		e.preventDefault();
		
		var $form = $(this);
		var $btn = $form.find('button[type="submit"]');
		var checkoutConfig = collectCheckoutFields();
		
		$btn.prop('disabled', true).text('<?php _e( 'Saving...', 'woo-smart-automation' ); ?>');
		
		$.post(wsaFunnelBuilder.ajaxUrl, {
			action: 'wsa_update_funnel',
			nonce: wsaFunnelBuilder.nonce,
			funnel_id: $('input[name="funnel_id"]').val(),
			title: $('#funnel_title').val(),
			primary_product: $('#primary_product').val(),
			products: [$('#primary_product').val()],
			button_text: checkoutConfig.submit_button.text,
			button_color: checkoutConfig.submit_button.color,
			button_text_color: checkoutConfig.submit_button.text_color,
			checkout_fields: JSON.stringify(checkoutConfig),
			active: $('input[name="active"]').is(':checked') ? 1 : 0
		}, function(response) {
			if (response.success) {
				$btn.text('✓ <?php _e( 'Saved!', 'woo-smart-automation' ); ?>');
				setTimeout(function() {
					$btn.html('<span class="dashicons dashicons-saved"></span> <?php _e( 'Save Changes', 'woo-smart-automation' ); ?>');
				}, 1500);
			} else {
				alert(response.data.message || '<?php _e( 'Error saving changes.', 'woo-smart-automation' ); ?>');
			}
		}).fail(function() {
			alert('<?php _e( 'Request failed.', 'woo-smart-automation' ); ?>');
		}).always(function() {
			$btn.prop('disabled', false);
		});
	});
});
</script>
