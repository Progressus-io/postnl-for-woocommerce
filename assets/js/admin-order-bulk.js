( function( $ ) {

	var current_bulk_action = 'create-label';

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

		get_selected_order_ids: function( post_form ) {
			var order_ids = [];
			post_form.find( 'input[name="post[]"]:checked' ).each( function() {
				order_ids.push( $( this ).val() );
			} );
			if ( ! order_ids.length ) {
				post_form.find( 'input[name="id[]"]:checked' ).each( function() {
					order_ids.push( $( this ).val() );
				} );
			}
			return order_ids;
		},

		trigger_download: function( url ) {
			var a = document.createElement( 'a' );
			a.href = url;
			a.download = '';
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
		},

		toggle_create_label_modal: function( evt ){
			evt.preventDefault();

			var value     = jQuery( this ).val();
			var title     = jQuery( ':selected', this ).text();
			var post_form = jQuery( this ).parents( '#posts-filter, #wc-orders-filter' );

			var is_create   = 'postnl-create-label' === value;
			var is_print    = 'postnl-print-labels' === value;
			var is_download = 'postnl-download-labels' === value;

			if ( is_create || is_print || is_download ) {
				if ( is_print ) {
					current_bulk_action = 'print';
				} else if ( is_download ) {
					current_bulk_action = 'download';
				} else {
					current_bulk_action = 'create-label';
				}

				tb_show( "", '/?TB_inline=true&width=385&height=200&inlineId=postnl-create-label-modal' );
				var tb_window = jQuery( '#TB_window' );
				tb_window.find( '#TB_ajaxWindowTitle' ).text( title );

				tb_window.find( '#postnl-create-label-proceed' ).off( 'click.postnl' ).on( 'click.postnl', function( evt ) {
					evt.preventDefault();

					if ( 'create-label' === current_bulk_action ) {
						post_form.append( '<div id="postnl-field-container" style="display:none;"></div>' );
						tb_window.find( '.shipment-postnl-row-container' ).each( function() {
							var field_clone = jQuery( this ).clone();
							post_form.find( '#postnl-field-container' ).append( field_clone );
						} );
						var selected_value = tb_window.find( '#postnl_position_printing_labels' ).val();
						post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_position_printing_labels" value="' + selected_value + '">' );
						jQuery( this ).prop( 'disabled', true );
						post_form.submit();
						return;
					}

					// Print or Download via AJAX.
					var proceed_btn = jQuery( this );
					var action      = current_bulk_action;

					// Open print window synchronously before AJAX to avoid popup blocker.
					var printWindow = null;
					if ( 'print' === action ) {
						printWindow = window.open( '', '_blank' );
					}

					var order_ids = postnl_order_bulk.get_selected_order_ids( post_form );

					if ( ! order_ids.length ) {
						tb_window.find( '#postnl-bulk-error' ).text( 'No orders selected.' ).show();
						if ( printWindow ) {
							printWindow.close();
						}
						return;
					}

					proceed_btn.prop( 'disabled', true );
					tb_window.find( '#postnl-bulk-error' ).hide().text( '' );
					tb_window.find( '#postnl-bulk-spinner' ).show();

					var ajax_data = {
						action:      'postnl_bulk_labels_ajax',
						security:    postnl_admin_bulk_obj.security,
						order_ids:   order_ids,
						bulk_action: action,
					};

					var pos_val = tb_window.find( '#postnl_position_printing_labels' ).val();
					if ( pos_val ) {
						ajax_data['postnl_position_printing_labels'] = pos_val;
					}

					var num_val = tb_window.find( '#postnl_num_labels' ).val();
					if ( num_val ) {
						ajax_data['postnl_num_labels'] = num_val;
					}

					$.post( postnl_admin_bulk_obj.ajax_url, ajax_data, function( response ) {
						tb_window.find( '#postnl-bulk-spinner' ).hide();
						proceed_btn.prop( 'disabled', false );

						if ( ! response.success ) {
							var error_text = response.data && response.data.message ? response.data.message : 'Unknown error.';
							tb_window.find( '#postnl-bulk-error' ).text( error_text ).show();
							if ( printWindow ) {
								printWindow.close();
							}
							return;
						}

						tb_remove();

						if ( 'print' === action && printWindow ) {
							printWindow.location.href = response.data.url;
							printWindow.onload = function() {
								printWindow.print();
							};
						} else {
							postnl_order_bulk.trigger_download( response.data.url );
						}
					} );
				} );
			}

			if ( 'postnl-change-shipping-options' === value ) {
				tb_show( "", '/?TB_inline=true&width=385&height=230&inlineId=postnl-change-shipping-options-modal' );
				var tb_window = jQuery( '#TB_window' );
				tb_window.find( '#TB_ajaxWindowTitle' ).text( title );
				tb_window.find( '#postnl_shipping_zone' ).on( 'change', function( event ) {
					var current = this.value;
					$( '.conditional' ).each( function() {
						if ( $( this ).hasClass( current ) ) {
							$( this ).show();
						} else {
							$( this ).hide();
						}
					} );
				} );
				tb_window.find( '#postnl-change-shipping-options-proceed' ).on( 'click', function( evt ) {
					evt.preventDefault();
					post_form.append( '<div id="postnl-field-container" style="display:none;"></div>' );
					tb_window.find( '.shipment-postnl-row-container' ).each( function() {
						var field_clone = jQuery( this ).clone();
						post_form.find( '#postnl-field-container' ).append( field_clone );
					} );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_shipping_zone" value="' + tb_window.find( '#postnl_shipping_zone' ).val() + '">' );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_default_shipping_options_nl" value="' + tb_window.find( '#postnl_default_shipping_options_nl' ).val() + '">' );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_default_shipping_options_be" value="' + tb_window.find( '#postnl_default_shipping_options_be' ).val() + '">' );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_default_shipping_options_eu" value="' + tb_window.find( '#postnl_default_shipping_options_eu' ).val() + '">' );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_default_shipping_options_row" value="' + tb_window.find( '#postnl_default_shipping_options_row' ).val() + '">' );
					post_form.find( '#postnl-field-container' ).append( '<input type="hidden" name="postnl_default_shipping_options_pickup" value="' + tb_window.find( '#postnl_default_shipping_options_pickup' ).val() + '">' );
					jQuery( this ).prop( 'disabled', true );
					post_form.submit();
				} );
			}
		},

		disable_submit_button: function( evt ) {
			var bulkactions  = jQuery( this ).closest( '.bulkactions' );
			var bulkdropdown = bulkactions.find( 'select[name=action]' );
			var val          = bulkdropdown.val();

			if ( 'postnl-create-label' === val ) {
				jQuery( this ).prop( 'disabled', true );
				jQuery( '#posts-filter' ).submit();
			}
			if ( 'postnl-change-shipping-options' === val ) {
				jQuery( this ).prop( 'disabled', true );
				jQuery( '#posts-filter' ).submit();
			}
			if ( 'postnl-print-labels' === val || 'postnl-download-labels' === val ) {
				evt.preventDefault();
				tb_show( "", '/?TB_inline=true&width=385&height=200&inlineId=postnl-create-label-modal' );
			}
		},
	};
	postnl_order_bulk.init();

} )( jQuery );
