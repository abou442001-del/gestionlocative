<?php
/**
 * Vue : formulaire d'ajout / modification d'un édifice.
 * Permet également d'ajouter directement plusieurs sous-édifices (biens).
 *
 * @var object|null $building
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $posted_sub_units
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

$sub_unit_rows = ! empty( $posted_sub_units ) ? $posted_sub_units : array( array() );
$property_types    = Limpeed_Properties::get_types();
$property_statuses = Limpeed_Properties::get_statuses();

$sub_unit_field = function ( $row, $name, $default = '' ) {
	return isset( $row[ $name ] ) && '' !== $row[ $name ] ? $row[ $name ] : $default;
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

		<h2><?php esc_html_e( 'Sous-édifices (biens)', 'limpeed-immobilier' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Ajoutez ici directement les sous-édifices (biens) composant cet édifice. Vous pourrez toujours en ajouter ou en modifier plus tard depuis la section Biens.', 'limpeed-immobilier' ); ?></p>

		<table class="wp-list-table widefat fixed striped" id="limpeed-sub-units-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Adresse / repère', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Dépôt', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="limpeed-sub-units-rows">
				<?php foreach ( $sub_unit_rows as $index => $row ) : ?>
					<tr class="limpeed-sub-unit-row">
						<td><input type="text" name="sub_units[<?php echo esc_attr( $index ); ?>][reference]" value="<?php echo esc_attr( $sub_unit_field( $row, 'reference' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : A1', 'limpeed-immobilier' ); ?>" class="small-text"></td>
						<td><input type="text" name="sub_units[<?php echo esc_attr( $index ); ?>][address]" value="<?php echo esc_attr( $sub_unit_field( $row, 'address' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : RDC Gauche', 'limpeed-immobilier' ); ?>" class="regular-text"></td>
						<td>
							<select name="sub_units[<?php echo esc_attr( $index ); ?>][type]">
								<?php foreach ( $property_types as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_unit_field( $row, 'type', 'appartement' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][monthly_rent]" value="<?php echo esc_attr( $sub_unit_field( $row, 'monthly_rent', 0 ) ); ?>" class="small-text"></td>
						<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][charges]" value="<?php echo esc_attr( $sub_unit_field( $row, 'charges', 0 ) ); ?>" class="small-text"></td>
						<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][deposit_amount]" value="<?php echo esc_attr( $sub_unit_field( $row, 'deposit_amount', 0 ) ); ?>" class="small-text"></td>
						<td>
							<select name="sub_units[<?php echo esc_attr( $index ); ?>][status]">
								<?php foreach ( $property_statuses as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_unit_field( $row, 'status', 'vacant' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><button type="button" class="button limpeed-remove-sub-unit-row"><?php esc_html_e( 'Retirer', 'limpeed-immobilier' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="limpeed-add-sub-unit-row"><?php esc_html_e( '+ Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></button></p>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>

<template id="limpeed-sub-unit-row-template">
	<tr class="limpeed-sub-unit-row">
		<td><input type="text" name="sub_units[__INDEX__][reference]" value="" placeholder="<?php esc_attr_e( 'Ex : A1', 'limpeed-immobilier' ); ?>" class="small-text"></td>
		<td><input type="text" name="sub_units[__INDEX__][address]" value="" placeholder="<?php esc_attr_e( 'Ex : RDC Gauche', 'limpeed-immobilier' ); ?>" class="regular-text"></td>
		<td>
			<select name="sub_units[__INDEX__][type]">
				<?php foreach ( $property_types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'appartement', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][monthly_rent]" value="0" class="small-text"></td>
		<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][charges]" value="0" class="small-text"></td>
		<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][deposit_amount]" value="0" class="small-text"></td>
		<td>
			<select name="sub_units[__INDEX__][status]">
				<?php foreach ( $property_statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'vacant', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><button type="button" class="button limpeed-remove-sub-unit-row"><?php esc_html_e( 'Retirer', 'limpeed-immobilier' ); ?></button></td>
	</tr>
</template>
<script>
( function () {
	var rowsBody   = document.getElementById( 'limpeed-sub-units-rows' );
	var addButton  = document.getElementById( 'limpeed-add-sub-unit-row' );
	var template   = document.getElementById( 'limpeed-sub-unit-row-template' );
	var nextIndex  = <?php echo (int) count( $sub_unit_rows ); ?>;

	if ( ! rowsBody || ! addButton || ! template ) {
		return;
	}

	function bindRemove( row ) {
		var btn = row.querySelector( '.limpeed-remove-sub-unit-row' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				row.parentNode.removeChild( row );
			} );
		}
	}

	Array.prototype.forEach.call( rowsBody.querySelectorAll( '.limpeed-sub-unit-row' ), bindRemove );

	addButton.addEventListener( 'click', function () {
		var html = template.innerHTML.split( '__INDEX__' ).join( String( nextIndex ) );
		nextIndex++;
		var wrapper = document.createElement( 'tbody' );
		wrapper.innerHTML = html.trim();
		var row = wrapper.firstElementChild;
		rowsBody.appendChild( row );
		bindRemove( row );
	} );
} )();
</script>
