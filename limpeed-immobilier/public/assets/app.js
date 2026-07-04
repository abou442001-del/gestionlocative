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
} );
