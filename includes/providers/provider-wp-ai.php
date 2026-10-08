<?php
// includes/providers/provider-wp-ai.php
// WordPress AI Client (core, WP 7.0+). The provider, credentials and model
// all come from the site's Settings → Connectors configuration — this file
// never sees an API key, an endpoint or a model id. Core functions and
// classes are referenced by name only, because the plugin still supports
// WordPress versions that don't ship them.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Whether this WordPress ships the AI Client and allows AI on this request.
 */
function ildesc_wp_ai_available() {
    return function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && call_user_func( 'wp_supports_ai' );
}

/**
 * Whether a Connectors-configured provider can actually serve a text
 * prompt. Cached per request — it walks the provider registry.
 */
function ildesc_wp_ai_is_ready() {
    static $ready = null;
    if ( null === $ready ) {
        $ready = ildesc_wp_ai_available() && true === ildesc_wp_ai_builder( 'ok', false )->is_supported_for_text_generation();
    }
    return $ready;
}

/**
 * Whether the configured connector can run a text prompt with web search.
 * Remembered for a day (it only changes when the site owner reconfigures
 * Connectors), so the Settings page can say so without probing every load.
 */
function ildesc_wp_ai_supports_search() {
    $cached = get_transient( 'ildesc_wp_ai_search_support' );
    if ( false !== $cached ) {
        return 'yes' === $cached;
    }
    $supported = ildesc_wp_ai_is_ready() && true === ildesc_wp_ai_builder( 'ok', true )->is_supported_for_text_generation();
    set_transient( 'ildesc_wp_ai_search_support', $supported ? 'yes' : 'no', DAY_IN_SECONDS );
    return $supported;
}

/**
 * Starts a prompt builder, optionally requiring web search.
 *
 * @return WP_AI_Client_Prompt_Builder
 */
function ildesc_wp_ai_builder( $prompt, $with_search, $timeout = 0 ) {
    $builder = call_user_func( 'wp_ai_client_prompt', $prompt );

    $web_search_class = 'WordPress\AiClient\Tools\DTO\WebSearch';
    if ( $with_search && class_exists( $web_search_class ) ) {
        $builder->using_web_search( new $web_search_class() );
    }

    // Core's default is 30s — too short for a grounded, search-backed answer.
    $request_options_class = 'WordPress\AiClient\Providers\Http\DTO\RequestOptions';
    if ( $timeout > 0 && class_exists( $request_options_class ) ) {
        $builder->using_request_options( $request_options_class::fromArray( [ 'timeout' => (float) $timeout ] ) );
    }

    return $builder;
}

/**
 * Sends a prompt through the WordPress AI Client and returns the raw text.
 * $options['use_search_tool'] asks for web search; if the configured
 * connector can't search, the request is sent without it rather than failed.
 * max_tokens is deliberately not forwarded: on reasoning models the cap also
 * covers hidden thinking tokens and truncates the JSON answer.
 *
 * @return string|WP_Error
 */
function ildesc_ai_call_wp_ai( $prompt, $options = [] ) {
    if ( ! ildesc_wp_ai_available() ) {
        return new WP_Error( 'wp_ai_unavailable', __( 'The WordPress AI Client is not available on this site (it needs WordPress 7.0+ with AI features enabled). Choose another AI provider in IntelliDesc settings.', 'intellidesc-for-woocommerce' ) );
    }

    $timeout = $options['timeout'] ?? 120;
    $builder = null;

    if ( ! empty( $options['use_search_tool'] ) ) {
        $builder = ildesc_wp_ai_builder( $prompt, true, $timeout );
        if ( true !== $builder->is_supported_for_text_generation() ) {
            $builder = null;
        }
    }
    if ( null === $builder ) {
        $builder = ildesc_wp_ai_builder( $prompt, false, $timeout );
        if ( true !== $builder->is_supported_for_text_generation() ) {
            return new WP_Error( 'wp_ai_not_configured', __( 'No AI connector is configured under Settings → Connectors. Set one up there, or choose another AI provider in IntelliDesc settings.', 'intellidesc-for-woocommerce' ) );
        }
    }

    $result = $builder->generate_text_result();

    if ( is_wp_error( $result ) ) {
        $data   = $result->get_error_data();
        $status = is_array( $data ) && ! empty( $data['status'] ) ? (int) $data['status'] : 500;
        return new WP_Error(
            'api_http_' . $status,
            sprintf(
                /* translators: %s: error message from the WordPress AI Client */
                __( 'WordPress AI request failed: %s', 'intellidesc-for-woocommerce' ),
                $result->get_error_message()
            )
        );
    }

    // Recorded as "connector/model" so the usage page shows what actually answered.
    // Completion tokens already include any thinking tokens.
    $usage = $result->getTokenUsage();
    ildesc_usage_capture(
        $result->getProviderMetadata()->getId() . '/' . $result->getModelMetadata()->getId(),
        $usage->getPromptTokens(),
        $usage->getCompletionTokens()
    );

    try {
        return $result->toText();
    } catch ( Exception $e ) {
        return new WP_Error( 'api_error', __( 'The WordPress AI Client returned no text.', 'intellidesc-for-woocommerce' ) );
    }
}
