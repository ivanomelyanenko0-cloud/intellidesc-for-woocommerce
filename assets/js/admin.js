jQuery(document).ready(function($) {
    
    // ==========================================
    // 1. SINGLE PRODUCT GENERATION
    // ==========================================
    $(document).on('click', '#gpa-trigger-btn, #gpa-autocomplete-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $status = $('#gpa-status-message'); 
        var $messageSpan = $('#gpa-message'); // Fallback if meta box is different
        
        var $output = $status.length ? $status : $messageSpan;

        var productId = $('#post_ID').val();
        var productTitle = $('#title').val(); 
        var seoKeyword = $('#gpa-seo-keyword').val(); // Undefined in Free, which is fine
        
        if (!productTitle) {
            alert(gpa_params.no_title); 
            return;
        }

        $btn.prop('disabled', true).text(gpa_params.btn_loading); 
        $output.removeClass('notice-error notice-success').html(gpa_params.loading_text); 

        $.ajax({
            url: gpa_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'gpa_autocomplete_features',
                product_id: productId,
                product_title: productTitle,
                seo_keyword: seoKeyword, // In Free this sends undefined/empty, which is safe
                nonce: gpa_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    var msg = '<span style="color:green;">' + gpa_params.status_success + '</span>';
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
                    if (response.data.features && $('.gpa-features-wrap').length) {
                        var $wrap = $('.gpa-features-wrap');
                        $wrap.empty();
                        response.data.features.forEach(function(feat, index) {
                            $wrap.append(
                                '<tr class="gpa-feature-row">' +
                                '<td><input type="text" class="gpa-input-wide" name="gpa_feature[' + index + '][name]" value="' + feat.name + '"></td>' +
                                '<td><input type="text" class="gpa-input-wide" name="gpa_feature[' + index + '][value]" value="' + feat.value + '"></td>' +
                                '<td><button type="button" class="button gpa-remove-feature">Remove</button></td>' +
                                '</tr>'
                            );
                        });
                    }

                    // Reload if Attributes were saved (Logic mostly for Pro, but safe to keep)
                    if (response.data.reload_required) {
                        msg += '<span style="color:green;">' + gpa_params.status_success + '</span>';
                        msg += ' <strong>' + response.data.attr_msg + '</strong>';
                        $output.addClass('notice-success').html(msg);
                        
                        $btn.prop('disabled', true);
                        
                        setTimeout(function() {
                            window.location.reload();
                        }, 5000); 
                        
                        return;
                    }
                } else {
                    $output.addClass('notice-error').html('<span style="color:red;">' + gpa_params.status_error + (response.data.message || 'Unknown') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $output.html('<span style="color:red;">' + gpa_params.server_error + ' ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text(gpa_params.btn_default); 
            }
        });
    });

    // ==========================================
    // 2. UI HELPERS (Common)
    // ==========================================
    
    // Remove Feature Row
    $(document).on('click', '.gpa-remove-feature', function() {
        $(this).closest('.gpa-feature-row').remove();
    });
    
    // Add Feature Row
    $(document).on('click', '#gpa-add-feature', function() {
         var index = $('.gpa-feature-row').length; 
         var newRow = '<tr class="gpa-feature-row">' +
            '<td><input type="text" class="gpa-input-wide" name="gpa_feature[' + index + '][name]" placeholder="Name"></td>' +
            '<td><input type="text" class="gpa-input-wide" name="gpa_feature[' + index + '][value]" placeholder="Value"></td>' +
            '<td><button type="button" class="button gpa-remove-feature">Remove</button></td>' +
            '</tr>';
        $('.gpa-features-wrap').append(newRow);
    });

    // Clear Excerpt (Button from Free version)
    $('#gpa-clear-excerpt').on('click', function(e) {
        e.preventDefault();
        var confirmText = gpa_params.confirm_clear || 'Are you sure?';
        if (confirm(confirmText)) {
            if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                tinymce.get('excerpt').setContent('');
            } else {
                $('#excerpt').val('');
            }
        }
    });
});