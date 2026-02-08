jQuery(document).ready(function($) {
	
	// Handle progress bar click
	$(document).on('click', '.wsa-risk-progress-bar', function(e) {
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();
		
		var orderId = $(this).data('order-id');
		
		// Show loading overlay
		showLoadingModal();
		
		// Make AJAX request
		$.ajax({
			url: wsaRiskScore.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_get_risk_details',
				nonce: wsaRiskScore.nonce,
				order_id: orderId
			},
			success: function(response) {
				$('.wsa-risk-modal-overlay').remove();
				if (response.success) {
					showModal(response.data.html);
				} else {
					alert('Error: ' + response.data.message);
				}
			},
			error: function() {
				$('.wsa-risk-modal-overlay').remove();
				alert('Failed to load risk details. Please try again.');
			}
		});
	});
	
	// Show loading modal
	function showLoadingModal() {
		var loadingHtml = '<div class="wsa-risk-modal-overlay">' +
			'<div class="wsa-risk-details-modal">' +
			'<div class="wsa-modal-loading">' +
			'<div class="spinner"></div>' +
			'<p>Loading risk details...</p>' +
			'</div>' +
			'</div>' +
			'</div>';
		$('body').append(loadingHtml);
	}
	
	// Show modal
	function showModal(content) {
		var modalHtml = '<div class="wsa-risk-modal-overlay">' + content + '</div>';
		$('body').append(modalHtml);
		$('body').css('overflow', 'hidden');
	}
	
	// Close modal on close button or overlay click
	$(document).on('click', '.wsa-modal-close', function(e) {
		e.stopPropagation();
		$('.wsa-risk-modal-overlay').remove();
		$('body').css('overflow', '');
	});
	
	$(document).on('click', '.wsa-risk-modal-overlay', function(e) {
		if ($(e.target).hasClass('wsa-risk-modal-overlay')) {
			$('.wsa-risk-modal-overlay').remove();
			$('body').css('overflow', '');
		}
	});
	
	// Close modal on ESC key
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && $('.wsa-risk-modal-overlay').length) {
			$('.wsa-risk-modal-overlay').remove();
			$('body').css('overflow', '');
		}
	});

	// Handle recalculate risk score button
	$(document).on('click', '.wsa-recalculate-btn', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var orderId = $btn.data('order-id');
		var originalText = $btn.text();
		
		// Disable button and show loading
		$btn.prop('disabled', true).text('Recalculating...');
		
		// Make AJAX request to recalculate
		$.ajax({
			url: wsaRiskScore.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_recalculate_risk',
				nonce: wsaRiskScore.nonce,
				order_id: orderId
			},
			success: function(response) {
				if (response.success) {
					// Close current modal and reopen with fresh data
					$('.wsa-risk-modal-overlay').remove();
					
					// Show loading
					showLoadingModal();
					
					// Re-fetch the updated modal content
					$.ajax({
						url: wsaRiskScore.ajax_url,
						type: 'POST',
						data: {
							action: 'wsa_get_risk_details',
							nonce: wsaRiskScore.nonce,
							order_id: orderId
						},
						success: function(response) {
							$('.wsa-risk-modal-overlay').remove();
							if (response.success) {
								showModal(response.data.html);
							} else {
								alert('Error: ' + response.data.message);
							}
						},
						error: function() {
							$('.wsa-risk-modal-overlay').remove();
							alert('Failed to reload risk details. Please try again.');
						}
					});
				} else {
					$btn.prop('disabled', false).text(originalText);
					alert('Error: ' + (response.data.message || 'Failed to recalculate'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text(originalText);
				alert('Failed to recalculate risk score. Please try again.');
			}
		});
	});
});
