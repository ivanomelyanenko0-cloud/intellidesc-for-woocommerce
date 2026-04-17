<?php
// includes/admin-settings.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ildesc_register_settings() {
    $option_group = 'ildesc_settings_group';
    $page_slug    = 'ildesc_settings_page';

    // 1. Common Settings (Free & Pro)
    // ---------------------------------------------------------
    
    // API Key
    register_setting( $option_group, ILDESC_SETTINGS_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );
    
    // Templates
    register_setting( $option_group, ILDESC_CATEGORY_TEMPLATES, array('type' => 'array', 'sanitize_callback' => 'ildesc_sanitize_category_templates') );

    // Language
    register_setting( 
        $option_group, 
        ILDESC_CONTENT_LANGUAGE, 
        array(
            'type'              => 'string',
            'default'           => 'default',
            'sanitize_callback' => 'sanitize_text_field'
        )
    );

    // Unit Rules
    register_setting( $option_group, ILDESC_UNIT_RULES, array('type' => 'array', 'sanitize_callback' => 'ildesc_sanitize_unit_rules') );

    // Sections
    add_settings_section(
        'ildesc_templates_section',
        esc_html__( 'Feature Templates Settings', 'intellidesc-for-woocommerce' ),
        'ildesc_templates_section_callback',
        $page_slug
    );

    add_settings_field(
        'ildesc_category_template_fields',
        esc_html__( 'Templates by Category', 'intellidesc-for-woocommerce' ),
        'ildesc_category_template_fields_callback',
        $page_slug,
        'ildesc_templates_section'
    );

    add_settings_section(
        'ildesc_unit_rules_section',
        esc_html__( 'Unit Rules', 'intellidesc-for-woocommerce' ),
        'ildesc_unit_rules_section_callback',
        $page_slug
    );

    add_settings_field(
        'ildesc_unit_rules_fields',
        esc_html__( 'Feature Units', 'intellidesc-for-woocommerce' ),
        'ildesc_unit_rules_fields_callback',
        $page_slug,
        'ildesc_unit_rules_section'
    );
}
add_action( 'admin_init', 'ildesc_register_settings' );

function ildesc_sanitize_category_templates( $input ) {
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

function ildesc_sanitize_unit_rules( $input ) {
    if ( empty( $input ) || ! is_array( $input ) ) {
        return [];
    }
    $sanitized = [];
    foreach ( $input as $item ) {
        $feature = sanitize_text_field( $item['feature'] ?? '' );
        $unit    = sanitize_text_field( $item['unit'] ?? '' );
        if ( ! empty( $feature ) && ! empty( $unit ) ) {
            $sanitized[] = [ 'feature' => $feature, 'unit' => $unit ];
        }
    }
    return $sanitized;
}

function ildesc_templates_section_callback() {
    echo '<p>' . esc_html__( 'Define mandatory features that Gemini should look for specific WooCommerce categories.', 'intellidesc-for-woocommerce' ) . '</p>';
}

function ildesc_unit_rules_section_callback() {
    echo '<p>' . esc_html__( 'Define the exact unit or format Gemini must use when outputting specific feature values. For example: "Battery Capacity" → "mAh" will force Gemini to always output battery values as "5000 mAh".', 'intellidesc-for-woocommerce' ) . '</p>';
}

function ildesc_unit_rules_fields_callback() {
    $rules = get_option( ILDESC_UNIT_RULES, [] );
    if ( ! is_array( $rules ) ) {
        $rules = [];
    }
    ?>
    <input type="hidden" name="<?php echo esc_attr( ILDESC_UNIT_RULES ); ?>" value="" />

    <table id="ildesc-unit-rules-table" class="widefat striped ildesc-table-layout" data-index="<?php echo intval( count( $rules ) + 100 ); ?>">
        <thead>
            <tr>
                <th class="ildesc-col-name"><?php esc_html_e( 'Feature Name', 'intellidesc-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Unit / Format', 'intellidesc-for-woocommerce' ); ?></th>
                <th class="ildesc-col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rules as $index => $rule ) : ?>
                <tr class="ildesc-unit-rule-row">
                    <td>
                        <input type="text"
                               class="ildesc-input-wide"
                               name="<?php echo esc_attr( ILDESC_UNIT_RULES ); ?>[<?php echo esc_attr( $index ); ?>][feature]"
                               value="<?php echo esc_attr( $rule['feature'] ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. Battery Capacity', 'intellidesc-for-woocommerce' ); ?>">
                    </td>
                    <td>
                        <input type="text"
                               class="ildesc-input-wide"
                               name="<?php echo esc_attr( ILDESC_UNIT_RULES ); ?>[<?php echo esc_attr( $index ); ?>][unit]"
                               value="<?php echo esc_attr( $rule['unit'] ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. mAh', 'intellidesc-for-woocommerce' ); ?>">
                    </td>
                    <td>
                        <button type="button" class="button ildesc-remove-unit-rule"><?php esc_html_e( 'Remove', 'intellidesc-for-woocommerce' ); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" id="ildesc-add-unit-rule" class="button button-secondary">
                        <?php esc_html_e( 'Add Unit Rule', 'intellidesc-for-woocommerce' ); ?>
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php
}

function ildesc_category_template_fields_callback() {
    $templates = get_option( ILDESC_CATEGORY_TEMPLATES, [] );
    if (!is_array($templates)) { $templates = []; }
    $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

    ob_start();
    ?>
    <option value="0"><?php esc_html_e( '— Select Category —', 'intellidesc-for-woocommerce' ); ?></option>
    <?php foreach ( $categories as $cat ): ?>
        <option value="<?php echo esc_attr( $cat->term_id ); ?>">
            <?php echo esc_html( $cat->name ); ?>
        </option>
    <?php endforeach; 
    $category_options_html = ob_get_clean();
    ?>
    
    <input type="hidden" name="<?php echo esc_attr( ILDESC_CATEGORY_TEMPLATES ); ?>" value="" />

    <table id="ildesc-templates-table" class="widefat striped ildesc-table-layout" data-index="<?php echo intval( count( $templates ) + 100 ); ?>" data-options="<?php echo esc_attr( wp_json_encode( $category_options_html ) ); ?>">
        <thead>
            <tr>
                <th class="ildesc-col-category"><?php esc_html_e( 'WooCommerce Category', 'intellidesc-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Mandatory Features (comma separated)', 'intellidesc-for-woocommerce' ); ?></th>
                <th class="ildesc-col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $templates ) ): ?>
                <?php foreach ( $templates as $index => $template ): ?>
                    <tr class="ildesc-template-row">
                        <td>
                            <select name="<?php echo esc_attr( ILDESC_CATEGORY_TEMPLATES ); ?>[<?php echo esc_attr( $index ); ?>][category_id]" class="ildesc-input-wide">
                                <option value="0"><?php esc_html_e( '— Select Category —', 'intellidesc-for-woocommerce' ); ?></option>
                                <?php foreach ( $categories as $cat ): ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" 
                                            <?php selected( $cat->term_id, $template['category_id'] ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="<?php echo esc_attr( ILDESC_CATEGORY_TEMPLATES ); ?>[<?php echo esc_attr( $index ); ?>][features]" 
                                   value="<?php echo esc_attr( $template['features'] ); ?>" 
                                   placeholder="Processor, RAM..." class="ildesc-input-wide">
                        </td>
                        <td>
                             <button type="button" class="button ildesc-remove-template"><?php esc_html_e( 'Remove', 'intellidesc-for-woocommerce' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" id="ildesc-add-template" class="button button-secondary"><?php esc_html_e( 'Add Template', 'intellidesc-for-woocommerce' ); ?></button>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php
}

function ildesc_get_available_languages() {
    return array(
        'default' => __( 'WordPress Default Language', 'intellidesc-for-woocommerce' ),
        'en' => 'English',
        'uk' => 'Українська',
        'pl' => 'Polski',
        'de' => 'Deutsch (German)',
        'fr' => 'Français (French)',
        'es' => 'Español (Spanish)',
        'it' => 'Italiano (Italian)',
    );
}

function ildesc_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        __( 'IntelliDesc Settings', 'intellidesc-for-woocommerce' ),
        __( 'IntelliDesc', 'intellidesc-for-woocommerce' ),
        'manage_options',
        'ildesc_settings_page',
        'ildesc_settings_page_content'
    );
}
add_action( 'admin_menu', 'ildesc_add_settings_page' );

function ildesc_settings_page_content() {
    ?>
    <div class="wrap">
        <h2><?php esc_html_e( 'IntelliDesc for WooCommerce', 'intellidesc-for-woocommerce' ); ?></h2>        
        
        <details class="ildesc-info-card">
            <summary class="ildesc-info-header">
                <div class="ildesc-icon-text">
                    <span class="dashicons dashicons-info-outline"></span> 
                    <span><?php esc_html_e('How to get API Key & Pricing Limits (Read First)', 'intellidesc-for-woocommerce'); ?></span>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </summary>
            
            <div class="ildesc-info-content">
                <div class="ildesc-info-flex-container">
                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('1. How to get a FREE API Key', 'intellidesc-for-woocommerce'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'intellidesc-for-woocommerce'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</li>
                            <li><?php echo wp_kses_post( __('Click <strong>"Create API Key"</strong>.', 'intellidesc-for-woocommerce') ); ?></li>
                            <li><?php esc_html_e('Select "Create API key in new project".', 'intellidesc-for-woocommerce'); ?></li>
                            <li><?php esc_html_e('Copy the key and paste it below.', 'intellidesc-for-woocommerce'); ?></li>
                        </ol>
                    </div>

                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('2. Is it Free?', 'intellidesc-for-woocommerce'); ?></h3>
                        <p><?php echo wp_kses_post( __('Yes! The <strong>"Gemini Flash"</strong> models (2.0 / 2.5) have a generous free tier.', 'intellidesc-for-woocommerce') ); ?></p>
                        <p class="ildesc-margin-top-5"><strong><?php esc_html_e('Limits:', 'intellidesc-for-woocommerce'); ?></strong> 5 - 15 requests / minute.</p>
                        <p><em><?php esc_html_e('The plugin automatically handles delays for bulk actions to keep you safe.', 'intellidesc-for-woocommerce'); ?></em></p>
                    </div>
                </div>

                <table class="ildesc-limit-table">
                    <thead>
                        <tr>
                            <th class="ildesc-col-20"><?php esc_html_e('Plan', 'intellidesc-for-woocommerce'); ?></th>
                            <th class="ildesc-col-30"><?php esc_html_e('Speed Limit (RPM)', 'intellidesc-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Daily Limit (approx)', 'intellidesc-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Free</strong></td>
                            <td><strong>5 - 15 RPM</strong><br><span class="ildesc-text-small-muted">(Depends on model version)</span></td>
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
            <?php settings_fields( 'ildesc_settings_group' ); ?>       
            <h3><?php esc_html_e( 'Main API Settings', 'intellidesc-for-woocommerce' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Gemini API Key', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( ILDESC_SETTINGS_KEY ); ?>" 
                            value="<?php echo esc_attr( get_option( ILDESC_SETTINGS_KEY ) ); ?>" 
                            class="ildesc-api-key-input" placeholder="AIzaSy..."/>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Content Generation', 'intellidesc-for-woocommerce' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Language', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <select name="<?php echo esc_attr( ILDESC_CONTENT_LANGUAGE ); ?>">
                            <?php foreach ( ildesc_get_available_languages() as $code => $name ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( get_option( ILDESC_CONTENT_LANGUAGE, 'default' ), $code ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php do_settings_sections( 'ildesc_settings_page' ); ?>
            <hr class="ildesc-separator">
            <div class="ildesc-pro-banner">
                <h3 class="ildesc-pro-banner-title">
                    <span class="dashicons dashicons-star-filled ildesc-pro-icon"></span>
                    <?php esc_html_e( 'Unlock Advanced Features with IntelliDesc PRO', 'intellidesc-for-woocommerce' ); ?>
                </h3>
                <p><?php esc_html_e( 'Take your store automation to the next level. The PRO version includes:', 'intellidesc-for-woocommerce' ); ?></p>
                
                <ul class="ildesc-pro-list">
                    <li><strong><?php esc_html_e( 'Native WooCommerce Attributes:', 'intellidesc-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Save generated features as real, filterable global attributes.', 'intellidesc-for-woocommerce' ); ?></li>
                    <li><strong><?php esc_html_e( 'Bulk Generation Mode:', 'intellidesc-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Process 50+ products at once with our Smart Queue system (no server timeouts).', 'intellidesc-for-woocommerce' ); ?></li>
                    <li><strong><?php esc_html_e( 'SEO Meta Optimization:', 'intellidesc-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Automatically generate Yoast/RankMath Meta Titles and Descriptions.', 'intellidesc-for-woocommerce' ); ?></li>
                    <li><strong><?php esc_html_e( 'Tone of Voice & Presets:', 'intellidesc-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Customize the AI personality and use presets for Fashion, Tech, or Automotive niches.', 'intellidesc-for-woocommerce' ); ?></li>
                </ul>

                <a href="https://checkout.freemius.com/plugin/23001/plan/38599/" target="_blank" class="button button-primary">
                    <?php esc_html_e( 'Upgrade to PRO Version', 'intellidesc-for-woocommerce' ); ?>
                </a>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}