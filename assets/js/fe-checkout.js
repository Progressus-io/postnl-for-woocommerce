( function( $ ) {

	var postnl_fe_checkout = {
		// init Class
		init: function() {
			jQuery('body').on( 'updated_checkout', this.operate );

			// Trigger update_checkout on address change
			jQuery('#billing_house_number').on( 'change', function (){
				if ( ! jQuery('#ship-to-different-address-checkbox').is(':checked') ) {
					jQuery('body').trigger('update_checkout');
				}
			} );
			jQuery('#billing_postcode').on( 'change', function (){
				if ( ! jQuery('#ship-to-different-address-checkbox').is(':checked') ) {
					jQuery('body').trigger('update_checkout');
				}
			} );
			jQuery('#shipping_house_number').on( 'change', function (){
				jQuery('body').trigger('update_checkout');
			} );
			jQuery('#shipping_postcode').on( 'change', function (){
				jQuery('body').trigger('update_checkout');
			} );
		},

		operate: function() {
			var checkout_option = jQuery( '#postnl_checkout_option' );
			var radio_tabs      = checkout_option.find( '.postnl_checkout_tab_list .postnl_option' );
			var radio_options   = checkout_option.find( '.postnl_checkout_content_container .postnl_sub_radio' );
			var tab_value       = checkout_option.find( '.postnl_checkout_tab_list input.postnl_option:checked' ).val();

			checkout_option.find( '#postnl_' + tab_value + '_content' ).addClass( 'active' );

			var field_name = 'postnl_' + tab_value;
			var closest_li = jQuery( 'input[name=' + field_name + ']:checked' ).closest( 'li' );
			closest_li.addClass( 'active' );
			var dropoff_data = closest_li.data();

			for ( let name in dropoff_data ) {
				jQuery( '#' + field_name + '_' + name ).val( dropoff_data[ name ] );
			}

			radio_tabs.on( 'change', function( evt ) {
				var pri_container = jQuery( this ).closest( '#postnl_checkout_option' );
				var ul_list       = jQuery( this ).closest( '.postnl_checkout_tab_list' );
				var current_val   = jQuery( this ).val();

				ul_list.children( 'li' ).removeClass( 'active' );

				jQuery( this ).closest( 'li' ).addClass( 'active' );

				pri_container.find( '.postnl_checkout_content_container .postnl_content' ).removeClass( 'active' );
				pri_container.find( '#postnl_' + current_val + '_content' ).addClass( 'active' );

				// Auto-select first option in the newly active tab if no option is currently selected
				var active_content = pri_container.find( '#postnl_' + current_val + '_content' );
				var field_name = 'postnl_' + current_val;
				var current_selection = jQuery( 'input[name=' + field_name + ']:checked' );

				if ( current_selection.length === 0 ) {
					var first_option = active_content.find( 'input[name=' + field_name + ']:first' );
					if ( first_option.length > 0 ) {
						first_option.prop( 'checked', true ).trigger( 'change' );
					}
				}
			} );

			radio_options.on( 'change', function() {
				var ul_list    = jQuery( this ).closest( '.postnl_list' );
				var field_name = jQuery( this ).attr( 'name' );
				ul_list.find( 'ul.postnl_sub_list > li' ).removeClass( 'active' );

				var closest_li = jQuery( this ).closest( 'li' );
				closest_li.addClass( 'active' );
				var dropoff_data = closest_li.data();

				for ( let name in dropoff_data ) {
					jQuery( '#' + field_name + '_' + name ).val( dropoff_data[ name ] );
				}

				jQuery('body').trigger('update_checkout');
			} );

			// Trigger the active tab to ensure proper initialization
			var active_tab_input = checkout_option.find( '.postnl_checkout_tab_list .active .postnl_option' );
			if ( active_tab_input.length > 0 ) {
				active_tab_input.trigger( 'change' );
			}
		},
	};

	postnl_fe_checkout.init();

} )( jQuery );
