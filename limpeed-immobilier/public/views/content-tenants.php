<?php
/**
 * Contenu frontend de la section "Locataires" (liste + formulaire).
 * Sélection en cascade : propriétaire → édifice → sous-édifice (bien loué).
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
	$tenant = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$tenant = Limpeed_Tenants::get( (int) $_GET['id'] );
		if ( ! $tenant ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Locataire introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit    = ! empty( $tenant );
	$errors     = Limpeed_Frontend_Tenants::$errors;
	$posted     = Limpeed_Frontend_Tenants::$posted;
	$owners     = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$buildings  = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
	$properties = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );

	$field = function ( $name, $default = '' ) use ( $tenant, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $tenant->$name ) ) {
			return $tenant->$name;
		}
		return $default;
	};

	// Détermine l'édifice et le propriétaire actuellement liés au bien sélectionné,
	// pour pré-remplir correctement la cascade dès le premier affichage.
	$current_property_id = (int) $field( 'property_id' );
	$current_building_id = 0;
	$current_owner_id    = 0;

	if ( $current_property_id ) {
		$current_property = Limpeed_Properties::get( $current_property_id );
		if ( $current_property ) {
			$current_building_id = (int) $current_property->building_id;
			$current_building    = Limpeed_Buildings::get( $current_building_id );
			if ( $current_building ) {
				$current_owner_id = (int) $current_building->owner_id;
			}
		}
	}

	$buildings_for_owner = array_filter(
		$buildings,
		function ( $b ) use ( $current_owner_id ) {
			return (int) $b->owner_id === $current_owner_id;
		}
	);
	$properties_for_building = array_filter(
		$properties,
		function ( $p ) use ( $current_building_id ) {
			return (int) $p->building_id === $current_building_id;
		}
	);

	$buildings_json = array_map(
		function ( $b ) {
			return array( 'id' => (int) $b->id, 'owner_id' => (int) $b->owner_id, 'label' => $b->name );
		},
		$buildings
	);
	$properties_json = array_map(
		function ( $p ) {
			return array( 'id' => (int) $p->id, 'building_id' => (int) $p->building_id, 'label' => $p->address );
		},
		$properties
	);
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le locataire', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$property = Limpeed_Properties::get( $tenant->property_id );
				if ( $property ) {
					printf(
						/* translators: %s: lien vers le bien loué */
						esc_html__( 'Bien loué : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $property->id ) ) ) . '">' . esc_html( $property->address ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', array( 'tenant_id' => $tenant->id ) ) ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', $is_edit ? array( 'action' => 'edit', 'id' => $tenant->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_tenant', 'limpeed_tenant_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="limpeed_ui_owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select id="limpeed_ui_owner_id">
					<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $current_owner_id, $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="limpeed_ui_building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select id="limpeed_ui_building_id" <?php disabled( empty( $current_owner_id ) ); ?>>
					<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $buildings_for_owner as $building_option ) : ?>
						<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( $current_building_id, $building_option->id ); ?>>
							<?php echo esc_html( $building_option->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="property_id"><?php esc_html_e( 'Sous-édifice (bien loué)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="property_id" id="property_id" required <?php disabled( empty( $current_building_id ) ); ?>>
					<option value=""><?php esc_html_e( '— Choisir un sous-édifice —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $properties_for_building as $property_option ) : ?>
						<option value="<?php echo esc_attr( $property_option->id ); ?>" <?php selected( $current_property_id, $property_option->id ); ?>>
							<?php echo esc_html( $property_option->address ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Choisissez d\'abord le propriétaire, puis l\'édifice, pour afficher ses sous-édifices disponibles.', 'limpeed-immobilier' ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="full_name"><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="full_name" id="full_name" required value="<?php echo esc_attr( $field( 'full_name' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="phone"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="phone" id="phone" value="<?php echo esc_attr( $field( 'phone' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label>
				<input type="email" name="email" id="email" value="<?php echo esc_attr( $field( 'email' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="lease_start"><?php esc_html_e( 'Date début bail', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="lease_start" id="lease_start" value="<?php echo esc_attr( $field( 'lease_start' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="lease_end"><?php esc_html_e( 'Date fin bail', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="lease_end" id="lease_end" value="<?php echo esc_attr( $field( 'lease_end' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="rent_amount"><?php esc_html_e( 'Montant du loyer', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="rent_amount" id="rent_amount" value="<?php echo esc_attr( $field( 'rent_amount', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="deposit_paid"><?php esc_html_e( 'Dépôt versé', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="deposit_paid" id="deposit_paid" value="<?php echo esc_attr( $field( 'deposit_paid', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
				<select name="status" id="status">
					<?php foreach ( Limpeed_Tenants::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'actif' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Le statut du bien associé (loué/vacant) sera mis à jour automatiquement.', 'limpeed-immobilier' ); ?></p>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

	<script type="application/json" id="limpeed-tenant-cascade-data">
	<?php echo wp_json_encode( array( 'buildings' => $buildings_json, 'properties' => $properties_json ) ); ?>
	</script>
	<script>
	( function () {
		var dataEl = document.getElementById( 'limpeed-tenant-cascade-data' );
		if ( ! dataEl ) {
			return;
		}
		var data = JSON.parse( dataEl.textContent );

		var ownerSelect    = document.getElementById( 'limpeed_ui_owner_id' );
		var buildingSelect = document.getElementById( 'limpeed_ui_building_id' );
		var propertySelect = document.getElementById( 'property_id' );

		if ( ! ownerSelect || ! buildingSelect || ! propertySelect ) {
			return;
		}

		function clearOptions( select, placeholder ) {
			select.innerHTML = '';
			var opt = document.createElement( 'option' );
			opt.value = '';
			opt.textContent = placeholder;
			select.appendChild( opt );
		}

		function populateBuildings( ownerId, selectedBuildingId ) {
			clearOptions( buildingSelect, '<?php echo esc_js( __( '— Choisir un édifice —', 'limpeed-immobilier' ) ); ?>' );
			buildingSelect.disabled = ! ownerId;
			if ( ! ownerId ) {
				return;
			}
			data.buildings.forEach( function ( building ) {
				if ( String( building.owner_id ) === String( ownerId ) ) {
					var opt = document.createElement( 'option' );
					opt.value = building.id;
					opt.textContent = building.label;
					if ( selectedBuildingId && String( building.id ) === String( selectedBuildingId ) ) {
						opt.selected = true;
					}
					buildingSelect.appendChild( opt );
				}
			} );
		}

		function populateProperties( buildingId, selectedPropertyId ) {
			clearOptions( propertySelect, '<?php echo esc_js( __( '— Choisir un sous-édifice —', 'limpeed-immobilier' ) ); ?>' );
			propertySelect.disabled = ! buildingId;
			if ( ! buildingId ) {
				return;
			}
			data.properties.forEach( function ( property ) {
				if ( String( property.building_id ) === String( buildingId ) ) {
					var opt = document.createElement( 'option' );
					opt.value = property.id;
					opt.textContent = property.label;
					if ( selectedPropertyId && String( property.id ) === String( selectedPropertyId ) ) {
						opt.selected = true;
					}
					propertySelect.appendChild( opt );
				}
			} );
		}

		ownerSelect.addEventListener( 'change', function () {
			populateBuildings( ownerSelect.value, null );
			populateProperties( null, null );
		} );

		buildingSelect.addEventListener( 'change', function () {
			populateProperties( buildingSelect.value, null );
		} );
	} )();
	</script>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$search      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$property_id = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : 0;
	$status      = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page    = 20;

	$args = array(
		'search'      => $search,
		'property_id' => $property_id,
		'status'      => $status,
		'per_page'    => $per_page,
		'paged'       => $paged,
	);

	$total_items       = Limpeed_Tenants::count( $args );
	$tenants           = Limpeed_Tenants::get_all( $args );
	$filter_properties = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="limpeed_view" value="tenants">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un locataire...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<select name="property_id">
					<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_properties as $property_option ) : ?>
						<option value="<?php echo esc_attr( $property_option->id ); ?>" <?php selected( $property_id, $property_option->id ); ?>>
							<?php echo esc_html( $property_option->address ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( Limpeed_Tenants::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Locataire ajouté avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Locataire mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Locataire supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Bien loué', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Début bail', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Fin bail', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $tenants ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Aucun locataire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php
				$statuses = Limpeed_Tenants::get_statuses();
				foreach ( $tenants as $tenant_row ) :
					$edit_url   = Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant_row->id ) );
					$delete_url = wp_nonce_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'delete', 'id' => $tenant_row->id ) ), 'limpeed_delete_tenant_' . $tenant_row->id );
					$property   = Limpeed_Properties::get( $tenant_row->property_id );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $tenant_row->full_name ); ?></a></td>
						<td><?php echo $property ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $property->id ) ) ) . '">' . esc_html( $property->address ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo $tenant_row->phone ? esc_html( $tenant_row->phone ) : '&mdash;'; ?></td>
						<td><?php echo $tenant_row->lease_start ? esc_html( mysql2date( get_option( 'date_format' ), $tenant_row->lease_start ) ) : '&mdash;'; ?></td>
						<td><?php echo $tenant_row->lease_end ? esc_html( mysql2date( get_option( 'date_format' ), $tenant_row->lease_end ) ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $tenant_row->rent_amount, 2 ) ); ?></td>
						<td><span class="limpeed-app-badge"><?php echo isset( $statuses[ $tenant_row->status ] ) ? esc_html( $statuses[ $tenant_row->status ] ) : esc_html( $tenant_row->status ); ?></span></td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce locataire ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search, 'property_id' => $property_id, 'status' => $status ) ); ?>
	</div>
<?php endif; ?>
