( function( $ ) {

	var postnl_order_bulk = {
		// init Class
		init: function() {
			jQuery( '#posts-filter' )
				.on( 'change', '#bulk-action-selector-top', this.toggle_create_label_modal );
			jQuery( '#posts-filter' )
				.on( 'change', '#bulk-action-selector-bottom', this.toggle_create_label_modal );
		},

		toggle_create_label_modal: function( evt ){
			evt.preventDefault();

			var value 		= jQuery( this ).val();
			var title 		= jQuery(':selected', this ).text();
			var post_form 	= jQuery( this ).parents('#posts-filter');

			if( 'postnl-create-label' == value ){

				// Show thickbox modal.
				tb_show( "", '/?TB_inline=true&width=460&height=420&inlineId=postnl-create-label-modal' );
				jQuery("#TB_window #TB_ajaxWindowTitle").text(title); // Set title

			}else{
				jQuery('#TB_closeWindowButton').click();

			}

		},
	};

	postnl_order_bulk.init();

} )( jQuery );
