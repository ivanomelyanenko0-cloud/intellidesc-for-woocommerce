<?php
// includes/faq.php
// Product FAQ block: AI generation (its own request, so its spend is exact),
// the metabox editor, the storefront "FAQ" tab, FAQPage JSON-LD and the FAQ
// settings. Identical in the Free and Pro trees; the PRO extras (more than 3
// questions, adding rows by hand, JSON-LD, bulk/CLI) are gated at runtime by
// ildesc_faq_is_pro(), so a lapsed PRO license falls back to the Free view.
//
// Stored as `_ildesc_faq` post meta: [ [ 'q' => question, 'a' => answer ], ... ].
// That is the merchant's content, so uninstall keeps it.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ILDESC_FAQ_META', '_ildesc_faq' );
define( 'ILDESC_FAQ_FREE_LIMIT', 3 );
define( 'ILDESC_FAQ_PRO_LIMIT', 10 );
define( 'ILDESC_FAQ_PRO_DEFAULT', 5 );

function ildesc_faq_is_pro() {
    return function_exists( 'ildesc_fs' ) && ildesc_fs()->is_premium();
}

/**
 * How many questions one generation asks for.
 */
function ildesc_faq_generate_count() {
    if ( ! ildesc_faq_is_pro() ) {
        return ILDESC_FAQ_FREE_LIMIT;
    }
    $count = absint( get_option( ILDESC_FAQ_COUNT, ILDESC_FAQ_PRO_DEFAULT ) );
    return min( ILDESC_FAQ_PRO_LIMIT, max( ILDESC_FAQ_FREE_LIMIT, $count ) );
}

/**
 * Most questions a product may store. Free can edit and remove its 3 but not
 * add more; a lapsed PRO license keeps whatever it already has rather than
 * losing questions on the next save.
 */
function ildesc_faq_storage_limit( $product_id ) {
    if ( ildesc_faq_is_pro() ) {
        return ILDESC_FAQ_PRO_LIMIT;
    }
    return max( ILDESC_FAQ_FREE_LIMIT, count( ildesc_get_faq( $product_id ) ) );
}

/**
 * @return array [ [ 'q' => string, 'a' => string ], ... ]
 */
function ildesc_get_faq( $product_id ) {
    $faq = get_post_meta( $product_id, ILDESC_FAQ_META, true );
    return is_array( $faq ) ? $faq : [];
}

/**
 * Cleans raw rows (either [ 'q', 'a' ] or the AI's [ 'Question', 'Answer' ])
 * into stored shape, drops incomplete rows and caps the count.
 */
function ildesc_sanitize_faq_items( $items, $limit ) {
    $clean = [];
    foreach ( (array) $items as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $question = sanitize_text_field( (string) ( $item['q'] ?? $item['Question'] ?? '' ) );
        $answer   = sanitize_textarea_field( (string) ( $item['a'] ?? $item['Answer'] ?? '' ) );
        if ( $question === '' || $answer === '' ) {
            continue;
        }
        $clean[] = [ 'q' => $question, 'a' => $answer ];
    }
    return array_slice( $clean, 0, max( 0, (int) $limit ) );
}

/**
 * Whether bulk/CLI runs should leave this product's FAQ alone: it already has
 * one and "Overwrite existing data" is off.
 */
function ildesc_faq_should_skip( $product_id ) {
    return ! get_option( ILDESC_OVERWRITE_DATA, 0 ) && ! empty( ildesc_get_faq( $product_id ) );
}

// ------------------------------------------------------------------
// GENERATION
// ------------------------------------------------------------------

/**
 * Generates and saves a FAQ for a product.
 *
 * @param int   $product_id
 * @param array $context Optional unsaved editor content: 'title', 'short', 'long', 'features' (strings).
 *                       Falls back to the saved product.
 * @return array|WP_Error Saved FAQ rows.
 */
function ildesc_generate_faq_for_product( $product_id, $context = [] ) {
    $provider = ildesc_get_current_provider();
    $api_key  = ildesc_get_api_key_for_provider( $provider );
    if ( ildesc_provider_needs_api_key( $provider ) && empty( $api_key ) ) {
        /* translators: %s: AI provider name (e.g. Gemini, Claude) */
        return new WP_Error( 'api_key', sprintf( __( '%s API Key is missing.', 'intellidesc-for-woocommerce' ), ildesc_ai_provider_label( $provider ) ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return new WP_Error( 'not_found', __( 'Product not found.', 'intellidesc-for-woocommerce' ) );
    }

    $title = trim( (string) ( $context['title'] ?? '' ) ) !== '' ? $context['title'] : $product->get_name();
    $short = trim( (string) ( $context['short'] ?? '' ) ) !== '' ? $context['short'] : wp_strip_all_tags( $product->get_short_description() );
    $long  = trim( (string) ( $context['long'] ?? '' ) ) !== '' ? $context['long'] : wp_strip_all_tags( $product->get_description() );

    $features = trim( (string) ( $context['features'] ?? '' ) );
    if ( $features === '' ) {
        $pairs = [];
        foreach ( (array) get_post_meta( $product_id, '_ildesc_editable_features', true ) as $feature ) {
            if ( ! empty( $feature['name'] ) && ! empty( $feature['value'] ) ) {
                $pairs[] = $feature['name'] . ': ' . $feature['value'];
            }
        }
        $features = implode( ' | ', $pairs );
    }

    $long  = function_exists( 'mb_substr' ) ? mb_substr( $long, 0, 4000 ) : substr( $long, 0, 4000 );
    $count = ildesc_faq_generate_count();

    $known = '';
    foreach ( [ 'Short description' => $short, 'Description' => $long, 'Specifications' => $features ] as $label => $text ) {
        if ( trim( (string) $text ) !== '' ) {
            $known .= "\n    {$label}: " . trim( preg_replace( '/\s+/', ' ', (string) $text ) );
        }
    }
    $known_block = $known !== ''
        ? "PRODUCT INFORMATION ALREADY ON THE PRODUCT PAGE (stay consistent with it — never contradict it):{$known}"
        : '';

    $tone     = ildesc_build_tone_instruction();
    $language = ildesc_build_language_instruction();

    $prompt = "Act as an E-commerce Assistant. Write a customer FAQ for the product: \"{$title}\".

    {$known_block}

    {$tone}
    {$language}

    TASK: Write exactly {$count} questions a real shopper would ask before buying this product (e.g. compatibility, sizing or dimensions, materials, how to use it, care, what is included), each with a helpful answer of 1-3 sentences.
    USE THE WEB SEARCH TOOL TO VERIFY FACTS. If a detail cannot be verified, answer in general terms for this type of product instead of inventing precise numbers.
    Do NOT ask about price, discounts, shipping, returns or stock — those are store policies, not product facts. Plain text only, no HTML or Markdown.

    STRICT OUTPUT RULES:
    1. Return ONLY raw JSON.
    2. No Markdown blocks.
    3. Structure: {'FAQ': [{'Question': 'string', 'Answer': 'string'}]}";

    $result = ildesc_ai_call( $provider, ildesc_get_model_for_provider( $provider ), $prompt, $api_key, [
        'use_search_tool' => true,
        'fallback_model'  => ildesc_get_fallback_model_for_provider( $provider ),
    ] );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    ildesc_clear_model_unavailable_flag( $provider );

    $decoded = ildesc_parse_ai_json( $result );
    if ( is_wp_error( $decoded ) ) {
        return $decoded;
    }

    $faq = ildesc_sanitize_faq_items( $decoded['FAQ'] ?? [], $count );
    if ( empty( $faq ) ) {
        return new WP_Error( 'empty_content', __( 'The AI returned no usable questions for this product. Please try again or switch AI providers/models.', 'intellidesc-for-woocommerce' ) );
    }

    ildesc_usage_record_fields( [ 'faq' => 1 ] );
    update_post_meta( $product_id, ILDESC_FAQ_META, $faq );

    return $faq;
}

add_action( 'wp_ajax_ildesc_generate_faq', 'ildesc_handle_generate_faq' );
function ildesc_handle_generate_faq() {
    check_ajax_referer( 'ildesc_autocomplete_nonce', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $product_id || ! current_user_can( 'edit_product', $product_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'intellidesc-for-woocommerce' ) ] );
    }

    // Bulk runs respect "Overwrite existing data"; the single-product button always regenerates.
    if ( ! empty( $_POST['skip_existing'] ) && ildesc_faq_should_skip( $product_id ) ) {
        wp_send_json_success( [ 'skipped' => true, 'faq' => ildesc_get_faq( $product_id ) ] );
    }

    $result = ildesc_generate_faq_for_product( $product_id, [
        'title'    => isset( $_POST['product_title'] ) ? sanitize_text_field( wp_unslash( $_POST['product_title'] ) ) : '',
        'short'    => isset( $_POST['current_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['current_excerpt'] ) ) : '',
        'long'     => isset( $_POST['current_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['current_content'] ) ) : '',
        'features' => isset( $_POST['existing_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['existing_features'] ) ) : '',
    ] );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'skipped' => false, 'faq' => $result ] );
}

// ------------------------------------------------------------------
// METABOX EDITOR
// ------------------------------------------------------------------

function ildesc_render_faq_row( $index, $question, $answer ) {
    ?>
    <div class="ildesc-faq-row">
        <div class="ildesc-faq-fields">
            <input type="text" class="ildesc-input-wide" name="ildesc_faq[<?php echo esc_attr( $index ); ?>][q]" value="<?php echo esc_attr( $question ); ?>" placeholder="<?php esc_attr_e( 'Question', 'intellidesc-for-woocommerce' ); ?>">
            <textarea class="ildesc-input-wide" rows="2" name="ildesc_faq[<?php echo esc_attr( $index ); ?>][a]" placeholder="<?php esc_attr_e( 'Answer', 'intellidesc-for-woocommerce' ); ?>"><?php echo esc_textarea( $answer ); ?></textarea>
        </div>
        <button type="button" class="button ildesc-remove-faq" aria-label="<?php esc_attr_e( 'Remove question', 'intellidesc-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Remove question', 'intellidesc-for-woocommerce' ); ?>">&#x2715;</button>
    </div>
    <?php
}

/**
 * FAQ section of the "AI Features" metabox. Saved together with the product
 * (the metabox's own nonce covers it); the Generate button saves right away.
 */
function ildesc_render_faq_metabox_section( $post ) {
    $faq    = ildesc_get_faq( $post->ID );
    $is_pro = ildesc_faq_is_pro();
    $limit  = ildesc_faq_storage_limit( $post->ID );
    ?>
    <div class="ildesc-faq-box">
        <div class="ildesc-faq-header">
            <span class="ildesc-tools-header"><?php esc_html_e( 'FAQ', 'intellidesc-for-woocommerce' ); ?></span>
            <button type="button" id="ildesc-generate-faq" class="button">
                <span class="dashicons dashicons-format-chat"></span>
                <span class="ildesc-btn-text"><?php esc_html_e( 'Generate FAQ', 'intellidesc-for-woocommerce' ); ?></span>
            </button>
            <button type="button" id="ildesc-delete-faq" class="button button-link-delete ildesc-text-danger"<?php echo empty( $faq ) ? ' style="display:none"' : ''; ?>>
                <?php esc_html_e( 'Delete FAQ', 'intellidesc-for-woocommerce' ); ?>
            </button>
            <span class="spinner ildesc-faq-spinner"></span>
        </div>
        <div id="ildesc-faq-status"></div>

        <input type="hidden" name="ildesc_faq_present" value="1">
        <div class="ildesc-faq-list" data-max="<?php echo esc_attr( $limit ); ?>">
            <?php
            foreach ( $faq as $index => $item ) {
                ildesc_render_faq_row( $index, $item['q'] ?? '', $item['a'] ?? '' );
            }
            ?>
        </div>

        <?php if ( $is_pro ) : ?>
            <button type="button" id="ildesc-add-faq" class="button button-secondary"><?php esc_html_e( 'Add Question', 'intellidesc-for-woocommerce' ); ?></button>
        <?php else : ?>
            <p class="description">
                <?php
                printf(
                    /* translators: %d: number of FAQ questions in the free version */
                    esc_html__( 'The free version generates %d questions, which you can edit or remove. Upgrade to PRO for up to 10 questions, adding your own, FAQ schema markup and bulk FAQ generation.', 'intellidesc-for-woocommerce' ),
                    (int) ILDESC_FAQ_FREE_LIMIT
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'save_post', 'ildesc_save_faq_metabox' );
function ildesc_save_faq_metabox( $post_id ) {
    if ( ! isset( $_POST['ildesc_faq_present'], $_POST['ildesc_features_nonce_field'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ildesc_features_nonce_field'] ) ), 'ildesc_features_nonce' ) ) {
        return;
    }
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    $input = isset( $_POST['ildesc_faq'] ) && is_array( $_POST['ildesc_faq'] )
        ? wp_unslash( $_POST['ildesc_faq'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in ildesc_sanitize_faq_items().
        : [];

    // The server-side cap is what enforces the Free 3-question limit, whatever the form sent.
    $faq = ildesc_sanitize_faq_items( $input, ildesc_faq_storage_limit( $post_id ) );

    if ( empty( $faq ) ) {
        delete_post_meta( $post_id, ILDESC_FAQ_META );
    } else {
        update_post_meta( $post_id, ILDESC_FAQ_META, $faq );
    }
}

add_action( 'admin_enqueue_scripts', 'ildesc_faq_localize_script', 20 );
function ildesc_faq_localize_script() {
    if ( ! wp_script_is( 'ildesc-admin-script', 'enqueued' ) ) {
        return;
    }
    wp_localize_script( 'ildesc-admin-script', 'ildesc_faq_params', [
        'generate'        => __( 'Generate FAQ', 'intellidesc-for-woocommerce' ),
        'generating'      => __( 'Generating FAQ...', 'intellidesc-for-woocommerce' ),
        'confirm_replace' => __( 'Replace the current FAQ with newly generated questions?', 'intellidesc-for-woocommerce' ),
        'confirm_delete'  => __( 'Remove all questions? The FAQ is deleted when you update the product.', 'intellidesc-for-woocommerce' ),
        'deleted_hint'    => __( 'All questions removed — click Update to save.', 'intellidesc-for-woocommerce' ),
        'saved'           => __( 'FAQ generated and saved.', 'intellidesc-for-woocommerce' ),
        'max_reached'     => __( 'Maximum number of questions reached.', 'intellidesc-for-woocommerce' ),
        'question'        => __( 'Question', 'intellidesc-for-woocommerce' ),
        'answer'          => __( 'Answer', 'intellidesc-for-woocommerce' ),
        'remove'          => __( 'Remove question', 'intellidesc-for-woocommerce' ),
        'bulk_faq_ok'     => __( 'FAQ OK', 'intellidesc-for-woocommerce' ),
        'bulk_faq_skip'   => __( 'FAQ skipped (already exists)', 'intellidesc-for-woocommerce' ),
        'bulk_faq_failed' => __( 'FAQ failed', 'intellidesc-for-woocommerce' ),
    ] );
}

// ------------------------------------------------------------------
// STOREFRONT
// ------------------------------------------------------------------

add_filter( 'woocommerce_product_tabs', 'ildesc_add_faq_product_tab' );
function ildesc_add_faq_product_tab( $tabs ) {
    global $product;
    if ( ! get_option( ILDESC_FAQ_TAB, 1 ) || ! $product instanceof WC_Product || empty( ildesc_get_faq( $product->get_id() ) ) ) {
        return $tabs;
    }
    $tabs['ildesc_faq'] = [
        'title'    => __( 'FAQ', 'intellidesc-for-woocommerce' ),
        'priority' => 25,
        'callback' => 'ildesc_render_faq_product_tab',
    ];
    return $tabs;
}

function ildesc_render_faq_product_tab() {
    global $product;
    echo '<h2>' . esc_html__( 'Frequently Asked Questions', 'intellidesc-for-woocommerce' ) . '</h2>';
    echo ildesc_get_faq_html( ildesc_get_faq( $product->get_id() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ildesc_get_faq_html().
}

/**
 * FAQ markup shared by the product tab and the [ildesc_faq] shortcode/block.
 */
function ildesc_get_faq_html( $faq ) {
    $html = '<div class="ildesc-faq">';
    foreach ( $faq as $item ) {
        $html .= '<details class="ildesc-faq-item"><summary class="ildesc-faq-question">' . esc_html( $item['q'] ) . '</summary>';
        $html .= '<div class="ildesc-faq-answer"><p>' . nl2br( esc_html( $item['a'] ) ) . '</p></div></details>';
    }
    return $html . '</div>';
}

add_action( 'wp_enqueue_scripts', 'ildesc_faq_frontend_style' );
function ildesc_faq_frontend_style() {
    if ( ! function_exists( 'is_product' ) || ! is_product() || ! get_option( ILDESC_FAQ_TAB, 1 ) ) {
        return;
    }
    ildesc_faq_enqueue_style();
}

// Registered early so the ildesc/faq block can declare it as its style
// (that also loads it inside the block editor).
add_action( 'init', 'ildesc_faq_register_style' );
function ildesc_faq_register_style() {
    wp_register_style( 'ildesc-faq', false, [], '1.0' );
    wp_add_inline_style( 'ildesc-faq', '.ildesc-faq-item{border-bottom:1px solid rgba(0,0,0,.1);padding:.75em 0}.ildesc-faq-question{cursor:pointer;font-weight:600}.ildesc-faq-answer p{margin:.5em 0 0}' );
}

/**
 * Also called while rendering the shortcode, which can sit on any page.
 */
function ildesc_faq_enqueue_style() {
    wp_enqueue_style( 'ildesc-faq' );
}

/**
 * FAQPage structured data (PRO). Google shows FAQ rich results only for a few
 * authoritative sites since 2023, so the value here is machine-readable Q&A
 * for AI answer engines — see the setting's description.
 */
add_action( 'wp_head', 'ildesc_output_faq_schema' );
function ildesc_output_faq_schema() {
    if ( ! ildesc_faq_is_pro() || ! get_option( ILDESC_FAQ_SCHEMA, 1 ) || ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }
    $faq = ildesc_get_faq( get_queried_object_id() );
    if ( empty( $faq ) ) {
        return;
    }
    ildesc_print_faq_schema( $faq );
}

/**
 * Prints one FAQPage script. Also used by the shortcode, which merges every
 * FAQ shown on a page into a single FAQPage (one per page is what parsers expect).
 */
function ildesc_print_faq_schema( $faq ) {
    $entities = [];
    foreach ( $faq as $item ) {
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $item['q'],
            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item['a'] ],
        ];
    }
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];

    echo "<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}

// ------------------------------------------------------------------
// SETTINGS
// ------------------------------------------------------------------

add_action( 'admin_init', 'ildesc_register_faq_settings' );
function ildesc_register_faq_settings() {
    register_setting( 'ildesc_settings_group', ILDESC_FAQ_TAB, [ 'type' => 'integer', 'sanitize_callback' => 'intval', 'default' => 1 ] );
    register_setting( 'ildesc_settings_group', ILDESC_FAQ_COUNT, [ 'type' => 'integer', 'sanitize_callback' => 'ildesc_sanitize_faq_count', 'default' => ILDESC_FAQ_PRO_DEFAULT ] );
    register_setting( 'ildesc_settings_group', ILDESC_FAQ_SCHEMA, [ 'type' => 'integer', 'sanitize_callback' => 'intval', 'default' => 1 ] );
}

function ildesc_sanitize_faq_count( $value ) {
    return min( ILDESC_FAQ_PRO_LIMIT, max( ILDESC_FAQ_FREE_LIMIT, absint( $value ) ) );
}

/**
 * "FAQ" section of the IntelliDesc settings form.
 */
function ildesc_render_faq_settings() {
    ?>
    <h3><?php esc_html_e( 'FAQ', 'intellidesc-for-woocommerce' ); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php esc_html_e( 'Show FAQ tab', 'intellidesc-for-woocommerce' ); ?></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( ILDESC_FAQ_TAB ); ?>" value="0">
                <label><input type="checkbox" name="<?php echo esc_attr( ILDESC_FAQ_TAB ); ?>" value="1" <?php checked( get_option( ILDESC_FAQ_TAB, 1 ), 1 ); ?> />
                <?php esc_html_e( 'Add a "FAQ" tab to product pages that have a FAQ.', 'intellidesc-for-woocommerce' ); ?></label>
            </td>
        </tr>
        <?php if ( ildesc_faq_is_pro() ) : ?>
        <tr valign="top">
            <th scope="row"><?php esc_html_e( 'Questions per FAQ', 'intellidesc-for-woocommerce' ); ?></th>
            <td>
                <input type="number" min="<?php echo esc_attr( ILDESC_FAQ_FREE_LIMIT ); ?>" max="<?php echo esc_attr( ILDESC_FAQ_PRO_LIMIT ); ?>" class="small-text" name="<?php echo esc_attr( ILDESC_FAQ_COUNT ); ?>" value="<?php echo esc_attr( ildesc_faq_generate_count() ); ?>">
                <p class="description"><?php esc_html_e( 'How many questions "Generate FAQ" writes (3-10). Each FAQ is one extra AI request.', 'intellidesc-for-woocommerce' ); ?></p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php esc_html_e( 'FAQ schema markup', 'intellidesc-for-woocommerce' ); ?></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( ILDESC_FAQ_SCHEMA ); ?>" value="0">
                <label><input type="checkbox" name="<?php echo esc_attr( ILDESC_FAQ_SCHEMA ); ?>" value="1" <?php checked( get_option( ILDESC_FAQ_SCHEMA, 1 ), 1 ); ?> />
                <?php esc_html_e( 'Output FAQPage structured data (JSON-LD) on product pages.', 'intellidesc-for-woocommerce' ); ?></label>
                <p class="description"><?php esc_html_e( 'Helps AI assistants and answer engines read your Q&A. Note: since 2023 Google shows FAQ rich results only for a small set of authoritative government and health sites, so do not expect FAQ snippets in Google search. Turn this off if your SEO plugin already outputs FAQ schema for products.', 'intellidesc-for-woocommerce' ); ?></p>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}
