/**
 * ThatSeoAgent screen: the interactive layer.
 *
 * Every view is server-rendered and works without this file; Alpine.js adds
 * saving without reloads, the batched content check, filters, the live
 * sidebar, and moving between views without reloading the page. Components start from the state the page was rendered with
 * (window.thatSeoAgent, printed before this file), so nothing is fetched on load.
 *
 * Load order: this file, then Alpine (assets/vendor/alpine.min.js), both
 * deferred. Everything registers on `alpine:init`, which Alpine fires before
 * it walks the page.
 *
 * Requests go through wp.apiFetch, which adds the REST nonce.
 */
( function () {
	'use strict';

	var data = window.thatSeoAgent || {};

	function t( key ) {
		return ( data.i18n && data.i18n[ key ] ) || key;
	}

	function api( options ) {
		return window.wp.apiFetch( options );
	}

	// apiFetch rejects with the REST error object ({ code, message }) or,
	// when the request never got an answer, with a generic one.
	function errorText( error ) {
		if ( error && error.message && 'fetch_error' !== error.code ) {
			return error.message;
		}
		return t( 'offline' );
	}

	// Every class a state can paint with, so a binding can turn the old one
	// off: Alpine's object form removes the classes whose value is false.
	var BAR_CLASSES = {
		ok: 'bg-ink',
		off: 'bg-rule',
		yellow: 'bg-level-yellow',
		orange: 'bg-level-orange',
		red: 'bg-level-red',
	};

	var SQUARE_CLASSES = {
		clear: 'bg-level-clear',
		yellow: 'bg-level-yellow',
		orange: 'bg-level-orange',
		red: 'bg-level-red',
	};

	function classesFor( map, active ) {
		var out = {};
		Object.keys( map ).forEach( function ( key ) {
			out[ map[ key ] ] = key === active;
		} );
		return out;
	}

	/**
	 * Parse a form field name into its path:
	 * "thatseoagent_identity[social][twitter]" → ["thatseoagent_identity", "social", "twitter"].
	 */
	function namePath( name ) {
		var match = /^([^[\]]+)((?:\[[^\]]*\])*)$/.exec( name );
		if ( ! match ) {
			return [ name ];
		}
		var path = [ match[ 1 ] ];
		var re = /\[([^\]]*)\]/g;
		var part;
		while ( ( part = re.exec( match[ 2 ] ) ) ) {
			path.push( part[ 1 ] );
		}
		return path;
	}

	function setPath( target, path, value ) {
		var node = target;
		for ( var i = 0; i < path.length - 1; i++ ) {
			if ( typeof node[ path[ i ] ] !== 'object' || node[ path[ i ] ] === null ) {
				node[ path[ i ] ] = {};
			}
			node = node[ path[ i ] ];
		}
		// Later fields win, as in a regular form post: a hidden "0" followed
		// by a ticked checkbox's "1" submits "1".
		node[ path[ path.length - 1 ] ] = value;
	}

	var FORM_FIELDS = [ 'option_page', 'action', '_wpnonce', '_wp_http_referer', 'submit' ];

	document.addEventListener( 'alpine:init', function () {
		var Alpine = window.Alpine;

		/*
		 * The bulletin shown in the sidebar on every view. Refreshed after
		 * anything that can change it, such as saving the settings.
		 */
		Alpine.store( 'bulletin', Object.assign( {}, data.bulletin, {
			refresh: function () {
				var store = this;
				return api( { path: '/thatseoagent/v1/bulletin' } ).then( function ( bulletin ) {
					Object.assign( store, bulletin );
				} ).catch( function () {
					// The page still shows the last known state; nothing to undo.
				} );
			},
			// Method names must differ from the response's fields:
			// refresh() assigns the response over the store.
			barClass: function ( index ) {
				var observation = this.observations && this.observations[ index ];
				return classesFor( BAR_CLASSES, observation ? observation.state : 'off' );
			},
			squareClass: function () {
				return classesFor( SQUARE_CLASSES, this.level );
			},
		} ) );

		/*
		 * Moving between views without reloading the page.
		 *
		 * go() fetches a view with the X-ThatSeoAgent-View header — the
		 * server answers with the screen alone, as JSON — and swaps the
		 * navigation and the main column. Alpine's own mutation observer
		 * stops the components that leave and starts the ones that arrive,
		 * so nothing is initialised twice. History, the title and the
		 * sidebar's bulletin follow. Anything unexpected loads the page
		 * instead.
		 *
		 * `leave` is asked before the current view is replaced: a view with
		 * unsaved work sets it, and clears it when it goes.
		 */
		Alpine.store( 'router', {
			leave: null,
			loading: false,
			// The view on screen, without its hash.
			shown: window.location.href.split( '#' )[ 0 ],
			request: 0,

			go: function ( href, push ) {
				var store = this;

				if ( ! window.fetch || ! window.DOMParser ) {
					window.location.href = href;
					return;
				}

				if ( store.leave && ! store.leave() ) {
					if ( ! push ) {
						// Back or forward was cancelled: put the address back.
						window.history.pushState( {}, '', store.shown );
					}
					return;
				}

				var request = ++store.request;
				store.loading = true;

				window.fetch( href, {
					credentials: 'same-origin',
					headers: { 'X-ThatSeoAgent-View': '1', Accept: 'application/json' },
				} ).then( function ( response ) {
					if ( ! response.ok || -1 === ( response.headers.get( 'content-type' ) || '' ).indexOf( 'json' ) ) {
						throw new Error( 'not a view' );
					}
					return response.json();
				} ).then( function ( answer ) {
					// A later click won: this answer is stale.
					if ( request !== store.request ) {
						return;
					}

					// The plugin was updated since this page loaded: this script
					// is the old one, and the new view needs the new one.
					if ( answer.version && data.version && answer.version !== data.version ) {
						window.location.href = href;
						return;
					}

					var app = document.getElementById( 'thatseoagent-app' );
					var next = new window.DOMParser().parseFromString( answer.html, 'text/html' );
					var main = app && app.querySelector( 'main' );
					var nav = app && app.querySelector( 'aside nav' );
					var nextMain = next.querySelector( '#thatseoagent-app main' );
					var nextNav = next.querySelector( '#thatseoagent-app aside nav' );

					if ( ! main || ! nextMain ) {
						throw new Error( 'no view' );
					}

					// The address first: a view reads its hash as it starts.
					if ( push ) {
						window.history.pushState( {}, '', href );
					}
					store.shown = href.split( '#' )[ 0 ];

					main.replaceWith( document.importNode( nextMain, true ) );
					if ( nav && nextNav ) {
						nav.replaceWith( document.importNode( nextNav, true ) );
					}

					if ( answer.title ) {
						document.title = answer.title;
					}
					if ( answer.bulletin ) {
						Object.assign( Alpine.store( 'bulletin' ), answer.bulletin );
					}

					store.loading = false;

					// Once Alpine has started the new view.
					Alpine.nextTick( function () {
						store.arrive( new URL( href, window.location.href ).hash );
					} );
				} ).catch( function () {
					window.location.href = href;
				} );
			},

			// Scroll to the link's anchor, or to the top with focus on the
			// new view's title, where a screen reader should land.
			arrive: function ( hash ) {
				var target = hash ? document.getElementById( decodeURIComponent( hash.slice( 1 ) ) ) : null;

				if ( target ) {
					target.scrollIntoView();
					return;
				}

				window.scrollTo( 0, 0 );

				var heading = document.querySelector( '#thatseoagent-app main h1, #thatseoagent-app main h2' );
				if ( heading ) {
					heading.setAttribute( 'tabindex', '-1' );
					heading.focus( { preventScroll: true } );
				}
			},
		} );

		/*
		 * The screen itself, on #thatseoagent-app: sends clicks on links to
		 * another of its views, and back and forward, through the router.
		 */
		Alpine.data( 'tsaScreen', function () {
			return {
				follow: function ( event ) {
					if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
						return;
					}

					var link = event.target.closest( 'a' );
					if ( ! link || ! link.href || link.hasAttribute( 'download' ) || ( link.target && '_self' !== link.target ) ) {
						return;
					}

					var url = new URL( link.href, window.location.href );
					var isView = url.origin === window.location.origin &&
						/\/wp-admin\/admin\.php$/.test( url.pathname ) &&
						'thatseoagent' === url.searchParams.get( 'page' );

					// Another screen, or an anchor of this same view: the
					// browser does it.
					if ( ! isView || ( url.hash && url.href.split( '#' )[ 0 ] === this.$store.router.shown ) ) {
						return;
					}

					event.preventDefault();
					this.$store.router.go( url.href, true );
				},

				back: function () {
					// Back to an anchor of the view on screen only scrolls.
					if ( window.location.href.split( '#' )[ 0 ] !== this.$store.router.shown ) {
						this.$store.router.go( window.location.href, false );
					}
				},
			};
		} );

		/*
		 * One message at a time, bottom corner, announced to screen readers.
		 */
		Alpine.store( 'toast', {
			text: '',
			tone: 'info',
			visible: false,
			timer: null,
			show: function ( text, tone ) {
				var store = this;
				store.text = text;
				store.tone = tone || 'info';
				store.visible = true;
				clearTimeout( store.timer );
				store.timer = setTimeout( function () {
					store.visible = false;
				}, 6000 );
			},
			hide: function () {
				this.visible = false;
			},
		} );

		/*
		 * Day / Night. Applied at once; saved per user in the background.
		 */
		Alpine.data( 'tsaEdition', function () {
			return {
				theme: data.theme || 'light',

				choose: function ( theme ) {
					if ( theme === this.theme ) {
						return;
					}

					var previous = this.theme;
					var self = this;

					this.apply( theme );

					api( { path: '/thatseoagent/v1/preferences', method: 'POST', data: { theme: theme } } ).catch( function () {
						self.apply( previous );
						Alpine.store( 'toast' ).show( t( 'themeFailed' ), 'error' );
					} );
				},

				apply: function ( theme ) {
					this.theme = theme;
					document.body.classList.toggle( 'thatseoagent-theme-dark', 'dark' === theme );
					document.body.classList.toggle( 'thatseoagent-theme-light', 'light' === theme );
				},
			};
		} );

		/*
		 * Settings: saved through /wp/v2/settings — the same registered
		 * options and sanitizers as the form's post to options.php, which
		 * stays the fallback when scripts are off.
		 */
		Alpine.data( 'tsaSettings', function () {
			return {
				status: 'idle', // idle | dirty | saving | saved | error
				message: '',
				current: '',
				snapshot: '',

				init: function () {
					var self = this;
					var form = this.$refs.form;

					this.snapshot = JSON.stringify( this.values() );

					this.onEdit = function () {
						self.check();
					};
					form.addEventListener( 'input', this.onEdit );
					form.addEventListener( 'change', this.onEdit );
					// The media pickers set their hidden field without firing
					// an event; check again once the picker has closed.
					form.addEventListener( 'click', function () {
						setTimeout( self.onEdit, 300 );
					} );
					this.onFocus = function () {
						self.check();
					};
					window.addEventListener( 'focus', this.onFocus );

					this.onLeave = function ( event ) {
						if ( 'dirty' === self.status ) {
							event.preventDefault();
							event.returnValue = t( 'leave' );
						}
					};
					window.addEventListener( 'beforeunload', this.onLeave );

					// Moving to another view replaces this one without unloading
					// the page, so beforeunload never fires: ask here too.
					Alpine.store( 'router' ).leave = function () {
						return 'dirty' !== self.status || window.confirm( t( 'leave' ) );
					};

					this.watchSections();
				},

				destroy: function () {
					Alpine.store( 'router' ).leave = null;
					window.removeEventListener( 'beforeunload', this.onLeave );
					window.removeEventListener( 'focus', this.onFocus );
					if ( this.observer ) {
						this.observer.disconnect();
					}
				},

				// The section in view marks its entry in the index.
				watchSections: function () {
					var self = this;
					var sections = Array.prototype.slice.call( this.$root.querySelectorAll( 'form section[id]' ) );

					if ( ! sections.length || ! ( 'IntersectionObserver' in window ) ) {
						return;
					}

					this.current = window.location.hash ? window.location.hash.slice( 1 ) : sections[ 0 ].id;
					this.$root.setAttribute( 'data-spy', '' );

					this.observer = new IntersectionObserver( function ( entries ) {
						entries.forEach( function ( entry ) {
							if ( entry.isIntersecting ) {
								self.current = entry.target.id;
							}
						} );
					}, { rootMargin: '-15% 0px -70% 0px' } );

					sections.forEach( function ( section ) {
						self.observer.observe( section );
					} );
				},

				values: function () {
					var out = {};
					var entries = new FormData( this.$refs.form ).entries();
					var entry;

					while ( ! ( entry = entries.next() ).done ) {
						var name = entry.value[ 0 ];
						var value = entry.value[ 1 ];

						if ( FORM_FIELDS.indexOf( name ) !== -1 ) {
							continue;
						}

						var path = namePath( name );

						// Attachment fields are integers; a removed image leaves
						// an empty string the REST schema would reject.
						if ( '' === value && /_id$/.test( path[ path.length - 1 ] ) ) {
							value = 0;
						}

						setPath( out, path, value );
					}

					return out;
				},

				check: function () {
					if ( 'saving' === this.status ) {
						return;
					}

					var dirty = JSON.stringify( this.values() ) !== this.snapshot;

					if ( dirty ) {
						this.status = 'dirty';
						this.message = t( 'unsaved' );
					} else if ( 'dirty' === this.status ) {
						this.status = 'idle';
						this.message = '';
					}
				},

				save: function () {
					var self = this;
					var values = this.values();

					this.status = 'saving';
					this.message = t( 'saving' );

					return api( { path: '/wp/v2/settings', method: 'POST', data: values } ).then( function ( saved ) {
						var notes = [];

						// The IndexNow sanitizer keeps the old key instead of
						// storing an invalid one; say so rather than pretend.
						if ( undefined !== values.thatseoagent_indexnow_key ) {
							var sent = String( values.thatseoagent_indexnow_key ).replace( /[^a-zA-Z0-9-]/g, '' );
							if ( '' !== sent && saved.thatseoagent_indexnow_key !== sent ) {
								notes.push( t( 'indexnowKept' ) );
								var field = self.$refs.form.querySelector( '[name="thatseoagent_indexnow_key"]' );
								if ( field ) {
									field.value = saved.thatseoagent_indexnow_key || '';
								}
							}
						}

						self.snapshot = JSON.stringify( self.values() );
						self.status = notes.length ? 'error' : 'saved';
						self.message = notes.length ? notes.join( ' ' ) : t( 'saved' );

						Alpine.store( 'bulletin' ).refresh();
					} ).catch( function ( error ) {
						self.status = 'error';
						self.message = t( 'saveFailed' ) + ' ' + errorText( error );
					} );
				},
			};
		} );

		/*
		 * Content check: a batched run, a few posts per request.
		 */
		Alpine.data( 'tsaAudit', function ( initial ) {
			initial = initial || {};

			return {
				postType: initial.postType || '',
				// Labels for findings that are not Google's: source => label.
				sources: initial.sources || {},
				status: initial.last ? 'done' : 'idle', // idle | running | done | error
				token: '',
				total: initial.last ? initial.last.total : 0,
				checked: initial.last ? initial.last.total : 0,
				rows: initial.last ? initial.last.rows : [],
				finished: initial.last ? initial.last.finished : 0,
				filter: 'issues',
				// A few pages at a time, so the list stays short.
				page: 1,
				perPage: 20,
				error: '',
				loading: false,
				run: 0,

				get percent() {
					return this.total ? Math.round( ( 100 * this.checked ) / this.total ) : 0;
				},

				get sorted() {
					return this.rows.slice().sort( function ( a, b ) {
						return a.score - b.score;
					} );
				},

				get visible() {
					var rows = this.sorted;
					return 'issues' === this.filter ? rows.filter( function ( row ) {
						return row.issues.length > 0;
					} ) : rows;
				},

				get pages() {
					return Math.max( 1, Math.ceil( this.visible.length / this.perPage ) );
				},

				get pageRows() {
					var page = Math.min( this.page, this.pages );
					return this.visible.slice( ( page - 1 ) * this.perPage, page * this.perPage );
				},

				turn: function ( step ) {
					this.page = Math.min( this.pages, Math.max( 1, this.page + step ) );

					var list = this.$root.querySelector( '#thatseoagent-results' );
					if ( list ) {
						list.scrollIntoView( { block: 'start' } );
					}
				},

				get withIssues() {
					return this.rows.filter( function ( row ) {
						return row.issues.length > 0;
					} ).length;
				},

				get average() {
					if ( ! this.rows.length ) {
						return null;
					}
					var sum = this.rows.reduce( function ( total, row ) {
						return total + row.score;
					}, 0 );
					return Math.round( sum / this.rows.length );
				},

				get finishedText() {
					return this.finished ? new Date( this.finished * 1000 ).toLocaleString() : '';
				},

				// Each row carries the level its score is painted with
				// (ThatSeoAgent_Audit::level_for_score()).
				levelClass: function ( level ) {
					return classesFor( SQUARE_CLASSES, level );
				},

				start: function () {
					var self = this;
					// A restarted or stopped check must not keep appending
					// the rows of the batch it had in flight.
					var run = ++this.run;

					this.status = 'running';
					this.error = '';
					this.rows = [];
					this.page = 1;
					this.checked = 0;
					this.total = 0;

					var step = function ( progress ) {
						if ( run !== self.run || 'running' !== self.status ) {
							return null;
						}

						self.token = progress.token;
						self.total = progress.total;
						self.checked = progress.checked;
						Array.prototype.push.apply( self.rows, progress.rows );

						if ( 'done' === progress.status ) {
							// The list as stored, with the findings only the
							// whole run could make.
							if ( progress.all ) {
								self.rows = progress.all;
							}
							self.status = 'done';
							self.finished = Math.floor( Date.now() / 1000 );
							return null;
						}

						return api( { path: '/thatseoagent/v1/audit/runs/' + progress.token, method: 'POST' } ).then( step );
					};

					return api( { path: '/thatseoagent/v1/audit/runs', method: 'POST', data: { post_type: this.postType } } )
						.then( step )
						.catch( function ( error ) {
							if ( run !== self.run ) {
								return;
							}
							self.status = 'error';
							self.error = t( 'checkFailed' ) + ' ' + errorText( error );
						} );
				},

				stop: function () {
					var token = this.token;
					this.run++;
					this.status = this.rows.length ? 'done' : 'idle';
					this.finished = 0;
					if ( token ) {
						api( { path: '/thatseoagent/v1/audit/runs/' + token, method: 'DELETE' } ).catch( function () {} );
					}
				},

				// Another content type: show its last finished check, if any.
				switchType: function () {
					var self = this;

					this.run++;
					this.loading = true;
					this.error = '';

					return api( { path: '/thatseoagent/v1/audit?post_type=' + encodeURIComponent( this.postType ) } ).then( function ( answer ) {
						// null when this content type was never checked.
						var last = answer && answer.last;
						self.rows = last ? last.rows : [];
						self.page = 1;
						self.total = last ? last.total : 0;
						self.checked = self.total;
						self.finished = last ? last.finished : 0;
						self.status = last ? 'done' : 'idle';
					} ).catch( function ( error ) {
						self.status = 'error';
						self.error = errorText( error );
					} ).then( function () {
						self.loading = false;
					} );
				},
			};
		} );

		/*
		 * AI index: rebuild llms.txt on demand.
		 */
		Alpine.data( 'tsaLlms', function ( initial ) {
			initial = initial || {};

			return {
				body: initial.body || '',
				entries: initial.entries || 0,
				sections: initial.sections || [],
				busy: false,

				regenerate: function () {
					var self = this;

					this.busy = true;

					return api( { path: '/thatseoagent/v1/llms', method: 'POST' } ).then( function ( result ) {
						self.body = result.body;
						self.entries = result.entries;
						self.sections = result.sections;
						Alpine.store( 'toast' ).show( t( 'regenerated' ), 'ok' );
					} ).catch( function ( error ) {
						Alpine.store( 'toast' ).show( t( 'regenFailed' ) + ' ' + errorText( error ), 'error' );
					} ).then( function () {
						self.busy = false;
					} );
				},
			};
		} );

		/*
		 * Markdown for agents: the on-demand check.
		 */
		Alpine.data( 'tsaMarkdown', function ( initial ) {
			return {
				// The last check, kept on the server.
				result: initial || null,
				checking: false,
				busy: false,

				check: function () {
					var self = this;

					this.busy = true;
					this.checking = true;

					return api( { path: '/thatseoagent/v1/markdown/check', method: 'POST' } ).then( function ( result ) {
						self.result = result;
						Alpine.store( 'toast' ).show( t( 'mdChecked' ), 'ok' );
					} ).catch( function ( error ) {
						Alpine.store( 'toast' ).show( t( 'mdCheckFailed' ) + ' ' + errorText( error ), 'error' );
					} ).then( function () {
						self.busy = false;
						self.checking = false;
					} );
				},

				describe: function ( response ) {
					if ( ! response || ! response.status ) {
						return t( 'mdNoAnswer' );
					}
					var type = ( response.type || '' ).split( ';' )[ 0 ] || '—';
					return type + ' (' + response.status + ')' + ( response.vary ? ', Vary: Accept' : '' );
				},
			};
		} );

		/*
		 * Settings → Caches and CDN: the .htaccess rule that keeps page
		 * caches away from requests asking for Markdown.
		 */
		Alpine.data( 'tsaHtaccess', function ( initial ) {
			return {
				htaccess: initial || {},
				busy: false,

				install: function () {
					return this.write( 'POST', t( 'mdRuleAdded' ) );
				},

				remove: function () {
					return this.write( 'DELETE', t( 'mdRuleRemoved' ) );
				},

				write: function ( method, done ) {
					var self = this;

					this.busy = true;

					return api( { path: '/thatseoagent/v1/markdown/htaccess', method: method } ).then( function ( status ) {
						self.htaccess = status;
						Alpine.store( 'toast' ).show( done, 'ok' );
					} ).catch( function ( error ) {
						Alpine.store( 'toast' ).show( t( 'mdRuleFailed' ) + ' ' + errorText( error ), 'error' );
					} ).then( function () {
						self.busy = false;
					} );
				},

				text: function () {
					var h = this.htaccess;
					if ( ! h.applies ) {
						return t( 'mdRuleNotApache' );
					}
					if ( ! h.present ) {
						return t( 'mdRuleMissing' ) + ( h.writable ? '' : ' ' + t( 'mdRuleNotWritable' ) );
					}
					if ( h.below ) {
						return t( 'mdRuleBelow' ).replace( '%s', h.below );
					}
					return t( 'mdRuleOk' );
				},
			};
		} );

		/*
		 * AI crawlers: request the homepage as each crawler, on demand.
		 */
		Alpine.data( 'tsaCrawlers', function ( initial ) {
			return {
				// The last check, kept on the server.
				results: initial ? initial.bots : null,
				robots: initial ? initial.robots : null,
				when: initial ? initial.when : '',
				changes: initial ? initial.changes : [],
				busy: false,

				probe: function () {
					var self = this;

					this.busy = true;

					return api( { path: '/thatseoagent/v1/crawlers/probe', method: 'POST' } ).then( function ( result ) {
						self.results = result.bots;
						self.robots = result.robots;
						self.when = result.when;
						self.changes = result.changes;
						Alpine.store( 'toast' ).show( t( 'probeDone' ), 'ok' );
					} ).catch( function ( error ) {
						Alpine.store( 'toast' ).show( t( 'probeFailed' ) + ' ' + errorText( error ), 'error' );
					} ).then( function () {
						self.busy = false;
					} );
				},

				describe: function ( token ) {
					var result = this.results && this.results[ token ];
					if ( ! result ) {
						return '';
					}
					if ( ! result.status ) {
						return t( 'probeNoAnswer' );
					}
					return ( result.reached ? t( 'probeReached' ) : t( 'probeTurnedAway' ) ).replace( '%d', result.status );
				},
			};
		} );
	} );
}() );
