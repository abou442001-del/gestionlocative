/**
 * Composant Alpine.js de la section "Paiements" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'enregistrement rapide (locataire
 * peuplé en Ajax), actions Ajax, panneau de détail (Infos / Historique).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-payments.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedPaymentsApp', function ( config ) {
		return {
			// Configuration initiale (rendue côté serveur).
			propertyOptions: config.propertyOptions || [],
			paymentStatuses: config.paymentStatuses || {},
			paymentMethods: config.paymentMethods || {},
			currentPeriod: config.currentPeriod || '',
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			search: '',
			// filterTenantId n'a pas de sélecteur dans la barre d'outils : il ne
			// sert qu'à conserver le filtre reçu par navigation croisée depuis
			// une fiche locataire ("Voir l'historique des paiements").
			filterTenantId: config.presetTenantId || '',
			filterPropertyId: config.presetPropertyId || '',
			filterPeriod: '',
			filterStatus: '',
			searchTimer: null,

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				paymentId: 0,
				data: createDefaultPaymentData(),
				tenants: [],
				loadingTenants: false,
				commissionFormatted: '',
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				payment: null,
				history: [],
			},

			toasts: [],
			toastSeq: 0,

			init: function () {
				this.fetchList();
			},

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			// -----------------------------------------------------------
			// Liste.
			// -----------------------------------------------------------
			fetchList: function () {
				var self = this;
				self.loading = true;
				self.items = [];

				var query = new URLSearchParams( {
					search: self.search,
					tenant_id: self.filterTenantId,
					property_id: self.filterPropertyId,
					period: self.filterPeriod,
					status: self.filterStatus,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'payments?' + query )
					.then( function ( body ) {
						self.items = body.items || [];
						self.total = body.total || 0;
						self.totalPages = body.total_pages || 1;
						self.paged = body.paged || 1;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.loading = false;
					} );
			},

			onSearchInput: function () {
				var self = this;
				clearTimeout( self.searchTimer );
				self.searchTimer = setTimeout( function () {
					self.paged = 1;
					self.fetchList();
				}, 300 );
			},

			onFilterChange: function () {
				this.paged = 1;
				this.fetchList();
			},

			goToPage: function ( page ) {
				if ( page < 1 || page > this.totalPages ) {
					return;
				}
				this.paged = page;
				this.fetchList();
			},

			// -----------------------------------------------------------
			// Modale ajout / modification.
			// -----------------------------------------------------------
			loadTenants: function () {
				var self = this;
				self.modal.loadingTenants = true;
				return self.apiFetch( 'tenants?per_page=500' )
					.then( function ( body ) {
						self.modal.tenants = ( body.items || [] ).map( function ( tenant ) {
							return {
								id: tenant.id,
								label: tenant.property_label ? ( tenant.full_name + ' — ' + tenant.property_label ) : tenant.full_name,
							};
						} );
					} )
					.finally( function () {
						self.modal.loadingTenants = false;
					} );
			},

			openAddModal: function ( presetTenantId, presetPeriod ) {
				var self = this;
				self.modal.mode = 'add';
				self.modal.paymentId = 0;
				self.modal.errors = [];
				self.modal.commissionFormatted = '';
				self.modal.data = createDefaultPaymentData();
				if ( presetTenantId ) {
					self.modal.data.tenant_id = presetTenantId;
				}
				if ( presetPeriod ) {
					self.modal.data.period = presetPeriod;
				}
				self.modal.open = true;
				self.loadTenants();
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.paymentId = row.id;
				self.modal.errors = [];
				self.modal.open = true;

				self.loadTenants();
				self.apiFetch( 'payments/' + row.id )
					.then( function ( payment ) {
						self.modal.data = {
							tenant_id: payment.tenant_id || '',
							period: payment.period || '',
							amount: payment.amount || 0,
							payment_date: payment.payment_date ? payment.payment_date.substring( 0, 10 ) : '',
							payment_method: payment.payment_method || 'especes',
							status: payment.status || 'paye',
						};
						self.modal.commissionFormatted = payment.commission_formatted || '';
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			closeModal: function () {
				this.modal.open = false;
			},

			savePayment: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'payments/' + self.modal.paymentId : 'payments';

				self.apiFetch( path, {
					method: isEdit ? 'PUT' : 'POST',
					body: JSON.stringify( self.modal.data ),
				} )
					.then( function () {
						self.modal.open = false;
						self.toast( 'success', isEdit ? self.i18n.updated : self.i18n.created );
						self.fetchList();
					} )
					.catch( function ( error ) {
						self.modal.errors = [ error.message ];
					} )
					.finally( function () {
						self.modal.saving = false;
					} );
			},

			// -----------------------------------------------------------
			// Suppression.
			// -----------------------------------------------------------
			deletePayment: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'payments/' + row.id, { method: 'DELETE' } )
					.then( function () {
						self.toast( 'success', self.i18n.deleted );
						self.fetchList();
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			// -----------------------------------------------------------
			// Panneau de détail (drawer).
			// -----------------------------------------------------------
			openDrawer: function ( row ) {
				var self = this;
				self.drawer.open = true;
				self.drawer.tab = 'infos';
				self.drawer.loading = true;
				self.drawer.payment = null;
				self.drawer.history = [];

				Promise.all( [
					self.apiFetch( 'payments/' + row.id ),
					self.apiFetch( 'payments/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.payment = results[0];
						self.drawer.history = results[1].items || [];
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
						self.drawer.open = false;
					} )
					.finally( function () {
						self.drawer.loading = false;
					} );
			},

			closeDrawer: function () {
				this.drawer.open = false;
			},

			switchDrawerTab: function ( tab ) {
				this.drawer.tab = tab;
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

		function createDefaultPaymentData() {
			return {
				tenant_id: '',
				period: config.currentPeriod || '',
				amount: 0,
				payment_date: '',
				payment_method: 'especes',
				status: 'paye',
			};
		}
	} );
} );
