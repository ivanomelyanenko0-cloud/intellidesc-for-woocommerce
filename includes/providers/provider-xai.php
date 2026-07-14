<?php
// includes/providers/provider-xai.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calls the xAI Grok API — an OpenAI-Chat-Completions-compatible endpoint.
 * Thin wrapper over the shared builder in provider-openai.php.
 *
 * @return string|WP_Error Raw text response on success, WP_Error on failure.
 */
function ildesc_ai_call_xai( $model, $prompt, $api_key, $options = [] ) {
    return ildesc_ai_call_openai_compatible( 'https://api.x.ai/v1/chat/completions', 'xai', $model, $prompt, $api_key, $options );
}
