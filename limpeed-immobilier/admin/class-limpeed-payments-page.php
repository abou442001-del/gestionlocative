<?php
/**
 * Contrôleur de la page admin "Paiements".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-payments-list-table.php';

class Limpeed_Payments_Page {

	const SLUG = 'limpeed-payments';

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

		if ( ! current_user_can( 'manage_limpeed_payments' ) ) {
			return;
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_payment_' . $id );

			Limpeed_Payments::delete( $id );
			self::redirect( array( 'message' => 'deleted' ) );
		}

		// Ajout ou modification.
		if ( isset( $_POST['limpeed_payment_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_payment', 'limpeed_payment_nonce' );

			$tenant_id = isset( $_POST['tenant_id'] ) ? (int) $_POST['tenant_id'] : 0;
			$tenant    = $tenant_id ? Limpeed_Tenants::get( $tenant_id ) : null;

			$data = array(
				'tenant_id'         => $tenant_id,
				'property_id'       => $tenant ? (int) $tenant->property_id : 0,
				'amount'            => isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '',
				'payment_date'      => isset( $_POST['payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) : '',
				'period'            => isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '',
				'status'            => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
				'payment_method'    => isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '',
				'commission_amount' => isset( $_POST['commission_amount'] ) ? wp_unslash( $_POST['commission_amount'] ) : '',
			);

			$errors = self::validate( $data, $tenant );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$id = isset( $_POST['payment_id'] ) ? (int) $_POST['payment_id'] : 0;

			if ( $id > 0 ) {
				Limpeed_Payments::update( $id, $data );
				self::redirect( array( 'message' => 'updated' ) );
			} else {
				Limpeed_Payments::insert( $data );
				self::redirect( array( 'message' => 'created' ) );
			}
		}
	}

	/**
	 * Valide les données du formulaire.
	 *
	 * @param array      $data
	 * @param object|null $tenant
	 * @return array
	 */
	private static function validate( $data, $tenant ) {
		$errors = array();

		if ( ! $tenant ) {
			$errors[] = __( 'Veuillez sélectionner un locataire valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['period'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $data['period'] ) ) {
			$errors[] = __( 'Veuillez indiquer un mois concerné valide (format AAAA-MM).', 'limpeed-immobilier' );
		}

		if ( '' === $data['amount'] || ! is_numeric( $data['amount'] ) ) {
			$errors[] = __( 'Le montant du paiement doit être un nombre.', 'limpeed-immobilier' );
		}

		if ( '' !== $data['commission_amount'] && ! is_numeric( $data['commission_amount'] ) ) {
			$errors[] = __( 'La commission doit être un nombre.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Payments::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['payment_method'], Limpeed_Payments::get_payment_methods() ) ) {
			$errors[] = __( 'Le mode de paiement sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * Redirige vers la liste des paiements avec des paramètres additionnels.
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
		if ( ! current_user_can( 'manage_limpeed_payments' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$payment = null;
			if ( 'edit' === $action && isset( $_GET['id'] ) ) {
				$payment = Limpeed_Payments::get( (int) $_GET['id'] );
				if ( ! $payment ) {
					wp_die( esc_html__( 'Paiement introuvable.', 'limpeed-immobilier' ) );
				}
			}

			$errors             = self::$errors;
			$posted             = self::$posted;
			$tenants            = Limpeed_Tenants::get_all( array( 'per_page' => 9999 ) );
			$preselected_tenant = isset( $_GET['tenant_id'] ) ? (int) $_GET['tenant_id'] : 0;
			$preselected_period = isset( $_GET['period'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '';

			include LIMPEED_PLUGIN_DIR . 'admin/views/payments-form.php';
			return;
		}

		$list_table = new Limpeed_Payments_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/payments-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Payments_Page', 'handle_request' ) );
