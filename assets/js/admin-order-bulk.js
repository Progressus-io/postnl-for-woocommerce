( function( $ ) {

	var postnl_order_bulk = {
		// init Class
		init: function() {
			var posts_filter = jQuery( '#posts-filter' );
			
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
			var post_form 	= jQuery( this ).parents('#posts-filter');

			if( 'postnl-create-label' == value ){

				// Show thickbox modal.
				tb_show( "", '/?TB_inline=true&width=460&height=420&inlineId=postnl-create-label-modal' );
				var tb_window = jQuery( '#TB_window' );
				tb_window.find( '#TB_ajaxWindowTitle' ).text(title); // Set title
				tb_window.find( '#postnl_create_label_proceed' ).on( 'click', function( evt ) {
					evt.preventDefault();

					post_form.append( '<div id="postnl-field-container" style="display:none;"></div>' );
					tb_window.find( '.shipment-postnl-row-container' ).each( function() {
						var field_clone = jQuery( this ).clone();
						post_form.find( '#postnl-field-container' ).append( field_clone );
					} );

					jQuery( this ).prop( 'disabled', true );
					post_form.submit();
				} );

			}else{
				jQuery('#TB_closeWindowButton').click();

			}

		},

		disable_submit_button: function( evt ) {
			var bulkactions = jQuery( this ).closest( '.bulkactions' );
			var bulkdropdown = bulkactions.find( 'select[name=action]' );

			if ( 'postnl-create-label' === bulkdropdown.val() ) {
				jQuery( this ).prop( 'disabled', true );
				jQuery( '#posts-filter' ).submit();
			}
		},
	};

	postnl_order_bulk.init();
	
	//Force to select Track & Trace
	const insuredShipping = $('#postnl_insured_shipping');
	const insuredPlus = $('#postnl_insured_plus');
	const trackAndTrace = $('#postnl_track_and_trace');

	function updateTrackAndTraceStatus() {
		if (insuredShipping.is(':checked') || insuredPlus.is(':checked')) {
			trackAndTrace.prop('checked', true);
		}
	}

	function trackAndTraceChangeHandler() {
		if (insuredShipping.is(':checked') || insuredPlus.is(':checked')) {
			trackAndTrace.prop('checked', true);
		}
	}

	insuredShipping.on('change', updateTrackAndTraceStatus);
	insuredPlus.on('change', updateTrackAndTraceStatus);
	trackAndTrace.on('change', trackAndTraceChangeHandler);

	// Run the function once at the beginning to set the correct initial state
	updateTrackAndTraceStatus();

} )( jQuery );

