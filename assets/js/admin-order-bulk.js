( function( $ ) {

	var postnl_order_bulk = {
		// init Class
		init: function() {
			var posts_filter = jQuery( '#posts-filter, #wc-orders-filter' );
			
			posts_filter
				.on( 'change', '#bulk-action-selector-top', this.toggle_create_label_modal );
			posts_filter
				.on( 'change', '#bulk-action-selector-bottom', this.toggle_create_label_modal );
			posts_filter
				.on( 'click', '.button.action', this.disable_submit_button );
		},

		toggle_create_label_modal: function( evt ){
			evt.preventDefault();

			var value 		= jQuery( this ).val();
			var title 		= jQuery(':selected', this ).text();
			var post_form 	= jQuery( this ).parents('#posts-filter, #wc-orders-filter');

			if( 'postnl-create-label' == value ){
				// Show thickbox modal.
				tb_show( "", '/?TB_inline=true&width=385&height=200&inlineId=postnl-create-label-modal' );
				var tb_window = jQuery( '#TB_window' );
				tb_window.find( '#TB_ajaxWindowTitle' ).text(title); // Set title
				tb_window.find( '#postnl-create-label-proceed' ).on( 'click', function( evt ) {
					evt.preventDefault();

					post_form.append( '<div id="postnl-field-container" style="display:none;"></div>' );
					tb_window.find( '.shipment-postnl-row-container' ).each( function() {
						var field_clone = jQuery( this ).clone();
						post_form.find( '#postnl-field-container' ).append( field_clone );
					} );
					var selected_value = tb_window.find('#postnl_position_printing_labels').val();
					post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_position_printing_labels" value="' + selected_value + '">');
					jQuery( this ).prop( 'disabled', true );
					post_form.submit();
				} );
			}

			if( 'postnl-change-shipping-options' == value ){
				tb_show( "", '/?TB_inline=true&width=385&height=230&inlineId=postnl-change-shipping-options-modal' );
				var tb_window = jQuery( '#TB_window' );
				tb_window.find( '#TB_ajaxWindowTitle' ).text(title);
				tb_window.find( '#postnl_shipping_zone' ).on( 'change', function( event ) {
					var current = this.value;
					$('.conditional').each(function() {
						if ( $(this).hasClass(current) ) {
							$(this).show();
						} else {
							$(this).hide()
						}
					});
				});
				tb_window.find( '#postnl-change-shipping-options-proceed' ).on( 'click', function( evt ) {
					evt.preventDefault();
					post_form.append( '<div id="postnl-field-container" style="display:none;"></div>' );
					tb_window.find( '.shipment-postnl-row-container' ).each( function() {
						var field_clone = jQuery( this ).clone();
						post_form.find( '#postnl-field-container' ).append( field_clone );
					} );
					post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_shipping_zone" value="' + tb_window.find('#postnl_shipping_zone').val() + '">');
                    post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_default_shipping_options_nl" value="' + tb_window.find('#postnl_default_shipping_options_nl').val() + '">');
                    post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_default_shipping_options_be" value="' + tb_window.find('#postnl_default_shipping_options_be').val() + '">');
                    post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_default_shipping_options_eu" value="' + tb_window.find('#postnl_default_shipping_options_eu').val() + '">');
                    post_form.find('#postnl-field-container').append('<input type="hidden" name="postnl_default_shipping_options_row" value="' + tb_window.find('#postnl_default_shipping_options_row').val() + '">');
					jQuery( this ).prop( 'disabled', true );
					post_form.submit();
				} );
			}

		},

		disable_submit_button: function( evt ) {
			var bulkactions = jQuery( this ).closest( '.bulkactions' );
			var bulkdropdown = bulkactions.find( 'select[name=action]' );

			if ( 'postnl-create-label' === bulkdropdown.val() ) {
				jQuery( this ).prop( 'disabled', true );
				jQuery( '#posts-filter' ).submit();
			}
			if ( 'postnl-change-shipping-options' === bulkdropdown.val() ) {
				jQuery( this ).prop( 'disabled', true );
				jQuery( '#posts-filter' ).submit();
			}
		},
	};
	postnl_order_bulk.init();

} )( jQuery );