<?php
/**
 * Plugin Name:       IntelliDesc for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/intellidesc-for-woocommerce/
 * Description:       Automatically fills product features using Google Gemini API.
 * Version:           1.5.0
 * Author:            Ivan O.
 * Author URI:        https://profiles.wordpress.org/lukystile/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       intellidesc-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
define( 'ILDESC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ILDESC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ILDESC_SETTINGS_KEY', 'ildesc_gemini_api_key' );
define( 'ILDESC_SAVE_ATTRIBUTES', 'ildesc_save_attributes' );
define( 'ILDESC_CATEGORY_TEMPLATES', 'ildesc_category_templates' );
define( 'ILDESC_CONTENT_LANGUAGE',   'ildesc_content_language' );
define( 'ILDESC_OVERWRITE_DATA',     'ildesc_overwrite_data' );
define( 'ILDESC_UNIT_RULES',         'ildesc_unit_rules' );


// Enqueue Assets (Unified function)
function ildesc_enqueue_admin_assets(  $hook  ) {
    $is_settings_page = strpos( $hook, 'ildesc_settings_page' ) !== false;
    $is_product_page  = in_array( $hook, ['post.php', 'post-new.php', 'edit.php'] );
    
    global $post;
    if ( ! $is_settings_page ) {
        if ( ! $is_product_page || ( $hook !== 'edit.php' && ( ! isset( $post ) || $post->post_type !== 'product' ) ) ) {
            return;
        }
    }

    // CSS
    wp_enqueue_style( 'ildesc-admin-style', ILDESC_PLUGIN_URL . 'assets/admin-style.css', array(), '1.3.0' );
    
    // JS 
    wp_enqueue_script( 'ildesc-admin-script', ILDESC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.3.0', true );
    
    // Localize JS
    wp_localize_script( 'ildesc-admin-script', 'ildesc_params', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'ildesc_autocomplete_nonce' ),
        'no_title'        => __( 'Please enter a product title first.', 'intellidesc-for-woocommerce' ),
        'no_selected'     => __( 'No products selected!', 'intellidesc-for-woocommerce' ),
        'confirm_bulk'    => __( 'Start generation for selected products?', 'intellidesc-for-woocommerce' ),
        'loading_text'    => __( 'Sending request to Gemini...', 'intellidesc-for-woocommerce' ),
        'btn_default'     => __( 'Generate Content', 'intellidesc-for-woocommerce' ),
        'btn_loading'     => __( 'Generating...', 'intellidesc-for-woocommerce' ),
        'status_success'  => __( 'Success! Data saved.', 'intellidesc-for-woocommerce' ),
        'status_done'     => __( 'Completed! Refreshing page...', 'intellidesc-for-woocommerce' ),
        'status_error'    => __( 'Error: ', 'intellidesc-for-woocommerce' ),
        'server_error'    => __( 'Server connection failed.', 'intellidesc-for-woocommerce' ),
        'bulk_processing' => __( 'Processing', 'intellidesc-for-woocommerce' ),
        'bulk_retry'      => __( 'Retry', 'intellidesc-for-woocommerce' ),
        'bulk_failed'     => __( 'Failed', 'intellidesc-for-woocommerce' ),
        'close_btn'       => __( 'Close', 'intellidesc-for-woocommerce' ),
        'stop_btn'        => __( 'Stop', 'intellidesc-for-woocommerce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'ildesc_enqueue_admin_assets' );

// Include other files
require_once ILDESC_PLUGIN_DIR . 'includes/admin-settings.php';
require_once ILDESC_PLUGIN_DIR . 'includes/ajax-handler.php';

// Register Metaboxes
function ildesc_register_metaboxes() {
    add_meta_box(
        'ildesc_actions_metabox',
        __( 'Gemini AI Actions', 'intellidesc-for-woocommerce' ),
        'ildesc_render_actions_metabox',
        'product',
        'side',
        'high'
    );
    add_meta_box(
        'ildesc_features_metabox',
        __( 'Gemini AI Features (Edit)', 'intellidesc-for-woocommerce' ),
        'ildesc_render_product_features_metabox',
        'product',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'ildesc_register_metaboxes' );

// Render Side Metabox
function ildesc_render_actions_metabox(  $post  ) {
    ?>
    <div class="ildesc-actions-wrapper">
        <p class="description">
            <?php esc_html_e( 'Click below to generate description and features based on title.', 'intellidesc-for-woocommerce' ); ?>
        </p>
        <button type="button" id="ildesc-trigger-btn" class="button button-primary ildesc-full-width">
            <?php esc_html_e( 'Generate Content', 'intellidesc-for-woocommerce' ); ?>
        </button>

        <div id="ildesc-loader" class="ildesc-loader-container">
            <span class="spinner is-active ildesc-spinner-inline"></span>
            <span id="ildesc-loader-text"><?php esc_html_e( 'Processing...', 'intellidesc-for-woocommerce' ); ?></span>
        </div>
        
        <div id="ildesc-status-message" class="ildesc-margin-top-10"></div>
    </div>
    <?php 
}

// Render Main Features Metabox
function ildesc_render_product_features_metabox(  $post  ) {
    wp_nonce_field( 'ildesc_features_nonce', 'ildesc_features_nonce_field' );
    $features = get_post_meta( $post->ID, '_ildesc_editable_features', true );
    if ( !is_array( $features ) ) {
        $features = [];
    }
    // We check availability for SMM post
    $smm_post = get_post_meta( $post->ID, '_ildesc_smm_post', true );
    ?>

    <div class="ildesc-tools-box">
        <span class="ildesc-tools-header"><?php esc_html_e( 'Description Tools:', 'intellidesc-for-woocommerce' ); ?></span>
        <button type="button" id="ildesc-clear-excerpt" class="button button-link-delete ildesc-text-danger">
            <?php esc_html_e( 'Clear Short Description', 'intellidesc-for-woocommerce' ); ?>
        </button>
    </div>
    
    <table id="ildesc-features-table" class="widefat striped ildesc-full-width ildesc-table-layout">
        <thead>
            <tr>
                <th class="ildesc-col-name"><?php esc_html_e( 'Feature Name', 'intellidesc-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Value', 'intellidesc-for-woocommerce' ); ?></th>
                <th class="ildesc-col-action"><?php esc_html_e( 'Action', 'intellidesc-for-woocommerce' ); ?></th>
            </tr>
        </thead>
        <tbody class="ildesc-features-wrap">
            <?php if ( !empty( $features ) ) { ?>
                <?php foreach ( $features as $index => $feature ) { ?>
                    <tr class="ildesc-feature-row">
                        <td>
                            <input type="text" class="ildesc-input-wide" name="ildesc_feature[<?php echo esc_attr( $index ); ?>][name]" 
                                   value="<?php echo esc_attr( $feature['name'] ?? '' ); ?>" placeholder="Name">
                        </td>
                        <td>
                            <input type="text" class="ildesc-input-wide" name="ildesc_feature[<?php echo esc_attr( $index ); ?>][value]" 
                                   value="<?php echo esc_attr( $feature['value'] ?? '' ); ?>" placeholder="Value">
                        </td>
                        <td>
                            <button type="button" class="button ildesc-remove-feature"><?php esc_html_e( 'Remove', 'intellidesc-for-woocommerce' ); ?></button>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" id="ildesc-add-feature" class="button button-secondary"><?php esc_html_e( 'Add Feature', 'intellidesc-for-woocommerce' ); ?></button>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php 
}

// Save Meta
function ildesc_save_product_features_metabox( $post_id ) {
    // Security Checks
    if ( ! isset( $_POST['ildesc_features_nonce_field'] ) 
         || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ildesc_features_nonce_field'] ) ), 'ildesc_features_nonce' ) ) {
        return $post_id;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $post_id;
    }
    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return $post_id;
    }
    
    // Handle Features
    $features_input = isset( $_POST['ildesc_feature'] ) 
        ? map_deep( wp_unslash( $_POST['ildesc_feature'] ), 'sanitize_text_field' ) 
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
    
    update_post_meta( $post_id, '_ildesc_editable_features', $sanitized );
}
add_action( 'save_post', 'ildesc_save_product_features_metabox' );

// Display frontend attributes
function ildesc_display_features_from_meta(  $attributes, $product  ) {

    if ( get_option( ILDESC_SAVE_ATTRIBUTES, 0 ) == 1 ) {
        return $attributes;
    }
    $features = get_post_meta( $product->get_id(), '_ildesc_editable_features', true );
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
            $attributes['ildesc_' . $k] = $attr;
        }
    }
    return $attributes;
}
add_filter( 'woocommerce_product_get_attributes', 'ildesc_display_features_from_meta', 10, 2 );