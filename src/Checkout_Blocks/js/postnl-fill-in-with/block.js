import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { useDispatch } from '@wordpress/data';

export const FillBlock = ( { checkoutExtensionData } ) => {
    const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );
    const [ showButton, setShowButton ] = useState( false );
    const [ isLoading, setIsLoading ]   = useState(false);
    const { setBillingAddress, setShippingAddress } = useDispatch('wc/store/cart');

	useEffect( () => {
		if ( postnlSettings?.is_enabled_for_checkout ) {
			setShowButton( true );
			prefillCheckoutFields();
		}
	}, [ postnlData ] );

	const handleButtonClick = async ( event ) => {
        event.preventDefault();
        if ( isLoading ) return;

        setIsLoading( true );

        try {
            const response = await fetch( postnlSettings.restUrl, {
                method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': postnlSettings.nonce,
				},
				body: JSON.stringify({}),
            } );

            const data = await response.json();

            if ( data.success && data.data.redirect_uri ) {
                window.location.href = data.data.redirect_uri;
            } else {
                alert( __( 'Unable to connect to PostNL. Please try again later.', 'postnl-for-woocommerce' ) );
            }
        } catch ( error ) {
            alert( __( 'An error occurred. Please try again.', 'postnl-for-woocommerce' ) );
        } finally {
            setIsLoading( false );
        }
    };

	const prefillCheckoutFields = async () => {
		try {
			const response = await fetch( postnlSettings.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'get_postnl_user_info',
					nonce: postnlSettings.ajaxNonce,
				} ),
			} );

			const data = await response.json();
			if ( data.success && data.data ) {
				const { person, primaryAddress } = data.data;
				// Update WooCommerce blocks billing address
				const addressFields = {
                    first_name: person.givenName || '',
                    last_name: person.familyName || '',
                    email: person.email || '',
                    address_1: primaryAddress.streetName || '',
                    address_2: primaryAddress.houseNumberAddition || '',
                    city: primaryAddress.cityName || '',
					house_number: primaryAddress.houseNumber || '',
                    postcode: primaryAddress.postalCode || '',
                    country: primaryAddress.countryName || 'NL', // Default to NL for PostNL.
                };

                setShippingAddress(addressFields);
				setBillingAddress(addressFields);

			} else {
				console.error( 'Failed to retrieve PostNL user data:', data.message );
			}
		} catch ( err ) {
			console.error( 'Error fetching PostNL user data:', err );
			alert( __( 'Failed to retrieve PostNL address. Please try again.', 'postnl-for-woocommerce' ) );
		}
	};

    if ( ! showButton ) {
        return null;
    }

	const title       = __( 'Fill in with PostNL', 'postnl-for-woocommerce' );
	const description = __( 'Your name and address are automatically filled in via your PostNL account. That saves you from having to fill in the form!', 'postnl-for-woocommerce' );
	const postnl_logo = postnlData?.fill_in_with_postnl_settings.postnl_logo_url || '';
	return (
		<div className="button--postnl-container">
			<a
				type="button"
				id="postnl-login-button"
				aria-label={ title }
				href="#"
                onClick={ handleButtonClick }
                className={ isLoading ? 'disabled' : '' }
			>
				<span id="postnl-login-button__text">
					<span id="postnl-login-button__first-text">
						<img
							src={ postnl_logo }
							alt={ __( 'PostNL Logo', 'postnl-for-woocommerce' ) }
							id="postnl-logo"
						/>
					</span>
					<span id="postnl-login-button__second-text">
						{ title }
					</span>
				</span>
			</a>
			<div className="col-12 hidden-md">
				<p className="postnl-fill-in-with__description">
					{ description }
				</p>
			</div>
		</div>
	);
};
