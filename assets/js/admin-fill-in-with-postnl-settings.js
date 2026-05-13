( function ( $ ) {
	$( '.postnl-css-editor' ).each( function() {
		// Guard: Prevent initializing again
		if ( $( this ).data( 'codeEditorInitialized' ) ) {
			return;
		}
		wp.codeEditor.initialize( this, {
			codemirror: {
				mode: 'css',
				lineNumbers: true,
				indentUnit: 2,
				viewportMargin: Infinity
			}
		} );
		// Mark as initialized
		$( this ).data( 'codeEditorInitialized', true );
	} );

	$( '.postnl-range-slider' ).each( function () {
		var $number = $( this );
		var min     = $number.attr( 'min' ) || '0';
		var max     = $number.attr( 'max' ) || '100';
		var step    = $number.attr( 'step' ) || '1';
		var unit    = $number.data( 'unit' ) || '';
		var value   = $number.val() || min;

		var $label  = $( '<span class="postnl-slider-value"></span>' ).css( {
			display:       'inline-block',
			minWidth:      '3em',
			marginLeft:    '8px',
			fontWeight:    'bold',
		} ).text( value + unit );

		var $range = $( '<input type="range">' ).attr( {
			min:   min,
			max:   max,
			step:  step,
			value: value,
		} ).css( {
			verticalAlign: 'middle',
			width:         '160px',
			marginLeft:    '10px',
			cursor:        'pointer',
		} );

		$range.on( 'input', function () {
			var v = $( this ).val();
			$number.val( v );
			$label.text( v + unit );
		} );

		$number.on( 'input change', function () {
			var v = $( this ).val();
			$range.val( v );
			$label.text( v + unit );
		} );

		$number.after( $label ).after( $range );
	} );
} )( jQuery );