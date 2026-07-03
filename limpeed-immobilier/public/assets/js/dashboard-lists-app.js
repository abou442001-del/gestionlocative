/**
 * Composant Alpine.js des 4 listes du Tableau de bord (derniers locataires,
 * derniers propriétaires, quittances soldées, quittances en attente) :
 * recherche et pagination réelles en Ajax, au lieu d'un instantané statique
 * "10 derniers" calculé au chargement de la page.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-dashboard.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	function makeList( endpoint, extraParams ) {
		return {
			endpoint: endpoint,
			extraParams: extraParams || {},
			search: '',
			perPage: 5,
			page: 1,
			items: [],
			total: 0,
			totalPages: 1,
			loading: true,
			searchTimer: null,
		};
	}

	Alpine.data( 'limpeedDashboardListsApp', function ( config ) {
		return {
			i18n: config.i18n || {},

			lists: {
				tenants: makeList( 'tenants', { with_balance: 1 } ),
				owners: makeList( 'owners', {} ),
				paid: makeList( 'payments', { status: 'paye' } ),
				unpaid: makeList( 'dashboard/unpaid-tenants', {} ),
			},

			init: function () {
				var self = this;
				Object.keys( this.lists ).forEach( function ( key ) {
					self.fetchList( key );
				} );
			},

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			buildQuery: function ( list ) {
				var params = Object.assign( {}, list.extraParams, {
					search: list.search,
					paged: list.page,
					per_page: list.perPage,
				} );
				return Object.keys( params )
					.filter( function ( key ) { return params[ key ] !== '' && params[ key ] !== null && typeof params[ key ] !== 'undefined'; } )
					.map( function ( key ) { return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] ); } )
					.join( '&' );
			},

			fetchList: function ( key ) {
				var self = this;
				var list = this.lists[ key ];
				list.loading = true;

				return self.apiFetch( list.endpoint + '?' + self.buildQuery( list ) )
					.then( function ( body ) {
						list.items      = body.items || [];
						list.total      = body.total || 0;
						list.totalPages = body.total_pages || 1;
						if ( list.page > list.totalPages ) {
							list.page = list.totalPages;
						}
					} )
					.catch( function () {
						list.items = [];
					} )
					.finally( function () {
						list.loading = false;
					} );
			},

			onSearchInput: function ( key ) {
				var self = this;
				var list = this.lists[ key ];
				clearTimeout( list.searchTimer );
				list.searchTimer = setTimeout( function () {
					list.page = 1;
					self.fetchList( key );
				}, 350 );
			},

			onPerPageChange: function ( key ) {
				this.lists[ key ].page = 1;
				this.fetchList( key );
			},

			goToPage: function ( key, page ) {
				var list = this.lists[ key ];
				if ( page < 1 || page > list.totalPages || page === list.page ) {
					return;
				}
				list.page = page;
				this.fetchList( key );
			},

			pageNumbers: function ( key ) {
				var list  = this.lists[ key ];
				var total = list.totalPages;
				var current = list.page;
				var span = 2;
				var start = Math.max( 1, current - span );
				var end   = Math.min( total, current + span );
				var pages = [];
				for ( var i = start; i <= end; i++ ) {
					pages.push( i );
				}
				return pages;
			},

			rangeLabel: function ( key ) {
				var list = this.lists[ key ];
				if ( list.total === 0 ) {
					return this.i18n.noResults || '';
				}
				var start = ( list.page - 1 ) * list.perPage + 1;
				var end   = Math.min( list.total, list.page * list.perPage );
				return ( this.i18n.showingRange || '%1$d–%2$d sur %3$d' )
					.replace( '%1$d', start )
					.replace( '%2$d', end )
					.replace( '%3$d', list.total );
			},
		};
	} );
} );
