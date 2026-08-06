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

	/**
	 * Read the visible text of the label bound to a field, minus its help tip.
	 *
	 * @param {jQuery} $field Field whose label should be read.
	 * @return {string} Trimmed label text, or an empty string when the field has no label.
	 */
	function postnlFieldLabel( $field ) {
		var fieldId = $field.attr( 'id' );

		if ( ! fieldId ) {
			return '';
		}

		return $( 'label[for="' + fieldId + '"]' ).clone().children().remove().end().text().trim();
	}

	/**
	 * Repaint the button preview from the current values of the styling fields.
	 *
	 * @return {void}
	 */
	function postnlUpdatePreview() {
		var $preview = $( '#postnl-button-preview' );
		if ( ! $preview.length ) {
			return;
		}

		var bgColor      = $( '#postnl_button_background_color' ).val() || '#ff6200';
		var border       = $( '#postnl_button_border' ).val() || 'none';
		var borderRadius = $( '#postnl_button_border_radius' ).val() || '4';

		$preview.css( {
			'background-color': bgColor,
			'border':           border,
			'border-radius':    borderRadius + 'px',
		} );
	}

	// Namespaced so that re-initialising rebinds these handlers instead of stacking them.
	$( '#postnl_button_border, #postnl_button_border_radius' )
		.off( '.postnlPreview' )
		.on( 'input.postnlPreview change.postnlPreview', postnlUpdatePreview );

	$( '#postnl_button_background_color' )
		.off( '.postnlPreview' )
		.on( 'change.postnlPreview', postnlUpdatePreview );

	$( '#postnl-button-preview' )
		.off( '.postnlPreview' )
		.on( 'mouseenter.postnlPreview', function () {
			$( this ).css( 'background-color', $( '#postnl_button_hover_background_color' ).val() || '#e55500' );
		} )
		.on( 'mouseleave.postnlPreview', function () {
			$( this ).css( 'background-color', $( '#postnl_button_background_color' ).val() || '#ff6200' );
		} );

	// Reflect the saved values in the preview on initial load.
	postnlUpdatePreview();

	$( '.postnl-range-slider' ).each( function () {
		var $number = $( this );

		// Guard: prevent appending a second slider on re-initialisation.
		if ( $number.data( 'postnlSliderInitialized' ) ) {
			return;
		}

		var min       = $number.attr( 'min' ) || '0';
		var max       = $number.attr( 'max' ) || '100';
		var step      = $number.attr( 'step' ) || '1';
		var unit      = $number.data( 'unit' ) || '';
		var value     = $number.val() || min;
		var labelText = postnlFieldLabel( $number );

		// Hidden from assistive tech: the number input it mirrors already announces the value.
		var $label  = $( '<span class="postnl-slider-value" aria-hidden="true"></span>' ).css( {
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

		if ( labelText ) {
			$range.attr( 'aria-label', labelText );
		}

		$range.on( 'input', function () {
			var v = $( this ).val();
			$number.val( v ).trigger( 'change' );
			$label.text( v + unit );
		} );

		$number.on( 'input change', function () {
			var v = $( this ).val();
			$range.val( v );
			$label.text( v + unit );
		} );

		$number.after( $label ).after( $range );
		$number.data( 'postnlSliderInitialized', true );
	} );
} )( jQuery );
