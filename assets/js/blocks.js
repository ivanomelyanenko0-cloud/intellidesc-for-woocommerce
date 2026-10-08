/**
 * IntelliDesc blocks: FAQ and Specs. Plain JS (no build step), rendered on the
 * server by the same functions as the [ildesc_faq] / [ildesc_specs] shortcodes
 * (includes/shortcodes.php), so the editor preview matches the site exactly.
 */
( function ( wp, config ) {
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var ComboboxControl = wp.components.ComboboxControl;
    var ServerSideRender = wp.serverSideRender;
    var apiFetch = wp.apiFetch;
    var decode = wp.htmlEntities.decodeEntities;
    var isPro = !! ( config && config.isPro );

    function toOption( product ) {
        return { value: product.id, label: decode( product.title.rendered ) + ' (#' + product.id + ')' };
    }

    /**
     * Product search. Empty value = the current product (product pages,
     * Query Loop, Single Product template).
     */
    function ProductPicker( props ) {
        var state = useState( [] );
        var options = state[ 0 ];
        var setOptions = state[ 1 ];

        function merge( list ) {
            setOptions( function ( current ) {
                var seen = {};
                return current.concat( list ).filter( function ( option ) {
                    if ( seen[ option.value ] ) {
                        return false;
                    }
                    seen[ option.value ] = true;
                    return true;
                } );
            } );
        }

        // The selected product must be among the options to show its name.
        useEffect( function () {
            if ( ! props.value ) {
                return;
            }
            apiFetch( { path: '/wp/v2/product/' + props.value + '?_fields=id,title' } )
                .then( function ( product ) {
                    merge( [ toOption( product ) ] );
                } )
                .catch( function () {} );
        }, [ props.value ] );

        var timer = useRef( null );
        function search( term ) {
            clearTimeout( timer.current );
            if ( ! term || term.length < 2 ) {
                return;
            }
            timer.current = setTimeout( function () {
                apiFetch( { path: '/wp/v2/product?per_page=20&_fields=id,title&search=' + encodeURIComponent( term ) } )
                    .then( function ( products ) {
                        merge( products.map( toOption ) );
                    } )
                    .catch( function () {} );
            }, 300 );
        }

        return el( ComboboxControl, {
            label: __( 'Product', 'intellidesc-for-woocommerce' ),
            help: __( 'Leave empty to show the current product.', 'intellidesc-for-woocommerce' ),
            value: props.value || null,
            options: options,
            onChange: function ( value ) {
                props.onChange( value ? parseInt( value, 10 ) : 0 );
            },
            onFilterValueChange: search,
        } );
    }

    function makeEdit( type ) {
        return function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var controls = [
                el( ProductPicker, {
                    key: 'product',
                    value: attributes.productId,
                    onChange: function ( value ) {
                        setAttributes( { productId: value } );
                    },
                } ),
                el( TextControl, {
                    key: 'title',
                    label: __( 'Title', 'intellidesc-for-woocommerce' ),
                    help: __( 'Optional heading above the block.', 'intellidesc-for-woocommerce' ),
                    value: attributes.title,
                    onChange: function ( value ) {
                        setAttributes( { title: value } );
                    },
                } ),
            ];

            if ( 'specs' === type && isPro ) {
                controls.push( el( SelectControl, {
                    key: 'style',
                    label: __( 'Table style', 'intellidesc-for-woocommerce' ),
                    value: attributes.tableStyle,
                    options: [
                        { value: '', label: __( 'Default', 'intellidesc-for-woocommerce' ) },
                        { value: 'striped', label: __( 'Striped', 'intellidesc-for-woocommerce' ) },
                        { value: 'compact', label: __( 'Compact', 'intellidesc-for-woocommerce' ) },
                    ],
                    onChange: function ( value ) {
                        setAttributes( { tableStyle: value } );
                    },
                } ) );
            }

            return el(
                'div',
                useBlockProps(),
                el( InspectorControls, null, el( PanelBody, { title: __( 'Settings', 'intellidesc-for-woocommerce' ) }, controls ) ),
                el( ServerSideRender, { block: 'ildesc/' + type, attributes: attributes } )
            );
        };
    }

    wp.blocks.registerBlockType( 'ildesc/faq', {
        title: __( 'IntelliDesc FAQ', 'intellidesc-for-woocommerce' ),
        description: __( 'The product FAQ generated by IntelliDesc.', 'intellidesc-for-woocommerce' ),
        category: 'widgets',
        icon: 'editor-help',
        keywords: [ 'faq', 'questions', 'woocommerce' ],
        edit: makeEdit( 'faq' ),
        save: function () {
            return null;
        },
    } );

    wp.blocks.registerBlockType( 'ildesc/specs', {
        title: __( 'IntelliDesc Specs', 'intellidesc-for-woocommerce' ),
        description: __( 'The product features table generated by IntelliDesc.', 'intellidesc-for-woocommerce' ),
        category: 'widgets',
        icon: 'editor-table',
        keywords: [ 'specs', 'features', 'attributes', 'woocommerce' ],
        edit: makeEdit( 'specs' ),
        save: function () {
            return null;
        },
    } );
} )( window.wp, window.ildescBlocks );
