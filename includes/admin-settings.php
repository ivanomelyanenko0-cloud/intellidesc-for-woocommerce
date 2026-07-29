<?php
// includes/admin-settings.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ildesc_get_gemini_models() {
    $api_key = get_option( ILDESC_SETTINGS_KEY );
    if ( empty( $api_key ) ) return [];

    if ( isset( $_GET['refresh_models'] ) && '1' === $_GET['refresh_models'] && current_user_can( 'manage_options' )
        && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ildesc_refresh_models' ) ) {
        delete_transient( 'ildesc_gemini_models_list' );
    }

    $cached = get_transient( 'ildesc_gemini_models_list' );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['models'] ) ) return [];

    $models    = [];
    $blacklist = [ 'nano', 'embedding', 'tts', 'aqa', 'vision', 'preview', 'Experimental', 'exp', 'image', 'img' ];

    foreach ( $body['models'] as $model ) {
        $name = $model['name'];
        if ( strpos( $name, 'gemini' ) === false || ! in_array( 'generateContent', $model['supportedGenerationMethods'] ) ) continue;
        foreach ( $blacklist as $bad ) {
            if ( strpos( $name, $bad ) !== false ) continue 2;
        }
        $id          = str_replace( 'models/', '', $name );
        $models[$id] = $model['displayName'] . ' (' . $model['version'] . ')';
    }

    krsort( $models );
    set_transient( 'ildesc_gemini_models_list', $models, 86400 );
    return $models;
}

function ildesc_get_anthropic_models() {
    $api_key = get_option( ILDESC_ANTHROPIC_API_KEY );
    if ( empty( $api_key ) ) return [];

    if ( isset( $_GET['refresh_models'] ) && '1' === $_GET['refresh_models'] && current_user_can( 'manage_options' )
        && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ildesc_refresh_models' ) ) {
        delete_transient( 'ildesc_anthropic_models_list' );
    }

    $cached = get_transient( 'ildesc_anthropic_models_list' );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( 'https://api.anthropic.com/v1/models', [
        'headers' => [
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'timeout' => 15,
    ] );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['data'] ) ) return [];

    $models = [];
    foreach ( $body['data'] as $model ) {
        $models[ $model['id'] ] = $model['display_name'] ?? $model['id'];
    }

    set_transient( 'ildesc_anthropic_models_list', $models, 86400 );
    return $models;
}

function ildesc_get_openai_models() {
    $api_key = get_option( ILDESC_OPENAI_API_KEY );
    if ( empty( $api_key ) ) return [];

    if ( isset( $_GET['refresh_models'] ) && '1' === $_GET['refresh_models'] && current_user_can( 'manage_options' )
        && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ildesc_refresh_models' ) ) {
        delete_transient( 'ildesc_openai_models_list' );
    }

    $cached = get_transient( 'ildesc_openai_models_list' );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( 'https://api.openai.com/v1/models', [
        'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
        'timeout' => 15,
    ] );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['data'] ) ) return [];

    $models            = [];
    $allowed_prefixes  = [ 'gpt-', 'o1', 'o3', 'o4', 'chatgpt-' ];
    $blacklist         = [ 'whisper', 'tts', 'dall-e', 'embedding', 'moderation', 'davinci', 'babbage', 'audio', 'realtime', 'transcribe', 'image', 'search', 'similarity', 'edit', 'insert', 'instruct' ];

    foreach ( $body['data'] as $model ) {
        $id = $model['id'];

        $is_allowed = false;
        foreach ( $allowed_prefixes as $prefix ) {
            if ( strpos( $id, $prefix ) === 0 ) { $is_allowed = true; break; }
        }
        if ( ! $is_allowed ) continue;

        foreach ( $blacklist as $bad ) {
            if ( strpos( $id, $bad ) !== false ) continue 2;
        }

        $models[ $id ] = $id;
    }

    krsort( $models );
    set_transient( 'ildesc_openai_models_list', $models, 86400 );
    return $models;
}

function ildesc_get_xai_models() {
    $api_key = get_option( ILDESC_XAI_API_KEY );
    if ( empty( $api_key ) ) return [];

    if ( isset( $_GET['refresh_models'] ) && '1' === $_GET['refresh_models'] && current_user_can( 'manage_options' )
        && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ildesc_refresh_models' ) ) {
        delete_transient( 'ildesc_xai_models_list' );
    }

    $cached = get_transient( 'ildesc_xai_models_list' );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get( 'https://api.x.ai/v1/models', [
        'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
        'timeout' => 15,
    ] );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['data'] ) ) return [];

    $models = [];
    foreach ( $body['data'] as $model ) {
        $models[ $model['id'] ] = $model['id'];
    }

    krsort( $models );
    set_transient( 'ildesc_xai_models_list', $models, 86400 );
    return $models;
}

/**
 * Renders a small status readout below a provider's model field: the raw
 * saved model id (resolved to a human label when possible), plus a warning
 * when it's missing from the freshly-fetched model list or was recently
 * reported as unavailable by the provider (see ildesc_ai_error_from_response()).
 */
function ildesc_render_model_status_notice( $provider, $saved_model, $models ) {
    echo '<p class="description ildesc-current-model">';
    echo esc_html__( 'Currently active model:', 'intellidesc-for-woocommerce' ) . ' <code>' . esc_html( $saved_model ) . '</code>';
    if ( isset( $models[ $saved_model ] ) ) {
        echo ' — ' . esc_html( $models[ $saved_model ] );
    } elseif ( ! empty( $models ) ) {
        echo ' <span class="ildesc-text-danger">(' . esc_html__( 'not found in the latest model list — may be deprecated or renamed', 'intellidesc-for-woocommerce' ) . ')</span>';
    }
    echo '</p>';

    $flag = ildesc_get_model_unavailable_flag( $provider );
    if ( $flag && $flag['model'] === $saved_model ) {
        echo '<p class="description ildesc-text-danger">' . esc_html( sprintf(
            /* translators: 1: model id, 2: date, 3: raw API error message */
            __( 'The model "%1$s" failed with a "model not found" error on %2$s: %3$s. Please select a different model above and save your settings.', 'intellidesc-for-woocommerce' ),
            $flag['model'],
            date_i18n( get_option( 'date_format' ), $flag['time'] ),
            $flag['message']
        ) ) . '</p>';
    }
}

/**
 * Shows a dismissible admin notice when the currently configured AI model
 * was recently reported as unavailable (404) by its provider — scoped to
 * the IntelliDesc settings page and the WooCommerce product edit/list
 * screens, since those are the places a merchant can act on it.
 */
add_action( 'admin_notices', 'ildesc_deprecated_model_admin_notice' );
function ildesc_deprecated_model_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    $is_relevant_screen = ( strpos( $screen->id, 'ildesc_settings_page' ) !== false )
        || ( 'product' === $screen->post_type && in_array( $screen->base, [ 'post', 'edit' ], true ) );
    if ( ! $is_relevant_screen ) {
        return;
    }

    $provider = ildesc_get_current_provider();
    $flag     = ildesc_get_model_unavailable_flag( $provider );
    if ( ! $flag || $flag['model'] !== ildesc_get_model_for_provider( $provider ) ) {
        return;
    }

    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        esc_html( sprintf(
            /* translators: 1: AI provider name, 2: model id */
            __( 'IntelliDesc: your selected %1$s model "%2$s" recently failed with a "model not found" error — it may have been deprecated or renamed. Go to WooCommerce → IntelliDesc to pick a different model.', 'intellidesc-for-woocommerce' ),
            ildesc_ai_provider_label( $provider ),
            $flag['model']
        ) )
    );
}

function ildesc_register_settings() {
    $option_group = 'ildesc_settings_group';
    $page_slug    = 'ildesc_settings_page';

    // 1. Common Settings (Free & Pro)
    // ---------------------------------------------------------
    
    // API Key
    register_setting( $option_group, ILDESC_SETTINGS_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );

    // Selected model
    register_setting( $option_group, ILDESC_SELECTED_MODEL, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );

    // AI Provider selection
    register_setting( $option_group, ILDESC_AI_PROVIDER, array('type' => 'string', 'default' => 'gemini', 'sanitize_callback' => 'sanitize_text_field') );

    // Anthropic Claude
    register_setting( $option_group, ILDESC_ANTHROPIC_API_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );
    register_setting( $option_group, ILDESC_ANTHROPIC_MODEL, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );

    // OpenAI
    register_setting( $option_group, ILDESC_OPENAI_API_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );
    register_setting( $option_group, ILDESC_OPENAI_MODEL, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );

    // xAI Grok
    register_setting( $option_group, ILDESC_XAI_API_KEY, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );
    register_setting( $option_group, ILDESC_XAI_MODEL, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field') );

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
    register_setting( $option_group, ILDESC_SKIP_FEATURES, array('type' => 'integer', 'sanitize_callback' => 'intval', 'default' => 0) );

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
    echo '<p>' . esc_html__( 'Define mandatory features that the AI should look for specific WooCommerce categories.', 'intellidesc-for-woocommerce' ) . '</p>';
}

function ildesc_unit_rules_section_callback() {
    echo '<p>' . esc_html__( 'Define the exact unit or format the AI must use when outputting specific feature values. For example: "Battery Capacity" → "mAh" will force the AI to always output battery values as "5000 mAh".', 'intellidesc-for-woocommerce' ) . '</p>';
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
                    <td style="text-align:center">
                        <button type="button" class="button ildesc-remove-unit-rule"
                            aria-label="<?php esc_attr_e( 'Remove', 'intellidesc-for-woocommerce' ); ?>"
                            title="<?php esc_attr_e( 'Remove', 'intellidesc-for-woocommerce' ); ?>">&#x2715;</button>
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
                        <td style="text-align:center">
                            <button type="button" class="button ildesc-remove-template"
                                aria-label="<?php esc_attr_e( 'Remove', 'intellidesc-for-woocommerce' ); ?>"
                                title="<?php esc_attr_e( 'Remove', 'intellidesc-for-woocommerce' ); ?>">&#x2715;</button>
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

/**
 * Renders a password-style API key field with a show/hide toggle and a
 * "Key is set / Not configured" status badge. Shared across all providers
 * so every "AI Provider" row looks and behaves identically.
 */
function ildesc_render_api_key_field( $option_name, $placeholder ) {
    $value = get_option( $option_name );
    ?>
    <div class="ildesc-api-key-wrap">
        <input type="password"
            name="<?php echo esc_attr( $option_name ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            class="ildesc-api-key-input ildesc-api-key-field"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            autocomplete="current-password" />
        <button type="button" class="button ildesc-toggle-api-key"
            title="<?php esc_attr_e( 'Show / Hide key', 'intellidesc-for-woocommerce' ); ?>">
            <span class="dashicons dashicons-visibility"></span>
        </button>
        <?php if ( ! empty( $value ) ) : ?>
            <span class="ildesc-key-status is-set">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Key is set', 'intellidesc-for-woocommerce' ); ?>
            </span>
        <?php else : ?>
            <span class="ildesc-key-status is-missing">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'Not configured', 'intellidesc-for-woocommerce' ); ?>
            </span>
        <?php endif; ?>
    </div>
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
        <div class="ildesc-page-header">
            <h1><?php esc_html_e( 'IntelliDesc for WooCommerce', 'intellidesc-for-woocommerce' ); ?></h1>
            <span class="ildesc-page-badge">AI Powered</span>
        </div>

        <details class="ildesc-info-card">
            <summary class="ildesc-info-header">
                <div class="ildesc-icon-text">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span><?php esc_html_e('How to get an API Key & Pricing (Read First)', 'intellidesc-for-woocommerce'); ?></span>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </summary>

            <div class="ildesc-info-content">
                <p class="description"><?php esc_html_e('Pick one AI provider below, grab an API key from it, then select that provider in the form under "Main API Settings".', 'intellidesc-for-woocommerce'); ?></p>

                <div class="ildesc-info-flex-container">
                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('Google Gemini', 'intellidesc-for-woocommerce'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'intellidesc-for-woocommerce'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</li>
                            <li><?php echo wp_kses_post( __('Click <strong>"Create API Key"</strong>.', 'intellidesc-for-woocommerce') ); ?></li>
                            <li><?php esc_html_e('Select "Create API key in new project" and copy it.', 'intellidesc-for-woocommerce'); ?></li>
                        </ol>
                        <p><?php echo wp_kses_post( __('<strong>Free tier:</strong> 5–15 requests/minute, ~1,500/day. Paid: 1,000+ RPM, unlimited.', 'intellidesc-for-woocommerce') ); ?></p>
                    </div>

                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('Anthropic Claude', 'intellidesc-for-woocommerce'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'intellidesc-for-woocommerce'); ?> <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>.</li>
                            <li><?php echo wp_kses_post( __('Open <strong>Settings → API Keys</strong> and click "Create Key".', 'intellidesc-for-woocommerce') ); ?></li>
                            <li><?php esc_html_e('Add billing credit under Settings → Billing.', 'intellidesc-for-woocommerce'); ?></li>
                        </ol>
                        <p><em><?php esc_html_e('Pay-as-you-go — no free tier. Requires a paid credit balance.', 'intellidesc-for-woocommerce'); ?></em></p>
                    </div>

                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('OpenAI', 'intellidesc-for-woocommerce'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'intellidesc-for-woocommerce'); ?> <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</li>
                            <li><?php echo wp_kses_post( __('Click <strong>"Create new secret key"</strong>.', 'intellidesc-for-woocommerce') ); ?></li>
                            <li><?php esc_html_e('Add billing credit under Settings → Billing.', 'intellidesc-for-woocommerce'); ?></li>
                        </ol>
                        <p><em><?php esc_html_e('Pay-as-you-go — no free tier. Requires a paid credit balance.', 'intellidesc-for-woocommerce'); ?></em></p>
                    </div>

                    <div class="ildesc-info-flex-item">
                        <h3><?php esc_html_e('xAI Grok', 'intellidesc-for-woocommerce'); ?></h3>
                        <ol>
                            <li><?php esc_html_e('Go to', 'intellidesc-for-woocommerce'); ?> <a href="https://console.x.ai" target="_blank">xAI Console</a>.</li>
                            <li><?php echo wp_kses_post( __('Open <strong>API Keys</strong> and create a new key.', 'intellidesc-for-woocommerce') ); ?></li>
                            <li><?php esc_html_e('Add billing credit to your account.', 'intellidesc-for-woocommerce'); ?></li>
                        </ol>
                        <p><em><?php esc_html_e('Pay-as-you-go — no free tier. Requires a paid credit balance.', 'intellidesc-for-woocommerce'); ?></em></p>
                    </div>
                </div>
            </div>
        </details>

        <form method="post" action="options.php" class="ildesc-settings-form">
            <?php settings_fields( 'ildesc_settings_group' ); ?>
            <h3><?php esc_html_e( 'Main API Settings', 'intellidesc-for-woocommerce' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'AI Provider', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php $current_provider = ildesc_get_current_provider(); ?>
                        <select name="<?php echo esc_attr( ILDESC_AI_PROVIDER ); ?>" id="ildesc-provider-select">
                            <option value="gemini" <?php selected( $current_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'intellidesc-for-woocommerce' ); ?></option>
                            <option value="anthropic" <?php selected( $current_provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic Claude', 'intellidesc-for-woocommerce' ); ?></option>
                            <option value="openai" <?php selected( $current_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'intellidesc-for-woocommerce' ); ?></option>
                            <option value="xai" <?php selected( $current_provider, 'xai' ); ?>><?php esc_html_e( 'xAI Grok', 'intellidesc-for-woocommerce' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Choose which AI provider generates your product content.', 'intellidesc-for-woocommerce' ); ?></p>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-gemini" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'Gemini API Key', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php ildesc_render_api_key_field( ILDESC_SETTINGS_KEY, 'AIzaSy...' ); ?>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-gemini" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'AI Model', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php
                        $models         = ildesc_get_gemini_models();
                        $selected_model = get_option( ILDESC_SELECTED_MODEL, 'gemini-3.1-flash-lite' );

                        if ( empty( $models ) ) {
                            echo '<p class="description ildesc-text-danger">' . esc_html__( 'Please save a valid API Key first to fetch available models.', 'intellidesc-for-woocommerce' ) . '</p>';
                            echo '<input type="text" class="regular-text" name="' . esc_attr( ILDESC_SELECTED_MODEL ) . '" value="' . esc_attr( $selected_model ) . '" placeholder="gemini-3.1-flash-lite">';
                        } else {
                            echo '<select name="' . esc_attr( ILDESC_SELECTED_MODEL ) . '">';
                            foreach ( $models as $id => $label ) {
                                echo '<option value="' . esc_attr( $id ) . '" ' . selected( $selected_model, $id, false ) . '>' . esc_html( $label . ' [' . $id . ']' ) . '</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                        <p class="description">
                            <a href="<?php echo esc_url( add_query_arg( array( 'refresh_models' => 1, '_wpnonce' => wp_create_nonce( 'ildesc_refresh_models' ) ) ) ); ?>"><?php esc_html_e( 'Refresh model list', 'intellidesc-for-woocommerce' ); ?></a>
                        </p>
                        <?php ildesc_render_model_status_notice( 'gemini', $selected_model, $models ); ?>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-anthropic" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'Anthropic API Key', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php ildesc_render_api_key_field( ILDESC_ANTHROPIC_API_KEY, 'sk-ant-...' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Get your key from', 'intellidesc-for-woocommerce' ); ?> <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>.
                        </p>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-anthropic" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'Claude Model', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php
                        $models           = ildesc_get_anthropic_models();
                        $anthropic_model  = get_option( ILDESC_ANTHROPIC_MODEL, 'claude-sonnet-4-5-20250929' );

                        if ( empty( $models ) ) {
                            echo '<p class="description ildesc-text-danger">' . esc_html__( 'Please save a valid API Key first to fetch available models.', 'intellidesc-for-woocommerce' ) . '</p>';
                            echo '<input type="text" class="regular-text" name="' . esc_attr( ILDESC_ANTHROPIC_MODEL ) . '" value="' . esc_attr( $anthropic_model ) . '" placeholder="claude-sonnet-4-5-20250929">';
                        } else {
                            echo '<select name="' . esc_attr( ILDESC_ANTHROPIC_MODEL ) . '">';
                            foreach ( $models as $id => $label ) {
                                echo '<option value="' . esc_attr( $id ) . '" ' . selected( $anthropic_model, $id, false ) . '>' . esc_html( $label ) . '</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                        <p class="description">
                            <a href="<?php echo esc_url( add_query_arg( array( 'refresh_models' => 1, '_wpnonce' => wp_create_nonce( 'ildesc_refresh_models' ) ) ) ); ?>"><?php esc_html_e( 'Refresh model list', 'intellidesc-for-woocommerce' ); ?></a>
                        </p>
                        <?php ildesc_render_model_status_notice( 'anthropic', $anthropic_model, $models ); ?>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-openai" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'OpenAI API Key', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php ildesc_render_api_key_field( ILDESC_OPENAI_API_KEY, 'sk-...' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Get your key from', 'intellidesc-for-woocommerce' ); ?> <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>.
                        </p>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-openai" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'OpenAI Model', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php
                        $models       = ildesc_get_openai_models();
                        $openai_model = get_option( ILDESC_OPENAI_MODEL, 'gpt-4.1-mini' );

                        if ( empty( $models ) ) {
                            echo '<p class="description ildesc-text-danger">' . esc_html__( 'Please save a valid API Key first to fetch available models.', 'intellidesc-for-woocommerce' ) . '</p>';
                            echo '<input type="text" class="regular-text" name="' . esc_attr( ILDESC_OPENAI_MODEL ) . '" value="' . esc_attr( $openai_model ) . '" placeholder="gpt-4.1-mini">';
                        } else {
                            echo '<select name="' . esc_attr( ILDESC_OPENAI_MODEL ) . '">';
                            foreach ( $models as $id => $label ) {
                                echo '<option value="' . esc_attr( $id ) . '" ' . selected( $openai_model, $id, false ) . '>' . esc_html( $label ) . '</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                        <p class="description">
                            <a href="<?php echo esc_url( add_query_arg( array( 'refresh_models' => 1, '_wpnonce' => wp_create_nonce( 'ildesc_refresh_models' ) ) ) ); ?>"><?php esc_html_e( 'Refresh model list', 'intellidesc-for-woocommerce' ); ?></a>
                        </p>
                        <?php ildesc_render_model_status_notice( 'openai', $openai_model, $models ); ?>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-xai" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'xAI API Key', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php ildesc_render_api_key_field( ILDESC_XAI_API_KEY, 'xai-...' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Get your key from', 'intellidesc-for-woocommerce' ); ?> <a href="https://console.x.ai" target="_blank">console.x.ai</a>.
                        </p>
                    </td>
                </tr>
                <tr valign="top" class="ildesc-provider-row ildesc-provider-row-xai" style="display:none;">
                    <th scope="row"><?php esc_html_e( 'Grok Model', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <?php
                        $models   = ildesc_get_xai_models();
                        $xai_model = get_option( ILDESC_XAI_MODEL, 'grok-4-fast' );

                        if ( empty( $models ) ) {
                            echo '<p class="description ildesc-text-danger">' . esc_html__( 'Please save a valid API Key first to fetch available models.', 'intellidesc-for-woocommerce' ) . '</p>';
                            echo '<input type="text" class="regular-text" name="' . esc_attr( ILDESC_XAI_MODEL ) . '" value="' . esc_attr( $xai_model ) . '" placeholder="grok-4-fast">';
                        } else {
                            echo '<select name="' . esc_attr( ILDESC_XAI_MODEL ) . '">';
                            foreach ( $models as $id => $label ) {
                                echo '<option value="' . esc_attr( $id ) . '" ' . selected( $xai_model, $id, false ) . '>' . esc_html( $label ) . '</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                        <p class="description">
                            <a href="<?php echo esc_url( add_query_arg( array( 'refresh_models' => 1, '_wpnonce' => wp_create_nonce( 'ildesc_refresh_models' ) ) ) ); ?>"><?php esc_html_e( 'Refresh model list', 'intellidesc-for-woocommerce' ); ?></a>
                        </p>
                        <?php ildesc_render_model_status_notice( 'xai', $xai_model, $models ); ?>
                    </td>
                </tr>
            </table>
            <script>
            (function($){
                function ildescToggleProviderRows() {
                    var provider = $('#ildesc-provider-select').val();
                    $('.ildesc-provider-row').hide();
                    $('.ildesc-provider-row-' + provider).show();
                }
                $(document).on('change', '#ildesc-provider-select', ildescToggleProviderRows);
                $(ildescToggleProviderRows);
            })(jQuery);
            </script>

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
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Generate Descriptions Only', 'intellidesc-for-woocommerce' ); ?></th>
                    <td>
                        <input type="hidden" name="<?php echo esc_attr( ILDESC_SKIP_FEATURES ); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr( ILDESC_SKIP_FEATURES ); ?>" value="1" <?php checked( get_option( ILDESC_SKIP_FEATURES ), 1 ); ?> />
                        <p class="description"><?php esc_html_e( 'If checked, the AI will only generate the Short and Long Descriptions. Feature extraction (technical specs table) will be skipped.', 'intellidesc-for-woocommerce' ); ?></p>
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