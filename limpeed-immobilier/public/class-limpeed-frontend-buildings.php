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
	 * Lignes de sous-édifices postées en cas d'erreur.
	 *
	 * @var array
	 */
	public static $posted_sub_units = array();

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

			$sub_units = self::parse_sub_units_from_post();

			$errors = array_merge( self::validate( $data ), Limpeed_Properties::validate_sub_units( $sub_units ) );

			if ( ! empty( $errors ) ) {
				self::$errors           = $errors;
				self::$posted           = $data;
				self::$posted_sub_units = $sub_units;
				return;
			}

			$id = isset( $_POST['building_id'] ) ? (int) $_POST['building_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Buildings::update( $id, $data );
				Limpeed_Properties::insert_sub_units( $id, $sub_units );
				Limpeed_Frontend::redirect_to( 'buildings', array( 'message' => 'updated' ) );
			} else {
				$new_id = Limpeed_Buildings::insert( $data );
				if ( $new_id ) {
					Limpeed_Properties::insert_sub_units( $new_id, $sub_units );
				}
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

	/**
	 * Extrait et nettoie les lignes de sous-édifices postées avec le formulaire
	 * Édifice ($_POST['sub_units'][N][champ]).
	 *
	 * @return array
	 */
	private static function parse_sub_units_from_post() {
		$rows = array();

		if ( ! isset( $_POST['sub_units'] ) || ! is_array( $_POST['sub_units'] ) ) {
			return $rows;
		}

		foreach ( $_POST['sub_units'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rows[] = array(
				'reference'      => isset( $row['reference'] ) ? wp_unslash( $row['reference'] ) : '',
				'address'        => isset( $row['address'] ) ? wp_unslash( $row['address'] ) : '',
				'type'           => isset( $row['type'] ) ? sanitize_text_field( wp_unslash( $row['type'] ) ) : '',
				'monthly_rent'   => isset( $row['monthly_rent'] ) ? wp_unslash( $row['monthly_rent'] ) : '',
				'charges'        => isset( $row['charges'] ) ? wp_unslash( $row['charges'] ) : '',
				'deposit_amount' => isset( $row['deposit_amount'] ) ? wp_unslash( $row['deposit_amount'] ) : '',
				'status'         => isset( $row['status'] ) ? sanitize_text_field( wp_unslash( $row['status'] ) ) : '',
			);
		}

		return $rows;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Buildings', 'handle_request' ) );
