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

			if ( ! Limpeed_Branches::can_access_owner( $id ) ) {
				wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette fiche.', 'limpeed-immobilier' ), '', array( 'response' => 403 ) );
			}

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

			// Un agent/responsable cantonné à une succursale ne peut créer ou
			// réassigner un propriétaire qu'à sa propre succursale (jamais un
			// choix libre) : évite qu'un compte compromis ou mal utilisé sorte
			// un propriétaire du cloisonnement. Seul un administrateur non
			// restreint peut choisir librement la succursale dans le formulaire.
			$current_branch_id = Limpeed_Branches::current_user_branch_id();
			$data['branch_id'] = 0 !== $current_branch_id
				? $current_branch_id
				: ( isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0 );

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0;

			if ( $id > 0 && ! Limpeed_Branches::can_access_owner( $id ) ) {
				wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette fiche.', 'limpeed-immobilier' ), '', array( 'response' => 403 ) );
			}

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
