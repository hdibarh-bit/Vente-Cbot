/* Arvator : menu mobile et onglets de résultats. Pas de dépendance. */
( function () {
	'use strict';

	document.documentElement.classList.add( 'js' );

	var toggle = document.querySelector( '.nav-toggle' );
	var nav = document.getElementById( 'menu-principal' );
	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && nav.classList.contains( 'is-open' ) ) {
				nav.classList.remove( 'is-open' );
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.focus();
			}
		} );
	}

	// Bouton « Copier » (clé de licence).
	document.querySelectorAll( '[data-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = document.querySelector( btn.getAttribute( 'data-copy' ) );
			if ( ! target ) { return; }
			target.focus();
			target.select();
			var done = function () {
				var old = btn.textContent;
				btn.textContent = 'Copié ✓';
				setTimeout( function () { btn.textContent = old; }, 1800 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( target.value ).then( done, function () { document.execCommand( 'copy' ); done(); } );
			} else {
				document.execCommand( 'copy' );
				done();
			}
		} );
	} );

	document.querySelectorAll( '[data-tabs]' ).forEach( function ( root ) {
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		var panels = tabs.map( function ( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		} );

		function select( index, focus ) {
			tabs.forEach( function ( tab, i ) {
				var on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', on ? '0' : '-1' );
				if ( panels[ i ] ) {
					panels[ i ].hidden = ! on;
				}
			} );
			if ( focus ) {
				tabs[ index ].focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () { select( i, false ); } );
			tab.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowRight' === e.key ) { e.preventDefault(); select( ( i + 1 ) % tabs.length, true ); }
				if ( 'ArrowLeft' === e.key ) { e.preventDefault(); select( ( i - 1 + tabs.length ) % tabs.length, true ); }
			} );
		} );

		select( 0, false );
	} );
}() );
