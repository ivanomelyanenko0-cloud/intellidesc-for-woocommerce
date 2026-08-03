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
                if (response.success) {
                    var extra = response.data.message ? ' — ' + response.data.message : '';
                    showStatus($output, 'success', ildesc_params.status_success + extra);

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
                        $btn.prop('disabled', true);
                        setTimeout(function() { window.location.reload(); }, 5000);
                        return;
                    }
                } else {
                    showStatus($output, 'error', ildesc_params.status_error + (response.data.message || ildesc_params.unknown_error));
                }
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
    // HELPERS
    // ==========================================

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

    function getEditorText(id) {
        if (typeof tinymce !== 'undefined' && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            return tinymce.get(id).getContent({ format: 'text' }).trim();
        } else if ($('#' + id).length) {
            return $('#' + id).val().trim();
        }
        return '';
    }
});
