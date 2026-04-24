( function ( $ ) {
	if ( typeof window.postnlNewApiKeyBanner === 'undefined' ) {
		return;
	}

	var config = window.postnlNewApiKeyBanner;

	function send( $banner, mode ) {
		$.post(
			config.ajaxUrl,
			{
				action: config.action,
				nonce: $banner.data( 'nonce' ),
				mode: mode
			}
		).always( function () {
			$banner.fadeOut( 200, function () {
				$( this ).remove();
			} );
		} );
	}

	$( document ).on( 'click', '.postnl-new-api-key-remind', function ( e ) {
		e.preventDefault();
		send( $( this ).closest( '.postnl-new-api-key-banner' ), 'remind' );
	} );

	$( document ).on( 'click', '.postnl-new-api-key-dismiss', function ( e ) {
		e.preventDefault();
		send( $( this ).closest( '.postnl-new-api-key-banner' ), 'dismiss' );
	} );
} )( jQuery );
