<?php
// includes/shortcodes.php
// Shows IntelliDesc content outside the WooCommerce product tabs: page builders
// (Elementor, Divi, Bricks, WPBakery...), custom templates and other pages.
//
//   [ildesc_faq]    FAQ, same markup as the product tab
//   [ildesc_specs]  features table (`_ildesc_editable_features`)
//   [ildesc_field]  one field: short, long, smm (PRO)
//
// Shared attributes: id (product; empty = current product), title, class.
// The ildesc/faq and ildesc/specs blocks are thin wrappers over the same
// renderers (server-side rendered, no build step).
//
// Identical in the Free and Pro trees; PRO extras (smm field, specs table
// styles, FAQPage JSON-LD off the product page) are gated at render time, so
// a lapsed PRO license simply drops them.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ILDESC_SPECS_STYLES', [ 'striped', 'compact' ] );

function ildesc_shortcodes_is_pro() {
    return ildesc_faq_is_pro();
}

/**
 * Resolves the product a shortcode/block should show. An explicit id wins;
 * otherwise the block context (Query Loop, Single Product template), then the
 * product being displayed. Returns 0 when there is nothing the visitor may see.
 */
function ildesc_shortcode_product_id( $id, $block = null ) {
    $id = absint( $id );

    if ( ! $id && $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
        $id = (int) $block->context['postId'];
    }
    if ( ! $id ) {
        global $product;
        if ( $product instanceof WC_Product ) {
            $id = $product->get_id();
        } elseif ( 'product' === get_post_type() ) {
            $id = (int) get_the_ID();
        }
    }
    if ( ! $id ) {
        return 0;
    }

    if ( 'product_variation' === get_post_type( $id ) ) {
        $id = wp_get_post_parent_id( $id );
    }
    if ( 'product' !== get_post_type( $id ) ) {
        return 0;
    }
    if ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'read_post', $id ) ) {
        return 0;
    }
    if ( post_password_required( $id ) ) {
        return 0;
    }
    return $id;
}

/**
 * Wraps rendered content with the optional title and the merchant's classes.
 */
function ildesc_shortcode_wrap( $type, $inner, $atts ) {
    $classes = [ 'ildesc-sc', 'ildesc-sc-' . $type ];
    foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $class ) {
        $class = sanitize_html_class( $class );
        if ( '' !== $class ) {
            $classes[] = $class;
        }
    }

    $html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
    if ( '' !== trim( (string) $atts['title'] ) ) {
        $html .= '<h2 class="ildesc-sc-title">' . esc_html( $atts['title'] ) . '</h2>';
    }
    return $html . $inner . '</div>';
}

// ------------------------------------------------------------------
// [ildesc_faq]
// ------------------------------------------------------------------

add_shortcode( 'ildesc_faq', 'ildesc_faq_shortcode' );
function ildesc_faq_shortcode( $atts, $content = '', $tag = 'ildesc_faq', $block = null ) {
    $atts = shortcode_atts( [ 'id' => 0, 'title' => '', 'class' => '' ], $atts, 'ildesc_faq' );

    $product_id = ildesc_shortcode_product_id( $atts['id'], $block );
    $faq        = $product_id ? ildesc_get_faq( $product_id ) : [];
    if ( empty( $faq ) ) {
        return '';
    }

    ildesc_faq_enqueue_style();
    ildesc_shortcode_queue_faq_schema( $product_id, $faq );

    return ildesc_shortcode_wrap( 'faq', ildesc_get_faq_html( $faq ), $atts );
}

/**
 * PRO: FAQPage JSON-LD for FAQs shown off the product page (a landing page,
 * a category page built in Elementor...). Every FAQ on the page is merged
 * into one FAQPage printed in the footer. Product pages already get theirs in
 * wp_head (ildesc_output_faq_schema()), and a second FAQPage would conflict.
 */
function ildesc_shortcode_queue_faq_schema( $product_id = 0, $faq = [] ) {
    static $queued = [];

    if ( $product_id ) {
        if ( ! ildesc_shortcodes_is_pro() || ! get_option( ILDESC_FAQ_SCHEMA, 1 )
            || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( function_exists( 'is_product' ) && is_product() ) ) {
            return $queued;
        }
        $queued[ $product_id ] = $faq;
    }
    return $queued;
}

add_action( 'wp_footer', 'ildesc_shortcode_print_faq_schema', 20 );
function ildesc_shortcode_print_faq_schema() {
    $queued = ildesc_shortcode_queue_faq_schema();
    if ( empty( $queued ) ) {
        return;
    }
    ildesc_print_faq_schema( array_merge( ...array_values( $queued ) ) );
}

// ------------------------------------------------------------------
// [ildesc_specs]
// ------------------------------------------------------------------

add_action( 'init', 'ildesc_specs_register_style' );
function ildesc_specs_register_style() {
    wp_register_style( 'ildesc-specs', false, [], '1.0' );
    wp_add_inline_style( 'ildesc-specs', '.ildesc-specs{width:100%;border-collapse:collapse}.ildesc-specs th,.ildesc-specs td{padding:.5em .75em;border-bottom:1px solid rgba(0,0,0,.1);text-align:left;vertical-align:top}.ildesc-specs th{font-weight:600;width:40%}.ildesc-specs-striped tr:nth-child(odd){background:rgba(0,0,0,.04)}.ildesc-specs-compact th,.ildesc-specs-compact td{padding:.25em .5em;font-size:.9em}' );
}

/**
 * @return array [ [ 'name' => string, 'value' => string ], ... ] complete rows only.
 */
function ildesc_get_specs( $product_id ) {
    $features = get_post_meta( $product_id, '_ildesc_editable_features', true );
    if ( ! is_array( $features ) ) {
        return [];
    }
    return array_values( array_filter( $features, function ( $f ) {
        return is_array( $f ) && '' !== trim( (string) ( $f['name'] ?? '' ) ) && '' !== trim( (string) ( $f['value'] ?? '' ) );
    } ) );
}

add_shortcode( 'ildesc_specs', 'ildesc_specs_shortcode' );
function ildesc_specs_shortcode( $atts, $content = '', $tag = 'ildesc_specs', $block = null ) {
    $atts = shortcode_atts( [ 'id' => 0, 'title' => '', 'class' => '', 'style' => '' ], $atts, 'ildesc_specs' );

    $product_id = ildesc_shortcode_product_id( $atts['id'], $block );
    $specs      = $product_id ? ildesc_get_specs( $product_id ) : [];
    if ( empty( $specs ) ) {
        return '';
    }

    wp_enqueue_style( 'ildesc-specs' );

    // The WooCommerce attribute-table classes let themes style it like the
    // "Additional information" tab.
    $classes = [ 'ildesc-specs', 'woocommerce-product-attributes', 'shop_attributes' ];
    $style   = sanitize_key( $atts['style'] );
    if ( ildesc_shortcodes_is_pro() && in_array( $style, ILDESC_SPECS_STYLES, true ) ) {
        $classes[] = 'ildesc-specs-' . $style;
    }

    $html = '<table class="' . esc_attr( implode( ' ', $classes ) ) . '"><tbody>';
    foreach ( $specs as $spec ) {
        $html .= '<tr class="woocommerce-product-attributes-item">';
        $html .= '<th class="woocommerce-product-attributes-item__label" scope="row">' . esc_html( $spec['name'] ) . '</th>';
        $html .= '<td class="woocommerce-product-attributes-item__value">' . esc_html( $spec['value'] ) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return ildesc_shortcode_wrap( 'specs', $html, $atts );
}

// ------------------------------------------------------------------
// [ildesc_field]
// ------------------------------------------------------------------

add_shortcode( 'ildesc_field', 'ildesc_field_shortcode' );
function ildesc_field_shortcode( $atts ) {
    static $rendering = false;

    $atts  = shortcode_atts( [ 'field' => 'long', 'id' => 0, 'title' => '', 'class' => '' ], $atts, 'ildesc_field' );
    $field = sanitize_key( $atts['field'] );

    // A description that contains this shortcode would otherwise render itself forever.
    if ( $rendering ) {
        return '';
    }

    $product_id = ildesc_shortcode_product_id( $atts['id'] );
    if ( ! $product_id ) {
        return '';
    }

    $rendering = true;
    switch ( $field ) {
        case 'short':
            $html = ildesc_format_field_html( get_post_field( 'post_excerpt', $product_id, 'raw' ) );
            break;
        case 'long':
            $html = ildesc_format_field_html( get_post_field( 'post_content', $product_id, 'raw' ) );
            break;
        case 'smm':
            $smm  = ildesc_shortcodes_is_pro() ? (string) get_post_meta( $product_id, '_ildesc_smm_post', true ) : '';
            $html = '' !== trim( $smm ) ? '<p>' . nl2br( esc_html( $smm ) ) . '</p>' : '';
            break;
        default:
            $html = '';
    }
    $rendering = false;

    if ( '' === $html ) {
        return '';
    }
    return ildesc_shortcode_wrap( 'field-' . $field, $html, $atts );
}

/**
 * Formats a stored description for output: sanitized, then the usual content
 * formatting (blocks, paragraphs, nested shortcodes). Deliberately not
 * `the_content`, whose third-party filters (share buttons, related posts...)
 * don't belong inside a builder widget.
 */
function ildesc_format_field_html( $text ) {
    $text = wp_kses_post( (string) $text );
    if ( '' === trim( $text ) ) {
        return '';
    }
    if ( has_blocks( $text ) ) {
        return do_shortcode( do_blocks( $text ) );
    }
    return do_shortcode( shortcode_unautop( wpautop( wptexturize( $text ) ) ) );
}

// ------------------------------------------------------------------
// BLOCKS (ildesc/faq, ildesc/specs)
// ------------------------------------------------------------------

add_action( 'init', 'ildesc_register_blocks', 20 );
function ildesc_register_blocks() {
    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    wp_register_script(
        'ildesc-blocks',
        ILDESC_PLUGIN_URL . 'assets/js/blocks.js',
        [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-api-fetch', 'wp-html-entities' ],
        '1.0',
        true
    );
    wp_add_inline_script( 'ildesc-blocks', 'window.ildescBlocks = ' . wp_json_encode( [ 'isPro' => ildesc_shortcodes_is_pro() ] ) . ';', 'before' );
    wp_set_script_translations( 'ildesc-blocks', 'intellidesc-for-woocommerce' );

    $attributes = [
        'productId' => [ 'type' => 'number', 'default' => 0 ],
        'title'     => [ 'type' => 'string', 'default' => '' ],
        // Declared explicitly: ServerSideRender sends it, and the REST
        // renderer rejects attributes the block type doesn't know.
        'className' => [ 'type' => 'string', 'default' => '' ],
    ];

    register_block_type( 'ildesc/faq', [
        'api_version'     => 2,
        'editor_script'   => 'ildesc-blocks',
        'style'           => 'ildesc-faq',
        'attributes'      => $attributes,
        'uses_context'    => [ 'postId', 'postType' ],
        'render_callback' => 'ildesc_render_faq_block',
    ] );

    register_block_type( 'ildesc/specs', [
        'api_version'     => 2,
        'editor_script'   => 'ildesc-blocks',
        'style'           => 'ildesc-specs',
        'attributes'      => $attributes + [ 'tableStyle' => [ 'type' => 'string', 'default' => '' ] ],
        'uses_context'    => [ 'postId', 'postType' ],
        'render_callback' => 'ildesc_render_specs_block',
    ] );
}

function ildesc_block_shortcode_atts( $attributes ) {
    return [
        'id'    => $attributes['productId'] ?? 0,
        'title' => $attributes['title'] ?? '',
        'class' => $attributes['className'] ?? '',
        'style' => $attributes['tableStyle'] ?? '',
    ];
}

function ildesc_render_faq_block( $attributes, $content = '', $block = null ) {
    $html = ildesc_faq_shortcode( ildesc_block_shortcode_atts( $attributes ), '', 'ildesc_faq', $block );
    return '' !== $html ? $html : ildesc_block_editor_placeholder( 'faq', $attributes, $block );
}

function ildesc_render_specs_block( $attributes, $content = '', $block = null ) {
    $html = ildesc_specs_shortcode( ildesc_block_shortcode_atts( $attributes ), '', 'ildesc_specs', $block );
    return '' !== $html ? $html : ildesc_block_editor_placeholder( 'specs', $attributes, $block );
}

/**
 * Empty blocks render nothing on the site, but in the editor (the REST
 * preview) say why, so the block isn't just an invisible box.
 */
function ildesc_block_editor_placeholder( $type, $attributes, $block ) {
    if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! current_user_can( 'edit_posts' ) ) {
        return '';
    }

    if ( ! ildesc_shortcode_product_id( $attributes['productId'] ?? 0, $block ) ) {
        $message = __( 'Shows the current product on product pages. Choose a product in the block settings to preview it here.', 'intellidesc-for-woocommerce' );
    } elseif ( 'faq' === $type ) {
        $message = __( 'This product has no FAQ yet. Generate one in the product editor (AI Features box).', 'intellidesc-for-woocommerce' );
    } else {
        $message = __( 'This product has no features yet. Generate them in the product editor (AI Features box).', 'intellidesc-for-woocommerce' );
    }
    return '<p class="ildesc-sc-placeholder" style="padding:1em;border:1px dashed #c3c4c7;color:#646970">' . esc_html( $message ) . '</p>';
}

// ------------------------------------------------------------------
// METABOX HINT
// ------------------------------------------------------------------

/**
 * Ready-to-copy shortcodes for this product, at the end of the "AI Features" box.
 */
function ildesc_render_shortcodes_hint( $post ) {
    $id         = (int) $post->ID;
    $shortcodes = [
        sprintf( '[ildesc_faq id="%d"]', $id ),
        sprintf( '[ildesc_specs id="%d"]', $id ),
        sprintf( '[ildesc_field field="long" id="%d"]', $id ),
        sprintf( '[ildesc_field field="short" id="%d"]', $id ),
    ];
    if ( ildesc_shortcodes_is_pro() ) {
        $shortcodes[] = sprintf( '[ildesc_field field="smm" id="%d"]', $id );
    }
    ?>
    <details class="ildesc-shortcodes-box">
        <summary class="ildesc-tools-header"><?php esc_html_e( 'Shortcodes for page builders', 'intellidesc-for-woocommerce' ); ?></summary>
        <p class="description">
            <?php esc_html_e( 'Paste into Elementor, Divi, Bricks, WPBakery or the Shortcode block. Without id they show the current product. Optional: title="..." and class="...". In the block editor you can also use the IntelliDesc FAQ and IntelliDesc Specs blocks.', 'intellidesc-for-woocommerce' ); ?>
            <?php if ( ildesc_shortcodes_is_pro() ) : ?>
                <?php esc_html_e( 'Specs table styles: style="striped" or style="compact".', 'intellidesc-for-woocommerce' ); ?>
            <?php endif; ?>
        </p>
        <?php foreach ( $shortcodes as $shortcode ) : ?>
            <div class="ildesc-shortcode-row">
                <code><?php echo esc_html( $shortcode ); ?></code>
                <button type="button" class="button button-small ildesc-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>" data-copied="<?php esc_attr_e( 'Copied!', 'intellidesc-for-woocommerce' ); ?>"><?php esc_html_e( 'Copy', 'intellidesc-for-woocommerce' ); ?></button>
            </div>
        <?php endforeach; ?>
    </details>
    <?php
}
