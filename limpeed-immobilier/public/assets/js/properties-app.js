/**
 * Composant Alpine.js de la section "Biens" (app frontend Limpeed).
 * Recherche/filtrage en direct, modale d'ajout/modification avec cascade
 * Propriétaire → Édifice, actions Ajax, panneau de détail
 * (Infos / Paiements / Historique).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-properties.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedPropertiesApp', function ( config ) {
		return {
			// Configuration initiale (rendue côté serveur).
			// buildingOptions (avec owner_id) sert uniquement au filtre "Édifice"
			// de la liste, statique : la cascade de la modale se peuple en Ajax
			// (voir loadBuildingsForOwner()), comme pour la section Locataires.
			ownerOptions: config.ownerOptions || [],
			buildingOptions: config.buildingOptions || [],
			propertyTypes: config.propertyTypes || {},
			legacyTypes: config.legacyTypes || {},
			propertyStatuses: config.propertyStatuses || {},
			averageRentByType: config.averageRentByType || {},
			i18n: config.i18n || {},

			// Liste.
			loading: true,
			items: [],
			total: 0,
			totalPages: 1,
			paged: 1,
			search: '',
			filterOwnerId: '',
			filterBuildingId: '',
			filterStatus: '',
			searchTimer: null,

			// Modale ajout/modification.
			modal: {
				open: false,
				mode: 'add',
				saving: false,
				errors: [],
				propertyId: 0,
				data: createDefaultPropertyData(),
				buildings: [],
				loadingCascade: false,
				extraTypeOption: null,
			},

			// Panneau de détail (drawer).
			drawer: {
				open: false,
				loading: false,
				tab: 'infos',
				property: null,
				payments: [],
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
					building_id: self.filterBuildingId,
					status: self.filterStatus,
					paged: self.paged,
					per_page: 20,
				} ).toString();

				return self.apiFetch( 'properties?' + query )
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
			averageRentHint: function () {
				var avg = this.averageRentByType[ this.modal.data.type ];
				if ( ! avg ) {
					return '';
				}
				return ( this.i18n.averageRent || 'Loyer moyen constaté : ' ) + Math.round( avg ).toLocaleString();
			},

			// Liste complète des options du select "Type de bien", ancien type
			// éventuel en tête. Calculée en un seul tableau (plutôt que deux
			// blocs x-for/x-if séparés dans le template) pour que la valeur
			// sélectionnée et la liste d'options se mettent à jour ensemble :
			// avec deux blocs distincts, x-model peut tenter de positionner le
			// <select> avant que l'option "ancien type" n'ait été insérée dans
			// le DOM, et affiche alors à tort la première option de la liste.
			typeSelectOptions: function () {
				var options = [];
				if ( this.modal.extraTypeOption ) {
					options.push( this.modal.extraTypeOption );
				}
				Object.keys( this.propertyTypes ).forEach( function ( key ) {
					options.push( { value: key, label: this.propertyTypes[ key ] } );
				}.bind( this ) );
				return options;
			},

			openAddModal: function () {
				this.modal.mode = 'add';
				this.modal.propertyId = 0;
				this.modal.errors = [];
				this.modal.data = createDefaultPropertyData();
				this.modal.buildings = [];
				this.modal.extraTypeOption = null;
				this.modal.open = true;
			},

			openEditModal: function ( row ) {
				var self = this;
				self.modal.mode = 'edit';
				self.modal.propertyId = row.id;
				self.modal.errors = [];
				self.modal.open = true;
				self.modal.loadingCascade = true;

				self.apiFetch( 'properties/' + row.id )
					.then( function ( property ) {
						// Le champ "type" est volontairement laissé vide ici puis
						// affecté après $nextTick() ci-dessous : x-model sur le
						// <select> peut tenter de positionner sa valeur avant que
						// l'option "ancien type" (x-for sur typeSelectOptions())
						// n'ait été insérée dans le DOM, et rien ne re-déclenche
						// cette synchronisation une fois l'option ajoutée. En
						// peuplant d'abord la liste d'options (avec un type vide,
						// donc sans tentative de sélection prématurée) puis en
						// attendant qu'Alpine ait fini de mettre à jour le DOM
						// avant d'assigner la vraie valeur, l'option existe déjà
						// quand x-model tente de la sélectionner.
						self.modal.data = {
							owner_id: property.owner_id || '',
							building_id: property.building_id || '',
							reference: property.reference || '',
							address: property.address || '',
							type: '',
							monthly_rent: property.monthly_rent || 0,
							charges: property.charges || 0,
							deposit_amount: property.deposit_amount || 0,
							status: property.status || 'vacant',
						};
						// Si le bien utilise un ancien type de bien (conservé pour
						// compatibilité, voir Limpeed_Properties::get_legacy_types()),
						// on l'ajoute comme option supplémentaire pour ne pas le
						// basculer silencieusement vers "Studio" à l'enregistrement.
						self.modal.extraTypeOption = self.legacyTypes[ property.type ]
							? { value: property.type, label: self.legacyTypes[ property.type ] + ' (' + ( self.i18n.legacyType || 'ancien type' ) + ')' }
							: null;

						self.$nextTick( function () {
							self.modal.data.type = property.type || 'studio';
						} );

						return self.loadBuildingsForOwner( property.owner_id );
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

			loadBuildingsForOwner: function ( ownerId ) {
				var self = this;
				if ( ! ownerId ) {
					self.modal.buildings = [];
					return Promise.resolve();
				}
				return self.apiFetch( 'buildings?owner_id=' + encodeURIComponent( ownerId ) ).then( function ( body ) {
					self.modal.buildings = body.items || [];
				} );
			},

			onModalOwnerChange: function () {
				this.modal.data.building_id = '';
				this.modal.buildings = [];
				this.loadBuildingsForOwner( this.modal.data.owner_id );
			},

			saveProperty: function () {
				var self = this;
				self.modal.saving = true;
				self.modal.errors = [];

				var payload = Object.assign( {}, self.modal.data );
				delete payload.owner_id;

				var isEdit = 'edit' === self.modal.mode;
				var path = isEdit ? 'properties/' + self.modal.propertyId : 'properties';

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
			deleteProperty: function ( row ) {
				var self = this;
				var confirmMessage = self.i18n.confirmDelete || 'Confirmez-vous la suppression ?';
				if ( ! window.confirm( confirmMessage ) ) {
					return;
				}

				self.apiFetch( 'properties/' + row.id, { method: 'DELETE' } )
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
				self.drawer.property = null;
				self.drawer.payments = [];
				self.drawer.history = [];

				Promise.all( [
					self.apiFetch( 'properties/' + row.id ),
					self.apiFetch( 'properties/' + row.id + '/payments' ),
					self.apiFetch( 'properties/' + row.id + '/history' ),
				] )
					.then( function ( results ) {
						self.drawer.property = results[0];
						self.drawer.payments = results[1].items || [];
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

		function createDefaultPropertyData() {
			return {
				owner_id: '',
				building_id: '',
				reference: '',
				address: '',
				type: 'studio',
				monthly_rent: 0,
				charges: 0,
				deposit_amount: 0,
				status: 'vacant',
			};
		}
	} );
} );
