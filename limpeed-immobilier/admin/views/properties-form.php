<?php
/**
 * Vue : formulaire d'ajout / modification d'un bien.
 *
 * @var object|null $property
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $owners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $property );

$field = function ( $name, $default = '' ) use ( $property, $posted, $is_edit ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	if ( $is_edit && isset( $property->$name ) ) {
		return $property->$name;
	}
	return $default;
};

$list_url        = add_query_arg( array( 'page' => 'limpeed-properties' ), admin_url( 'admin.php' ) );
$current_tenant  = $is_edit ? Limpeed_Properties::get_current_tenant( $property->id ) : null;
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier le bien', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un bien', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $is_edit ) : ?>
		<div class="limpeed-cross-nav">
			<p>
				<?php
				$owner = Limpeed_Owners::get( $property->owner_id );
				if ( $owner ) {
					$owner_url = add_query_arg(
						array(
							'page'   => 'limpeed-owners',
							'action' => 'edit',
							'id'     => $owner->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $owner_url ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php if ( $current_tenant ) : ?>
					<?php
					$tenant_url = add_query_arg(
						array(
							'page'   => 'limpeed-tenants',
							'action' => 'edit',
							'id'     => $current_tenant->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers le locataire actuel */
						esc_html__( 'Locataire actuel : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $tenant_url ) . '">' . esc_html( $current_tenant->full_name ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Aucun locataire actuel', 'limpeed-immobilier' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-properties' ) ); ?>">
		<?php wp_nonce_field( 'limpeed_save_property', 'limpeed_property_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select name="owner_id" id="owner_id" required>
							<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $owners as $owner_option ) : ?>
								<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id' ), $owner_option->id ); ?>>
									<?php echo esc_html( $owner_option->full_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><textarea name="address" id="address" class="large-text" rows="3" required><?php echo esc_textarea( $field( 'address' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="type"><?php esc_html_e( 'Type de bien', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="type" id="type">
							<?php foreach ( Limpeed_Properties::get_types() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'type', 'appartement' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="monthly_rent"><?php esc_html_e( 'Loyer mensuel', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="monthly_rent" type="number" step="0.01" min="0" id="monthly_rent" class="regular-text" value="<?php echo esc_attr( $field( 'monthly_rent', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="charges"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="charges" type="number" step="0.01" min="0" id="charges" class="regular-text" value="<?php echo esc_attr( $field( 'charges', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="deposit_amount"><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="deposit_amount" type="number" step="0.01" min="0" id="deposit_amount" class="regular-text" value="<?php echo esc_attr( $field( 'deposit_amount', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'vacant' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Le statut "Loué"/"Vacant" est mis à jour automatiquement selon la présence d\'un locataire actif.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
