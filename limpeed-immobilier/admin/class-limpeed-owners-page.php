<?php
/**
 * Contrôleur de la page admin "Propriétaires".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-owners-list-table.php';

class Limpeed_Owners_Page {

	const SLUG = 'limpeed-owners';

	/**
	 * Erreurs de validation du formulaire (persistent le temps de la requête).
	 *
	 * @var array
	 */
	public static $errors = array();

	/**
	 * Données postées en cas d'erreur, pour ré-afficher le formulaire rempli.
	 *
	 * @var array|null
	 */
	public static $posted = null;

	/**
	 * Traite les actions (ajout/modification/suppression) avant tout affichage.
	 * Doit être appelée sur le hook admin_init (avant l'envoi des en-têtes HTTP).
	 */
	public static function handle_request() {
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) {
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
				self::redirect( array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				self::redirect( array( 'message' => 'deleted' ) );
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
				self::redirect( array( 'message' => 'updated' ) );
			} else {
				Limpeed_Owners::insert( $data );
				self::redirect( array( 'message' => 'created' ) );
			}
		}
	}

	/**
	 * Valide les données du formulaire.
	 *
	 * @param array $data
	 * @return array Liste des messages d'erreur.
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

	/**
	 * Redirige vers la liste des propriétaires avec des paramètres additionnels.
	 *
	 * @param array $args
	 */
	private static function redirect( $args = array() ) {
		$url = add_query_arg(
			array_merge( array( 'page' => self::SLUG ), $args ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Affiche la page (liste ou formulaire).
	 */
	public function render() {
		if ( ! current_user_can( 'manage_limpeed_owners' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$owner = null;
			if ( 'edit' === $action && isset( $_GET['id'] ) ) {
				$owner = Limpeed_Owners::get( (int) $_GET['id'] );
				if ( ! $owner ) {
					wp_die( esc_html__( 'Propriétaire introuvable.', 'limpeed-immobilier' ) );
				}
			}

			$errors = self::$errors;
			$posted = self::$posted;

			include LIMPEED_PLUGIN_DIR . 'admin/views/owners-form.php';
			return;
		}

		$list_table = new Limpeed_Owners_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/owners-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Owners_Page', 'handle_request' ) );
