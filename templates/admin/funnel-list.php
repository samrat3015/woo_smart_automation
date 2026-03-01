<?php
/**
 * Admin Template: Funnel List Page
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$funnels_data = $funnels['funnels'] ?? [];
$total = $funnels['total'] ?? 0;
?>

<div class="wrap wsa-funnel-list-page">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-filter"></span>
		<?php _e( 'AI Funnel Builder', 'woo-smart-automation' ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=create' ) ); ?>" class="page-title-action">
		<span class="dashicons dashicons-plus-alt"></span>
		<?php _e( 'New Funnel', 'woo-smart-automation' ); ?>
	</a>

	<hr class="wp-header-end">

	<?php if ( empty( $funnels_data ) ) : ?>
		<div class="wsa-empty-state">
			<div class="wsa-empty-icon">📈</div>
			<h2><?php _e( 'No Funnels Yet', 'woo-smart-automation' ); ?></h2>
			<p><?php _e( 'Create your first AI-powered sales funnel to start converting more visitors into customers.', 'woo-smart-automation' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=create' ) ); ?>" class="button button-primary button-hero">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Create Your First Funnel', 'woo-smart-automation' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wsa-funnel-grid">
			<?php foreach ( $funnels_data as $funnel ) : ?>
				<div class="wsa-funnel-card" data-funnel-id="<?php echo esc_attr( $funnel['id'] ); ?>">
					<div class="wsa-funnel-card-header">
						<h3 class="wsa-funnel-title"><?php echo esc_html( $funnel['title'] ); ?></h3>
						<span class="wsa-funnel-status <?php echo $funnel['active'] ? 'active' : 'inactive'; ?>">
							<?php echo $funnel['active'] ? __( 'Active', 'woo-smart-automation' ) : __( 'Inactive', 'woo-smart-automation' ); ?>
						</span>
					</div>

					<div class="wsa-funnel-card-body">
						<div class="wsa-funnel-stats">
							<div class="wsa-stat">
								<span class="wsa-stat-value"><?php echo count( $funnel['landing_pages'] ?? [] ); ?></span>
								<span class="wsa-stat-label"><?php _e( 'Landing Pages', 'woo-smart-automation' ); ?></span>
							</div>
							<div class="wsa-stat">
								<span class="wsa-stat-value"><?php echo count( $funnel['products'] ?? [] ); ?></span>
								<span class="wsa-stat-label"><?php _e( 'Products', 'woo-smart-automation' ); ?></span>
							</div>
						</div>

						<?php if ( ! empty( $funnel['landing_pages'] ) ) : ?>
							<div class="wsa-funnel-pages">
								<strong><?php _e( 'Pages:', 'woo-smart-automation' ); ?></strong>
								<ul>
									<?php foreach ( array_slice( $funnel['landing_pages'], 0, 3 ) as $page ) : ?>
										<li>
											<a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank">
												<?php echo esc_html( $page['title'] ); ?>
											</a>
											<span class="wsa-page-status <?php echo $page['status']; ?>">
												<?php echo $page['status'] === 'publish' ? '✓' : '📝'; ?>
											</span>
										</li>
									<?php endforeach; ?>
									<?php if ( count( $funnel['landing_pages'] ) > 3 ) : ?>
										<li class="wsa-more">+<?php echo count( $funnel['landing_pages'] ) - 3; ?> more</li>
									<?php endif; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>

					<div class="wsa-funnel-card-footer">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=edit&id=' . $funnel['id'] ) ); ?>" class="button">
							<span class="dashicons dashicons-edit"></span>
							<?php _e( 'Edit', 'woo-smart-automation' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsa-funnel-builder&action=landing-page&funnel_id=' . $funnel['id'] ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-plus"></span>
							<?php _e( 'Add Page', 'woo-smart-automation' ); ?>
						</a>
						<div class="wsa-funnel-actions-menu">
							<button class="button wsa-actions-toggle">
								<span class="dashicons dashicons-ellipsis"></span>
							</button>
							<div class="wsa-actions-dropdown">
								<a href="#" class="wsa-action-duplicate" data-funnel-id="<?php echo esc_attr( $funnel['id'] ); ?>">
									<span class="dashicons dashicons-admin-page"></span>
									<?php _e( 'Duplicate', 'woo-smart-automation' ); ?>
								</a>
								<a href="#" class="wsa-action-delete" data-funnel-id="<?php echo esc_attr( $funnel['id'] ); ?>">
									<span class="dashicons dashicons-trash"></span>
									<?php _e( 'Delete', 'woo-smart-automation' ); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<style>
.wsa-funnel-list-page .page-title-action {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.wsa-empty-state {
	text-align: center;
	padding: 60px 20px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	margin-top: 20px;
}

.wsa-empty-icon {
	font-size: 64px;
	margin-bottom: 20px;
}

.wsa-empty-state h2 {
	margin: 0 0 10px;
}

.wsa-empty-state p {
	color: #666;
	margin-bottom: 20px;
}

.wsa-funnel-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.wsa-funnel-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	overflow: hidden;
	transition: box-shadow 0.2s;
}

.wsa-funnel-card:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.wsa-funnel-card-header {
	padding: 20px;
	border-bottom: 1px solid #eee;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.wsa-funnel-title {
	margin: 0;
	font-size: 16px;
}

.wsa-funnel-status {
	padding: 3px 10px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 500;
}

.wsa-funnel-status.active {
	background: #d4edda;
	color: #155724;
}

.wsa-funnel-status.inactive {
	background: #f8d7da;
	color: #721c24;
}

.wsa-funnel-card-body {
	padding: 20px;
}

.wsa-funnel-stats {
	display: flex;
	gap: 30px;
	margin-bottom: 15px;
}

.wsa-stat {
	text-align: center;
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

.wsa-funnel-pages {
	background: #f9f9f9;
	padding: 10px;
	border-radius: 4px;
	font-size: 13px;
}

.wsa-funnel-pages ul {
	margin: 5px 0 0 20px;
}

.wsa-funnel-pages li {
	display: flex;
	justify-content: space-between;
	margin-bottom: 5px;
}

.wsa-funnel-pages .wsa-page-status {
	font-size: 11px;
}

.wsa-funnel-pages .wsa-more {
	color: #666;
	font-style: italic;
}

.wsa-funnel-card-footer {
	padding: 15px 20px;
	background: #f9f9f9;
	display: flex;
	gap: 10px;
	align-items: center;
}

.wsa-funnel-card-footer .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.wsa-funnel-actions-menu {
	margin-left: auto;
	position: relative;
}

.wsa-actions-toggle {
	padding: 5px 8px !important;
}

.wsa-actions-dropdown {
	display: none;
	position: absolute;
	right: 0;
	top: 100%;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
	min-width: 150px;
	z-index: 100;
}

.wsa-actions-menu:hover .wsa-actions-dropdown,
.wsa-actions-dropdown:hover {
	display: block;
}

.wsa-actions-dropdown a {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 15px;
	text-decoration: none;
	color: #333;
}

.wsa-actions-dropdown a:hover {
	background: #f0f0f0;
}

.wsa-action-delete {
	color: #dc3545 !important;
}

@media (max-width: 782px) {
	.wsa-funnel-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Duplicate funnel
	$('.wsa-action-duplicate').on('click', function(e) {
		e.preventDefault();
		var funnelId = $(this).data('funnel-id');
		
		if (!confirm(wsaFunnelBuilder.i18n.confirmDelete || 'Are you sure?')) {
			return;
		}
		
		$.post(wsaFunnelBuilder.ajaxUrl, {
			action: 'wsa_duplicate_funnel',
			nonce: wsaFunnelBuilder.nonce,
			funnel_id: funnelId
		}, function(response) {
			if (response.success) {
				window.location.href = response.data.redirect;
			} else {
				alert(response.data.message);
			}
		});
	});
	
	// Delete funnel
	$('.wsa-action-delete').on('click', function(e) {
		e.preventDefault();
		var funnelId = $(this).data('funnel-id');
		var $card = $(this).closest('.wsa-funnel-card');
		
		if (!confirm(wsaFunnelBuilder.i18n.confirmDelete || 'Are you sure you want to delete this funnel?')) {
			return;
		}
		
		$.post(wsaFunnelBuilder.ajaxUrl, {
			action: 'wsa_delete_funnel',
			nonce: wsaFunnelBuilder.nonce,
			funnel_id: funnelId
		}, function(response) {
			if (response.success) {
				$card.fadeOut(300, function() {
					$(this).remove();
				});
			} else {
				alert(response.data.message);
			}
		});
	});
});
</script>
