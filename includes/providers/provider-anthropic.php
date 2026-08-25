<?php
// includes/providers/provider-anthropic.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calls the Anthropic Messages API.
 *
 * @param string $model    Claude model id.
 * @param string $prompt   Plain-text user prompt (already fully built).
 * @param string $api_key  Anthropic API key.
 * @param array  $options  ['timeout', 'fallback_model', 'max_tokens', 'use_search_tool'].
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_anthropic( $model, $prompt, $api_key, $options = [] ) {
    $timeout         = $options['timeout'] ?? 60;
    $fallback_model  = $options['fallback_model'] ?? '';
    $max_tokens      = $options['max_tokens'] ?? 4096;
    $use_search_tool = ! empty( $options['use_search_tool'] );

    $models_to_try = ( ! empty( $fallback_model ) && $model !== $fallback_model ) ? [ $model, $fallback_model ] : [ $model ];

    $response      = null;
    $http_code     = 0;
    $response_body = '';

    foreach ( $models_to_try as $model_attempt ) {
        $request_body_array = [
            'model'      => $model_attempt,
            'max_tokens' => $max_tokens,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ];

        if ( $use_search_tool ) {
            $request_body_array['tools'] = [
                [ 'type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3 ],
            ];
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
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
        return ildesc_ai_error_from_response( 'anthropic', $http_code, $response_body, $model_attempt );
    }

    $data = json_decode( $response_body, true );

    $text = '';
    if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
        foreach ( $data['content'] as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' && ! empty( $block['text'] ) ) {
                $text .= $block['text'];
            }
        }
    }

    if ( $text === '' ) {
        return new WP_Error( 'api_error', __( 'Unexpected API response structure from Claude.', 'intellidesc-for-woocommerce' ) );
    }

    return $text;
}
