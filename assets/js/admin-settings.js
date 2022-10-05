( function( $ ) {

	var postnl_settings = {
		// init Class
		init: function() {
			var environment_mode = jQuery( '#woocommerce_postnl_environment_mode' );


			this.display_api_key_field();
			environment_mode
				.on( 'change', this.display_api_key_field );
		},

		display_api_key_field: function() {
			var value = jQuery( '#woocommerce_postnl_environment_mode' ).val();

			if ( 'production' === value ) {
				jQuery('#woocommerce_postnl_api_keys').closest('tr').show();
				jQuery('#woocommerce_postnl_api_keys_sandbox').closest('tr').hide();
			} else {
				jQuery('#woocommerce_postnl_api_keys').closest('tr').hide();
				jQuery('#woocommerce_postnl_api_keys_sandbox').closest('tr').show();
			}

		},
	};

	postnl_settings.init();

} )( jQuery );
