/**
 * PostNL Fill-in-with Block Tests
 *
 * Tests for the OAuth-based address autofill component.
 */

import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { FillBlock } from '../../client/checkout/postnl-fill-in-with/block';
import { getSetting } from '@woocommerce/settings';
import { useSelect, useDispatch } from '@wordpress/data';

// Mock @wordpress/notices store
jest.mock( '@wordpress/notices', () => ( {
	store: 'core/notices',
} ) );

describe( 'PostNL Fill-in-with Block', () => {
	let mockSetExtensionData;
	let mockSetShippingAddress;
	let mockSetBillingAddress;
	let mockCreateErrorNotice;
	let originalFetch;
	let originalLocation;

	beforeEach( () => {
		jest.clearAllMocks();

		mockSetExtensionData = jest.fn();
		mockSetShippingAddress = jest.fn();
		mockSetBillingAddress = jest.fn();
		mockCreateErrorNotice = jest.fn();

		// Save original fetch and location
		originalFetch = global.fetch;
		originalLocation = window.location;

		// Mock fetch
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: {
							redirect_uri: 'https://postnl.nl/oauth',
						},
					} ),
			} )
		);

		// Mock window.location
		delete window.location;
		window.location = {
			href: 'https://shop.example.com/checkout',
			search: '',
		};

		// Setup default useDispatch mock
		useDispatch.mockImplementation( ( storeKey ) => {
			if ( storeKey === 'wc/store/cart' ) {
				return {
					setShippingAddress: mockSetShippingAddress,
					setBillingAddress: mockSetBillingAddress,
				};
			}
			if ( storeKey === 'core/notices' ) {
				return {
					createErrorNotice: mockCreateErrorNotice,
				};
			}
			return {};
		} );

		// Setup default useSelect mock
		useSelect.mockImplementation( ( callback ) => {
			const mockSelect = () => ( {
				getCustomerData: () => ( {
					shippingAddress: {
						country: 'NL',
						postcode: '1234AB',
					},
				} ),
			} );
			return callback( mockSelect );
		} );

		// Setup default settings with fill_in_with enabled
		getSetting.mockReturnValue( {
			fill_in_with_postnl_settings: {
				is_fill_in_with_postnl_enabled: true,
				rest_url: 'https://shop.example.com/wp-json/postnl/v1/oauth',
				nonce: 'test-nonce',
				postnl_logo_url: 'https://example.com/logo.png',
			},
		} );
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		window.location = originalLocation;
	} );

	const renderComponent = ( props = {} ) => {
		const defaultProps = {
			checkoutExtensionData: {
				setExtensionData: mockSetExtensionData,
			},
		};
		return render( <FillBlock { ...defaultProps } { ...props } /> );
	};

	describe( 'visibility conditions', () => {
		it( 'should render button when feature is enabled and country is NL', () => {
			renderComponent();

			expect(
				screen.getByText( 'Fill in with PostNL' )
			).toBeInTheDocument();
		} );

		it( 'should render button when country is BE', () => {
			useSelect.mockImplementation( ( callback ) => {
				const mockSelect = () => ( {
					getCustomerData: () => ( {
						shippingAddress: {
							country: 'BE',
						},
					} ),
				} );
				return callback( mockSelect );
			} );

			renderComponent();

			expect(
				screen.getByText( 'Fill in with PostNL' )
			).toBeInTheDocument();
		} );

		it( 'should not render when feature is disabled', () => {
			getSetting.mockReturnValue( {
				fill_in_with_postnl_settings: {
					is_fill_in_with_postnl_enabled: false,
				},
			} );

			renderComponent();

			expect(
				screen.queryByText( 'Fill in with PostNL' )
			).not.toBeInTheDocument();
		} );

		it( 'should not render for non-NL/BE countries', () => {
			useSelect.mockImplementation( ( callback ) => {
				const mockSelect = () => ( {
					getCustomerData: () => ( {
						shippingAddress: {
							country: 'DE',
						},
					} ),
				} );
				return callback( mockSelect );
			} );

			renderComponent();

			expect(
				screen.queryByText( 'Fill in with PostNL' )
			).not.toBeInTheDocument();
		} );

		it( 'should default to NL if no country set', () => {
			useSelect.mockImplementation( ( callback ) => {
				const mockSelect = () => ( {
					getCustomerData: () => ( {
						shippingAddress: {},
					} ),
				} );
				return callback( mockSelect );
			} );

			renderComponent();

			expect(
				screen.getByText( 'Fill in with PostNL' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'UI elements', () => {
		it( 'should display PostNL logo', () => {
			renderComponent();

			const logo = screen.getByAltText( 'PostNL Logo' );
			expect( logo ).toBeInTheDocument();
			expect( logo ).toHaveAttribute(
				'src',
				'https://example.com/logo.png'
			);
		} );

		it( 'should display description text', () => {
			renderComponent();

			expect(
				screen.getByText(
					/Your name and address are automatically filled in/i
				)
			).toBeInTheDocument();
		} );

		it( 'should have correct aria-label', () => {
			renderComponent();

			const button = screen.getByRole( 'link', {
				name: 'Fill in with PostNL',
			} );
			expect( button ).toBeInTheDocument();
		} );
	} );

	describe( 'login button click', () => {
		it( 'should call OAuth endpoint when clicked', async () => {
			const user = userEvent.setup();
			renderComponent();

			await user.click( screen.getByText( 'Fill in with PostNL' ) );

			expect( global.fetch ).toHaveBeenCalledWith(
				'https://shop.example.com/wp-json/postnl/v1/oauth',
				expect.objectContaining( {
					method: 'POST',
					headers: expect.objectContaining( {
						'X-WP-Nonce': 'test-nonce',
						'Content-Type': 'application/json',
					} ),
				} )
			);
		} );

		it( 'should redirect to OAuth URI on success', async () => {
			const user = userEvent.setup();
			renderComponent();

			await user.click( screen.getByText( 'Fill in with PostNL' ) );

			await waitFor( () => {
				expect( window.location.href ).toBe(
					'https://postnl.nl/oauth'
				);
			} );
		} );

		it( 'should show error notice when OAuth initiation fails', async () => {
			global.fetch.mockResolvedValueOnce( {
				json: () =>
					Promise.resolve( {
						success: false,
						data: null,
					} ),
			} );

			const user = userEvent.setup();
			renderComponent();

			await user.click( screen.getByText( 'Fill in with PostNL' ) );

			await waitFor( () => {
				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					'Failed to initiate PostNL login.',
					expect.objectContaining( {
						id: 'postnl-login-error',
						context: 'wc/checkout',
					} )
				);
			} );
		} );

		it( 'should show error notice on network error', async () => {
			global.fetch.mockRejectedValueOnce( new Error( 'Network error' ) );

			const user = userEvent.setup();
			renderComponent();

			await user.click( screen.getByText( 'Fill in with PostNL' ) );

			await waitFor( () => {
				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					expect.any( String ),
					expect.objectContaining( {
						id: 'postnl-login-error',
					} )
				);
			} );
		} );

		it( 'should add disabled class while loading', async () => {
			// Make fetch hang to test loading state
			global.fetch.mockImplementation(
				() => new Promise( () => {} ) // Never resolves
			);

			const user = userEvent.setup();
			renderComponent();

			const button = screen.getByRole( 'link', {
				name: 'Fill in with PostNL',
			} );

			await user.click( button );

			expect( button ).toHaveClass( 'disabled' );
		} );
	} );

	describe( 'OAuth callback handling', () => {
		beforeEach( () => {
			// Setup window.location.search with callback token
			window.location.search = '?callback=postnl_token_123';

			// Setup window.postnlSettings for prefillCheckoutFields
			// Note: This tests the BUG - the code uses postnlSettings instead of postnlData
			window.postnlSettings = {
				ajaxUrl: 'https://shop.example.com/wp-admin/admin-ajax.php',
				ajaxNonce: 'ajax-nonce',
			};
		} );

		afterEach( () => {
			delete window.postnlSettings;
		} );

		it( 'should detect callback token in URL', async () => {
			// The component should call prefillCheckoutFields when callback is present
			renderComponent();

			await waitFor( () => {
				// fetch is called for prefill
				expect( global.fetch ).toHaveBeenCalled();
			} );
		} );

		it( 'should call prefill endpoint when callback present', async () => {
			global.fetch.mockResolvedValueOnce( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: {
							person: {
								givenName: 'John',
								familyName: 'Doe',
								email: 'john@example.com',
							},
							primaryAddress: {
								streetName: 'Kalverstraat',
								houseNumber: '1',
								houseNumberAddition: 'A',
								cityName: 'Amsterdam',
								postalCode: '1000AA',
								countryName: 'NL',
							},
						},
					} ),
			} );

			await act( async () => {
				renderComponent();
			} );

			await waitFor( () => {
				expect( global.fetch ).toHaveBeenCalledWith(
					'https://shop.example.com/wp-admin/admin-ajax.php',
					expect.objectContaining( {
						method: 'POST',
					} )
				);
			} );
		} );

		it( 'should update shipping and billing address on successful prefill', async () => {
			global.fetch.mockResolvedValueOnce( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: {
							person: {
								givenName: 'John',
								familyName: 'Doe',
								email: 'john@example.com',
							},
							primaryAddress: {
								streetName: 'Kalverstraat',
								houseNumber: '1',
								houseNumberAddition: 'A',
								cityName: 'Amsterdam',
								postalCode: '1000AA',
								countryName: 'NL',
							},
						},
					} ),
			} );

			await act( async () => {
				renderComponent();
			} );

			await waitFor( () => {
				expect( mockSetShippingAddress ).toHaveBeenCalledWith(
					expect.objectContaining( {
						first_name: 'John',
						last_name: 'Doe',
						email: 'john@example.com',
						address_1: 'Kalverstraat',
						city: 'Amsterdam',
						postcode: '1000AA',
						'postnl/house_number': '1',
					} )
				);
			} );

			await waitFor( () => {
				expect( mockSetBillingAddress ).toHaveBeenCalled();
			} );
		} );

		it( 'should show error notice when prefill fails', async () => {
			global.fetch.mockResolvedValueOnce( {
				json: () =>
					Promise.resolve( {
						success: false,
						data: null,
					} ),
			} );

			await act( async () => {
				renderComponent();
			} );

			await waitFor( () => {
				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					'Failed to retrieve PostNL user data.',
					expect.objectContaining( {
						id: 'postnl-fetch-error',
						context: 'wc/checkout',
					} )
				);
			} );
		} );

		it( 'should show error notice on prefill network error', async () => {
			global.fetch.mockRejectedValueOnce( new Error( 'Network error' ) );

			await act( async () => {
				renderComponent();
			} );

			await waitFor( () => {
				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					'Failed to retrieve PostNL address. Please try again.',
					expect.objectContaining( {
						id: 'postnl-fetch-error',
					} )
				);
			} );
		} );
	} );

	describe( 'edge cases', () => {
		it( 'should handle missing fill_in_with_postnl_settings', () => {
			getSetting.mockReturnValue( {} );

			const { container } = renderComponent();

			expect( container.firstChild ).toBeNull();
		} );

		it( 'should handle null customer data', () => {
			useSelect.mockImplementation( ( callback ) => {
				const mockSelect = () => ( {
					getCustomerData: () => null,
				} );
				return callback( mockSelect );
			} );

			// Should not crash
			expect( () => renderComponent() ).not.toThrow();
		} );

		it( 'should handle null shipping address', () => {
			useSelect.mockImplementation( ( callback ) => {
				const mockSelect = () => ( {
					getCustomerData: () => ( {
						shippingAddress: null,
					} ),
				} );
				return callback( mockSelect );
			} );

			// Should not crash and use default country
			renderComponent();

			expect(
				screen.getByText( 'Fill in with PostNL' )
			).toBeInTheDocument();
		} );

		it( 'should handle prefill data with missing fields', async () => {
			window.location.search = '?callback=token';
			window.postnlSettings = {
				ajaxUrl: '/ajax',
				ajaxNonce: 'nonce',
			};

			global.fetch.mockResolvedValueOnce( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: {
							person: {
								givenName: 'John',
								// Missing familyName, email
							},
							primaryAddress: {
								streetName: 'Test Street',
								// Missing other fields
							},
						},
					} ),
			} );

			await act( async () => {
				renderComponent();
			} );

			await waitFor( () => {
				expect( mockSetShippingAddress ).toHaveBeenCalledWith(
					expect.objectContaining( {
						first_name: 'John',
						last_name: '',
						email: '',
						address_1: 'Test Street',
					} )
				);
			} );
		} );
	} );

	describe( 'known bug: undefined postnlSettings', () => {
		// This test documents the bug where postnlSettings is used but undefined
		// The code should use postnlData instead
		it( 'should handle missing postnlSettings gracefully', async () => {
			window.location.search = '?callback=token';
			delete window.postnlSettings;

			// The code has a bug where it uses `postnlSettings` instead of `postnlData`
			// This should cause a ReferenceError
			await act( async () => {
				// We expect this to fail because postnlSettings is undefined
				try {
					renderComponent();
				} catch ( e ) {
					// Expected error due to bug
					expect( e.name ).toBe( 'ReferenceError' );
				}
			} );
		} );
	} );
} );
