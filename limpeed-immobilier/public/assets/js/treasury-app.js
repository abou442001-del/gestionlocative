/**
 * Composant Alpine.js de l'onglet "Caisses" de la section Trésorerie (app
 * frontend Limpeed). L'onglet "Vue d'ensemble" reste un rapport statique
 * rendu côté serveur (aucun fetch nécessaire) ; seul l'onglet "Caisses" est
 * dynamique : solde de chaque caisse + historique/ajout de mouvements dans
 * un panneau de détail (drawer), sur le même principe que le drawer
 * locataire de tenants-app.js.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-treasury.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedTreasuryApp', function ( config ) {
		return {
			categories: config.categories || {},
			i18n: config.i18n || {},

			activeTab: 'overview',
			tabsLoaded: { caisses: false },

			funds: {
				loading: true,
				items: [],
				total: 0,
				totalLabel: '',
			},

			drawer: {
				open: false,
				loading: false,
				category: '',
				label: '',
				balanceLabel: '',
				items: [],
				total: 0,
				totalPages: 1,
				paged: 1,
			},

			form: createDefaultTransactionForm(),

			toasts: [],
			toastSeq: 0,

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			switchTab: function ( tab ) {
				this.activeTab = tab;
				if ( this.tabsLoaded[ tab ] ) {
					return;
				}
				if ( 'caisses' === tab ) {
					this.fetchBalances();
				}
			},

			fetchBalances: function () {
				var self = this;
				self.funds.loading = true;

				return self.apiFetch( 'funds/balances' )
					.then( function ( body ) {
						self.funds.items = body.items || [];
						self.funds.total = body.total || 0;
						self.funds.totalLabel = body.total_label || '';
						self.tabsLoaded.caisses = true;

						if ( self.drawer.open ) {
							var current = self.funds.items.find( function ( item ) {
								return item.key === self.drawer.category;
							} );
							if ( current ) {
								self.drawer.balanceLabel = current.balance_label;
							}
						}
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.funds.loading = false;
					} );
			},

			openDrawer: function ( item ) {
				var self = this;
				self.drawer.open = true;
				self.drawer.category = item.key;
				self.drawer.label = item.label;
				self.drawer.balanceLabel = item.balance_label;
				self.drawer.paged = 1;
				self.form = createDefaultTransactionForm();
				self.fetchDrawerTransactions();
			},

			fetchDrawerTransactions: function () {
				var self = this;
				self.drawer.loading = true;

				var query = new URLSearchParams( {
					category: self.drawer.category,
					paged: self.drawer.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'funds/transactions?' + query )
					.then( function ( body ) {
						self.drawer.items = body.items || [];
						self.drawer.total = body.total || 0;
						self.drawer.totalPages = body.total_pages || 1;
						self.drawer.paged = body.paged || 1;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
						self.drawer.open = false;
					} )
					.finally( function () {
						self.drawer.loading = false;
					} );
			},

			goToDrawerPage: function ( page ) {
				if ( page < 1 || page > this.drawer.totalPages ) {
					return;
				}
				this.drawer.paged = page;
				this.fetchDrawerTransactions();
			},

			closeDrawer: function () {
				this.drawer.open = false;
			},

			addTransaction: function () {
				var self = this;
				if ( self.form.saving ) {
					return;
				}
				self.form.saving = true;
				self.form.errors = [];

				self.apiFetch( 'funds/transactions', {
					method: 'POST',
					body: JSON.stringify( {
						fund_category: self.drawer.category,
						direction: self.form.direction,
						amount: self.form.amount,
						label: self.form.label,
						transaction_date: self.form.transaction_date,
					} ),
				} )
					.then( function () {
						self.form = createDefaultTransactionForm();
						self.drawer.paged = 1;
						return Promise.all( [ self.fetchDrawerTransactions(), self.fetchBalances() ] );
					} )
					.then( function () {
						self.toast( 'success', self.i18n.transactionAdded );
					} )
					.catch( function ( error ) {
						self.form.errors = [ error.message ];
					} )
					.finally( function () {
						self.form.saving = false;
					} );
			},

			deleteTransaction: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'funds/transactions/' + row.id, { method: 'DELETE' } )
					.then( function () {
						self.toast( 'success', self.i18n.transactionDeleted );
						return Promise.all( [ self.fetchDrawerTransactions(), self.fetchBalances() ] );
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			toast: function ( type, message ) {
				if ( ! message ) {
					return;
				}
				var self = this;
				var id = ++self.toastSeq;
				self.toasts.push( { id: id, type: type, message: message } );
				setTimeout( function () {
					self.toasts = self.toasts.filter( function ( t ) {
						return t.id !== id;
					} );
				}, 4000 );
			},
		};

		function createDefaultTransactionForm() {
			return {
				direction: 'in',
				amount: '',
				label: '',
				transaction_date: config.currentDate || '',
				saving: false,
				errors: [],
			};
		}
	} );
} );
