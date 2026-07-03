/**
 * Client REST partagé par les composants Alpine.js de l'application Limpeed
 * (Locataires, Édifices, ...). Dépend de window.limpeedRest { root, nonce }
 * défini en inline juste avant le chargement de ce script (voir les vues
 * content-*.php concernées).
 */
window.LimpeedRestClient = ( function () {
	'use strict';

	// Construit l'URL d'appel en tenant compte des deux formats possibles de
	// rest_url() : jolis permaliens (".../wp-json/limpeed/v1/") où la route et
	// la chaîne de requête se concatènent simplement, et permaliens "Simple"
	// (".../index.php?rest_route=/limpeed/v1/") où la route doit prolonger la
	// valeur du paramètre rest_route déjà présent et les paramètres de la
	// requête doivent être ajoutés avec "&" plutôt qu'un second "?".
	function buildUrl( path ) {
		var root       = window.limpeedRest.root;
		var queryIndex = path.indexOf( '?' );
		var routePath  = queryIndex === -1 ? path : path.substring( 0, queryIndex );
		var queryPart  = queryIndex === -1 ? '' : path.substring( queryIndex + 1 );

		var url = root + routePath;
		if ( queryPart ) {
			url += ( url.indexOf( '?' ) !== -1 ? '&' : '?' ) + queryPart;
		}
		return url;
	}

	function apiFetch( path, options ) {
		options = options || {};
		var headers = Object.assign(
			{ 'Content-Type': 'application/json' },
			options.headers || {}
		);
		if ( window.limpeedRest && window.limpeedRest.nonce ) {
			headers['X-WP-Nonce'] = window.limpeedRest.nonce;
		}

		return fetch( buildUrl( path ), Object.assign( {}, options, {
			headers: headers,
			credentials: 'same-origin',
		} ) ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				if ( ! response.ok ) {
					var message = body && body.message ? body.message : 'Erreur inconnue.';
					throw new Error( message );
				}
				return body;
			} );
		} );
	}

	return { buildUrl: buildUrl, apiFetch: apiFetch };
} )();
