( function () {
	'use strict';

	// Set the pdf.js worker URL from the localized PHP data.
	if ( typeof postnlPrintLabelData !== 'undefined' && postnlPrintLabelData.workerSrc ) {
		pdfjsLib.GlobalWorkerOptions.workerSrc = postnlPrintLabelData.workerSrc;
	}

	/**
	 * Fetch a PDF from `url`, render every page to a canvas, then open a print window.
	 *
	 * @param {string} url Absolute URL to the PDF label.
	 */
	function postnlPrintLabel( url ) {
		pdfjsLib.getDocument( url ).promise
			.then( function ( pdf ) {
				var renderPromises = [];

				for ( var i = 1; i <= pdf.numPages; i++ ) {
					renderPromises.push(
						pdf.getPage( i ).then( function ( page ) {
							var viewport      = page.getViewport( { scale: 2 } );
							var canvas        = document.createElement( 'canvas' );
							canvas.width      = viewport.width;
							canvas.height     = viewport.height;

							return page.render( {
								canvasContext: canvas.getContext( '2d' ),
								viewport:      viewport,
							} ).promise.then( function () {
								return canvas.toDataURL( 'image/png' );
							} );
						} )
					);
				}

				return Promise.all( renderPromises );
			} )
			.then( function ( dataUrls ) {
				var imgs = dataUrls.map( function ( src, i ) {
					return '<img src="' + src + '" style="width:100%;' +
						( i < dataUrls.length - 1 ? 'page-break-after:always;' : '' ) + '">';
				} ).join( '' );

				var html = '<!DOCTYPE html><html><head><title>Print Label</title>'
					+ '<style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#fff;}img{display:block;width:100%;}'
					+ '@media print{img{page-break-after:always;}}</style>'
					+ '</head><body>' + imgs + '</body></html>';

				var printWindow = window.open( '', '_blank' );
				printWindow.document.open();
				printWindow.document.write( html );
				printWindow.document.close();

				printWindow.onload = function () {
					printWindow.focus();
					printWindow.print();
				};
			} )
			.catch( function ( err ) {
				console.error( 'PostNL print failed:', err );
			} );
	}

	// Event delegation — works for buttons rendered at any time (single order & orders list).
	document.addEventListener( 'click', function ( e ) {
		// Matches the meta-box print button (data-label-url) and the WC action icon (.postnl-action-print-label).
		var btn = e.target.closest( '.button-print-label, .postnl-action-print-label' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var url = btn.getAttribute( 'data-label-url' ) || btn.getAttribute( 'href' );
		if ( url ) {
			postnlPrintLabel( url );
		}
	} );

	// Expose globally for programmatic use.
	window.postnlPrintLabel = postnlPrintLabel;
} )();
