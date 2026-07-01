<?php
/**
 * Contrôleur frontend de la section "Réglages".
 * Miroir du contrôleur admin (admin/class-limpeed-settings-page.php) mais
 * routé via l'application frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Settings {

	/**
	 * Traite l'enregistrement des réglages (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'settings' !== Limpeed_Frontend::current_view() ) {
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

		Limpeed_Frontend::redirect_to( 'settings', array( 'message' => 'saved' ) );
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Settings', 'handle_request' ) );
