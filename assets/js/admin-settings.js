( function( $ ) {

	var postnl_admin_settings = {
		// init Class
		init: function() {
			this.display_street_field();
		},

		display_street_field: function(){
			var mainform 	= jQuery('#mainform');
			mainform.find( '.country-nl, .country-be' ).closest( 'tr' ).hide();

			if ( 'NL' == postnl_admin.store_address.country ) {
				mainform.find( '.country-nl' ).closest( 'tr' ).show();
			} else {
				mainform.find( '.country-be' ).closest( 'tr' ).show();
			}

		},
	};

	postnl_admin_settings.init();

} )( jQuery );