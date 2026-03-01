/**
 * AI Funnel Builder Admin JavaScript
 *
 * @package WooSmartAutomation
 * @since 1.1.0
 */

(function($) {
    'use strict';

    // Global namespace
    window.WSAFunnelBuilder = window.WSAFunnelBuilder || {};

    /**
     * AI Settings Page Controller
     */
    WSAFunnelBuilder.AISettings = {
        init: function() {
            this.bindProviderSelection();
            this.bindVisibilityToggle();
            this.bindTestConnection();
            this.bindSaveSettings();
        },

        bindProviderSelection: function() {
            $('.wsa-provider-option').on('click', function() {
                var $card = $(this);
                var $radio = $card.find('input[type="radio"]');
                
                $('.wsa-provider-option').removeClass('selected');
                $card.addClass('selected');
                $radio.prop('checked', true).trigger('change');
                
                var provider = $radio.val();

                // Update model dropdown based on provider
                WSAFunnelBuilder.AISettings.updateModelDropdown(provider);

                // Swap API key for this provider
                if (window.wsaProviderData) {
                    var key = wsaProviderData.apiKeys[provider] || '';
                    $('#api_key').val(key);

                    // Update API key status text
                    var $status = $('#api-key-status');
                    if (wsaProviderData.configured[provider]) {
                        $status.html('<span class="wsa-status-configured">\u2713 ' + wsaProviderData.strings.keyConfigured + '</span>');
                    } else {
                        $status.text(wsaProviderData.strings.noKey);
                    }
                }
            });
        },

        bindVisibilityToggle: function() {
            $('#toggle-api-key').on('click', function() {
                var $input = $('#api_key');
                var type = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
                $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
            });
        },

        updateModelDropdown: function(provider) {
            var models = wsaFunnelBuilder.models[provider] || {};
            var $select = $('#model');
            
            $select.empty();
            if (Array.isArray(models)) {
                // Flat array format: ['model-id-1', 'model-id-2']
                models.forEach(function(model) {
                    $select.append('<option value="' + model + '">' + model + '</option>');
                });
            } else {
                // Object format: {model_id: 'Model Name'} or {model_id: {name: 'Model Name'}}
                $.each(models, function(key, value) {
                    var label = (typeof value === 'object' && value.name) ? value.name : (typeof value === 'string' ? value : key);
                    $select.append('<option value="' + key + '">' + label + '</option>');
                });
            }
        },

        bindTestConnection: function() {
            $('#test-connection').on('click', function() {
                var $btn = $(this);
                var $result = $('#wsa-test-result');
                
                $btn.prop('disabled', true).text(wsaFunnelBuilder.strings.testing || 'Testing...');
                $result.hide();
                
                $.post(wsaFunnelBuilder.ajaxUrl, {
                    action: 'wsa_test_ai_connection',
                    nonce: wsaFunnelBuilder.nonce,
                    provider: $('input[name="provider"]:checked').val(),
                    api_key: $('#api_key').val(),
                    model: $('#model').val()
                }, function(response) {
                    if (response.success) {
                        $result.removeClass('error').addClass('success').text(response.data.message).show();
                    } else {
                        $result.removeClass('success').addClass('error').text(response.data.message).show();
                    }
                }).fail(function() {
                    $result.removeClass('success').addClass('error').text('Connection failed').show();
                }).always(function() {
                    $btn.prop('disabled', false).text(wsaFunnelBuilder.strings.test || 'Test Connection');
                });
            });
        },

        bindSaveSettings: function() {
            $('#wsa-ai-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                
                $btn.prop('disabled', true);
                
                $.post(wsaFunnelBuilder.ajaxUrl, {
                    action: 'wsa_save_ai_settings',
                    nonce: wsaFunnelBuilder.nonce,
                    provider: $('input[name="provider"]:checked').val(),
                    api_key: $('#api_key').val(),
                    model: $('#model').val()
                }, function(response) {
                    var $notice = $('#wsa-test-result');
                    if (response.success) {
                        $notice.removeClass('error').addClass('success').text('\u2713 ' + response.data.message).show();

                        // Update local provider data cache after successful save
                        var savedProvider = $('input[name="provider"]:checked').val();
                        var savedKey = $('#api_key').val();
                        if (window.wsaProviderData && savedProvider) {
                            if (savedKey) {
                                wsaProviderData.apiKeys[savedProvider] = savedKey;
                                wsaProviderData.configured[savedProvider] = true;
                            }
                            // Update status text
                            var $status = $('#api-key-status');
                            $status.html('<span class="wsa-status-configured">\u2713 ' + wsaProviderData.strings.keyConfigured + '</span>');
                        }
                    } else {
                        $notice.removeClass('success').addClass('error').text('\u2717 ' + response.data.message).show();
                    }
                }).fail(function(jqXHR) {
                    var $notice = $('#wsa-test-result');
                    var errorMsg = 'Request failed';
                    try {
                        var resp = JSON.parse(jqXHR.responseText);
                        if (resp.data && resp.data.message) errorMsg = resp.data.message;
                    } catch(e) {}
                    $notice.removeClass('success').addClass('error').text('\u2717 ' + errorMsg).show();
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        }
    };

    /**
     * Funnel List Page Controller
     */
    WSAFunnelBuilder.FunnelList = {
        init: function() {
            this.bindDropdownMenus();
            this.bindDuplicateFunnel();
            this.bindDeleteFunnel();
        },

        bindDropdownMenus: function() {
            // Toggle dropdown
            $(document).on('click', '.wsa-more-actions', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $menu = $(this).siblings('.wsa-dropdown-menu');
                $('.wsa-dropdown-menu').not($menu).removeClass('show');
                $menu.toggleClass('show');
            });
            
            // Close on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wsa-actions-dropdown').length) {
                    $('.wsa-dropdown-menu').removeClass('show');
                }
            });
        },

        bindDuplicateFunnel: function() {
            $(document).on('click', '.wsa-duplicate-funnel', function(e) {
                e.preventDefault();
                
                var funnelId = $(this).data('funnel-id');
                
                if (!confirm(wsaFunnelBuilder.strings.confirmDuplicate || 'Duplicate this funnel?')) {
                    return;
                }
                
                $.post(wsaFunnelBuilder.ajaxUrl, {
                    action: 'wsa_duplicate_funnel',
                    nonce: wsaFunnelBuilder.nonce,
                    funnel_id: funnelId
                }, function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Error duplicating funnel');
                    }
                });
            });
        },

        bindDeleteFunnel: function() {
            $(document).on('click', '.wsa-delete-funnel', function(e) {
                e.preventDefault();
                
                var funnelId = $(this).data('funnel-id');
                var $card = $(this).closest('.wsa-funnel-card');
                
                if (!confirm(wsaFunnelBuilder.strings.confirmDelete || 'Are you sure you want to delete this funnel?')) {
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
                            // Show empty state if no funnels
                            if ($('.wsa-funnel-card').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message || 'Error deleting funnel');
                    }
                });
            });
        }
    };

    /**
     * Landing Page Builder Controller
     */
    WSAFunnelBuilder.LandingPageBuilder = {
        currentDevice: 'desktop',
        currentHtml: '',
        
        init: function() {
            this.bindTemplateSelection();
            this.bindImageUpload();
            this.bindDeviceSwitcher();
            this.bindCodeEditorToggle();
            this.bindGenerateButton();
            this.bindSaveActions();
        },

        bindTemplateSelection: function() {
            $('.wsa-template-card').on('click', function() {
                $('.wsa-template-card').removeClass('selected');
                $(this).addClass('selected');
                $('input[name="template_id"]').val($(this).data('template-id'));
            });
        },

        bindImageUpload: function() {
            var self = this;
            var $zone = $('.wsa-image-upload-zone');
            var $container = $('.wsa-uploaded-images');
            
            // Click to upload
            $zone.on('click', function() {
                var mediaUploader = wp.media({
                    title: 'Select Images',
                    button: { text: 'Use Images' },
                    multiple: true
                });
                
                mediaUploader.on('select', function() {
                    var attachments = mediaUploader.state().get('selection').toJSON();
                    attachments.forEach(function(attachment) {
                        self.addUploadedImage(attachment);
                    });
                });
                
                mediaUploader.open();
            });
            
            // Drag and drop
            $zone.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave', function() {
                $(this).removeClass('dragover');
            }).on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                self.uploadFiles(files);
            });
            
            // Remove image
            $(document).on('click', '.wsa-uploaded-image button', function() {
                $(this).closest('.wsa-uploaded-image').remove();
            });
        },

        addUploadedImage: function(attachment) {
            var html = '<div class="wsa-uploaded-image" data-id="' + attachment.id + '">' +
                       '<img src="' + attachment.sizes.thumbnail.url + '" alt="">' +
                       '<button type="button" class="wsa-remove-image">&times;</button>' +
                       '<input type="hidden" name="images[]" value="' + attachment.id + '">' +
                       '</div>';
            
            $('.wsa-uploaded-images').append(html);
        },

        uploadFiles: function(files) {
            var self = this;
            
            Array.from(files).forEach(function(file) {
                if (!file.type.startsWith('image/')) return;
                
                var formData = new FormData();
                formData.append('action', 'wsa_upload_image');
                formData.append('nonce', wsaFunnelBuilder.nonce);
                formData.append('image', file);
                
                $.ajax({
                    url: wsaFunnelBuilder.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            self.addUploadedImage(response.data);
                        }
                    }
                });
            });
        },

        bindDeviceSwitcher: function() {
            var self = this;
            
            $('.wsa-device-btn').on('click', function() {
                var device = $(this).data('device');
                
                $('.wsa-device-btn').removeClass('active');
                $(this).addClass('active');
                
                self.currentDevice = device;
                self.updatePreviewDevice(device);
            });
        },

        updatePreviewDevice: function(device) {
            var $preview = $('.wsa-preview-device');
            
            $preview.removeClass('desktop tablet mobile').addClass(device);
        },

        bindCodeEditorToggle: function() {
            var self = this;
            
            $('.wsa-toggle-code').on('click', function() {
                $(this).toggleClass('active');
                
                if ($(this).hasClass('active')) {
                    // Show code editor
                    $('.wsa-preview-frame').hide();
                    $('.wsa-code-editor').addClass('active').show();
                    $('.wsa-code-editor textarea').val(self.currentHtml);
                } else {
                    // Show preview
                    $('.wsa-code-editor').removeClass('active').hide();
                    $('.wsa-preview-frame').show();
                    
                    // Update preview with edited code
                    self.currentHtml = $('.wsa-code-editor textarea').val();
                    self.updatePreview(self.currentHtml);
                }
            });
        },

        bindGenerateButton: function() {
            var self = this;
            
            $('#wsa-generate-landing-page').on('click', function() {
                var $btn = $(this);
                var productId = $('#wsa_product_id').val();
                var templateId = $('input[name="template_id"]:checked, .wsa-template-card.selected').data('template-id') || 'bd-health-product';
                var images = [];
                var imagePlacement = $('#wsa_image_placement').val();
                
                // Collect uploaded images
                $('.wsa-uploaded-image').each(function() {
                    images.push($(this).data('id'));
                });
                
                if (!productId) {
                    alert(wsaFunnelBuilder.strings.selectProduct || 'Please select a product');
                    return;
                }
                
                // Disable button and show loading
                $btn.prop('disabled', true);
                $btn.find('.wsa-btn-text').text(wsaFunnelBuilder.strings.generating || 'Generating...');
                $btn.find('.dashicons').addClass('dashicons-update').removeClass('dashicons-randomize');
                
                $.post(wsaFunnelBuilder.ajaxUrl, {
                    action: 'wsa_generate_landing_page',
                    nonce: wsaFunnelBuilder.nonce,
                    product_id: productId,
                    template_id: templateId,
                    images: images,
                    image_placement: imagePlacement
                }, function(response) {
                    if (response.success) {
                        self.currentHtml = response.data.html;
                        self.updatePreview(response.data.html);
                    } else {
                        alert(response.data.message || 'Error generating landing page');
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                }).always(function() {
                    $btn.prop('disabled', false);
                    $btn.find('.wsa-btn-text').text(wsaFunnelBuilder.strings.generate || 'Generate with AI');
                    $btn.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-randomize');
                });
            });
        },

        updatePreview: function(html) {
            var $preview = $('.wsa-preview-content');
            
            if ($preview.is('iframe')) {
                var doc = $preview[0].contentDocument || $preview[0].contentWindow.document;
                doc.open();
                doc.write(html);
                doc.close();
            } else {
                $preview.html(html);
            }
        },

        bindSaveActions: function() {
            var self = this;
            
            // Save as Draft
            $('#wsa-save-draft').on('click', function() {
                self.saveLandingPage('draft');
            });
            
            // Publish
            $('#wsa-publish').on('click', function() {
                self.saveLandingPage('publish');
            });
        },

        saveLandingPage: function(status) {
            var self = this;
            var funnelId = $('#wsa_funnel_id').val();
            var pageId = $('#wsa_page_id').val();
            var title = $('#wsa_page_title').val();
            
            if (!title) {
                alert(wsaFunnelBuilder.strings.enterTitle || 'Please enter a title');
                return;
            }
            
            if (!self.currentHtml) {
                alert(wsaFunnelBuilder.strings.generateFirst || 'Please generate a landing page first');
                return;
            }
            
            $.post(wsaFunnelBuilder.ajaxUrl, {
                action: 'wsa_save_landing_page',
                nonce: wsaFunnelBuilder.nonce,
                funnel_id: funnelId,
                page_id: pageId,
                title: title,
                html: self.currentHtml,
                status: status,
                product_id: $('#wsa_product_id').val()
            }, function(response) {
                if (response.success) {
                    if (status === 'publish' && response.data.url) {
                        if (confirm((wsaFunnelBuilder.strings.published || 'Published!') + ' ' + (wsaFunnelBuilder.strings.viewPage || 'View page?'))) {
                            window.open(response.data.url, '_blank');
                        }
                    } else {
                        alert(response.data.message || 'Saved!');
                    }
                    
                    // Update page ID if new
                    if (response.data.page_id) {
                        $('#wsa_page_id').val(response.data.page_id);
                    }
                } else {
                    alert(response.data.message || 'Error saving');
                }
            }).fail(function() {
                alert('Request failed');
            });
        }
    };

    /**
     * Checkout Form Builder
     */
    WSAFunnelBuilder.CheckoutForm = {
        init: function() {
            this.bindFieldSorting();
            this.bindFieldToggle();
            this.bindFieldRequiredToggle();
        },

        bindFieldSorting: function() {
            if ($.fn.sortable) {
                $('.wsa-checkout-fields-list').sortable({
                    handle: '.wsa-field-handle',
                    update: function() {
                        WSAFunnelBuilder.CheckoutForm.updateFieldOrder();
                    }
                });
            }
        },

        bindFieldToggle: function() {
            $(document).on('change', '.wsa-field-visible', function() {
                var $item = $(this).closest('.wsa-field-item');
                $item.toggleClass('disabled', !this.checked);
            });
        },

        bindFieldRequiredToggle: function() {
            $(document).on('change', '.wsa-field-required', function() {
                var $item = $(this).closest('.wsa-field-item');
                var $label = $item.find('.wsa-field-label');
                
                if (this.checked) {
                    $label.append('<span class="required">*</span>');
                } else {
                    $label.find('.required').remove();
                }
            });
        },

        updateFieldOrder: function() {
            var order = [];
            $('.wsa-checkout-fields-list .wsa-field-item').each(function() {
                order.push($(this).data('field-id'));
            });
            
            $('input[name="field_order"]').val(JSON.stringify(order));
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Detect current page and initialize appropriate controller
        if ($('.wsa-ai-settings-page').length) {
            WSAFunnelBuilder.AISettings.init();
        }
        
        if ($('.wsa-funnel-list-page').length) {
            WSAFunnelBuilder.FunnelList.init();
        }
        
        if ($('.wsa-landing-page-builder').length) {
            WSAFunnelBuilder.LandingPageBuilder.init();
        }
        
        if ($('.wsa-funnel-edit-page').length) {
            WSAFunnelBuilder.CheckoutForm.init();
        }
    });

})(jQuery);
