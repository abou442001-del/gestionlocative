/**
 * Composant Alpine.js de la section "Travaux" (app frontend Limpeed).
 * Un agent soumet une demande (édifice, montant, motif) ; un administrateur
 * l'approuve ou la refuse (le contrôle réel des droits est fait côté API
 * REST — canReview ne sert ici qu'à afficher/masquer les boutons).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-work-requests.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedWorkRequestsApp', function ( config ) {
		return {
			buildingOptions: config.buildingOptions || [],
			statuses: config.statuses || {},
			canReview: !! config.canReview,
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			filterBuildingId: '',
			filterStatus: '',

			// Modale demande.
			modal: {
				open: false,
				saving: false,
				errors: [],
				data: createDefaultRequestData(),
			},

			// Modale refus.
			rejectModal: {
				open: false,
				saving: false,
				requestId: 0,
				notes: '',
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
					building_id: self.filterBuildingId,
					status: self.filterStatus,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'work-requests?' + query )
					.then( function ( body ) {
						self.items = body.items || [];
						self.total = body.total || 0;
						self.totalPages = body.total_pages || 1;
						self.paged = body.paged || 1;
						self.canReview = !! body.can_review;
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
			// Modale demande de travaux.
			// -----------------------------------------------------------
			openAddModal: function () {
				this.modal.errors = [];
				this.modal.data = createDefaultRequestData();
				this.modal.open = true;
			},

			closeModal: function () {
				this.modal.open = false;
			},

			saveRequest: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				self.apiFetch( 'work-requests', {
					method: 'POST',
					body: JSON.stringify( self.modal.data ),
				} )
					.then( function () {
						self.modal.open = false;
						self.toast( 'success', self.i18n.created );
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
			deleteRequest: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'work-requests/' + row.id, { method: 'DELETE' } )
					.then( function () {
						self.toast( 'success', self.i18n.deleted );
						self.fetchList();
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			// -----------------------------------------------------------
			// Approbation / refus (administrateur uniquement).
			// -----------------------------------------------------------
			approveRequest: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmApprove || 'Confirmez-vous l\'approbation ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'work-requests/' + row.id + '/approve', { method: 'POST' } )
					.then( function () {
						self.toast( 'success', self.i18n.approved );
						self.fetchList();
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} );
			},

			openRejectModal: function ( row ) {
				this.rejectModal.requestId = row.id;
				this.rejectModal.notes = '';
				this.rejectModal.open = true;
			},

			closeRejectModal: function () {
				this.rejectModal.open = false;
			},

			confirmReject: function () {
				var self = this;
				self.rejectModal.saving = true;

				self.apiFetch( 'work-requests/' + self.rejectModal.requestId + '/reject', {
					method: 'POST',
					body: JSON.stringify( { review_notes: self.rejectModal.notes } ),
				} )
					.then( function () {
						self.rejectModal.open = false;
						self.toast( 'success', self.i18n.rejected );
						self.fetchList();
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.rejectModal.saving = false;
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

		function createDefaultRequestData() {
			return {
				building_id: '',
				amount: '',
				reason: '',
			};
		}
	} );
} );
