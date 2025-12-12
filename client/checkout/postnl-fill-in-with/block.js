import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';


export const FillBlock = ( { checkoutExtensionData } ) => {
	const postnlData                    = getSetting( 'postnl-for-woocommerce-blocks_data', {} );
	const [ showButton, setShowButton ] = useState( false );
	const [ isLoading, setIsLoading ]   = useState( false );
	const { setBillingAddress }         = useDispatch( 'wc/store/cart' );
	const { createErrorNotice }         = useDispatch( noticesStore );

	const { CART_STORE_KEY } = window.wc.wcBlocksData;

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect(
		( select ) => {
			const store = select( CART_STORE_KEY );
			return store ? store.getCustomerData() : {};
		},
		[ CART_STORE_KEY ]
	);
	const allowedCountries = [ 'NL', 'BE' ];
	const shippingAddress  = customerData ? customerData.shippingAddress : null;
	const { setShippingAddress } = useDispatch( CART_STORE_KEY );

	useEffect( () => {
		let countryToCheck = shippingAddress?.country || 'NL';
		if (
			allowedCountries.includes( countryToCheck ) &&
			postnlData?.fill_in_with_postnl_settings?.is_fill_in_with_postnl_enabled
		) {

			setShowButton( true );
		} else {
			setShowButton( false );
		}
	}, [ shippingAddress, postnlData ] );

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
					'postnl/house_number': primaryAddress.houseNumber || '',
					postcode: primaryAddress.postalCode || '',
					country: primaryAddress.countryName || 'NL', // Default to NL for PostNL.
				};

				setShippingAddress( addressFields );
				setBillingAddress( addressFields );

			} else {
				createErrorNotice( __( 'Failed to retrieve PostNL user data.', 'postnl-for-woocommerce' ), {
                    id: 'postnl-fetch-error',
					context: 'wc/checkout',
					type: 'default',
					isDismissible: true,
                } );	
			}
		} catch ( err ) {
			createErrorNotice( __( 'Failed to retrieve PostNL address. Please try again.', 'postnl-for-woocommerce'), {
				id: 'postnl-fetch-error',
				context: 'wc/checkout',
				type: 'default',
				isDismissible: true,
			} );
		}
	};

	const handleLoginButtonClick = async ( e ) => {
		e.preventDefault();
		setIsLoading( true );

		try {
			const response = await fetch( postnlData?.fill_in_with_postnl_settings?.rest_url, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': postnlData?.fill_in_with_postnl_settings?.nonce,
					'Content-Type': 'application/json',
				},
			} );
			const result = await response.json();
			if ( result.success && result.data?.redirect_uri ) {
				window.location.href = result.data.redirect_uri;
			} else {
				createErrorNotice(
					__( 'Failed to initiate PostNL login.', 'postnl-for-woocommerce' ),
					{
						id: 'postnl-login-error',
						context: 'wc/checkout',
						type: 'default',
						isDismissible: true,
					}
				);
			}
		} catch ( err ) {
			const message =
				err?.response?.data?.message ||
				__( 'An unknown error occurred.', 'postnl-for-woocommerce' );
			createErrorNotice( message, {
				id: 'postnl-login-error',
				context: 'wc/checkout',
				type: 'default',
				isDismissible: true,
			} );
		}
		setIsLoading( false );
	};

	useEffect( () => {
        const urlParams   = new URLSearchParams( window.location.search );
        const postnlToken = urlParams.get( 'callback' );
        if ( postnlToken ) {
            prefillCheckoutFields();
        }
    }, [] );

    if ( ! showButton ) {
        return null;
    }

	const title       = __( 'Fill in with PostNL', 'postnl-for-woocommerce' );
	const description = __( 'Your name and address are automatically filled in via your PostNL account. That saves you from having to fill in the form!', 'postnl-for-woocommerce' );
	const postnl_logo = postnlData?.fill_in_with_postnl_settings.postnl_logo_url || '';
	return (

		<div className="postnl-login-button__container postnl-button-in-checkout">
			<a
				type="button"
				id="postnl-login-button"
				aria-label={ title }
				href="#"
				className={ isLoading ? 'disabled' : '' }
				onClick={ handleLoginButtonClick }
			>
				<img
					src={ postnl_logo }
					alt={ __( 'PostNL Logo', 'postnl-for-woocommerce' ) }
					id="postnl-logo"
				/>
				<span id="postnl-login-button__text">
					{ title }
				</span>
			</a>
			<p className="postnl-login-button__description">
				{ description }
			</p>
		</div>
	);
};
