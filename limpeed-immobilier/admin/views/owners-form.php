<?php
/**
 * Vue : formulaire d'ajout / modification d'un propriétaire.
 *
 * @var object|null $owner
 * @var array       $errors
 * @var array|null  $posted
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $owner );

$field = function ( $name, $default = '' ) use ( $owner, $posted, $is_edit ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	if ( $is_edit && isset( $owner->$name ) ) {
		return $owner->$name;
	}
	return $default;
};

$list_url = add_query_arg( array( 'page' => 'limpeed-owners' ), admin_url( 'admin.php' ) );
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier le propriétaire', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un propriétaire', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-owners' ) ); ?>">
		<?php wp_nonce_field( 'limpeed_save_owner', 'limpeed_owner_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="owner_id" value="<?php echo esc_attr( $owner->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="full_name"><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="full_name" type="text" id="full_name" class="regular-text" required value="<?php echo esc_attr( $field( 'full_name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="phone"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="phone" type="text" id="phone" class="regular-text" value="<?php echo esc_attr( $field( 'phone' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="email" type="email" id="email" class="regular-text" value="<?php echo esc_attr( $field( 'email' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label></th>
					<td><textarea name="address" id="address" class="large-text" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="bank_details"><?php esc_html_e( 'Coordonnées bancaires (RIB/IBAN ou mobile money)', 'limpeed-immobilier' ); ?></label></th>
					<td><textarea name="bank_details" id="bank_details" class="large-text" rows="3"><?php echo esc_textarea( $field( 'bank_details' ) ); ?></textarea></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
