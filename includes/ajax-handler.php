<?php
// includes/ajax-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_ildesc_autocomplete_features', 'ildesc_handle_autocomplete_features');

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
    $api_key = get_option(ILDESC_SETTINGS_KEY);
    if (empty($api_key)) {
        return new WP_Error('api_key', __('Gemini API Key is missing.', 'intellidesc-for-woocommerce'));
    }

    // GET PRODUCT TYPE
    $product = wc_get_product($product_id);
    if (!$product) return new WP_Error('not_found', 'Product not found.');
    
    $product_type = $product->get_type();
    $is_virtual = $product->is_virtual();
    $is_downloadable = $product->is_downloadable();

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
    $selected_model = get_option( ILDESC_SELECTED_MODEL, 'gemini-3.1-flash-lite' );

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

    // ---------------------------------------------------------
    // 4. CONSTRUCT FINAL JSON & SYSTEM INSTRUCTION
    // ---------------------------------------------------------
    
    $json_structure = "{'Short_Description': 'string', 'Long_Description': 'string'";
    if ( ! $skip_features ) {
        $json_structure .= ", 'Features': [{'Name': 'string', 'Value': 'string'}]";
    }
    $json_structure .= "}";

    $features_task = '';
    if ( ! $skip_features ) {
        $features_task = "2. Generate features.

       Input Constraints:
       {$specialized_features_prompt}
       (If keys provided above, strictly fill ONLY those. Otherwise, find technical specs).

       Technical Data Rules:
       - Values must be PRECISE and FACTUAL.
       - USE GOOGLE SEARCH TOOL.
       {$units_prompt_part}
       {$formatting_rules}

       - DO NOT use generic descriptors like 'High res'. Use NUMBERS.";
    }

    $base_instruction = "Act as an E-commerce Assistant. Analyze the product: \"{$product_title}\".

    {$user_constraints_prompt}
    {$tone_instruction}
    {$product_type_context}

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

    $request_body_array = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $base_instruction]]]],
        'tools' => [['google_search' => (object)[]]]
    ];
    $request_json = wp_json_encode($request_body_array);

    $fallback_model = 'gemini-2.5-flash';
    $models_to_try  = ($selected_model !== $fallback_model) ? [$selected_model, $fallback_model] : [$selected_model];

    $response      = null;
    $http_code     = 0;
    $response_body = '';

    foreach ($models_to_try as $model_attempt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_attempt}:generateContent?key=" . $api_key;
        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => $request_json,
            'timeout' => 60,
        ));

        if (is_wp_error($response)) continue;

        $http_code     = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code === 200) break;
    }

    if (is_wp_error($response)) {
        return new WP_Error('api_error', __('Connection failed: ', 'intellidesc-for-woocommerce') . $response->get_error_message());
    }

    // ---------------------------------------------------------
    // 6. HTTP STATUS & JSON PARSING
    // ---------------------------------------------------------

    if ($http_code !== 200) {
        $error_data  = json_decode($response_body, true);
        $api_details = isset($error_data['error']['message']) ? ' — ' . $error_data['error']['message'] : '';

        $status_messages = [
            400 => __('Bad request. The product title or prompt contains invalid characters.', 'intellidesc-for-woocommerce'),
            401 => __('Invalid API key. Go to WooCommerce → IntelliDesc and check your Gemini API key.', 'intellidesc-for-woocommerce'),
            403 => __('Access denied. Your API key does not have permission to use this model. Check billing in Google AI Studio.', 'intellidesc-for-woocommerce'),
            404 => __('Model not found. The Gemini model may have been renamed or removed. Contact plugin support.', 'intellidesc-for-woocommerce'),
            429 => __('Rate limit exceeded. You have hit the free tier limit (5–15 requests/min). Wait 60 seconds and try again.', 'intellidesc-for-woocommerce'),
            500 => __('Gemini API internal error. This is on Google\'s side — try again in a few moments.', 'intellidesc-for-woocommerce'),
            503 => __('Gemini API is temporarily unavailable (overloaded or maintenance). Try again later.', 'intellidesc-for-woocommerce'),
        ];

        $message = isset($status_messages[$http_code])
            ? $status_messages[$http_code]
            /* translators: %d: HTTP status code */
            : sprintf(__('Gemini API returned an unexpected error (HTTP %d).', 'intellidesc-for-woocommerce'), $http_code);

        return new WP_Error('api_http_' . $http_code, $message . $api_details);
    }

    $data = json_decode($response_body, true);

    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $finish_reason = $data['candidates'][0]['finishReason'] ?? '';
        if ($finish_reason === 'SAFETY') {
            return new WP_Error('api_safety', __('Gemini blocked the response due to safety filters. Try rephrasing the product title.', 'intellidesc-for-woocommerce'));
        }
        if ($finish_reason === 'RECITATION') {
            return new WP_Error('api_recitation', __('Gemini blocked the response due to recitation policy. Try a more specific product title.', 'intellidesc-for-woocommerce'));
        }
        return new WP_Error('api_error', __('Unexpected API response structure. Check your quota at Google AI Studio.', 'intellidesc-for-woocommerce'));
    }

    $raw_text = $data['candidates'][0]['content']['parts'][0]['text'];

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

    // ---------------------------------------------------------
    // 7. SAVING DATA
    // ---------------------------------------------------------

    $overwrite = get_option( ILDESC_OVERWRITE_DATA, 0 );

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

    return [
        'success'           => true,
        'message'           => __('Content generated successfully!', 'intellidesc-for-woocommerce'),
        'short_description' => $features_json['Short_Description'] ?? '',
        'long_description'  => $features_json['Long_Description'] ?? '',
        'features'          => $editable_features,
        'reload_required'   => false,
    ];
}