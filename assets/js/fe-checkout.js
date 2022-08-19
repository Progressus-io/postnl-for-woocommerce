( function( $ ) {

	var postnl_fe_checkout = {
		// init Class
		init: function() {
			jQuery('body').on( 'updated_checkout', this.use_select2 );
			jQuery('body').on( 'updated_checkout', this.operate );
		},

		operate: function() {
			var checkout_option = jQuery( '#postnl_checkout_option' );
			var radio_tabs      = checkout_option.find( '.postnl_checkout_tab_list .postnl_option' );
			var radio_options   = checkout_option.find( '.postnl_checkout_content_container .postnl_sub_radio' );

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
			} );

			checkout_option.find( '.postnl_checkout_tab_list .active .postnl_option' ).trigger( 'click' );
		},

		use_select2: function(){
			var selectbox = jQuery( '.postnl-checkout-field.select2 select' );
			selectbox.select2( {
				minimumResultsForSearch: -1
			} );
		},
	};

	postnl_fe_checkout.init();

} )( jQuery );
