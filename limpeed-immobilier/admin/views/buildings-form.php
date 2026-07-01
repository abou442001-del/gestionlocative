<?php
/**
 * Vue : formulaire d'ajout / modification d'un édifice.
 *
 * @var object|null $building
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $owners
 * @var int         $preselected_owner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $building );

$field = function ( $name, $default = '' ) use ( $building, $posted, $is_edit ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	if ( $is_edit && isset( $building->$name ) ) {
		return $building->$name;
	}
	return $default;
};

$list_url = add_query_arg( array( 'page' => 'limpeed-buildings' ), admin_url( 'admin.php' ) );

$form_action = add_query_arg(
	array_filter(
		array(
			'page'   => 'limpeed-buildings',
			'action' => $is_edit ? 'edit' : 'add',
			'id'     => $is_edit ? $building->id : null,
		)
	),
	admin_url( 'admin.php' )
);
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier l\'édifice', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></h1>

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
				$owner = Limpeed_Owners::get( $building->owner_id );
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
				<?php
				$properties_url = add_query_arg(
					array(
						'page'        => 'limpeed-properties',
						'building_id' => $building->id,
					),
					admin_url( 'admin.php' )
				);
				$add_property_url = add_query_arg(
					array(
						'page'        => 'limpeed-properties',
						'action'      => 'add',
						'building_id' => $building->id,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $properties_url ); ?>"><?php esc_html_e( 'Voir les sous-édifices', 'limpeed-immobilier' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $add_property_url ); ?>"><?php esc_html_e( 'Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_building', 'limpeed_building_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="building_id" value="<?php echo esc_attr( $building->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select name="owner_id" id="owner_id" required>
							<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $owners as $owner_option ) : ?>
								<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id', $preselected_owner ), $owner_option->id ); ?>>
									<?php echo esc_html( $owner_option->full_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="name"><?php esc_html_e( 'Nom de l\'édifice', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="name" type="text" id="name" class="regular-text" required value="<?php echo esc_attr( $field( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : Résidence Les Palmiers', 'limpeed-immobilier' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label></th>
					<td><textarea name="address" id="address" class="large-text" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?></label></th>
					<td><textarea name="description" id="description" class="large-text" rows="3"><?php echo esc_textarea( $field( 'description' ) ); ?></textarea></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
