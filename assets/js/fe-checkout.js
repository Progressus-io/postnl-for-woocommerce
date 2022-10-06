var reload_require = false;

( function( $ ) {

	var postnl_fe_checkout = {
		// init Class
		init: function() {
			jQuery('body').on( 'updated_checkout', this.use_select2 );
			jQuery('body').on( 'updated_checkout', this.operate );

			// Reload page if country changed to NL
			var billing_country = jQuery('#billing_country');
			billing_country.attr( 'old-value', billing_country.val() );

			var shipping_country = jQuery('#shipping_country');
			shipping_country.attr( 'old-value', shipping_country.val() );

			jQuery('body').on( 'updated_checkout', this.refresh_page );
			billing_country.on( 'change', this.check_country );
			shipping_country.on( 'change', this.check_country );
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

			checkout_option.find( '.postnl_checkout_tab_list .active .postnl_option' ).trigger( 'click' );
		},

		use_select2: function(){
			var selectbox = jQuery( '.postnl-checkout-field.select2 select' );
			selectbox.select2( {
				minimumResultsForSearch: -1
			} );
		},

		check_country: function() {
			reload_require = 'NL' === $(this).attr('old-value') || 'NL' === $(this).val();
			$(this).attr( 'old-value', $(this).val() );

			console.log( 'reload_require : ' + reload_require  );
		},
		refresh_page: function() {
			if ( reload_require ){
				location.reload(true);
			}
		}
	};

	postnl_fe_checkout.init();

} )( jQuery );
