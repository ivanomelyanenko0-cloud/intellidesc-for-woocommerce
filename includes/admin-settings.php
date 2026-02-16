<?php
// includes/admin-settings.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!defined('GPA_SETTINGS_KEY')) { define( 'GPA_SETTINGS_KEY', 'gpa_gemini_api_key' ); }

define( 'GPA_CATEGORY_TEMPLATES', 'gpa_category_templates' );
define( 'GPA_CONTENT_LANGUAGE', 'gpa_content_language' );

// Pro Constants
define( 'GPA_SELECTED_MODEL', 'gpa_selected_model' );
define( 'GPA_LICENSE_KEY', 'gpa_license_key' );
define( 'GPA_LICENSE_STATUS', 'gpa_license_status' );

function gpa_register_settings() {
    $option_group = 'gpa_settings_group';
    $page_slug    = 'gpa_settings_page';

    // 1. Common Settings (Free & Pro)
    // ---------------------------------------------------------
    
    // API Key
    register_setting( $option_group, GPA_SETTINGS_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );
    
    // Templates
    register_setting( $option_group, GPA_CATEGORY_TEMPLATES, array('type' => 'array', 'sanitize_callback' => 'gpa_sanitize_category_templates') );

    // Language
    register_setting( $option_group, GPA_CONTENT_LANGUAGE, array('type' => 'string', 'default' => 'default') );

    // 3. Sections
    add_settings_section(
        'gpa_templates_section',
        esc_html__( 'Feature Templates Settings', 'gemini-product-autocomplete' ),
        'gpa_templates_section_callback',
        $page_slug 
    );
    
    add_settings_field(
        'gpa_category_template_fields',
        esc_html__( 'Templates by Category', 'gemini-product-autocomplete' ),
        'gpa_category_template_fields_callback',
        $page_slug,
        'gpa_templates_section'
    );
}
add_action( 'admin_init', 'gpa_register_settings' );

function gpa_sanitize_category_templates( $input ) {
    if ( empty($input) || !is_array($input) ) {
        return [];
    }
    $sanitized = [];
    foreach ( $input as $item ) {
        if ( ! empty( $item['category_id'] ) && ! empty( $item['features'] ) ) {
            $features = array_map( 'sanitize_text_field', array_map('trim', explode( ',', $item['features'] ) ) );
            $sanitized[] = [
                'category_id' => intval( $item['category_id'] ),
                'features' => implode( ',', array_filter( $features ) )
            ];
        }
    }
    return $sanitized;
}

function gpa_templates_section_callback() {
    echo '<p>' . esc_html__( 'Define mandatory features that Gemini should look for specific WooCommerce categories.', 'gemini-product-autocomplete' ) . '</p>';
}

function gpa_category_template_fields_callback() {
    $templates = get_option( GPA_CATEGORY_TEMPLATES, [] );
    if (!is_array($templates)) { $templates = []; }
    $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

    ob_start();
    ?>
    <option value="0"><?php esc_html_e( '— Select Category —', 'gemini-product-autocomplete' ); ?></option>
    <?php foreach ( $categories as $cat ): ?>
        <option value="<?php echo esc_attr( $cat->term_id ); ?>">
            <?php echo esc_html( $cat->name ); ?>
        </option>
    <?php endforeach; 
    $category_options_html = ob_get_clean();
    ?>
    
    <input type="hidden" name="<?php echo esc_attr( GPA_CATEGORY_TEMPLATES ); ?>" value="" />

    <table id="gpa-templates-table" class="widefat striped gpa-table-layout">
        <thead>
            <tr>
                <th class="gpa-col-category"><?php esc_html_e( 'WooCommerce Category', 'gemini-product-autocomplete' ); ?></th>
                <th><?php esc_html_e( 'Mandatory Features (comma separated)', 'gemini-product-autocomplete' ); ?></th>
                <th class="gpa-col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $templates ) ): ?>
                <?php foreach ( $templates as $index => $template ): ?>
                    <tr class="gpa-template-row">
                        <td>
                            <select name="<?php echo esc_attr( GPA_CATEGORY_TEMPLATES ); ?>[<?php echo esc_attr( $index ); ?>][category_id]" class="gpa-input-wide">
                                <option value="0"><?php esc_html_e( '— Select Category —', 'gemini-product-autocomplete' ); ?></option>
                                <?php foreach ( $categories as $cat ): ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" 
                                            <?php selected( $cat->term_id, $template['category_id'] ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="<?php echo esc_attr( GPA_CATEGORY_TEMPLATES ); ?>[<?php echo esc_attr( $index ); ?>][features]" 
                                   value="<?php echo esc_attr( $template['features'] ); ?>" 
                                   placeholder="Processor, RAM..." class="gpa-input-wide">
                        </td>
                        <td>
                             <button type="button" class="button gpa-remove-template"><?php esc_html_e( 'Remove', 'gemini-product-autocomplete' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" id="gpa-add-template" class="button button-secondary"><?php esc_html_e( 'Add Template', 'gemini-product-autocomplete' ); ?></button>
                </td>
            </tr>
        </tfoot>
    </table>
    
    <script>
        jQuery(document).ready(function($) {
            var templateIndex = <?php echo intval( count( $templates ) + 100 ); ?>;
            var categoryOptions = <?php echo wp_json_encode( $category_options_html ); ?>;
            
            $('#gpa-add-template').on('click', function() {
                var newRow = '<tr class="gpa-template-row"><td>' +
                    '<select name="<?php echo esc_attr( GPA_CATEGORY_TEMPLATES ); ?>[' + templateIndex + '][category_id]" class="gpa-input-wide">' + 
                    categoryOptions + 
                    '</select>' +
                    '</td><td>' +
                    '<input type="text" name="<?php echo esc_attr( GPA_CATEGORY_TEMPLATES ); ?>[' + templateIndex + '][features]" class="gpa-input-wide">' +
                    '</td><td>' +
                    '<button type="button" class="button gpa-remove-template"><?php esc_html_e( 'Remove', 'gemini-product-autocomplete' ); ?></button>' +
                    '</td></tr>';
                $('#gpa-templates-table tbody').append(newRow);
                templateIndex++;
            });
            
            $('#gpa-templates-table').on('click', '.gpa-remove-template', function() {
                $(this).closest('tr').remove();
            });
        });
    </script>
    <?php
}

function gpa_get_available_languages() {
    return array(
        'default' => __( 'WordPress Default Language', 'gemini-product-autocomplete' ),
        'en' => 'English',
        'uk' => 'Українська',
        'pl' => 'Polski',
        'de' => 'Deutsch (German)',
        'fr' => 'Français (French)',
        'es' => 'Español (Spanish)',
        'it' => 'Italiano (Italian)',
        // ... (truncated for brevity, same as Pro)
    );
}

function gpa_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        __( 'Gemini API Settings', 'gemini-product-autocomplete' ),
        __( 'Gemini Autocomplete', 'gemini-product-autocomplete' ),
        'manage_options',
        'gemini-autocomplete-settings',
        'gpa_settings_page_content'
    );
}
add_action( 'admin_menu', 'gpa_add_settings_page' );

function gpa_settings_page_content() {
    ?>
    <div class="wrap">
        <h2><?php esc_html_e( 'Gemini Product Autocomplete Settings', 'gemini-product-autocomplete' ); ?></h2>        
        
        <details class="gpa-info-card">
            <summary class="gpa-info-header">
                <div class="gpa-icon-text">
                    <span class="dashicons dashicons-info-outline"></span> 
                    <span><?php esc_html_e('How to get API Key & Pricing Limits (Read First)', 'gemini-product-autocomplete'); ?></span>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </summary>
            
            <div class="gpa-info-content">
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px;">
                        <h3><?php esc_html_e('1. How to get a FREE API Key', 'gemini-product-autocomplete'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'gemini-product-autocomplete'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</li>
                            <li><?php echo wp_kses_post( __('Click <strong>"Create API Key"</strong>.', 'gemini-product-autocomplete') ); ?></li>
                            <li><?php esc_html_e('Select "Create API key in new project".', 'gemini-product-autocomplete'); ?></li>
                            <li><?php esc_html_e('Copy the key and paste it below.', 'gemini-product-autocomplete'); ?></li>
                        </ol>
                    </div>

                    <div style="flex: 1; min-width: 300px;">
                        <h3><?php esc_html_e('2. Is it Free?', 'gemini-product-autocomplete'); ?></h3>
                        <p><?php echo wp_kses_post( __('Yes! The <strong>"Gemini Flash"</strong> models (2.0 / 2.5) have a generous free tier.', 'gemini-product-autocomplete') ); ?></p>
                        <p style="margin-top:5px;"><strong><?php esc_html_e('Limits:', 'gemini-product-autocomplete'); ?></strong> 5 - 15 requests / minute.</p>
                        <p><em><?php esc_html_e('The plugin automatically handles delays for bulk actions to keep you safe.', 'gemini-product-autocomplete'); ?></em></p>
                    </div>
                </div>

                <table class="gpa-limit-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?php esc_html_e('Plan', 'gemini-product-autocomplete'); ?></th>
                            <th style="width: 30%;"><?php esc_html_e('Speed Limit (RPM)', 'gemini-product-autocomplete'); ?></th>
                            <th><?php esc_html_e('Daily Limit (approx)', 'gemini-product-autocomplete'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Free</strong></td>
                            <td><strong>5 - 15 RPM</strong><br><span style="font-size:10px; color:#888;">(Depends on model version)</span></td>
                            <td>~1,500 Requests / Day</td>
                        </tr>
                        <tr>
                            <td><strong>Paid</strong></td>
                            <td>1,000+ RPM</td>
                            <td>Unlimited</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>

        <form method="post" action="options.php">
            <?php settings_fields( 'gpa_settings_group' ); ?>       
            <h3><?php esc_html_e( 'Main API Settings', 'gemini-product-autocomplete' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Gemini API Key', 'gemini-product-autocomplete' ); ?></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( GPA_SETTINGS_KEY ); ?>" 
                               value="<?php echo esc_attr( get_option( GPA_SETTINGS_KEY ) ); ?>" 
                               style="width: 400px;" placeholder="AIzaSy..."/>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Content Generation', 'gemini-product-autocomplete' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Language', 'gemini-product-autocomplete' ); ?></th>
                    <td>
                        <select name="<?php echo esc_attr( GPA_CONTENT_LANGUAGE ); ?>">
                            <?php foreach ( gpa_get_available_languages() as $code => $name ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( get_option( GPA_CONTENT_LANGUAGE, 'default' ), $code ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
                <hr style="margin: 30px 0;">
                <div style="opacity: 0.6; pointer-events: none; filter: grayscale(1);">
                    <h3>
                        <?php esc_html_e( 'Advanced Content Settings', 'gemini-product-autocomplete' ); ?> 
                        <span style="background: #2271b1; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px; vertical-align: middle; margin-left: 10px;">PRO ONLY</span>
                    </h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Tone of Voice', 'gemini-product-autocomplete' ); ?></th>
                            <td>
                                <select disabled>
                                    <option selected>Neutral / Informative</option>
                                    <option>Persuasive (Sales)</option>
                                    <option>Playful / Fun</option>
                                    <option>Luxury / Elegant</option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Available in Pro: Adjust the AI personality to match your brand.', 'gemini-product-autocomplete' ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Product Presets', 'gemini-product-autocomplete' ); ?></th>
                            <td>
                                <select disabled>
                                    <option selected>Generic</option>
                                    <option>Fashion & Apparel</option>
                                    <option>Electronics</option>
                                    <option>Beauty & Cosmetics</option>
                                    <option>Automotive</option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Available in Pro: Optimized prompts for specific niches (e.g. Fabric for clothes, Specs for tech).', 'gemini-product-autocomplete' ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'SEO Optimization', 'gemini-product-autocomplete' ); ?></th>
                            <td>
                                 <label><input type="checkbox" disabled> <?php esc_html_e('Generate Meta Title & Description (Yoast/RankMath support)', 'gemini-product-autocomplete'); ?></label>
                            </td>
                        </tr>
                         <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Bulk Generation', 'gemini-product-autocomplete' ); ?></th>
                            <td>
                                 <label><input type="checkbox" disabled> <?php esc_html_e('Enable Bulk Actions for Product List', 'gemini-product-autocomplete'); ?></label>
                                 <p class="description"><?php esc_html_e( 'Generate content for 100+ products in one click.', 'gemini-product-autocomplete' ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Save as Attributes', 'gemini-product-autocomplete' ); ?></th>
                            <td>
                                 <label><input type="checkbox" disabled> <?php esc_html_e('Save features as real WooCommerce attributes for filtering', 'gemini-product-autocomplete'); ?></label>
                            </td>
                        </tr>
                    </table>
                </div>
                <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #72aee6; margin-top: 10px;">
                    <p style="margin: 0;"><strong><?php esc_html_e('Want these features?', 'gemini-product-autocomplete'); ?></strong> <a href="https://checkout.freemius.com/plugin/23001/plan/38599/" target="_blank" style="font-weight: bold;"><?php esc_html_e('Upgrade to PRO Version', 'gemini-product-autocomplete'); ?></a></p>
                </div>
            
            <?php do_settings_sections( 'gpa_settings_page' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}