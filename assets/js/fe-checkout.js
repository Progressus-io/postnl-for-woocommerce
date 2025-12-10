( function( $ ) {
	/*
	* Helper – refresh the "Delivery Day" tab title with base-fee
	* */
	function updateDeliveryDayTabFee() {
		const $input = $('.postnl_checkout_tab_list .postnl_option[value="delivery_day"]');
		const $label = $input.closest('label');
		if (!$label.length) {
			return;
		}

		const tabBase = parseFloat($label.data('base-fee') || 0);
		let extraFee = 0;
		let extraFeeFormatted = '';

		if ($input.is(':checked')) {
			extraFee = parseFloat($('#postnl_delivery_day_price').val() || 0);
			if (isNaN(extraFee)) {
				extraFee = 0;
			}
			// Get formatted price from selected delivery option
			const $selected = $('.postnl_sub_radio:checked').closest('li');
			if ($selected.length) {
				extraFeeFormatted = $selected.data('price-formatted') || '';
			}
		}

		let text = postnlParams.i18n.deliveryDays;
		const fees = [];

		// Add base fee if > 0
		if (tabBase > 0 && postnlParams.delivery_day_fee_formatted) {
			fees.push(postnlParams.delivery_day_fee_formatted);
		}

		if (extraFee > 0 && extraFeeFormatted) {
			fees.push(extraFeeFormatted);
		}

		if (fees.length > 0) {
			text += ' (+' + fees.join(' +') + ')';
		}

		$label.children('span').first().text(text);
	}

	/*
	* Helper – refresh the "Pickup" tab title with base-fee.
	*/
	function updatePickupTabFee() {
		const $input = $('.postnl_checkout_tab_list .postnl_option[value="dropoff_points"]');
		const $label = $input.closest('label');
		if (!$label.length) {
			return;
		}

		const tabBase = parseFloat($label.data('base-fee') || 0);

		let text = postnlParams.i18n.pickup;

		// Add base fee if > 0.
		if (tabBase > 0 && postnlParams.pickup_fee_formatted) {
			text += ' (+' + postnlParams.pickup_fee_formatted + ')';
		}

		$label.children('span').first().text(text);
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

		isAddressReady: function () {
			return !!($('#shipping_postcode').val() || $('#billing_postcode').val());
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

			if (!window.__postnlFirstRefreshDone && postnl_fe_checkout.isAddressReady()) {
				window.__postnlFirstRefreshDone = true;
				$('body').trigger('update_checkout');
			}

			updateDeliveryDayTabFee();
			updatePickupTabFee();

			radio_tabs.on('change', function () {
				const pri_container = $(this).closest('#postnl_checkout_option');
				const ul_list = $(this).closest('.postnl_checkout_tab_list');
				const current_val = $(this).val();

				ul_list.children('li').removeClass('active');
				$(this).closest('li').addClass('active');

				pri_container.find('.postnl_checkout_content_container .postnl_content').removeClass('active');
				const content = pri_container.find('#postnl_' + current_val + '_content').addClass('active');

				let $checked = content.find('.postnl_sub_radio:checked');
				if (!$checked.length) {
					$checked = content.find('.postnl_sub_radio').first();
					if ($checked.length) {
						$checked.prop('checked', true);
					}

					if ($checked.length) {
						$checked.prop('checked', true).trigger('change');
						return;
					}

				}

				$checked.trigger('change');

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

			});

			checkout_option
				.find('.postnl_checkout_tab_list .active .postnl_option')
				.trigger('click');
		},
	};

	postnl_fe_checkout.init();

} )( jQuery );
