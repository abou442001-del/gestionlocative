<?php
/**
 * Contrôleur frontend de la section "Biens".
 * Traitement des formulaires (ajout/modification/suppression), miroir du
 * contrôleur admin (admin/class-limpeed-properties-page.php) mais routé via
 * l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Properties {

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
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'properties' !== Limpeed_Frontend::current_view() ) {
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
				Limpeed_Frontend::redirect_to( 'properties', array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			} else {
				Limpeed_Frontend::redirect_to( 'properties', array( 'message' => 'deleted' ) );
			}
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_property_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_property', 'limpeed_property_nonce' );

			$data = array(
				'building_id'    => isset( $_POST['building_id'] ) ? (int) $_POST['building_id'] : 0,
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
				Limpeed_Frontend::redirect_to( 'properties', array( 'message' => 'updated' ) );
			} else {
				Limpeed_Properties::insert( $data );
				Limpeed_Frontend::redirect_to( 'properties', array( 'message' => 'created' ) );
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

		if ( empty( $data['building_id'] ) || ! Limpeed_Buildings::get( $data['building_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un édifice valide.', 'limpeed-immobilier' );
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
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Properties', 'handle_request' ) );
