/**
 * Composant Alpine.js de la section "Comptabilité" (app frontend Limpeed).
 * Trois onglets indépendants sur une seule page (pas de drawer par ligne
 * ici, contrairement aux autres sections) :
 * - Bilan : produits (commissions)/charges/résultat d'une période choisie.
 * - Grand livre : vue consolidée paginée des encaissements, reversements et
 *   charges (lecture seule).
 * - Charges : CRUD complet des dépenses de l'agence (seule donnée réellement
 *   éditable de ce module).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-accounting.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedAccountingApp', function ( config ) {
		return {
			buildingOptions: config.buildingOptions || [],
			branchOptions: config.branchOptions || [],
			categories: config.categories || {},
			entryTypes: config.entryTypes || {},
			currentPeriod: config.currentPeriod || '',
			financialResultsUrlBase: config.financialResultsUrlBase || '',
			ledgerExportUrlBase: config.ledgerExportUrlBase || '',
			i18n: config.i18n || {},

			activeTab: 'bilan',
			tabsLoaded: { bilan: false, ledger: false, expenses: false },

			// Onglet Bilan.
			bilan: {
				loading: true,
				period: config.currentPeriod || '',
				data: null,
			},

			// Onglet Grand livre.
			ledger: {
				loading: true,
				items: [],
				total: 0,
				totalPages: 1,
				paged: 1,
				entryType: '',
				period: '',
				search: '',
				searchTimer: null,
			},

			// Onglet Charges.
			expenses: {
				loading: true,
				items: [],
				total: 0,
				totalPages: 1,
				paged: 1,
				search: '',
				category: '',
				searchTimer: null,
			},

			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				expenseId: 0,
				data: createDefaultExpenseData(),
			},

			toasts: [],
			toastSeq: 0,

			init: function () {
				this.fetchBilan();
			},

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			switchTab: function ( tab ) {
				this.activeTab = tab;
				if ( this.tabsLoaded[ tab ] ) {
					return;
				}
				if ( 'ledger' === tab ) {
					this.fetchLedger();
				} else if ( 'expenses' === tab ) {
					this.fetchExpenses();
				}
			},

			// -----------------------------------------------------------
			// Onglet Bilan.
			// -----------------------------------------------------------
			fetchBilan: function () {
				var self = this;
				self.bilan.loading = true;

				return self.apiFetch( 'accounting/summary?period=' + encodeURIComponent( self.bilan.period ) )
					.then( function ( body ) {
						self.bilan.data = body;
						self.tabsLoaded.bilan = true;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.bilan.loading = false;
					} );
			},

			onBilanPeriodChange: function () {
				this.fetchBilan();
			},

			financialResultsUrl: function () {
				if ( ! this.financialResultsUrlBase ) {
					return '#';
				}
				var separator = this.financialResultsUrlBase.indexOf( '?' ) === -1 ? '?' : '&';
				return this.financialResultsUrlBase + separator + 'period=' + encodeURIComponent( this.bilan.period );
			},

			// -----------------------------------------------------------
			// Export CSV du grand livre (respecte les filtres courants de
			// l'onglet Grand livre).
			// -----------------------------------------------------------
			ledgerExportUrl: function () {
				if ( ! this.ledgerExportUrlBase ) {
					return '#';
				}
				var separator = this.ledgerExportUrlBase.indexOf( '?' ) === -1 ? '?' : '&';
				return this.ledgerExportUrlBase + separator + new URLSearchParams( {
					entry_type: this.ledger.entryType,
					period: this.ledger.period,
					search: this.ledger.search,
				} ).toString();
			},

			// -----------------------------------------------------------
			// Onglet Grand livre.
			// -----------------------------------------------------------
			fetchLedger: function () {
				var self = this;
				self.ledger.loading = true;

				var query = new URLSearchParams( {
					entry_type: self.ledger.entryType,
					period: self.ledger.period,
					search: self.ledger.search,
					paged: self.ledger.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'accounting/ledger?' + query )
					.then( function ( body ) {
						self.ledger.items = body.items || [];
						self.ledger.total = body.total || 0;
						self.ledger.totalPages = body.total_pages || 1;
						self.ledger.paged = body.paged || 1;
						self.tabsLoaded.ledger = true;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.ledger.loading = false;
					} );
			},

			onLedgerSearchInput: function () {
				var self = this;
				clearTimeout( self.ledger.searchTimer );
				self.ledger.searchTimer = setTimeout( function () {
					self.ledger.paged = 1;
					self.fetchLedger();
				}, 300 );
			},

			onLedgerFilterChange: function () {
				this.ledger.paged = 1;
				this.fetchLedger();
			},

			goToLedgerPage: function ( page ) {
				if ( page < 1 || page > this.ledger.totalPages ) {
					return;
				}
				this.ledger.paged = page;
				this.fetchLedger();
			},

			// -----------------------------------------------------------
			// Onglet Charges — liste.
			// -----------------------------------------------------------
			fetchExpenses: function () {
				var self = this;
				self.expenses.loading = true;

				var query = new URLSearchParams( {
					search: self.expenses.search,
					category: self.expenses.category,
					paged: self.expenses.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'expenses?' + query )
					.then( function ( body ) {
						self.expenses.items = body.items || [];
						self.expenses.total = body.total || 0;
						self.expenses.totalPages = body.total_pages || 1;
						self.expenses.paged = body.paged || 1;
						self.tabsLoaded.expenses = true;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.expenses.loading = false;
					} );
			},

			onExpensesSearchInput: function () {
				var self = this;
				clearTimeout( self.expenses.searchTimer );
				self.expenses.searchTimer = setTimeout( function () {
					self.expenses.paged = 1;
					self.fetchExpenses();
				}, 300 );
			},

			onExpensesFilterChange: function () {
				this.expenses.paged = 1;
				this.fetchExpenses();
			},

			goToExpensesPage: function ( page ) {
				if ( page < 1 || page > this.expenses.totalPages ) {
					return;
				}
				this.expenses.paged = page;
				this.fetchExpenses();
			},

			// -----------------------------------------------------------
			// Modale ajout / modification d'une charge.
			// -----------------------------------------------------------
			openAddModal: function () {
				this.modal.mode = 'add';
				this.modal.expenseId = 0;
				this.modal.errors = [];
				this.modal.data = createDefaultExpenseData();
				this.modal.open = true;
			},

			openEditModal: function ( row ) {
				this.modal.mode = 'edit';
				this.modal.expenseId = row.id;
				this.modal.errors = [];
				this.modal.data = {
					expense_date: row.expense_date || '',
					category: row.category || 'autre',
					label: row.label || '',
					amount: row.amount || 0,
					building_id: row.building_id || '',
					branch_id: row.branch_id || '',
					notes: row.notes || '',
				};
				this.modal.open = true;
			},

			closeModal: function () {
				this.modal.open = false;
			},

			saveExpense: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'expenses/' + self.modal.expenseId : 'expenses';

				self.apiFetch( path, {
					method: isEdit ? 'PUT' : 'POST',
					body: JSON.stringify( self.modal.data ),
				} )
					.then( function () {
						self.modal.open = false;
						self.toast( 'success', isEdit ? self.i18n.expenseUpdated : self.i18n.expenseCreated );
						self.fetchExpenses();
						if ( self.tabsLoaded.ledger ) {
							self.fetchLedger();
						}
						if ( self.tabsLoaded.bilan ) {
							self.fetchBilan();
						}
					} )
					.catch( function ( error ) {
						self.modal.errors = [ error.message ];
					} )
					.finally( function () {
						self.modal.saving = false;
					} );
			},

			deleteExpense: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'expenses/' + row.id, { method: 'DELETE' } )
					.then( function () {
						self.toast( 'success', self.i18n.expenseDeleted );
						self.fetchExpenses();
						if ( self.tabsLoaded.ledger ) {
							self.fetchLedger();
						}
						if ( self.tabsLoaded.bilan ) {
							self.fetchBilan();
						}
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			// -----------------------------------------------------------
			// Notifications toast.
			// -----------------------------------------------------------
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

		function createDefaultExpenseData() {
			return {
				expense_date: config.currentDate || '',
				category: 'autre',
				label: '',
				amount: '',
				building_id: '',
				branch_id: '',
				notes: '',
			};
		}
	} );
} );
