/* TS Promotions: offer edit screen. Shows fields only when they apply, filters page lists, picks the pop-up picture. */
( function ( $ ) {
	'use strict';

	var $form = $( '#post' );

	// data-tsp-show="field=value" (or "field!=value"): visible only while the field named tsp[field] has (or has not) that value.
	function value( field ) {
		var $inputs = $form.find( '[name="tsp[' + field + ']"]' );
		if ( $inputs.is( ':checkbox' ) ) {
			return $inputs.is( ':checked' ) ? '1' : '';
		}
		if ( $inputs.is( ':radio' ) ) {
			return $inputs.filter( ':checked' ).val() || '';
		}
		return $inputs.val() || '';
	}

	function refresh() {
		$form.find( '[data-tsp-show]' ).each( function () {
			var rule = this.getAttribute( 'data-tsp-show' );
			var not = rule.indexOf( '!=' ) !== -1;
			var parts = rule.split( not ? '!=' : '=' );
			var same = value( parts[ 0 ] ) === parts[ 1 ];
			this.hidden = not ? same : ! same;
		} );
	}

	$form.on( 'change', ':input[name^="tsp["]', refresh );
	refresh();

	// Page lists: type to filter.
	$form.on( 'input', '.tsp-pages__filter', function () {
		var q = this.value.toLowerCase().trim();
		$( this ).siblings( '.tsp-pages__list' ).children( 'li' ).not( '.tsp-pages__trips' ).each( function () {
			this.hidden = q !== '' && this.textContent.toLowerCase().indexOf( q ) === -1;
		} );
	} );

	// Pop-up picture from the Media Library.
	var frame;
	$form.on( 'click', '.tsp-image__pick', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( { library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var img = frame.state().get( 'selection' ).first().toJSON();
				var src = img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url;
				$( '#tsp-image_id' ).val( img.id );
				$( '.tsp-image__preview' ).empty().append( $( '<img>' ).attr( { src: src, alt: '' } ) );
				$( '.tsp-image__remove' ).prop( 'hidden', false );
			} );
		}
		frame.open();
	} );
	$form.on( 'click', '.tsp-image__remove', function ( e ) {
		e.preventDefault();
		$( '#tsp-image_id' ).val( '0' );
		$( '.tsp-image__preview' ).empty();
		this.hidden = true;
	} );
}( jQuery ) );
