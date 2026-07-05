/**
 * Composant Alpine.js de la section "Locataires" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'ajout/modification avec cascade
 * Propriétaire → Édifice → Sous-édifice, actions Ajax, panneau de détail.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-tenants.php)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedTenantsApp', function ( config ) {
		return {
			// Configuration initiale (rendue côté serveur).
			statuses: config.statuses || {},
			idDocumentTypes: config.idDocumentTypes || {},
			propertyOptions: config.propertyOptions || [],
			monthNames: config.monthNames || [],
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			search: '',
			filterPropertyId: '',
			filterStatus: '',
			searchTimer: null,

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				tenantId: 0,
				data: createDefaultTenantData(),
				owners: [],
				buildings: [],
				properties: [],
				loadingCascade: false,
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				tenant: null,
				amendments: [],
				history: [],
				calendar: [],
				calendarYear: new Date().getFullYear(),
				calendarLoading: false,
			},

			amendmentForm: createDefaultAmendmentForm(),

			toasts: [],
			toastSeq: 0,

			init: function () {
				this.fetchList();
			},

			// -----------------------------------------------------------
			// Requêtes REST (voir limpeed-rest-client.js).
			// -----------------------------------------------------------
			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			fetchList: function () {
				var self = this;
				self.loading = true;
				self.items = [];

				var query = new URLSearchParams( {
					search: self.search,
					property_id: self.filterPropertyId,
					status: self.filterStatus,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'tenants?' + query )
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
			openAddModal: function () {
				var self = this;
				self.modal.mode = 'add';
				self.modal.tenantId = 0;
				self.modal.errors = [];
				self.modal.data = createDefaultTenantData();
				self.modal.buildings = [];
				self.modal.properties = [];
				self.modal.open = true;
				self.loadOwners();
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.tenantId = row.id;
				self.modal.errors = [];
				self.modal.open = true;
				self.modal.loadingCascade = true;

				self.apiFetch( 'tenants/' + row.id )
					.then( function ( tenant ) {
						self.modal.data = {
							owner_id: tenant.owner_id || '',
							building_id: tenant.building_id || '',
							property_id: tenant.property_id || '',
							full_name: tenant.full_name || '',
							phone: tenant.phone || '',
							email: tenant.email || '',
							lease_start: tenant.lease_start || '',
							lease_end: tenant.lease_end || '',
							rent_amount: tenant.rent_amount || 0,
							deposit_paid: tenant.deposit_paid || 0,
							status: tenant.status || 'actif',
							id_document_type: tenant.id_document_type || '',
							id_document_number: tenant.id_document_number || '',
							date_of_birth: tenant.date_of_birth || '',
							profession: tenant.profession || '',
							dependents_count: tenant.dependents_count || 0,
							guarantor_name: tenant.guarantor_name || '',
							guarantor_phone: tenant.guarantor_phone || '',
							is_new_tenant: !! tenant.is_new_tenant,
						};
						return Promise.all( [
							self.loadOwners(),
							self.loadBuildings( self.modal.data.owner_id ),
							self.loadProperties( self.modal.data.building_id ),
						] );
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.modal.loadingCascade = false;
					} );
			},

			closeModal: function () {
				this.modal.open = false;
			},

			loadOwners: function () {
				var self = this;
				return self.apiFetch( 'owners' ).then( function ( body ) {
					self.modal.owners = body.items || [];
				} );
			},

			loadBuildings: function ( ownerId ) {
				var self = this;
				if ( ! ownerId ) {
					self.modal.buildings = [];
					return Promise.resolve();
				}
				return self.apiFetch( 'buildings?owner_id=' + encodeURIComponent( ownerId ) ).then( function ( body ) {
					self.modal.buildings = body.items || [];
				} );
			},

			loadProperties: function ( buildingId ) {
				var self = this;
				if ( ! buildingId ) {
					self.modal.properties = [];
					return Promise.resolve();
				}
				var query = 'building_id=' + encodeURIComponent( buildingId ) + '&tenant_id=' + encodeURIComponent( self.modal.tenantId || 0 );
				return self.apiFetch( 'properties?' + query ).then( function ( body ) {
					self.modal.properties = body.items || [];
				} );
			},

			onModalOwnerChange: function () {
				this.modal.data.building_id = '';
				this.modal.data.property_id = '';
				this.modal.buildings = [];
				this.modal.properties = [];
				this.loadBuildings( this.modal.data.owner_id );
			},

			onModalBuildingChange: function () {
				this.modal.data.property_id = '';
				this.modal.properties = [];
				this.loadProperties( this.modal.data.building_id );
			},

			saveTenant: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var payload = Object.assign( {}, self.modal.data );
				delete payload.owner_id;
				delete payload.building_id;

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'tenants/' + self.modal.tenantId : 'tenants';

				self.apiFetch( path, {
					method: isEdit ? 'PUT' : 'POST',
					body: JSON.stringify( payload ),
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
			deleteTenant: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'tenants/' + row.id, { method: 'DELETE' } )
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
				self.drawer.tenant = null;
				self.drawer.amendments = [];
				self.drawer.history = [];
				self.drawer.calendar = [];
				self.drawer.calendarYear = new Date().getFullYear();
				self.amendmentForm = createDefaultAmendmentForm();

				Promise.all( [
					self.apiFetch( 'tenants/' + row.id ),
					self.apiFetch( 'tenants/' + row.id + '/amendments' ),
					self.apiFetch( 'tenants/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.tenant = results[0];
						self.drawer.amendments = results[1].items || [];
						self.drawer.history = results[2].items || [];
						return self.loadCalendar();
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

			// -----------------------------------------------------------
			// Calendrier annuel de suivi des paiements (onglet Paiements).
			// -----------------------------------------------------------
			loadCalendar: function () {
				var self = this;
				if ( ! self.drawer.tenant ) {
					return Promise.resolve();
				}
				self.drawer.calendarLoading = true;

				return self.apiFetch( 'tenants/' + self.drawer.tenant.id + '/payment-calendar?year=' + self.drawer.calendarYear )
					.then( function ( body ) {
						self.drawer.calendar = body.items || [];
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.drawer.calendarLoading = false;
					} );
			},

			changeCalendarYear: function ( delta ) {
				this.drawer.calendarYear += delta;
				this.loadCalendar();
			},

			// -----------------------------------------------------------
			// Avenants au bail.
			// -----------------------------------------------------------
			addAmendment: function () {
				var self = this;
				if ( ! self.drawer.tenant || self.amendmentForm.saving ) {
					return;
				}

				self.amendmentForm.saving = true;

				self.apiFetch( 'tenants/' + self.drawer.tenant.id + '/amendments', {
					method: 'POST',
					body: JSON.stringify( self.amendmentForm ),
				} )
					.then( function () {
						self.amendmentForm = createDefaultAmendmentForm();
						return Promise.all( [
							self.apiFetch( 'tenants/' + self.drawer.tenant.id + '/amendments' ),
							self.apiFetch( 'tenants/' + self.drawer.tenant.id ),
						] );
					} )
					.then( function ( results ) {
						self.drawer.amendments = results[0].items || [];
						self.drawer.tenant = results[1];
						self.toast( 'success', self.i18n.amendmentAdded || 'Avenant ajouté.' );
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.amendmentForm.saving = false;
					} );
			},

			deleteAmendment: function ( amendment ) {
				var self = this;
				if ( ! self.drawer.tenant || ! window.confirm( self.i18n.confirmDeleteAmendment || 'Confirmez-vous la suppression de cet avenant ?' ) ) {
					return;
				}

				self.apiFetch( 'tenants/' + self.drawer.tenant.id + '/amendments/' + amendment.id, { method: 'DELETE' } )
					.then( function () {
						self.drawer.amendments = self.drawer.amendments.filter( function ( a ) {
							return a.id !== amendment.id;
						} );
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			switchDrawerTab: function ( tab ) {
				this.drawer.tab = tab;
			},

			monthLabel: function ( period ) {
				var parts = ( period || '' ).split( '-' );
				var monthIndex = parseInt( parts[1], 10 ) - 1;
				var name = this.monthNames[ monthIndex ] || parts[1];
				return name + ' ' + parts[0];
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

		function createDefaultTenantData() {
			return {
				owner_id: '',
				building_id: '',
				property_id: '',
				full_name: '',
				phone: '',
				email: '',
				lease_start: '',
				lease_end: '',
				rent_amount: 0,
				deposit_paid: 0,
				advance_start_period: new Date().toISOString().slice( 0, 7 ),
				status: 'actif',
				id_document_type: '',
				id_document_number: '',
				date_of_birth: '',
				profession: '',
				dependents_count: 0,
				guarantor_name: '',
				guarantor_phone: '',
				is_new_tenant: false,
			};
		}

		function createDefaultAmendmentForm() {
			return {
				amendment_date: new Date().toISOString().slice( 0, 10 ),
				description: '',
				new_rent_amount: '',
				new_lease_end: '',
				saving: false,
			};
		}
	} );
} );
