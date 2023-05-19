( function( $ ) {

    //Force to select Track & Trace
    const insured_shipping = $('#postnl_insured_shipping');
    const insuredPlus = $('#postnl_insured_plus');
    const track_and_trace = $('#postnl_track_and_trace');

    function update_track_trace_status() {
        if (insured_shipping.is(':checked') || insuredPlus.is(':checked')) {
            track_and_trace.prop('checked', true);
        }
    }

    function track_and_traceChangeHandler() {
        if (insured_shipping.is(':checked') || insuredPlus.is(':checked')) {
            track_and_trace.prop('checked', true);
        }
    }

    insured_shipping.on('change', update_track_trace_status);
    insuredPlus.on('change', update_track_trace_status);
    track_and_trace.on('change', track_and_traceChangeHandler);

    // Run the function once at the beginning to set the correct initial state
    update_track_trace_status();

} )( jQuery );

