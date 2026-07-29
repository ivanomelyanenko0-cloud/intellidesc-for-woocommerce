<?php
// includes/ai-dispatch.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the currently configured AI provider slug.
 * Absence of the option resolves to 'gemini' for backward compatibility
 * with installs that predate multi-provider support.
 */
function ildesc_get_current_provider() {
    $provider = get_option( ILDESC_AI_PROVIDER, 'gemini' );
    $valid    = [ 'gemini', 'anthropic', 'openai', 'xai' ];
    return in_array( $provider, $valid, true ) ? $provider : 'gemini';
}

/**
 * Returns the stored API key for the given provider.
 */
function ildesc_get_api_key_for_provider( $provider ) {
    switch ( $provider ) {
        case 'anthropic':
            return get_option( ILDESC_ANTHROPIC_API_KEY, '' );
        case 'openai':
            return get_option( ILDESC_OPENAI_API_KEY, '' );
        case 'xai':
            return get_option( ILDESC_XAI_API_KEY, '' );
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
        case 'anthropic':
            return get_option( ILDESC_ANTHROPIC_MODEL, 'claude-sonnet-4-5-20250929' );
        case 'openai':
            return get_option( ILDESC_OPENAI_MODEL, 'gpt-4.1-mini' );
        case 'xai':
            return get_option( ILDESC_XAI_MODEL, 'grok-4-fast' );
        case 'gemini':
        default:
            return get_option( ILDESC_SELECTED_MODEL, 'gemini-3.1-flash-lite' );
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
 * Dispatches a single-turn text completion request to the given provider.
 *
 * @param string $provider 'gemini' | 'anthropic' | 'openai' | 'xai'.
 * @param string $model    Provider-specific model id.
 * @param string $prompt   Plain-text user prompt (already fully built).
 * @param string $api_key  Raw API key for that provider.
 * @param array  $options  ['timeout' => 60, 'use_search_tool' => true, 'fallback_model' => '...'].
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call( $provider, $model, $prompt, $api_key, $options = [] ) {
    switch ( $provider ) {
        case 'anthropic':
            return ildesc_ai_call_anthropic( $model, $prompt, $api_key, $options );
        case 'openai':
            return ildesc_ai_call_openai( $model, $prompt, $api_key, $options );
        case 'xai':
            return ildesc_ai_call_xai( $model, $prompt, $api_key, $options );
        case 'gemini':
        default:
            return ildesc_ai_call_gemini( $model, $prompt, $api_key, $options );
    }
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

