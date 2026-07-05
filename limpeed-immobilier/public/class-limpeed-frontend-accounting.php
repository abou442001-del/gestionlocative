<?php
/**
 * Contrôleur frontend de la section "Comptabilité".
 * Le bilan et le grand livre transitent par l'API REST (voir
 * content-accounting.php + accounting-app.js) : ce contrôleur ne gère que le
 * téléchargement du PDF "Résultats financiers du mois", une action de
 * navigation classique qui ne peut pas raisonnablement passer par un appel
 * Ajax/JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Accounting {

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'accounting' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_treasury' ) ) {
			return;
		}

		if ( isset( $_GET['action'], $_GET['period'] ) && 'download_financial_results' === $_GET['action'] ) {
			// Nonce fixe (non spécifique à la période) : la période est choisie
			// dynamiquement côté client (onglet Bilan, sélecteur de mois) sans
			// rechargement de page, un nonce par période nécessiterait de
			// régénérer/exposer un nonce à chaque changement de sélection.
			check_admin_referer( 'limpeed_download_financial_results' );

			$period = sanitize_text_field( wp_unslash( $_GET['period'] ) );
			if ( ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				wp_die( esc_html__( 'Période invalide.', 'limpeed-immobilier' ) );
			}
			Limpeed_Contracts::stream_financial_results_pdf( $period );
		}

		// Export CSV du grand livre : mêmes filtres que le nonce fixe ci-dessus
		// (choisis dynamiquement côté client, onglet Grand livre).
		if ( isset( $_GET['action'] ) && 'export_ledger_csv' === $_GET['action'] ) {
			check_admin_referer( 'limpeed_export_ledger_csv' );

			Limpeed_Accounting::stream_ledger_csv(
				array(
					'entry_type' => isset( $_GET['entry_type'] ) ? sanitize_key( $_GET['entry_type'] ) : '',
					'period'     => isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '',
					'search'     => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
				)
			);
		}
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Accounting', 'handle_request' ) );
