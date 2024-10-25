/**
 * External dependencies
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';

// Import the child components
import { Block as DeliveryDayBlock } from '../postnl-delivery-day/block';
import { Block as DropoffPointsBlock } from '../postnl-dropoff-points/block';

export const Block = ( { checkoutExtensionData } ) => {
	// Define the tabs
	const tabs = [
		{ id: 'delivery_day', name: __( 'Delivery Day', 'postnl-for-woocommerce' ) },
		{ id: 'dropoff_points', name: __( 'Dropoff Points', 'postnl-for-woocommerce' ) },
	];

	// State for the active tab
	const [ activeTab, setActiveTab ] = useState( tabs[ 0 ].id );

	// For now, we'll assume `letterbox` is false
	const letterbox = false;

	return (
		<div id="postnl_checkout_option" className={ `postnl_checkout_container ${ letterbox ? 'is-hidden' : '' }` }>
			<div className="postnl_checkout_tab_container">
				<ul className="postnl_checkout_tab_list">
					{ tabs.map( ( tab ) => (
						<li key={ tab.id } className={ activeTab === tab.id ? 'active' : '' }>
							<label htmlFor={ `postnl_option_${ tab.id }` } className="postnl_checkout_tab">
								<span>{ tab.name }</span>
								<input
									type="radio"
									name="postnl_option"
									id={ `postnl_option_${ tab.id }` }
									className="postnl_option"
									value={ tab.id }
									checked={ activeTab === tab.id }
									onChange={ () => setActiveTab( tab.id ) }
								/>
							</label>
						</li>
					) ) }
				</ul>
			</div>
			<div className="postnl_checkout_content_container">
				<div
					className={ `postnl_content ${ activeTab === 'delivery_day' ? 'active' : '' }` }
					id="postnl_delivery_day_content"
				>
					{/* Render Delivery Day Block */}
					<DeliveryDayBlock checkoutExtensionData={ checkoutExtensionData } />
				</div>
				<div
					className={ `postnl_content ${ activeTab === 'dropoff_points' ? 'active' : '' }` }
					id="postnl_dropoff_points_content"
				>
					{/* Render Dropoff Points Block */}
					<DropoffPointsBlock checkoutExtensionData={ checkoutExtensionData } />
				</div>
			</div>
		</div>
	);
};
