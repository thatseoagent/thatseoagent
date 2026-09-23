/**
 * ThatSeoAgent — character counters and search preview for the SEO meta box.
 */

jQuery( function ( $ ) {
	// The limit is where the text may be cut in search results, not a
	// maximum: Google trims by screen width and sets none.
	function updateCounter( input, counter ) {
		var max = parseInt( $( counter ).attr( 'data-limit' ), 10 );
		var len = $( input ).val().length;
		$( counter ).text( len + '/' + max );
		$( counter ).toggleClass( 'warning', len > max );
	}

	function updatePreview() {
		// An empty field falls back to what the front end would emit with
		// it empty — the post's name and the site's, the generated
		// description — not to the field's placeholder: the preview is meant
		// to show what lands in search results.
		var $title = $( '#thatseoagent_title' );
		var title = $title.val() || $title.attr( 'data-thatseoagent-generated' ) || $title.attr( 'placeholder' );
		var $desc = $( '#thatseoagent_description' );
		var desc = $desc.val() || $desc.attr( 'data-thatseoagent-generated' ) || $desc.attr( 'placeholder' );
		$( '#preview-title' ).text( title );
		$( '#preview-desc' ).text( desc );
	}

	$( '#thatseoagent_title' ).on( 'input', function () {
		updateCounter( this, '#title-counter' );
		updatePreview();
	} ).trigger( 'input' );

	$( '#thatseoagent_description' ).on( 'input', function () {
		updateCounter( this, '#desc-counter' );
		updatePreview();
	} ).trigger( 'input' );
} );
