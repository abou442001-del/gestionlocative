/**
 * Composant Alpine.js des cartes KPI dynamiques du Tableau de bord (app
 * frontend Limpeed) : taux d'occupation, loyers impayés du mois en cours,
 * baux arrivant à échéance. Rafraîchies en Ajax au chargement puis toutes
 * les 60 secondes, sans recharger la page.
 *
 * Dépend de :
 * - window.limpeedRest { root, nonce } (voir content-dashboard.php)
 * - limpeed-rest-client.js (window.LimpeedRestClient)
 * - Alpine.js (vendored dans public/assets/vendor/alpinejs/alpine.min.js)
 */
document.addEventListener( 'alpine:init', function () {
	'use strict';

	Alpine.data( 'limpeedDashboardKpisApp', function ( config ) {
		return {
			i18n: config.i18n || {},
			expiringDays: config.expiringDays || 30,

			loading: true,
			occupancyRate: 0,
			propertiesOccupied: 0,
			propertiesTotal: 0,
			unpaidCount: 0,
			unpaidTotalFormatted: '',
			unpaidTenants: [],
			expiringCount: 0,
			expiringLeases: [],

			expandedPanel: '',
			refreshTimer: null,

			init: function () {
				var self = this;
				self.fetchKpis();
				self.refreshTimer = setInterval( function () {
					self.fetchKpis();
				}, 60000 );
			},

			destroy: function () {
				clearInterval( this.refreshTimer );
			},

			apiFetch: function ( path, options ) {
				return window.LimpeedRestClient.apiFetch( path, options );
			},

			fetchKpis: function () {
				var self = this;
				return self.apiFetch( 'dashboard/kpis?expiring_days=' + self.expiringDays )
					.then( function ( body ) {
						self.occupancyRate = body.occupancy_rate || 0;
						self.propertiesOccupied = body.properties_occupied || 0;
						self.propertiesTotal = body.properties_total || 0;
						self.unpaidCount = body.unpaid_count || 0;
						self.unpaidTotalFormatted = body.unpaid_total_formatted || '';
						self.unpaidTenants = body.unpaid_tenants || [];
						self.expiringCount = body.expiring_count || 0;
						self.expiringLeases = body.expiring_leases || [];
					} )
					.catch( function () {
						// Échec silencieux : les cartes gardent leurs dernières
						// valeurs connues plutôt que d'afficher une erreur bloquante
						// sur le tableau de bord.
					} )
					.finally( function () {
						self.loading = false;
					} );
			},

			togglePanel: function ( panel ) {
				this.expandedPanel = this.expandedPanel === panel ? '' : panel;
			},
		};
	} );
} );
