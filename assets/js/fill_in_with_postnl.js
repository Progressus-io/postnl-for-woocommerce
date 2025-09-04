( function ( $ ) {

	var fill_in_with_postnl = {
		init: function () {
			this.handle_login_button();
            this.prefill_checkout_fields();
            this.bind_country_listeners();
		},

        handle_login_button: function () {
            $( document ).on( 'click', '#postnl-login-button', function ( e ) {
                e.preventDefault();

                const button = $( this );
                button.prop( 'disabled', true );

                $.ajax( {
                    url: postnlCheckoutParams.rest_url,
                    method: 'POST',
                    beforeSend: function ( xhr ) {
                        xhr.setRequestHeader( 'X-WP-Nonce', postnlCheckoutParams.nonce );
                    },
                    success: function ( response ) {
                        if ( response.success && response.data.redirect_uri ) {
                            window.location.href = response.data.redirect_uri;
                        } else {
                            fill_in_with_postnl.show_notice( 'Failed to initiate PostNL login.', 'error' );
                        }
                    },
                    error: function ( response ) {
                        const message = response.responseJSON?.data?.message || 'An unknown error occurred.';
                        fill_in_with_postnl.show_notice( message, 'error' );
                    },
                    complete: function () {
                        button.prop( 'disabled', false );
                    }
                } );
            } );
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
                        $( '#shipping_first_name' ).val( person.givenName ).trigger( 'change' );
						$( '#shipping_last_name' ).val( person.familyName ).trigger( 'change' );

						$( '#billing_address_1' ).val( primaryAddress.streetName ).trigger( 'change' );
                        $( '#billing_address_2' ).val( primaryAddress.houseNumberAddition ).trigger( 'change' );
                        $( '#billing_house_number' ).val( primaryAddress.houseNumber ).trigger( 'change' );
						$( '#billing_postcode' ).val( primaryAddress.postalCode ).trigger( 'change' );
						$( '#billing_city' ).val( primaryAddress.cityName ).trigger( 'change' );
						$( '#billing_country' ).val( primaryAddress.countryName ).trigger( 'change' );

                        $( '#shipping_address_1' ).val( primaryAddress.streetName ).trigger( 'change' );
                        $( '#shipping_address_2' ).val( primaryAddress.houseNumberAddition ).trigger( 'change' );
                        $( '#shipping_house_number' ).val( primaryAddress.houseNumber ).trigger( 'change' );
						$( '#shipping_postcode' ).val( primaryAddress.postalCode ).trigger( 'change' );
						$( '#shipping_city' ).val( primaryAddress.cityName ).trigger( 'change' );
						$( '#shipping_country' ).val( primaryAddress.countryName ).trigger( 'change' );
					}
				},
                error: function ( response ) {
                    const message = response.responseJSON?.data?.message || 'An unknown error occurred.';
                    fill_in_with_postnl.show_notice( message, 'error' );
                }
			} );
		},

        toggle_login_button: function () {
            const allowedCountries = [ 'NL', 'BE' ];
            const billingCountry   = ( $( '#billing_country' ).val() || '' ).toUpperCase();
            let shippingCountry    = '';

            // Only check shipping country if "Ship to a different address" is enabled
            if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
                shippingCountry = ( $( '#shipping_country' ).val() || '' ).toUpperCase();
            }

            const cartShippingCountry =
                ( $( '#calc_shipping_country' ).val() || $( 'select.wc-blocks-components-select__select' ).val() || '' ).toUpperCase();

            // Checkout button toggle
            if ( allowedCountries.includes( billingCountry ) || allowedCountries.includes( shippingCountry ) ) {
                $( '.postnl-button-in-checkout' ).removeClass( 'hidden' );
            } else {
                $( '.postnl-button-in-checkout' ).addClass( 'hidden' );
            }

            // Cart & mini-cart button toggle
            if ( allowedCountries.includes( cartShippingCountry ) ) {
                $( '.postnl-button-in-cart, .postnl-button-in-minicart' ).removeClass( 'hidden' );
            } else {
                $( '.postnl-button-in-cart, .postnl-button-in-minicart' ).addClass( 'hidden' );
            }
        },

        bind_country_listeners: function () {
            // Re-check when checkout updates via AJAX
            $( document.body ).on( 'updated_checkout', function () {
                fill_in_with_postnl.toggle_login_button();
            } );

            // Re-check immediately on dropdown change
            $( document ).on( 'change', '#billing_country, #shipping_country, #calc_shipping_country, select.wc-blocks-components-select__select', function () {
                fill_in_with_postnl.toggle_login_button();
            } );
        },

        show_notice: function ( message, type = 'error' ) {
            const noticeId = `postnl-notice-${type}`;
            let noticesWrapper = $( '.woocommerce-notices-wrapper' ).first();

            // Ensure wrapper exists
            if ( ! noticesWrapper.length ) {
                noticesWrapper = $( '<div class="woocommerce-notices-wrapper"></div>' );
                $( '.woocommerce' ).prepend( noticesWrapper );
            }

            // Remove old notice
            noticesWrapper.find( `#${noticeId}` ).remove();

            // Add new notice
            noticesWrapper.append(
                `<div id="${noticeId}" class="woocommerce-${type}" data-postnl="1">${message}</div>`
            );
        }

	};

	fill_in_with_postnl.init();

} )( jQuery );
