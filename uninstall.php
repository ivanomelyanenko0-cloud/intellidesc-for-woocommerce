<?php
// uninstall.php — runs when the plugin is deleted from the Plugins screen.

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/includes/uninstall-cleanup.php';

ildesc_remove_plugin_data( 'intellidesc-for-woocommerce' );
