
( function( $ ) {

	var postnl_order_single = {

		// init Class
		init: function() {
			jQuery( '#shipment-postnl-label-form' )
				.on( 'click', 'a.delete-label', this.delete_label )
				.on( 'click', 'button.button-save-form', this.save_form );
		},

		// When a user enters a new tracking item
		save_form: function () {
			var label_form = jQuery( '#shipment-postnl-label-form' );
			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			
			var data = {
				action:   'postnl_order_save_form',
				order_id: woocommerce_admin_meta_boxes.post_id,
			};
			
			for ( var i = 0; i < postnl_admin_order_obj.fields.length; i++ ) {
				var field_name = postnl_admin_order_obj.fields[ i ];
				var maybe_field = label_form.find( '#' + postnl_admin_order_obj.fields[ i ] );

				if ( ! maybe_field.is( ':input' ) ) {
					continue;
				}
				
				if ( 'checkbox' === maybe_field.prop( 'type' ) ) {
					data[ field_name ] = maybe_field.is( ':checked' ) ? maybe_field.val() : '';
				} else {
					data[ field_name ] = maybe_field.val();
				}
			}

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				label_form.unblock();

				if ( true === response.success ) {
					label_form.addClass( 'generated' );

					for (let field in response.data.backend ) {
						jQuery( '#postnl_' + field ).prop( 'disabled', true );
					}
				}
			});

			return false;
		},

		// Delete a tracking item
		delete_label: function() {
			var label_form = jQuery( '#shipment-postnl-label-form' );
			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			
			var data = {
				action:   'postnl_order_delete_data',
				order_id: woocommerce_admin_meta_boxes.post_id,
			};

			for ( var i = 0; i < postnl_admin_order_obj.fields.length; i++ ) {
				var field_name = postnl_admin_order_obj.fields[ i ];
				var maybe_field = label_form.find( '#' + postnl_admin_order_obj.fields[ i ] );

				if ( ! maybe_field.is( ':input' ) ) {
					continue;
				}
				
				if ( 'checkbox' === maybe_field.prop( 'type' ) ) {
					data[ field_name ] = maybe_field.is( ':checked' ) ? maybe_field.val() : '';
				} else {
					data[ field_name ] = maybe_field.val();
				}
			}

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				label_form.unblock();

				if ( true === response.success ) {
					label_form.removeClass( 'generated' );

					for ( var i = 0; i < postnl_admin_order_obj.fields.length; i++ ) {
						label_form.find( '#' + postnl_admin_order_obj.fields[ i ] ).removeAttr( 'disabled' );
					}
				}
			});

			return false;
		},

		refresh_items: function() {
			var data = {
				action:                   'wc_shipment_tracking_get_items',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				security:                 $( '#wc_shipment_tracking_get_nonce' ).val()
			};

			$( '#woocommerce-shipment-tracking' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#woocommerce-shipment-tracking' ).unblock();
				if ( response != '-1' ) {
					$( '#woocommerce-shipment-tracking #tracking-items' ).html( response );
				}
			});
		},
	}

	postnl_order_single.init();

	window.postnl_order_single_refresh = postnl_order_single.refresh_items;
} )( jQuery );
