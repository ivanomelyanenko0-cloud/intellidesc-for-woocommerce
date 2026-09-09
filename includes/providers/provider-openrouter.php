<?php
// includes/providers/provider-openrouter.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calls the OpenRouter API — a router in front of many underlying models,
 * on its own Chat-Completions-shaped endpoint. Unlike xAI, OpenRouter has no
 * separate Responses API, so web search is requested via its "web" plugin on
 * the same endpoint instead of switching endpoints like
 * ildesc_ai_call_openai_compatible() does for OpenAI/xAI — that's why this
 * doesn't reuse that shared builder.
 *
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_openrouter( $model, $prompt, $api_key, $options = [] ) {
    $timeout         = $options['timeout'] ?? 60;
    $fallback_model  = $options['fallback_model'] ?? '';
    $max_tokens      = $options['max_tokens'] ?? 4096;
    $use_search_tool = ! empty( $options['use_search_tool'] );

    $models_to_try = ( ! empty( $fallback_model ) && $model !== $fallback_model ) ? [ $model, $fallback_model ] : [ $model ];

    $response      = null;
    $http_code     = 0;
    $response_body = '';
    $model_attempt = $model;

    foreach ( $models_to_try as $model_attempt ) {
        $request_body_array = [
            'model'      => $model_attempt,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens' => $max_tokens,
        ];

        if ( $use_search_tool ) {
            $request_body_array['plugins'] = [ [ 'id' => 'web' ] ];
        }

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'IntelliDesc for WooCommerce',
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
        return ildesc_ai_error_from_response( 'openrouter', $http_code, $response_body, $model_attempt );
    }

    $data = json_decode( $response_body, true );

    if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
        /* translators: %s: AI provider name (e.g. OpenAI, Grok) */
        return new WP_Error( 'api_error', sprintf( __( 'Unexpected API response structure from %s.', 'intellidesc-for-woocommerce' ), ildesc_ai_provider_label( 'openrouter' ) ) );
    }

    return $data['choices'][0]['message']['content'];
}
