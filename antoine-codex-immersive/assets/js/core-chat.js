/*!
 * ANTOINE CODEX Immersive — panneau de conversation ANTOINE CODEX CORE.
 * Le navigateur n'appelle que la route WordPress /wp-json/antoine-codex/v1/core/message :
 * aucune clé ni adresse de serveur CORE n'est présente ici.
 */
( function () {
	'use strict';

	var cfg = window.ACXCoreConfig;
	var d = document;
	var root = d.querySelector( '[data-acx-core]' );
	if ( ! cfg || ! root ) {
		return;
	}

	var t = cfg.i18n || {};
	var launcher = root.querySelector( '[data-acx-core-launcher]' );
	var HASH = '#antoine-codex-core';

	function bindOpeners( openFn ) {
		d.addEventListener( 'click', function ( e ) {
			var el = e.target.closest( '[data-acx-core-open], a[href$="' + HASH + '"]' );
			if ( ! el ) {
				return;
			}
			e.preventDefault();
			openFn( { trigger: el } );
		} );
		if ( window.location.hash === HASH ) {
			setTimeout( function () {
				openFn( {} );
			}, 300 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Mode « widget existant » : on ouvre le widget CORE déjà présent.    */
	/* ------------------------------------------------------------------ */
	if ( cfg.mode === 'external' ) {
		var resolveFn = function () {
			if ( ! cfg.externalJs ) {
				return null;
			}
			var parts = cfg.externalJs.split( '.' );
			var ctx = window;
			var obj = window;
			for ( var i = 0; i < parts.length; i++ ) {
				ctx = obj;
				obj = obj ? obj[ parts[ i ] ] : undefined;
			}
			return typeof obj === 'function' ? { fn: obj, ctx: ctx } : null;
		};
		var openExternal = function () {
			var f = resolveFn();
			if ( f ) {
				f.fn.call( f.ctx );
				return true;
			}
			var el = cfg.externalSelector ? d.querySelector( cfg.externalSelector ) : null;
			if ( el ) {
				el.click();
				return true;
			}
			if ( window.console ) {
				console.warn( t.externalMissing || 'CORE widget not found' );
			}
			return false;
		};
		if ( launcher ) {
			launcher.hidden = false;
			launcher.addEventListener( 'click', openExternal );
			// Masque le bouton d'origine (le widget lui-même reste intact).
			if ( cfg.externalSelector ) {
				var tries = 0;
				var hideOriginal = function () {
					var el = d.querySelector( cfg.externalSelector );
					if ( el ) {
						el.classList.add( 'acx-core-external-hidden' );
					} else if ( tries++ < 20 ) {
						setTimeout( hideOriginal, 500 );
					}
				};
				hideOriginal();
			}
		}
		bindOpeners( openExternal );
		window.ACXCore = { open: openExternal, close: function () {}, send: function () {} };
		return;
	}

	/* ------------------------------------------------------------------ */
	/* Panneau intégré (modes passerelle HTTP et extension PHP).           */
	/* ------------------------------------------------------------------ */
	var panel = root.querySelector( '.acx-core__panel' );
	if ( ! panel ) {
		return;
	}
	var backdrop = root.querySelector( '.acx-core__backdrop' );
	var log = root.querySelector( '[data-acx-core-log]' );
	var form = root.querySelector( '[data-acx-core-form]' );
	var input = root.querySelector( '[data-acx-core-input]' );
	var sendBtn = root.querySelector( '[data-acx-core-send]' );
	var counter = root.querySelector( '[data-acx-core-count]' );
	var intro = root.querySelector( '[data-acx-core-intro]' );
	var diagBox = root.querySelector( '[data-acx-core-diag]' );
	var scrollBox = root.querySelector( '[data-acx-core-scroll]' );
	var maxLen = cfg.maxLength || 2000;
	var mobileMq = window.matchMedia( '(max-width: 640px)' );
	var reduceMq = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var busy = false;
	var lastTrigger = null;
	var closeTimer = 0;

	// Région d'annonce pour lecteurs d'écran.
	var status = d.createElement( 'p' );
	status.className = 'screen-reader-text';
	status.setAttribute( 'role', 'status' );
	panel.appendChild( status );

	function announce( msg ) {
		status.textContent = '';
		setTimeout( function () {
			status.textContent = msg;
		}, 50 );
	}

	/* --- Stockage de session (conversation conservée entre les pages) --- */
	function uuid() {
		if ( window.crypto && crypto.randomUUID ) {
			return crypto.randomUUID();
		}
		return 'acx-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	var store = { sid: uuid(), messages: [], open: false, diagnostic: '' };
	try {
		var raw = window.sessionStorage.getItem( cfg.storageKey );
		if ( raw ) {
			var parsed = JSON.parse( raw );
			if ( parsed && Array.isArray( parsed.messages ) ) {
				store = parsed;
			}
		}
	} catch ( e ) {}

	function save() {
		try {
			store.messages = store.messages.slice( -40 );
			window.sessionStorage.setItem( cfg.storageKey, JSON.stringify( store ) );
		} catch ( e ) {}
	}

	/* --- Rendu Markdown minimal et sûr --- */
	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function inline( s ) {
		s = esc( s );
		s = s.replace( /`([^`]+)`/g, '<code>$1</code>' );
		s = s.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
		s = s.replace( /(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>' );
		s = s.replace( /\[([^\]]+)\]\(((?:https?:\/\/|mailto:|\/)[^\s)]+)\)/g, function ( m, text, href ) {
			return '<a href="' + href + '" target="_blank" rel="noopener">' + text + '</a>';
		} );
		s = s.replace( /(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>' );
		return s;
	}

	function markdown( text ) {
		var blocks = String( text ).replace( /\r/g, '' ).split( /\n{2,}/ );
		return blocks.map( function ( block ) {
			var lines = block.split( '\n' );
			if ( lines.every( function ( l ) {
				return /^\s*[-*•]\s+/.test( l );
			} ) ) {
				return '<ul>' + lines.map( function ( l ) {
					return '<li>' + inline( l.replace( /^\s*[-*•]\s+/, '' ) ) + '</li>';
				} ).join( '' ) + '</ul>';
			}
			if ( lines.every( function ( l ) {
				return /^\s*\d+[.)]\s+/.test( l );
			} ) ) {
				return '<ol>' + lines.map( function ( l ) {
					return '<li>' + inline( l.replace( /^\s*\d+[.)]\s+/, '' ) ) + '</li>';
				} ).join( '' ) + '</ol>';
			}
			if ( /^#{1,4}\s+/.test( lines[ 0 ] ) && lines.length === 1 ) {
				return '<p><strong>' + inline( lines[ 0 ].replace( /^#{1,4}\s+/, '' ) ) + '</strong></p>';
			}
			return '<p>' + lines.map( inline ).join( '<br>' ) + '</p>';
		} ).join( '' );
	}

	/* --- Messages --- */
	function scrollDown() {
		scrollBox.scrollTo( { top: scrollBox.scrollHeight, behavior: reduceMq.matches ? 'auto' : 'smooth' } );
	}

	function renderMessage( msg ) {
		var li = d.createElement( 'li' );
		li.className = 'acx-core__msg acx-core__msg--' + msg.role;
		var who = d.createElement( 'span' );
		who.className = 'acx-core__who';
		who.textContent = msg.role === 'user' ? ( t.you || 'Vous' ) : ( t.core || 'CORE' );
		var bubble = d.createElement( 'div' );
		bubble.className = 'acx-core__bubble';
		if ( msg.role === 'assistant' ) {
			bubble.innerHTML = markdown( msg.content );
		} else {
			bubble.textContent = msg.content;
		}
		li.appendChild( who );
		li.appendChild( bubble );

		if ( msg.role === 'assistant' && msg.suggestions && msg.suggestions.length ) {
			var follow = d.createElement( 'div' );
			follow.className = 'acx-core__follow';
			msg.suggestions.forEach( function ( s ) {
				var b = d.createElement( 'button' );
				b.type = 'button';
				b.className = 'acx-core__chip';
				b.textContent = s;
				b.addEventListener( 'click', function () {
					send( s );
				} );
				follow.appendChild( b );
			} );
			li.appendChild( follow );
		}
		log.appendChild( li );
		return li;
	}

	function renderError( text, retryFn ) {
		var li = d.createElement( 'li' );
		li.className = 'acx-core__msg acx-core__msg--error';
		li.setAttribute( 'role', 'alert' );
		var bubble = d.createElement( 'div' );
		bubble.className = 'acx-core__bubble';
		bubble.textContent = text;
		if ( retryFn ) {
			var b = d.createElement( 'button' );
			b.type = 'button';
			b.className = 'acx-core__retry';
			b.textContent = t.retry || 'Réessayer';
			b.addEventListener( 'click', function () {
				li.remove();
				retryFn();
			} );
			bubble.appendChild( d.createElement( 'br' ) );
			bubble.appendChild( b );
		}
		li.appendChild( bubble );
		log.appendChild( li );
		scrollDown();
	}

	function renderTyping() {
		var li = d.createElement( 'li' );
		li.className = 'acx-core__msg acx-core__msg--assistant acx-core__msg--typing';
		li.innerHTML = '<span class="acx-core__who">' + esc( t.core || 'CORE' ) + '</span><div class="acx-core__bubble"><span class="acx-core__dots" aria-hidden="true"><i></i><i></i><i></i></span><span>' + esc( t.thinking || '…' ) + '</span></div>';
		log.appendChild( li );
		scrollDown();
		return li;
	}

	function updateIntro() {
		intro.hidden = store.messages.length > 0;
	}

	function restore() {
		log.innerHTML = '';
		store.messages.forEach( renderMessage );
		updateIntro();
		if ( store.diagnostic && ! store.diagnosticSent ) {
			showDiag();
		}
	}

	function setBusy( on ) {
		busy = on;
		root.classList.toggle( 'is-busy', on );
		sendBtn.disabled = on;
		input.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		panel.setAttribute( 'aria-busy', on ? 'true' : 'false' );
	}

	/* --- Envoi --- */
	function send( text, ctx, isRetry ) {
		text = String( text || '' ).trim();
		ctx = ctx || {};
		if ( ! text || busy ) {
			return;
		}
		if ( text.length > maxLen ) {
			announce( t.tooLong || 'Message trop long.' );
			return;
		}

		var history = store.messages.filter( function ( m ) {
			return m.role === 'user' || m.role === 'assistant';
		} ).slice( -12 ).map( function ( m ) {
			return { role: m.role, content: m.content };
		} );

		if ( ! isRetry ) {
			var userMsg = { role: 'user', content: text, ts: Date.now() };
			store.messages.push( userMsg );
			renderMessage( userMsg );
			save();
		}
		updateIntro();
		scrollDown();
		setBusy( true );
		var typing = renderTyping();

		var controller = 'AbortController' in window ? new AbortController() : null;
		var timer = setTimeout( function () {
			if ( controller ) {
				controller.abort();
			}
		}, ( cfg.timeout || 50 ) * 1000 );

		var payload = {
			message: text,
			session_id: store.sid,
			history: history,
			context: {
				page_url: window.location.href.split( '#' )[ 0 ],
				page_title: d.title,
				source: ctx.source || 'chat',
				locale: cfg.locale || d.documentElement.lang,
				diagnostic: ctx.diagnostic || store.diagnostic || ''
			}
		};

		var retry = function () {
			send( text, ctx, true );
		};

		fetch( cfg.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify( payload ),
			signal: controller ? controller.signal : undefined
		} )
			.then( function ( r ) {
				return r.json().catch( function () {
					return {};
				} ).then( function ( data ) {
					return { status: r.status, ok: r.ok, data: data };
				} );
			} )
			.then( function ( res ) {
				typing.remove();
				if ( res.ok && res.data && res.data.reply ) {
					if ( res.data.session_id ) {
						store.sid = res.data.session_id;
					}
					var msg = { role: 'assistant', content: res.data.reply, suggestions: res.data.suggestions || [], ts: Date.now() };
					store.messages.push( msg );
					save();
					renderMessage( msg );
					scrollDown();
					announce( ( t.core || 'CORE' ) + ' : ' + res.data.reply.slice( 0, 180 ) );
					return;
				}
				var m = t.error;
				if ( res.status === 429 ) {
					m = t.errorBusy;
				} else if ( res.status === 503 ) {
					m = t.errorOff;
				} else if ( res.status === 504 ) {
					m = t.errorTimeout;
				}
				renderError( m, res.status === 429 || res.status === 503 ? null : retry );
			} )
			.catch( function ( err ) {
				typing.remove();
				if ( err && err.name === 'AbortError' ) {
					renderError( t.errorTimeout, retry );
				} else if ( navigator.onLine === false ) {
					renderError( t.offline, retry );
				} else {
					renderError( t.error, retry );
				}
			} )
			.then( function () {
				clearTimeout( timer );
				setBusy( false );
			} );
	}

	/* --- Ouverture / fermeture --- */
	function isOpen() {
		return root.classList.contains( 'is-open' );
	}

	function trap( e ) {
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			close();
			return;
		}
		if ( e.key !== 'Tab' || ! mobileMq.matches ) {
			return;
		}
		var f = panel.querySelectorAll( 'a[href], button:not([disabled]), textarea, [tabindex]:not([tabindex="-1"])' );
		f = Array.prototype.filter.call( f, function ( el ) {
			return el.offsetParent !== null;
		} );
		if ( ! f.length ) {
			return;
		}
		if ( e.shiftKey && d.activeElement === f[ 0 ] ) {
			e.preventDefault();
			f[ f.length - 1 ].focus();
		} else if ( ! e.shiftKey && d.activeElement === f[ f.length - 1 ] ) {
			e.preventDefault();
			f[ 0 ].focus();
		}
	}

	function open( opts ) {
		opts = opts || {};
		clearTimeout( closeTimer );
		lastTrigger = opts.trigger || d.activeElement;
		panel.hidden = false;
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		void panel.offsetWidth;
		root.classList.add( 'is-open' );
		if ( launcher ) {
			launcher.setAttribute( 'aria-expanded', 'true' );
		}
		if ( mobileMq.matches ) {
			d.body.classList.add( 'is-locked' );
		}
		store.open = true;
		save();
		if ( opts.focus !== false ) {
			setTimeout( function () {
				input.focus( { preventScroll: true } );
			}, 80 );
			announce( t.opened || '' );
		}
		scrollBox.scrollTop = scrollBox.scrollHeight;
	}

	function close() {
		if ( ! isOpen() ) {
			return;
		}
		root.classList.remove( 'is-open' );
		if ( launcher ) {
			launcher.setAttribute( 'aria-expanded', 'false' );
		}
		d.body.classList.remove( 'is-locked' );
		store.open = false;
		save();
		closeTimer = setTimeout( function () {
			if ( ! isOpen() ) {
				panel.hidden = true;
				if ( backdrop ) {
					backdrop.hidden = true;
				}
			}
		}, reduceMq.matches ? 0 : 700 );
		var target = lastTrigger && d.contains( lastTrigger ) && lastTrigger !== d.body ? lastTrigger : launcher;
		if ( target && target.focus ) {
			target.focus( { preventScroll: true } );
		}
	}

	function reset() {
		store = { sid: uuid(), messages: [], open: true, diagnostic: '' };
		save();
		log.innerHTML = '';
		diagBox.hidden = true;
		updateIntro();
		announce( t.reset || '' );
		input.focus();
	}

	/* --- Quick Diagnostic → CORE --- */
	function showDiag() {
		diagBox.innerHTML = '';
		var p = d.createElement( 'p' );
		p.textContent = t.diagReceived || '';
		var b = d.createElement( 'button' );
		b.type = 'button';
		b.className = 'btn btn--core btn--sm';
		b.innerHTML = '<span class="btn__label"></span>';
		b.firstChild.textContent = t.diagSend || 'OK';
		b.addEventListener( 'click', function () {
			store.diagnosticSent = true;
			save();
			diagBox.hidden = true;
			send( t.diagPrompt || 'Quick Diagnostic', { source: 'quick-diagnostic', diagnostic: store.diagnostic } );
		} );
		diagBox.appendChild( p );
		diagBox.appendChild( b );
		diagBox.hidden = false;
	}

	d.addEventListener( 'acx:quick-diagnostic:complete', function ( e ) {
		var detail = e.detail || {};
		var summary = detail.summary;
		if ( ! summary ) {
			return;
		}
		if ( typeof summary !== 'string' ) {
			try {
				summary = JSON.stringify( summary );
			} catch ( err ) {
				return;
			}
		}
		store.diagnostic = summary.slice( 0, 8000 );
		store.diagnosticSent = false;
		save();
		showDiag();
		if ( detail.open !== false ) {
			open( { focus: false } );
		}
	} );

	/* --- Événements --- */
	if ( launcher ) {
		launcher.hidden = false;
		launcher.addEventListener( 'click', function () {
			if ( isOpen() ) {
				close();
			} else {
				open( { trigger: launcher } );
			}
		} );
	}

	each( '[data-acx-core-close]', function ( b ) {
		b.addEventListener( 'click', close );
	} );

	root.querySelector( '[data-acx-core-reset]' ).addEventListener( 'click', reset );

	each( '[data-acx-core-suggest]', function ( b ) {
		b.addEventListener( 'click', function () {
			send( b.textContent );
		} );
	} );

	panel.addEventListener( 'keydown', trap );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var v = input.value;
		if ( ! v.trim() || busy ) {
			return;
		}
		input.value = '';
		autosize();
		send( v );
	} );

	input.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey && ! e.isComposing ) {
			e.preventDefault();
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
			}
		}
	} );

	function autosize() {
		input.style.height = 'auto';
		input.style.height = Math.min( 180, input.scrollHeight ) + 'px';
		if ( counter ) {
			counter.textContent = input.value.length + ' / ' + maxLen;
		}
	}
	input.addEventListener( 'input', autosize );

	function each( sel, fn ) {
		Array.prototype.forEach.call( root.querySelectorAll( sel ), fn );
	}

	bindOpeners( open );
	restore();

	// Réouverture après navigation (écrans larges uniquement, sans voler le focus).
	if ( store.open && ! mobileMq.matches ) {
		open( { focus: false } );
	}

	window.ACXCore = {
		open: function () {
			open( {} );
		},
		close: close,
		send: function ( text, ctx ) {
			if ( ! isOpen() ) {
				open( { focus: false } );
			}
			send( text, ctx );
		}
	};
} )();
