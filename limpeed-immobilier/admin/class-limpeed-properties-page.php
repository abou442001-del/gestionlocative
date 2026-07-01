<?php
/**
 * Contrôleur de la page admin "Biens".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-properties-list-table.php';

class Limpeed_Properties_Page {

	const SLUG = 'limpeed-properties';

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
			check_admin_referer( 'limpeed_delete_property_' . $id );

			$result = Limpeed_Properties::delete( $id );

			if ( is_wp_error( $result ) ) {
				self::redirect( array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				self::redirect( array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_property_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_property', 'limpeed_property_nonce' );

			$data = array(
				'owner_id'       => isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0,
				'address'        => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
				'type'           => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
				'monthly_rent'   => isset( $_POST['monthly_rent'] ) ? wp_unslash( $_POST['monthly_rent'] ) : '',
				'charges'        => isset( $_POST['charges'] ) ? wp_unslash( $_POST['charges'] ) : '',
				'deposit_amount' => isset( $_POST['deposit_amount'] ) ? wp_unslash( $_POST['deposit_amount'] ) : '',
				'status'         => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Properties::update( $id, $data );
				self::redirect( array( 'message' => 'updated' ) );
			} else {
				Limpeed_Properties::insert( $data );
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

		if ( empty( trim( $data['address'] ) ) ) {
			$errors[] = __( 'L\'adresse du bien est obligatoire.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['type'], Limpeed_Properties::get_types() ) ) {
			$errors[] = __( 'Le type de bien sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Properties::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		foreach ( array( 'monthly_rent', 'charges', 'deposit_amount' ) as $field ) {
			if ( '' !== $data[ $field ] && ! is_numeric( $data[ $field ] ) ) {
				$errors[] = __( 'Les montants (loyer, charges, dépôt) doivent être des nombres.', 'limpeed-immobilier' );
				break;
			}
		}

		return $errors;
	}

	/**
	 * Redirige vers la liste des biens avec des paramètres additionnels.
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
			$property = null;
			if ( 'edit' === $action && isset( $_GET['id'] ) ) {
				$property = Limpeed_Properties::get( (int) $_GET['id'] );
				if ( ! $property ) {
					wp_die( esc_html__( 'Bien introuvable.', 'limpeed-immobilier' ) );
				}
			}

			$errors = self::$errors;
			$posted = self::$posted;
			$owners = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );

			include LIMPEED_PLUGIN_DIR . 'admin/views/properties-form.php';
			return;
		}

		$list_table = new Limpeed_Properties_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/properties-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Properties_Page', 'handle_request' ) );
