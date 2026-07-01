<?php
/**
 * Contrôleur frontend de la section "Propriétaires".
 * Traitement des formulaires (ajout/modification/suppression), miroir du
 * contrôleur admin (admin/class-limpeed-owners-page.php) mais routé via
 * l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Owners {

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
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'owners' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_owners' ) ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_owner_' . $id );

			$result = Limpeed_Owners::delete( $id );

			if ( is_wp_error( $result ) ) {
				Limpeed_Frontend::redirect_to( 'owners', array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				Limpeed_Frontend::redirect_to( 'owners', array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_owner_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_owner', 'limpeed_owner_nonce' );

			$data = array(
				'full_name'    => isset( $_POST['full_name'] ) ? wp_unslash( $_POST['full_name'] ) : '',
				'phone'        => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
				'email'        => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
				'address'      => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
				'bank_details' => isset( $_POST['bank_details'] ) ? wp_unslash( $_POST['bank_details'] ) : '',
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Owners::update( $id, $data );
				Limpeed_Frontend::redirect_to( 'owners', array( 'message' => 'updated' ) );
			} else {
				Limpeed_Owners::insert( $data );
				Limpeed_Frontend::redirect_to( 'owners', array( 'message' => 'created' ) );
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

		if ( empty( trim( $data['full_name'] ) ) ) {
			$errors[] = __( 'Le nom complet est obligatoire.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors[] = __( 'L\'adresse email n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['phone'] ) && ! preg_match( '/^[0-9+\s().-]{6,20}$/', $data['phone'] ) ) {
			$errors[] = __( 'Le numéro de téléphone n\'est pas valide.', 'limpeed-immobilier' );
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Owners', 'handle_request' ) );
