<?php
// includes/usage-tracking.php
// Records per-day API usage (calls, failures, tokens) per provider/model in a
// single non-autoloaded option. Identical in the Free and Pro trees — the
// admin page that displays the data lives in usage-page.php, which differs.
//
// Flow: a provider implementation calls ildesc_usage_capture() once it has a
// successful HTTP response (it is the only place that knows the real model
// used after a fallback retry, and the provider-specific usage fields).
// ildesc_ai_call() then picks that up with ildesc_usage_take() and writes one
// combined record per call via ildesc_usage_record_call().

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Internal single-slot buffer between a provider implementation and
 * ildesc_ai_call(). Pass an array to store it; call with no argument to read
 * and clear it.
 */
function ildesc_usage_buffer( $set = null ) {
    static $buffer = null;
    if ( $set !== null ) {
        $buffer = $set;
        return null;
    }
    $value  = $buffer;
    $buffer = null;
    return $value;
}

/**
 * Called by a provider after a successful (HTTP 200) response.
 *
 * @param string     $model         Model that actually answered (may be the fallback).
 * @param int        $input_tokens  Prompt tokens billed.
 * @param int        $output_tokens Completion tokens billed (incl. reasoning tokens).
 * @param float|null $cost          Real cost in USD if the provider reports one (OpenRouter).
 * @param int        $searches      Web searches the provider ran for this call (billed separately from tokens).
 */
function ildesc_usage_capture( $model, $input_tokens, $output_tokens, $cost = null, $searches = 0 ) {
    ildesc_usage_buffer( [
        'model'    => (string) $model,
        'in'       => max( 0, (int) $input_tokens ),
        'out'      => max( 0, (int) $output_tokens ),
        'cost'     => $cost === null ? 0.0 : max( 0.0, (float) $cost ),
        'searches' => max( 0, (int) $searches ),
    ] );
}

/**
 * Returns and clears whatever the last provider call captured, or null.
 */
function ildesc_usage_take() {
    return ildesc_usage_buffer();
}

/**
 * Writes the usage record for one ildesc_ai_call().
 *
 * "Calls" counts every call made by the plugin; "errors" the ones that ended
 * in a WP_Error (including a 200 response whose text was blocked/empty, whose
 * tokens are still billed and therefore still recorded).
 */
function ildesc_usage_record_call( $provider, $requested_model, $result, $captured ) {
    $model = ( is_array( $captured ) && $captured['model'] !== '' ) ? $captured['model'] : (string) $requested_model;
    if ( $model === '' ) {
        $model = 'unknown';
    }

    ildesc_usage_last_call( [
        'provider' => $provider,
        'ok'       => ! is_wp_error( $result ),
        'in'       => is_array( $captured ) ? $captured['in'] : 0,
        'out'      => is_array( $captured ) ? $captured['out'] : 0,
        'cost'     => is_array( $captured ) ? $captured['cost'] : 0.0,
        'searches' => is_array( $captured ) ? $captured['searches'] : 0,
    ] );

    ildesc_usage_add( $provider, $model, [
        'calls'    => 1,
        'errors'   => is_wp_error( $result ) ? 1 : 0,
        'in'       => is_array( $captured ) ? $captured['in'] : 0,
        'out'      => is_array( $captured ) ? $captured['out'] : 0,
        'cost'     => is_array( $captured ) ? $captured['cost'] : 0.0,
        'searches' => is_array( $captured ) ? $captured['searches'] : 0,
    ] );
}

function ildesc_usage_empty_bucket() {
    return [ 'calls' => 0, 'errors' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0, 'searches' => 0 ];
}

/**
 * Adds a delta to today's bucket for a provider/model and prunes days older
 * than the retention window. Concurrent requests can occasionally lose an
 * increment (read-modify-write on one option) — acceptable for statistics.
 */
function ildesc_usage_add( $provider, $model, $delta ) {
    $stats = get_option( ILDESC_USAGE_STATS, [] );
    if ( ! is_array( $stats ) ) {
        $stats = [];
    }

    $day    = wp_date( 'Y-m-d' );
    $bucket = $stats[ $day ][ $provider ][ $model ] ?? ildesc_usage_empty_bucket();
    foreach ( $delta as $key => $value ) {
        $bucket[ $key ] = ( $bucket[ $key ] ?? 0 ) + $value;
    }
    $stats[ $day ][ $provider ][ $model ] = $bucket;

    $cutoff = wp_date( 'Y-m-d', time() - ILDESC_USAGE_RETENTION_DAYS * DAY_IN_SECONDS );
    foreach ( array_keys( $stats ) as $stored_day ) {
        if ( (string) $stored_day < $cutoff ) {
            unset( $stats[ $stored_day ] );
        }
    }

    update_option( ILDESC_USAGE_STATS, $stats, false );
}

/**
 * Returns stored stats limited to the last $days days (today included),
 * as [ 'Y-m-d' => [ provider => [ model => bucket ] ] ].
 */
function ildesc_usage_get_stats( $days ) {
    $stats = get_option( ILDESC_USAGE_STATS, [] );
    if ( ! is_array( $stats ) ) {
        return [];
    }
    $cutoff = wp_date( 'Y-m-d', time() - ( max( 1, (int) $days ) - 1 ) * DAY_IN_SECONDS );
    $slice  = [];
    foreach ( $stats as $day => $providers ) {
        if ( (string) $day >= $cutoff ) {
            $slice[ $day ] = $providers;
        }
    }
    ksort( $slice );
    return $slice;
}

/**
 * Sums a stats slice into [ provider => bucket ].
 */
function ildesc_usage_totals_by_provider( $stats ) {
    $totals = [];
    foreach ( $stats as $providers ) {
        foreach ( $providers as $provider => $models ) {
            foreach ( $models as $bucket ) {
                if ( ! isset( $totals[ $provider ] ) ) {
                    $totals[ $provider ] = ildesc_usage_empty_bucket();
                }
                foreach ( $bucket as $key => $value ) {
                    $totals[ $provider ][ $key ] = ( $totals[ $provider ][ $key ] ?? 0 ) + $value;
                }
            }
        }
    }
    return $totals;
}

/**
 * admin-post.php handler: wipes all recorded usage statistics.
 */
add_action( 'admin_post_ildesc_usage_reset', 'ildesc_handle_usage_reset' );
function ildesc_handle_usage_reset() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'intellidesc-for-woocommerce' ), '', [ 'response' => 403 ] );
    }
    check_admin_referer( 'ildesc_usage_reset' );

    delete_option( ILDESC_USAGE_STATS );
    delete_option( ILDESC_USAGE_FIELD_STATS );

    wp_safe_redirect( add_query_arg( [ 'page' => 'ildesc_usage_page', 'reset' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
}

// ------------------------------------------------------------------
// SPEND PER FIELD
// ------------------------------------------------------------------
// One generation request returns every field (descriptions, features, SMM,
// SEO) in a single billed call, so per-field spend can only be estimated:
// the call's usage is split by each field's share of the response text. The
// FAQ is its own request and is therefore attributed exactly. Stored in a
// separate option, [ 'Y-m-d' => [ provider => [ field => bucket ] ] ], because
// the main stats' readers sum every bucket key as a number.

/**
 * Remembers the usage of the most recent ildesc_ai_call() (pass an array) or
 * returns it (no argument) so its caller can attribute it to fields.
 */
function ildesc_usage_last_call( $set = null ) {
    static $last = null;
    if ( $set !== null ) {
        $last = $set;
    }
    return $last;
}

function ildesc_usage_empty_field_bucket() {
    return [ 'n' => 0, 'in' => 0.0, 'out' => 0.0, 'cost' => 0.0, 'searches' => 0.0 ];
}

/**
 * Field slug => label, in display order.
 */
function ildesc_usage_field_labels() {
    return [
        'short'    => __( 'Short description', 'intellidesc-for-woocommerce' ),
        'long'     => __( 'Long description', 'intellidesc-for-woocommerce' ),
        'features' => __( 'Features', 'intellidesc-for-woocommerce' ),
        'smm'      => __( 'Social media post', 'intellidesc-for-woocommerce' ),
        'seo'      => __( 'SEO meta title & description', 'intellidesc-for-woocommerce' ),
        'faq'      => __( 'FAQ', 'intellidesc-for-woocommerce' ),
    ];
}

/**
 * Each field's share of a decoded generation response, by text length.
 *
 * @param array $response Decoded JSON returned by the model.
 * @return array [ field => length ]
 */
function ildesc_content_field_shares( $response ) {
    $length = static function ( $value ) {
        if ( is_array( $value ) ) {
            $value = wp_json_encode( $value );
        }
        $value = (string) $value;
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    };

    return array_filter( [
        'short'    => $length( $response['Short_Description'] ?? '' ),
        'long'     => $length( $response['Long_Description'] ?? '' ),
        'features' => empty( $response['Features'] ) ? 0 : $length( $response['Features'] ),
        'smm'      => $length( $response['Social_Media_Post'] ?? '' ),
        'seo'      => $length( $response['Meta_Title'] ?? '' ) + $length( $response['Meta_Description'] ?? '' ),
    ] );
}

/**
 * Splits the most recent successful call's usage across fields in proportion
 * to $shares and adds it to today's per-field stats.
 *
 * @param array $shares [ field => weight ].
 */
function ildesc_usage_record_fields( $shares ) {
    $last = ildesc_usage_last_call();
    $sum  = array_sum( array_map( 'floatval', (array) $shares ) );
    if ( ! is_array( $last ) || empty( $last['ok'] ) || $sum <= 0 ) {
        return;
    }

    $stats = get_option( ILDESC_USAGE_FIELD_STATS, [] );
    if ( ! is_array( $stats ) ) {
        $stats = [];
    }

    $day      = wp_date( 'Y-m-d' );
    $provider = $last['provider'];
    foreach ( $shares as $field => $weight ) {
        $ratio  = (float) $weight / $sum;
        $bucket = $stats[ $day ][ $provider ][ $field ] ?? ildesc_usage_empty_field_bucket();
        $bucket['n']++;
        foreach ( [ 'in', 'out', 'cost', 'searches' ] as $key ) {
            $bucket[ $key ] += $last[ $key ] * $ratio;
        }
        $stats[ $day ][ $provider ][ $field ] = $bucket;
    }

    $cutoff = wp_date( 'Y-m-d', time() - ILDESC_USAGE_RETENTION_DAYS * DAY_IN_SECONDS );
    foreach ( array_keys( $stats ) as $stored_day ) {
        if ( (string) $stored_day < $cutoff ) {
            unset( $stats[ $stored_day ] );
        }
    }

    update_option( ILDESC_USAGE_FIELD_STATS, $stats, false );
}

/**
 * Per-field totals over the last $days days: [ provider => [ field => bucket ] ].
 */
function ildesc_usage_field_totals( $days ) {
    $stats = get_option( ILDESC_USAGE_FIELD_STATS, [] );
    if ( ! is_array( $stats ) ) {
        return [];
    }
    $cutoff = wp_date( 'Y-m-d', time() - ( max( 1, (int) $days ) - 1 ) * DAY_IN_SECONDS );
    $totals = [];
    foreach ( $stats as $day => $providers ) {
        if ( (string) $day < $cutoff ) {
            continue;
        }
        foreach ( $providers as $provider => $fields ) {
            foreach ( $fields as $field => $bucket ) {
                $row = $totals[ $provider ][ $field ] ?? ildesc_usage_empty_field_bucket();
                foreach ( $row as $key => $value ) {
                    $row[ $key ] = $value + (float) ( $bucket[ $key ] ?? 0 );
                }
                $totals[ $provider ][ $field ] = $row;
            }
        }
    }
    return $totals;
}
