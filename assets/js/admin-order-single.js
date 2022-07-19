
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
				var field_name = postnl_admin_order_obj.fields[ i ].replace( postnl_admin_order_obj.prefix, '' );
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
				console.log( response );
				if ( response.success === true ) {
					alert( 'ok' );
				}
			});

			return false;
		},

		// Delete a tracking item
		delete_label: function() {

			var tracking_id = $( this ).attr( 'rel' );

			$( '#tracking-item-' + tracking_id ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			var data = {
				action:      'wc_shipment_tracking_delete_item',
				order_id:    woocommerce_admin_meta_boxes.post_id,
				tracking_id: tracking_id,
				security:    $( '#wc_shipment_tracking_delete_nonce' ).val()
			};

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#tracking-item-' + tracking_id ).unblock();
				if ( response != '-1' ) {
					$( '#tracking-item-' + tracking_id ).remove();
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
