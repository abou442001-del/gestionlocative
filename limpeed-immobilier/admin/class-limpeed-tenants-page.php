<?php
/**
 * Contrôleur de la page admin "Locataires".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-tenants-list-table.php';

class Limpeed_Tenants_Page {

	const SLUG = 'limpeed-tenants';

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

		if ( ! current_user_can( 'manage_limpeed_tenants' ) ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_tenant_' . $id );

			Limpeed_Tenants::delete( $id );
			self::redirect( array( 'message' => 'deleted' ) );
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
				self::redirect( array( 'message' => 'updated' ) );
			} else {
				Limpeed_Tenants::insert( $data );
				self::redirect( array( 'message' => 'created' ) );
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

	/**
	 * Redirige vers la liste des locataires avec des paramètres additionnels.
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
		if ( ! current_user_can( 'manage_limpeed_tenants' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$tenant = null;
			if ( 'edit' === $action && isset( $_GET['id'] ) ) {
				$tenant = Limpeed_Tenants::get( (int) $_GET['id'] );
				if ( ! $tenant ) {
					wp_die( esc_html__( 'Locataire introuvable.', 'limpeed-immobilier' ) );
				}
			}

			$errors     = self::$errors;
			$posted     = self::$posted;
			$owners     = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
			$buildings  = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
			$properties = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );

			include LIMPEED_PLUGIN_DIR . 'admin/views/tenants-form.php';
			return;
		}

		$list_table = new Limpeed_Tenants_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/tenants-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Tenants_Page', 'handle_request' ) );
