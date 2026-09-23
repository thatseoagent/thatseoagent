/**
 * ThatSeoAgent — the Breadcrumbs block in the editor.
 *
 * Rendered on the server (ThatSeoAgent_Breadcrumbs::render_block()). The
 * editor cannot know which page the block will sit on — a template shows
 * on many — so it shows the shape of a trail, not a real one. Plain script,
 * no build step.
 */
( function ( blocks, element, i18n, blockEditor, components ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'thatseoagent/breadcrumbs', {
		apiVersion: 3,
		title: __( 'Breadcrumbs', 'thatseoagent' ),
		category: 'theme',
		icon: 'arrow-right-alt2',
		attributes: {
			separator: { type: 'string', default: '›' },
		},

		edit: function ( props ) {
			var separator = props.attributes.separator;
			var gap = separator ? ' ' + separator + ' ' : ' ';

			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Breadcrumbs', 'thatseoagent' ) },
						el( components.TextControl, {
							label: __( 'Separator', 'thatseoagent' ),
							value: separator,
							onChange: function ( value ) {
								props.setAttributes( { separator: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'nav',
					{ 'aria-label': __( 'Breadcrumb', 'thatseoagent' ) },
					el( 'span', null, __( 'Home', 'thatseoagent' ) ),
					gap,
					el( 'span', null, __( 'Section', 'thatseoagent' ) ),
					gap,
					el( 'span', { 'aria-current': 'page' }, __( 'Current page', 'thatseoagent' ) )
				)
			);
		},

		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components ) );
