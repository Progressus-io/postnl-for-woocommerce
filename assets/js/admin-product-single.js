( function( $ ) {

	var postnl_settings = {
		// Init Class
		init: function() {
			this.prevent_conflicting_checkboxes();
		},

		/**
		 * Prevent both "18+" and "Letterbox Parcel" checkboxes from being checked at the same time.
		 */
		prevent_conflicting_checkboxes: function () {
			var $adultCheckbox = $('input[name="_postnl_adult_product"]');
			var $letterboxCheckbox = $('input[name="_postnl_letterbox_parcel"]');

			if ( ! $adultCheckbox.length || ! $letterboxCheckbox.length ) {
				return;
			}

			$adultCheckbox.on( 'change', function () {
				if ( $( this ).is( ':checked' ) ) {
					$letterboxCheckbox.prop( 'checked', false );
				}
			} );

			$letterboxCheckbox.on( 'change', function () {
				if ( $( this ).is( ':checked' ) ) {
					$adultCheckbox.prop( 'checked', false );
				}
			} );
		}
	};

	// Initialize the settings
	postnl_settings.init();

} )( jQuery );
