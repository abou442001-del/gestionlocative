/**
 * Composant Alpine.js de la section "Mandats" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'ajout/modification (sélection de
 * l'édifice), actions Ajax, panneau de détail (Infos / Historique) avec lien
 * de téléchargement du PDF du mandat.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-mandates.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedMandatesApp', function ( config ) {
		return {
			buildingOptions: config.buildingOptions || [],
			statuses: config.statuses || {},
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			search: '',
			filterBuildingId: '',
			filterStatus: '',
			searchTimer: null,

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				mandateId: 0,
				data: createDefaultMandateData(),
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				mandate: null,
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
					building_id: self.filterBuildingId,
					status: self.filterStatus,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'mandates?' + query )
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
				this.modal.mandateId = 0;
				this.modal.errors = [];
				this.modal.data = createDefaultMandateData();
				this.modal.open = true;
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.mandateId = row.id;
				self.modal.errors = [];
				self.modal.open = true;

				self.apiFetch( 'mandates/' + row.id )
					.then( function ( mandate ) {
						self.modal.data = {
							building_id: mandate.building_id || '',
							start_date: mandate.start_date || '',
							end_date: mandate.end_date || '',
							commission_rate: mandate.commission_rate || 0,
							status: mandate.status || 'actif',
							signed_date: mandate.signed_date || '',
							notes: mandate.notes || '',
						};
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			closeModal: function () {
				this.modal.open = false;
			},

			saveMandate: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'mandates/' + self.modal.mandateId : 'mandates';

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
			deleteMandate: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'mandates/' + row.id, { method: 'DELETE' } )
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
				self.drawer.mandate = null;
				self.drawer.history = [];

				Promise.all( [
					self.apiFetch( 'mandates/' + row.id ),
					self.apiFetch( 'mandates/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.mandate = results[0];
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

		function createDefaultMandateData() {
			return {
				building_id: '',
				start_date: '',
				end_date: '',
				commission_rate: 0,
				status: 'actif',
				signed_date: '',
				notes: '',
			};
		}
	} );
} );
