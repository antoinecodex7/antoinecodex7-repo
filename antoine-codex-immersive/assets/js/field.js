/*!
 * ANTOINE CODEX Immersive — champ de particules « structure cachée ».
 * WebGL 1 natif, sans bibliothèque. Des données dispersées s'organisent en une structure
 * (noyau, couches, orbites) au fil du défilement ; le pointeur agit comme une lentille
 * qui révèle localement la structure et ses connexions.
 *
 * Garde-fous : chargé uniquement si WebGL est disponible et que le visiteur n'a pas demandé
 * la réduction des animations ; rendu suspendu hors écran et onglet masqué ; densité et
 * résolution adaptées à l'appareil puis ajustées en direct si les images/s chutent.
 */
( function () {
	'use strict';

	var instances = [];

	/* ------------------------------------------------------------------ */
	/* Mathématiques minimales (matrices 4×4 en colonnes)                  */
	/* ------------------------------------------------------------------ */
	function perspective( fovy, aspect, near, far ) {
		var f = 1 / Math.tan( fovy / 2 );
		var nf = 1 / ( near - far );
		return new Float32Array( [ f / aspect, 0, 0, 0, 0, f, 0, 0, 0, 0, ( far + near ) * nf, -1, 0, 0, 2 * far * near * nf, 0 ] );
	}

	function multiply( a, b ) {
		var o = new Float32Array( 16 );
		for ( var c = 0; c < 4; c++ ) {
			for ( var r = 0; r < 4; r++ ) {
				o[ c * 4 + r ] = a[ r ] * b[ c * 4 ] + a[ 4 + r ] * b[ c * 4 + 1 ] + a[ 8 + r ] * b[ c * 4 + 2 ] + a[ 12 + r ] * b[ c * 4 + 3 ];
			}
		}
		return o;
	}

	function translation( x, y, z ) {
		return new Float32Array( [ 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, x, y, z, 1 ] );
	}

	function rotX( a ) {
		var c = Math.cos( a ), s = Math.sin( a );
		return new Float32Array( [ 1, 0, 0, 0, 0, c, s, 0, 0, -s, c, 0, 0, 0, 0, 1 ] );
	}

	function rotY( a ) {
		var c = Math.cos( a ), s = Math.sin( a );
		return new Float32Array( [ c, 0, -s, 0, 0, 1, 0, 0, s, 0, c, 0, 0, 0, 0, 1 ] );
	}

	function rotZ( a ) {
		var c = Math.cos( a ), s = Math.sin( a );
		return new Float32Array( [ c, s, 0, 0, -s, c, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1 ] );
	}

	function scale( k ) {
		return new Float32Array( [ k, 0, 0, 0, 0, k, 0, 0, 0, 0, k, 0, 0, 0, 0, 1 ] );
	}

	function lerp( a, b, t ) {
		return a + ( b - a ) * t;
	}

	// Générateur pseudo-aléatoire déterministe (même composition à chaque visite).
	function rng( seed ) {
		var s = seed >>> 0;
		return function () {
			s = ( s + 0x6d2b79f5 ) >>> 0;
			var t = s;
			t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
			t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	function gauss( rand ) {
		var u = 1 - rand(), v = rand();
		return Math.sqrt( -2 * Math.log( u ) ) * Math.cos( 2 * Math.PI * v );
	}

	/* ------------------------------------------------------------------ */
	/* Capacités de l'appareil                                             */
	/* ------------------------------------------------------------------ */
	function tier( density ) {
		var coarse = window.matchMedia( '(pointer: coarse)' ).matches;
		var small = window.innerWidth < 768;
		var cores = navigator.hardwareConcurrency || 4;
		var mem = navigator.deviceMemory || 4;
		if ( density === 'low' ) {
			return 'low';
		}
		if ( density === 'high' ) {
			return coarse || small ? 'mid' : 'high';
		}
		if ( coarse || small || cores <= 2 || mem <= 2 ) {
			return 'low';
		}
		if ( cores <= 4 || mem <= 4 ) {
			return 'mid';
		}
		return 'high';
	}

	var COUNTS = {
		full: { high: 16000, mid: 9000, low: 4200 },
		lite: { high: 7000, mid: 4500, low: 2400 }
	};
	var DPR = { high: 1.75, mid: 1.5, low: 1.25 };

	/* ------------------------------------------------------------------ */
	/* Shaders                                                             */
	/* ------------------------------------------------------------------ */
	var POINT_VS = [
		'precision highp float;',
		'attribute vec3 aChaos;',
		'attribute vec3 aOrder;',
		'attribute vec4 aRand;',
		'uniform mat4 uProj;',
		'uniform mat4 uView;',
		'uniform mat4 uModel;',
		'uniform float uTime;',
		'uniform float uProgress;',
		'uniform float uIntro;',
		'uniform float uWave;',
		'uniform vec3 uMouse;',
		'uniform float uMouseOn;',
		'uniform float uSize;',
		'uniform float uDpr;',
		'uniform float uGain;',
		'uniform vec3 cIndigo;',
		'uniform vec3 cGold;',
		'uniform vec3 cOrange;',
		'uniform vec3 cWhite;',
		'varying vec3 vColor;',
		'varying float vAlpha;',
		'vec3 wave(vec3 o){',
		'  o.y += uWave * (0.16 * sin(uTime * 0.55 + o.x * 0.8 + o.z * 0.45) + 0.08 * cos(uTime * 0.4 - o.z * 1.2));',
		'  return o;',
		'}',
		'void main(){',
		'  float r = aRand.x;',
		'  vec3 c = aChaos;',
		'  float t = uTime * 0.14;',
		'  c += vec3(sin(t + aRand.y * 6.2831 + c.y * 0.6), cos(t * 0.9 + aRand.z * 6.2831 + c.x * 0.5), sin(t * 0.7 + aRand.w * 6.2831)) * 0.24;',
		'  vec3 wo = (uModel * vec4(wave(aOrder), 1.0)).xyz;',
		'  float p = clamp(uProgress * 1.3 - r * 0.3, 0.0, 1.0);',
		'  p = p * p * (3.0 - 2.0 * p);',
		'  vec3 pos = mix(c, wo, p);',
		'  float dist = length(pos.xy - uMouse.xy);',
		'  float lens = (1.0 - smoothstep(0.0, 1.9, dist)) * uMouseOn;',
		'  float q = clamp(p + lens * (1.0 - p) * 0.92, 0.0, 1.0);',
		'  pos = mix(c, wo, q);',
		'  pos += normalize(aOrder + vec3(0.001)) * sin(uTime * 0.8 + r * 30.0) * 0.018 * q;',
		'  pos.xy *= 1.0 + (1.0 - uIntro) * 0.25;',
		'  vec4 mv = uView * vec4(pos, 1.0);',
		'  gl_Position = uProj * mv;',
		'  float signal = step(0.978, aRand.w);',
		'  float size = uSize * (0.55 + aRand.y * 1.15) * (1.0 + lens * 0.9 + signal * 1.3);',
		'  gl_PointSize = max(1.0, size * uDpr * (5.5 / -mv.z));',
		'  vec3 base = mix(cIndigo, cGold, q * smoothstep(0.15, 0.95, aRand.z + 0.25));',
		'  base = mix(base, cWhite, 0.1 * q);',
		'  vec3 col = mix(base, cOrange, clamp(signal + lens * 0.85 * step(0.55, aRand.z), 0.0, 1.0));',
		'  vColor = col;',
		'  float tw = 0.62 + 0.38 * sin(uTime * 1.6 + r * 40.0);',
		'  float depth = smoothstep(16.0, 3.5, -mv.z);',
		'  vAlpha = (0.34 + 0.66 * q + lens * 0.4 + signal * 0.35) * tw * uIntro * depth * uGain;',
		'}'
	].join( '\n' );

	var POINT_FS = [
		'precision mediump float;',
		'varying vec3 vColor;',
		'varying float vAlpha;',
		'void main(){',
		'  vec2 c = gl_PointCoord - 0.5;',
		'  float d = length(c);',
		'  if (d > 0.5) discard;',
		'  float a = smoothstep(0.5, 0.0, d);',
		'  a = a * a + smoothstep(0.14, 0.0, d) * 0.55;',
		'  gl_FragColor = vec4(vColor, a * vAlpha);',
		'}'
	].join( '\n' );

	var LINE_VS = [
		'precision highp float;',
		'attribute vec3 aPos;',
		'attribute float aSeed;',
		'uniform mat4 uProj;',
		'uniform mat4 uView;',
		'uniform mat4 uModel;',
		'uniform float uTime;',
		'uniform float uProgress;',
		'uniform float uIntro;',
		'uniform float uWave;',
		'uniform vec3 uMouse;',
		'uniform float uMouseOn;',
		'varying float vA;',
		'void main(){',
		'  vec3 o = aPos;',
		'  o.y += uWave * (0.16 * sin(uTime * 0.55 + o.x * 0.8 + o.z * 0.45) + 0.08 * cos(uTime * 0.4 - o.z * 1.2));',
		'  vec3 wp = (uModel * vec4(o, 1.0)).xyz;',
		'  float dist = length(wp.xy - uMouse.xy);',
		'  float lens = (1.0 - smoothstep(0.0, 2.1, dist)) * uMouseOn;',
		'  float reveal = smoothstep(0.5, 1.0, uProgress);',
		'  vec4 mv = uView * vec4(wp, 1.0);',
		'  float depth = smoothstep(16.0, 3.5, -mv.z);',
		'  vA = max(0.08 + reveal * 0.22, lens * 0.7) * (0.55 + 0.45 * sin(uTime * 1.8 + aSeed * 25.0)) * uIntro * depth;',
		'  gl_Position = uProj * mv;',
		'}'
	].join( '\n' );

	var LINE_FS = [
		'precision mediump float;',
		'uniform vec3 uColor;',
		'varying float vA;',
		'void main(){ gl_FragColor = vec4(uColor, vA); }'
	].join( '\n' );

	function compile( gl, type, src ) {
		var sh = gl.createShader( type );
		gl.shaderSource( sh, src );
		gl.compileShader( sh );
		if ( ! gl.getShaderParameter( sh, gl.COMPILE_STATUS ) ) {
			throw new Error( gl.getShaderInfoLog( sh ) || 'shader' );
		}
		return sh;
	}

	function program( gl, vs, fs ) {
		var p = gl.createProgram();
		gl.attachShader( p, compile( gl, gl.VERTEX_SHADER, vs ) );
		gl.attachShader( p, compile( gl, gl.FRAGMENT_SHADER, fs ) );
		gl.linkProgram( p );
		if ( ! gl.getProgramParameter( p, gl.LINK_STATUS ) ) {
			throw new Error( gl.getProgramInfoLog( p ) || 'link' );
		}
		return p;
	}

	/* ------------------------------------------------------------------ */
	/* Compositions                                                        */
	/* ------------------------------------------------------------------ */
	function fibSphere( n, radius, out, offset ) {
		var ga = Math.PI * ( 3 - Math.sqrt( 5 ) );
		for ( var i = 0; i < n; i++ ) {
			var y = 1 - ( 2 * ( i + 0.5 ) ) / n;
			var r = Math.sqrt( 1 - y * y );
			var phi = i * ga;
			out[ ( offset + i ) * 3 ] = Math.cos( phi ) * r * radius;
			out[ ( offset + i ) * 3 + 1 ] = y * radius;
			out[ ( offset + i ) * 3 + 2 ] = Math.sin( phi ) * r * radius;
		}
	}

	function nearestLines( nodes, n, k ) {
		var segs = [];
		var seen = {};
		for ( var i = 0; i < n; i++ ) {
			var best = [];
			for ( var j = 0; j < n; j++ ) {
				if ( i === j ) {
					continue;
				}
				var dx = nodes[ i * 3 ] - nodes[ j * 3 ], dy = nodes[ i * 3 + 1 ] - nodes[ j * 3 + 1 ], dz = nodes[ i * 3 + 2 ] - nodes[ j * 3 + 2 ];
				var dd = dx * dx + dy * dy + dz * dz;
				if ( best.length < k || dd < best[ best.length - 1 ][ 0 ] ) {
					best.push( [ dd, j ] );
					best.sort( function ( a, b ) {
						return a[ 0 ] - b[ 0 ];
					} );
					if ( best.length > k ) {
						best.pop();
					}
				}
			}
			best.forEach( function ( b ) {
				var key = Math.min( i, b[ 1 ] ) + '-' + Math.max( i, b[ 1 ] );
				if ( ! seen[ key ] ) {
					seen[ key ] = 1;
					segs.push( i, b[ 1 ] );
				}
			} );
		}
		return segs;
	}

	// Noyau : sphère de Fibonacci (nœuds reliés), couches internes, bandes orbitales segmentées.
	function buildCore( count, rand ) {
		var order = new Float32Array( count * 3 );
		var NODES = 300;
		var R = 1.55;
		fibSphere( NODES, R, order, 0 );

		var shell = Math.floor( count * 0.46 );
		var inner = Math.floor( count * 0.18 );
		var rings = count - NODES - shell - inner;
		var i, idx = NODES;

		// Coque : points jitterés sur la sphère.
		var tmp = new Float32Array( shell * 3 );
		fibSphere( shell, R, tmp, 0 );
		for ( i = 0; i < shell; i++, idx++ ) {
			var j = 1 + ( rand() - 0.5 ) * 0.03;
			order[ idx * 3 ] = tmp[ i * 3 ] * j;
			order[ idx * 3 + 1 ] = tmp[ i * 3 + 1 ] * j;
			order[ idx * 3 + 2 ] = tmp[ i * 3 + 2 ] * j;
		}

		// Couches internes.
		var innerA = Math.floor( inner * 0.62 );
		tmp = new Float32Array( inner * 3 );
		fibSphere( innerA, R * 0.58, tmp, 0 );
		fibSphere( inner - innerA, R * 0.28, tmp, innerA );
		for ( i = 0; i < inner; i++, idx++ ) {
			order[ idx * 3 ] = tmp[ i * 3 ];
			order[ idx * 3 + 1 ] = tmp[ i * 3 + 1 ];
			order[ idx * 3 + 2 ] = tmp[ i * 3 + 2 ];
		}

		// Bandes orbitales (segments de données).
		var bands = [
			{ r: R * 1.55, tiltX: 1.25, tiltZ: 0.28, segs: 18, share: 0.55 },
			{ r: R * 1.95, tiltX: 1.72, tiltZ: -0.42, segs: 26, share: 0.45 }
		];
		var start = idx;
		bands.forEach( function ( b, bi ) {
			var n = bi === bands.length - 1 ? count - idx : Math.floor( rings * b.share );
			var cx = Math.cos( b.tiltX ), sx = Math.sin( b.tiltX ), cz = Math.cos( b.tiltZ ), sz = Math.sin( b.tiltZ );
			for ( var k = 0; k < n && idx < count; k++, idx++ ) {
				var seg = Math.floor( rand() * b.segs );
				var a = ( ( seg + rand() * 0.72 ) / b.segs ) * Math.PI * 2;
				var rr = b.r + gauss( rand ) * 0.035;
				var x = Math.cos( a ) * rr, y = ( rand() - 0.5 ) * 0.05, z = Math.sin( a ) * rr;
				// Inclinaison X puis Z.
				var y1 = y * cx - z * sx, z1 = y * sx + z * cx;
				var x2 = x * cz - y1 * sz, y2 = x * sz + y1 * cz;
				order[ idx * 3 ] = x2;
				order[ idx * 3 + 1 ] = y2;
				order[ idx * 3 + 2 ] = z1;
			}
		} );
		void start;

		var lines = nearestLines( order, NODES, 3 );
		return { order: order, nodes: NODES, lines: lines };
	}

	// Terrain de données : grille ondulée + petit noyau (pages intérieures).
	function buildTerrain( count, rand ) {
		var order = new Float32Array( count * 3 );
		var core = Math.floor( count * 0.16 );
		var gridN = count - core;
		var cols = Math.round( Math.sqrt( gridN * 2.2 ) );
		var rows = Math.floor( gridN / cols );
		var W = 12, D = 6;
		var idx = 0, r, c;
		// Nœuds (grille grossière) en tête : ils portent les lignes.
		var NC = 16, NR = 8;
		for ( r = 0; r < NR; r++ ) {
			for ( c = 0; c < NC; c++, idx++ ) {
				order[ idx * 3 ] = ( c / ( NC - 1 ) - 0.5 ) * W;
				order[ idx * 3 + 1 ] = 0;
				order[ idx * 3 + 2 ] = ( r / ( NR - 1 ) - 0.5 ) * D;
			}
		}
		var nodes = idx;
		for ( r = 0; r < rows && idx < count - core; r++ ) {
			for ( c = 0; c < cols && idx < count - core; c++, idx++ ) {
				order[ idx * 3 ] = ( c / ( cols - 1 ) - 0.5 ) * W + ( rand() - 0.5 ) * 0.02;
				order[ idx * 3 + 1 ] = 0;
				order[ idx * 3 + 2 ] = ( r / ( rows - 1 ) - 0.5 ) * D;
			}
		}
		var tmp = new Float32Array( ( count - idx ) * 3 );
		fibSphere( count - idx, 0.75, tmp, 0 );
		for ( var i = 0; idx < count; i++, idx++ ) {
			order[ idx * 3 ] = tmp[ i * 3 ] + 3.2;
			order[ idx * 3 + 1 ] = tmp[ i * 3 + 1 ] + 1.35;
			order[ idx * 3 + 2 ] = tmp[ i * 3 + 2 ] - 0.4;
		}
		var lines = [];
		for ( r = 0; r < NR; r++ ) {
			for ( c = 0; c < NC; c++ ) {
				var id = r * NC + c;
				if ( c < NC - 1 ) {
					lines.push( id, id + 1 );
				}
				if ( r < NR - 1 ) {
					lines.push( id, id + NC );
				}
			}
		}
		return { order: order, nodes: nodes, lines: lines };
	}

	function buildChaos( count, halfW, halfH, rand ) {
		var chaos = new Float32Array( count * 3 );
		var clusters = [];
		for ( var k = 0; k < 8; k++ ) {
			clusters.push( [ ( rand() * 2 - 1 ) * halfW * 0.95, ( rand() * 2 - 1 ) * halfH * 0.9, -rand() * 4 + 0.8, 0.35 + rand() * 0.9 ] );
		}
		for ( var i = 0; i < count; i++ ) {
			if ( rand() < 0.62 ) {
				var cl = clusters[ Math.floor( rand() * clusters.length ) ];
				chaos[ i * 3 ] = cl[ 0 ] + gauss( rand ) * cl[ 3 ] * 1.4;
				chaos[ i * 3 + 1 ] = cl[ 1 ] + gauss( rand ) * cl[ 3 ] * 0.8;
				chaos[ i * 3 + 2 ] = cl[ 2 ] + gauss( rand ) * cl[ 3 ];
			} else {
				chaos[ i * 3 ] = ( rand() * 2 - 1 ) * halfW * 1.15;
				chaos[ i * 3 + 1 ] = ( rand() * 2 - 1 ) * halfH * 1.1;
				chaos[ i * 3 + 2 ] = -rand() * 6 + 1.5;
			}
		}
		return chaos;
	}

	/* ------------------------------------------------------------------ */
	/* Instance                                                            */
	/* ------------------------------------------------------------------ */
	function Field( canvas, opts ) {
		this.canvas = canvas;
		this.mode = opts.mode === 'lite' ? 'lite' : 'full';
		this.state = opts.state || { heroProgress: 0, pointer: { x: 0, y: 0, active: false } };
		this.tier = tier( opts.density );
		this.dprMax = Math.min( window.devicePixelRatio || 1, DPR[ this.tier ] );
		this.dpr = this.dprMax;
		this.count = COUNTS[ this.mode ][ this.tier ];
		this.drawCount = this.count;
		this.visible = true;
		this.running = false;
		this.start = performance.now();
		this.last = this.start;
		this.frames = [];
		this.mouse = { x: 0, y: 0, on: 0 };
		this.fine = window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
		this.intro = 0;

		var gl = canvas.getContext( 'webgl', { alpha: true, antialias: false, depth: false, premultipliedAlpha: true, powerPreference: this.tier === 'high' ? 'high-performance' : 'low-power' } ) ||
			canvas.getContext( 'experimental-webgl' );
		if ( ! gl ) {
			throw new Error( 'webgl' );
		}
		this.gl = gl;
		this.build();
		this.bind();
	}

	Field.prototype.build = function () {
		var gl = this.gl;
		var rand = rng( this.mode === 'lite' ? 7331 : 1789 );
		this.aspect = Math.max( 0.3, this.canvas.clientWidth / Math.max( 1, this.canvas.clientHeight ) );
		this.camZ = 7;
		this.fov = 45 * Math.PI / 180;
		var halfH = Math.tan( this.fov / 2 ) * this.camZ;
		var halfW = halfH * this.aspect;

		var shape = this.mode === 'lite' ? buildTerrain( this.count, rand ) : buildCore( this.count, rand );
		// Mélange (hors nœuds) : tout sous-ensemble dessiné reste réparti sur la forme entière.
		for ( var s = this.count - 1; s > shape.nodes; s-- ) {
			var sw = shape.nodes + Math.floor( rand() * ( s - shape.nodes + 1 ) );
			for ( var ax = 0; ax < 3; ax++ ) {
				var tmpv = shape.order[ s * 3 + ax ];
				shape.order[ s * 3 + ax ] = shape.order[ sw * 3 + ax ];
				shape.order[ sw * 3 + ax ] = tmpv;
			}
		}
		var chaos = buildChaos( this.count, halfW, halfH, rand );
		var rnd = new Float32Array( this.count * 4 );
		for ( var i = 0; i < this.count * 4; i++ ) {
			rnd[ i ] = rand();
		}
		// Les nœuds sont toujours dessinés (et un peu plus lumineux).
		for ( i = 0; i < shape.nodes; i++ ) {
			rnd[ i * 4 + 1 ] = 0.7 + rand() * 0.3;
			rnd[ i * 4 + 2 ] = 0.8 + rand() * 0.2;
		}

		this.pp = program( gl, POINT_VS, POINT_FS );
		this.lp = program( gl, LINE_VS, LINE_FS );

		this.buf = {
			chaos: this.makeBuffer( chaos ),
			order: this.makeBuffer( shape.order ),
			rand: this.makeBuffer( rnd )
		};

		var lineCount = shape.lines.length;
		var lpos = new Float32Array( lineCount * 3 );
		var lseed = new Float32Array( lineCount );
		for ( i = 0; i < lineCount; i++ ) {
			var n = shape.lines[ i ];
			lpos[ i * 3 ] = shape.order[ n * 3 ];
			lpos[ i * 3 + 1 ] = shape.order[ n * 3 + 1 ];
			lpos[ i * 3 + 2 ] = shape.order[ n * 3 + 2 ];
			lseed[ i ] = rnd[ n * 4 ];
		}
		this.lineCount = lineCount;
		this.buf.lpos = this.makeBuffer( lpos );
		this.buf.lseed = this.makeBuffer( lseed );
		this.nodes = shape.nodes;

		this.loc = {};
		var self = this;
		[ 'aChaos', 'aOrder', 'aRand' ].forEach( function ( a ) {
			self.loc[ a ] = gl.getAttribLocation( self.pp, a );
		} );
		[ 'uProj', 'uView', 'uModel', 'uTime', 'uProgress', 'uIntro', 'uWave', 'uMouse', 'uMouseOn', 'uSize', 'uDpr', 'uGain', 'cIndigo', 'cGold', 'cOrange', 'cWhite' ].forEach( function ( u ) {
			self.loc[ 'p_' + u ] = gl.getUniformLocation( self.pp, u );
		} );
		self.loc.aPos = gl.getAttribLocation( self.lp, 'aPos' );
		self.loc.aSeed = gl.getAttribLocation( self.lp, 'aSeed' );
		[ 'uProj', 'uView', 'uModel', 'uTime', 'uProgress', 'uIntro', 'uWave', 'uMouse', 'uMouseOn', 'uColor' ].forEach( function ( u ) {
			self.loc[ 'l_' + u ] = gl.getUniformLocation( self.lp, u );
		} );

		gl.disable( gl.DEPTH_TEST );
		gl.enable( gl.BLEND );
		gl.blendFunc( gl.SRC_ALPHA, gl.ONE );
		gl.clearColor( 0, 0, 0, 0 );
	};

	Field.prototype.makeBuffer = function ( data ) {
		var gl = this.gl;
		var b = gl.createBuffer();
		gl.bindBuffer( gl.ARRAY_BUFFER, b );
		gl.bufferData( gl.ARRAY_BUFFER, data, gl.STATIC_DRAW );
		return b;
	};

	Field.prototype.bind = function () {
		var self = this;
		this.onResize = function () {
			self.resize();
		};
		if ( 'ResizeObserver' in window ) {
			this.ro = new ResizeObserver( this.onResize );
			this.ro.observe( this.canvas );
		} else {
			window.addEventListener( 'resize', this.onResize );
		}
		if ( 'IntersectionObserver' in window ) {
			this.io = new IntersectionObserver( function ( entries ) {
				self.visible = entries[ 0 ].isIntersecting;
				self.toggle();
			} );
			this.io.observe( this.canvas );
		}
		this.onVis = function () {
			self.toggle();
		};
		document.addEventListener( 'visibilitychange', this.onVis );
		this.onLost = function ( e ) {
			e.preventDefault();
			self.destroy();
		};
		this.canvas.addEventListener( 'webglcontextlost', this.onLost );
		this.resize();
		this.toggle();
	};

	Field.prototype.resize = function () {
		var w = this.canvas.clientWidth, h = this.canvas.clientHeight;
		if ( ! w || ! h ) {
			return;
		}
		this.canvas.width = Math.round( w * this.dpr );
		this.canvas.height = Math.round( h * this.dpr );
		this.aspect = w / h;
		this.gl.viewport( 0, 0, this.canvas.width, this.canvas.height );
		this.proj = perspective( this.fov, this.aspect, 0.1, 60 );
	};

	Field.prototype.toggle = function () {
		var should = this.visible && ! document.hidden && ! this.dead;
		if ( should && ! this.running ) {
			this.running = true;
			this.last = performance.now();
			var self = this;
			this.raf = requestAnimationFrame( function ( t ) {
				self.frame( t );
			} );
		} else if ( ! should && this.running ) {
			this.running = false;
			cancelAnimationFrame( this.raf );
		}
	};

	Field.prototype.adapt = function ( dt ) {
		this.frames.push( dt );
		if ( this.frames.length < 90 ) {
			return;
		}
		var avg = this.frames.reduce( function ( a, b ) {
			return a + b;
		}, 0 ) / this.frames.length;
		this.frames = [];
		if ( avg > 26 ) {
			if ( this.dpr > 1 ) {
				this.dpr = Math.max( 1, this.dpr - 0.25 );
				this.resize();
			} else if ( this.drawCount > this.count * 0.3 ) {
				this.drawCount = Math.max( this.nodes, Math.floor( this.drawCount * 0.7 ) );
			}
		}
	};

	Field.prototype.frame = function ( now ) {
		if ( ! this.running ) {
			return;
		}
		var self = this;
		var dt = Math.min( 100, now - this.last );
		this.last = now;
		this.adapt( dt );
		this.render( now );
		this.raf = requestAnimationFrame( function ( t ) {
			self.frame( t );
		} );
	};

	Field.prototype.render = function ( now ) {
		var gl = this.gl;
		var t = ( now - this.start ) / 1000;
		var st = this.state;
		var hp = st.heroProgress || 0;
		var ptr = st.pointer || { x: 0, y: 0, active: false };
		var wide = this.aspect > 1.1;
		var halfH = Math.tan( this.fov / 2 ) * this.camZ;
		var halfW = halfH * this.aspect;

		this.intro = Math.min( 1, this.intro + 0.012 );
		var introEase = 1 - Math.pow( 1 - this.intro, 3 );

		// Progression : auto-organisation à l'arrivée, puis révélation au défilement.
		var base = this.mode === 'lite' ? 0.66 : 0.52;
		var progress = Math.min( 1, base * introEase + hp * ( 1 - base ) * 1.15 );

		// Lentille : pointeur sur écran à pointeur fin, balayage automatique sinon.
		var mx, my, on;
		if ( this.fine && ptr.active ) {
			mx = ptr.x * halfW;
			my = ptr.y * halfH;
			on = 1;
		} else {
			var ox = wide ? halfW * 0.42 : 0;
			var oy = wide ? 0 : halfH * 0.28;
			mx = ox + Math.cos( t * 0.35 ) * 1.5;
			my = oy + Math.sin( t * 0.5 ) * 1.1;
			on = 0.75;
		}
		this.mouse.x = lerp( this.mouse.x, mx, 0.08 );
		this.mouse.y = lerp( this.mouse.y, my, 0.08 );
		this.mouse.on = lerp( this.mouse.on, on, 0.05 );

		var px = this.fine && ptr.active ? ptr.x : 0;
		var py = this.fine && ptr.active ? ptr.y : 0;

		var model;
		if ( this.mode === 'lite' ) {
			var lx = wide ? halfW * 0.35 : 0.4;
			model = multiply(
				translation( lx, -0.9 + hp * 0.6, -1.2 ),
				multiply( rotX( 0.62 + py * 0.06 ), multiply( rotY( -0.35 + px * 0.12 + Math.sin( t * 0.08 ) * 0.08 ), scale( wide ? 1 : 0.75 ) ) )
			);
		} else {
			var cx = wide ? halfW * 0.42 : 0;
			var cy = wide ? 0.05 : halfH * 0.3;
			var s = ( wide ? 1 : 0.72 ) * ( 1 + hp * 0.22 );
			model = multiply(
				translation( cx - hp * ( wide ? 0.8 : 0 ), cy + hp * 0.5, hp * 1.2 ),
				multiply( rotZ( 0.18 ), multiply( rotX( 0.22 + py * 0.14 + hp * 0.5 ), multiply( rotY( t * 0.07 + px * 0.3 ), scale( s ) ) ) )
			);
		}

		var view = translation( -px * 0.25, -py * 0.18, -this.camZ );
		var wave = this.mode === 'lite' ? 1 : 0;

		gl.clear( gl.COLOR_BUFFER_BIT );

		// Lignes (connexions révélées).
		gl.useProgram( this.lp );
		gl.uniformMatrix4fv( this.loc.l_uProj, false, this.proj );
		gl.uniformMatrix4fv( this.loc.l_uView, false, view );
		gl.uniformMatrix4fv( this.loc.l_uModel, false, model );
		gl.uniform1f( this.loc.l_uTime, t );
		gl.uniform1f( this.loc.l_uProgress, progress );
		gl.uniform1f( this.loc.l_uIntro, introEase );
		gl.uniform1f( this.loc.l_uWave, wave );
		gl.uniform3f( this.loc.l_uMouse, this.mouse.x, this.mouse.y, 0 );
		gl.uniform1f( this.loc.l_uMouseOn, this.mouse.on );
		gl.uniform3f( this.loc.l_uColor, 0.89, 0.76, 0.5 );
		this.attr( this.buf.lpos, this.loc.aPos, 3 );
		this.attr( this.buf.lseed, this.loc.aSeed, 1 );
		gl.drawArrays( gl.LINES, 0, this.lineCount );
		gl.disableVertexAttribArray( this.loc.aPos );
		gl.disableVertexAttribArray( this.loc.aSeed );

		// Particules.
		gl.useProgram( this.pp );
		gl.uniformMatrix4fv( this.loc.p_uProj, false, this.proj );
		gl.uniformMatrix4fv( this.loc.p_uView, false, view );
		gl.uniformMatrix4fv( this.loc.p_uModel, false, model );
		gl.uniform1f( this.loc.p_uTime, t );
		gl.uniform1f( this.loc.p_uProgress, progress );
		gl.uniform1f( this.loc.p_uIntro, introEase );
		gl.uniform1f( this.loc.p_uWave, wave );
		gl.uniform3f( this.loc.p_uMouse, this.mouse.x, this.mouse.y, 0 );
		gl.uniform1f( this.loc.p_uMouseOn, this.mouse.on );
		gl.uniform1f( this.loc.p_uSize, this.mode === 'lite' ? 4 : 4.2 );
		gl.uniform1f( this.loc.p_uDpr, this.dpr );
		gl.uniform1f( this.loc.p_uGain, this.mode === 'lite' ? 1.45 : 1 );
		gl.uniform3f( this.loc.p_cIndigo, 0.38, 0.36, 0.86 );
		gl.uniform3f( this.loc.p_cGold, 0.89, 0.76, 0.5 );
		gl.uniform3f( this.loc.p_cOrange, 1.0, 0.36, 0.06 );
		gl.uniform3f( this.loc.p_cWhite, 0.96, 0.95, 0.91 );
		this.attr( this.buf.chaos, this.loc.aChaos, 3 );
		this.attr( this.buf.order, this.loc.aOrder, 3 );
		this.attr( this.buf.rand, this.loc.aRand, 4 );
		gl.drawArrays( gl.POINTS, 0, this.drawCount );

		if ( ! this.readyShown ) {
			this.readyShown = true;
			this.canvas.classList.add( 'is-ready' );
			var host = this.canvas.closest( '.hero, .page-hero' );
			if ( host ) {
				host.classList.add( 'has-field' );
			}
		}
	};

	Field.prototype.attr = function ( buf, loc, size ) {
		var gl = this.gl;
		if ( loc < 0 ) {
			return;
		}
		gl.bindBuffer( gl.ARRAY_BUFFER, buf );
		gl.enableVertexAttribArray( loc );
		gl.vertexAttribPointer( loc, size, gl.FLOAT, false, 0, 0 );
	};

	Field.prototype.destroy = function () {
		this.dead = true;
		this.running = false;
		cancelAnimationFrame( this.raf );
		if ( this.ro ) {
			this.ro.disconnect();
		} else {
			window.removeEventListener( 'resize', this.onResize );
		}
		if ( this.io ) {
			this.io.disconnect();
		}
		document.removeEventListener( 'visibilitychange', this.onVis );
		this.canvas.classList.remove( 'is-ready' );
		var host = this.canvas.closest( '.hero, .page-hero' );
		if ( host ) {
			host.classList.remove( 'has-field' );
		}
	};

	window.ACXField = {
		create: function ( canvas, opts ) {
			if ( canvas.__acxField ) {
				return canvas.__acxField;
			}
			try {
				var f = new Field( canvas, opts || {} );
				canvas.__acxField = f;
				instances.push( f );
				return f;
			} catch ( e ) {
				// Échec WebGL : le visuel statique reste affiché.
				if ( window.console && console.warn ) {
					console.warn( 'ACX field disabled:', e && e.message );
				}
				return null;
			}
		},
		destroyAll: function () {
			instances.forEach( function ( f ) {
				f.destroy();
				f.canvas.__acxField = null;
			} );
			instances = [];
		}
	};
} )();
