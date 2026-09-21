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
		var desc = $( '#lean_seo_description' ).val() || $( '#lean_seo_description' ).attr( 'placeholder' );
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
