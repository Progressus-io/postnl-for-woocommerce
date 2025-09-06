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

			const adultSelector =
				'input[name="_postnl_adult_product"], ' +
				'[data-template-block-id="postnl-adult-product"] .components-checkbox-control__input';

			const letterboxSelector =
				'input[name="_postnl_letterbox_parcel"], ' +
				'[data-template-block-id="postnl-letterbox-parcel"] .components-checkbox-control__input';

			if ($(adultSelector).is(':checked')) {
				$(letterboxSelector).prop('checked', false);
			}
			if ($(letterboxSelector).is(':checked')) {
				$(adultSelector).prop('checked', false);
			}

			$(document)
				.off('change.postnlConflict')
				.on('change.postnlConflict', adultSelector, function () {
					if ($(this).is(':checked')) {
						$(letterboxSelector).prop('checked', false);
					}
				})
				.on('change.postnlConflict', letterboxSelector, function () {
					if ($(this).is(':checked')) {
						$(adultSelector).prop('checked', false);
					}
				});
		}
	};

	// Initialize the settings
	postnl_settings.init();

} )( jQuery );
