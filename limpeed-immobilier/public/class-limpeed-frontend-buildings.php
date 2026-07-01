<?php
/**
 * Contrôleur frontend de la section "Édifices".
 * Traitement des formulaires (ajout/modification/suppression), miroir du
 * contrôleur admin (admin/class-limpeed-buildings-page.php) mais routé via
 * l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Buildings {

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
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'buildings' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_properties' ) ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_building_' . $id );

			$result = Limpeed_Buildings::delete( $id );

			if ( is_wp_error( $result ) ) {
				Limpeed_Frontend::redirect_to( 'buildings', array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				Limpeed_Frontend::redirect_to( 'buildings', array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_building_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_building', 'limpeed_building_nonce' );

			$data = array(
				'owner_id'    => isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0,
				'name'        => isset( $_POST['building_name'] ) ? wp_unslash( $_POST['building_name'] ) : '',
				'address'     => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['building_id'] ) ? (int) $_POST['building_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Buildings::update( $id, $data );
				Limpeed_Frontend::redirect_to( 'buildings', array( 'message' => 'updated' ) );
			} else {
				Limpeed_Buildings::insert( $data );
				Limpeed_Frontend::redirect_to( 'buildings', array( 'message' => 'created' ) );
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

		if ( empty( $data['owner_id'] ) || ! Limpeed_Owners::get( $data['owner_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un propriétaire valide.', 'limpeed-immobilier' );
		}

		if ( empty( trim( $data['name'] ) ) ) {
			$errors[] = __( 'Le nom de l\'édifice est obligatoire.', 'limpeed-immobilier' );
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Buildings', 'handle_request' ) );
