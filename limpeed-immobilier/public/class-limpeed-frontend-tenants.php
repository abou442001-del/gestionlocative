<?php
/**
 * Contrôleur frontend de la section "Locataires".
 * Traitement des formulaires (ajout/modification/suppression), miroir du
 * contrôleur admin (admin/class-limpeed-tenants-page.php) mais routé via
 * l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Tenants {

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
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'tenants' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_tenants' ) ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_tenant_' . $id );

			Limpeed_Tenants::delete( $id );
			Limpeed_Frontend::redirect_to( 'tenants', array( 'message' => 'deleted' ) );
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_tenant_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_tenant', 'limpeed_tenant_nonce' );

			$data = array(
				'property_id'  => isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0,
				'full_name'    => isset( $_POST['full_name'] ) ? wp_unslash( $_POST['full_name'] ) : '',
				'phone'        => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
				'email'        => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
				'lease_start'  => isset( $_POST['lease_start'] ) ? sanitize_text_field( wp_unslash( $_POST['lease_start'] ) ) : '',
				'lease_end'    => isset( $_POST['lease_end'] ) ? sanitize_text_field( wp_unslash( $_POST['lease_end'] ) ) : '',
				'rent_amount'  => isset( $_POST['rent_amount'] ) ? wp_unslash( $_POST['rent_amount'] ) : '',
				'deposit_paid' => isset( $_POST['deposit_paid'] ) ? wp_unslash( $_POST['deposit_paid'] ) : '',
				'status'       => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			);

			$id = isset( $_POST['tenant_id'] ) ? (int) $_POST['tenant_id'] : 0;

			$errors = self::validate( $data, $id );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			if ( $id > 0 ) {
				Limpeed_Tenants::update( $id, $data );
				Limpeed_Frontend::redirect_to( 'tenants', array( 'message' => 'updated' ) );
			} else {
				Limpeed_Tenants::insert( $data );
				Limpeed_Frontend::redirect_to( 'tenants', array( 'message' => 'created' ) );
			}
		}
	}

	/**
	 * Valide les données du formulaire.
	 *
	 * @param array $data
	 * @param int   $tenant_id Id du locataire en cours de modification (0 pour un ajout).
	 * @return array
	 */
	private static function validate( $data, $tenant_id = 0 ) {
		$errors = array();

		if ( empty( $data['property_id'] ) || ! Limpeed_Properties::get( $data['property_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un bien valide.', 'limpeed-immobilier' );
		} elseif ( 'actif' === ( $data['status'] ?? 'actif' ) ) {
			$current_tenant = Limpeed_Properties::get_current_tenant( $data['property_id'] );
			if ( $current_tenant && (int) $current_tenant->id !== (int) $tenant_id ) {
				$errors[] = __( 'Ce bien a déjà un locataire actif. Terminez d\'abord son bail avant d\'en ajouter un nouveau.', 'limpeed-immobilier' );
			}
		}

		if ( empty( trim( $data['full_name'] ) ) ) {
			$errors[] = __( 'Le nom complet du locataire est obligatoire.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors[] = __( 'L\'adresse email n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['phone'] ) && ! preg_match( '/^[0-9+\s().-]{6,20}$/', $data['phone'] ) ) {
			$errors[] = __( 'Le numéro de téléphone n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['lease_start'] ) && ! empty( $data['lease_end'] ) && $data['lease_start'] > $data['lease_end'] ) {
			$errors[] = __( 'La date de fin de bail doit être postérieure à la date de début.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Tenants::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		foreach ( array( 'rent_amount', 'deposit_paid' ) as $field ) {
			if ( '' !== $data[ $field ] && ! is_numeric( $data[ $field ] ) ) {
				$errors[] = __( 'Les montants (loyer, dépôt) doivent être des nombres.', 'limpeed-immobilier' );
				break;
			}
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Tenants', 'handle_request' ) );
