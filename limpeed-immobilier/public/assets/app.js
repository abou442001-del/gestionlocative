/**
 * Initiales d'un nom complet (1 ou 2 lettres), pour l'avatar rond des cartes
 * de liste. Miroir JS de Limpeed_Frontend::initials() (PHP), utilisé par les
 * sections rendues via Alpine.js où le nom n'est connu qu'après le chargement
 * Ajax (Locataires...).
 *
 * @param {string} name
 * @return {string}
 */
window.limpeedInitials = function ( name ) {
	var trimmed = ( name || '' ).trim();
	if ( ! trimmed ) {
		return '?';
	}
	var parts = trimmed.split( /\s+/ ).filter( Boolean );
	if ( parts.length >= 2 ) {
		return ( parts[0].charAt( 0 ) + parts[ parts.length - 1 ].charAt( 0 ) ).toUpperCase();
	}
	return parts[0].slice( 0, 2 ).toUpperCase();
};

/**
 * Échappe les caractères HTML spéciaux, pour l'insertion de données venant de
 * l'API (noms de locataires/propriétaires...) dans du innerHTML construit par
 * concaténation de chaînes (voir la recherche globale de la barre du haut).
 *
 * @param {string} value
 * @return {string}
 */
window.limpeedEscapeHtml = function ( value ) {
	return String( value == null ? '' : value ).replace( /[&<>"']/g, function ( char ) {
		return ( {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;',
		} )[ char ];
	} );
};

document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.limpeed-confirm-delete' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			var message = link.getAttribute( 'data-confirm' ) || 'Confirmez-vous cette action ?';
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );

	// Cartes de liste cliquables (grilles Propriétaires/Bordereaux/Agents...) :
	// navigue vers data-href sauf si le clic provient d'un lien/bouton imbriqué
	// dans le pied de carte (Modifier/Supprimer), qui doit garder son propre
	// comportement plutôt que déclencher aussi la navigation de la carte.
	document.querySelectorAll( '.limpeed-entity-card[data-href]' ).forEach( function ( card ) {
		card.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( 'a, button' ) ) {
				return;
			}
			window.location.href = card.getAttribute( 'data-href' );
		} );
		card.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key && ! event.target.closest( 'a, button' ) ) {
				window.location.href = card.getAttribute( 'data-href' );
			}
		} );
	} );

	var themeToggle = document.getElementById( 'limpeed-theme-toggle' );
	if ( themeToggle ) {
		themeToggle.addEventListener( 'click', function () {
			var current = document.documentElement.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light';
			var next    = 'dark' === current ? 'light' : 'dark';
			document.documentElement.setAttribute( 'data-theme', next );
			try {
				localStorage.setItem( 'limpeedTheme', next );
			} catch ( e ) {}
		} );
	}

	var sidebarToggle = document.getElementById( 'limpeed-sidebar-toggle' );
	if ( sidebarToggle ) {
		sidebarToggle.addEventListener( 'click', function () {
			var collapsed = 'collapsed' === document.documentElement.getAttribute( 'data-sidebar' );
			var next      = ! collapsed;
			if ( next ) {
				document.documentElement.setAttribute( 'data-sidebar', 'collapsed' );
			} else {
				document.documentElement.removeAttribute( 'data-sidebar' );
			}
			try {
				localStorage.setItem( 'limpeedSidebarCollapsed', next ? 'true' : 'false' );
			} catch ( e ) {}
		} );
	}

	var userMenuToggle = document.getElementById( 'limpeed-user-menu-toggle' );
	var userMenuPanel  = document.getElementById( 'limpeed-user-menu-panel' );
	if ( userMenuToggle && userMenuPanel ) {
		userMenuToggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			userMenuPanel.classList.toggle( 'is-open' );
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( userMenuPanel.classList.contains( 'is-open' ) && ! userMenuPanel.contains( event.target ) ) {
				userMenuPanel.classList.remove( 'is-open' );
			}
		} );
	}

	var notifToggle = document.getElementById( 'limpeed-notif-toggle' );
	var notifPanel   = document.getElementById( 'limpeed-notif-panel' );
	if ( notifToggle && notifPanel ) {
		notifToggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			notifPanel.classList.toggle( 'is-open' );
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( notifPanel.classList.contains( 'is-open' ) && ! notifPanel.contains( event.target ) ) {
				notifPanel.classList.remove( 'is-open' );
			}
		} );
	}

	var quickAddToggle = document.getElementById( 'limpeed-quick-add-toggle' );
	var quickAddPanel  = document.getElementById( 'limpeed-quick-add-panel' );
	if ( quickAddToggle && quickAddPanel ) {
		quickAddToggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			quickAddPanel.classList.toggle( 'is-open' );
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( quickAddPanel.classList.contains( 'is-open' ) && ! quickAddPanel.contains( event.target ) ) {
				quickAddPanel.classList.remove( 'is-open' );
			}
		} );
	}

	// Recherche globale de la barre du haut : requête l'endpoint REST
	// limpeed/v1/search (voir Limpeed_Rest_Api::get_global_search()), avec
	// un debounce court pour éviter une requête à chaque frappe.
	var searchInput   = document.getElementById( 'limpeed-global-search-input' );
	var searchResults = document.getElementById( 'limpeed-global-search-results' );
	if ( searchInput && searchResults && window.LimpeedRestClient ) {
		var searchTimer = null;
		var typeIcons   = {
			owner: 'dashicons-groups',
			building: 'dashicons-admin-multisite',
			property: 'dashicons-building',
			tenant: 'dashicons-admin-users',
		};

		function closeSearchResults() {
			searchResults.classList.remove( 'is-open' );
			searchResults.innerHTML = '';
		}

		function renderSearchResults( items ) {
			if ( ! items.length ) {
				searchResults.innerHTML = '<p class="limpeed-app-global-search-empty">Aucun résultat.</p>';
				searchResults.classList.add( 'is-open' );
				return;
			}
			searchResults.innerHTML = items.map( function ( item ) {
				var icon    = typeIcons[ item.type ] || 'dashicons-search';
				var label   = window.limpeedEscapeHtml( item.label );
				var sub     = item.sublabel ? '<span class="limpeed-app-global-search-sub">' + window.limpeedEscapeHtml( item.sublabel ) + '</span>' : '';
				var typeLbl = window.limpeedEscapeHtml( item.type_label );
				var url     = window.limpeedEscapeHtml( item.url );
				return (
					'<a class="limpeed-app-global-search-item" href="' + url + '">' +
						'<span class="dashicons ' + icon + '"></span>' +
						'<span class="limpeed-app-global-search-item-text">' +
							'<span class="limpeed-app-global-search-label">' + label + '</span>' + sub +
						'</span>' +
						'<span class="limpeed-app-global-search-type">' + typeLbl + '</span>' +
					'</a>'
				);
			} ).join( '' );
			searchResults.classList.add( 'is-open' );
		}

		searchInput.addEventListener( 'input', function () {
			var term = searchInput.value.trim();
			clearTimeout( searchTimer );
			if ( term.length < 2 ) {
				closeSearchResults();
				return;
			}
			searchTimer = setTimeout( function () {
				window.LimpeedRestClient.apiFetch( 'search?q=' + encodeURIComponent( term ) )
					.then( function ( body ) {
						renderSearchResults( body.items || [] );
					} )
					.catch( function () {
						closeSearchResults();
					} );
			}, 300 );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '.limpeed-app-global-search' ) ) {
				closeSearchResults();
			}
		} );

		searchInput.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeSearchResults();
				searchInput.blur();
			}
		} );
	}
} );
