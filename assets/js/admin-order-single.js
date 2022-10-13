
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
			var error_cont = label_form.find( '#shipment-postnl-error-text' );

			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			error_cont.empty();
			
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

						if ( 'create_return_label' === field && 'yes' === response.data.backend[ field ] ) {
							label_form.addClass( 'has-return' );
						}

						if ( 'letterbox' === field && 'yes' === response.data.backend[ field ] ) {
							label_form.addClass( 'has-letterbox' );
						}
					}

					if( response.data.tracking_note ) {

						$( '#woocommerce-order-notes' ).block({
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6
							}
						});

						var data = {
							action:    'woocommerce_add_order_note',
							post_id:   woocommerce_admin_meta_boxes.post_id,
							note_type: response.data.note_type,
							note:      response.data.tracking_note,
							security:  woocommerce_admin_meta_boxes.add_order_note_nonce
						};

						$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response_note ) {
							$( 'ul.order_notes' ).prepend( response_note );
							$( '#woocommerce-order-notes' ).unblock();
							$( '#add_order_note' ).val( '' );
						});
					}
				} else {
					var error_text = response.data.hasOwnProperty( 'message' ) ? response.data.message : 'Unknown error!';
					error_cont.html( error_text );
				}
			});

			return false;
		},

		// Delete a tracking item
		delete_label: function() {
			var label_form = jQuery( '#shipment-postnl-label-form' );
			var error_cont = label_form.find( '#shipment-postnl-error-text' );
			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			error_cont.empty();
			
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
					label_form.removeClass( 'has-return' );
					label_form.removeClass( 'has-letterbox' );

					for ( var i = 0; i < postnl_admin_order_obj.fields.length; i++ ) {
						label_form.find( '#' + postnl_admin_order_obj.fields[ i ] ).removeAttr( 'disabled' );
					}

					$( '.order_notes .note .postnl-tracking-link' ).each( function(){
						var note = $( this ).closest( 'li.note' );

						$( note ).block({
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6
							}
						});

						var data = {
							action:   'woocommerce_delete_order_note',
							note_id:  $( note ).attr( 'rel' ),
							security: woocommerce_admin_meta_boxes.delete_order_note_nonce
						};

						$.post( woocommerce_admin_meta_boxes.ajax_url, data, function() {
							$( note ).remove();
						});
					} );
				} else {
					var error_text = response.data.hasOwnProperty( 'message' ) ? response.data.message : 'Unknown error!';
					error_cont.html( error_text );
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
