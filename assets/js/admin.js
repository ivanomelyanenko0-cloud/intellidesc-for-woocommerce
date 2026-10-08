jQuery(document).ready(function($) {

    // ==========================================
    // 1. SINGLE PRODUCT GENERATION
    // ==========================================
    $(document).on('click', '#ildesc-trigger-btn, #ildesc-autocomplete-btn', function(e) {
        e.preventDefault();

        var $btn    = $(this);
        var $output = $('#ildesc-status-message').length ? $('#ildesc-status-message') : $('#ildesc-message');
        var $loader = $('#ildesc-loader');

        var productTitle = $('#title').val();
        if (!productTitle) {
            alert(ildesc_params.no_title);
            return;
        }

        var productId        = $('#post_ID').val();
        var seoKeyword       = $('#ildesc-seo-keyword').val();
        var currentExcerpt   = getEditorText('excerpt');
        var currentContent   = getEditorText('content');
        var uiProductType    = $('#product-type').val() || 'simple';
        var uiIsVirtual      = $('#_virtual').is(':checked') ? 1 : 0;
        var uiIsDownloadable = $('#_downloadable').is(':checked') ? 1 : 0;

        var existingFeatures = [];
        $('.ildesc-feature-row').each(function() {
            var fName = $(this).find('input[name*="[name]"]').val().trim();
            var fVal  = $(this).find('input[name*="[value]"]').val().trim();
            if (fName || fVal) existingFeatures.push(fName + ': ' + fVal);
        });

        // Loading state
        $btn.prop('disabled', true);
        setBtnText($btn, ildesc_params.btn_loading);
        $output.removeClass('ildesc-msg-success ildesc-msg-error').html('');
        $loader.addClass('ildesc-visible').find('#ildesc-loader-text').text(ildesc_params.loading_text);

        $.ajax({
            url:      ildesc_params.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:             'ildesc_autocomplete_features',
                product_id:         productId,
                product_title:      productTitle,
                seo_keyword:        seoKeyword,
                current_excerpt:    currentExcerpt,
                current_content:    currentContent,
                existing_features:  existingFeatures.join(' | '),
                product_type_ui:    uiProductType,
                is_virtual_ui:      uiIsVirtual,
                is_downloadable_ui: uiIsDownloadable,
                nonce:              ildesc_params.nonce
            },
            success: function(response) {
                applyContentResponse(response, $output, $btn);
            },
            error: function(xhr, status, error) {
                showStatus($output, 'error', ildesc_params.server_error + ' ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false);
                setBtnText($btn, ildesc_params.btn_default);
                $loader.removeClass('ildesc-visible');
            }
        });
    });

    // ==========================================
    // 2. UI HELPERS
    // ==========================================

    $(document).on('click', '.ildesc-remove-feature', function() {
        $(this).closest('.ildesc-feature-row').fadeOut(150, function() { $(this).remove(); });
    });

    $(document).on('click', '.ildesc-remove-template', function() {
        $(this).closest('.ildesc-template-row').fadeOut(150, function() { $(this).remove(); });
    });

    $(document).on('click', '.ildesc-remove-unit-rule', function() {
        $(this).closest('.ildesc-unit-rule-row').fadeOut(150, function() { $(this).remove(); });
    });

    $(document).on('click', '#ildesc-add-feature', function() {
        var index = $('.ildesc-feature-row').length;
        var $row  = $(buildFeatureRow(index, '', ''));
        $('.ildesc-features-wrap').append($row.addClass('ildesc-row-new'));
        $row.find('input').first().focus();
    });

    // ==========================================
    // CONTENT HISTORY (Undo / Revert)
    // ==========================================
    $(document).on('click', '#ildesc-undo-last-btn, .ildesc-revert-version', function(e) {
        e.preventDefault();

        if (!window.confirm(ildesc_params.confirm_revert)) {
            return;
        }

        var $btn         = $(this);
        var $output      = $('#ildesc-status-message').length ? $('#ildesc-status-message') : $('#ildesc-message');
        var productId    = $btn.data('product-id');
        var historyIndex = $btn.data('history-index');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text(ildesc_params.reverting_text);

        $.ajax({
            url:      ildesc_params.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:         'ildesc_revert_content_version',
                product_id:     productId,
                history_index:  historyIndex,
                nonce:          ildesc_params.nonce
            },
            success: function(response) {
                applyContentResponse(response, $output, $btn);
                if (!response.success) { $btn.text(originalText); }
            },
            error: function(xhr, status, error) {
                showStatus($output, 'error', ildesc_params.server_error + ' ' + error);
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    $('#ildesc-clear-excerpt').on('click', function(e) {
        e.preventDefault();
        var confirmText = ildesc_params.confirm_clear || 'Are you sure?';
        if (confirm(confirmText)) {
            if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                tinymce.get('excerpt').setContent('');
            } else {
                $('#excerpt').val('');
            }
        }
    });

    $(document).on('click', '.ildesc-model-advisor-dismiss', function(e) {
        e.preventDefault();
        var $notice = $(this).closest('.ildesc-model-advisor');
        $.post(ildesc_params.ajax_url, {
            action: 'ildesc_dismiss_model_advisor',
            nonce: ildesc_params.nonce,
            provider: $notice.data('provider'),
            model: $notice.data('model')
        }, function() {
            $notice.fadeOut(200, function() { $(this).remove(); });
        });
    });

    // ==========================================
    // 3. TEMPLATES SETTINGS (Admin Page)
    // ==========================================
    $('#ildesc-add-template').on('click', function() {
        var $table = $('#ildesc-templates-table');
        if (!$table.length) return;

        var templateIndex      = parseInt($table.attr('data-index'), 10);
        var categoryOptionsRaw = $table.attr('data-options');
        var categoryOptions    = categoryOptionsRaw ? JSON.parse(categoryOptionsRaw) : '';

        var $row = $(
            '<tr class="ildesc-template-row ildesc-row-new">' +
            '<td><select name="ildesc_category_templates[' + templateIndex + '][category_id]" class="ildesc-input-wide">' + categoryOptions + '</select></td>' +
            '<td><input type="text" name="ildesc_category_templates[' + templateIndex + '][features]" class="ildesc-input-wide" placeholder="' + ildesc_params.placeholder_features + '"></td>' +
            '<td style="text-align:center"><button type="button" class="button ildesc-remove-template" aria-label="Remove" title="Remove">&#x2715;</button></td>' +
            '</tr>'
        );

        $table.find('tbody').append($row);
        $table.attr('data-index', templateIndex + 1);
        $row.find('input').focus();
    });

    // ==========================================
    // 4. UNIT RULES (Admin Page)
    // ==========================================
    $('#ildesc-add-unit-rule').on('click', function() {
        var $table = $('#ildesc-unit-rules-table');
        if (!$table.length) return;

        var index = parseInt($table.attr('data-index'), 10);

        var $row = $(
            '<tr class="ildesc-unit-rule-row ildesc-row-new">' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_unit_rules[' + index + '][feature]" placeholder="e.g. Battery Capacity"></td>' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_unit_rules[' + index + '][unit]" placeholder="e.g. mAh"></td>' +
            '<td style="text-align:center"><button type="button" class="button ildesc-remove-unit-rule" aria-label="Remove" title="Remove">&#x2715;</button></td>' +
            '</tr>'
        );

        $table.find('tbody').append($row);
        $table.attr('data-index', index + 1);
        $row.find('input').first().focus();
    });

    // ==========================================
    // 5. API KEY SHOW / HIDE
    // ==========================================
    $(document).on('click', '.ildesc-toggle-api-key', function() {
        var $input = $(this).siblings('.ildesc-api-key-field');
        var $icon  = $(this).find('.dashicons');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // ==========================================
    // 6. CATALOG DUPLICATE SCAN
    // ==========================================
    var scanId = null;

    $(document).on('click', '#ildesc-scan-start-btn', function(e) {
        e.preventDefault();

        $('#ildesc-scan-modal').css('display', 'flex').hide().fadeIn();
        $('#ildesc-scan-log-window').empty();
        $('#ildesc-scan-progress').css('width', '0%');
        $('#ildesc-scan-status-text').text(ildesc_params.scan_starting);
        $('#ildesc-scan-close').text(ildesc_params.stop_btn);

        $.post(ildesc_params.ajax_url, {
            action: 'ildesc_scan_start',
            nonce:  ildesc_params.nonce
        }, function(response) {
            if (!response.success) {
                scanFail(response.data && response.data.message);
                return;
            }
            scanId = response.data.scan_id;
            if (response.data.total_products === 0) {
                scanFail(ildesc_params.no_selected);
                return;
            }
            processNextScanBatch(response.data.total_products);
        }).fail(function() {
            scanFail(null);
        });
    });

    function processNextScanBatch(totalProducts) {
        $.post(ildesc_params.ajax_url, {
            action:  'ildesc_scan_batch',
            nonce:   ildesc_params.nonce,
            scan_id: scanId
        }, function(response) {
            if (!response.success) {
                scanFail(response.data && response.data.message);
                return;
            }

            var scanned = response.data.scanned_count;
            var total   = response.data.total_products;
            var pct     = total > 0 ? Math.round((scanned / total) * 100) : 100;

            $('#ildesc-scan-progress').css('width', pct + '%');
            $('#ildesc-scan-status-text').text(
                ildesc_params.scan_progress.replace('%1$d', scanned).replace('%2$d', total)
            );

            var $log = $('#ildesc-scan-log-window');
            $('<div>').addClass('ildesc-log-item ildesc-log-info').text(scanned + ' / ' + total).appendTo($log);
            $log.scrollTop($log[0].scrollHeight);

            if (response.data.done) {
                finalizeScan();
            } else {
                setTimeout(function() { processNextScanBatch(total); }, 150);
            }
        }).fail(function() {
            scanFail(null);
        });
    }

    function finalizeScan() {
        $('#ildesc-scan-status-text').text(ildesc_params.scan_finalizing);
        $.post(ildesc_params.ajax_url, {
            action:  'ildesc_scan_finalize',
            nonce:   ildesc_params.nonce,
            scan_id: scanId
        }, function(response) {
            if (!response.success) {
                scanFail(response.data && response.data.message);
                return;
            }
            $('#ildesc-scan-progress').css('width', '100%');
            $('#ildesc-scan-status-text').text(ildesc_params.scan_done);
            setTimeout(function() { window.location.reload(); }, 1500);
        }).fail(function() {
            scanFail(null);
        });
    }

    function scanFail(message) {
        var $log = $('#ildesc-scan-log-window');
        $('<div>').addClass('ildesc-log-item ildesc-log-error')
            .text(ildesc_params.scan_error + (message || ildesc_params.unknown_error))
            .appendTo($log);
        $('#ildesc-scan-status-text').text('');
        $('#ildesc-scan-close').text(ildesc_params.close_btn);
    }

    $(document).on('click', '#ildesc-scan-close', function() {
        $('#ildesc-scan-modal').fadeOut();
    });

    // ==========================================
    // HELPERS
    // ==========================================

    function applyContentResponse(response, $output, $btn) {
        if (response.success) {
            var extra = response.data.message ? ' — ' + response.data.message : '';
            showStatus($output, 'success', ildesc_params.status_success + extra);
            if (response.data.confidence === 'generic') {
                $output.append('<div class="ildesc-generic-badge"><span class="dashicons dashicons-info-outline"></span> ' + ildesc_params.generic_notice + '</div>');
            }

            if (response.data.short_description) {
                if (typeof tinymce !== 'undefined' && tinymce.get('excerpt') && !tinymce.get('excerpt').isHidden()) {
                    tinymce.get('excerpt').setContent(response.data.short_description);
                } else {
                    $('#excerpt').val(response.data.short_description);
                }
            }

            if (response.data.long_description) {
                if (typeof tinymce !== 'undefined' && tinymce.get('content') && !tinymce.get('content').isHidden()) {
                    tinymce.get('content').setContent(response.data.long_description);
                } else if ($('#content').length) {
                    $('#content').val(response.data.long_description);
                }
            }

            if (response.data.features && $('.ildesc-features-wrap').length) {
                var $wrap = $('.ildesc-features-wrap');
                $wrap.empty();
                response.data.features.forEach(function(feat, index) {
                    $wrap.append(buildFeatureRow(index, feat.name, feat.value));
                });
            }

            if (response.data.reload_required) {
                showStatus($output, 'success', ildesc_params.status_done);
                if (response.data.confidence === 'generic') {
                    $output.append('<div class="ildesc-generic-badge"><span class="dashicons dashicons-info-outline"></span> ' + ildesc_params.generic_notice + '</div>');
                }
                if ($btn) { $btn.prop('disabled', true); }
                setTimeout(function() { window.location.reload(); }, 5000);
                return;
            }
        } else {
            showStatus($output, 'error', ildesc_params.status_error + (response.data.message || ildesc_params.unknown_error));
            if ($btn) { $btn.prop('disabled', false); }
        }
    }

    function buildFeatureRow(index, name, value) {
        var eName  = $('<div>').text(name).html();
        var eValue = $('<div>').text(value).html();
        return '<tr class="ildesc-feature-row">' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][name]" value="' + eName + '" placeholder="' + ildesc_params.placeholder_feature_name + '"></td>' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][value]" value="' + eValue + '" placeholder="Value"></td>' +
            '<td style="text-align:center"><button type="button" class="button ildesc-remove-feature" aria-label="Remove" title="Remove">&#x2715;</button></td>' +
            '</tr>';
    }

    function showStatus($el, type, text) {
        var iconClass = (type === 'success') ? 'dashicons-yes-alt' : 'dashicons-warning';
        $el.removeClass('ildesc-msg-success ildesc-msg-error')
           .addClass('ildesc-msg-' + type)
           .html('<span class="dashicons ' + iconClass + '"></span><span>' + text + '</span>');
    }

    function setBtnText($btn, text) {
        var $span = $btn.find('.ildesc-btn-text');
        if ($span.length) { $span.text(text); } else { $btn.text(text); }
    }

    $(document).on('submit', '.ildesc-usage-reset-form', function(e) {
        if (!window.confirm(ildesc_params.usage_reset_confirm)) {
            e.preventDefault();
        }
    });

    function getEditorText(id) {
        if (typeof tinymce !== 'undefined' && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            return tinymce.get(id).getContent({ format: 'text' }).trim();
        } else if ($('#' + id).length) {
            return $('#' + id).val().trim();
        }
        return '';
    }
});

// ==========================================
// FAQ BLOCK (product edit screen)
// ==========================================
jQuery(function($) {
    if (!$('.ildesc-faq-box').length || typeof ildesc_faq_params === 'undefined') {
        return;
    }

    var $list   = $('.ildesc-faq-list');
    var $status = $('#ildesc-faq-status');
    var rowSeq  = $list.children('.ildesc-faq-row').length;

    function esc(text) {
        return $('<div>').text(text == null ? '' : text).html();
    }

    function editorText(id) {
        if (typeof tinymce !== 'undefined' && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            return tinymce.get(id).getContent({ format: 'text' }).trim();
        }
        return $('#' + id).length ? String($('#' + id).val() || '').trim() : '';
    }

    function faqRow(question, answer) {
        var i = rowSeq++;
        return '<div class="ildesc-faq-row">' +
            '<div class="ildesc-faq-fields">' +
            '<input type="text" class="ildesc-input-wide" name="ildesc_faq[' + i + '][q]" value="' + esc(question) + '" placeholder="' + esc(ildesc_faq_params.question) + '">' +
            '<textarea class="ildesc-input-wide" rows="2" name="ildesc_faq[' + i + '][a]" placeholder="' + esc(ildesc_faq_params.answer) + '">' + esc(answer) + '</textarea>' +
            '</div>' +
            '<button type="button" class="button ildesc-remove-faq" aria-label="' + esc(ildesc_faq_params.remove) + '" title="' + esc(ildesc_faq_params.remove) + '">&#x2715;</button>' +
            '</div>';
    }

    function setStatus(type, text) {
        $status.removeClass('ildesc-msg-success ildesc-msg-error').addClass(type ? 'ildesc-msg-' + type : '').text(text || '');
    }

    function syncDeleteButton() {
        $('#ildesc-delete-faq').toggle($list.children('.ildesc-faq-row').length > 0);
    }

    $(document).on('click', '#ildesc-generate-faq', function(e) {
        e.preventDefault();
        var $btn = $(this);
        if ($list.children('.ildesc-faq-row').length && !window.confirm(ildesc_faq_params.confirm_replace)) {
            return;
        }

        var features = [];
        $('.ildesc-feature-row').each(function() {
            var name  = String($(this).find('input[name*="[name]"]').val() || '').trim();
            var value = String($(this).find('input[name*="[value]"]').val() || '').trim();
            if (name && value) features.push(name + ': ' + value);
        });

        $btn.prop('disabled', true).find('.ildesc-btn-text').text(ildesc_faq_params.generating);
        $('.ildesc-faq-spinner').addClass('is-active');
        setStatus('', '');

        $.ajax({
            url:      ildesc_params.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:            'ildesc_generate_faq',
                nonce:             ildesc_params.nonce,
                product_id:        $('#post_ID').val(),
                product_title:     $('#title').val() || '',
                current_excerpt:   editorText('excerpt'),
                current_content:   editorText('content'),
                existing_features: features.join(' | ')
            }
        }).done(function(response) {
            if (response && response.success) {
                $list.empty();
                $.each(response.data.faq || [], function(_, item) {
                    $list.append(faqRow(item.q, item.a));
                });
                setStatus('success', ildesc_faq_params.saved);
            } else {
                setStatus('error', ildesc_params.status_error + ((response && response.data && response.data.message) || ildesc_params.unknown_error));
            }
        }).fail(function(xhr, status, error) {
            setStatus('error', ildesc_params.server_error + ' ' + error);
        }).always(function() {
            $btn.prop('disabled', false).find('.ildesc-btn-text').text(ildesc_faq_params.generate);
            $('.ildesc-faq-spinner').removeClass('is-active');
            syncDeleteButton();
        });
    });

    $(document).on('click', '.ildesc-remove-faq', function() {
        $(this).closest('.ildesc-faq-row').remove();
        syncDeleteButton();
    });

    $(document).on('click', '#ildesc-delete-faq', function(e) {
        e.preventDefault();
        if (!window.confirm(ildesc_faq_params.confirm_delete)) return;
        $list.empty();
        syncDeleteButton();
        setStatus('', ildesc_faq_params.deleted_hint);
    });

    // PRO only — the button is not rendered in the free version, and the server caps the count anyway.
    $(document).on('click', '#ildesc-add-faq', function(e) {
        e.preventDefault();
        if ($list.children('.ildesc-faq-row').length >= (parseInt($list.data('max'), 10) || 0)) {
            setStatus('error', ildesc_faq_params.max_reached);
            return;
        }
        var $row = $(faqRow('', ''));
        $list.append($row);
        $row.find('input').focus();
        syncDeleteButton();
    });
});

// Metabox: copy a ready-made shortcode (includes/shortcodes.php).
jQuery(function($) {
    $(document).on('click', '.ildesc-copy-shortcode', function() {
        var $btn = $(this);
        var text = $btn.data('shortcode');
        if (!$btn.data('label')) {
            $btn.data('label', $btn.text());
        }
        var done = function() {
            $btn.text($btn.data('copied'));
            setTimeout(function() { $btn.text($btn.data('label')); }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var $tmp = $('<textarea>').val(text).appendTo('body').trigger('select');
            document.execCommand('copy');
            $tmp.remove();
            done();
        }
    });
});
