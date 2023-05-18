( function( $ ) {

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

