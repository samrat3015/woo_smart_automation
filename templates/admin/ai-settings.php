<?php
/**
 * Admin Template: AI Settings Page
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Provider icons map (used in template)
$provider_icons = [
	'gemini'      => '🌟',
	'openai'      => '🤖',
	'deepseek'    => '🔍',
	'huggingface' => '🤗',
];
?>

<div class="wrap wsa-ai-settings-page">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-cloud"></span>
		<?php _e( 'AI Settings', 'woo-smart-automation' ); ?>
	</h1>

	<div class="wsa-settings-container">
		<div class="wsa-settings-main">
			<form id="wsa-ai-settings-form" method="post">
				<?php wp_nonce_field( 'wsa_funnel_builder', 'nonce' ); ?>

				<!-- Provider Selection -->
				<div class="wsa-card">
					<h2 class="wsa-card-title">
						<span class="dashicons dashicons-admin-site-alt3"></span>
						<?php _e( 'AI Provider', 'woo-smart-automation' ); ?>
					</h2>

					<div class="wsa-provider-selector">
						<?php foreach ( $providers as $provider_key => $provider_label ) : ?>
							<label class="wsa-provider-option <?php echo $provider_key === $current_provider ? 'selected' : ''; ?>">
								<input type="radio" 
									   name="provider" 
									   value="<?php echo esc_attr( $provider_key ); ?>"
									   <?php checked( $provider_key, $current_provider ); ?>>
								<span class="wsa-provider-box">
									<span class="wsa-provider-icon">
										<?php echo $provider_icons[ $provider_key ] ?? '🔌'; ?>
									</span>
									<span class="wsa-provider-name"><?php echo esc_html( $provider_label ); ?></span>
									<?php if ( $configured_providers[ $provider_key ] ) : ?>
										<span class="wsa-provider-status configured">✓ <?php _e( 'Configured', 'woo-smart-automation' ); ?></span>
									<?php else : ?>
										<span class="wsa-provider-status not-configured"><?php _e( 'Not configured', 'woo-smart-automation' ); ?></span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- API Key Section -->
				<div class="wsa-card">
					<h2 class="wsa-card-title">
						<span class="dashicons dashicons-admin-network"></span>
						<?php _e( 'API Key', 'woo-smart-automation' ); ?>
					</h2>

					<div class="wsa-field-row">
						<label for="api_key">
							<?php _e( 'API Key', 'woo-smart-automation' ); ?>
							<span class="wsa-field-hint"><?php _e( 'Your API key is encrypted and stored securely.', 'woo-smart-automation' ); ?></span>
						</label>
						<div class="wsa-input-with-button">
							<input type="password" 
								   id="api_key" 
								   name="api_key" 
								   class="regular-text"
								   value="<?php echo esc_attr( $current_api_key ); ?>"
								   placeholder="<?php _e( 'Enter your API key...', 'woo-smart-automation' ); ?>"
								   autocomplete="new-password">
							<button type="button" class="button" id="toggle-api-key">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<p class="description" id="api-key-status">
							<?php if ( $configured_providers[ $current_provider ] ) : ?>
								<span class="wsa-status-configured">✓ <?php _e( 'API key is configured. Enter a new key to replace it.', 'woo-smart-automation' ); ?></span>
							<?php else : ?>
								<?php _e( 'No API key configured for this provider.', 'woo-smart-automation' ); ?>
							<?php endif; ?>
						</p>
					</div>

					<!-- API Key Help Links -->
					<div class="wsa-api-help">
						<strong><?php _e( 'Get your API key:', 'woo-smart-automation' ); ?></strong>
						<ul>
							<li><a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio (Gemini)</a></li>
							<li><a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></li>
							<li><a href="https://platform.deepseek.com/api_keys" target="_blank">DeepSeek Platform</a></li>
							<li><a href="https://huggingface.co/settings/tokens" target="_blank">HuggingFace Tokens</a></li>
						</ul>
					</div>
				</div>

				<!-- Model Selection -->
				<div class="wsa-card">
					<h2 class="wsa-card-title">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php _e( 'Model Selection', 'woo-smart-automation' ); ?>
					</h2>

					<div class="wsa-field-row">
						<label for="model"><?php _e( 'AI Model', 'woo-smart-automation' ); ?></label>
						<select id="model" name="model" class="regular-text">
							<?php 
							$provider_models = $models[ $current_provider ] ?? [];
							foreach ( $provider_models as $model_key => $model_data ) :
								$model_label = is_array( $model_data ) ? ( $model_data['name'] ?? $model_key ) : $model_data;
							?>
								<option value="<?php echo esc_attr( $model_key ); ?>" <?php selected( $model_key, $current_model ); ?>>
									<?php echo esc_html( $model_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php _e( 'Select the AI model to use for generating landing pages. Different models have different capabilities and costs.', 'woo-smart-automation' ); ?>
						</p>
					</div>
				</div>

				<!-- Actions -->
				<div class="wsa-actions">
					<button type="button" class="button button-secondary" id="test-connection">
						<span class="dashicons dashicons-admin-links"></span>
						<?php _e( 'Test Connection', 'woo-smart-automation' ); ?>
					</button>
					<button type="submit" class="button button-primary" id="save-settings">
						<span class="dashicons dashicons-yes"></span>
						<?php _e( 'Save Settings', 'woo-smart-automation' ); ?>
					</button>
				</div>

				<div id="wsa-test-result" class="wsa-notice" style="display: none;"></div>
			</form>
		</div>

		<div class="wsa-settings-sidebar">
			<!-- Usage Stats -->
			<div class="wsa-card">
				<h3><?php _e( 'Usage Guide', 'woo-smart-automation' ); ?></h3>
				<ol>
					<li><?php _e( 'Select your preferred AI provider', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Enter your API key', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Choose the AI model', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Test the connection', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Save your settings', 'woo-smart-automation' ); ?></li>
				</ol>
			</div>

			<!-- Provider Info -->
			<div class="wsa-card wsa-provider-info">
				<h3><?php _e( 'Provider Comparison', 'woo-smart-automation' ); ?></h3>
				<table class="wsa-comparison-table">
					<tr>
						<th><?php _e( 'Provider', 'woo-smart-automation' ); ?></th>
						<th><?php _e( 'Free Tier', 'woo-smart-automation' ); ?></th>
						<th><?php _e( 'Best For', 'woo-smart-automation' ); ?></th>
					</tr>
					<tr>
						<td>Gemini</td>
						<td>✓ Yes</td>
						<td><?php _e( 'Free usage', 'woo-smart-automation' ); ?></td>
					</tr>
					<tr>
						<td>OpenAI</td>
						<td>Limited</td>
						<td><?php _e( 'Quality', 'woo-smart-automation' ); ?></td>
					</tr>
					<tr>
						<td>DeepSeek</td>
						<td>✓ Yes</td>
						<td><?php _e( 'Value', 'woo-smart-automation' ); ?></td>
					</tr>
					<tr>
						<td>HuggingFace</td>
						<td>✓ Yes</td>
						<td><?php _e( 'Open source', 'woo-smart-automation' ); ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>



<style>
.wsa-ai-settings-page {
	max-width: 1200px;
}

.wsa-settings-container {
	display: flex;
	gap: 20px;
	margin-top: 20px;
}

.wsa-settings-main {
	flex: 1;
	min-width: 0;
}

.wsa-settings-sidebar {
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
	font-size: 16px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.wsa-provider-selector {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 15px;
}

.wsa-provider-option {
	cursor: pointer;
}

.wsa-provider-option input {
	display: none;
}

.wsa-provider-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 20px;
	border: 2px solid #ddd;
	border-radius: 8px;
	transition: all 0.2s;
	text-align: center;
}

.wsa-provider-option:hover .wsa-provider-box {
	border-color: #2271b1;
	background: #f0f6fc;
}

.wsa-provider-option.selected .wsa-provider-box,
.wsa-provider-option input:checked + .wsa-provider-box {
	border-color: #2271b1;
	background: #f0f6fc;
	box-shadow: 0 0 0 1px #2271b1;
}

.wsa-provider-icon {
	font-size: 32px;
	margin-bottom: 10px;
}

.wsa-provider-name {
	font-weight: 600;
	margin-bottom: 5px;
}

.wsa-provider-status {
	font-size: 12px;
	padding: 2px 8px;
	border-radius: 10px;
}

.wsa-provider-status.configured {
	background: #d4edda;
	color: #155724;
}

.wsa-provider-status.not-configured {
	background: #f8d7da;
	color: #721c24;
}

.wsa-field-row {
	margin-bottom: 20px;
}

.wsa-field-row label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
}

.wsa-field-hint {
	font-weight: normal;
	color: #666;
	font-size: 12px;
	display: block;
}

.wsa-input-with-button {
	display: flex;
	gap: 5px;
}

.wsa-input-with-button input {
	flex: 1;
}

.wsa-api-help {
	background: #f0f6fc;
	padding: 15px;
	border-radius: 4px;
	margin-top: 15px;
}

.wsa-api-help ul {
	margin: 10px 0 0 20px;
}

.wsa-api-help a {
	text-decoration: none;
}

.wsa-actions {
	display: flex;
	gap: 10px;
	margin-top: 20px;
}

.wsa-actions .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.wsa-notice {
	padding: 15px;
	border-radius: 4px;
	margin-top: 15px;
}

.wsa-notice.success {
	background: #d4edda;
	border: 1px solid #c3e6cb;
	color: #155724;
}

.wsa-notice.error {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	color: #721c24;
}

.wsa-status-configured {
	color: #155724;
}

.wsa-comparison-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.wsa-comparison-table th,
.wsa-comparison-table td {
	padding: 8px;
	text-align: left;
	border-bottom: 1px solid #eee;
}

.wsa-comparison-table th {
	font-weight: 600;
	background: #f9f9f9;
}

@media (max-width: 782px) {
	.wsa-settings-container {
		flex-direction: column;
	}
	
	.wsa-settings-sidebar {
		width: 100%;
	}
	
	.wsa-provider-selector {
		grid-template-columns: 1fr;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Make provider data available for funnel-builder.js
	window.wsaProviderData = {
		apiKeys: <?php echo json_encode( $provider_api_keys ); ?>,
		configured: <?php echo json_encode( $configured_providers ); ?>,
		strings: {
			keyConfigured: '<?php echo esc_js( __( 'API key is configured. Enter a new key to replace it.', 'woo-smart-automation' ) ); ?>',
			noKey: '<?php echo esc_js( __( 'No API key configured for this provider.', 'woo-smart-automation' ) ); ?>'
		}
	};
});
</script>

</div>
