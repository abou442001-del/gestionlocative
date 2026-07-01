<?php
/**
 * Vue : liste des agents.
 *
 * @var Limpeed_Agents_List_Table $list_table
 * @var WP_User[]                 $pending
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$add_url = add_query_arg(
	array(
		'page'   => 'limpeed-agents',
		'action' => 'add',
	),
	admin_url( 'admin.php' )
);

$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>
<div class="wrap limpeed-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Agents', 'limpeed-immobilier' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Ajouter un agent', 'limpeed-immobilier' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( 'created' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Compte agent créé avec succès. Un email lui a été envoyé pour définir son mot de passe.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rôle mis à jour avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'revoked' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Accès révoqué avec succès. Le compte WordPress est conservé.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'approved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demande approuvée : l\'agent a été notifié par email.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'rejected' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demande rejetée et compte supprimé.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'error' === $message ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( isset( $_GET['error_text'] ) ? sanitize_text_field( wp_unslash( $_GET['error_text'] ) ) : __( 'Une erreur est survenue.', 'limpeed-immobilier' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $pending ) ) : ?>
		<h2><?php esc_html_e( 'Demandes en attente', 'limpeed-immobilier' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Date de la demande', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending as $candidate ) : ?>
					<?php
					$reject_url = wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'limpeed-agents',
								'action' => 'reject',
								'id'     => $candidate->ID,
							),
							admin_url( 'admin.php' )
						),
						'limpeed_reject_agent_' . $candidate->ID
					);
					?>
					<tr>
						<td><?php echo esc_html( $candidate->user_login ); ?></td>
						<td><?php echo esc_html( $candidate->user_email ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $candidate->user_registered ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-agents' ) ); ?>" style="display:inline-block;">
								<?php wp_nonce_field( 'limpeed_approve_agent_' . $candidate->ID, 'limpeed_approve_nonce' ); ?>
								<input type="hidden" name="pending_id" value="<?php echo esc_attr( $candidate->ID ); ?>">
								<select name="role">
									<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'limpeed_agent', $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Approuver', 'limpeed-immobilier' ); ?></button>
							</form>
							<a href="<?php echo esc_url( $reject_url ); ?>" class="button limpeed-confirm-delete"><?php esc_html_e( 'Rejeter', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Agents actifs', 'limpeed-immobilier' ); ?></h2>

	<form method="get">
		<input type="hidden" name="page" value="limpeed-agents">
		<?php $list_table->search_box( __( 'Rechercher un agent', 'limpeed-immobilier' ), 'limpeed-agent-search' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
