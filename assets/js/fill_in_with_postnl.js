( function ( $ ) {

	var fill_in_with_postnl = {
		init: function () {
			this.handle_login_button();
            this.prefill_checkout_fields();
		},

        handle_login_button: function () {
            const button = $( '#postnl-login-button' );
            if ( button.length ) {
                button.on( 'click', function (e) {
                    e.preventDefault();

                    button.prop( 'disabled', true );

                    $.ajax( {
                        url: postnlCheckoutParams.rest_url,
                        method: 'POST',
                        data: {
                            nonce: postnlCheckoutParams.nonce
                        },
                        success: function ( response ) {
                            if ( response.success && response.data.redirect_uri ) {
                                window.location.href = response.data.redirect_uri;
                            } else {
                                alert('Failed to initiate PostNL login.');
                            }
                        },
                        error: function ( xhr, status, error ) {
                            alert( 'Error fetching redirect URI: ' + error );
                        },
                        complete: function () {
                            button.prop( 'disabled', false );
                        }
                    } );
                } );
            }
        },

        prefill_checkout_fields: function () {

			$.ajax( {
                type: 'POST',
                url: postnlCheckoutParams.ajax_url,
                data: {
                    action: 'get_postnl_user_info',
                    nonce: postnlCheckoutParams.ajax_nonce
                },
				success: function( res ) {
					if ( ! res.success || ! res.data ) return;

					const { person, primaryAddress } = res.data;

					if ( person && primaryAddress ) {
						$( '#billing_first_name' ).val( person.givenName ).trigger( 'change' );
						$( '#billing_last_name' ).val( person.familyName ).trigger( 'change' );
						$( '#billing_email' ).val( person.email ).trigger( 'change' );

						$( '#billing_address_1' ).val( primaryAddress.streetName ).trigger( 'change' );
                        $( '#billing_address_2' ).val( primaryAddress.houseNumberAddition ).trigger( 'change' );
                        $( '#billing_house_number' ).val( primaryAddress.houseNumber ).trigger( 'change' );
						$( '#billing_postcode' ).val( primaryAddress.postalCode).trigger( 'change' );
						$( '#billing_city' ).val( primaryAddress.cityName).trigger( 'change' );
						$( '#billing_country' ).val( primaryAddress.countryName ).trigger( 'change' );
                        $( '#shipping_address_1' ).val( primaryAddress.streetName ).trigger( 'change' );
                        $( '#shipping_address_2' ).val( primaryAddress.houseNumberAddition ).trigger( 'change' );
                        $( '#shipping_house_number' ).val( primaryAddress.houseNumber ).trigger( 'change' );
						$( '#shipping_postcode' ).val( primaryAddress.postalCode).trigger( 'change' );
						$( '#shipping_city' ).val( primaryAddress.cityName).trigger( 'change' );
						$( '#shipping_country' ).val( primaryAddress.countryName ).trigger( 'change' );
					}
				},
                error: function ( response ) {
                    alert( response.data.message );
                }
			} );
		}
	};

	fill_in_with_postnl.init();

} )( jQuery );
