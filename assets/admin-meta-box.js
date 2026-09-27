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

/**
 * The social sharing image picker.
 */
jQuery( function ( $ ) {
	var $field = $( '.thatseoagent-share-image' );
	var frame;

	$field.on( 'click', '.thatseoagent-share-image-select', function ( e ) {
		e.preventDefault();

		if ( ! frame ) {
			frame = wp.media( {
				title: $( this ).attr( 'data-title' ),
				button: { text: $( this ).attr( 'data-button' ) },
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

				$( '#thatseoagent_share_image' ).val( attachment.id );
				$field.find( '.thatseoagent-share-image-preview' ).empty().append( $( '<img alt="">' ).attr( 'src', src ) );
				$field.find( '.thatseoagent-share-image-remove' ).prop( 'hidden', false );
			} );
		}

		frame.open();
	} );

	$field.on( 'click', '.thatseoagent-share-image-remove', function ( e ) {
		e.preventDefault();

		$( '#thatseoagent_share_image' ).val( '' );
		$field.find( '.thatseoagent-share-image-preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );
} );
