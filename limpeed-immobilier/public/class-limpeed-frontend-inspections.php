<?php
/**
 * Contrôleur frontend de la section "États des lieux".
 * Le CRUD lui-même transite entièrement par l'API REST (voir
 * content-inspections.php + inspections-app.js) : ce contrôleur ne gère que
 * le téléchargement du PDF de l'état des lieux.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Inspections {

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'inspections' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_tenants' ) ) {
			return;
		}

		if ( isset( $_GET['action'], $_GET['id'] ) && 'download_pdf' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_download_inspection_pdf_' . $id );
			Limpeed_Contracts::stream_inspection_pdf( $id );
		}
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Inspections', 'handle_request' ) );
