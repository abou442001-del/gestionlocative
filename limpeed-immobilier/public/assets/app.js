document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.limpeed-confirm-delete' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			var message = link.getAttribute( 'data-confirm' ) || 'Confirmez-vous cette action ?';
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
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
} );
