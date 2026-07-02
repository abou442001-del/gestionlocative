<?php
/**
 * Contenu frontend de la section "Journal d'activité" (lecture seule).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$object_type = isset( $_GET['object_type'] ) ? sanitize_text_field( wp_unslash( $_GET['object_type'] ) ) : '';
$action_filter = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page    = 30;

$args = array(
	'object_type' => $object_type,
	'action'      => $action_filter,
	'per_page'    => $per_page,
	'paged'       => $paged,
);

$total_items = Limpeed_Activity_Log::count( $args );
$entries     = Limpeed_Activity_Log::get_all( $args );
?>

<div class="limpeed-app-panel">
	<p class="limpeed-app-description"><?php esc_html_e( 'Historique des créations, modifications et suppressions effectuées par les agents.', 'limpeed-immobilier' ); ?></p>

	<form method="get" class="limpeed-app-search">
		<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
		<input type="hidden" name="limpeed_view" value="activity-log">
		<select name="object_type">
			<option value=""><?php esc_html_e( 'Tous les éléments', 'limpeed-immobilier' ); ?></option>
			<?php foreach ( Limpeed_Activity_Log::get_object_types() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $object_type, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="action_filter">
			<option value=""><?php esc_html_e( 'Toutes les actions', 'limpeed-immobilier' ); ?></option>
			<?php foreach ( Limpeed_Activity_Log::get_actions() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $action_filter, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
	</form>

	<table class="limpeed-app-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Agent', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Action', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Élément', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Détail', 'limpeed-immobilier' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'Aucune activité enregistrée pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
			<?php endif; ?>
			<?php
			$actions_labels = Limpeed_Activity_Log::get_actions();
			$types_labels   = Limpeed_Activity_Log::get_object_types();
			foreach ( $entries as $entry ) :
				$user = $entry->user_id ? get_userdata( $entry->user_id ) : false;
				?>
				<tr>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->created_at ) ); ?></td>
					<td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Utilisateur supprimé', 'limpeed-immobilier' ); ?></td>
					<td><?php echo isset( $actions_labels[ $entry->action ] ) ? esc_html( $actions_labels[ $entry->action ] ) : esc_html( $entry->action ); ?></td>
					<td><?php echo isset( $types_labels[ $entry->object_type ] ) ? esc_html( $types_labels[ $entry->object_type ] ) : esc_html( $entry->object_type ); ?></td>
					<td><?php echo esc_html( $entry->description ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'object_type' => $object_type, 'action_filter' => $action_filter ) ); ?>
</div>
