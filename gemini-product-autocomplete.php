<?php
/**
 * Plugin Name:       Gemini Product Autocomplete
 * Plugin URI:        https://wordpress.org/plugins/gemini-product-autocomplete/
 * Description:       Automatically fills product features using Google Gemini API.
 * Version:           1.2.0
 * Author:            Ivan O.
 * Author URI:        https://profiles.wordpress.org/lukystile/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gemini-product-autocomplete
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
define( 'GPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GPA_SETTINGS_KEY', 'gpa_gemini_api_key' );


// Enqueue Assets (Unified function)
function gpa_enqueue_admin_assets(  $hook  ) {
    // Only load on product editing or listing pages
    $is_settings_page = strpos( $hook, 'gemini-autocomplete-settings' ) !== false;
    if ( $is_settings_page ) {
        wp_enqueue_style(
            'gpa-admin-style',
            GPA_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            '1.3.0'
        );
        return;
    }
    if ( !in_array( $hook, ['post.php', 'post-new.php', 'edit.php'] ) ) {
        return;
    }
    global $post;
    if ( $hook !== 'edit.php' && (!isset( $post ) || $post->post_type !== 'product') ) {
        return;
    }
    // CSS
    wp_enqueue_style(
        'gpa-admin-style',
        GPA_PLUGIN_URL . 'assets/admin-style.css',
        array(),
        '1.2.0'
    );
    // JS
    wp_enqueue_script(
        'gpa-admin-script',
        GPA_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        '1.2.0',
        true
    );
    // Localize JS
    wp_localize_script( 'gpa-admin-script', 'gpa_params', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'gpa_autocomplete_nonce' ),
        'no_title'        => __( 'Please enter a product title first.', 'gemini-product-autocomplete' ),
        'no_selected'     => __( 'No products selected!', 'gemini-product-autocomplete' ),
        'confirm_bulk'    => __( 'Start generation for selected products?', 'gemini-product-autocomplete' ),
        'loading_text'    => __( 'Sending request to Gemini...', 'gemini-product-autocomplete' ),
        'btn_default'     => __( 'Generate Content', 'gemini-product-autocomplete' ),
        'btn_loading'     => __( 'Generating...', 'gemini-product-autocomplete' ),
        'status_success'  => __( 'Success! Data saved.', 'gemini-product-autocomplete' ),
        'status_done'     => __( 'Completed! Refreshing page...', 'gemini-product-autocomplete' ),
        'status_error'    => __( 'Error: ', 'gemini-product-autocomplete' ),
        'server_error'    => __( 'Server connection failed.', 'gemini-product-autocomplete' ),
        'bulk_processing' => __( 'Processing', 'gemini-product-autocomplete' ),
        'bulk_retry'      => __( 'Retry', 'gemini-product-autocomplete' ),
        'bulk_failed'     => __( 'Failed', 'gemini-product-autocomplete' ),
        'close_btn'       => __( 'Close', 'gemini-product-autocomplete' ),
        'stop_btn'        => __( 'Stop', 'gemini-product-autocomplete' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'gpa_enqueue_admin_assets' );

// Include other files
require_once GPA_PLUGIN_DIR . 'includes/admin-settings.php';
require_once GPA_PLUGIN_DIR . 'includes/ajax-handler.php';

// Register Metaboxes
function gpa_register_metaboxes() {
    add_meta_box(
        'gpa_actions_metabox',
        __( 'Gemini AI Actions', 'gemini-product-autocomplete' ),
        'gpa_render_actions_metabox',
        'product',
        'side',
        'high'
    );
    add_meta_box(
        'gpa_features_metabox',
        __( 'Gemini AI Features (Edit)', 'gemini-product-autocomplete' ),
        'gpa_render_product_features_metabox',
        'product',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'gpa_register_metaboxes' );

// Render Side Metabox
function gpa_render_actions_metabox(  $post  ) {
    ?>
    <div class="gpa-actions-wrapper">
        <p class="description">
            <?php esc_html_e( 'Click below to generate description and features based on title.', 'gemini-product-autocomplete' ); ?>
        </p>
        <button type="button" id="gpa-trigger-btn" class="button button-primary gpa-full-width">
            <?php esc_html_e( 'Generate Content', 'gemini-product-autocomplete' ); ?>
        </button>

        <div id="gpa-loader" style="display:none; margin-top:10px;">
            <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>
            <span id="gpa-loader-text"><?php esc_html_e( 'Processing...', 'gemini-product-autocomplete' ); ?></span>
        </div>
        
        <div id="gpa-status-message" style="margin-top: 10px;"></div>
    </div>
    <?php 
}

// Render Main Features Metabox
function gpa_render_product_features_metabox(  $post  ) {
    wp_nonce_field( 'gpa_features_nonce', 'gpa_features_nonce_field' );
    $features = get_post_meta( $post->ID, '_gpa_editable_features', true );
    if ( !is_array( $features ) ) {
        $features = [];
    }
    // We check availability for SMM post
    $smm_post = get_post_meta( $post->ID, '_gpa_smm_post', true );
    ?>

    <div class="gpa-tools-box">
        <span class="gpa-tools-header"><?php esc_html_e( 'Description Tools:', 'gemini-product-autocomplete' ); ?></span>
        <button type="button" id="gpa-clear-excerpt" class="button button-link-delete gpa-text-danger">
            <?php esc_html_e( 'Clear Short Description', 'gemini-product-autocomplete' ); ?>
        </button>
    </div>
    
    <table id="gpa-features-table" class="widefat striped gpa-full-width gpa-table-layout">
        <thead>
            <tr>
                <th class="gpa-col-name"><?php esc_html_e( 'Feature Name', 'gemini-product-autocomplete' ); ?></th>
                <th><?php esc_html_e( 'Value', 'gemini-product-autocomplete' ); ?></th>
                <th class="gpa-col-action"><?php esc_html_e( 'Action', 'gemini-product-autocomplete' ); ?></th>
            </tr>
        </thead>
        <tbody class="gpa-features-wrap">
            <?php if ( !empty( $features ) ) { ?>
                <?php foreach ( $features as $index => $feature ) { ?>
                    <tr class="gpa-feature-row">
                        <td>
                            <input type="text" class="gpa-input-wide" name="gpa_feature[<?php echo esc_attr( $index ); ?>][name]" 
                                   value="<?php echo esc_attr( $feature['name'] ?? '' ); ?>" placeholder="Name">
                        </td>
                        <td>
                            <input type="text" class="gpa-input-wide" name="gpa_feature[<?php echo esc_attr( $index ); ?>][value]" 
                                   value="<?php echo esc_attr( $feature['value'] ?? '' ); ?>" placeholder="Value">
                        </td>
                        <td>
                            <button type="button" class="button gpa-remove-feature"><?php esc_html_e( 'Remove', 'gemini-product-autocomplete' ); ?></button>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" id="gpa-add-feature" class="button button-secondary"><?php esc_html_e( 'Add Feature', 'gemini-product-autocomplete' ); ?></button>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php 
}

// Save Meta
// Save Meta
function gpa_save_product_features_metabox( $post_id ) {
    // Security Checks
    if ( ! isset( $_POST['gpa_features_nonce_field'] ) 
         || ! wp_verify_nonce( wp_unslash( $_POST['gpa_features_nonce_field'] ), 'gpa_features_nonce' ) ) {
        return $post_id;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $post_id;
    }
    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return $post_id;
    }
    
    // Handle Features
    $features_input = isset( $_POST['gpa_feature'] ) 
        ? wp_unslash( $_POST['gpa_feature'] ) 
        : array();
    
    $sanitized = [];
    if ( is_array( $features_input ) ) {
        foreach ( $features_input as $f ) {
            if ( isset( $f['name'] ) && isset( $f['value'] ) ) {
                $clean_name  = sanitize_text_field( $f['name'] );
                $clean_value = sanitize_text_field( $f['value'] );
                
                if ( ! empty( $clean_name ) ) {
                    $sanitized[] = [ 'name' => $clean_name, 'value' => $clean_value ];
                }
            }
        }
    }
    
    update_post_meta( $post_id, '_gpa_editable_features', $sanitized );
}
add_action( 'save_post', 'gpa_save_product_features_metabox' );

// Display frontend attributes
function gpa_display_features_from_meta(  $attributes, $product  ) {

    if ( get_option( 'gpa_save_attributes', 0 ) == 1 ) {
        return $attributes;
    }
    $features = get_post_meta( $product->get_id(), '_gpa_editable_features', true );
    if ( empty( $features ) || !is_array( $features ) ) {
        return $attributes;
    }
    foreach ( $features as $k => $f ) {
        if ( !empty( $f['name'] ) && !empty( $f['value'] ) ) {
            $attr = new WC_Product_Attribute();
            $attr->set_name( $f['name'] );
            $attr->set_options( array($f['value']) );
            $attr->set_visible( true );
            $attr->set_variation( false );
            $attributes['gpa_' . $k] = $attr;
        }
    }
    return $attributes;
}
add_filter( 'woocommerce_product_get_attributes', 'gpa_display_features_from_meta', 10, 2 );