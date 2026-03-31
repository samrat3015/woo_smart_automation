jQuery(document).ready(function ($) {

	// ── In-memory cache: keyed by order ID → rendered HTML string ──────────
	// Prevents repeated AJAX calls every time the modal is opened.
	// Cache is cleared for an order after a successful recalculate.
	var wsaModalCache = {};

	// ── Open Risk Analysis Modal ────────────────────────────────────────────
	$(document).on('click', '.wsa-risk-progress-bar', function (e) {
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();

		var orderId = $(this).data('order-id');

		// Remove any existing modal first
		$('.wsa-risk-modal-overlay').remove();

		// ── Serve from cache instantly if available ─────────────────────────
		if (wsaModalCache[orderId]) {
			showModal(wsaModalCache[orderId]);
			return;
		}

		// ── First open: show loader, then fetch ─────────────────────────────
		showLoadingModal(orderId);

		$.ajax({
			url: wsaRiskScore.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_get_risk_details',
				nonce: wsaRiskScore.nonce,
				order_id: orderId
			},
			success: function (response) {
				$('.wsa-risk-modal-overlay').remove();
				if (response.success) {
					// Store in cache for future clicks
					wsaModalCache[orderId] = response.data.html;
					showModal(response.data.html);
				} else {
					alert('Error: ' + response.data.message);
				}
			},
			error: function () {
				$('.wsa-risk-modal-overlay').remove();
				alert('Failed to load risk details. Please try again.');
			}
		});
	});

	// ── Recheck button in the table column ─────────────────────────────────
	$(document).on('click', '.wsa-recheck-risk-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);
		var orderId = $btn.data('order-id');

		$btn.addClass('rotating');

		// Invalidate cache so modal shows fresh data after reload
		delete wsaModalCache[orderId];

		$.ajax({
			url: wsaRiskScore.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_recalculate_risk',
				nonce: wsaRiskScore.nonce,
				order_id: orderId
			},
			success: function (response) {
				if (response.success) {
					location.reload();
				} else {
					$btn.removeClass('rotating');
					alert('Error: ' + response.data.message);
				}
			},
			error: function () {
				$btn.removeClass('rotating');
				alert('Connection error.');
			}
		});
	});

	// ── Recalculate button inside the modal ─────────────────────────────────
	$(document).on('click', '.wsa-recalculate-btn', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var orderId = $btn.data('order-id');
		var originalText = $btn.text();

		$btn.prop('disabled', true).html('<span>⏳</span> Recalculating...');

		$.ajax({
			url: wsaRiskScore.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_recalculate_risk',
				nonce: wsaRiskScore.nonce,
				order_id: orderId
			},
			success: function (response) {
				if (response.success) {
					// Invalidate cache for this order so next open is fresh
					delete wsaModalCache[orderId];

					// Close current modal, show loader, re-fetch fresh HTML
					$('.wsa-risk-modal-overlay').remove();
					showLoadingModal(orderId);

					$.ajax({
						url: wsaRiskScore.ajax_url,
						type: 'POST',
						data: {
							action: 'wsa_get_risk_details',
							nonce: wsaRiskScore.nonce,
							order_id: orderId
						},
						success: function (r) {
							$('.wsa-risk-modal-overlay').remove();
							if (r.success) {
								// Update cache with fresh data
								wsaModalCache[orderId] = r.data.html;
								showModal(r.data.html);
							} else {
								alert('Error: ' + r.data.message);
							}
						},
						error: function () {
							$('.wsa-risk-modal-overlay').remove();
							alert('Failed to reload risk details. Please try again.');
						}
					});
				} else {
					$btn.prop('disabled', false).html('<span>⚡</span> Refresh');
					alert('Error: ' + (response.data.message || 'Failed to recalculate'));
				}
			},
			error: function () {
				$btn.prop('disabled', false).html('<span>⚡</span> Refresh');
				alert('Failed to recalculate risk score. Please try again.');
			}
		});
	});

	// ── Helpers ─────────────────────────────────────────────────────────────

	function showLoadingModal(orderId) {
		var html =
			'<div class="wsa-risk-modal-overlay">' +
			'<div class="wsa-risk-details-modal wsa-v2">' +
			'<div class="wsa-modal-loading-container">' +
			'<div class="wsa-spinner-ring"></div>' +
			'<p>Fetching Risk Intelligence...</p>' +
			'<small>Order #' + (orderId || '') + '</small>' +
			'</div>' +
			'</div>' +
			'</div>';
		$('body').append(html);
		$('body').css('overflow', 'hidden');
	}

	function showModal(content) {
		var html = '<div class="wsa-risk-modal-overlay">' + content + '</div>';
		$('body').append(html);
		$('body').css('overflow', 'hidden');
	}

	// ── Close modal ─────────────────────────────────────────────────────────
	$(document).on('click', '.wsa-modal-close', function (e) {
		e.stopPropagation();
		$('.wsa-risk-modal-overlay').remove();
		$('body').css('overflow', '');
	});

	$(document).on('click', '.wsa-risk-modal-overlay', function (e) {
		if ($(e.target).hasClass('wsa-risk-modal-overlay')) {
			$('.wsa-risk-modal-overlay').remove();
			$('body').css('overflow', '');
		}
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $('.wsa-risk-modal-overlay').length) {
			$('.wsa-risk-modal-overlay').remove();
			$('body').css('overflow', '');
		}
	});
});
