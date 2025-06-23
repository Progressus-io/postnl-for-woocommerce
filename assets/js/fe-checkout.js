( function( $ ) {
	/*
      * Helper – refresh the “Delivery Days” tab title with base-fee
      *
      */
	function updateDeliveryDayTabFee() {
		const $label   = $('.postnl_checkout_tab_list .postnl_option[value="delivery_day"]').closest('label');
		if ( ! $label.length ) { return; }

		let baseFee  = parseFloat( $label.data('base-fee') || 0 );
		let extraFee = parseFloat( $('#postnl_delivery_day_price').val() || 0 );

		if ( isNaN( baseFee )  ) { baseFee  = 0; }
		if ( isNaN( extraFee ) ) { extraFee = 0; }

		let text = 'Delivery Days';
		if ( baseFee > 0 || extraFee > 0 ) {
			text += ' €' + baseFee.toFixed(2);
			if ( extraFee > 0 ) {
				text += '+€' + extraFee.toFixed(2);
			}
		}
		$label.children('span').first().text( text );
	}

	var postnl_fe_checkout = {
		// init Class
		init: function() {
			jQuery('body').on( 'updated_checkout', this.operate );

			// Trigger update_checkout on address change
			jQuery('#billing_house_number').on( 'change', function (){
				if ( ! jQuery('#ship-to-different-address-checkbox').is(':checked') ) {
					jQuery('body').trigger('update_checkout');
				}
			} );
			jQuery('#billing_postcode').on( 'change', function (){
				if ( ! jQuery('#ship-to-different-address-checkbox').is(':checked') ) {
					jQuery('body').trigger('update_checkout');
				}
			} );
			jQuery('#shipping_house_number').on( 'change', function (){
				jQuery('body').trigger('update_checkout');
			} );
			jQuery('#shipping_postcode').on( 'change', function (){
				jQuery('body').trigger('update_checkout');
			} );
		},

		operate: function () {
			const checkout_option = $('#postnl_checkout_option');
			if (!checkout_option.length) {
				return;
			}

			const radio_tabs = checkout_option.find('.postnl_checkout_tab_list .postnl_option');
			const radio_options = checkout_option.find('.postnl_checkout_content_container .postnl_sub_radio');
			const tab_value = checkout_option.find('.postnl_checkout_tab_list input.postnl_option:checked').val();

			checkout_option.find('#postnl_' + tab_value + '_content').addClass('active');

			const field_name = 'postnl_' + tab_value;
			const closest_li = $('input[name=' + field_name + ']:checked').closest('li');
			closest_li.addClass('active');
			const dropoff_data = closest_li.data();

			for (let name in dropoff_data) {
				$('#' + field_name + '_' + name).val(dropoff_data[name]);
			}

			updateDeliveryDayTabFee();

			radio_tabs.on('change', function () {
				const pri_container = $(this).closest('#postnl_checkout_option');
				const ul_list = $(this).closest('.postnl_checkout_tab_list');
				const current_val = $(this).val();

				ul_list.children('li').removeClass('active');
				$(this).closest('li').addClass('active');

				pri_container.find('.postnl_checkout_content_container .postnl_content').removeClass('active');
				pri_container.find('#postnl_' + current_val + '_content').addClass('active');

				updateDeliveryDayTabFee();
			});

			radio_options.on('change', function () {
				const ul_list = $(this).closest('.postnl_list');
				const field_name = $(this).attr('name');

				ul_list.find('ul.postnl_sub_list > li').removeClass('active');

				const closest_li = $(this).closest('li');
				closest_li.addClass('active');
				const dropoff_data = closest_li.data();

				for (let name in dropoff_data) {
					$('#' + field_name + '_' + name).val(dropoff_data[name]);
				}

				$('body').trigger('update_checkout');

				/* every pick may change the surcharge */
				updateDeliveryDayTabFee();
			});

			checkout_option
				.find('.postnl_checkout_tab_list .active .postnl_option')
				.trigger('click');
		},
	};

	postnl_fe_checkout.init();

} )( jQuery );
