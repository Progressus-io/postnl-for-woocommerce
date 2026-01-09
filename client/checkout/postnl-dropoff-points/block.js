/**
 * External dependencies
 */
import {
	useEffect,
	useState,
	useCallback,
	useRef,
	useMemo,
} from '@wordpress/element';
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
import {
	batchSetExtensionData,
	clearDeliveryDayExtensionData,
	clearDropoffPointExtensionData,
} from '../../utils/extension-data-helper';

/**
 * Empty dropoff state - used for clearing selections.
 */
const EMPTY_DROPOFF_STATE = {
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
};

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
 *
 * @param {Object} props Component props
 * @param {Object} props.checkoutExtensionData Extension data methods
 * @param {boolean} props.isActive Whether the pickup tab is active
 * @param {Array} props.dropoffOptions Available dropoff points
 */
export const Block = ( {
	checkoutExtensionData,
	isActive,
	dropoffOptions,
} ) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );

	// Store setExtensionData in a ref to avoid recreating debounced function
	const setExtensionDataRef = useRef( setExtensionData );
	setExtensionDataRef.current = setExtensionData;

	// Create a stable debounced batch update function
	const debouncedBatchUpdate = useMemo(
		() =>
			debounce( ( data ) => {
				batchSetExtensionData( setExtensionDataRef.current, data );
			}, 250 ),
		[]
	);

	// Cleanup debounce on unmount
	useEffect( () => {
		return () => {
			debouncedBatchUpdate.cancel();
		};
	}, [ debouncedBatchUpdate ] );

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

	// Sync with extension data when selection changes (debounced)
	useEffect( () => {
		debouncedBatchUpdate( {
			dropoffPoints: selection.dropoffPoints,
			dropoffPointsAddressCompany: selection.dropoffPointsAddressCompany,
			dropoffPointsAddress1: selection.dropoffPointsAddress1,
			dropoffPointsAddress2: selection.dropoffPointsAddress2,
			dropoffPointsCity: selection.dropoffPointsCity,
			dropoffPointsPostcode: selection.dropoffPointsPostcode,
			dropoffPointsCountry: selection.dropoffPointsCountry,
			dropoffPointsPartnerID: selection.dropoffPointsPartnerID,
			dropoffPointsDate: selection.dropoffPointsDate,
			dropoffPointsTime: selection.dropoffPointsTime,
			dropoffPointsType: selection.dropoffPointsType,
			dropoffPointsDistance: selection.dropoffPointsDistance,
		} );
	}, [ selection, debouncedBatchUpdate ] );

	// Clear all dropoff point selections
	const clearSelections = useCallback(
		( clearSession = false ) => {
			setSelection( EMPTY_DROPOFF_STATE );

			if ( clearSession ) {
				clearDropoffPoint();
			}

			// Clear extension data using helper
			clearDropoffPointExtensionData( setExtensionData );
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
		// Find the selected dropoff point
		const selectedDropoffPoint = dropoffOptions.find(
			( point ) => `${ point.partner_id }-${ point.loc_code }` === value
		);

		if ( ! selectedDropoffPoint ) {
			return;
		}

		const address = selectedDropoffPoint.address || {};
		const distance = Number( selectedDropoffPoint.distance ) || null;

		// Build selection data once - reused for state and extension data
		const selectionData = {
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
			dropoffPointsDistance: distance,
		};

		// Update local state and extension data (reusing same object)
		setSelection( selectionData );
		batchSetExtensionData( setExtensionData, selectionData );

		// Save to session manager (different key format)
		saveDropoffPoint( {
			value,
			company: selectionData.dropoffPointsAddressCompany,
			address1: selectionData.dropoffPointsAddress1,
			address2: selectionData.dropoffPointsAddress2,
			city: selectionData.dropoffPointsCity,
			postcode: selectionData.dropoffPointsPostcode,
			country: selectionData.dropoffPointsCountry,
			partnerID: selectionData.dropoffPointsPartnerID,
			date: selectionData.dropoffPointsDate,
			time: selectionData.dropoffPointsTime,
			type: selectionData.dropoffPointsType,
			distance: selectedDropoffPoint.distance || '',
		} );

		// Clear delivery day data
		clearDeliveryDay();
		clearDeliveryDayExtensionData( setExtensionData );

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
		</div>
	);
};
