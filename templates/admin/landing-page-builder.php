<?php
/**
 * Admin Template: Landing Page Builder
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $page );
$page_title = $is_edit ? $page['title'] : '';
$page_html = $is_edit ? $page['html'] : '';
$selected_product = $is_edit ? $page['product_id'] : ( $funnel['primary_product'] ?? 0 );
$selected_template = $is_edit ? $page['template'] : '';
$saved_prompt = $is_edit ? $page['prompt'] : '';
$saved_images = $is_edit ? $page['images'] : [];
?>

<div class="wrap wsa-landing-page-builder">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-welcome-widgets-menus"></span>
		<?php echo $is_edit ? __( 'Edit Landing Page', 'woo-smart-automation' ) : __( 'Create Landing Page', 'woo-smart-automation' ); ?>
	</h1>

	<?php if ( $funnel ) : ?>
		<span class="wsa-funnel-badge">
			<?php printf( __( 'Funnel: %s', 'woo-smart-automation' ), esc_html( $funnel['title'] ) ); ?>
		</span>
	<?php endif; ?>

	<hr class="wp-header-end">

	<div class="wsa-builder-container">
		<!-- Left Panel: Configuration -->
		<div class="wsa-builder-config">
			<form id="wsa-landing-page-form">
				<?php wp_nonce_field( 'wsa_funnel_builder', 'nonce' ); ?>
				<input type="hidden" name="page_id" value="<?php echo esc_attr( $page['id'] ?? '' ); ?>">
				<input type="hidden" name="funnel_id" value="<?php echo esc_attr( $funnel['id'] ?? '' ); ?>">

				<!-- Page Settings -->
				<div class="wsa-card">
					<h3 class="wsa-card-title"><?php _e( 'Page Settings', 'woo-smart-automation' ); ?></h3>
					
					<div class="wsa-field-row">
						<label for="page_title"><?php _e( 'Page Title', 'woo-smart-automation' ); ?></label>
						<input type="text" id="page_title" name="title" value="<?php echo esc_attr( $page_title ); ?>" class="regular-text">
					</div>

					<div class="wsa-field-row">
						<label for="product_id"><?php _e( 'Product', 'woo-smart-automation' ); ?></label>
						<select id="product_id" name="product_id" class="regular-text">
							<option value=""><?php _e( 'Select a product...', 'woo-smart-automation' ); ?></option>
							<?php foreach ( $products as $product_id => $product_label ) : ?>
								<option value="<?php echo esc_attr( $product_id ); ?>" <?php selected( $product_id, $selected_product ); ?>>
									<?php echo esc_html( $product_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Template Selection -->
				<div class="wsa-card">
					<h3 class="wsa-card-title"><?php _e( 'Template', 'woo-smart-automation' ); ?></h3>
					
					<div class="wsa-template-grid">
						<?php foreach ( $templates as $template ) : ?>
							<label class="wsa-template-option <?php echo $template['slug'] === $selected_template ? 'selected' : ''; ?>">
								<input type="radio" name="template" value="<?php echo esc_attr( $template['slug'] ); ?>" 
									<?php checked( $template['slug'], $selected_template ); ?>>
								<span class="wsa-template-box">
									<span class="wsa-template-name"><?php echo esc_html( $template['name'] ); ?></span>
									<span class="wsa-template-desc"><?php echo esc_html( $template['description'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
						<label class="wsa-template-option <?php echo 'custom' === $selected_template || empty( $selected_template ) ? 'selected' : ''; ?>">
							<input type="radio" name="template" value="custom" <?php checked( true, empty( $selected_template ) || 'custom' === $selected_template ); ?>>
							<span class="wsa-template-box">
								<span class="wsa-template-name"><?php _e( 'Custom', 'woo-smart-automation' ); ?></span>
								<span class="wsa-template-desc"><?php _e( 'Write your own prompt', 'woo-smart-automation' ); ?></span>
							</span>
						</label>
					</div>
				</div>

				<!-- Custom Prompt -->
				<div class="wsa-card">
					<h3 class="wsa-card-title"><?php _e( 'Custom Instructions', 'woo-smart-automation' ); ?></h3>
					<div class="wsa-field-row">
						<textarea id="custom_prompt" name="custom_prompt" rows="6" placeholder="<?php _e( 'Add any additional instructions for the AI...', 'woo-smart-automation' ); ?>"><?php echo esc_textarea( $saved_prompt ); ?></textarea>
						<p class="description"><?php _e( 'Optional. Add extra instructions or customize the template prompt.', 'woo-smart-automation' ); ?></p>
					</div>
				</div>

				<!-- Image Upload Section -->
				<div class="wsa-card">
					<h3 class="wsa-card-title">
						<?php _e( 'Images', 'woo-smart-automation' ); ?>
						<span class="wsa-badge"><?php _e( 'Text-based placement', 'woo-smart-automation' ); ?></span>
					</h3>
					
					<div id="wsa-images-container">
						<?php if ( ! empty( $saved_images ) ) : ?>
							<?php foreach ( $saved_images as $index => $image ) : ?>
								<div class="wsa-image-row" data-index="<?php echo $index; ?>">
									<div class="wsa-image-preview">
										<img src="<?php echo esc_url( $image['url'] ); ?>" alt="">
									</div>
									<div class="wsa-image-fields">
										<input type="hidden" name="images[<?php echo $index; ?>][url]" value="<?php echo esc_url( $image['url'] ); ?>">
										<input type="hidden" name="images[<?php echo $index; ?>][attachment_id]" value="<?php echo esc_attr( $image['attachment_id'] ?? '' ); ?>">
										<textarea name="images[<?php echo $index; ?>][placement_text]" 
												  placeholder="<?php _e( 'Describe where to place this image (e.g., "Use as hero background", "Product image in center", "Customer testimonial avatar")', 'woo-smart-automation' ); ?>"
												  rows="2"><?php echo esc_textarea( $image['placement_text'] ?? '' ); ?></textarea>
									</div>
									<button type="button" class="button wsa-remove-image">
										<span class="dashicons dashicons-no"></span>
									</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<button type="button" class="button" id="wsa-add-image">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php _e( 'Add Image', 'woo-smart-automation' ); ?>
					</button>

					<p class="description wsa-image-note">
						<strong><?php _e( 'Important:', 'woo-smart-automation' ); ?></strong>
						<?php _e( 'Describe in plain text where and how each image should be used. The AI will interpret your instructions and place images accordingly.', 'woo-smart-automation' ); ?>
					</p>
				</div>

				<!-- Generate Button -->
				<div class="wsa-card wsa-generate-section">
					<button type="button" class="button button-primary button-hero" id="wsa-generate-btn">
						<span class="dashicons dashicons-update"></span>
						<?php _e( 'Generate Landing Page', 'woo-smart-automation' ); ?>
					</button>
					<p class="description"><?php _e( 'AI will create a complete landing page based on your settings.', 'woo-smart-automation' ); ?></p>
				</div>
			</form>
		</div>

		<!-- Right Panel: Preview -->
		<div class="wsa-builder-preview">
			<div class="wsa-preview-toolbar">
				<div class="wsa-device-switcher">
					<button type="button" class="active" data-device="desktop" title="<?php _e( 'Desktop', 'woo-smart-automation' ); ?>">
						<span class="dashicons dashicons-desktop"></span>
					</button>
					<button type="button" data-device="tablet" title="<?php _e( 'Tablet', 'woo-smart-automation' ); ?>">
						<span class="dashicons dashicons-tablet"></span>
					</button>
					<button type="button" data-device="mobile" title="<?php _e( 'Mobile', 'woo-smart-automation' ); ?>">
						<span class="dashicons dashicons-smartphone"></span>
					</button>
				</div>

				<div class="wsa-preview-actions">
					<button type="button" class="button" id="wsa-toggle-code" title="<?php _e( 'View HTML Code', 'woo-smart-automation' ); ?>">
						<span class="dashicons dashicons-editor-code"></span>
					</button>
					<?php if ( $is_edit ) : ?>
						<a href="<?php echo esc_url( ( new \WooSmartAutomation\Modules\AIFunnelBuilder\PreviewRenderer() )->get_preview_url( $page['id'] ) ); ?>" 
						   class="button" target="_blank" title="<?php _e( 'Open in New Tab', 'woo-smart-automation' ); ?>">
							<span class="dashicons dashicons-external"></span>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="wsa-preview-container" data-device="desktop">
				<div id="wsa-preview-frame">
					<?php if ( ! empty( $page_html ) ) : ?>
						<?php echo $page_html; ?>
					<?php else : ?>
						<div class="wsa-preview-placeholder">
							<div class="wsa-preview-placeholder-icon">🎨</div>
							<h3><?php _e( 'Preview will appear here', 'woo-smart-automation' ); ?></h3>
							<p><?php _e( 'Select a product and template, then click "Generate Landing Page"', 'woo-smart-automation' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div id="wsa-code-editor" style="display: none;">
				<textarea id="wsa-html-code" rows="20"><?php echo esc_textarea( $page_html ); ?></textarea>
			</div>

			<!-- Save Actions -->
			<div class="wsa-save-toolbar">
				<button type="button" class="button button-secondary" id="wsa-save-draft">
					<span class="dashicons dashicons-edit"></span>
					<?php _e( 'Save Draft', 'woo-smart-automation' ); ?>
				</button>
				<button type="button" class="button button-primary" id="wsa-publish">
					<span class="dashicons dashicons-visibility"></span>
					<?php _e( 'Publish', 'woo-smart-automation' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<style>
.wsa-landing-page-builder {
	max-width: 100%;
}

.wsa-funnel-badge {
	background: #f0f6fc;
	padding: 5px 12px;
	border-radius: 4px;
	font-size: 13px;
	margin-left: 15px;
	color: #2271b1;
}

.wsa-builder-container {
	display: flex;
	gap: 20px;
	margin-top: 20px;
	align-items: flex-start;
}

.wsa-builder-config {
	width: 400px;
	flex-shrink: 0;
}

.wsa-builder-preview {
	flex: 1;
	min-width: 0;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	position: sticky;
	top: 32px;
}

.wsa-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 15px;
}

.wsa-card-title {
	margin: 0 0 15px;
	font-size: 14px;
	display: flex;
	align-items: center;
	gap: 10px;
}

.wsa-badge {
	background: #2271b1;
	color: #fff;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: normal;
}

.wsa-field-row {
	margin-bottom: 15px;
}

.wsa-field-row:last-child {
	margin-bottom: 0;
}

.wsa-field-row label {
	display: block;
	font-weight: 600;
	margin-bottom: 5px;
}

.wsa-field-row input,
.wsa-field-row select,
.wsa-field-row textarea {
	width: 100%;
}

/* Template Grid */
.wsa-template-grid {
	display: grid;
	gap: 10px;
}

.wsa-template-option {
	cursor: pointer;
}

.wsa-template-option input {
	display: none;
}

.wsa-template-box {
	display: block;
	padding: 12px;
	border: 2px solid #ddd;
	border-radius: 4px;
	transition: all 0.2s;
}

.wsa-template-option:hover .wsa-template-box {
	border-color: #2271b1;
}

.wsa-template-option.selected .wsa-template-box,
.wsa-template-option input:checked + .wsa-template-box {
	border-color: #2271b1;
	background: #f0f6fc;
}

.wsa-template-name {
	display: block;
	font-weight: 600;
	margin-bottom: 3px;
}

.wsa-template-desc {
	font-size: 12px;
	color: #666;
}

/* Image Upload */
.wsa-image-row {
	display: flex;
	gap: 10px;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
	margin-bottom: 10px;
	align-items: flex-start;
}

.wsa-image-preview {
	width: 80px;
	height: 80px;
	flex-shrink: 0;
	border: 1px solid #ddd;
	border-radius: 4px;
	overflow: hidden;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #fff;
}

.wsa-image-preview img {
	max-width: 100%;
	max-height: 100%;
	object-fit: contain;
}

.wsa-image-fields {
	flex: 1;
}

.wsa-image-fields textarea {
	width: 100%;
	resize: vertical;
}

.wsa-remove-image {
	color: #dc3545;
}

.wsa-image-note {
	margin-top: 15px;
	padding: 10px;
	background: #fff3cd;
	border-radius: 4px;
}

/* Generate Section */
.wsa-generate-section {
	text-align: center;
}

.wsa-generate-section .button-hero {
	display: inline-flex;
	align-items: center;
	gap: 10px;
}

/* Preview Toolbar */
.wsa-preview-toolbar {
	display: flex;
	justify-content: space-between;
	padding: 10px 15px;
	border-bottom: 1px solid #eee;
	background: #f9f9f9;
}

.wsa-device-switcher {
	display: flex;
	gap: 5px;
}

.wsa-device-switcher button {
	background: #fff;
	border: 1px solid #ddd;
	padding: 5px 10px;
	cursor: pointer;
	border-radius: 4px;
}

.wsa-device-switcher button.active {
	background: #2271b1;
	color: #fff;
	border-color: #2271b1;
}

.wsa-preview-actions {
	display: flex;
	gap: 5px;
}

/* Preview Container */
.wsa-preview-container {
	padding: 20px;
	background: #f0f0f0;
	min-height: 500px;
}

.wsa-preview-container[data-device="mobile"] #wsa-preview-frame {
	max-width: 375px;
	margin: 0 auto;
	box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.wsa-preview-container[data-device="tablet"] #wsa-preview-frame {
	max-width: 768px;
	margin: 0 auto;
	box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

#wsa-preview-frame {
	background: #fff;
	min-height: 400px;
}

.wsa-preview-placeholder {
	text-align: center;
	padding: 60px 20px;
	color: #666;
}

.wsa-preview-placeholder-icon {
	font-size: 48px;
	margin-bottom: 15px;
}

/* Code Editor */
#wsa-code-editor {
	padding: 20px;
}

#wsa-html-code {
	width: 100%;
	font-family: monospace;
	font-size: 13px;
}

/* Save Toolbar */
.wsa-save-toolbar {
	display: flex;
	gap: 10px;
	padding: 15px;
	border-top: 1px solid #eee;
	background: #f9f9f9;
	justify-content: flex-end;
}

.wsa-save-toolbar .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

/* Loading State */
.wsa-generating #wsa-generate-btn {
	pointer-events: none;
	opacity: 0.7;
}

.wsa-generating #wsa-generate-btn .dashicons {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

@media (max-width: 1200px) {
	.wsa-builder-container {
		flex-direction: column;
	}
	
	.wsa-builder-config {
		width: 100%;
	}
	
	.wsa-builder-preview {
		position: static;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	var imageIndex = <?php echo ! empty( $saved_images ) ? count( $saved_images ) : 0; ?>;
	var generatedHtml = <?php echo wp_json_encode( $page_html ); ?>;

	// Template selection
	$('.wsa-template-option input').on('change', function() {
		$('.wsa-template-option').removeClass('selected');
		$(this).closest('.wsa-template-option').addClass('selected');
	});

	// Device switcher
	$('.wsa-device-switcher button').on('click', function() {
		$('.wsa-device-switcher button').removeClass('active');
		$(this).addClass('active');
		$('.wsa-preview-container').attr('data-device', $(this).data('device'));
	});

	// Toggle code view
	$('#wsa-toggle-code').on('click', function() {
		$('#wsa-code-editor').toggle();
		$('.wsa-preview-container').toggle();
	});

	// Add image
	$('#wsa-add-image').on('click', function() {
		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('Media uploader not available. Please reload the page.');
			return;
		}
		var frame = wp.media({
			title: (wsaFunnelBuilder.i18n && wsaFunnelBuilder.i18n.uploadImage) || (wsaFunnelBuilder.strings && wsaFunnelBuilder.strings.uploadImage) || 'Upload Image',
			button: { text: (wsaFunnelBuilder.i18n && wsaFunnelBuilder.i18n.uploadImage) || 'Use Image' },
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			addImageRow(attachment);
		});

		frame.open();
	});

	function addImageRow(attachment) {
		var html = `
			<div class="wsa-image-row" data-index="${imageIndex}">
				<div class="wsa-image-preview">
					<img src="${attachment.url}" alt="">
				</div>
				<div class="wsa-image-fields">
					<input type="hidden" name="images[${imageIndex}][url]" value="${attachment.url}">
					<input type="hidden" name="images[${imageIndex}][attachment_id]" value="${attachment.id}">
					<textarea name="images[${imageIndex}][placement_text]" 
							  placeholder="<?php _e( 'Describe where to place this image...', 'woo-smart-automation' ); ?>"
							  rows="2"></textarea>
				</div>
				<button type="button" class="button wsa-remove-image">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		`;
		$('#wsa-images-container').append(html);
		imageIndex++;
	}

	// Remove image
	$(document).on('click', '.wsa-remove-image', function() {
		$(this).closest('.wsa-image-row').remove();
	});

	// Generate landing page
	$('#wsa-generate-btn').on('click', function() {
		var $btn = $(this);
		var productId = $('#product_id').val();
		
		if (!productId) {
			alert('<?php _e( 'Please select a product first.', 'woo-smart-automation' ); ?>');
			return;
		}

		// Collect images
		var images = [];
		$('.wsa-image-row').each(function() {
			var $row = $(this);
			images.push({
				url: $row.find('input[name*="[url]"]').val(),
				attachment_id: $row.find('input[name*="[attachment_id]"]').val(),
				placement_text: $row.find('textarea').val()
			});
		});

		$('body').addClass('wsa-generating');
		$btn.prop('disabled', true);

		// Show progress indicator
		var elapsed = 0;
		$('#wsa-preview-frame').html(
			'<div style="text-align:center;padding:60px 20px;">' +
			'<div style="font-size:48px;margin-bottom:15px;">⏳</div>' +
			'<h3 style="color:#2271b1;">Generating Landing Page...</h3>' +
			'<p style="color:#666;" id="wsa-gen-timer">Elapsed: 0s — AI is creating your page</p>' +
			'<div style="width:200px;height:4px;background:#eee;border-radius:2px;margin:15px auto;overflow:hidden;">' +
			'<div style="width:30%;height:100%;background:#2271b1;border-radius:2px;animation:wsaProgress 2s ease-in-out infinite;"></div>' +
			'</div>' +
			'<style>@keyframes wsaProgress{0%{width:10%;margin-left:0}50%{width:60%;margin-left:20%}100%{width:10%;margin-left:90%}}</style>' +
			'</div>'
		);
		var timerInterval = setInterval(function() {
			elapsed++;
			var msg = elapsed < 15 ? 'AI is creating your page' :
			          elapsed < 45 ? 'Building HTML & CSS layout...' :
			          elapsed < 90 ? 'Almost done, finalizing design...' :
			          'Still working, large pages take longer...';
			$('#wsa-gen-timer').text('Elapsed: ' + elapsed + 's — ' + msg);
		}, 1000);
		
		$.ajax({
			url: wsaFunnelBuilder.ajaxUrl,
			type: 'POST',
			timeout: 180000, // 3 minutes client-side timeout
			data: {
				action: 'wsa_generate_landing_page',
				nonce: wsaFunnelBuilder.nonce,
				product_id: productId,
				funnel_id: $('input[name="funnel_id"]').val() || '',
				template: $('input[name="template"]:checked').val(),
				custom_prompt: $('#custom_prompt').val(),
				images: images
			},
			success: function(response) {
				if (response.success && response.data.html) {
					generatedHtml = response.data.html;
					$('#wsa-preview-frame').html(generatedHtml);
					$('#wsa-html-code').val(generatedHtml);
				} else {
					var errorMsg = (response.data && response.data.message) || '<?php _e( 'Generation failed. Please try again.', 'woo-smart-automation' ); ?>';
					$('#wsa-preview-frame').html(
						'<div style="text-align:center;padding:60px 20px;color:#d63638;">' +
						'<div style="font-size:48px;margin-bottom:15px;">⚠️</div>' +
						'<h3>Generation Failed</h3>' +
						'<p>' + errorMsg + '</p>' +
						'<p style="color:#666;font-size:12px;margin-top:10px;">Tip: Check your API key in AI Settings, or try a different model.</p>' +
						'</div>'
					);
				}
			},
			error: function(xhr, status, error) {
				var errorMsg = status === 'timeout'
					? '<?php _e( 'Request timed out. The AI is taking too long. Try selecting a faster model in AI Settings.', 'woo-smart-automation' ); ?>'
					: '<?php _e( 'Request failed: ', 'woo-smart-automation' ); ?>' + (error || status);
				$('#wsa-preview-frame').html(
					'<div style="text-align:center;padding:60px 20px;color:#d63638;">' +
					'<div style="font-size:48px;margin-bottom:15px;">❌</div>' +
					'<h3>Request Failed</h3>' +
					'<p>' + errorMsg + '</p>' +
					'</div>'
				);
			},
			complete: function() {
				clearInterval(timerInterval);
				$('body').removeClass('wsa-generating');
				$btn.prop('disabled', false);
			}
		});
	});

	// Save draft
	$('#wsa-save-draft').on('click', function() {
		savePageContent('draft');
	});

	// Publish
	$('#wsa-publish').on('click', function() {
		savePageContent('publish');
	});

	function savePageContent(status) {
		var html = $('#wsa-html-code').val() || generatedHtml;
		
		if (!html) {
			alert('<?php _e( 'Please generate content first.', 'woo-smart-automation' ); ?>');
			return;
		}

		var images = [];
		$('.wsa-image-row').each(function() {
			var $row = $(this);
			images.push({
				url: $row.find('input[name*="[url]"]').val(),
				attachment_id: $row.find('input[name*="[attachment_id]"]').val(),
				placement_text: $row.find('textarea').val()
			});
		});

		$.post(wsaFunnelBuilder.ajaxUrl, {
			action: 'wsa_save_landing_page',
			nonce: wsaFunnelBuilder.nonce,
			page_id: $('input[name="page_id"]').val(),
			funnel_id: $('input[name="funnel_id"]').val(),
			title: $('#page_title').val(),
			product_id: $('#product_id').val(),
			html: html,
			template: $('input[name="template"]:checked').val(),
			prompt: $('#custom_prompt').val(),
			images: images,
			status: status
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
				
				// Update page_id if new
				if (response.data.page_id) {
					$('input[name="page_id"]').val(response.data.page_id);
				}

				// Update funnel_id if auto-created
				if (response.data.funnel_id) {
					$('input[name="funnel_id"]').val(response.data.funnel_id);
				}

				if (status === 'publish' && response.data.url) {
					if (confirm('<?php _e( 'View published page?', 'woo-smart-automation' ); ?>')) {
						window.open(response.data.url, '_blank');
					}
				}
			} else {
				alert(response.data.message || '<?php _e( 'Save failed.', 'woo-smart-automation' ); ?>');
			}
		}).fail(function() {
			alert('<?php _e( 'Request failed.', 'woo-smart-automation' ); ?>');
		});
	}
});
</script>
