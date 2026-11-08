/*!
 * ANTOINE CODEX Immersive — compteur de visiteurs.
 * Envoie un signal de visite (sans cookie) puis met à jour le compteur et le graphique
 * avec les chiffres frais, même si la page vient du cache.
 */
( function () {
	'use strict';

	var cfg = window.ACXVisits;
	if ( ! cfg || ! window.fetch ) {
		return;
	}
	var d = document;
	var section = d.querySelector( '[data-acx-visitors]' );
	if ( ! cfg.count && ! section ) {
		return;
	}
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var fmt = function ( n ) {
		try {
			return new Intl.NumberFormat( cfg.locale ).format( n );
		} catch ( e ) {
			return String( n );
		}
	};

	function countUp( el, to ) {
		var from = parseInt( ( el.textContent || '0' ).replace( /\D/g, '' ), 10 ) || 0;
		if ( reduce || from === to ) {
			el.textContent = fmt( to );
			return;
		}
		var start = null;
		var dur = 900;
		function step( ts ) {
			start = start || ts;
			var p = Math.min( 1, ( ts - start ) / dur );
			var e = 1 - Math.pow( 1 - p, 3 );
			el.textContent = fmt( Math.round( from + ( to - from ) * e ) );
			if ( p < 1 ) {
				requestAnimationFrame( step );
			}
		}
		requestAnimationFrame( step );
	}

	function row( label, flag, value, max, tip, code ) {
		var li = d.createElement( 'li' );
		li.className = 'acx-geo-chart__row' + ( code ? '' : ' acx-geo-chart__row--other' );
		if ( code ) {
			li.setAttribute( 'data-code', code );
			li.tabIndex = 0;
			li.setAttribute( 'aria-label', tip );
		}
		var lab = d.createElement( 'span' );
		lab.className = 'acx-geo-chart__label';
		var f;
		if ( flag ) {
			f = d.createElement( 'img' );
			f.className = 'acx-geo-chart__flag';
			f.src = flag;
			f.width = 24;
			f.height = 16;
			f.alt = '';
			f.loading = 'lazy';
		} else {
			f = d.createElement( 'span' );
			f.className = 'acx-geo-chart__flag acx-geo-chart__flag--none';
			f.setAttribute( 'aria-hidden', 'true' );
		}
		var name = d.createElement( 'span' );
		name.className = 'acx-geo-chart__name';
		name.textContent = label;
		lab.appendChild( f );
		lab.appendChild( name );
		var track = d.createElement( 'span' );
		track.className = 'acx-geo-chart__track';
		track.setAttribute( 'aria-hidden', 'true' );
		var bar = d.createElement( 'span' );
		bar.className = 'acx-geo-chart__bar';
		bar.style.setProperty( '--w', ( 100 * value / max ).toFixed( 2 ) + '%' );
		track.appendChild( bar );
		var val = d.createElement( 'span' );
		val.className = 'acx-geo-chart__value';
		val.textContent = fmt( value );
		li.appendChild( lab );
		li.appendChild( track );
		li.appendChild( val );
		if ( code ) {
			var t = d.createElement( 'span' );
			t.className = 'acx-geo-chart__tip';
			t.setAttribute( 'role', 'tooltip' );
			t.textContent = tip;
			li.appendChild( t );
		}
		return li;
	}

	function render( s ) {
		if ( ! section || ! s ) {
			return;
		}
		Array.prototype.forEach.call( section.querySelectorAll( '[data-stat]' ), function ( el ) {
			var k = el.getAttribute( 'data-stat' );
			if ( typeof s[ k ] === 'number' ) {
				countUp( el, s[ k ] );
			}
		} );
		var wrap = section.querySelector( '[data-geo-chart-wrap]' );
		if ( ! wrap || ! s.countries || ! s.countries.length ) {
			return;
		}
		var max = Math.max.apply( null, s.countries.map( function ( c ) {
			return c.visitors;
		} ).concat( [ s.other || 0, 1 ] ) );
		var ol = d.createElement( 'ol' );
		ol.className = 'acx-geo-chart acx-geo-chart--public is-in';
		ol.setAttribute( 'data-geo-chart', '' );
		s.countries.forEach( function ( c ) {
			var tip = cfg.tip.replace( '%1$s', c.name ).replace( '%2$s', fmt( c.visitors ) ).replace( '%3$s', fmt( c.share ) ).replace( '%%', '%' );
			ol.appendChild( row( c.name, c.flag, c.visitors, max, tip, c.code ) );
		} );
		if ( s.other > 0 ) {
			ol.appendChild( row( cfg.other, '', s.other, max, '', '' ) );
		}
		wrap.innerHTML = '';
		wrap.appendChild( ol );
	}

	// Les barres « poussent » quand le graphique entre à l'écran.
	if ( section && 'IntersectionObserver' in window && ! reduce ) {
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) {
					Array.prototype.forEach.call( section.querySelectorAll( '[data-geo-chart]' ), function ( c ) {
						c.classList.add( 'is-in' );
					} );
					io.disconnect();
				}
			} );
		}, { threshold: 0.2 } );
		io.observe( section );
	} else if ( section ) {
		Array.prototype.forEach.call( section.querySelectorAll( '[data-geo-chart]' ), function ( c ) {
			c.classList.add( 'is-in' );
		} );
	}

	function send() {
		var headers = { 'Content-Type': 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}
		var req = cfg.count
			? fetch( cfg.url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: headers, body: '{}' } )
			: fetch( cfg.url.replace( /\/visit$/, '/visitors' ), { credentials: 'same-origin', headers: headers } );
		req.then( function ( r ) {
			return r.ok ? r.json() : null;
		} ).then( function ( data ) {
			if ( data ) {
				render( cfg.count ? data.stats : data );
			}
		} ).catch( function () {} );
	}

	// Pas de comptage pour les pages préchargées en arrière-plan.
	if ( d.visibilityState === 'prerender' ) {
		d.addEventListener( 'visibilitychange', function once() {
			if ( d.visibilityState === 'visible' ) {
				d.removeEventListener( 'visibilitychange', once );
				send();
			}
		} );
	} else {
		send();
	}
} )();
