<?php
/**
 * Contrôleur de la page admin "Journal d'activité" (lecture seule, réservée aux limpeed_admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-activity-log-list-table.php';

class Limpeed_Activity_Log_Page {

	/**
	 * Affiche la page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_limpeed_agents' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$list_table = new Limpeed_Activity_Log_List_Table();
		$list_table->prepare_items();

		include LIMPEED_PLUGIN_DIR . 'admin/views/activity-log-list.php';
	}
}
