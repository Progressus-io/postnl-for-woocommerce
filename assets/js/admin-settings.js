( function( $ ) {

	var postnl_admin_settings = {
		// init Class
		init: function() {
			var mainform = jQuery( '#mainform' );
			
			mainform
				.on( 'change', '#woocommerce_postnl_address_country', this.toggle_street_field );
			
			jQuery( '#woocommerce_postnl_address_country' ).trigger( 'change' );
		},

		toggle_street_field: function( evt ){
			evt.preventDefault();

			var value 		= jQuery( this ).val();
			var mainform 	= jQuery( this ).parents('#mainform');

			mainform.find( '.country-nl, .country-be' ).closest( 'tr' ).hide();
			if( 'NL' == value ){

				mainform.find( '.country-nl' ).closest( 'tr' ).show();

			}else{
				
				mainform.find( '.country-be' ).closest( 'tr' ).show();

			}

		},
	};

	postnl_admin_settings.init();

} )( jQuery );