( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Button = wp.components.Button;
	var Flex = wp.components.Flex;
	var FlexBlock = wp.components.FlexBlock;
	var ServerSideRender = wp.serverSideRender;

	var MAX_ADDONS = 6;

	function AddonRow( props ) {
		var addon = props.addon;

		return el(
			'div',
			{ className: 'rs-addon-row', style: { borderBottom: '1px solid #ddd', marginBottom: '12px' } },
			el( TextControl, {
				label: __( 'Label', 'rs-services' ),
				value: addon.label || '',
				onChange: function ( v ) { props.onChange( { label: v } ); },
			} ),
			el(
				Flex,
				{ align: 'flex-end' },
				el( FlexBlock, null, el( TextControl, {
					label: __( 'Price', 'rs-services' ),
					type: 'number',
					min: 0,
					step: 0.01,
					value: addon.price,
					onChange: function ( v ) { props.onChange( { price: Math.max( 0, parseFloat( v ) || 0 ) } ); },
				} ) ),
				el( FlexBlock, null, el( TextControl, {
					label: __( 'Days (+/-)', 'rs-services' ),
					type: 'number',
					step: 1,
					value: addon.days || 0,
					onChange: function ( v ) { props.onChange( { days: parseInt( v, 10 ) || 0 } ); },
				} ) )
			),
			el( Button, {
				variant: 'link',
				isDestructive: true,
				onClick: props.onRemove,
				style: { marginBottom: '12px' },
			}, __( 'Remove add-on', 'rs-services' ) )
		);
	}

	wp.blocks.registerBlockType( 'rs-services/service-calculator', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var addons = attributes.addons || [];

			function updateAddon( index, patch ) {
				var next = addons.slice();
				next[ index ] = Object.assign( {}, next[ index ], patch );
				setAttributes( { addons: next } );
			}

			function removeAddon( index ) {
				setAttributes( { addons: addons.filter( function ( _a, i ) { return i !== index; } ) } );
			}

			function addAddon() {
				setAttributes( { addons: addons.concat( [ { label: __( 'New add-on', 'rs-services' ), price: 10, days: 0 } ] ) } );
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'rs-services' ) },
						el( TextControl, {
							label: __( 'Heading', 'rs-services' ),
							value: attributes.heading,
							onChange: function ( v ) { setAttributes( { heading: v } ); },
						} ),
						el( TextControl, {
							label: __( 'Currency symbol', 'rs-services' ),
							value: attributes.currencySymbol,
							maxLength: 4,
							onChange: function ( v ) { setAttributes( { currencySymbol: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show excerpt', 'rs-services' ),
							checked: !! attributes.showExcerpt,
							onChange: function ( v ) { setAttributes( { showExcerpt: v } ); },
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Add-ons', 'rs-services' ), initialOpen: false },
						addons.map( function ( addon, index ) {
							return el( AddonRow, {
								key: index,
								addon: addon,
								onChange: function ( patch ) { updateAddon( index, patch ); },
								onRemove: function () { removeAddon( index ); },
							} );
						} ),
						addons.length < MAX_ADDONS && el( Button, { variant: 'secondary', onClick: addAddon }, __( 'Add add-on', 'rs-services' ) )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( ServerSideRender, {
						block: 'rs-services/service-calculator',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
