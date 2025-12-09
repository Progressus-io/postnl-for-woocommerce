(function ($) {

    //Force to select Track & Trace
    const insured_shipping = $('#postnl_insured_shipping');
    const insured_plus = $('#postnl_insured_plus');
    const track_and_trace = $('#postnl_track_and_trace');

    function update_track_trace_status() {
        if (insured_shipping.is(':checked') || insured_plus.is(':checked')) {
            track_and_trace.prop('checked', true);
        }
    }


    insured_shipping.on('change', update_track_trace_status);
    insured_plus.on('change', update_track_trace_status);
    track_and_trace.on('change', update_track_trace_status);

    // Run the function once at the beginning to set the correct initial state
    update_track_trace_status();

    // Force to select Insured Shipping if Delivery Code at Door is selected
    const delivery_code_at_door = $('#postnl_delivery_code_at_door');

    function update_insured_shipping_status() {
        if (delivery_code_at_door.is(':checked')) {
            insured_shipping.prop('checked', true);
        }
    }
    
    delivery_code_at_door.on('change', update_insured_shipping_status);
    insured_shipping.on('change', update_insured_shipping_status);

    // Run the function once at the beginning to set the correct initial state
    update_insured_shipping_status();


})(jQuery);