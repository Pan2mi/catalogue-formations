/**
 * Catalogue : filtres instantanés (sans rechargement).
 * Sans JavaScript, le formulaire GET et le bouton « Filtrer » restent utilisables.
 */
( function () {
	'use strict';
	var L = window.ifCatalogue || { one: '%d notion', many: '%d notions' };
	var KEYS = [ 'gniveau', 'niveau', 'gmatiere', 'matiere' ];

	function init( form ) {
		var root = form.parentNode;
		var results = root.querySelector( '.if-cat-results' );
		if ( ! results ) {
			return;
		}
		var count = root.querySelector( '.if-cat-count' );
		var empty = root.querySelector( '.if-cat-empty' );
		var reset = form.querySelector( '.if-filters__reset' );
		var sel = {};
		var all = {}; // Options d'origine des listes Niveau / Matière.
		KEYS.forEach( function ( k ) {
			sel[ k ] = form.querySelector( 'select[name="' + k + '"]' );
			if ( sel[ k ] && ( 'niveau' === k || 'matiere' === k ) ) {
				all[ k ] = Array.prototype.slice.call( sel[ k ].options ).map( function ( o ) {
					return { value: o.value, text: o.text, groups: ( o.getAttribute( 'data-groups' ) || '' ).split( ' ' ).filter( Boolean ) };
				} );
			}
		} );
		form.classList.add( 'if-filters--live' );

		function val( k ) {
			return sel[ k ] ? sel[ k ].value : '';
		}

		/** Restreint Niveau (ou Matière) aux éléments du groupe choisi. */
		function narrow( k, gk ) {
			if ( ! sel[ k ] || ! all[ k ] ) {
				return;
			}
			var g = val( gk );
			var current = sel[ k ].value;
			var keep = all[ k ].filter( function ( o ) {
				return '' === o.value || ! g || -1 !== o.groups.indexOf( g );
			} );
			sel[ k ].innerHTML = '';
			keep.forEach( function ( o ) {
				var opt = document.createElement( 'option' );
				opt.value = o.value;
				opt.textContent = o.text;
				if ( o.groups.length ) {
					opt.setAttribute( 'data-groups', o.groups.join( ' ' ) );
				}
				sel[ k ].appendChild( opt );
			} );
			sel[ k ].value = keep.some( function ( o ) { return o.value === current; } ) ? current : '';
		}

		function has( list, v ) {
			return -1 !== ( list || '' ).split( ' ' ).indexOf( v );
		}

		function matches( card, f ) {
			if ( card.hasAttribute( 'data-other' ) ) {
				return ! ( f.niveau || f.gniveau || f.matiere || f.gmatiere );
			}
			var d = card.dataset;
			return ( ! f.niveau || d.niveau === f.niveau ) &&
				( ! f.matiere || d.matiere === f.matiere ) &&
				( ! f.gniveau || has( d.gniveau, f.gniveau ) ) &&
				( ! f.gmatiere || has( d.gmatiere, f.gmatiere ) );
		}

		function apply() {
			narrow( 'niveau', 'gniveau' );
			narrow( 'matiere', 'gmatiere' );
			var f = {};
			KEYS.forEach( function ( k ) {
				f[ k ] = val( k );
			} );
			var ids = {};
			Array.prototype.forEach.call( results.querySelectorAll( '.if-card' ), function ( c ) {
				var ok = matches( c, f );
				c.hidden = ! ok;
				if ( ok ) {
					ids[ c.getAttribute( 'data-id' ) ] = true;
				}
			} );
			// Sections et titres sans notion visible : masqués.
			Array.prototype.forEach.call( results.querySelectorAll( '.if-cat-block' ), function ( b ) {
				b.hidden = ! b.querySelector( '.if-card:not([hidden])' );
			} );
			Array.prototype.forEach.call( results.querySelectorAll( '.if-cat-niveau' ), function ( s ) {
				s.hidden = ! s.querySelector( '.if-cat-block:not([hidden])' );
			} );
			var n = Object.keys( ids ).length;
			var txt = ( n > 1 ? L.many : L.one ).replace( '%d', n );
			if ( count && count.textContent !== txt ) {
				count.textContent = txt; // annoncé par les lecteurs d'écran (aria-live).
			}
			if ( empty ) {
				empty.hidden = n > 0;
			}
			var active = KEYS.some( function ( k ) { return f[ k ]; } );
			if ( reset ) {
				reset.hidden = ! active;
			}
			// Adresse à jour : un rechargement ou un lien partagé garde les filtres.
			if ( window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				KEYS.forEach( function ( k ) {
					if ( sel[ k ] && f[ k ] ) {
						url.searchParams.set( k, f[ k ] );
					} else if ( sel[ k ] ) {
						url.searchParams.delete( k );
					}
				} );
				window.history.replaceState( null, '', url.toString() );
			}
		}

		KEYS.forEach( function ( k ) {
			if ( sel[ k ] ) {
				sel[ k ].addEventListener( 'change', function () {
					apply();
				} );
			}
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			apply();
		} );
		if ( reset ) {
			reset.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				KEYS.forEach( function ( k ) {
					if ( sel[ k ] ) {
						sel[ k ].value = '';
					}
				} );
				apply();
				var first = KEYS.map( function ( k ) { return sel[ k ]; } ).filter( Boolean )[ 0 ];
				if ( first ) {
					first.focus();
				}
			} );
		}
		apply();
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form[data-if-filters]' ), init );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
