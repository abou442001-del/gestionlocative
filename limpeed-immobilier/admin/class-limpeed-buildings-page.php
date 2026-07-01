<?php
/**
 * Contrôleur de la page admin "Édifices".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-buildings-list-table.php';

class Limpeed_Buildings_Page {

	const SLUG = 'limpeed-buildings';

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
	 * Traite les actions avant tout affichage (hook admin_init).
	 */
	public static function handle_request() {
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) {
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
				self::redirect( array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				self::redirect( array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_building_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_building', 'limpeed_building_nonce' );

			$data = array(
				'owner_id'    => isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0,
				'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
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
				self::redirect( array( 'message' => 'updated' ) );
			} else {
				Limpeed_Buildings::insert( $data );
				self::redirect( array( 'message' => 'created' ) );
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

	/**
	 * Redirige vers la liste des édifices avec des paramètres additionnels.
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
		if ( ! current_user_can( 'manage_limpeed_properties' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$building = null;
			if ( 'edit' === $action && isset( $_GET['id'] ) ) {
				$building = Limpeed_Buildings::get( (int) $_GET['id'] );
				if ( ! $building ) {
					wp_die( esc_html__( 'Édifice introuvable.', 'limpeed-immobilier' ) );
				}
			}

			$errors             = self::$errors;
			$posted             = self::$posted;
			$owners             = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
			$preselected_owner  = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;

			include LIMPEED_PLUGIN_DIR . 'admin/views/buildings-form.php';
			return;
		}

		$list_table = new Limpeed_Buildings_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/buildings-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Buildings_Page', 'handle_request' ) );
