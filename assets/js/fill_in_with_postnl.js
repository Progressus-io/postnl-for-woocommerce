( function ( $ ) {

	var fill_in_with_postnl = {
		init: function () {
			this.handle_login_button();
		},

		handle_login_button: function () {
			const button = $( '#postnl-login-button' );
			if (button.length) {
				$.ajax(
					{
						url: postnlCheckoutParams.rest_url,
						method: 'POST',
						data: {
							nonce: postnlCheckoutParams.nonce
						},
						success: function (response) {
							if (response.success && response.data.redirect_uri) {
								button.attr( 'href', response.data.redirect_uri );
							} else {
								button.hide();
								console.error( 'Failed to fetch redirect URI:', response.message );
							}
						},
						error: function (xhr, status, error) {
							button.hide();
							console.error( 'Error fetching redirect URI:', error );
						}
					}
				);
			}
		}
	};

	fill_in_with_postnl.init();

} )( jQuery );
