<?php
// includes/uninstall-cleanup.php
// Removes the plugin's stored data when it is deleted from the Plugins screen.
// Identical in the Free and Pro trees and deliberately self-contained (no
// plugin constants or functions), because Free runs it from uninstall.php
// without the main plugin file being loaded.
//
// Removed: every `ildesc_*` option (settings, API keys, usage statistics, scan
// report) and its transients, plus the per-product undo history.
// Kept: the AI-generated features and social post stored on products — that is
// the merchant's content, and they may have edited it.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @param string $current_slug Plugin folder being uninstalled: 'intellidesc-for-woocommerce' (Free)
 *                             or 'intellidesc-for-woocommerce-pro' (Pro).
 */
function ildesc_remove_plugin_data( $current_slug ) {
    // Free and Pro share every option key and are meant to replace each other
    // (typically Free -> Pro on upgrade). Deleting one must not wipe the settings
    // and API keys the other edition still uses.
    $other_slug = ( 'intellidesc-for-woocommerce-pro' === $current_slug ) ? 'intellidesc-for-woocommerce' : 'intellidesc-for-woocommerce-pro';
    if ( file_exists( WP_PLUGIN_DIR . '/' . $other_slug . '/' . $other_slug . '.php' ) ) {
        return;
    }

    if ( is_multisite() ) {
        foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $blog_id ) {
            switch_to_blog( $blog_id );
            ildesc_remove_site_data();
            restore_current_blog();
        }
        return;
    }

    ildesc_remove_site_data();
}

/**
 * Removes the plugin's data for the current site.
 */
function ildesc_remove_site_data() {
    global $wpdb;

    // Options are looked up by prefix so options added by later versions are covered too.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off lookup during uninstall; table name is $wpdb->options.
    $option_names = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( 'ildesc_' ) . '%',
        $wpdb->esc_like( '_transient_ildesc_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_ildesc_' ) . '%'
    ) );

    foreach ( $option_names as $option_name ) {
        delete_option( $option_name );
    }

    delete_post_meta_by_key( '_ildesc_content_history' );
}
