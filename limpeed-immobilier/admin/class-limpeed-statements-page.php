<?php
/**
 * Contrôleur de la page admin "Bordereaux".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-statements-list-table.php';

class Limpeed_Statements_Page {

	const SLUG = 'limpeed-statements';

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
	 * Le téléchargement d'un bordereau doit impérativement se faire ici,
	 * avant que WordPress n'envoie les en-têtes HTML de la page admin.
	 */
	public static function handle_request() {
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) {
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

		// Suppression.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_delete_statement_' . $id );

			Limpeed_Statements::delete( $id );
			self::redirect( array( 'message' => 'deleted' ) );
		}

		// Génération d'un nouveau bordereau.
		if ( isset( $_POST['limpeed_statement_nonce'] ) ) {
			check_admin_referer( 'limpeed_generate_statement', 'limpeed_statement_nonce' );

			$data = array(
				'owner_id'     => isset( $_POST['owner_id'] ) ? (int) $_POST['owner_id'] : 0,
				'period_start' => isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '',
				'period_end'   => isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '',
			);

			$errors = self::validate( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$result = Limpeed_Statements::generate( $data['owner_id'], $data['period_start'], $data['period_end'] );

			if ( is_wp_error( $result ) ) {
				self::$errors = array( $result->get_error_message() );
				self::$posted = $data;
				return;
			}

			self::redirect( array( 'message' => 'created' ) );
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

	/**
	 * Redirige vers la liste des bordereaux avec des paramètres additionnels.
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
	 * Affiche la page (liste ou formulaire de génération).
	 */
	public function render() {
		if ( ! current_user_can( 'manage_limpeed_statements' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( 'add' === $action ) {
			$errors = self::$errors;
			$posted = self::$posted;
			$owners = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );

			include LIMPEED_PLUGIN_DIR . 'admin/views/statements-form.php';
			return;
		}

		$list_table = new Limpeed_Statements_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/statements-list.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Statements_Page', 'handle_request' ) );
