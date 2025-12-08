( function( $ ) {

	var postnl_settings = {
		// init Class
		init: function() {
            jQuery( '#woocommerce_postnl_environment_mode' ) .on( 'change', this.display_api_key_field );
            this.display_api_key_field();
            jQuery( '#woocommerce_postnl_return_address_or_reply_no' ).on( 'change', this.switch_return_home_address );
            this.switch_return_home_address();
            jQuery( '#woocommerce_postnl_return_shipment_and_labels' ).on( 'change', this.display_return_shipment_and_labels_all );
            this.display_return_shipment_and_labels_all();
			this.display_printer_type_resolution_field();
            this.init_merchant_codes_repeater();
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

		display_printer_type_resolution_field: function () {
            var select = jQuery( '#woocommerce_postnl_printer_type' );
            var parent = this;
            this.checkValue( select )
            select.on('change', function() {
                parent.checkValue( select );
            });
        },

        checkValue: function ( select ) {
            if ( select[0].value == 'PDF' ) {
                jQuery('#woocommerce_postnl_printer_type_resolution').closest('tr').hide();
                jQuery('#woocommerce_postnl_label_format').closest('tr').show();
            } else {
                jQuery('#woocommerce_postnl_printer_type_resolution').closest('tr').show();
                jQuery('#woocommerce_postnl_label_format').closest('tr').hide();
                jQuery('#woocommerce_postnl_label_format').val('A6').trigger('change');
            }
        },
		
        display_return_shipment_and_labels_all: function() {
            var value = jQuery( '#woocommerce_postnl_return_shipment_and_labels' ).val();
            if ( 'shipping_return' === value ) {
                jQuery('#woocommerce_postnl_return_shipment_and_labels_all').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_default').closest('tr').hide();               
                jQuery('#woocommerce_postnl_return_customer_code').closest('tr').hide();               
            } else {
                jQuery('#woocommerce_postnl_return_shipment_and_labels_all').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_default').closest('tr').show();
                jQuery('#woocommerce_postnl_return_customer_code').closest('tr').show();                              
            }

            if( 'none' === value ){
                jQuery('#woocommerce_postnl_return_address_default').closest('tr').hide();               
            }
        },


        switch_return_home_address: function( event = false ) {
            if( jQuery( '#woocommerce_postnl_return_address_or_reply_no' ).is(":checked") ) {
                jQuery('#woocommerce_postnl_return_replynumber').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_street').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_house_no').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_house_noext').closest('tr').show();
                jQuery('#woocommerce_postnl_freepost_zip').closest('tr').hide();
                jQuery('#woocommerce_postnl_freepost_city').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_zip').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_city').closest('tr').show();
            } else {
                jQuery('#woocommerce_postnl_return_replynumber').closest('tr').show();
                jQuery('#woocommerce_postnl_return_address_street').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house_no').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_house_noext').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_zip').closest('tr').hide();
                jQuery('#woocommerce_postnl_return_address_city').closest('tr').hide();
                jQuery('#woocommerce_postnl_freepost_zip').closest('tr').show();
                jQuery('#woocommerce_postnl_freepost_city').closest('tr').show();
            }
        },

        /**
		 * Initialize merchant codes repeater functionality.
		 */
		init_merchant_codes_repeater: function () {
			var self = this;

			// Add new row
			jQuery( '#add-merchant-code-row' ).on( 'click', function ( e ) {
				e.preventDefault();

				var template = jQuery( '#merchant-code-row-template' ).html();
				jQuery( '#merchant-codes-rows' ).append( template );

				// Update disabled countries in all dropdowns after adding new row
				self.updateCountrySelectOptions();
			} );

			// Remove row
			jQuery( document ).on( 'click', '.remove-row', function ( e ) {
				e.preventDefault();
				jQuery( this ).closest( '.merchant-codes-row' ).remove();

				// Update disabled countries after removing a row
				self.updateCountrySelectOptions();
			} );

			// Add initial row if none exist
			if (
				jQuery( '#merchant-codes-rows .merchant-codes-row' ).length ===
				0
			) {
				jQuery( '#add-merchant-code-row' ).trigger( 'click' );
			}

			// Prevent selecting the same country twice
			jQuery( document ).on( 'change', '.country-select', function () {
				self.updateCountrySelectOptions();
			} );

			// Initialize disabled state on page load
			this.updateCountrySelectOptions();
		},

		/**
		 * Update country select options to disable already selected countries.
		 */
		updateCountrySelectOptions: function () {
			var selectedCountries = [];

			// Collect all selected countries
			jQuery( '.country-select' ).each( function () {
				if ( jQuery( this ).val() ) {
					selectedCountries.push( jQuery( this ).val() );
				}
			} );

			// Update each select's options
			jQuery( '.country-select' ).each( function () {
				var currentValue = jQuery( this ).val();
				jQuery( this )
					.find( 'option' )
					.each( function () {
						var optionValue = jQuery( this ).val();
						if (
							optionValue &&
							selectedCountries.indexOf( optionValue ) !== -1 &&
							optionValue !== currentValue
						) {
							jQuery( this ).prop( 'disabled', true );
						} else {
							jQuery( this ).prop( 'disabled', false );
						}
					} );
			} );
		},
	};

	postnl_settings.init();

} )( jQuery );
