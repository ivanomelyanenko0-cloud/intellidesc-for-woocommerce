<?php
// includes/duplicate-scan.php
// Catalog-wide exact & near-duplicate ("thin content") description detection.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const ILDESC_SCAN_BATCH_SIZE   = 200;
const ILDESC_SCAN_SHINGLE_SIZE = 6;
const ILDESC_SCAN_BUCKET_CAP   = 40;
const ILDESC_SCAN_JACCARD_MIN  = 0.5;
const ILDESC_SCAN_MIN_TOKENS   = 10;

add_action( 'admin_menu', 'ildesc_add_duplicate_scan_page' );
function ildesc_add_duplicate_scan_page() {
    add_submenu_page(
        'woocommerce',
        __( 'IntelliDesc Duplicate Content Scan', 'intellidesc-for-woocommerce' ),
        __( 'IntelliDesc Duplicates', 'intellidesc-for-woocommerce' ),
        'manage_options',
        'ildesc_duplicate_scan_page',
        'ildesc_duplicate_scan_page_content'
    );
}

add_action( 'wp_ajax_ildesc_scan_start', 'ildesc_handle_scan_start' );
add_action( 'wp_ajax_ildesc_scan_batch', 'ildesc_handle_scan_batch' );
add_action( 'wp_ajax_ildesc_scan_finalize', 'ildesc_handle_scan_finalize' );

add_action( 'admin_footer', 'ildesc_render_scan_modal' );
function ildesc_render_scan_modal() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'ildesc_duplicate_scan_page' ) === false ) {
        return;
    }
    ?>
    <div id="ildesc-scan-modal" class="ildesc-modal-overlay" style="display:none;">
        <div class="ildesc-modal-content">
            <h3 class="ildesc-modal-title"><?php esc_html_e( 'Scanning Catalog', 'intellidesc-for-woocommerce' ); ?></h3>

            <div class="ildesc-progress-wrapper">
                <div id="ildesc-scan-progress" class="ildesc-progress-bar"></div>
            </div>

            <div id="ildesc-scan-log-window" class="ildesc-log-window"></div>

            <div class="ildesc-status-text-wrapper">
                <span id="ildesc-scan-status-text" class="ildesc-status-text"></span>
            </div>

            <div class="ildesc-modal-footer">
                <button type="button" id="ildesc-scan-close" class="button button-secondary">
                    <?php esc_html_e( 'Close', 'intellidesc-for-woocommerce' ); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Normalizes description text for both exact-hash and shingle comparison:
 * strips markup, decodes entities, collapses whitespace, lowercases.
 * mb_* / \s+/u used throughout since output can be in uk/pl/de/fr/es/it.
 */
function ildesc_scan_normalize_text( $html ) {
    $text = wp_strip_all_tags( (string) $html );
    $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
    $text = preg_replace( '/\s+/u', ' ', $text );
    return trim( mb_strtolower( $text ) );
}

function ildesc_scan_tokenize( $normalized_text ) {
    return preg_split( '/\s+/u', $normalized_text, -1, PREG_SPLIT_NO_EMPTY );
}

/**
 * Overlapping k-word shingles, hashed with crc32() to keep the inverted
 * index compact. Shingle size 6 (vs. the classic 3-5) is deliberate: this
 * catalog's descriptions come from a shared AI prompt template, so short
 * boilerplate phrases can coincidentally recur across unrelated products —
 * a wider window makes that far less likely while still trivially catching
 * real near-duplicates, which share long consecutive runs of text.
 */
function ildesc_scan_shingle_hashes( $tokens ) {
    $count = count( $tokens );
    if ( $count < ILDESC_SCAN_SHINGLE_SIZE ) {
        return [];
    }
    $hashes = [];
    for ( $i = 0; $i <= $count - ILDESC_SCAN_SHINGLE_SIZE; $i++ ) {
        $shingle  = implode( ' ', array_slice( $tokens, $i, ILDESC_SCAN_SHINGLE_SIZE ) );
        $hashes[] = crc32( $shingle );
    }
    return array_unique( $hashes );
}

function ildesc_scan_state_key( $scan_id ) {
    return 'ildesc_scan_state_' . $scan_id;
}

function ildesc_handle_scan_start() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ) );
    }

    global $wpdb;
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','private')"
    );

    $scan_id = wp_generate_password( 12, false );
    $state   = array(
        'last_id'        => 0,
        'total_products' => $total,
        'scanned_count'  => 0,
        'exact_hash_map' => array(),
        'shingle_index'  => array(),
        'shingle_counts' => array(),
        'word_counts'    => array(),
    );
    set_transient( ildesc_scan_state_key( $scan_id ), $state, HOUR_IN_SECONDS );

    wp_send_json_success( array( 'scan_id' => $scan_id, 'total_products' => $total ) );
}

function ildesc_handle_scan_batch() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ) );
    }

    $scan_id = isset( $_POST['scan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_id'] ) ) : '';
    $state   = $scan_id ? get_transient( ildesc_scan_state_key( $scan_id ) ) : false;
    if ( ! $scan_id || false === $state ) {
        wp_send_json_error( array( 'message' => __( 'Scan session expired. Please start again.', 'intellidesc-for-woocommerce' ) ) );
    }

    global $wpdb;
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- constant SQL, only the bound value below is dynamic.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts}
         WHERE post_type = 'product' AND post_status IN ('publish','private') AND ID > %d
         ORDER BY ID ASC LIMIT %d",
        $state['last_id'],
        ILDESC_SCAN_BATCH_SIZE
    ) );

    foreach ( $rows as $row ) {
        $product_id = (int) $row->ID;
        $state['last_id'] = $product_id;
        $state['scanned_count']++;

        $normalized = ildesc_scan_normalize_text( $row->post_content );
        if ( mb_strlen( $normalized ) < 10 ) {
            continue;
        }

        $hash = md5( $normalized );
        if ( ! isset( $state['exact_hash_map'][ $hash ] ) ) {
            $state['exact_hash_map'][ $hash ] = array();
        }
        $state['exact_hash_map'][ $hash ][] = $product_id;

        $tokens = ildesc_scan_tokenize( $normalized );
        $state['word_counts'][ $product_id ] = count( $tokens );

        if ( count( $tokens ) < ILDESC_SCAN_MIN_TOKENS ) {
            continue;
        }

        $shingle_hashes = ildesc_scan_shingle_hashes( $tokens );
        $state['shingle_counts'][ $product_id ] = count( $shingle_hashes );

        foreach ( $shingle_hashes as $shingle_hash ) {
            if ( ! isset( $state['shingle_index'][ $shingle_hash ] ) ) {
                $state['shingle_index'][ $shingle_hash ] = array();
            }
            if ( count( $state['shingle_index'][ $shingle_hash ] ) < ILDESC_SCAN_BUCKET_CAP ) {
                $state['shingle_index'][ $shingle_hash ][] = $product_id;
            }
        }
    }

    $done = count( $rows ) < ILDESC_SCAN_BATCH_SIZE;
    set_transient( ildesc_scan_state_key( $scan_id ), $state, HOUR_IN_SECONDS );

    wp_send_json_success( array(
        'scanned_count'  => $state['scanned_count'],
        'total_products' => $state['total_products'],
        'done'           => $done,
    ) );
}

/**
 * Tiny disjoint-set (union-find) used to cluster near-duplicate pairs into
 * groups. Near-duplicate similarity isn't strictly transitive, so chaining
 * A-B and B-C into one group can include an A-C pair below threshold — an
 * accepted tradeoff here, mitigated by the strict 0.5 Jaccard bar.
 */
class Ildesc_Union_Find {
    private $parent = array();

    public function find( $id ) {
        if ( ! isset( $this->parent[ $id ] ) ) {
            $this->parent[ $id ] = $id;
        }
        if ( $this->parent[ $id ] !== $id ) {
            $this->parent[ $id ] = $this->find( $this->parent[ $id ] );
        }
        return $this->parent[ $id ];
    }

    public function union( $a, $b ) {
        $root_a = $this->find( $a );
        $root_b = $this->find( $b );
        if ( $root_a !== $root_b ) {
            $this->parent[ $root_a ] = $root_b;
        }
    }
}

/**
 * Runs the union-find clustering + exact/near-dup group assembly shared by
 * Free and Pro. Returns [ exact_groups, near_groups, product_ids_needed ]
 * where near_groups' 'min_similarity' is always populated (Pro decides
 * whether to display it) and product_ids_needed is every product ID that
 * appears in at least one group, for title/edit-link resolution by the
 * caller.
 */
function ildesc_scan_build_groups( $state ) {
    $exact_groups = array();
    $grouped_ids  = array();
    foreach ( $state['exact_hash_map'] as $hash => $ids ) {
        $ids = array_unique( $ids );
        if ( count( $ids ) >= 2 ) {
            $exact_groups[] = array( 'hash' => $hash, 'product_ids' => $ids );
            foreach ( $ids as $id ) {
                $grouped_ids[ $id ] = true;
            }
        }
    }

    $pair_shared = array();
    foreach ( $state['shingle_index'] as $ids ) {
        $ids = array_values( array_unique( $ids ) );
        $n   = count( $ids );
        if ( $n < 2 ) {
            continue;
        }
        for ( $i = 0; $i < $n; $i++ ) {
            for ( $j = $i + 1; $j < $n; $j++ ) {
                $a  = min( $ids[ $i ], $ids[ $j ] );
                $b  = max( $ids[ $i ], $ids[ $j ] );
                $key = $a . '-' . $b;
                if ( ! isset( $pair_shared[ $key ] ) ) {
                    $pair_shared[ $key ] = 0;
                }
                $pair_shared[ $key ]++;
            }
        }
    }

    $uf              = new Ildesc_Union_Find();
    $pair_similarity = array();
    foreach ( $pair_shared as $key => $shared ) {
        list( $a, $b ) = array_map( 'intval', explode( '-', $key ) );
        $count_a = $state['shingle_counts'][ $a ] ?? 0;
        $count_b = $state['shingle_counts'][ $b ] ?? 0;
        $union   = $count_a + $count_b - $shared;
        if ( $union <= 0 ) {
            continue;
        }
        $jaccard = $shared / $union;
        if ( $jaccard >= ILDESC_SCAN_JACCARD_MIN ) {
            $uf->union( $a, $b );
            $pair_similarity[ $key ] = $jaccard;
        }
    }

    $clusters = array();
    foreach ( $pair_similarity as $key => $jaccard ) {
        list( $a, $b ) = array_map( 'intval', explode( '-', $key ) );
        $root = $uf->find( $a );
        if ( ! isset( $clusters[ $root ] ) ) {
            $clusters[ $root ] = array( 'ids' => array(), 'min_similarity' => 1.0 );
        }
        $clusters[ $root ]['ids'][ $a ] = true;
        $clusters[ $root ]['ids'][ $b ] = true;
        if ( $jaccard < $clusters[ $root ]['min_similarity'] ) {
            $clusters[ $root ]['min_similarity'] = $jaccard;
        }
    }

    $near_groups = array();
    foreach ( $clusters as $cluster ) {
        $ids = array_keys( $cluster['ids'] );
        sort( $ids );
        // Skip a near-dup group already fully covered by an exact-dup group.
        $already_exact = false;
        foreach ( $exact_groups as $exact_group ) {
            if ( count( array_diff( $ids, $exact_group['product_ids'] ) ) === 0 ) {
                $already_exact = true;
                break;
            }
        }
        if ( $already_exact ) {
            continue;
        }
        $near_groups[] = array( 'product_ids' => $ids, 'min_similarity' => $cluster['min_similarity'] );
        foreach ( $ids as $id ) {
            $grouped_ids[ $id ] = true;
        }
    }

    return array( $exact_groups, $near_groups, array_keys( $grouped_ids ) );
}

function ildesc_handle_scan_finalize() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ) );
    }

    $scan_id = isset( $_POST['scan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_id'] ) ) : '';
    $state   = $scan_id ? get_transient( ildesc_scan_state_key( $scan_id ) ) : false;
    if ( ! $scan_id || false === $state ) {
        wp_send_json_error( array( 'message' => __( 'Scan session expired. Please start again.', 'intellidesc-for-woocommerce' ) ) );
    }

    list( $exact_groups, $near_groups, $needed_ids ) = ildesc_scan_build_groups( $state );

    $titles = array();
    foreach ( $needed_ids as $id ) {
        $title = get_the_title( $id );
        if ( $title ) {
            $titles[ $id ] = $title;
        }
    }

    $report = array(
        'scanned_at'     => time(),
        'total_products' => $state['total_products'],
        'exact_groups'   => $exact_groups,
        'near_groups'    => $near_groups,
        'thin_content'   => array(),
        'product_titles' => $titles,
    );
    update_option( ILDESC_DUPLICATE_SCAN_REPORT, $report, false );
    delete_transient( ildesc_scan_state_key( $scan_id ) );

    wp_send_json_success( $report );
}

/**
 * Shared results renderer. $is_pro toggles edit-links, the similarity-score
 * column, and the thin-content table — Free's copy of this file never
 * passes true, so that branch is dead code here but kept identical to Pro's
 * copy for easy diffing/hand-syncing between the two trees.
 */
function ildesc_render_scan_report( $report, $is_pro = false ) {
    $titles = $report['product_titles'];

    $render_product = function ( $id ) use ( $titles, $is_pro ) {
        $title = $titles[ $id ] ?? sprintf( '#%d', $id );
        if ( $is_pro ) {
            return '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $title ) . '</a>';
        }
        return esc_html( $title );
    };

    echo '<h2>' . esc_html__( 'Exact Duplicates', 'intellidesc-for-woocommerce' ) . '</h2>';
    if ( empty( $report['exact_groups'] ) ) {
        echo '<p class="description">' . esc_html__( 'No exact duplicate descriptions found.', 'intellidesc-for-woocommerce' ) . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Products with identical descriptions', 'intellidesc-for-woocommerce' ) . '</th></tr></thead><tbody>';
        foreach ( $report['exact_groups'] as $group ) {
            echo '<tr><td>' . wp_kses_post( implode( ', ', array_map( $render_product, $group['product_ids'] ) ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h2>' . esc_html__( 'Near-Duplicates', 'intellidesc-for-woocommerce' ) . '</h2>';
    if ( empty( $report['near_groups'] ) ) {
        echo '<p class="description">' . esc_html__( 'No near-duplicate descriptions found.', 'intellidesc-for-woocommerce' ) . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Products with similar descriptions', 'intellidesc-for-woocommerce' ) . '</th>';
        if ( $is_pro ) {
            echo '<th>' . esc_html__( 'Similarity', 'intellidesc-for-woocommerce' ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $report['near_groups'] as $group ) {
            echo '<tr><td>' . wp_kses_post( implode( ', ', array_map( $render_product, $group['product_ids'] ) ) ) . '</td>';
            if ( $is_pro ) {
                echo '<td>' . esc_html( round( $group['min_similarity'] * 100 ) ) . '%</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

function ildesc_duplicate_scan_page_content() {
    $report = get_option( ILDESC_DUPLICATE_SCAN_REPORT, false );
    ?>
    <div class="wrap">
        <div class="ildesc-page-header">
            <h1><?php esc_html_e( 'Duplicate Content Scan', 'intellidesc-for-woocommerce' ); ?></h1>
            <span class="ildesc-page-badge">AI Powered</span>
        </div>

        <p>
            <?php if ( $report ) : ?>
                <?php
                echo esc_html( sprintf(
                    /* translators: %s: date/time of last scan */
                    __( 'Last scanned: %s', 'intellidesc-for-woocommerce' ),
                    date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report['scanned_at'] )
                ) );
                ?>
            <?php else : ?>
                <?php esc_html_e( 'This catalog has not been scanned yet.', 'intellidesc-for-woocommerce' ); ?>
            <?php endif; ?>
        </p>

        <button type="button" id="ildesc-scan-start-btn" class="button button-primary">
            <?php $report ? esc_html_e( 'Rescan Catalog', 'intellidesc-for-woocommerce' ) : esc_html_e( 'Scan Catalog', 'intellidesc-for-woocommerce' ); ?>
        </button>

        <hr class="ildesc-separator">

        <?php if ( $report ) : ?>
            <?php ildesc_render_scan_report( $report, false ); ?>
        <?php endif; ?>
    </div>
    <?php
}
