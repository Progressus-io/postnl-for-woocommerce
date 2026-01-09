/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */
import {
	getDropoffPoint,
	setDropoffPoint as saveDropoffPoint,
	clearDropoffPoint,
	clearDeliveryDay,
} from '../../utils/session-manager';

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

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce( ( namespace, key, value ) => {
			setExtensionData( namespace, key, value );
		}, 250 ),
		[ setExtensionData ]
	);

	// Initialize state from centralized session manager
	const [ selection, setSelection ] = useState( () => {
		const saved = getDropoffPoint();
		return {
			dropoffPoints: saved.value || '',
			dropoffPointsAddressCompany: saved.company || '',
			dropoffPointsAddress1: saved.address1 || '',
			dropoffPointsAddress2: saved.address2 || '',
			dropoffPointsCity: saved.city || '',
			dropoffPointsPostcode: saved.postcode || '',
			dropoffPointsCountry: saved.country || '',
			dropoffPointsPartnerID: saved.partnerID || '',
			dropoffPointsDate: saved.date || '',
			dropoffPointsTime: saved.time || '',
			dropoffPointsType: saved.type || '',
			dropoffPointsDistance: saved.distance
				? Number( saved.distance )
				: null,
		};
	} );

	// Sync with extension data when selection changes
	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'dropoffPoints',
			selection.dropoffPoints
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddressCompany',
			selection.dropoffPointsAddressCompany
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddress1',
			selection.dropoffPointsAddress1
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsAddress2',
			selection.dropoffPointsAddress2
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsCity',
			selection.dropoffPointsCity
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsPostcode',
			selection.dropoffPointsPostcode
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsCountry',
			selection.dropoffPointsCountry
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsPartnerID',
			selection.dropoffPointsPartnerID
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsDate',
			selection.dropoffPointsDate
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsTime',
			selection.dropoffPointsTime
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsType',
			selection.dropoffPointsType
		);
		debouncedSetExtensionData(
			'postnl',
			'dropoffPointsDistance',
			selection.dropoffPointsDistance
		);
	}, [ selection, debouncedSetExtensionData ] );

	// Clear all dropoff point selections
	const clearSelections = useCallback(
		( clearSession = false ) => {
			setSelection( {
				dropoffPoints: '',
				dropoffPointsAddressCompany: '',
				dropoffPointsAddress1: '',
				dropoffPointsAddress2: '',
				dropoffPointsCity: '',
				dropoffPointsPostcode: '',
				dropoffPointsCountry: '',
				dropoffPointsPartnerID: '',
				dropoffPointsDate: '',
				dropoffPointsTime: '',
				dropoffPointsType: '',
				dropoffPointsDistance: null,
			} );

			if ( clearSession ) {
				clearDropoffPoint();
			}

			// Clear extension data
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
			setExtensionData( 'postnl', 'dropoffPointsType', '' );
			setExtensionData( 'postnl', 'dropoffPointsDistance', null );
		},
		[ setExtensionData ]
	);

	// Handle tab activation and auto-select first option
	useEffect( () => {
		if ( ! isActive ) {
			clearSelections( true );
			return;
		}

		// If active and no option selected, select the default (first) option
		if (
			isActive &&
			dropoffOptions.length > 0 &&
			! selection.dropoffPoints
		) {
			const first = dropoffOptions[ 0 ];
			const value = `${ first.partner_id }-${ first.loc_code }`;
			handleOptionChange( value );
		}
	}, [ isActive, dropoffOptions ] );

	/**
	 * Handle the change of a dropoff option
	 *
	 * @param {string} value - The value of the selected option
	 */
	const handleOptionChange = async ( value ) => {
		// Find the selected dropoff point.
		const selectedDropoffPoint = dropoffOptions.find( ( point ) => {
			const pointValue = `${ point.partner_id }-${ point.loc_code }`;
			return pointValue === value;
		} );

		if ( ! selectedDropoffPoint ) {
			return;
		}

		const address = selectedDropoffPoint.address || {};

		// Update local state
		const newSelection = {
			dropoffPoints: value,
			dropoffPointsAddressCompany: address.company || '',
			dropoffPointsAddress1: address.address_1 || '',
			dropoffPointsAddress2: address.address_2 || '',
			dropoffPointsCity: address.city || '',
			dropoffPointsPostcode: address.postcode || '',
			dropoffPointsCountry: address.country || '',
			dropoffPointsPartnerID: selectedDropoffPoint.partner_id || '',
			dropoffPointsDate: selectedDropoffPoint.date || '',
			dropoffPointsTime: selectedDropoffPoint.time || '',
			dropoffPointsType: selectedDropoffPoint.type || '',
			dropoffPointsDistance:
				Number( selectedDropoffPoint.distance ) || null,
		};

		setSelection( newSelection );

		// Save to centralized session manager
		saveDropoffPoint( {
			value,
			company: address.company || '',
			address1: address.address_1 || '',
			address2: address.address_2 || '',
			city: address.city || '',
			postcode: address.postcode || '',
			country: address.country || '',
			partnerID: selectedDropoffPoint.partner_id || '',
			date: selectedDropoffPoint.date || '',
			time: selectedDropoffPoint.time || '',
			type: selectedDropoffPoint.type || '',
			distance: selectedDropoffPoint.distance || '',
		} );

		// Update extension data immediately (not debounced for user action)
		setExtensionData( 'postnl', 'dropoffPoints', value );
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
			selectedDropoffPoint.partner_id || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsDate',
			selectedDropoffPoint.date || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsTime',
			selectedDropoffPoint.time || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsType',
			selectedDropoffPoint.type || ''
		);
		setExtensionData(
			'postnl',
			'dropoffPointsDistance',
			Number( selectedDropoffPoint.distance ) || null
		);

		// Clear delivery day data (using centralized session manager)
		clearDeliveryDay();

		// Clear delivery day extension data
		setExtensionData( 'postnl', 'deliveryDay', '' );
		setExtensionData( 'postnl', 'deliveryDayDate', '' );
		setExtensionData( 'postnl', 'deliveryDayFrom', '' );
		setExtensionData( 'postnl', 'deliveryDayTo', '' );
		setExtensionData( 'postnl', 'deliveryDayPrice', '' );
		setExtensionData( 'postnl', 'deliveryDayType', '' );

		// Call extensionCartUpdate to update the cart total
		try {
			const { extensionCartUpdate } = window.wc?.blocksCheckout || {};
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
			// Handle error silently
		}
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
					const isChecked = selection.dropoffPoints === value;
					const activeClass = isChecked ? 'active' : '';

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
									className={ `${ point.type } ${ activeClass }` }
									data-partner_id={ point.partner_id }
									data-loc_code={ point.loc_code }
									data-date={ point.date }
									data-time={ point.time }
									data-type={ point.type }
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
				value={ selection.dropoffPoints }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddressCompany"
				id="dropoffPointsAddressCompany"
				value={ selection.dropoffPointsAddressCompany }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddress1"
				id="dropoffPointsAddress1"
				value={ selection.dropoffPointsAddress1 }
			/>
			<input
				type="hidden"
				name="dropoffPointsAddress2"
				id="dropoffPointsAddress2"
				value={ selection.dropoffPointsAddress2 }
			/>
			<input
				type="hidden"
				name="dropoffPointsCity"
				id="dropoffPointsCity"
				value={ selection.dropoffPointsCity }
			/>
			<input
				type="hidden"
				name="dropoffPointsPostcode"
				id="dropoffPointsPostcode"
				value={ selection.dropoffPointsPostcode }
			/>
			<input
				type="hidden"
				name="dropoffPointsCountry"
				id="dropoffPointsCountry"
				value={ selection.dropoffPointsCountry }
			/>
			<input
				type="hidden"
				name="dropoffPointsPartnerID"
				id="dropoffPointsPartnerID"
				value={ selection.dropoffPointsPartnerID }
			/>
			<input
				type="hidden"
				name="dropoffPointsDate"
				id="dropoffPointsDate"
				value={ selection.dropoffPointsDate }
			/>
			<input
				type="hidden"
				name="dropoffPointsTime"
				id="dropoffPointsTime"
				value={ selection.dropoffPointsTime }
			/>
			<input
				type="hidden"
				name="dropoffPointsType"
				id="dropoffPointsType"
				value={ selection.dropoffPointsType }
			/>
			<input
				type="hidden"
				name="dropoffPointsDistance"
				id="dropoffPointsDistance"
				value={ selection.dropoffPointsDistance ?? '' }
			/>
		</div>
	);
};
