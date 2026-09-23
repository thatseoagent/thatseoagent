/**
 * Lean SEO screen: the interactive layer.
 *
 * Every view is server-rendered and works without this file; Alpine.js adds
 * saving without reloads, the batched content check, filters and the live
 * sidebar. Components start from the state the page was rendered with
 * (window.leanSeo, printed before this file), so nothing is fetched on load.
 *
 * Load order: this file, then Alpine (assets/vendor/alpine.min.js), both
 * deferred. Everything registers on `alpine:init`, which Alpine fires before
 * it walks the page.
 *
 * Requests go through wp.apiFetch, which adds the REST nonce.
 */
( function () {
	'use strict';

	var data = window.leanSeo || {};

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
	 * "lean_seo_identity[social][twitter]" → ["lean_seo_identity", "social", "twitter"].
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
				return api( { path: '/lean-seo/v1/bulletin' } ).then( function ( bulletin ) {
					Object.assign( store, bulletin );
				} ).catch( function () {
					// The page still shows the last known state; nothing to undo.
				} );
			},
			// Method names differ from the response's fields (square, …):
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
		Alpine.data( 'lsEdition', function () {
			return {
				theme: data.theme || 'light',

				choose: function ( theme ) {
					if ( theme === this.theme ) {
						return;
					}

					var previous = this.theme;
					var self = this;

					this.apply( theme );

					api( { path: '/lean-seo/v1/preferences', method: 'POST', data: { theme: theme } } ).catch( function () {
						self.apply( previous );
						Alpine.store( 'toast' ).show( t( 'themeFailed' ), 'error' );
					} );
				},

				apply: function ( theme ) {
					this.theme = theme;
					document.body.classList.toggle( 'lean-seo-theme-dark', 'dark' === theme );
					document.body.classList.toggle( 'lean-seo-theme-light', 'light' === theme );
				},
			};
		} );

		/*
		 * Settings: saved through /wp/v2/settings — the same registered
		 * options and sanitizers as the form's post to options.php, which
		 * stays the fallback when scripts are off.
		 */
		Alpine.data( 'lsSettings', function () {
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

					this.watchSections();
				},

				destroy: function () {
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
						if ( undefined !== values.lean_seo_indexnow_key ) {
							var sent = String( values.lean_seo_indexnow_key ).replace( /[^a-zA-Z0-9-]/g, '' );
							if ( '' !== sent && saved.lean_seo_indexnow_key !== sent ) {
								notes.push( t( 'indexnowKept' ) );
								var field = self.$refs.form.querySelector( '[name="lean_seo_indexnow_key"]' );
								if ( field ) {
									field.value = saved.lean_seo_indexnow_key || '';
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
		Alpine.data( 'lsAudit', function ( initial ) {
			initial = initial || {};

			return {
				postType: initial.postType || '',
				status: initial.last ? 'done' : 'idle', // idle | running | done | error
				token: '',
				total: initial.last ? initial.last.total : 0,
				checked: initial.last ? initial.last.total : 0,
				rows: initial.last ? initial.last.rows : [],
				finished: initial.last ? initial.last.finished : 0,
				filter: 'issues',
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

				scoreClass: function ( score ) {
					var level = score >= 80 ? 'clear' : ( score >= 60 ? 'yellow' : ( score >= 40 ? 'orange' : 'red' ) );
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
							self.status = 'done';
							self.finished = Math.floor( Date.now() / 1000 );
							return null;
						}

						return api( { path: '/lean-seo/v1/audit/runs/' + progress.token, method: 'POST' } ).then( step );
					};

					return api( { path: '/lean-seo/v1/audit/runs', method: 'POST', data: { post_type: this.postType } } )
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
						api( { path: '/lean-seo/v1/audit/runs/' + token, method: 'DELETE' } ).catch( function () {} );
					}
				},

				// Another content type: show its last finished check, if any.
				switchType: function () {
					var self = this;

					this.run++;
					this.loading = true;
					this.error = '';

					return api( { path: '/lean-seo/v1/audit?post_type=' + encodeURIComponent( this.postType ) } ).then( function ( last ) {
						self.rows = last ? last.rows : [];
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
		 * Products: filter the rows on this page by state.
		 */
		Alpine.data( 'lsProducts', function () {
			return {
				filter: 'all', // all | attention | complete

				shows: function ( level ) {
					if ( 'all' === this.filter ) {
						return true;
					}
					return 'complete' === this.filter ? 'clear' === level : 'clear' !== level;
				},
			};
		} );

		/*
		 * AI index: rebuild llms.txt on demand.
		 */
		Alpine.data( 'lsLlms', function ( initial ) {
			initial = initial || {};

			return {
				body: initial.body || '',
				entries: initial.entries || 0,
				sections: initial.sections || [],
				busy: false,

				regenerate: function () {
					var self = this;

					this.busy = true;

					return api( { path: '/lean-seo/v1/llms', method: 'POST' } ).then( function ( result ) {
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
	} );
}() );
