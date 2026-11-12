( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var blockEditor = wp.blockEditor;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var InnerBlocks = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var InspectorAdvancedControls = blockEditor.InspectorAdvancedControls;
	var components = wp.components;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;

	var CONTAINER_HELP = {
		'container': __( 'Full fluid width with responsive theme gutters (clamp 20px–64px).', 'xmastree-blocks' ),
		'container-fluid': __( 'Edge-to-edge Bootstrap fluid container with standard 12px horizontal gutters.', 'xmastree-blocks' ),
		'stand-container': __( 'Stand-panel container with responsive gutters tightening at 1200px+.', 'xmastree-blocks' ),
		'custom': __( 'Centered container constrained to a custom pixel max-width.', 'xmastree-blocks' ),
	};

	function clampMaxWidth( value ) {
		var num = parseInt( value, 10 );
		if ( isNaN( num ) || num <= 0 ) {
			return 1200;
		}
		return Math.max( 200, Math.min( 2400, num ) );
	}

	function getContainerConfig( containerType, customMaxWidth ) {
		var safeWidth;
		switch ( containerType ) {
			case 'container-fluid':
				return {
					innerClassName: 'container-fluid xmastree-container-inner',
					innerStyle: undefined,
					label: '.container-fluid',
				};
			case 'stand-container':
				return {
					innerClassName: 'stand-container xmastree-container-inner',
					innerStyle: undefined,
					label: '.stand-container',
				};
			case 'custom':
				safeWidth = clampMaxWidth( customMaxWidth );
				return {
					innerClassName: 'xmastree-custom-container xmastree-container-inner',
					innerStyle: { maxWidth: safeWidth + 'px' },
					label: 'Custom (' + safeWidth + 'px)',
				};
			case 'container':
			default:
				return {
					innerClassName: 'container xmastree-container-inner',
					innerStyle: undefined,
					label: '.container',
				};
		}
	}

	registerBlockType( 'xmastree/container', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var clientId = props.clientId;

			var containerType = attributes.containerType || 'container';
			var customMaxWidth = typeof attributes.customMaxWidth === 'number' ? attributes.customMaxWidth : 1200;
			var tagName = attributes.tagName === 'section' ? 'section' : 'div';

			var hasInnerBlocks = useSelect(
				function ( select ) {
					var store = select( blockEditor.store || 'core/block-editor' );
					if ( ! store || ! store.getBlockOrder ) {
						return false;
					}
					return store.getBlockOrder( clientId ).length > 0;
				},
				[ clientId ]
			);

			var config = getContainerConfig( containerType, customMaxWidth );

			var blockProps = useBlockProps( {
				className: hasInnerBlocks ? undefined : 'xmastree-container-is-empty',
				'data-container-type': containerType,
			} );

			var appenderComponent = hasInnerBlocks
				? undefined
				: ( InnerBlocks && InnerBlocks.ButtonBlockAppender ) || blockEditor.ButtonBlockAppender;

			var innerBlocksProps = useInnerBlocksProps(
				{
					className: config.innerClassName,
					style: config.innerStyle,
					'data-container-label': config.label,
				},
				{
					renderAppender: appenderComponent,
				}
			);

			var WrapperTag = tagName;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Container Settings', 'xmastree-blocks' ),
							initialOpen: true,
						},
						el( SelectControl, {
							label: __( 'Container Type', 'xmastree-blocks' ),
							value: containerType,
							options: [
								{
									label: __( 'Theme Default (.container)', 'xmastree-blocks' ),
									value: 'container',
								},
								{
									label: __( 'Full-Width Fluid (.container-fluid)', 'xmastree-blocks' ),
									value: 'container-fluid',
								},
								{
									label: __( 'Stand Container (.stand-container)', 'xmastree-blocks' ),
									value: 'stand-container',
								},
								{
									label: __( 'Custom Max-Width', 'xmastree-blocks' ),
									value: 'custom',
								},
							],
							help: CONTAINER_HELP[ containerType ] || CONTAINER_HELP['container'],
							onChange: function ( nextType ) {
								setAttributes( { containerType: nextType } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						containerType === 'custom' && el( RangeControl, {
							label: __( 'Custom Max-Width (px)', 'xmastree-blocks' ),
							value: customMaxWidth,
							min: 200,
							max: 2400,
							step: 10,
							help: __( 'Centers content horizontally with responsive theme gutters inside this max-width.', 'xmastree-blocks' ),
							onChange: function ( nextVal ) {
								if ( typeof nextVal === 'number' && ! isNaN( nextVal ) ) {
									setAttributes( { customMaxWidth: nextVal } );
								} else if ( nextVal === undefined || nextVal === '' ) {
									setAttributes( { customMaxWidth: 1200 } );
								}
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					InspectorAdvancedControls,
					null,
					el( SelectControl, {
						label: __( 'HTML Element', 'xmastree-blocks' ),
						value: tagName,
						options: [
							{ label: __( '<div> (Default)', 'xmastree-blocks' ), value: 'div' },
							{ label: __( '<section>', 'xmastree-blocks' ), value: 'section' },
						],
						help: __( 'Choose <section> when using this container as a top-level page section.', 'xmastree-blocks' ),
						onChange: function ( nextTag ) {
							setAttributes( { tagName: nextTag === 'section' ? 'section' : 'div' } );
						},
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					} )
				),
				el(
					WrapperTag,
					blockProps,
					el( 'div', innerBlocksProps )
				)
			);
		},

		save: function () {
			return el( InnerBlocks.Content, null );
		},
	} );
}( window.wp ) );
