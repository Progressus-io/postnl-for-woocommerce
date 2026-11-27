/**
 * PostNL settings page tests (assets/js/admin-settings.js).
 *
 * The script is loaded against a real jsdom form through a small jQuery stand-in
 * that implements the calls it makes, so the visibility of the return address
 * rows is asserted on actual table rows.
 */

/**
 * Wrap a list of DOM nodes in the jQuery calls admin-settings.js uses.
 *
 * @param {Element[]} nodes Matched nodes.
 * @return {Object} jQuery-like collection.
 */
function wrap( nodes ) {
	const collection = {
		nodes,
		length: nodes.length,
		add: ( other ) =>
			wrap( [
				...nodes,
				...( other && other.nodes ? other.nodes : [] ),
			] ),
		on: () => collection,
		trigger: () => collection,
		each: ( fn ) => {
			nodes.forEach( ( node, index ) => fn.call( node, index, node ) );
			return collection;
		},
		is: ( selector ) =>
			nodes.some( ( node ) =>
				':checked' === selector
					? !! node.checked
					: node.matches( selector )
			),
		closest: ( selector ) =>
			wrap(
				nodes
					.map( ( node ) => node.closest( selector ) )
					.filter( Boolean )
			),
		find: ( selector ) =>
			wrap(
				nodes.flatMap( ( node ) => [
					...node.querySelectorAll( selector ),
				] )
			),
		hide: () => {
			nodes.forEach( ( node ) => ( node.style.display = 'none' ) );
			return collection;
		},
		show: () => {
			nodes.forEach( ( node ) => ( node.style.display = '' ) );
			return collection;
		},
		val: ( value ) => {
			if ( undefined === value ) {
				return nodes[ 0 ] ? nodes[ 0 ].value : undefined;
			}
			nodes.forEach( ( node ) => ( node.value = value ) );
			return collection;
		},
		prop: ( name ) => ( nodes[ 0 ] ? nodes[ 0 ][ name ] : undefined ),
		attr: () => collection,
		data: () => undefined,
		text: () => collection,
		html: () => collection,
		css: () => collection,
		empty: () => collection,
		append: () => collection,
		remove: () => collection,
	};

	nodes.forEach( ( node, index ) => ( collection[ index ] = node ) );

	return collection;
}

/**
 * Minimal jQuery stand-in backed by the jsdom document.
 *
 * @param {*} selector Selector, node, document or ready callback.
 * @return {Object} jQuery-like collection.
 */
function jQueryStub( selector ) {
	if ( 'function' === typeof selector ) {
		selector( jQueryStub );
		return wrap( [] );
	}
	if ( 'string' === typeof selector ) {
		return wrap( [ ...document.querySelectorAll( selector ) ] );
	}
	return wrap( selector ? [ selector ] : [] );
}
jQueryStub.trim = ( value ) => String( value ).trim();
jQueryStub.post = () => ( {
	done: () => ( {} ),
	fail: () => ( {} ),
	always: () => ( {} ),
} );

/**
 * Build the return settings rows of the PostNL settings form.
 *
 * @param {Object}  options             Form shape.
 * @param {boolean} options.checkbox    Whether "Return to home address" is rendered.
 * @param {boolean} options.checked     Whether it is ticked.
 * @param {boolean} options.replyNumber Whether the reply number and freepost rows are rendered.
 */
function renderForm( { checkbox, checked = false, replyNumber } ) {
	const ids = [
		'return_address_street',
		'return_address_house_no',
		'return_address_house_noext',
		'return_address_zip',
		'return_address_city',
	];
	if ( replyNumber ) {
		ids.push( 'return_replynumber', 'freepost_zip', 'freepost_city' );
	}

	const rows = ids
		.map(
			( id ) =>
				`<tr><td><input type="text" id="woocommerce_postnl_${ id }"></td></tr>`
		)
		.join( '' );
	const toggle = checkbox
		? `<tr><td><input type="checkbox" id="woocommerce_postnl_return_address_or_reply_no" ${
				checked ? 'checked' : ''
		  }></td></tr>`
		: '';

	const printerType =
		'<tr><td><select id="woocommerce_postnl_printer_type"><option value="PDF" selected>PDF</option></select></td></tr>';

	document.body.innerHTML = `<table>${ printerType }${ toggle }${ rows }</table>`;
}

/**
 * Run admin-settings.js against the current document.
 */
function loadScript() {
	jest.isolateModules( () => {
		require( '../../assets/js/admin-settings.js' );
	} );
}

/**
 * Whether the row holding a settings field is visible.
 *
 * @param {string} id Field id without the woocommerce_postnl_ prefix.
 * @return {boolean} True when the row is shown.
 */
function rowVisible( id ) {
	return (
		'none' !==
		document.querySelector( `#woocommerce_postnl_${ id }` ).closest( 'tr' )
			.style.display
	);
}

describe( 'PostNL settings: return address rows', () => {
	beforeEach( () => {
		global.jQuery = jQueryStub;
	} );

	afterEach( () => {
		delete global.jQuery;
		document.body.innerHTML = '';
	} );

	it( 'shows the home return address when the "Return to home address" choice is not rendered (BE store on V4)', () => {
		renderForm( { checkbox: false, replyNumber: false } );

		loadScript();

		[
			'return_address_street',
			'return_address_house_no',
			'return_address_house_noext',
			'return_address_zip',
			'return_address_city',
		].forEach( ( id ) => expect( rowVisible( id ) ).toBe( true ) );
	} );

	it( 'shows the reply number and hides the home address when the box is unticked', () => {
		renderForm( { checkbox: true, checked: false, replyNumber: true } );

		loadScript();

		expect( rowVisible( 'return_replynumber' ) ).toBe( true );
		expect( rowVisible( 'freepost_zip' ) ).toBe( true );
		expect( rowVisible( 'return_address_street' ) ).toBe( false );
		expect( rowVisible( 'return_address_zip' ) ).toBe( false );
	} );

	it( 'shows the home address and hides the reply number when the box is ticked', () => {
		renderForm( { checkbox: true, checked: true, replyNumber: true } );

		loadScript();

		expect( rowVisible( 'return_address_street' ) ).toBe( true );
		expect( rowVisible( 'return_address_city' ) ).toBe( true );
		expect( rowVisible( 'return_replynumber' ) ).toBe( false );
		expect( rowVisible( 'freepost_city' ) ).toBe( false );
	} );
} );
