/* Arvator : menu mobile, onglets de résultats, bouton « Copier », bandeau cookies. Pas de dépendance. */
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

	// Bandeau de consentement aux cookies : choix retenu 6 mois dans « arv_consent » (v1-r1 accepté, v1-r0 refusé).
	var consent = document.getElementById( 'consent' );
	if ( consent ) {
		var refBox = document.getElementById( 'consent-ref' );
		var prefs = document.getElementById( 'consent-prefs' );
		var btn = function ( name ) { return consent.querySelector( '[data-consent="' + name + '"]' ); };
		var current = function () {
			var m = document.cookie.match( /(?:^|;\s*)arv_consent=(v1-r[01])(?:;|$)/ );
			return m ? m[1] : '';
		};
		var showPrefs = function ( on ) {
			prefs.hidden = ! on;
			btn( 'custom' ).hidden = on;
			btn( 'save' ).hidden = ! on;
			btn( 'custom' ).setAttribute( 'aria-expanded', on ? 'true' : 'false' );
		};
		var open = function ( withPrefs ) {
			refBox.checked = 'v1-r1' === current();
			showPrefs( !! withPrefs );
			consent.hidden = false;
			btn( withPrefs ? 'save' : 'accept' ).focus();
		};
		var save = function ( accepted ) {
			var before = current();
			document.cookie = 'arv_consent=v1-r' + ( accepted ? '1' : '0' ) + '; Max-Age=' + ( 182 * 86400 ) + '; Path=/; SameSite=Lax' + ( 'https:' === location.protocol ? '; Secure' : '' );
			consent.hidden = true;
			// Le site applique le choix côté serveur : mémoriser le code d'un lien de parrain, ou effacer le cookie retiré.
			if ( ( accepted && /[?&]ref=/.test( location.search ) ) || ( ! accepted && 'v1-r1' === before ) ) {
				location.reload();
			}
		};
		btn( 'accept' ).addEventListener( 'click', function () { save( true ); } );
		btn( 'refuse' ).addEventListener( 'click', function () { save( false ); } );
		btn( 'custom' ).addEventListener( 'click', function () { showPrefs( true ); refBox.focus(); } );
		btn( 'save' ).addEventListener( 'click', function () { save( refBox.checked ); } );
		document.querySelectorAll( '[data-consent-open]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () { open( true ); } );
		} );
		if ( '' === current() ) {
			open( false );
		}
	}

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
