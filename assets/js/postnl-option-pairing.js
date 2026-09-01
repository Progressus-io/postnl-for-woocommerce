/**
 * PostNL option pairing rule engine.
 *
 * Pure helpers for validating the option checkboxes in the Label & Tracking
 * widget against the allowed combinations exposed by the backend.
 *
 * Exposed as `window.postnl_option_pairing` for the admin meta box JS and as a
 * CommonJS module for Jest tests.
 */
( function ( root ) {
	'use strict';

	function arraysEqualAsSets( a, b ) {
		if ( a.length !== b.length ) {
			return false;
		}
		for ( var i = 0; i < a.length; i++ ) {
			if ( b.indexOf( a[ i ] ) === -1 ) {
				return false;
			}
		}
		return true;
	}

	function isSubset( subset, superset ) {
		for ( var i = 0; i < subset.length; i++ ) {
			if ( superset.indexOf( subset[ i ] ) === -1 ) {
				return false;
			}
		}
		return true;
	}

	function uniq( arr ) {
		var seen = {};
		var out  = [];
		for ( var i = 0; i < arr.length; i++ ) {
			if ( ! seen[ arr[ i ] ] ) {
				seen[ arr[ i ] ] = true;
				out.push( arr[ i ] );
			}
		}
		return out;
	}

	/**
	 * Decide whether adding `candidate` to `selected` is extendable to any
	 * allowed combination.
	 *
	 * @param {string[]}   selected      Currently checked option flags.
	 * @param {string}     candidate     Flag the user wants to add.
	 * @param {string[][]} combinations  Allowed combinations for the active route/feature.
	 *
	 * @return {boolean}
	 */
	function canEnable( selected, candidate, combinations ) {
		var probe = selected.concat( [ candidate ] );
		for ( var i = 0; i < combinations.length; i++ ) {
			if ( isSubset( probe, combinations[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compute which already-selected flags prevent `candidate` from being valid.
	 *
	 * Returned list is the minimal-by-frequency set of currently-selected flags
	 * that are absent from every combination containing `candidate`; if no
	 * combination contains the candidate at all, returns every selected flag.
	 *
	 * @param {string[]}   selected
	 * @param {string}     candidate
	 * @param {string[][]} combinations
	 *
	 * @return {string[]}
	 */
	function getBlockers( selected, candidate, combinations ) {
		var containingCandidate = combinations.filter( function ( combo ) {
			return combo.indexOf( candidate ) !== -1;
		} );

		if ( ! containingCandidate.length ) {
			return selected.slice();
		}

		var blockers = [];
		for ( var i = 0; i < selected.length; i++ ) {
			var flag = selected[ i ];
			var inAll = containingCandidate.every( function ( combo ) {
				return combo.indexOf( flag ) !== -1;
			} );
			if ( ! inAll ) {
				blockers.push( flag );
			}
		}
		return blockers;
	}

	/**
	 * Compute the additional flags needed to complete the current selection.
	 *
	 * Picks the smallest allowed superset of `selected`; if `selected` is
	 * already a valid combination, returns an empty array.
	 *
	 * @param {string[]}   selected
	 * @param {string[][]} combinations
	 *
	 * @return {string[]}
	 */
	function getRequiredCompanions( selected, combinations ) {
		if ( ! selected.length ) {
			return [];
		}

		var exact = combinations.some( function ( combo ) {
			return arraysEqualAsSets( combo, selected );
		} );
		if ( exact ) {
			return [];
		}

		var supersets = combinations.filter( function ( combo ) {
			return isSubset( selected, combo );
		} );

		if ( ! supersets.length ) {
			return [];
		}

		supersets.sort( function ( a, b ) {
			return a.length - b.length;
		} );

		var smallest = supersets[ 0 ];
		var missing  = [];
		for ( var i = 0; i < smallest.length; i++ ) {
			if ( selected.indexOf( smallest[ i ] ) === -1 ) {
				missing.push( smallest[ i ] );
			}
		}
		return missing;
	}

	/**
	 * Evaluate the full UI state for the current selection.
	 *
	 * @param {string[]}   selected      Currently checked option flags.
	 * @param {string[]}   allFlags      Every flag the UI exposes.
	 * @param {string[][]} combinations  Allowed combinations for the active route/feature.
	 *
	 * @return {{disabled: Object<string, string[]>, missing: string[], isComplete: boolean}}
	 *   disabled: map of unchecked-flag -> blocker flags causing it to be disabled.
	 *   missing:  flags needed to complete the current selection (if any).
	 *   isComplete: true when selected matches an allowed combination exactly.
	 */
	function evaluate( selected, allFlags, combinations ) {
		selected = uniq( selected );

		var disabled = {};
		for ( var i = 0; i < allFlags.length; i++ ) {
			var flag = allFlags[ i ];
			if ( selected.indexOf( flag ) !== -1 ) {
				continue;
			}
			if ( ! canEnable( selected, flag, combinations ) ) {
				disabled[ flag ] = getBlockers( selected, flag, combinations );
			}
		}

		var missing    = getRequiredCompanions( selected, combinations );
		var isComplete = selected.length === 0 || ( missing.length === 0 && combinations.some( function ( c ) {
			return arraysEqualAsSets( c, selected );
		} ) );

		return {
			disabled: disabled,
			missing: missing,
			isComplete: isComplete,
		};
	}

	var api = {
		evaluate: evaluate,
		canEnable: canEnable,
		getBlockers: getBlockers,
		getRequiredCompanions: getRequiredCompanions,
	};

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
	}
	if ( root ) {
		root.postnl_option_pairing = api;
	}
} )( typeof window !== 'undefined' ? window : null );
