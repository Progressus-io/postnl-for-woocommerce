( function( $ ) {

	var postnl_settings = {
		// init Class
		init: function() {
            jQuery( '#woocommerce_postnl_environment_mode' ) .on( 'change', this.display_api_key_field );
            jQuery( '#woocommerce_postnl_return_address_or_reply_no' ).on( 'change', this.switch_return_home_address );
            this.switch_return_home_address();
            this.display_api_key_field();
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

        switch_return_home_address: function( event = false ) {
            if( jQuery( '#woocommerce_postnl_return_address_or_reply_no' ).is(":checked") ) {
                jQuery('#woocommerce_postnl_return_replynumber').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_street').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_house_no').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_house_noext').closest('tr').show();
            } else {
                jQuery('#woocommerce_postnl_return_replynumber').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_street').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house_no').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house_noext').closest('tr').hide();
            }
        }

	};

	postnl_settings.init();

} )( jQuery );
