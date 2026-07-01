<?php
/**
 * Vue : formulaire de création d'un compte agent.
 *
 * @var array      $errors
 * @var array|null $posted
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field = function ( $name, $default = '' ) use ( $posted ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	return $default;
};

$list_url    = add_query_arg( array( 'page' => 'limpeed-agents' ), admin_url( 'admin.php' ) );
$form_action = add_query_arg( array( 'page' => 'limpeed-agents', 'action' => 'add' ), admin_url( 'admin.php' ) );
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Ajouter un agent', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<p class="description"><?php esc_html_e( 'Un compte WordPress est créé avec le rôle choisi. L\'agent reçoit un email pour définir son propre mot de passe.', 'limpeed-immobilier' ); ?></p>

	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_agent', 'limpeed_agent_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="user_login"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="user_login" type="text" id="user_login" class="regular-text" required value="<?php echo esc_attr( $field( 'user_login' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="display_name"><?php esc_html_e( 'Nom affiché', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="display_name" type="text" id="display_name" class="regular-text" value="<?php echo esc_attr( $field( 'display_name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="user_email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="user_email" type="email" id="user_email" class="regular-text" required value="<?php echo esc_attr( $field( 'user_email' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="role"><?php esc_html_e( 'Rôle', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="role" id="role">
							<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'role', 'limpeed_agent' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Créer le compte agent', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
