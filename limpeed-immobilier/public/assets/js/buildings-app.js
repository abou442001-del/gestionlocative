/**
 * Composant Alpine.js de la section "Édifices" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'ajout/modification (sélection du
 * propriétaire), actions Ajax, panneau de détail (Infos / Biens / Historique).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-buildings.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedBuildingsApp', function ( config ) {
		return {
			// Configuration initiale (rendue côté serveur).
			ownerOptions: config.ownerOptions || [],
			propertyStatuses: config.propertyStatuses || {},
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			search: '',
			filterOwnerId: '',
			searchTimer: null,

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				buildingId: 0,
				data: createDefaultBuildingData(),
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				building: null,
				properties: [],
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
					owner_id: self.filterOwnerId,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'buildings?' + query )
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
				this.modal.mode = 'add';
				this.modal.buildingId = 0;
				this.modal.errors = [];
				this.modal.data = createDefaultBuildingData();
				this.modal.open = true;
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.buildingId = row.id;
				self.modal.errors = [];
				self.modal.open = true;

				self.apiFetch( 'buildings/' + row.id )
					.then( function ( building ) {
						self.modal.data = {
							owner_id: building.owner_id || '',
							name: building.name || '',
							address: building.address || '',
							description: building.description || '',
							commission_rate: building.commission_rate || 0,
						};
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			closeModal: function () {
				this.modal.open = false;
			},

			saveBuilding: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'buildings/' + self.modal.buildingId : 'buildings';

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
			deleteBuilding: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'buildings/' + row.id, { method: 'DELETE' } )
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
				self.drawer.building = null;
				self.drawer.properties = [];
				self.drawer.history = [];

				Promise.all( [
					self.apiFetch( 'buildings/' + row.id ),
					self.apiFetch( 'properties?building_id=' + row.id ),
					self.apiFetch( 'buildings/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.building = results[0];
						self.drawer.properties = results[1].items || [];
						self.drawer.history = results[2].items || [];
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

		function createDefaultBuildingData() {
			return {
				owner_id: '',
				name: '',
				address: '',
				description: '',
				commission_rate: 0,
			};
		}
	} );
} );
