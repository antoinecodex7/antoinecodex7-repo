/*!
 * ANTOINE CODEX Immersive — interactions principales.
 * Vanilla JS, sans dépendance. Tout est facultatif : sans JavaScript, le site reste complet.
 */
( function () {
	'use strict';

	window.ACXReady = true;

	var d = document;
	var root = d.documentElement;
	var cfg = window.ACX || {};
	var reduceMq = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var fineMq = window.matchMedia( '(hover: hover) and (pointer: fine)' );
	var hasIO = 'IntersectionObserver' in window;

	function reduced() {
		return reduceMq.matches;
	}

	function clamp( v, a, b ) {
		return Math.min( b, Math.max( a, v ) );
	}

	function each( sel, fn, ctx ) {
		Array.prototype.forEach.call( ( ctx || d ).querySelectorAll( sel ), fn );
	}

	/* ---------------------------------------------------------------------
	 * 1. Découpe des titres en mots (révélation mot à mot).
	 * ------------------------------------------------------------------- */
	function splitWords( el, cls, inner ) {
		var index = 0;
		( function walk( node ) {
			Array.prototype.slice.call( node.childNodes ).forEach( function ( child ) {
				if ( child.nodeType === 3 ) {
					var parts = child.textContent.split( /(\s+)/ );
					var frag = d.createDocumentFragment();
					parts.forEach( function ( part ) {
						if ( ! part ) {
							return;
						}
						if ( /^\s+$/.test( part ) ) {
							frag.appendChild( d.createTextNode( ' ' ) );
							return;
						}
						var w = d.createElement( 'span' );
						w.className = cls;
						if ( inner ) {
							var wi = d.createElement( 'span' );
							wi.className = inner;
							wi.style.setProperty( '--i', index );
							wi.textContent = part;
							w.appendChild( wi );
						} else {
							w.style.setProperty( '--i', index );
							w.textContent = part;
						}
						index++;
						frag.appendChild( w );
					} );
					node.replaceChild( frag, child );
				} else if ( child.nodeType === 1 ) {
					walk( child );
				}
			} );
		} )( el );
		return index;
	}

	each( '[data-split]', function ( el ) {
		splitWords( el, 'w', 'wi' );
		if ( ! el.hasAttribute( 'data-reveal' ) ) {
			el.setAttribute( 'data-reveal-split', '' );
		}
	} );

	/* ---------------------------------------------------------------------
	 * 2. Révélations au défilement.
	 * ------------------------------------------------------------------- */
	var revealEls = d.querySelectorAll( '[data-reveal], [data-reveal-split], .method-step' );

	// Décalage automatique entre éléments frères.
	each( '[data-reveal]', function ( el ) {
		if ( el.style.getPropertyValue( '--delay' ) ) {
			return;
		}
		var i = 0;
		var sib = el.previousElementSibling;
		while ( sib ) {
			if ( sib.hasAttribute( 'data-reveal' ) ) {
				i++;
			}
			sib = sib.previousElementSibling;
		}
		el.style.setProperty( '--delay', Math.min( i, 6 ) * 90 );
	} );

	function revealAll() {
		Array.prototype.forEach.call( revealEls, function ( el ) {
			el.classList.add( 'is-in' );
		} );
	}

	if ( ! hasIO || reduced() ) {
		revealAll();
	} else {
		var revealIO = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-in' );
						revealIO.unobserve( entry.target );
					}
				} );
			},
			{ rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
		);
		Array.prototype.forEach.call( revealEls, function ( el ) {
			revealIO.observe( el );
		} );
	}

	// Ouverture : lancement immédiat.
	var hero = d.querySelector( '.hero, .page-hero' );
	window.requestAnimationFrame( function () {
		each( '.hero', function ( h ) {
			h.classList.add( 'is-in' );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * 3. Manifeste : mots révélés selon le défilement.
	 * ------------------------------------------------------------------- */
	var scrubs = [];
	each( '[data-scrub-words]', function ( el ) {
		var count = splitWords( el, 'sw' );
		var words = el.querySelectorAll( '.sw' );
		// Mots-clés mis en valeur en or (ceux qui suivent « : » ou mots longs de fin de phrase).
		Array.prototype.forEach.call( words, function ( w, i ) {
			if ( /[.:]$/.test( w.textContent ) && w.textContent.length > 6 ) {
				w.classList.add( 'is-accent' );
			}
			if ( i === count - 1 ) {
				w.classList.add( 'is-accent' );
			}
		} );
		el.classList.add( 'is-scrub' );
		scrubs.push( { el: el, words: words, last: -1 } );
	} );

	/* ---------------------------------------------------------------------
	 * 4. Défilement : en-tête, progression, découpes, méthode.
	 * ------------------------------------------------------------------- */
	var header = d.querySelector( '[data-header]' );
	var progressEls = d.querySelectorAll( '[data-progress]' );
	var clipEls = d.querySelectorAll( '[data-clip]' );
	var readout = d.querySelector( '[data-hero-readout]' );
	var lastY = window.pageYOffset;
	var ticking = false;
	var state = window.ACXState = window.ACXState || { heroProgress: 0 };

	function onScroll() {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( update );
		}
	}

	function update() {
		ticking = false;
		var y = window.pageYOffset;
		var vh = window.innerHeight;
		var docH = Math.max( 1, root.scrollHeight - vh );
		root.style.setProperty( '--scroll', ( y / docH ).toFixed( 4 ) );

		if ( header ) {
			header.classList.toggle( 'is-scrolled', y > 24 );
			var navOpen = root.classList.contains( 'nav-open' );
			var hide = y > lastY && y > vh * 0.6 && ! navOpen && ! header.contains( d.activeElement );
			header.classList.toggle( 'is-hidden', hide );
		}
		lastY = y;

		if ( hero ) {
			var hr = hero.getBoundingClientRect();
			state.heroProgress = clamp( -hr.top / Math.max( 1, hr.height * 0.85 ), 0, 1 );
			if ( readout ) {
				var pct = Math.round( 35 + state.heroProgress * 65 );
				readout.textContent = 'STRUCT · ' + ( '00' + pct ).slice( -3 ) + '%';
			}
		}

		if ( reduced() ) {
			scrubs.forEach( function ( s ) {
				s.el.classList.remove( 'is-scrub' );
			} );
			return;
		}

		scrubs.forEach( function ( s ) {
			var r = s.el.getBoundingClientRect();
			if ( r.bottom < -vh || r.top > vh * 2 ) {
				return;
			}
			var p = clamp( ( vh * 0.88 - r.top ) / ( r.height + vh * 0.35 ), 0, 1 );
			var n = Math.round( p * s.words.length );
			if ( n !== s.last ) {
				for ( var i = 0; i < s.words.length; i++ ) {
					s.words[ i ].classList.toggle( 'is-on', i < n );
				}
				s.last = n;
			}
		} );

		Array.prototype.forEach.call( progressEls, function ( el ) {
			var r = el.getBoundingClientRect();
			var p = clamp( ( vh * 0.62 - r.top ) / Math.max( 1, r.height ), 0, 1 );
			el.style.setProperty( '--progress', p.toFixed( 3 ) );
		} );

		Array.prototype.forEach.call( clipEls, function ( el ) {
			var r = el.getBoundingClientRect();
			var c = clamp( ( r.top - vh * 0.08 ) / ( vh * 0.75 ), 0, 1 );
			el.style.setProperty( '--clip', c.toFixed( 3 ) );
		} );
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onScroll, { passive: true } );
	update();

	/* ---------------------------------------------------------------------
	 * 5. Pointeur : lumière du hero, cartes inclinées, boutons magnétiques.
	 * ------------------------------------------------------------------- */
	function pointerFx() {
		return cfg.pointerFx !== false && fineMq.matches && ! reduced();
	}

	state.pointer = { x: 0, y: 0, active: false };
	window.addEventListener(
		'pointermove',
		function ( e ) {
			state.pointer.x = ( e.clientX / window.innerWidth ) * 2 - 1;
			state.pointer.y = -( ( e.clientY / window.innerHeight ) * 2 - 1 );
			state.pointer.active = true;
		},
		{ passive: true }
	);
	d.addEventListener( 'pointerleave', function () {
		state.pointer.active = false;
	} );

	each( '[data-tilt]', function ( card ) {
		var raf = 0;
		card.addEventListener( 'pointermove', function ( e ) {
			if ( ! pointerFx() ) {
				return;
			}
			var r = card.getBoundingClientRect();
			var x = ( e.clientX - r.left ) / r.width;
			var y = ( e.clientY - r.top ) / r.height;
			cancelAnimationFrame( raf );
			raf = requestAnimationFrame( function () {
				card.style.setProperty( '--mx', ( x * 100 ).toFixed( 1 ) + '%' );
				card.style.setProperty( '--my', ( y * 100 ).toFixed( 1 ) + '%' );
				card.style.setProperty( '--ry', ( ( x - 0.5 ) * 5 ).toFixed( 2 ) + 'deg' );
				card.style.setProperty( '--rx', ( ( 0.5 - y ) * 5 ).toFixed( 2 ) + 'deg' );
			} );
		} );
		card.addEventListener( 'pointerleave', function () {
			card.style.setProperty( '--rx', '0deg' );
			card.style.setProperty( '--ry', '0deg' );
		} );
	} );

	each( '[data-magnetic]', function ( btn ) {
		btn.addEventListener( 'pointermove', function ( e ) {
			if ( ! pointerFx() ) {
				return;
			}
			var r = btn.getBoundingClientRect();
			var dx = e.clientX - ( r.left + r.width / 2 );
			var dy = e.clientY - ( r.top + r.height / 2 );
			btn.style.transform = 'translate(' + clamp( dx * 0.18, -8, 8 ).toFixed( 1 ) + 'px,' + clamp( dy * 0.3, -6, 6 ).toFixed( 1 ) + 'px)';
		} );
		btn.addEventListener( 'pointerleave', function () {
			btn.style.transform = '';
		} );
	} );

	/* ---------------------------------------------------------------------
	 * 6. Navigation mobile (plein écran, piège de focus, Échap).
	 * ------------------------------------------------------------------- */
	var toggle = d.querySelector( '[data-menu-toggle]' );
	var mnav = d.querySelector( '[data-mobile-nav]' );
	var toggleText = d.querySelector( '[data-menu-toggle-text]' );
	var i18n = cfg.i18n || {};

	function setInert( on ) {
		[ d.querySelector( '.site-main' ), d.querySelector( '.site-footer' ), d.querySelector( '.acx-core' ) ].forEach( function ( el ) {
			if ( ! el ) {
				return;
			}
			if ( on ) {
				el.setAttribute( 'inert', '' );
				el.setAttribute( 'aria-hidden', 'true' );
			} else {
				el.removeAttribute( 'inert' );
				el.removeAttribute( 'aria-hidden' );
			}
		} );
	}

	function openNav() {
		mnav.hidden = false;
		each( '.mobile-nav__list > li', function ( li, i ) {
			li.style.setProperty( '--i', i );
		}, mnav );
		// Forcer le calcul avant la transition.
		void mnav.offsetWidth;
		mnav.classList.add( 'is-open' );
		root.classList.add( 'nav-open' );
		d.body.classList.add( 'is-locked' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		if ( toggleText ) {
			toggleText.textContent = i18n.menuClose || 'Fermer le menu';
		}
		setInert( true );
		header.classList.remove( 'is-hidden' );
		var first = mnav.querySelector( 'a' );
		if ( first ) {
			setTimeout( function () {
				first.focus( { preventScroll: true } );
			}, 60 );
		}
	}

	function closeNav( returnFocus ) {
		mnav.classList.remove( 'is-open' );
		root.classList.remove( 'nav-open' );
		d.body.classList.remove( 'is-locked' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		if ( toggleText ) {
			toggleText.textContent = i18n.menuOpen || 'Ouvrir le menu';
		}
		setInert( false );
		var delay = reduced() ? 0 : 700;
		setTimeout( function () {
			if ( ! mnav.classList.contains( 'is-open' ) ) {
				mnav.hidden = true;
			}
		}, delay );
		if ( returnFocus ) {
			toggle.focus();
		}
	}

	if ( toggle && mnav ) {
		toggle.addEventListener( 'click', function () {
			if ( toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
				closeNav( false );
			} else {
				openNav();
			}
		} );

		mnav.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( 'a' );
			if ( a ) {
				closeNav( false );
			}
		} );

		d.addEventListener( 'keydown', function ( e ) {
			if ( ! mnav.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( e.key === 'Escape' ) {
				closeNav( true );
				return;
			}
			if ( e.key === 'Tab' ) {
				var focusables = [ toggle ].concat( Array.prototype.slice.call( mnav.querySelectorAll( 'a[href], button:not([disabled])' ) ) );
				var first = focusables[ 0 ];
				var last = focusables[ focusables.length - 1 ];
				if ( e.shiftKey && d.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && d.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );

		window.matchMedia( '(min-width: 1081px)' ).addEventListener( 'change', function ( mq ) {
			if ( mq.matches && mnav.classList.contains( 'is-open' ) ) {
				closeNav( false );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * 7. Bandeau défilant : pause hors écran.
	 * ------------------------------------------------------------------- */
	if ( hasIO ) {
		var pauseIO = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				entry.target.classList.toggle( 'is-paused', ! entry.isIntersecting );
			} );
		} );
		each( '[data-marquee]', function ( m ) {
			pauseIO.observe( m );
		} );
	}

	/* ---------------------------------------------------------------------
	 * 8. Champ de particules WebGL : chargé à la demande, jamais bloquant.
	 * ------------------------------------------------------------------- */
	var canvases = d.querySelectorAll( 'canvas[data-acx-field]' );

	function webglAvailable() {
		try {
			var c = d.createElement( 'canvas' );
			return !! ( window.WebGLRenderingContext && ( c.getContext( 'webgl' ) || c.getContext( 'experimental-webgl' ) ) );
		} catch ( e ) {
			return false;
		}
	}

	function saveData() {
		return !! ( navigator.connection && navigator.connection.saveData );
	}

	function startFields() {
		if ( ! window.ACXField ) {
			return;
		}
		Array.prototype.forEach.call( canvases, function ( c ) {
			window.ACXField.create( c, {
				mode: c.getAttribute( 'data-acx-field' ) || 'full',
				density: cfg.density || 'auto',
				state: state
			} );
		} );
	}

	function loadField() {
		if ( window.ACXField ) {
			startFields();
			return;
		}
		var s = d.createElement( 'script' );
		s.src = cfg.fieldUrl;
		s.async = true;
		s.onload = startFields;
		d.head.appendChild( s );
	}

	if ( canvases.length && cfg.webgl !== false && cfg.fieldUrl && ! reduced() && ! saveData() && webglAvailable() ) {
		var kick = function () {
			if ( 'requestIdleCallback' in window ) {
				window.requestIdleCallback( loadField, { timeout: 1200 } );
			} else {
				setTimeout( loadField, 400 );
			}
		};
		if ( d.readyState === 'complete' ) {
			kick();
		} else {
			window.addEventListener( 'load', kick );
		}
	}

	reduceMq.addEventListener( 'change', function () {
		if ( reduced() ) {
			revealAll();
			if ( window.ACXField ) {
				window.ACXField.destroyAll();
			}
		}
	} );
} )();
