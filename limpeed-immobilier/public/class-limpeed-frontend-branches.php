<?php
/**
 * Contrôleur frontend de la section "Succursales".
 * Traitement des formulaires (ajout/modification/suppression), réservé aux
 * administrateurs non restreints (administrator / limpeed_admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Branches {

	/**
	 * Erreurs de validation du formulaire.
	 *
	 * @var array
	 */
	public static $errors = array();

	/**
	 * Données postées en cas d'erreur.
	 *
	 * @var array|null
	 */
	public static $posted = null;

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'branches' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_agents' ) || 0 !== Limpeed_Branches::current_user_branch_id() ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_branch_' . $id );

			$result = Limpeed_Branches::delete( $id );

			if ( is_wp_error( $result ) ) {
				Limpeed_Frontend::redirect_to( 'branches', array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				Limpeed_Frontend::redirect_to( 'branches', array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_branch_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_branch', 'limpeed_branch_nonce' );

			// Le champ est nommé "branch_name" (et non "name") côté formulaire :
			// "name" est une query var publique réservée de WordPress (recherche
			// de page/article par slug) — la poster telle quelle ferait échouer
			// la requête principale (404) avant même que ce contrôleur s'exécute.
			$data = array(
				'name'    => isset( $_POST['branch_name'] ) ? wp_unslash( $_POST['branch_name'] ) : '',
				'address' => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
				'phone'   => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Branches::update( $id, $data );
				Limpeed_Frontend::redirect_to( 'branches', array( 'message' => 'updated' ) );
			} else {
				Limpeed_Branches::insert( $data );
				Limpeed_Frontend::redirect_to( 'branches', array( 'message' => 'created' ) );
			}
		}
	}

	/**
	 * Valide les données du formulaire.
	 *
	 * @param array $data
	 * @return array
	 */
	private static function validate( $data ) {
		$errors = array();

		if ( empty( trim( $data['name'] ) ) ) {
			$errors[] = __( 'Le nom de la succursale est obligatoire.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['phone'] ) && ! preg_match( '/^[0-9+\s().-]{6,20}$/', $data['phone'] ) ) {
			$errors[] = __( 'Le numéro de téléphone n\'est pas valide.', 'limpeed-immobilier' );
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Branches', 'handle_request' ) );
