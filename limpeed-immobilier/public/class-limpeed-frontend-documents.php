<?php
/**
 * Contrôleur frontend de la section "Documents".
 * L'upload/la liste/la suppression transitent par l'API REST (voir
 * content-documents.php + documents-app.js) : ce contrôleur ne gère que le
 * téléchargement sécurisé d'un document.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Documents {

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'documents' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_tenants' ) && ! current_user_can( 'manage_limpeed_properties' ) && ! current_user_can( 'manage_limpeed_owners' ) ) {
			return;
		}

		if ( isset( $_GET['action'], $_GET['id'] ) && 'download' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_download_document_' . $id );

			$document = Limpeed_Documents::get( $id );
			if ( ! $document ) {
				wp_die( esc_html__( 'Document introuvable.', 'limpeed-immobilier' ) );
			}

			$file_path = Limpeed_Documents::get_file_path( $document );
			if ( ! file_exists( $file_path ) ) {
				wp_die( esc_html__( 'Le fichier de ce document est introuvable sur le serveur.', 'limpeed-immobilier' ) );
			}

			nocache_headers();
			header( 'Content-Type: ' . ( $document->mime_type ? $document->mime_type : 'application/octet-stream' ) );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $document->file_name ) . '"' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
			exit;
		}
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Documents', 'handle_request' ) );
