/**
 * Lean SEO — character counters and search preview for the SEO meta box.
 */

jQuery( function ( $ ) {
	function updateCounter( input, counter, max ) {
		var len = $( input ).val().length;
		$( counter ).text( len + '/' + max );
		$( counter ).toggleClass( 'warning', len > max );
	}

	function updatePreview() {
		var title = $( '#lean_seo_title' ).val() || $( '#lean_seo_title' ).attr( 'placeholder' );
		// Fall back to the description the front end would emit, not to the
		// field's instruction text — the preview is meant to show what lands
		// in search results.
		var $desc = $( '#lean_seo_description' );
		var desc = $desc.val() || $desc.attr( 'data-lean-seo-generated' ) || $desc.attr( 'placeholder' );
		$( '#preview-title' ).text( title );
		$( '#preview-desc' ).text( desc );
	}

	$( '#lean_seo_title' ).on( 'input', function () {
		updateCounter( this, '#title-counter', 60 );
		updatePreview();
	} ).trigger( 'input' );

	$( '#lean_seo_description' ).on( 'input', function () {
		updateCounter( this, '#desc-counter', 160 );
		updatePreview();
	} ).trigger( 'input' );
} );
