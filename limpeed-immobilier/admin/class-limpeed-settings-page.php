<?php
/**
 * Page de réglages du plugin.
 * Contient notamment la confirmation explicite requise avant toute
 * suppression des données à la désinstallation (uninstall.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Settings_Page {

	const SLUG = 'limpeed-settings';

	/**
	 * Traite l'enregistrement des réglages (hook admin_init).
	 */
	public static function handle_request() {
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_agents' ) ) {
			return;
		}

		if ( ! isset( $_POST['limpeed_settings_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'limpeed_save_settings', 'limpeed_settings_nonce' );

		$confirm = isset( $_POST['limpeed_confirm_data_deletion'] ) && '1' === $_POST['limpeed_confirm_data_deletion'];
		update_option( 'limpeed_confirm_data_deletion', $confirm ? '1' : '0' );

		$advance_months = isset( $_POST['limpeed_advance_months'] ) ? (int) $_POST['limpeed_advance_months'] : 1;
		$advance_months = min( 12, max( 1, $advance_months ) );
		update_option( 'limpeed_advance_months', $advance_months );

		if ( isset( $_POST['limpeed_remove_logo'] ) && '1' === $_POST['limpeed_remove_logo'] ) {
			Limpeed_Branding::remove_logo();
		} elseif ( ! empty( $_FILES['limpeed_logo']['name'] ) ) {
			$result = Limpeed_Branding::save_uploaded_logo( $_FILES['limpeed_logo'] );
			if ( is_wp_error( $result ) ) {
				self::redirect( array( 'message' => 'error', 'error_text' => rawurlencode( $result->get_error_message() ) ) );
			}
		}

		self::redirect( array( 'message' => 'saved' ) );
	}

	/**
	 * Redirige vers la page de réglages avec des paramètres additionnels.
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
	 * Affiche la page de réglages.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_limpeed_agents' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$confirm        = '1' === get_option( 'limpeed_confirm_data_deletion', '0' );
		$advance_months = (int) get_option( 'limpeed_advance_months', 1 );
		$logo_url       = Limpeed_Branding::get_logo_url();
		$message        = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

		include LIMPEED_PLUGIN_DIR . 'admin/views/settings.php';
	}
}

add_action( 'admin_init', array( 'Limpeed_Settings_Page', 'handle_request' ) );
