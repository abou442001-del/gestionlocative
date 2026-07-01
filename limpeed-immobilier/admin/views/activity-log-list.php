<?php
/**
 * Vue : journal d'activité (lecture seule).
 *
 * @var Limpeed_Activity_Log_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Journal d\'activité', 'limpeed-immobilier' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Historique des créations, modifications et suppressions effectuées par les agents.', 'limpeed-immobilier' ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="limpeed-activity-log">
		<?php $list_table->display(); ?>
	</form>
</div>
