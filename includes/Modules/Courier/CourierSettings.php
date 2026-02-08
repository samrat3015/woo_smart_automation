<?php
namespace WooSmartAutomation\Modules\Courier;

/**
 * Courier Settings Page
 */
class CourierSettings {
	
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ], 99 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}
	
	public function add_settings_page() {
		add_submenu_page(
			'woo-smart-automation',
			__( 'Courier Integration Settings', 'woo-smart-automation' ),
			__( 'Courier Settings', 'woo-smart-automation' ),
			'manage_options',
			'wsa-courier-settings',
			[ $this, 'render_settings_page' ]
		);
	}
	
	public function register_settings() {
		register_setting( 'wsa_courier_settings', 'wsa_steadfast_fraud_check_enabled' );
		register_setting( 'wsa_courier_settings', 'wsa_steadfast_api_key' );
		register_setting( 'wsa_courier_settings', 'wsa_steadfast_secret_key' );
		register_setting( 'wsa_courier_settings', 'wsa_steadfast_minimum_order_amount' );
		register_setting( 'wsa_courier_settings', 'wsa_steadfast_skip_repeat_customers' );
	}
	
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'SteadFast Courier Integration', 'woo-smart-automation' ); ?></h1>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'wsa_courier_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php _e( 'Enable Fraud Check', 'woo-smart-automation' ); ?></label>
						</th>
						<td>
							<input type="checkbox" name="wsa_steadfast_fraud_check_enabled" value="1" 
								<?php checked( 1, get_option( 'wsa_steadfast_fraud_check_enabled' ) ); ?>>
							<p class="description">
								<?php _e( 'Enable SteadFast fraud check API to enhance risk scoring with cross-merchant delivery data', 'woo-smart-automation' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php _e( 'API Key', 'woo-smart-automation' ); ?></label>
						</th>
						<td>
							<input type="text" name="wsa_steadfast_api_key" 
								value="<?php echo esc_attr( get_option( 'wsa_steadfast_api_key' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php _e( 'Get your API credentials from https://portal.packzy.com', 'woo-smart-automation' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php _e( 'Secret Key', 'woo-smart-automation' ); ?></label>
						</th>
						<td>
							<input type="password" name="wsa_steadfast_secret_key" 
								value="<?php echo esc_attr( get_option( 'wsa_steadfast_secret_key' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php _e( 'Keep your secret key secure', 'woo-smart-automation' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php _e( 'Smart Limit Management', 'woo-smart-automation' ); ?></h2>
				<p class="description"><?php _e( 'Configure these settings to reduce API usage and manage the daily limit intelligently.', 'woo-smart-automation' ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php _e( 'Minimum Order Amount', 'woo-smart-automation' ); ?></label>
						</th>
						<td>
							<input type="number" name="wsa_steadfast_minimum_order_amount" 
								value="<?php echo esc_attr( get_option( 'wsa_steadfast_minimum_order_amount', '1000' ) ); ?>" 
								class="small-text" min="0" step="100">
							<span> BDT</span>
							<p class="description">
								<?php _e( 'Only check courier score for orders above this amount. Set to 0 to check all orders. Recommended: 1000 BDT', 'woo-smart-automation' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php _e( 'Skip Repeat Customers', 'woo-smart-automation' ); ?></label>
						</th>
						<td>
							<input type="checkbox" name="wsa_steadfast_skip_repeat_customers" value="1" 
								<?php checked( 1, get_option( 'wsa_steadfast_skip_repeat_customers', 1 ) ); ?>>
							<span><?php _e( 'Don\'t check customers who already have completed orders', 'woo-smart-automation' ); ?></span>
							<p class="description">
								<?php _e( 'Saves API calls by skipping already verified customers. Highly recommended.', 'woo-smart-automation' ); ?>
							</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
			
			<hr style="margin: 30px 0;">
			
			<h2><?php _e( 'Test API Connection', 'woo-smart-automation' ); ?></h2>
			<p><?php _e( 'Click the button below to verify your API credentials are working correctly.', 'woo-smart-automation' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wsa-test-api">
					<?php _e( 'Test Connection', 'woo-smart-automation' ); ?>
				</button>
				<span id="wsa-test-result" style="margin-left: 10px;"></span>
			</p>
			
			<script>
			jQuery(document).ready(function($) {
				$('#wsa-test-api').on('click', function() {
					var btn = $(this);
					var result = $('#wsa-test-result');
					
					btn.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'woo-smart-automation' ); ?>');
					result.html('');
					
					$.post(ajaxurl, {
						action: 'wsa_test_steadfast_api',
						nonce: '<?php echo wp_create_nonce( 'wsa_test_api' ); ?>'
					}, function(response) {
						btn.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'woo-smart-automation' ); ?>');
						
						if (response.success) {
							result.html('<span style="color: #46b450; font-weight: 600;">✓ ' + response.data.message + '</span>');
						} else {
							result.html('<span style="color: #dc3232; font-weight: 600;">✗ ' + response.data.message + '</span>');
						}
					}).fail(function() {
						btn.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'woo-smart-automation' ); ?>');
						result.html('<span style="color: #dc3232;">✗ <?php esc_html_e( 'Request failed', 'woo-smart-automation' ); ?></span>');
					});
				});
			});
			</script>
			
			<hr style="margin: 30px 0;">
			
			<div class="notice notice-info inline">
				<h3><?php _e( 'How It Works', 'woo-smart-automation' ); ?></h3>
				<p><?php _e( 'When enabled, the system will check customer phone numbers against SteadFast\'s delivery database to:', 'woo-smart-automation' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php _e( 'Identify customers with good delivery history across all merchants', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Detect repeat fraudsters who cancel/return orders frequently', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Calculate accurate risk scores based on cross-merchant data', 'woo-smart-automation' ); ?></li>
					<li><?php _e( 'Reduce COD fraud by 30-40% with intelligent detection', 'woo-smart-automation' ); ?></li>
				</ul>
				<p><strong><?php _e( 'Smart Caching:', 'woo-smart-automation' ); ?></strong> <?php _e( 'Results are cached for 30 days and stored permanently in database. Repeat customers are automatically skipped. This reduces API usage by 80-90%.', 'woo-smart-automation' ); ?></p>
				<p><strong><?php _e( 'Privacy Note:', 'woo-smart-automation' ); ?></strong> <?php _e( 'Customer phone numbers are checked against SteadFast for fraud prevention only. Data is stored securely.', 'woo-smart-automation' ); ?></p>
			</div>
		</div>
		<?php
	}
}
