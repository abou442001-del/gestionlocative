<?php
/**
 * Contenu frontend de la section "Édifices" (liste + formulaire).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

if ( in_array( $action, array( 'add', 'edit' ), true ) ) :
	// -----------------------------------------------------------------
	// Formulaire d'ajout / modification.
	// -----------------------------------------------------------------
	$building = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$building = Limpeed_Buildings::get( (int) $_GET['id'] );
		if ( ! $building ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Édifice introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit           = ! empty( $building );
	$errors            = Limpeed_Frontend_Buildings::$errors;
	$posted            = Limpeed_Frontend_Buildings::$posted;
	$posted_sub_units  = Limpeed_Frontend_Buildings::$posted_sub_units;
	$owners            = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$preselected_owner = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;

	$field = function ( $name, $default = '' ) use ( $building, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $building->$name ) ) {
			return $building->$name;
		}
		return $default;
	};

	$sub_unit_rows      = ! empty( $posted_sub_units ) ? $posted_sub_units : array( array() );
	$property_types     = Limpeed_Properties::get_types();
	$property_statuses  = Limpeed_Properties::get_statuses();

	$sub_unit_field = function ( $row, $name, $default = '' ) {
		return isset( $row[ $name ] ) && '' !== $row[ $name ] ? $row[ $name ] : $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier l\'édifice', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$owner = Limpeed_Owners::get( $building->owner_id );
				if ( $owner ) {
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'building_id' => $building->id ) ) ); ?>"><?php esc_html_e( 'Voir les sous-édifices', 'limpeed-immobilier' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'add', 'building_id' => $building->id ) ) ); ?>"><?php esc_html_e( 'Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></a>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', $is_edit ? array( 'action' => 'edit', 'id' => $building->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_building', 'limpeed_building_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="building_id" value="<?php echo esc_attr( $building->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="owner_id" id="owner_id" required>
					<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id', $preselected_owner ), $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="building_name"><?php esc_html_e( 'Nom de l\'édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="building_name" id="building_name" required value="<?php echo esc_attr( $field( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : Résidence Les Palmiers', 'limpeed-immobilier' ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label>
				<textarea name="address" id="address" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>
			<div class="limpeed-form-row">
				<label for="description"><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?></label>
				<textarea name="description" id="description" rows="3"><?php echo esc_textarea( $field( 'description' ) ); ?></textarea>
			</div>

			<h3><?php esc_html_e( 'Sous-édifices (biens)', 'limpeed-immobilier' ); ?></h3>
			<p class="limpeed-app-description"><?php esc_html_e( 'Ajoutez ici directement les sous-édifices (biens) composant cet édifice. Vous pourrez toujours en ajouter ou en modifier plus tard depuis la section Biens.', 'limpeed-immobilier' ); ?></p>

			<table class="limpeed-app-table" id="limpeed-sub-units-table">
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
							<td><input type="text" name="sub_units[<?php echo esc_attr( $index ); ?>][reference]" value="<?php echo esc_attr( $sub_unit_field( $row, 'reference' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : A1', 'limpeed-immobilier' ); ?>"></td>
							<td><input type="text" name="sub_units[<?php echo esc_attr( $index ); ?>][address]" value="<?php echo esc_attr( $sub_unit_field( $row, 'address' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : RDC Gauche', 'limpeed-immobilier' ); ?>"></td>
							<td>
								<select name="sub_units[<?php echo esc_attr( $index ); ?>][type]">
									<?php foreach ( $property_types as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_unit_field( $row, 'type', 'appartement' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][monthly_rent]" value="<?php echo esc_attr( $sub_unit_field( $row, 'monthly_rent', 0 ) ); ?>"></td>
							<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][charges]" value="<?php echo esc_attr( $sub_unit_field( $row, 'charges', 0 ) ); ?>"></td>
							<td><input type="number" step="0.01" min="0" name="sub_units[<?php echo esc_attr( $index ); ?>][deposit_amount]" value="<?php echo esc_attr( $sub_unit_field( $row, 'deposit_amount', 0 ) ); ?>"></td>
							<td>
								<select name="sub_units[<?php echo esc_attr( $index ); ?>][status]">
									<?php foreach ( $property_statuses as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_unit_field( $row, 'status', 'vacant' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><button type="button" class="limpeed-app-btn limpeed-app-btn-secondary limpeed-remove-sub-unit-row"><?php esc_html_e( 'Retirer', 'limpeed-immobilier' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" id="limpeed-add-sub-unit-row"><?php esc_html_e( '+ Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></button></p>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

	<template id="limpeed-sub-unit-row-template">
		<tr class="limpeed-sub-unit-row">
			<td><input type="text" name="sub_units[__INDEX__][reference]" value="" placeholder="<?php esc_attr_e( 'Ex : A1', 'limpeed-immobilier' ); ?>"></td>
			<td><input type="text" name="sub_units[__INDEX__][address]" value="" placeholder="<?php esc_attr_e( 'Ex : RDC Gauche', 'limpeed-immobilier' ); ?>"></td>
			<td>
				<select name="sub_units[__INDEX__][type]">
					<?php foreach ( $property_types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'appartement', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][monthly_rent]" value="0"></td>
			<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][charges]" value="0"></td>
			<td><input type="number" step="0.01" min="0" name="sub_units[__INDEX__][deposit_amount]" value="0"></td>
			<td>
				<select name="sub_units[__INDEX__][status]">
					<?php foreach ( $property_statuses as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'vacant', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><button type="button" class="limpeed-app-btn limpeed-app-btn-secondary limpeed-remove-sub-unit-row"><?php esc_html_e( 'Retirer', 'limpeed-immobilier' ); ?></button></td>
		</tr>
	</template>
	<script>
	( function () {
		var rowsBody  = document.getElementById( 'limpeed-sub-units-rows' );
		var addButton = document.getElementById( 'limpeed-add-sub-unit-row' );
		var template  = document.getElementById( 'limpeed-sub-unit-row-template' );
		var nextIndex = <?php echo (int) count( $sub_unit_rows ); ?>;

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

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$owner_id = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;

	$args = array(
		'search'   => $search,
		'owner_id' => $owner_id,
		'per_page' => 9999,
	);

	$buildings     = Limpeed_Buildings::get_all( $args );
	$filter_owners = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );

	// Regroupe les édifices par propriétaire, propriétaires triés par nom,
	// pour une navigation rangée par propriétaire plutôt qu'une liste plate.
	$owners_by_id = array();
	foreach ( $filter_owners as $filter_owner ) {
		$owners_by_id[ (int) $filter_owner->id ] = $filter_owner;
	}

	$buildings_by_owner = array();
	foreach ( $buildings as $building_row ) {
		$buildings_by_owner[ (int) $building_row->owner_id ][] = $building_row;
	}

	uksort(
		$buildings_by_owner,
		function ( $a, $b ) use ( $owners_by_id ) {
			$name_a = isset( $owners_by_id[ $a ] ) ? $owners_by_id[ $a ]->full_name : '';
			$name_b = isset( $owners_by_id[ $b ] ) ? $owners_by_id[ $b ]->full_name : '';
			return strcasecmp( $name_a, $name_b );
		}
	);
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
				<input type="hidden" name="limpeed_view" value="buildings">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un édifice...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<select name="owner_id">
					<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $owner_id, $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Édifice ajouté avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Édifice mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Édifice supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<?php if ( empty( $buildings_by_owner ) ) : ?>
			<p><?php esc_html_e( 'Aucun édifice pour le moment.', 'limpeed-immobilier' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $buildings_by_owner as $group_owner_id => $owner_buildings ) : ?>
			<?php $group_owner = $owners_by_id[ $group_owner_id ] ?? null; ?>
			<h3>
				<?php if ( $group_owner ) : ?>
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'view', 'id' => $group_owner->id ) ) ); ?>"><?php echo esc_html( $group_owner->full_name ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Sans propriétaire', 'limpeed-immobilier' ); ?>
				<?php endif; ?>
			</h3>
			<table class="limpeed-app-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Sous-édifices', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $owner_buildings as $building_row ) : ?>
						<?php
						$edit_url       = Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $building_row->id ) );
						$delete_url     = wp_nonce_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'delete', 'id' => $building_row->id ) ), 'limpeed_delete_building_' . $building_row->id );
						$properties_n   = Limpeed_Properties::count( array( 'building_id' => $building_row->id ) );
						$properties_url = Limpeed_Frontend::app_url( 'properties', array( 'building_id' => $building_row->id ) );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $building_row->name ); ?></a></td>
							<td><?php echo $building_row->address ? esc_html( $building_row->address ) : '&mdash;'; ?></td>
							<td>
								<?php if ( $properties_n > 0 ) : ?>
									<a href="<?php echo esc_url( $properties_url ); ?>"><?php echo esc_html( $properties_n ); ?></a>
								<?php else : ?>
									0
								<?php endif; ?>
							</td>
							<td class="limpeed-app-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
								<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de cet édifice ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
