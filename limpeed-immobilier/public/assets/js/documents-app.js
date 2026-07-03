/**
 * Composant Alpine.js de la section "Documents" (app frontend Limpeed).
 * Sélection d'une entité (type + recherche), liste de ses documents,
 * upload et téléchargement.
 *
 * L'upload transite en multipart/form-data plutôt que par
 * LimpeedRestClient.apiFetch() : ce dernier force un Content-Type
 * application/json, incompatible avec un envoi de fichier (qui a besoin
 * d'un Content-Type multipart avec la frontière générée par le navigateur).
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-documents.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedDocumentsApp', function ( config ) {
		return {
			entityTypes: config.entityTypes || {},
			i18n: config.i18n || {},

			entityType: '',
			entitySearch: '',
			entityOptions: [],
			entityId: '',
			searchTimer: null,
			loadingEntities: false,

			documents: [],
			loadingDocuments: false,

			uploadForm: { title: '', file: null, uploading: false },

			toasts: [],
			toastSeq: 0,

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			onEntityTypeChange: function () {
				this.entitySearch = '';
				this.entityOptions = [];
				this.entityId = '';
				this.documents = [];
			},

			onEntitySearchInput: function () {
				var self = this;
				clearTimeout( self.searchTimer );
				self.searchTimer = setTimeout( function () {
					self.searchEntities();
				}, 300 );
			},

			searchEntities: function () {
				var self = this;
				if ( ! self.entityType ) {
					return;
				}

				var endpoints = {
					owner: 'owners',
					building: 'buildings',
					property: 'properties',
					tenant: 'tenants',
				};

				self.loadingEntities = true;
				var query = new URLSearchParams( { search: self.entitySearch, per_page: 20 } ).toString();

				self.apiFetch( endpoints[ self.entityType ] + '?' + query )
					.then( function ( body ) {
						self.entityOptions = ( body.items || [] ).map( function ( item ) {
							return {
								id: item.id,
								label: item.label || item.full_name || item.name || ( '#' + item.id ),
							};
						} );
					} )
					.finally( function () {
						self.loadingEntities = false;
					} );
			},

			selectEntity: function ( id ) {
				this.entityId = id;
				this.fetchDocuments();
			},

			fetchDocuments: function () {
				var self = this;
				if ( ! self.entityType || ! self.entityId ) {
					return;
				}

				self.loadingDocuments = true;
				self.apiFetch( 'documents?entity_type=' + self.entityType + '&entity_id=' + self.entityId )
					.then( function ( body ) {
						self.documents = body.items || [];
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.loadingDocuments = false;
					} );
			},

			onFileChange: function ( event ) {
				this.uploadForm.file = event.target.files[0] || null;
			},

			uploadDocument: function () {
				var self = this;
				if ( ! self.uploadForm.file || ! self.entityId ) {
					return;
				}

				self.uploadForm.uploading = true;

				var formData = new FormData();
				formData.append( 'entity_type', self.entityType );
				formData.append( 'entity_id', self.entityId );
				formData.append( 'title', self.uploadForm.title || self.uploadForm.file.name );
				formData.append( 'file', self.uploadForm.file );

				fetch( window.LimpeedRestClient.buildUrl( 'documents' ), {
					method: 'POST',
					headers: { 'X-WP-Nonce': window.limpeedRest.nonce },
					credentials: 'same-origin',
					body: formData,
				} )
					.then( function ( response ) {
						return response.json().then( function ( body ) {
							if ( ! response.ok ) {
								throw new Error( body && body.message ? body.message : 'Erreur inconnue.' );
							}
							return body;
						} );
					} )
					.then( function () {
						self.uploadForm = { title: '', file: null, uploading: false };
						var fileInput = document.getElementById( 'limpeed-document-file-input' );
						if ( fileInput ) {
							fileInput.value = '';
						}
						self.toast( 'success', self.i18n.uploaded || 'Document ajouté.' );
						self.fetchDocuments();
					} )
					.catch( function ( error ) {
						self.toast( 'error', error.message );
					} )
					.finally( function () {
						self.uploadForm.uploading = false;
					} );
			},

			deleteDocument: function ( doc ) {
				var self = this;
				if ( ! window.confirm( self.i18n.confirmDelete || 'Confirmez-vous la suppression de ce document ?' ) ) {
					return;
				}

				self.apiFetch( 'documents/' + doc.id, { method: 'DELETE' } )
					.then( function () {
						self.documents = self.documents.filter( function ( d ) {
							return d.id !== doc.id;
						} );
						self.toast( 'success', self.i18n.deleted || 'Document supprimé.' );
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
	} );
} );
