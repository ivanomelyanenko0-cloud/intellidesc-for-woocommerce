jQuery(document).ready(function($) {
    
    // ==========================================
    // 1. SINGLE PRODUCT GENERATION
    // ==========================================
    $(document).on('click', '#ildesc-trigger-btn, #ildesc-autocomplete-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $status = $('#ildesc-status-message'); 
        var $messageSpan = $('#ildesc-message'); // Fallback if meta box is different
        
        var $output = $status.length ? $status : $messageSpan;

        var productId = $('#post_ID').val();
        var productTitle = $('#title').val(); 
        var seoKeyword = $('#ildesc-seo-keyword').val(); // Undefined in Free, which is fine
        
        if (!productTitle) {
            alert(ildesc_params.no_title); 
            return;
        }

        var currentExcerpt = getEditorText('excerpt');
        var currentContent = getEditorText('content');

        var uiProductType = $('#product-type').val() || 'simple';
        var uiIsVirtual = $('#_virtual').is(':checked') ? 1 : 0;
        var uiIsDownloadable = $('#_downloadable').is(':checked') ? 1 : 0;

        // Get existing features
        var existingFeatures = [];
        $('.ildesc-feature-row').each(function() {
            var fName = $(this).find('input[name*="[name]"]').val().trim();
            var fVal = $(this).find('input[name*="[value]"]').val().trim();
            if (fName || fVal) {
                existingFeatures.push(fName + ': ' + fVal);
            }
        });

        $btn.prop('disabled', true).text(ildesc_params.btn_loading); 
        $output.removeClass('notice-error notice-success').html(ildesc_params.loading_text); 

        $.ajax({
            url: ildesc_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ildesc_autocomplete_features',
                product_id: productId,
                product_title: productTitle,
                seo_keyword: seoKeyword, // In Free this sends undefined/empty, which is safe
                current_excerpt: currentExcerpt,
                current_content: currentContent,
                existing_features: existingFeatures.join(' | '),
                product_type_ui: uiProductType,
                is_virtual_ui: uiIsVirtual,
                is_downloadable_ui: uiIsDownloadable,
                nonce: ildesc_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    var msg = '<span class="ildesc-text-success">' + ildesc_params.status_success + '</span>';

                    if(response.data.message) {
                        msg += ' (' + response.data.message + ')';
                    }

                    $output.addClass('notice-success').html(msg);
                    
                    // Update Short Description
                    if (response.data.short_description) {
                        if (typeof tinymce !== 'undefined' && tinymce.get('excerpt') && !tinymce.get('excerpt').isHidden()) {
                            tinymce.get('excerpt').setContent(response.data.short_description);
                        } else {
                            $('#excerpt').val(response.data.short_description);
                        }
                    }

                    // Update Long Description (Available in Free as Text, Pro as HTML)
                    if (response.data.long_description) {
                        if (typeof tinymce !== 'undefined' && tinymce.get('content') && !tinymce.get('content').isHidden()) {
                            tinymce.get('content').setContent(response.data.long_description);
                        } 
                        else if ($('#content').length) {
                            $('#content').val(response.data.long_description);
                        }
                    }
                    
                    // Update Features Table
                    if (response.data.features && $('.ildesc-features-wrap').length) {
                        var $wrap = $('.ildesc-features-wrap');
                        $wrap.empty();
                        response.data.features.forEach(function(feat, index) {
                            $wrap.append(
                                '<tr class="ildesc-feature-row">' +
                                '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][name]" value="' + feat.name + '"></td>' +
                                '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][value]" value="' + feat.value + '"></td>' +
                                '<td><button type="button" class="button ildesc-remove-feature">Remove</button></td>' +
                                '</tr>'
                            );
                        });
                    }

                    // Reload if Attributes were saved (Logic mostly for Pro, but safe to keep)
                    if (response.data.reload_required) {
                        msg += '<br><span class="ildesc-text-success">' + ildesc_params.status_success + '</span>';
                        msg += ' <strong>' + response.data.attr_msg + '</strong>';
                        $output.addClass('notice-success').html(msg);
                        
                        $btn.prop('disabled', true);
                        
                        setTimeout(function() {
                            window.location.reload();
                        }, 5000); 
                        
                        return;
                    }
                } else {
                    $output.addClass('notice-error').html('<span class="ildesc-text-error">' + ildesc_params.status_error + (response.data.message || 'Unknown') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $output.html('<span class="ildesc-text-error">' + ildesc_params.server_error + ' ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text(ildesc_params.btn_default); 
            }
        });
    });

    // ==========================================
    // 2. UI HELPERS (Common)
    // ==========================================
    
    // Remove Feature Row
    $(document).on('click', '.ildesc-remove-feature', function() {
        $(this).closest('.ildesc-feature-row').remove();
    });
    
    // Add Feature Row
    $(document).on('click', '#ildesc-add-feature', function() {
         var index = $('.ildesc-feature-row').length; 
         var newRow = '<tr class="ildesc-feature-row">' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][name]" placeholder="Name"></td>' +
            '<td><input type="text" class="ildesc-input-wide" name="ildesc_feature[' + index + '][value]" placeholder="Value"></td>' +
            '<td><button type="button" class="button ildesc-remove-feature">Remove</button></td>' +
            '</tr>';
        $('.ildesc-features-wrap').append(newRow);
    });

    // Clear Excerpt (Button from Free version)
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

    /**
     * Helper to read plain text from WP Editor (TinyMCE or plain textarea)
     */
    function getEditorText(id) {
        if (typeof tinymce !== 'undefined' && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            return tinymce.get(id).getContent({format: 'text'}).trim(); 
        } else if ($('#' + id).length) {
            return $('#' + id).val().trim();
        }
        return '';
    }

    // ==========================================
    // 3. TEMPLATES SETTINGS (Admin Page)
    // ==========================================
    $('#ildesc-add-template').on('click', function() {
        var $table = $('#ildesc-templates-table');
        if (!$table.length) return;
        
        var templateIndex = parseInt($table.attr('data-index'), 10);
        var categoryOptionsRaw = $table.attr('data-options');
        var categoryOptions = categoryOptionsRaw ? JSON.parse(categoryOptionsRaw) : '';
        
        var newRow = '<tr class="ildesc-template-row"><td>' +
            '<select name="ildesc_category_templates[' + templateIndex + '][category_id]" class="ildesc-input-wide">' + 
            categoryOptions + 
            '</select>' +
            '</td><td>' +
            '<input type="text" name="ildesc_category_templates[' + templateIndex + '][features]" class="ildesc-input-wide">' +
            '</td><td>' +
            '<button type="button" class="button ildesc-remove-template">Remove</button>' +
            '</td></tr>';
            
        $table.find('tbody').append(newRow);
        $table.attr('data-index', templateIndex + 1);
    });
    
    $(document).on('click', '.ildesc-remove-template', function() {
        $(this).closest('tr').remove();
    });
});