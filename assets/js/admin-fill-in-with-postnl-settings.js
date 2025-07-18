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
} )( jQuery );