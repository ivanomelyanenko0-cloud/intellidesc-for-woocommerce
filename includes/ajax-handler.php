<?php
// includes/ajax-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_gpa_autocomplete_features', 'gpa_handle_autocomplete_features');

/**
 * Main AJAX Handler Wrapper
 */
function gpa_handle_autocomplete_features() {
    check_ajax_referer('gpa_autocomplete_nonce', 'nonce');

    if (!current_user_can('edit_products')) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'gemini-product-autocomplete')));
    }

    $product_id    = isset( $_POST['product_id'] )    ? absint( $_POST['product_id'] ) : 0;
    $product_title = isset( $_POST['product_title'] ) ? sanitize_text_field( wp_unslash( $_POST['product_title'] ) ) : '';
    
    if ( $product_id === 0 || empty( $product_title ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid product data.', 'gemini-product-autocomplete' ) ) );
    }
    // SEO Keyword is a Pro feature, but we can safely sanitize it here. 

    // Call the core generation logic
    $result = gpa_generate_content_for_product($product_id, $product_title, true);

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
function gpa_generate_content_for_product($product_id, $product_title, $seo_keyword = '', $is_ajax = false) {
    
    // 1. Basic Checks
    $api_key = get_option(GPA_SETTINGS_KEY);
    if (empty($api_key)) {
        return new WP_Error('api_key', __('Gemini API Key is missing.', 'gemini-product-autocomplete'));
    }

    // 2. Language Setup
    $selected_lang = get_option(GPA_CONTENT_LANGUAGE, 'default');
    $target_language = ($selected_lang === 'default') ? substr(get_locale(), 0, 2) : $selected_lang;
    $target_language = !empty($target_language) ? sanitize_text_field($target_language) : 'en';
    $language_instruction = "IMPORTANT: Write ALL content in language code: '{$target_language}'.";

    // ---------------------------------------------------------
    // 3. SETTINGS & PROMPT CONSTRUCTION
    // ---------------------------------------------------------

    // A. MODEL SELECTION
    // Default (Free): Stable Flash model
    $selected_model = 'gemini-2.5-flash'; 

    // B. TONE OF VOICE
    // Default (Free): Neutral / Informative
    $tone_instruction = "Tone: Informative, professional, neutral. Avoid marketing fluff.";

    // C. SEO INSTRUCTIONS
    $seo_prompt_part = "";

    // D. DESCRIPTION FORMATTING
    // Default (Free): Simple Text, No HTML, Standard Length
    $desc_prompt_part = "Create a universal, factual 'Long_Description' (2 paragraphs). Keep it generic and safe. NO HTML tags, just plain text.";
    
    // E. TEMPLATES (Category Rules)
    $templates = get_option('gpa_category_templates', []);
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

    // F. PRESETS & FORMATTING RULES
    $preset_context = "NICHE CONTEXT: General E-commerce product.";
    $formatting_rules = "DATA CLEANING RULES (CRITICAL):
    1. Feature Values must be SHORT and CONCISE.
       - BAD: 'MediaTek Helio G99 (6 nm) Octa-core (2x2.2 GHz...)'
       - GOOD: 'MediaTek Helio G99'
       - Rule: Remove frequencies (GHz), architecture details (Cortex), and manufacturing size (nm).
    2. Feature Names must be STANDARD CATEGORIES.
       - BAD Name: 'Ice Force (Cryogenic hardening)'
       - GOOD Name: 'Technology' -> Value: 'Ice Force'";

    // ---------------------------------------------------------
    // 4. CONSTRUCT FINAL JSON & SYSTEM INSTRUCTION
    // ---------------------------------------------------------
    
    $json_structure = "{'Short_Description': 'string', 'Long_Description': 'string', 'Features': [{'Name': 'string', 'Value': 'string'}]}";

    $base_instruction = "Act as an E-commerce Assistant. Analyze the product: \"{$product_title}\". 
    
    {$tone_instruction}
    {$preset_context}
    
    {$seo_prompt_part}
    {$desc_prompt_part}
    
    {$language_instruction}
    
    TASK:
    1. Generate descriptions.
    2. Generate features. 
       
       Input Constraints:
       {$specialized_features_prompt} 
       (If keys provided above, strictly fill ONLY those. Otherwise, find technical specs).

       Technical Data Rules:
       - Values must be PRECISE and FACTUAL.
       - USE GOOGLE SEARCH TOOL.
       
       {$formatting_rules}
       
       - DO NOT use generic descriptors like 'High res'. Use NUMBERS.

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
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$selected_model}:generateContent?key=" . $api_key;

    $response = wp_remote_post($url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => $request_json,
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'WP Error: ' . $response->get_error_message());
    }

    // ---------------------------------------------------------
    // 6. JSON PARSING & DATA EXTRACTION
    // ---------------------------------------------------------
    
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
         return new WP_Error('api_error', 'Invalid API response structure. Check quota.');
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

    $product = wc_get_product($product_id);
    if (!$product) return new WP_Error('not_found', 'Product not found.');

    $overwrite = get_option('gpa_overwrite_data', 0);

    if ($overwrite || empty($product->get_short_description())) {
        $product->set_short_description(wp_kses_post($features_json['Short_Description'] ?? ''));
    }
    
    if ($overwrite || empty($product->get_description())) {
        $desc = $features_json['Long_Description'] ?? '';
        if (strpos($desc, '<p>') === false && !empty($desc)) $desc = nl2br($desc);
        $product->set_description(wp_kses_post($desc));
    }

    $editable_features = [];
    if (!empty($features_json['Features']) && is_array($features_json['Features'])) {
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
        update_post_meta($product_id, '_gpa_editable_features', $editable_features);
    }
    
    $product->save();

    // ---------------------------------------------------------
    // 8. PRO FEATURES: SAVING EXTRAS
    // ---------------------------------------------------------

    $reload_required = false;

    return [
        'success' => true,
        'message' => __('Content generated successfully!', 'gemini-product-autocomplete'),
        'short_description' => $features_json['Short_Description'] ?? '',
        'long_description' => $features_json['Long_Description'] ?? '',
        'smm_post' => $features_json['Social_Media_Post'] ?? '', 
        'features' => $editable_features,
        'reload_required' => $reload_required,
        'attr_msg' => __('Attributes created. Reloading...', 'gemini-product-autocomplete')
    ];
}