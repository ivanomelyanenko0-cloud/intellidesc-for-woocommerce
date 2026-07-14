<?php
// includes/providers/provider-gemini.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calls the Google Gemini generateContent API.
 *
 * @param string $model    Gemini model id.
 * @param string $prompt   Plain-text user prompt (already fully built).
 * @param string $api_key  Gemini API key.
 * @param array  $options  ['timeout', 'use_search_tool', 'fallback_model'].
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_gemini( $model, $prompt, $api_key, $options = [] ) {
    $timeout         = $options['timeout'] ?? 60;
    $use_search_tool = $options['use_search_tool'] ?? true;
    $fallback_model  = $options['fallback_model'] ?? 'gemini-2.5-flash';

    $request_body_array = [
        'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ] ],
    ];
    if ( $use_search_tool ) {
        $request_body_array['tools'] = [ [ 'google_search' => (object) [] ] ];
    }
    $request_json = wp_json_encode( $request_body_array );

    $models_to_try = ( ! empty( $fallback_model ) && $model !== $fallback_model ) ? [ $model, $fallback_model ] : [ $model ];

    $response      = null;
    $http_code     = 0;
    $response_body = '';

    foreach ( $models_to_try as $model_attempt ) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_attempt}:generateContent?key=" . rawurlencode( $api_key );
        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $request_json,
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
        return ildesc_ai_error_from_response( 'gemini', $http_code, $response_body );
    }

    $data = json_decode( $response_body, true );

    if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
        $finish_reason = $data['candidates'][0]['finishReason'] ?? '';
        if ( $finish_reason === 'SAFETY' ) {
            return new WP_Error( 'api_safety', __( 'Gemini blocked the response due to safety filters. Try rephrasing the product title.', 'intellidesc-for-woocommerce' ) );
        }
        if ( $finish_reason === 'RECITATION' ) {
            return new WP_Error( 'api_recitation', __( 'Gemini blocked the response due to recitation policy. Try a more specific product title.', 'intellidesc-for-woocommerce' ) );
        }
        return new WP_Error( 'api_error', __( 'Unexpected API response structure. Check your quota at Google AI Studio.', 'intellidesc-for-woocommerce' ) );
    }

    return $data['candidates'][0]['content']['parts'][0]['text'];
}
