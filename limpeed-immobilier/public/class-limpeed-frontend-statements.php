<?php
/**
 * Contrôleur frontend de la section "Bordereaux".
 * Traitement des actions (génération/téléchargement/suppression), miroir du
 * contrôleur admin (admin/class-limpeed-statements-page.php) mais routé via
 * l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Statements {

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
	 * Le téléchargement d'un bordereau doit impérativement se faire ici,
	 * avant que le thème ou l'application frontend n'envoie du HTML.
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'statements' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_statements' ) ) {
			return;
		}

		// Téléchargement.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'download' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_download_statement_' . $id );

			$statement = Limpeed_Statements::get( $id );
			if ( ! $statement ) {
				wp_die( esc_html__( 'Bordereau introuvable.', 'limpeed-immobilier' ) );
			}

			$file_path = Limpeed_Statements::get_file_path( $statement );
			if ( ! file_exists( $file_path ) ) {
				wp_die( esc_html__( 'Le fichier PDF de ce bordereau est introuvable sur le serveur.', 'limpeed-immobilier' ) );
			}

			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
			exit;
		}

		// Export CSV de l'historique (respecte le filtre propriétaire courant).
		if ( isset( $_GET['action'] ) && 'export_csv' === $_GET['action'] ) {
			check_admin_referer( 'limpeed_export_statements_csv' );

			$owner_id = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;
			Limpeed_Statements::stream_csv( array( 'owner_id' => $owner_id ) );
		}

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_statement_' . $id );

			Limpeed_Statements::delete( $id );
			Limpeed_Frontend::redirect_to( 'statements', array( 'message' => 'deleted' ) );
		}

		// Génération d'un nouveau bordereau.
		if ( isset( $_POST['limpeed_statement_nonce'] ) ) {
			check_admin_referer( 'limpeed_generate_statement', 'limpeed_statement_nonce' );

			$data = array(
				'owner_id'               => isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0,
				'period_start'           => isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '',
				'period_end'             => isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '',
				'other_deduction_label'  => isset( $_POST['other_deduction_label'] ) ? sanitize_text_field( wp_unslash( $_POST['other_deduction_label'] ) ) : '',
				'other_deduction_amount' => isset( $_POST['other_deduction_amount'] ) ? (float) $_POST['other_deduction_amount'] : 0,
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$result = Limpeed_Statements::generate( $data['owner_id'], $data['period_start'], $data['period_end'], $data['other_deduction_label'], $data['other_deduction_amount'] );

			if ( is_wp_error( $result ) ) {
				self::$errors = array( $result->get_error_message() );
				self::$posted = $data;
				return;
			}

			Limpeed_Frontend::redirect_to( 'statements', array( 'message' => 'created' ) );
		}
	}

	/**
	 * Valide les données du formulaire de génération.
	 *
	 * @param array $data
	 * @return array
	 */
	private static function validate( $data ) {
		$errors = array();

		if ( empty( $data['owner_id'] ) || ! Limpeed_Owners::get( $data['owner_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un propriétaire valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['period_start'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $data['period_start'] ) ) {
			$errors[] = __( 'Le début de la période doit être un mois valide (format AAAA-MM).', 'limpeed-immobilier' );
		}

		if ( empty( $data['period_end'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $data['period_end'] ) ) {
			$errors[] = __( 'La fin de la période doit être un mois valide (format AAAA-MM).', 'limpeed-immobilier' );
		}

		if ( empty( $errors ) && $data['period_start'] > $data['period_end'] ) {
			$errors[] = __( 'Le mois de fin doit être postérieur ou égal au mois de début.', 'limpeed-immobilier' );
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Statements', 'handle_request' ) );
