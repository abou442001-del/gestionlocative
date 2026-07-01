<?php
/**
 * Vue : modification du rôle d'un agent existant.
 *
 * @var WP_User    $agent
 * @var array      $errors
 * @var array|null $posted
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field = function ( $name, $default = '' ) use ( $posted, $agent ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	return $default;
};

$current_roles = array_intersect( $agent->roles, array_keys( Limpeed_Agents::get_available_roles() ) );
$current_role  = reset( $current_roles );

$list_url    = add_query_arg( array( 'page' => 'limpeed-agents' ), admin_url( 'admin.php' ) );
$form_action = add_query_arg( array( 'page' => 'limpeed-agents', 'action' => 'edit', 'id' => $agent->ID ), admin_url( 'admin.php' ) );
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Modifier le rôle de l\'agent', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_agent', 'limpeed_agent_nonce' ); ?>
		<input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->ID ); ?>">

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
					<td><?php echo esc_html( $agent->user_login ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></th>
					<td><?php echo esc_html( $agent->user_email ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="role"><?php esc_html_e( 'Rôle', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="role" id="role">
							<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'role', $current_role ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Mettre à jour le rôle', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
