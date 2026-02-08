/**
 * Device Block Admin JS
 * Handles block/unblock actions with confirm popups
 */
jQuery(document).ready(function($) {

	// ============================
	// BLOCK DEVICE - Order Table
	// ============================

	// Click "Block" button in order table
	$(document).on('click', '.wsa-block-device-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);
		var orderId = $btn.data('order-id');
		var phone   = $btn.data('phone');
		var email   = $btn.data('email');
		var ip      = $btn.data('ip');

		// Show loading
		$btn.prop('disabled', true).text('Loading...');

		// Fetch customer info for the popup
		$.ajax({
			url: wsaDeviceBlock.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_get_block_info',
				nonce: wsaDeviceBlock.nonce,
				order_id: orderId
			},
			success: function(response) {
				$btn.prop('disabled', false).html('🛡️ Block');

				if (response.success) {
					showBlockConfirmModal(response.data);
				} else {
					showToast(response.data.message || wsaDeviceBlock.i18n.error, 'error');
				}
			},
			error: function() {
				$btn.prop('disabled', false).html('🛡️ Block');
				showToast(wsaDeviceBlock.i18n.error, 'error');
			}
		});
	});

	// ============================
	// BLOCK CONFIRM MODAL
	// ============================

	function showBlockConfirmModal(data) {
		var html = '';
		html += '<div class="wsa-block-modal-overlay">';
		html += '  <div class="wsa-block-modal">';

		// Header
		html += '    <div class="wsa-block-modal-header">';
		html += '      <span class="wsa-block-modal-header-icon">🛡️</span>';
		html += '      <div>';
		html += '        <h3>' + wsaDeviceBlock.i18n.confirm_title + '</h3>';
		html += '        <p>Order #' + data.order_id + '</p>';
		html += '      </div>';
		html += '    </div>';

		// Body
		html += '    <div class="wsa-block-modal-body">';

		// Customer info grid
		html += '      <div class="wsa-block-customer-info">';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">Customer</span>';
		html += '          <span class="value">' + escapeHtml(data.customer_name) + '</span>';
		html += '        </div>';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">Risk Score</span>';
		html += '          <span class="value">' + escapeHtml(data.risk_score) + '</span>';
		html += '        </div>';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">Phone</span>';
		html += '          <span class="value">' + escapeHtml(data.phone || '—') + '</span>';
		html += '        </div>';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">Email</span>';
		html += '          <span class="value">' + escapeHtml(data.email || '—') + '</span>';
		html += '        </div>';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">IP Address</span>';
		html += '          <span class="value">' + escapeHtml(data.ip || '—') + '</span>';
		html += '        </div>';
		html += '        <div class="wsa-block-info-item">';
		html += '          <span class="label">Order Status</span>';
		html += '          <span class="value">' + escapeHtml(data.order_status) + '</span>';
		html += '        </div>';
		html += '      </div>';

		// Reason field
		html += '      <div class="wsa-block-reason-field">';
		html += '        <label for="wsa-block-reason">Reason for blocking (optional)</label>';
		html += '        <textarea id="wsa-block-reason" placeholder="e.g. Fake order, repeated cancellations..."></textarea>';
		html += '      </div>';

		// Warning
		html += '      <div class="wsa-block-warning">';
		html += '        <span class="wsa-block-warning-icon">⚠️</span>';
		html += '        <p>This will block the customer from placing future orders using this phone number, email address, or IP address.</p>';
		html += '      </div>';

		html += '    </div>'; // .wsa-block-modal-body

		// Footer
		html += '    <div class="wsa-block-modal-footer">';
		html += '      <button type="button" class="wsa-block-cancel-btn">' + wsaDeviceBlock.i18n.cancel_btn + '</button>';
		html += '      <button type="button" class="wsa-block-confirm-btn" data-order-id="' + data.order_id + '">🚫 ' + wsaDeviceBlock.i18n.block_btn + '</button>';
		html += '    </div>';

		html += '  </div>'; // .wsa-block-modal
		html += '</div>'; // .wsa-block-modal-overlay

		$('body').append(html);
		$('body').css('overflow', 'hidden');
	}

	// Cancel/Close block modal
	$(document).on('click', '.wsa-block-cancel-btn', function() {
		closeBlockModal();
	});

	$(document).on('click', '.wsa-block-modal-overlay', function(e) {
		if ($(e.target).hasClass('wsa-block-modal-overlay')) {
			closeBlockModal();
		}
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			if ($('.wsa-block-modal-overlay').length) {
				closeBlockModal();
			}
		}
	});

	function closeBlockModal() {
		$('.wsa-block-modal-overlay').remove();
		$('body').css('overflow', '');
	}

	// Confirm Block
	$(document).on('click', '.wsa-block-confirm-btn', function() {
		var $btn = $(this);
		var orderId = $btn.data('order-id');
		var reason  = $('#wsa-block-reason').val().trim();

		$btn.prop('disabled', true).text(wsaDeviceBlock.i18n.blocking);

		$.ajax({
			url: wsaDeviceBlock.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_block_device',
				nonce: wsaDeviceBlock.nonce,
				order_id: orderId,
				reason: reason
			},
			success: function(response) {
				closeBlockModal();

				if (response.success) {
					showToast(wsaDeviceBlock.i18n.success_blocked, 'success');

					// Update the button in the order table to show "Blocked" badge
					var $cell = $('button[data-order-id="' + orderId + '"]').closest('.wsa-device-block-cell');
					$cell.html('<span class="wsa-blocked-badge">🚫 Blocked</span>');
				} else {
					showToast(response.data.message || wsaDeviceBlock.i18n.error, 'error');
				}
			},
			error: function() {
				closeBlockModal();
				showToast(wsaDeviceBlock.i18n.error, 'error');
			}
		});
	});

	// ============================
	// UNBLOCK - Blocked Devices Page
	// ============================

	$(document).on('click', '.wsa-unblock-btn', function(e) {
		e.preventDefault();

		var $btn     = $(this);
		var blockId  = $btn.data('block-id');
		var customer = $btn.data('customer') || 'this customer';

		showUnblockConfirmModal(blockId, customer);
	});

	function showUnblockConfirmModal(blockId, customer) {
		var html = '';
		html += '<div class="wsa-block-modal-overlay">';
		html += '  <div class="wsa-unblock-modal">';

		// Header
		html += '    <div class="wsa-unblock-modal-header">';
		html += '      <span class="wsa-block-modal-header-icon">✅</span>';
		html += '      <h3>Unblock Customer?</h3>';
		html += '    </div>';

		// Body
		html += '    <div class="wsa-unblock-modal-body">';
		html += '      <p>Are you sure you want to unblock <span class="wsa-unblock-customer-name">' + escapeHtml(customer) + '</span>?</p>';
		html += '      <p>They will be able to place orders again.</p>';
		html += '    </div>';

		// Footer
		html += '    <div class="wsa-unblock-modal-footer">';
		html += '      <button type="button" class="wsa-block-cancel-btn">' + wsaDeviceBlock.i18n.cancel_btn + '</button>';
		html += '      <button type="button" class="wsa-unblock-confirm-btn" data-block-id="' + blockId + '">✅ Unblock</button>';
		html += '    </div>';

		html += '  </div>';
		html += '</div>';

		$('body').append(html);
		$('body').css('overflow', 'hidden');
	}

	// Confirm Unblock
	$(document).on('click', '.wsa-unblock-confirm-btn', function() {
		var $btn    = $(this);
		var blockId = $btn.data('block-id');

		$btn.prop('disabled', true).text(wsaDeviceBlock.i18n.unblocking);

		$.ajax({
			url: wsaDeviceBlock.ajax_url,
			type: 'POST',
			data: {
				action: 'wsa_unblock_device',
				nonce: wsaDeviceBlock.nonce,
				block_id: blockId
			},
			success: function(response) {
				closeBlockModal();

				if (response.success) {
					showToast(wsaDeviceBlock.i18n.success_unblock, 'success');

					// Reload the page after a short delay to refresh the table
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					showToast(response.data.message || wsaDeviceBlock.i18n.error, 'error');
				}
			},
			error: function() {
				closeBlockModal();
				showToast(wsaDeviceBlock.i18n.error, 'error');
			}
		});
	});

	// ============================
	// TOAST NOTIFICATION
	// ============================

	function showToast(message, type) {
		var icon = type === 'success' ? '✅' : '❌';
		var $toast = $('<div class="wsa-toast ' + type + '">' + icon + ' ' + escapeHtml(message) + '</div>');

		$('body').append($toast);

		setTimeout(function() {
			$toast.css('animation', 'wsaToastOut 0.3s ease forwards');
			setTimeout(function() {
				$toast.remove();
			}, 300);
		}, 3000);
	}

	// ============================
	// UTILITY
	// ============================

	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

});
