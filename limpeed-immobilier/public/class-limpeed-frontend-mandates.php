<?php
/**
 * Contrôleur frontend de la section "Mandats".
 * Le CRUD lui-même transite entièrement par l'API REST (voir
 * content-mandates.php + mandates-app.js) : ce contrôleur ne gère que le
 * téléchargement du PDF du mandat, une action de navigation classique qui ne
 * peut pas raisonnablement passer par un appel Ajax/JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Mandates {

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'mandates' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_properties' ) ) {
			return;
		}

		if ( isset( $_GET['action'], $_GET['id'] ) && 'download_contract' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_download_mandate_contract_' . $id );
			Limpeed_Contracts::stream_mandate_contract( $id );
		}
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Mandates', 'handle_request' ) );
