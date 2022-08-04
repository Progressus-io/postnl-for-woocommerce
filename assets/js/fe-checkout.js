( function( $ ) {

	var postnl_fe_checkout = {
		// init Class
		init: function() {
			jQuery('body').on( 'updated_checkout', this.use_select2 );
		},

		use_select2: function(){
			var selectbox = jQuery( '.postnl-checkout-field.select2 select' );
			selectbox.select2( {
				minimumResultsForSearch: -1
			} );
		},
	};

	postnl_fe_checkout.init();

} )( jQuery );
