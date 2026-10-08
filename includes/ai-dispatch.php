<?php
// includes/ai-dispatch.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Every provider slug the plugin knows, in Settings dropdown order.
 */
function ildesc_ai_providers() {
    return [ ILDESC_WP_AI_PROVIDER, 'gemini', 'anthropic', 'openai', 'xai', 'openrouter' ];
}

/**
 * Returns the currently configured AI provider slug.
 *
 * A saved choice is kept as-is — including 'wp_ai' when the AI Client has
 * since become unavailable, so the merchant gets a clear error instead of
 * being silently billed on another account. With nothing saved, a fresh
 * install (no plugin API key yet) uses the site's WordPress AI connector if
 * one is ready; everything else resolves to 'gemini', as it always has.
 */
function ildesc_get_current_provider() {
    $provider = get_option( ILDESC_AI_PROVIDER, null );
    if ( null === $provider || '' === $provider ) {
        return ( ! ildesc_has_any_plugin_api_key() && ildesc_wp_ai_is_ready() ) ? ILDESC_WP_AI_PROVIDER : 'gemini';
    }
    return in_array( $provider, ildesc_ai_providers(), true ) ? $provider : 'gemini';
}

/**
 * Whether an API key is saved for any of the plugin's own providers.
 */
function ildesc_has_any_plugin_api_key() {
    foreach ( ildesc_ai_providers() as $provider ) {
        if ( ildesc_provider_needs_api_key( $provider ) && '' !== trim( (string) ildesc_get_api_key_for_provider( $provider ) ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Returns the stored API key for the given provider.
 */
function ildesc_get_api_key_for_provider( $provider ) {
    switch ( $provider ) {
        case ILDESC_WP_AI_PROVIDER:
            return '';
        case 'anthropic':
            return get_option( ILDESC_ANTHROPIC_API_KEY, '' );
        case 'openai':
            return get_option( ILDESC_OPENAI_API_KEY, '' );
        case 'xai':
            return get_option( ILDESC_XAI_API_KEY, '' );
        case 'openrouter':
            return get_option( ILDESC_OPENROUTER_API_KEY, '' );
        case 'gemini':
        default:
            return get_option( ILDESC_SETTINGS_KEY, '' );
    }
}

/**
 * Returns the stored model id for the given provider, with a sane default.
 */
function ildesc_get_model_for_provider( $provider ) {
    switch ( $provider ) {
        case ILDESC_WP_AI_PROVIDER:
            return ''; // Chosen by the site's connector.
        case 'anthropic':
            return get_option( ILDESC_ANTHROPIC_MODEL, 'claude-sonnet-4-5-20250929' );
        case 'openai':
            return get_option( ILDESC_OPENAI_MODEL, 'gpt-4.1-mini' );
        case 'xai':
            return get_option( ILDESC_XAI_MODEL, 'grok-4-fast' );
        case 'openrouter':
            return get_option( ILDESC_OPENROUTER_MODEL, 'openai/gpt-4.1-mini' );
        case 'gemini':
        default:
            return get_option( ILDESC_SELECTED_MODEL, 'gemini-3.1-flash-lite' );
    }
}

/**
 * Returns a known-good fallback model id for the given provider, used to
 * retry a request when the user's configured model 404s (e.g. deprecated or
 * mistyped). Mirrors the same literals used as ildesc_get_model_for_provider()'s
 * defaults, since those are already the plugin's vetted "safe" choice per provider.
 */
function ildesc_get_fallback_model_for_provider( $provider ) {
    switch ( $provider ) {
        case ILDESC_WP_AI_PROVIDER:
            return '';
        case 'anthropic':
            return 'claude-sonnet-4-5-20250929';
        case 'openai':
            return 'gpt-4.1-mini';
        case 'xai':
            return 'grok-4-fast';
        case 'openrouter':
            return 'openai/gpt-4.1-mini';
        case 'gemini':
        default:
            return 'gemini-3.1-flash-lite';
    }
}

/**
 * Human-readable provider name for use in messages/labels.
 */
function ildesc_ai_provider_label( $provider ) {
    $labels = [
        'gemini'    => 'Gemini',
        'anthropic' => 'Claude',
        'openai'    => 'OpenAI',
        'xai'       => 'Grok',
        'openrouter' => 'OpenRouter',
        ILDESC_WP_AI_PROVIDER => 'WordPress AI',
    ];
    return $labels[ $provider ] ?? ucfirst( $provider );
}

/**
 * Records that a given provider/model combination just failed with a
 * "model not found" (404) response, so the Settings page can warn about it
 * even outside the request that triggered the failure.
 */
function ildesc_set_model_unavailable_flag( $provider, $model, $message ) {
    $flags = get_option( ILDESC_MODEL_DEPRECATION_FLAGS, [] );
    $flags[ $provider ] = [
        'model'   => $model,
        'message' => $message,
        'time'    => time(),
    ];
    update_option( ILDESC_MODEL_DEPRECATION_FLAGS, $flags, false );
}

/**
 * Returns the recorded unavailable-model flag for a provider, or null.
 */
function ildesc_get_model_unavailable_flag( $provider ) {
    $flags = get_option( ILDESC_MODEL_DEPRECATION_FLAGS, [] );
    return $flags[ $provider ] ?? null;
}

/**
 * Clears a provider's unavailable-model flag, e.g. after a successful call.
 */
function ildesc_clear_model_unavailable_flag( $provider ) {
    $flags = get_option( ILDESC_MODEL_DEPRECATION_FLAGS, [] );
    if ( isset( $flags[ $provider ] ) ) {
        unset( $flags[ $provider ] );
        update_option( ILDESC_MODEL_DEPRECATION_FLAGS, $flags, false );
    }
}

/**
 * Returns the id of the model considered "most current" for a provider,
 * based on the ordering already applied when the model list was fetched
 * (see ildesc_get_{provider}_models() in admin-settings.php: newest-first
 * for Anthropic's API order, or a lexical krsort() for the others). This is
 * a heuristic, not a guaranteed release-date comparison.
 */
function ildesc_get_recommended_model( $models ) {
    if ( empty( $models ) ) {
        return null;
    }
    $keys = array_keys( $models );
    return $keys[0];
}

/**
 * Returns the model id the user has already dismissed the advisor
 * suggestion for, on this provider (or null if none dismissed yet).
 */
function ildesc_get_model_advisor_dismissed( $provider ) {
    $dismissed = get_option( ILDESC_MODEL_ADVISOR_DISMISSED, [] );
    return $dismissed[ $provider ] ?? null;
}

/**
 * Records that the user dismissed the "a newer model is available"
 * suggestion for a given provider/model, so it won't be shown again unless
 * an even newer model later takes the top spot.
 */
function ildesc_set_model_advisor_dismissed( $provider, $model ) {
    $dismissed = get_option( ILDESC_MODEL_ADVISOR_DISMISSED, [] );
    $dismissed[ $provider ] = $model;
    update_option( ILDESC_MODEL_ADVISOR_DISMISSED, $dismissed, false );
}

/**
 * Dispatches a single-turn text completion request to the given provider.
 *
 * @param string $provider 'gemini' | 'anthropic' | 'openai' | 'xai' | 'openrouter'.
 * @param string $model    Provider-specific model id.
 * @param string $prompt   Plain-text user prompt (already fully built).
 * @param string $api_key  Raw API key for that provider.
 * @param array  $options  ['timeout' => 60, 'use_search_tool' => true, 'fallback_model' => '...'].
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call( $provider, $model, $prompt, $api_key, $options = [] ) {
    ildesc_usage_take(); // Drop anything left over from a previous call.

    switch ( $provider ) {
        case ILDESC_WP_AI_PROVIDER:
            $result = ildesc_ai_call_wp_ai( $prompt, $options );
            break;
        case 'anthropic':
            $result = ildesc_ai_call_anthropic( $model, $prompt, $api_key, $options );
            break;
        case 'openai':
            $result = ildesc_ai_call_openai( $model, $prompt, $api_key, $options );
            break;
        case 'xai':
            $result = ildesc_ai_call_xai( $model, $prompt, $api_key, $options );
            break;
        case 'openrouter':
            $result = ildesc_ai_call_openrouter( $model, $prompt, $api_key, $options );
            break;
        case 'gemini':
        default:
            $provider = 'gemini';
            $result   = ildesc_ai_call_gemini( $model, $prompt, $api_key, $options );
            break;
    }

    ildesc_usage_record_call( $provider, $model, $result, ildesc_usage_take() );

    return $result;
}

/**
 * Builds a WP_Error with a provider-aware, HTTP-status-specific message.
 * Shared across all provider implementations so error copy stays consistent.
 */
function ildesc_ai_error_from_response( $provider, $http_code, $response_body, $model = '' ) {
    $label      = ildesc_ai_provider_label( $provider );
    $error_data = json_decode( $response_body, true );

    $raw_message = $error_data['error']['message'] ?? '';
    if ( empty( $raw_message ) && is_string( $error_data['error'] ?? null ) ) {
        $raw_message = $error_data['error'];
    }
    $api_details = $raw_message ? ' — ' . $raw_message : '';

    // Some providers (e.g. xAI) report an invalid/missing API key under a
    // generic 400 status instead of 401. Detect that from the message text
    // so the user still gets an actionable "check your API key" message
    // instead of a misleading "bad request" one.
    if ( $http_code !== 401 && $raw_message && preg_match( '/api key|api_key|authentication|unauthorized/i', $raw_message ) ) {
        $http_code = 401;
    }

    if ( $http_code === 404 ) {
        // Record the failure so the Settings page can warn about it even
        // outside of this one request (see ildesc_get_model_unavailable_flag()).
        if ( $model !== '' ) {
            ildesc_set_model_unavailable_flag( $provider, $model, $raw_message );
        }

        // Google sometimes deprecates a model for new accounts only, while it
        // stays listed (and still works for older accounts) in the models API —
        // so our cached model list won't have dropped it. Surface this as an
        // actionable "pick another model" message instead of the generic 404.
        if ( $raw_message && preg_match( '/no longer available/i', $raw_message ) ) {
            /* translators: %s: AI provider name (e.g. Gemini, Claude) */
            $message = sprintf( __( 'This %s model is no longer available for your account. Go to WooCommerce → IntelliDesc, click "Refresh models", and select a different model from the dropdown.', 'intellidesc-for-woocommerce' ), $label );
            return new WP_Error( 'api_http_404_model_deprecated', $message . $api_details );
        }
    }

    $generic_messages = [
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        400 => sprintf( __( 'Bad request to %s. The product title or prompt contains invalid characters.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        401 => sprintf( __( 'Invalid %s API key. Go to WooCommerce → IntelliDesc and check your API key.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        403 => sprintf( __( 'Access denied by %s. Check your account permissions/billing.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        404 => sprintf( __( 'Model not found on %s. It may have been renamed or removed.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        429 => sprintf( __( '%s rate limit exceeded. Wait a moment and try again.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        500 => sprintf( __( '%s internal server error. Try again in a few moments.', 'intellidesc-for-woocommerce' ), $label ),
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        503 => sprintf( __( '%s is temporarily unavailable (overloaded or maintenance).', 'intellidesc-for-woocommerce' ), $label ),
    ];

    $message = $generic_messages[ $http_code ]
        ?? sprintf(
            /* translators: 1: AI provider name, 2: HTTP status code */
            __( '%1$s returned an unexpected error (HTTP %2$d).', 'intellidesc-for-woocommerce' ),
            $label,
            $http_code
        );

    return new WP_Error( 'api_http_' . $http_code, $message . $api_details );
}


/**
 * Whether the provider authenticates with an API key stored by this plugin.
 */
function ildesc_provider_needs_api_key( $provider ) {
    return ILDESC_WP_AI_PROVIDER !== $provider;
}

/**
 * The prompt line that pins the output language (Settings → Content language,
 * or the site locale when left on "default").
 */
function ildesc_build_language_instruction() {
    $selected_lang   = get_option( ILDESC_CONTENT_LANGUAGE, 'default' );
    $target_language = ( $selected_lang === 'default' ) ? substr( get_locale(), 0, 2 ) : $selected_lang;
    $target_language = ! empty( $target_language ) ? sanitize_text_field( $target_language ) : 'en';
    return "IMPORTANT: Write ALL content in language code: '{$target_language}'.";
}

/**
 * Extracts and decodes the JSON object from a raw model response, tolerating
 * Markdown fences, surrounding prose and C-style comments.
 *
 * @return array|WP_Error Decoded object, or WP_Error('json_parse').
 */
function ildesc_parse_ai_json( $raw_text ) {
    $clean_json = preg_replace( '/^```json\s*|\s*```$/i', '', trim( (string) $raw_text ) );
    $clean_json = str_replace( array( '```', '`' ), '', $clean_json );

    $start = strpos( $clean_json, '{' );
    $end   = strrpos( $clean_json, '}' );

    if ( $start === false || $end === false ) {
        return new WP_Error( 'json_parse', __( 'JSON Parsing Error.', 'intellidesc-for-woocommerce' ) );
    }

    $json_string = substr( $clean_json, $start, $end - $start + 1 );
    $json_string = preg_replace( '!/\*.*?\*/!s', '', $json_string );
    $decoded     = json_decode( $json_string, true );

    if ( empty( $decoded ) || ! is_array( $decoded ) ) {
        return new WP_Error( 'json_parse', __( 'JSON Decode Error.', 'intellidesc-for-woocommerce' ) );
    }

    return $decoded;
}

/**
 * The prompt line that sets the tone of voice (a PRO setting; Free always
 * uses the neutral tone).
 */
function ildesc_build_tone_instruction() {
    $tone_instruction = "Tone: Informative, professional, neutral. Avoid marketing fluff.";
    if ( function_exists( 'ildesc_fs' ) && ildesc_fs()->is_premium() ) {
        $tone = get_option( 'ildesc_tone_of_voice', 'neutral' );
        if ( $tone === 'persuasive' ) $tone_instruction = "Tone: Persuasive, sales-oriented, engaging.";
        elseif ( $tone === 'playful' ) $tone_instruction = "Tone: Playful, fun, creative.";
        elseif ( $tone === 'luxury' ) $tone_instruction = "Tone: Luxury, elegant, sophisticated.";
        elseif ( $tone === 'minimalist' ) $tone_instruction = "Tone: Minimalist, short, punchy.";
    }
    return $tone_instruction;
}
