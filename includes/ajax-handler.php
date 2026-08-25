<?php
// includes/ajax-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_ildesc_autocomplete_features', 'ildesc_handle_autocomplete_features');
add_action('wp_ajax_ildesc_dismiss_model_advisor', 'ildesc_handle_dismiss_model_advisor');
add_action('wp_ajax_ildesc_revert_content_version', 'ildesc_handle_revert_content_version');

/**
 * Prepends a snapshot to the product's content-history meta, capped at ILDESC_CONTENT_HISTORY_LIMIT.
 */
function ildesc_push_content_history_snapshot( $product_id, $snapshot ) {
    $history = get_post_meta( $product_id, '_ildesc_content_history', true );
    if ( ! is_array( $history ) ) {
        $history = [];
    }
    array_unshift( $history, $snapshot );
    $history = array_slice( $history, 0, ILDESC_CONTENT_HISTORY_LIMIT );
    update_post_meta( $product_id, '_ildesc_content_history', $history );
}

/**
 * Reverts a product's short/long description + features to a past snapshot.
 * The pre-revert content is itself pushed onto the history stack first, so a revert can be undone.
 */
function ildesc_handle_revert_content_version() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ) );
    }

    $product_id = isset( $_POST['product_id'] )     ? absint( $_POST['product_id'] )     : 0;
    $index      = isset( $_POST['history_index'] )  ? absint( $_POST['history_index'] )  : -1;

    if ( $product_id === 0 || ! current_user_can( 'edit_product', $product_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid product.', 'intellidesc-for-woocommerce' ) ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( array( 'message' => __( 'Product not found.', 'intellidesc-for-woocommerce' ) ) );
    }

    $history = get_post_meta( $product_id, '_ildesc_content_history', true );
    if ( ! is_array( $history ) || ! isset( $history[ $index ] ) ) {
        wp_send_json_error( array( 'message' => __( 'That history entry no longer exists.', 'intellidesc-for-woocommerce' ) ) );
    }

    $target = $history[ $index ];
    unset( $history[ $index ] );
    $history = array_values( $history );

    $current_features = get_post_meta( $product_id, '_ildesc_editable_features', true );
    array_unshift( $history, [
        'time'     => current_time( 'timestamp' ),
        'short'    => $product->get_short_description(),
        'long'     => $product->get_description(),
        'features' => is_array( $current_features ) ? $current_features : [],
        'provider' => isset( $target['provider'] ) ? $target['provider'] : '',
        'model'    => isset( $target['model'] ) ? $target['model'] : '',
    ] );
    $history = array_slice( $history, 0, ILDESC_CONTENT_HISTORY_LIMIT );
    update_post_meta( $product_id, '_ildesc_content_history', $history );

    $target_features = ( isset( $target['features'] ) && is_array( $target['features'] ) ) ? $target['features'] : [];

    $product->set_short_description( wp_kses_post( $target['short'] ?? '' ) );
    $product->set_description( wp_kses_post( $target['long'] ?? '' ) );
    $product->save();
    update_post_meta( $product_id, '_ildesc_editable_features', $target_features );

    wp_send_json_success( [
        'message'           => __( 'Reverted to the selected version.', 'intellidesc-for-woocommerce' ),
        'short_description' => $target['short'] ?? '',
        'long_description'  => $target['long'] ?? '',
        'features'          => $target_features,
        'reload_required'   => true,
    ] );
}

/**
 * Records that the merchant dismissed the "a newer model is available"
 * suggestion for a given provider, so it stops showing on the Settings page
 * until an even newer model takes the top spot.
 */
function ildesc_handle_dismiss_model_advisor() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ) );
    }

    $provider         = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
    $model            = isset( $_POST['model'] )    ? sanitize_text_field( wp_unslash( $_POST['model'] ) )    : '';
    $valid_providers  = array( 'gemini', 'anthropic', 'openai', 'xai' );

    if ( ! in_array( $provider, $valid_providers, true ) || empty( $model ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid request.', 'intellidesc-for-woocommerce' ) ) );
    }

    ildesc_set_model_advisor_dismissed( $provider, $model );

    wp_send_json_success();
}

/**
 * Main AJAX Handler Wrapper
 */
function ildesc_handle_autocomplete_features() {
    check_ajax_referer('ildesc_autocomplete_nonce', 'nonce');

    if (!current_user_can('edit_products')) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'intellidesc-for-woocommerce')));
    }

    $product_id    = isset( $_POST['product_id'] )    ? absint( $_POST['product_id'] ) : 0;
    $product_title = isset( $_POST['product_title'] ) ? sanitize_text_field( wp_unslash( $_POST['product_title'] ) ) : '';
    
    if ( $product_id === 0 || empty( $product_title ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid product data.', 'intellidesc-for-woocommerce' ) ) );
    }

    // Call the core generation logic
    $result = ildesc_generate_content_for_product($product_id, $product_title);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success($result);
    }
}

/**
 * Core Generation Logic
 * Can be used by AJAX (Single) or Bulk Loop (Pro)
 */
function ildesc_generate_content_for_product($product_id, $product_title) {
    
    // 1. Basic Checks
    $provider = ildesc_get_current_provider();
    $api_key  = ildesc_get_api_key_for_provider( $provider );
    if (empty($api_key)) {
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        return new WP_Error('api_key', sprintf( __('%s API Key is missing.', 'intellidesc-for-woocommerce'), ildesc_ai_provider_label( $provider ) ));
    }

    // GET PRODUCT TYPE
    $product = wc_get_product($product_id);
    if (!$product) return new WP_Error('not_found', 'Product not found.');
    
    $product_type = $product->get_type();
    $is_virtual = $product->is_virtual();
    $is_downloadable = $product->is_downloadable();

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already verified in ildesc_handle_autocomplete_features() before this function is called.
    if ( isset($_POST['product_type_ui']) ) {
        $product_type = sanitize_text_field(wp_unslash($_POST['product_type_ui']));
    }

    if ( isset($_POST['is_virtual_ui']) ) {
        $is_virtual = !empty($_POST['is_virtual_ui']);
    }

    if ( isset($_POST['is_downloadable_ui']) ) {
        $is_downloadable = !empty($_POST['is_downloadable_ui']);
    }

    $context_excerpt  = isset($_POST['current_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['current_excerpt'])) : '';
    $context_features = isset($_POST['existing_features']) ? sanitize_text_field(wp_unslash($_POST['existing_features'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    $user_constraints_prompt = "";
    if (!empty($context_features) || !empty($context_excerpt)) {
        $user_constraints_prompt = "
        =========================================
        CRITICAL INVENTORY CONSTRAINTS (STRICT):
        The seller has explicitly defined EXACTLY what is currently in stock in their warehouse.
        PREDEFINED DATA: [ {$context_features} ]

        1. If a property (e.g., Size, Color, Storage) is mentioned in the PREDEFINED DATA, you MUST output ONLY the exact values provided.
        2. ABSOLUTELY DO NOT add other sizes or colors found online. If the predefined data says 'Size: M', you must output ONLY 'M'. Do not add 'S', 'L', or 'XL'.
        3. Treat PREDEFINED DATA as the absolute truth for this specific store.
        - User's notes: " . mb_substr($context_excerpt, 0, 300) . "
        =========================================
            ";
    }

    // 2. Language Setup
    $selected_lang = get_option(ILDESC_CONTENT_LANGUAGE, 'default');
    $target_language = ($selected_lang === 'default') ? substr(get_locale(), 0, 2) : $selected_lang;
    $target_language = !empty($target_language) ? sanitize_text_field($target_language) : 'en';
    $language_instruction = "IMPORTANT: Write ALL content in language code: '{$target_language}'.";

    $skip_features = (bool) get_option( ILDESC_SKIP_FEATURES, 0 );

    // ---------------------------------------------------------
    // 3. SETTINGS & PROMPT CONSTRUCTION
    // ---------------------------------------------------------

    // A. MODEL SELECTION
    $selected_model = ildesc_get_model_for_provider( $provider );

    // B. TONE OF VOICE
    // Default (Free): Neutral / Informative
    $tone_instruction = "Tone: Informative, professional, neutral. Avoid marketing fluff.";

    // C. DESCRIPTION FORMATTING
    // Default (Free): Simple Text, No HTML, Standard Length
    $desc_prompt_part = "Create a universal, factual 'Long_Description' (2 paragraphs). Keep it generic and safe. NO HTML tags, just plain text.";
    
    // E. TEMPLATES (Category Rules)
    $templates = get_option( ILDESC_CATEGORY_TEMPLATES, []);
    $specialized_features_prompt = '';
    $allowed_features_list = []; 
    $terms = get_the_terms($product_id, 'product_cat');

    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            foreach ($templates as $template) {
                if ($template['category_id'] == $term->term_id) {
                    $raw_features = sanitize_text_field($template['features']);
                    $allowed_features_list = array_map('trim', explode(',', $raw_features));
                    $specialized_features_prompt = "STRICT CONSTRAINT: The 'Features' array must ONLY contain these keys: \"{$raw_features}\".";
                    break 2;
                }
            }
        }
    }

    // D. UNIT RULES
    $units_prompt_part = '';
    $unit_rules = get_option( ILDESC_UNIT_RULES, [] );
    if ( ! empty( $unit_rules ) && is_array( $unit_rules ) ) {
        $unit_lines = [];
        foreach ( $unit_rules as $rule ) {
            if ( ! empty( $rule['feature'] ) && ! empty( $rule['unit'] ) ) {
                $unit_lines[] = '- "' . $rule['feature'] . '": output value ONLY in "' . $rule['unit'] . '"';
            }
        }
        if ( ! empty( $unit_lines ) ) {
            $units_prompt_part = "UNITS & FORMAT (STRICT — override defaults):\n" . implode( "\n", $unit_lines );
        }
    }

    // E. FORMATTING RULES
    $formatting_rules = "DATA CLEANING RULES (CRITICAL):
    1. Feature Values must be SHORT and CONCISE.
       - BAD: 'MediaTek Helio G99 (6 nm) Octa-core (2x2.2 GHz...)'
       - GOOD: 'MediaTek Helio G99'
       - Rule: Remove frequencies (GHz), architecture details (Cortex), and manufacturing size (nm).
    2. Feature Names must be STANDARD CATEGORIES.
       - BAD Name: 'Ice Force (Cryogenic hardening)'
       - GOOD Name: 'Technology' -> Value: 'Ice Force'";

    // G. CHOOSE PRODUCT TYPE.

    $product_type_context = "PRODUCT TYPE: Standard physical item.";
    
    if ($product_type === 'variable') {
        $product_type_context = "PRODUCT TYPE: Variable product (e.g. clothing in different sizes/colors). IMPORTANT: For 'Features', you MUST include likely variation attributes. For example, add {'Name': 'Available Colors', 'Value': 'Red, Blue, Black'} or {'Name': 'Sizes', 'Value': 'S, M, L, XL'}.";
    } elseif ($is_downloadable) {
        $product_type_context = "PRODUCT TYPE: Digital Download (e.g. eBook, software, audio). IMPORTANT: Do NOT generate physical dimensions or materials. Focus 'Features' on digital specs like File Format, File Size, Number of Pages, Duration, or Resolution.";
    } elseif ($is_virtual) {
        $product_type_context = "PRODUCT TYPE: Virtual Service or Membership. IMPORTANT: Focus 'Features' on service details, duration, access type, or support terms. No physical specs.";
    }

    // H. UNBRANDED / GENERIC PRODUCT FALLBACK
    $generic_fallback_prompt = "UNBRANDED / GENERIC PRODUCTS (IMPORTANT):
    If this exact product cannot be identified as a specific branded/searchable item (e.g. it is generic, bulk, no-name, or otherwise has no findable web presence), DO NOT fail, refuse, or return empty fields.
    1. Write the Short_Description and Long_Description using well-established general knowledge about this type of product (typical use, materials, properties). A plausible, honest, generic description is REQUIRED — never leave them blank.
    2. Reuse any concrete facts already present in the product title itself (size, dimensions, color, quantity, material, units) — do not omit or contradict them.
    3. For Features, only include values that are generally true for this type of product; phrase them qualitatively rather than inventing precise brand-specific numbers you cannot verify. If a required Feature key (see constraints below) has no knowable value for a generic item, it is acceptable to omit that entry rather than fabricate a number.
    4. Set 'Confidence' to 'generic' whenever this fallback was used for any part of the output; otherwise set it to 'verified'.";

    // ---------------------------------------------------------
    // 4. CONSTRUCT FINAL JSON & SYSTEM INSTRUCTION
    // ---------------------------------------------------------

    $json_structure = "{'Short_Description': 'string', 'Long_Description': 'string', 'Confidence': 'verified|generic'";
    if ( ! $skip_features ) {
        $json_structure .= ", 'Features': [{'Name': 'string', 'Value': 'string'}]";
    }
    $json_structure .= "}";

    $search_tool_instruction = "- USE THE WEB SEARCH TOOL TO VERIFY REAL SPECIFICATIONS.\n       ";

    $features_task = '';
    if ( ! $skip_features ) {
        $features_task = "2. Generate features.

       Input Constraints:
       {$specialized_features_prompt}
       (If keys provided above, strictly fill ONLY those. Otherwise, find technical specs).

       Technical Data Rules:
       - Values must be PRECISE and FACTUAL.
       {$search_tool_instruction}{$units_prompt_part}
       {$formatting_rules}

       - Prefer exact NUMBERS over vague descriptors (e.g. 'High res') when a precise value is knowable for this specific product.
       - If this is a generic/unbranded item with no exact number available (see the UNBRANDED/GENERIC rule above), use a standard qualitative description instead of inventing a false number.";
    }

    $base_instruction = "Act as an E-commerce Assistant. Analyze the product: \"{$product_title}\".

    {$user_constraints_prompt}
    {$tone_instruction}
    {$product_type_context}
    {$generic_fallback_prompt}

    {$desc_prompt_part}

    {$language_instruction}

    TASK:
    1. Generate descriptions.
    {$features_task}

    STRICT OUTPUT RULES:
    1. Return ONLY raw JSON.
    2. No Markdown blocks.
    3. Structure: {$json_structure}";

    // ---------------------------------------------------------
    // 5. API REQUEST
    // ---------------------------------------------------------

    $ai_result = ildesc_ai_call( $provider, $selected_model, $base_instruction, $api_key, [
        'use_search_tool' => true,
        'fallback_model'  => ildesc_get_fallback_model_for_provider( $provider ),
    ] );

    if ( is_wp_error( $ai_result ) ) {
        return $ai_result;
    }

    ildesc_clear_model_unavailable_flag( $provider );

    $raw_text = $ai_result;

    // Robust cleaning
    $clean_json = preg_replace('/^```json\s*|\s*```$/', '', trim($raw_text));
    $clean_json = str_replace(array('```', '`'), '', $clean_json);
    
    $start = strpos($clean_json, '{');
    $end = strrpos($clean_json, '}');

    if ($start === false || $end === false) {
        return new WP_Error('json_parse', 'JSON Parsing Error.');
    }

    $json_string = substr($clean_json, $start, $end - $start + 1);
    $json_string = preg_replace('!/\*.*?\*/!s', '', $json_string);
    $features_json = json_decode($json_string, true);

    if ( empty( $features_json ) ) {
        return new WP_Error('json_parse', 'JSON Decode Error.');
    }

    $short_empty = trim( (string) ( $features_json['Short_Description'] ?? '' ) ) === '';
    $long_empty  = trim( (string) ( $features_json['Long_Description'] ?? '' ) ) === '';
    if ( $short_empty && $long_empty ) {
        return new WP_Error( 'empty_content', __( 'The AI returned an empty response for this product. Please try again or switch AI providers/models.', 'intellidesc-for-woocommerce' ) );
    }

    // ---------------------------------------------------------
    // 7. SAVING DATA
    // ---------------------------------------------------------

    $overwrite = get_option( ILDESC_OVERWRITE_DATA, 0 );

    $prev_short    = $product->get_short_description();
    $prev_long     = $product->get_description();
    $prev_features = get_post_meta( $product_id, '_ildesc_editable_features', true );
    if ( ! is_array( $prev_features ) ) {
        $prev_features = [];
    }

    if ( $overwrite && ( $prev_short !== '' || $prev_long !== '' || ! empty( $prev_features ) ) ) {
        ildesc_push_content_history_snapshot( $product_id, [
            'time'     => current_time( 'timestamp' ),
            'short'    => $prev_short,
            'long'     => $prev_long,
            'features' => $prev_features,
            'provider' => $provider,
            'model'    => $selected_model,
        ] );
    }

    if ($overwrite || empty($product->get_short_description())) {
        $product->set_short_description(wp_kses_post($features_json['Short_Description'] ?? ''));
    }
    
    if ($overwrite || empty($product->get_description())) {
        $desc = $features_json['Long_Description'] ?? '';
        if (strpos($desc, '<p>') === false && !empty($desc)) $desc = nl2br($desc);
        $product->set_description(wp_kses_post($desc));
    }

    $editable_features = [];
    if ( ! $skip_features && !empty($features_json['Features']) && is_array($features_json['Features'])) {
        foreach ($features_json['Features'] as $f) {
            $f_name = sanitize_text_field($f['Name'] ?? '');
            $f_value = sanitize_text_field($f['Value'] ?? '');
            
            if (!empty($allowed_features_list)) {
                $is_allowed = false;
                foreach ($allowed_features_list as $allowed) {
                    if (mb_strtolower(trim($allowed)) === mb_strtolower(trim($f_name))) {
                        $is_allowed = true;
                        $f_name = trim($allowed);
                        break;
                    }
                }
                if (!$is_allowed) continue;
            }
            $editable_features[] = ['name' => $f_name, 'value' => $f_value];
        }
        update_post_meta($product_id, '_ildesc_editable_features', $editable_features);
    }
    
    $product->save();

    $confidence = ( isset( $features_json['Confidence'] ) && strtolower( trim( (string) $features_json['Confidence'] ) ) === 'generic' ) ? 'generic' : 'verified';

    return [
        'success'           => true,
        'message'           => __('Content generated successfully!', 'intellidesc-for-woocommerce'),
        'short_description' => $features_json['Short_Description'] ?? '',
        'long_description'  => $features_json['Long_Description'] ?? '',
        'features'          => $editable_features,
        'confidence'        => $confidence,
        'reload_required'   => false,
    ];
}