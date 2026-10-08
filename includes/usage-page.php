<?php
// includes/usage-page.php
// "IntelliDesc Usage" submenu page — a per-provider summary of API calls and
// tokens for the last 30 days, built from the data recorded by
// usage-tracking.php. The PRO version adds per-model and per-day breakdowns,
// a chart, cost estimates and CSV export.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const ILDESC_USAGE_FREE_DAYS = 30;

add_action( 'admin_menu', 'ildesc_add_usage_page' );
function ildesc_add_usage_page() {
    add_submenu_page(
        'woocommerce',
        __( 'IntelliDesc AI Usage', 'intellidesc-for-woocommerce' ),
        __( 'IntelliDesc Usage', 'intellidesc-for-woocommerce' ),
        'manage_options',
        'ildesc_usage_page',
        'ildesc_usage_page_content'
    );
}

/**
 * Provider slugs in the order they appear in the Settings dropdown.
 */
function ildesc_usage_provider_order() {
    return ildesc_ai_providers();
}

function ildesc_usage_page_content() {
    $stats  = ildesc_usage_get_stats( ILDESC_USAGE_FREE_DAYS );
    $totals = ildesc_usage_totals_by_provider( $stats );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
    $was_reset = isset( $_GET['reset'] );
    ?>
    <div class="wrap">
        <div class="ildesc-page-header">
            <h1><?php esc_html_e( 'AI Usage', 'intellidesc-for-woocommerce' ); ?></h1>
            <span class="ildesc-page-badge">AI Powered</span>
        </div>

        <?php if ( $was_reset ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Usage statistics have been reset.', 'intellidesc-for-woocommerce' ); ?></p></div>
        <?php endif; ?>

        <p>
            <?php
            echo esc_html( sprintf(
                /* translators: %d: number of days */
                _n( 'Requests and tokens sent to your AI providers over the last %d day.', 'Requests and tokens sent to your AI providers over the last %d days.', ILDESC_USAGE_FREE_DAYS, 'intellidesc-for-woocommerce' ),
                ILDESC_USAGE_FREE_DAYS
            ) );
            ?>
        </p>
        <p class="description"><?php esc_html_e( 'Token counts are taken from each provider\'s API response and are meant for orientation only — your provider\'s own dashboard is the authoritative source for billing.', 'intellidesc-for-woocommerce' ); ?></p>
        <p class="description"><?php esc_html_e( 'Upgrade to PRO to unlock per-model and per-day breakdowns, a usage chart, cost estimates and CSV export.', 'intellidesc-for-woocommerce' ); ?></p>

        <hr class="ildesc-separator">

        <?php if ( empty( $totals ) ) : ?>
            <p class="description"><?php esc_html_e( 'No AI requests have been recorded yet. Statistics are collected from the first generation after this feature was installed.', 'intellidesc-for-woocommerce' ); ?></p>
        <?php else : ?>
            <table class="widefat striped ildesc-usage-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Provider', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php esc_html_e( 'Requests', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php esc_html_e( 'Failed', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php esc_html_e( 'Input tokens', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php esc_html_e( 'Output tokens', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php esc_html_e( 'Web searches', 'intellidesc-for-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sum = ildesc_usage_empty_bucket();
                    foreach ( ildesc_usage_provider_order() as $provider ) :
                        if ( ! isset( $totals[ $provider ] ) ) {
                            continue;
                        }
                        $row = $totals[ $provider ];
                        foreach ( [ 'calls', 'errors', 'in', 'out', 'searches' ] as $key ) {
                            $sum[ $key ] += $row[ $key ] ?? 0;
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( ildesc_ai_provider_label( $provider ) ); ?></td>
                            <td class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $row['calls'] ) ); ?></td>
                            <td class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $row['errors'] ) ); ?></td>
                            <td class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $row['in'] ) ); ?></td>
                            <td class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $row['out'] ) ); ?></td>
                            <td class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $row['searches'] ?? 0 ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th><?php esc_html_e( 'Total', 'intellidesc-for-woocommerce' ); ?></th>
                        <th class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $sum['calls'] ) ); ?></th>
                        <th class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $sum['errors'] ) ); ?></th>
                        <th class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $sum['in'] ) ); ?></th>
                        <th class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $sum['out'] ) ); ?></th>
                        <th class="ildesc-usage-num"><?php echo esc_html( number_format_i18n( $sum['searches'] ) ); ?></th>
                    </tr>
                </tfoot>
            </table>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ildesc-usage-reset-form">
                <input type="hidden" name="action" value="ildesc_usage_reset">
                <?php wp_nonce_field( 'ildesc_usage_reset' ); ?>
                <?php submit_button( __( 'Reset statistics', 'intellidesc-for-woocommerce' ), 'secondary', 'submit', false ); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
