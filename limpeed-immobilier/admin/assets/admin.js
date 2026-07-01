document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.limpeed-confirm-delete' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			var message = link.getAttribute( 'data-confirm' ) || 'Confirmez-vous la suppression ?';
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );
} );
