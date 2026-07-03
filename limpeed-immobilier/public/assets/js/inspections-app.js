/**
 * Composant Alpine.js de la section "États des lieux" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'ajout/modification avec sélection
 * du locataire (le bien est déduit automatiquement) et répétiteur de pièces,
 * actions Ajax, panneau de détail (Infos / Historique) avec téléchargement
 * PDF et comparaison entrée/sortie.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-inspections.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedInspectionsApp', function ( config ) {
		return {
			types: config.types || {},
			roomConditions: config.roomConditions || {},
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			filterType: '',
			filterTenantId: config.presetTenantId || '',

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				inspectionId: 0,
				data: createDefaultInspectionData(),
				tenants: [],
				loadingTenants: false,
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				inspection: null,
				history: [],
				comparison: null,
				loadingComparison: false,
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
					tenant_id: self.filterTenantId,
					type: self.filterType,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'inspections?' + query )
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
						self.modal.tenants = body.items || [];
					} )
					.finally( function () {
						self.modal.loadingTenants = false;
					} );
			},

			onTenantChange: function () {
				var self = this;
				var tenant = self.modal.tenants.find( function ( t ) {
					return String( t.id ) === String( self.modal.data.tenant_id );
				} );
				self.modal.data.property_id = tenant ? tenant.property_id : '';
			},

			openAddModal: function () {
				this.modal.mode = 'add';
				this.modal.inspectionId = 0;
				this.modal.errors = [];
				this.modal.data = createDefaultInspectionData();
				this.modal.open = true;
				this.loadTenants();
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.inspectionId = row.id;
				self.modal.errors = [];
				self.modal.open = true;

				self.loadTenants();
				self.apiFetch( 'inspections/' + row.id )
					.then( function ( inspection ) {
						self.modal.data = {
							tenant_id: inspection.tenant_id || '',
							property_id: inspection.property_id || '',
							type: inspection.type || 'entree',
							inspection_date: inspection.inspection_date || '',
							rooms: inspection.rooms && inspection.rooms.length ? inspection.rooms : [ createDefaultRoom() ],
							general_notes: inspection.general_notes || '',
						};
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			closeModal: function () {
				this.modal.open = false;
			},

			addRoom: function () {
				this.modal.data.rooms.push( createDefaultRoom() );
			},

			removeRoom: function ( index ) {
				this.modal.data.rooms.splice( index, 1 );
			},

			saveInspection: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'inspections/' + self.modal.inspectionId : 'inspections';

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
			deleteInspection: function ( row ) {
				var self = this;
				if ( ! window.confirm( self.i18n.confirmDelete || 'Confirmez-vous la suppression ?' ) ) {
					return;
				}

				self.apiFetch( 'inspections/' + row.id, { method: 'DELETE' } )
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
				self.drawer.inspection = null;
				self.drawer.history = [];
				self.drawer.comparison = null;

				Promise.all( [
					self.apiFetch( 'inspections/' + row.id ),
					self.apiFetch( 'inspections/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.inspection = results[0];
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
				if ( 'comparaison' === tab && ! this.drawer.comparison ) {
					this.loadComparison();
				}
			},

			loadComparison: function () {
				var self = this;
				if ( ! self.drawer.inspection ) {
					return;
				}
				self.drawer.loadingComparison = true;
				self.apiFetch( 'tenants/' + self.drawer.inspection.tenant_id + '/inspection-comparison' )
					.then( function ( body ) {
						self.drawer.comparison = body;
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.drawer.loadingComparison = false;
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

		function createDefaultRoom() {
			return { name: '', condition: 'bon', notes: '' };
		}

		function createDefaultInspectionData() {
			return {
				tenant_id: '',
				property_id: '',
				type: 'entree',
				inspection_date: new Date().toISOString().slice( 0, 10 ),
				rooms: [ createDefaultRoom() ],
				general_notes: '',
			};
		}
	} );
} );
