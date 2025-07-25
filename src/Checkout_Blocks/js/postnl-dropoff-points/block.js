/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { debounce } from 'lodash';

/**
 * Utility Functions
 */
const Utils = {
	maybe_convert_km: ( distanceInMeters ) => {
		if ( distanceInMeters >= 1000 ) {
			return `${ ( distanceInMeters / 1000 ).toFixed( 1 ) } km`;
		}
		return `${ distanceInMeters } m`;
	},
};

/**
 * Pickup Block Component
 * @param root0
 * @param root0.checkoutExtensionData
 * @param root0.isActive
 * @param root0.dropoffOptions
 */
export const Block = ( {
	checkoutExtensionData,
	isActive,
	dropoffOptions,
} ) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );

	// Initialize state from sessionStorage if available
	const [ dropoffPoints, setDropoffPoints ] = useState( () => {
		return sessionStorage.getItem( 'postnl_dropoffPoints' ) || '';
	} );
	const [ dropoffPointsAddressCompany, setDropoffPointsAddressCompany ] =
		useState( () => {
			return (
				sessionStorage.getItem(
					'postnl_dropoffPointsAddressCompany'
				) || ''
			);
		} );
	const [ dropoffPointsAddress1, setDropoffPointsAddress1 ] = useState(
		() => {
			return (
				sessionStorage.getItem( 'postnl_dropoffPointsAddress1' ) || ''
			);
		}
	);
	const [ dropoffPointsAddress2, setDropoffPointsAddress2 ] = useState(
		() => {
			return (
				sessionStorage.getItem( 'postnl_dropoffPointsAddress2' ) || ''
			);
		}
	);
	const [ dropoffPointsCity, setDropoffPointsCity ] = useState( () => {
		return sessionStorage.getItem( 'postnl_dropoffPointsCity' ) || '';
	} );
	const [ dropoffPointsPostcode, setDropoffPointsPostcode ] = useState(
		() => {
			return (
				sessionStorage.getItem( 'postnl_dropoffPointsPostcode' ) || ''
			);
		}
	);
	const [ dropoffPointsCountry, setDropoffPointsCountry ] = useState( () => {
		return sessionStorage.getItem( 'postnl_dropoffPointsCountry' ) || '';
	} );
	const [ dropoffPointsPartnerID, setDropoffPointsPartnerID ] = useState(
		() => {
			return (
				sessionStorage.getItem( 'postnl_dropoffPointsPartnerID' ) || ''
			);
		}
	);
	const [ dropoffPointsDate, setDropoffPointsDate ] = useState( () => {
		return sessionStorage.getItem( 'postnl_dropoffPointsDate' ) || '';
	} );
	const [ dropoffPointsTime, setDropoffPointsTime ] = useState( () => {
		return sessionStorage.getItem( 'postnl_dropoffPointsTime' ) || '';
	} );
	const [ dropoffPointsDistance, setDropoffPointsDistance ] = useState(
		() => {
			const value = sessionStorage.getItem(
				'postnl_dropoffPointsDistance'
			);
			return value !== null ? Number( value ) : null;
		}
	);

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce( ( namespace, key, value ) => {
			setExtensionData( namespace, key, value );
		}, 1000 ),
		[ setExtensionData ]
	);

	// Sync states with extension data
	useEffect( () => {
		debouncedSetExtensionData( 'postnl', 'dropoffPoints', dropoffPoints );
	}, [ dropoffPoints, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddressCompany',
			dropoffPointsAddressCompany
		);
	}, [ dropoffPointsAddressCompany, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddress1',
			dropoffPointsAddress1
		);
	}, [ dropoffPointsAddress1, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddress2',
			dropoffPointsAddress2
		);
	}, [ dropoffPointsAddress2, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsCity',
			dropoffPointsCity
		);
	}, [ dropoffPointsCity, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsPostcode',
			dropoffPointsPostcode
		);
	}, [ dropoffPointsPostcode, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsCountry',
			dropoffPointsCountry
		);
	}, [ dropoffPointsCountry, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsPartnerID',
			dropoffPointsPartnerID
		);
	}, [ dropoffPointsPartnerID, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsDate',
			dropoffPointsDate
		);
	}, [ dropoffPointsDate, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsTime',
			dropoffPointsTime
		);
	}, [ dropoffPointsTime, debouncedSetExtensionData ] );

	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsDistance',
			dropoffPointsDistance
		);
	}, [ dropoffPointsDistance, debouncedSetExtensionData ] );

	/**
	 * Effect to handle tab activation
	 */
	useEffect( () => {
		if ( ! isActive ) {
			clearSelections();
		}
	}, [ isActive ] );

	useEffect( () => {
		if (
			isActive &&
			dropoffOptions.length > 0 &&
			! dropoffPoints
		) {
			const first = dropoffOptions[ 0 ];
			const value = `${ first.partner_id }-${ first.loc_code }`;
			handleOptionChange( value );
		}
	}, [ isActive, dropoffOptions, dropoffPoints ] );

	/**
	 * Helper function to clear selections
	 * @param clearSession
	 */
	const clearSelections = ( clearSession = false ) => {
		setDropoffPoints( '' );
		if ( clearSession ) {
			sessionStorage.removeItem( 'postnl_dropoffPoints' );
		}
		setDropoffPointsAddressCompany( '' );
		setDropoffPointsAddress1( '' );
		setDropoffPointsAddress2( '' );
		setDropoffPointsCity( '' );
		setDropoffPointsPostcode( '' );
		setDropoffPointsCountry( '' );
		setDropoffPointsPartnerID( '' );
		setDropoffPointsDate( '' );
		setDropoffPointsTime( '' );
		setDropoffPointsDistance( null );
		if ( clearSession ) {
			sessionStorage.removeItem( 'postnl_dropoffPointsAddressCompany' );
			sessionStorage.removeItem( 'postnl_dropoffPointsAddress1' );
			sessionStorage.removeItem( 'postnl_dropoffPointsAddress2' );
			sessionStorage.removeItem( 'postnl_dropoffPointsCity' );
			sessionStorage.removeItem( 'postnl_dropoffPointsPostcode' );
			sessionStorage.removeItem( 'postnl_dropoffPointsCountry' );
			sessionStorage.removeItem( 'postnl_dropoffPointsPartnerID' );
			sessionStorage.removeItem( 'postnl_dropoffPointsDate' );
			sessionStorage.removeItem( 'postnl_dropoffPointsTime' );
			sessionStorage.removeItem( 'postnl_dropoffPointsDistance' );
		}
		setExtensionData( 'postnl', 'dropoffPoints', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddressCompany', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddress1', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddress2', '' );
		setExtensionData( 'postnl', 'dropoffPointsCity', '' );
		setExtensionData( 'postnl', 'dropoffPointsPostcode', '' );
		setExtensionData( 'postnl', 'dropoffPointsCountry', '' );
		setExtensionData( 'postnl', 'dropoffPointsPartnerID', '' );
		setExtensionData( 'postnl', 'dropoffPointsDate', '' );
		setExtensionData( 'postnl', 'dropoffPointsTime', '' );
		setExtensionData( 'postnl', 'dropoffPointsDistance', null );
	};

	/**
	 * Handle the change of a dropoff option
	 *
	 * @param {string} value - The value of the selected option
	 */
	const handleOptionChange = async ( value ) => {
		setDropoffPoints( value );
		sessionStorage.setItem( 'postnl_dropoffPoints', value );
		setExtensionData( 'postnl', 'dropoffPoints', value );

		// Find the selected dropoff point.
		const selectedDropoffPoint = dropoffOptions.find( ( point ) => {
			const pointValue = `${ point.partner_id }-${ point.loc_code }`;
			return pointValue === value;
		} );

		// Update hidden fields and extension data
		if ( selectedDropoffPoint ) {
			updateHiddenFields( selectedDropoffPoint );
		}

		// Clear delivery day data
		sessionStorage.removeItem( 'postnl_selected_option' );
		sessionStorage.removeItem( 'postnl_deliveryDay' );
		sessionStorage.removeItem( 'postnl_deliveryDayDate' );
		sessionStorage.removeItem( 'postnl_deliveryDayFrom' );
		sessionStorage.removeItem( 'postnl_deliveryDayTo' );
		sessionStorage.removeItem( 'postnl_deliveryDayPrice' );
		sessionStorage.removeItem( 'postnl_deliveryDayType' );

		setExtensionData( 'postnl', 'deliveryDay', '' );
		setExtensionData( 'postnl', 'deliveryDayDate', '' );
		setExtensionData( 'postnl', 'deliveryDayFrom', '' );
		setExtensionData( 'postnl', 'deliveryDayTo', '' );
		setExtensionData( 'postnl', 'deliveryDayPrice', '' );
		setExtensionData( 'postnl', 'deliveryDayType', '' );

		// Call extensionCartUpdate to update the cart total
		try {
			const { extensionCartUpdate } = window.wc.blocksCheckout || {};
			if ( typeof extensionCartUpdate === 'function' ) {
				await extensionCartUpdate( {
					namespace: 'postnl',
					data: {
						action: 'update_delivery_fee',
						price: postnlData.pickup_fee ?? 0,
						type: 'Pickup',
					},
				} );
			}
		} catch ( error ) {
			// Handle error
		} finally {
		}
	};

	/**
	 * Update hidden fields and extension data based on selected dropoff point
	 * @param dropoffPoint
	 */
	const updateHiddenFields = ( dropoffPoint ) => {
		const address = dropoffPoint.address || {};

		setDropoffPointsAddressCompany( address.company || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsAddressCompany',
			address.company || ''
		);

		setDropoffPointsAddress1( address.address_1 || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsAddress1',
			address.address_1 || ''
		);

		setDropoffPointsAddress2( address.address_2 || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsAddress2',
			address.address_2 || ''
		);

		setDropoffPointsCity( address.city || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsCity',
			address.city || ''
		);

		setDropoffPointsPostcode( address.postcode || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsPostcode',
			address.postcode || ''
		);

		setDropoffPointsCountry( address.country || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsCountry',
			address.country || ''
		);

		setDropoffPointsPartnerID( dropoffPoint.partner_id || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsPartnerID',
			dropoffPoint.partner_id || ''
		);

		setDropoffPointsDate( dropoffPoint.date || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsDate',
			dropoffPoint.date || ''
		);

		setDropoffPointsTime( dropoffPoint.time || '' );
		sessionStorage.setItem(
			'postnl_dropoffPointsTime',
			dropoffPoint.time || ''
		);

		setDropoffPointsDistance( Number( dropoffPoint.distance ) || null );
		sessionStorage.setItem(
			'postnl_dropoffPointsDistance',
			Number( dropoffPoint.distance ) || ''
		);

		// Also update extension data
		setExtensionData(
			'postnl',
			'dropoffPointsAddressCompany',
			address.company || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsAddress1',
			address.address_1 || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsAddress2',
			address.address_2 || ''
		);
		setExtensionData( 'postnl', 'dropoffPointsCity', address.city || '' );
		setExtensionData(
			'postnl',
			'dropoffPointsPostcode',
			address.postcode || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsCountry',
			address.country || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsPartnerID',
			dropoffPoint.partner_id || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsDate',
			dropoffPoint.date || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsTime',
			dropoffPoint.time || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsDistance',
			Number( dropoffPoint.distance ) || null
		);
	};

	/**
	 * Render the Pickup
	 */
	return (
		<div className="postnl-dropoff-container">
			{ dropoffOptions.some( ( point ) => point.show_desc ) && (
				<div className="postnl_content_desc">
					{ __(
						'Receive shipment at home? Make a selection from the Delivery Days.',
						'postnl-for-woocommerce'
					) }
				</div>
			) }
			<ul className="postnl_dropoff_points_list postnl_list">
				{ dropoffOptions.map( ( point, index ) => {
					const value = `${ point.partner_id }-${ point.loc_code }`;
					const address = `${ point.address.address_1 } ${ point.address.address_2 }, ${ point.address.city }, ${ point.address.postcode }`;
					const isChecked = dropoffPoints === value;
					const isActive = isChecked ? 'active' : '';

					return (
						<li key={ index }>
							<div className="list_title">
								<span className="company">
									{ point.address.company }
								</span>
								<span className="distance">
									{ Utils.maybe_convert_km( point.distance ) }
								</span>
							</div>
							<ul className="postnl_sub_list">
								<li
									className={ `${ point.type } ${ isActive }` }
									data-partner_id={ point.partner_id }
									data-loc_code={ point.loc_code }
									data-date={ point.date }
									data-time={ point.time }
									data-distance={ point.distance }
									data-address_company={
										point.address.company
									}
									data-address_address_1={
										point.address.address_1
									}
									data-address_address_2={
										point.address.address_2
									}
									data-address_city={ point.address.city }
									data-address_postcode={
										point.address.postcode
									}
									data-address_country={
										point.address.country
									}
								>
									<label
										className="postnl_sub_radio_label"
										htmlFor={ `dropoff_points_${ value }` }
									>
										<input
											type="radio"
											id={ `dropoff_points_${ value }` }
											name="dropoff_points"
											className="postnl_sub_radio"
											value={ value }
											checked={ isChecked }
											onChange={ () =>
												handleOptionChange( value )
											}
										/>
										<i>
											{ __(
												point.type,
												'postnl-for-woocommerce'
											) }
										</i>
										<span>{ address }</span>
									</label>
								</li>
							</ul>
						</li>
					);
				} ) }
			</ul>

			{ /* Hidden Inputs */ }
			<input
				type="hidden"
				name="dropoffPoints"
				id="dropoffPoints"
				value={ dropoffPoints }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddressCompany"
				id="dropoffPointsAddressCompany"
				value={ dropoffPointsAddressCompany }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddress1"
				id="dropoffPointsAddress1"
				value={ dropoffPointsAddress1 }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddress2"
				id="dropoffPointsAddress2"
				value={ dropoffPointsAddress2 }
			/>
			<input
				type="hidden"
				name="dropoffPointsCity"
				id="dropoffPointsCity"
				value={ dropoffPointsCity }
			/>
			<input
				type="hidden"
				name="dropoffPointsPostcode"
				id="dropoffPointsPostcode"
				value={ dropoffPointsPostcode }
			/>
			<input
				type="hidden"
				name="dropoffPointsCountry"
				id="dropoffPointsCountry"
				value={ dropoffPointsCountry }
			/>
			<input
				type="hidden"
				name="dropoffPointsPartnerID"
				id="dropoffPointsPartnerID"
				value={ dropoffPointsPartnerID }
			/>
			<input
				type="hidden"
				name="dropoffPointsDate"
				id="dropoffPointsDate"
				value={ dropoffPointsDate }
			/>
			<input
				type="hidden"
				name="dropoffPointsTime"
				id="dropoffPointsTime"
				value={ dropoffPointsTime }
			/>
			<input
				type="hidden"
				name="dropoffPointsDistance"
				id="dropoffPointsDistance"
				value={ dropoffPointsDistance }
			/>
		</div>
	);
};
