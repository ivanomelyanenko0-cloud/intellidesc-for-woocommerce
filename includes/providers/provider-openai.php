<?php
// includes/providers/provider-openai.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calls an OpenAI-compatible Chat Completions endpoint. Shared by the OpenAI
 * and xAI Grok providers since xAI's API is a compatible clone of OpenAI's.
 *
 * @param string $base_url      Chat Completions endpoint URL.
 * @param string $provider_slug 'openai' | 'xai' — used for error labeling.
 * @param string $model         Model id.
 * @param string $prompt        Plain-text user prompt (already fully built).
 * @param string $api_key       Bearer API key.
 * @param array  $options       ['timeout', 'fallback_model', 'max_tokens'].
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_openai_compatible( $base_url, $provider_slug, $model, $prompt, $api_key, $options = [] ) {
    $timeout        = $options['timeout'] ?? 60;
    $fallback_model = $options['fallback_model'] ?? '';
    $max_tokens     = $options['max_tokens'] ?? 4096;

    $models_to_try = ( ! empty( $fallback_model ) && $model !== $fallback_model ) ? [ $model, $fallback_model ] : [ $model ];

    $response      = null;
    $http_code     = 0;
    $response_body = '';

    foreach ( $models_to_try as $model_attempt ) {
        $request_body_array = [
            'model'      => $model_attempt,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens' => $max_tokens,
        ];

        $response = wp_remote_post( $base_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode( $request_body_array ),
            'timeout' => $timeout,
        ] );

        if ( is_wp_error( $response ) ) {
            continue;
        }

        $http_code     = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $http_code === 200 ) {
            break;
        }
    }

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'api_error', __( 'Connection failed: ', 'intellidesc-for-woocommerce' ) . $response->get_error_message() );
    }

    if ( $http_code !== 200 ) {
        return ildesc_ai_error_from_response( $provider_slug, $http_code, $response_body, $model_attempt );
    }

    $data = json_decode( $response_body, true );

    if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
        /* translators: %s: AI provider name (e.g. OpenAI, Grok) */
        return new WP_Error( 'api_error', sprintf( __( 'Unexpected API response structure from %s.', 'intellidesc-for-woocommerce' ), ildesc_ai_provider_label( $provider_slug ) ) );
    }

    return $data['choices'][0]['message']['content'];
}

/**
 * Calls the OpenAI Chat Completions API.
 *
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_openai( $model, $prompt, $api_key, $options = [] ) {
    return ildesc_ai_call_openai_compatible( 'https://api.openai.com/v1/chat/completions', 'openai', $model, $prompt, $api_key, $options );
}
