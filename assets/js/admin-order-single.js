
( function( $ ) {
	const label_form = jQuery( '#shipment-postnl-label-form' );

	var postnl_order_single = {

		// init Class
		init: function() {
			jQuery( '#shipment-postnl-label-form' )
				.on( 'click', 'a.delete-label', this.delete_label )
				.on( 'click', 'button.button-save-form', this.save_form )
				.on( 'click', 'button.button-activate-return', this.activate_return )
				.on( 'click', 'button.button-send-smart-return', this.send_smart_return );

			postnl_order_single.init_option_pairing();
		},

		// Resolve the flag name (id minus the postnl_ prefix) for a checkbox field id.
		strip_prefix: function ( field_id ) {
			var prefix = postnl_admin_order_obj.prefix || '';
			if ( prefix && field_id.indexOf( prefix ) === 0 ) {
				return field_id.substring( prefix.length );
			}
			return field_id;
		},

		// Collect the option flags currently checked in the meta box.
		get_selected_flags: function () {
			var prefix   = postnl_admin_order_obj.prefix || '';
			var selected = [];
			label_form.find( 'input[type="checkbox"]' ).each( function () {
				if ( ! this.checked ) {
					return;
				}
				var id = this.id || '';
				if ( prefix && id.indexOf( prefix ) === 0 ) {
					selected.push( id.substring( prefix.length ) );
				}
			} );
			return selected;
		},

		// Resolve allowed combinations for the active shipping feature, or [].
		get_active_combinations: function () {
			var all     = postnl_admin_order_obj.allowed_combinations || {};
			var feature = postnl_admin_order_obj.active_feature || '';
			if ( feature && all[ feature ] ) {
				return all[ feature ];
			}
			return [];
		},

		// Build the list of flag names that appear anywhere in the allowed combinations.
		get_known_flags: function ( combinations ) {
			var seen = {};
			for ( var i = 0; i < combinations.length; i++ ) {
				for ( var j = 0; j < combinations[ i ].length; j++ ) {
					seen[ combinations[ i ][ j ] ] = true;
				}
			}
			return Object.keys( seen );
		},

		// Wire change handlers and apply the initial pass.
		init_option_pairing: function () {
			if ( typeof window.postnl_option_pairing === 'undefined' ) {
				return;
			}

			var combinations = postnl_order_single.get_active_combinations();
			if ( ! combinations.length ) {
				return;
			}

			label_form.on( 'change', 'input[type="checkbox"]', function () {
				postnl_order_single.refresh_option_pairing();
			} );

			postnl_order_single.refresh_option_pairing();
		},

		// Recompute disabled state and helper text for all option checkboxes.
		refresh_option_pairing: function () {
			var combinations = postnl_order_single.get_active_combinations();
			if ( ! combinations.length ) {
				return;
			}

			// Once a label is generated the form is locked; nothing to validate.
			if ( label_form.hasClass( 'generated' ) ) {
				label_form.find( '.postnl-pairing-hint' ).remove();
				return;
			}

			var prefix   = postnl_admin_order_obj.prefix || '';
			var labels   = postnl_admin_order_obj.option_labels || {};
			var i18n     = postnl_admin_order_obj.i18n || {};
			var flags    = postnl_order_single.get_known_flags( combinations );
			var selected = postnl_order_single.get_selected_flags();
			var result   = window.postnl_option_pairing.evaluate( selected, flags, combinations );

			for ( var i = 0; i < flags.length; i++ ) {
				var flag    = flags[ i ];
				var $input  = label_form.find( '#' + prefix + flag );
				if ( ! $input.length ) {
					continue;
				}
				var $wrapper = $input.closest( '.form-field, .postnl-form-field, p, div' ).first();

				// Never re-disable a field that the label-generated state already locked.
				if ( $input.data( 'postnl-locked' ) === true ) {
					continue;
				}

				if ( typeof result.disabled[ flag ] !== 'undefined' ) {
					var blockers = ( result.disabled[ flag ] || [] ).map( function ( b ) {
						return labels[ b ] || b;
					} );
					$input.prop( 'disabled', true ).addClass( 'postnl-pairing-disabled' );
					$wrapper.addClass( 'postnl-disabled-option' );
					if ( i18n.cannot_combine && blockers.length ) {
						$input.attr( 'title', i18n.cannot_combine.replace( '%s', blockers.join( ', ' ) ) );
					} else {
						$input.removeAttr( 'title' );
					}
				} else {
					if ( $input.hasClass( 'postnl-pairing-disabled' ) ) {
						$input.prop( 'disabled', false ).removeClass( 'postnl-pairing-disabled' );
					}
					$wrapper.removeClass( 'postnl-disabled-option' );
					$input.removeAttr( 'title' );
				}
			}

			postnl_order_single.render_companion_hint( result.missing, labels, i18n );
		},

		// Render or clear the "required companion" inline hint.
		render_companion_hint: function ( missing, labels, i18n ) {
			var $hint = label_form.find( '.postnl-pairing-hint' );

			if ( ! missing.length ) {
				$hint.remove();
				return;
			}

			var names = missing.map( function ( m ) {
				return '"' + ( labels[ m ] || m ) + '"';
			} ).join( ', ' );

			var template = i18n.requires_companion || 'Add %s to complete a valid combination.';
			var text     = template.replace( '%s', names );

			if ( ! $hint.length ) {
				$hint = jQuery( '<p class="postnl-pairing-hint"></p>' );
				label_form.find( '#shipment-postnl-error-text' ).after( $hint );
			}
			$hint.text( text );
		},

		// When a user enters a new tracking item
		save_form: function () {
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

		activate_return: function() {
			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

            var data = {
                action:   'postnl_activate_return_function',
                security: $( '#activate_return_function_nonce' ).val(),
                order_id: woocommerce_admin_meta_boxes.post_id,
            };

            $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				if ( true === response.success ) {
					jQuery('button.button-activate-return').prop('disabled',true);
					jQuery('.activate-return-info').css('display', 'none');
					jQuery('.activated-return-info').css('display', 'block');
				} else {
					var error_cont = label_form.find( '#shipment-postnl-error-text' );
					error_cont.empty();
					var error_text = response.data.hasOwnProperty( 'message' ) ? response.data.message : 'Unknown error!';
					error_cont.html( error_text );
				}
	            label_form.unblock();
            });
        },

		send_smart_return: function() {
			label_form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

            var error_cont = jQuery( '#shipment-postnl-error-text' );
            var data = {
                action:   'postnl_send_smart_return_email',
                security: $( '#send_smart_return_email_nonce' ).val(),
                order_id: woocommerce_admin_meta_boxes.post_id,
            };

            $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
                if ( ! response.success ) {
                    response.data.message
                    error_cont.html(  response.data.message );
                }else{
					console.log(response);
					error_cont.html( 'Email sent successfully' );					
				}
	            label_form.unblock();
            });
        }
	}

	
	postnl_order_single.init();


	window.postnl_order_single_refresh = postnl_order_single.refresh_items;
} )( jQuery );
