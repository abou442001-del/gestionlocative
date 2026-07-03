<?php
/**
 * Contenu frontend de la section "Biens" (liste + formulaire).
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
	$property = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$property = Limpeed_Properties::get( (int) $_GET['id'] );
		if ( ! $property ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Bien introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit              = ! empty( $property );
	$errors               = Limpeed_Frontend_Properties::$errors;
	$posted               = Limpeed_Frontend_Properties::$posted;
	$buildings            = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
	$preselected_building = isset( $_GET['building_id'] ) ? (int) $_GET['building_id'] : 0;
	$current_tenant       = $is_edit ? Limpeed_Properties::get_current_tenant( $property->id ) : null;
	$average_rent_by_type = Limpeed_Properties::get_average_rent_by_type();

	$field = function ( $name, $default = '' ) use ( $property, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $property->$name ) ) {
			return $property->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le bien (sous-édifice)', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un bien (sous-édifice)', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$building = Limpeed_Buildings::get( $property->building_id );
				$owner    = Limpeed_Owners::get( $property->owner_id );
				if ( $building ) {
					printf(
						/* translators: %s: lien vers l'édifice */
						esc_html__( 'Édifice : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $building->id ) ) ) . '">' . esc_html( $building->name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php
				if ( $owner ) {
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php if ( $current_tenant ) : ?>
					<?php
					printf(
						/* translators: %s: lien vers le locataire actuel */
						esc_html__( 'Locataire actuel : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $current_tenant->id ) ) ) . '">' . esc_html( $current_tenant->full_name ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Aucun locataire actuel', 'limpeed-immobilier' ); ?>
				<?php endif; ?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', array( 'property_id' => $property->id ) ) ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', $is_edit ? array( 'action' => 'edit', 'id' => $property->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_property', 'limpeed_property_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<?php if ( empty( $buildings ) ) : ?>
					<p class="limpeed-app-description">
						<?php
						printf(
							/* translators: %s: lien vers l'ajout d'un édifice */
							esc_html__( 'Aucun édifice enregistré. %s avant d\'ajouter un sous-édifice.', 'limpeed-immobilier' ),
							'<a href="' . esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add' ) ) ) . '">' . esc_html__( 'Créez-en un', 'limpeed-immobilier' ) . '</a>'
						);
						?>
					</p>
				<?php else : ?>
					<select name="building_id" id="building_id" required>
						<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
						<?php foreach ( $buildings as $building_option ) : ?>
							<?php
							$owner_option = Limpeed_Owners::get( $building_option->owner_id );
							$label        = $owner_option
								? sprintf( '%s — %s', $building_option->name, $owner_option->full_name )
								: $building_option->name;
							?>
							<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( (int) $field( 'building_id', $preselected_building ), $building_option->id ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="limpeed-app-description"><?php esc_html_e( 'Le propriétaire est déterminé automatiquement à partir de l\'édifice sélectionné.', 'limpeed-immobilier' ); ?></p>
				<?php endif; ?>
			</div>
			<div class="limpeed-form-row">
				<label for="reference"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="reference" id="reference" value="<?php echo esc_attr( $field( 'reference' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : A1, RDC Gauche...', 'limpeed-immobilier' ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="address"><?php esc_html_e( 'Adresse / repère', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<textarea name="address" id="address" rows="3" required><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>
			<div class="limpeed-form-row">
				<label for="type"><?php esc_html_e( 'Type de bien', 'limpeed-immobilier' ); ?></label>
				<?php
				$current_type = $field( 'type', 'studio' );
				$legacy_types = Limpeed_Properties::get_legacy_types();
				?>
				<select name="type" id="type">
					<?php if ( isset( $legacy_types[ $current_type ] ) ) : ?>
						<option value="<?php echo esc_attr( $current_type ); ?>" selected='selected'>
							<?php echo esc_html( $legacy_types[ $current_type ] ); ?> (<?php esc_html_e( 'ancien type', 'limpeed-immobilier' ); ?>)
						</option>
					<?php endif; ?>
					<?php foreach ( Limpeed_Properties::get_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_type, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="monthly_rent"><?php esc_html_e( 'Loyer mensuel', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="monthly_rent" id="monthly_rent" value="<?php echo esc_attr( $field( 'monthly_rent', 0 ) ); ?>">
				<p class="limpeed-app-description" id="limpeed-rent-hint"></p>
			</div>
			<div class="limpeed-form-row">
				<label for="charges"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="charges" id="charges" value="<?php echo esc_attr( $field( 'charges', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="deposit_amount"><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="deposit_amount" id="deposit_amount" value="<?php echo esc_attr( $field( 'deposit_amount', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
				<select name="status" id="status">
					<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'vacant' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Le statut "Loué"/"Vacant" est mis à jour automatiquement selon la présence d\'un locataire actif.', 'limpeed-immobilier' ); ?></p>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

	<script type="application/json" id="limpeed-average-rent-data">
	<?php echo wp_json_encode( $average_rent_by_type ); ?>
	</script>
	<script>
	( function () {
		var dataEl = document.getElementById( 'limpeed-average-rent-data' );
		var typeSelect = document.getElementById( 'type' );
		var hintEl = document.getElementById( 'limpeed-rent-hint' );
		if ( ! dataEl || ! typeSelect || ! hintEl ) {
			return;
		}
		var averages = JSON.parse( dataEl.textContent );

		function updateHint() {
			var avg = averages[ typeSelect.value ];
			if ( avg ) {
				hintEl.textContent = '<?php echo esc_js( __( 'Loyer moyen constaté pour ce type de bien : ', 'limpeed-immobilier' ) ); ?>' + Math.round( avg ).toLocaleString();
			} else {
				hintEl.textContent = '';
			}
		}

		typeSelect.addEventListener( 'change', updateHint );
		updateHint();
	} )();
	</script>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste dynamique (Alpine.js) : recherche/filtres en direct, modale
	// d'ajout/modification avec cascade Propriétaire → Édifice peuplée en
	// Ajax, actions Ajax, panneau de détail. Toutes les données transitent
	// par l'API REST limpeed/v1 (voir includes/class-limpeed-rest-api.php) ;
	// ce fichier ne fait que fournir le balisage et la configuration initiale.
	// -----------------------------------------------------------------
	$filter_owners    = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$filter_buildings = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );

	$owner_options = array_map(
		function ( $owner ) {
			return array(
				'id'    => (int) $owner->id,
				'label' => $owner->full_name,
			);
		},
		$filter_owners
	);
	$building_options = array_map(
		function ( $building ) {
			return array(
				'id'       => (int) $building->id,
				'label'    => $building->name,
				'owner_id' => (int) $building->owner_id,
			);
		},
		$filter_buildings
	);

	$app_config = array(
		'ownerOptions'      => $owner_options,
		'buildingOptions'   => $building_options,
		'propertyTypes'     => Limpeed_Properties::get_types(),
		'legacyTypes'       => Limpeed_Properties::get_legacy_types(),
		'propertyStatuses'  => Limpeed_Properties::get_statuses(),
		'averageRentByType' => Limpeed_Properties::get_average_rent_by_type(),
		'i18n'              => array(
			'created'       => __( 'Bien ajouté avec succès.', 'limpeed-immobilier' ),
			'updated'       => __( 'Bien mis à jour avec succès.', 'limpeed-immobilier' ),
			'deleted'       => __( 'Bien supprimé avec succès.', 'limpeed-immobilier' ),
			'confirmDelete' => __( 'Confirmez-vous la suppression de ce bien ?', 'limpeed-immobilier' ),
			'averageRent'   => __( 'Loyer moyen constaté pour ce type de bien : ', 'limpeed-immobilier' ),
			'legacyType'    => __( 'ancien type', 'limpeed-immobilier' ),
		),
	);

	$rest_config = array(
		'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);
	?>

	<div class="limpeed-app-panel" x-data="limpeedPropertiesApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
		<div class="limpeed-app-toolbar">
			<div class="limpeed-app-search">
				<input type="text" x-model="search" @input="onSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher un bien...', 'limpeed-immobilier' ); ?>">
				<select x-model="filterOwnerId" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
					<template x-for="owner in ownerOptions" :key="owner.id">
						<option :value="owner.id" x-text="owner.label"></option>
					</template>
				</select>
				<select x-model="filterBuildingId" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les édifices', 'limpeed-immobilier' ); ?></option>
					<template x-for="building in buildingOptions" :key="building.id">
						<option :value="building.id" x-text="building.label"></option>
					</template>
				</select>
				<select x-model="filterStatus" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<template x-for="[key, label] in Object.entries(propertyStatuses)" :key="key">
						<option :value="key" x-text="label"></option>
					</template>
				</select>
			</div>
			<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter un bien', 'limpeed-immobilier' ); ?></button>
		</div>

		<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Adresse (sous-édifice)', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Locataire actuel', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<template x-if="loading">
					<template x-for="n in 5" :key="n">
						<tr class="limpeed-app-skeleton-row">
							<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:70%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:40%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
							<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
						</tr>
					</template>
				</template>
				<tr x-show="!loading && items.length === 0">
					<td colspan="8" class="limpeed-app-empty-state"><?php esc_html_e( 'Aucun bien pour le moment.', 'limpeed-immobilier' ); ?></td>
				</tr>
				<template x-for="row in items" :key="row.id">
					<tr>
						<td><button type="button" class="limpeed-app-link-btn" @click="openDrawer(row)" x-text="row.reference || '—'"></button></td>
						<td x-text="row.address || '—'"></td>
						<td x-text="row.building_label || '—'"></td>
						<td x-text="row.type_label"></td>
						<td x-text="row.rent_formatted"></td>
						<td><span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.status" x-text="row.status_label"></span></td>
						<td x-text="row.tenant_label || '—'"></td>
						<td class="limpeed-app-actions">
							<button type="button" class="limpeed-app-link-btn" @click="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
							<button type="button" class="limpeed-app-link-btn is-danger" @click="deleteProperty(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
						</td>
					</tr>
				</template>
			</tbody>
		</table>
</div>

		<div class="limpeed-app-pagination" x-show="totalPages > 1" x-cloak>
			<template x-for="p in totalPages" :key="p">
				<a href="#" @click.prevent="goToPage(p)" :class="{ 'is-active': p === paged }" x-text="p"></a>
			</template>
		</div>

		<!-- Modale ajout / modification -->
		<div class="limpeed-app-modal-overlay" x-show="modal.open" x-cloak @keydown.escape.window="closeModal()">
			<div class="limpeed-app-modal" @click.outside="closeModal()" x-show="modal.open" x-transition>
				<div class="limpeed-app-modal-header">
					<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier le bien', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter un bien', 'limpeed-immobilier' ) ); ?>'"></h2>
					<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<form class="limpeed-app-form" @submit.prevent="saveProperty()">
					<div class="limpeed-app-modal-body">
						<div class="limpeed-app-notice limpeed-app-notice-error" x-show="modal.errors.length">
							<ul>
								<template x-for="(error, index) in modal.errors" :key="index">
									<li x-text="error"></li>
								</template>
							</ul>
						</div>

						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.owner_id" @change="onModalOwnerChange()" :disabled="modal.loadingCascade">
								<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
								<template x-for="owner in ownerOptions" :key="owner.id">
									<option :value="owner.id" x-text="owner.label"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.building_id" :disabled="! modal.data.owner_id || modal.loadingCascade">
								<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
								<template x-for="building in modal.buildings" :key="building.id">
									<option :value="building.id" x-text="building.label"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-app-modal-grid">
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="modal.data.reference" placeholder="<?php esc_attr_e( 'Ex : A1, RDC Gauche...', 'limpeed-immobilier' ); ?>">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Type de bien', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.type">
									<template x-for="option in typeSelectOptions()" :key="option.value">
										<option :value="option.value" x-text="option.label"></option>
									</template>
								</select>
								<p class="limpeed-app-form-hint" x-text="averageRentHint()"></p>
							</div>
							<div class="limpeed-form-row is-full">
								<label><?php esc_html_e( 'Adresse / repère', 'limpeed-immobilier' ); ?></label>
								<textarea x-model="modal.data.address" rows="2"></textarea>
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Loyer mensuel', 'limpeed-immobilier' ); ?></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.monthly_rent">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.charges">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.deposit_amount">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.status">
									<template x-for="[key, label] in Object.entries(propertyStatuses)" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
						</div>
						<p class="limpeed-app-form-hint"><?php esc_html_e( 'Le statut "Loué"/"Vacant" est mis à jour automatiquement selon la présence d\'un locataire actif.', 'limpeed-immobilier' ); ?></p>
					</div>
					<div class="limpeed-app-modal-footer">
						<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="closeModal()"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></button>
						<button type="submit" class="limpeed-app-btn" :disabled="modal.saving">
							<span x-text="modal.saving ? '<?php echo esc_js( __( 'Enregistrement...', 'limpeed-immobilier' ) ); ?>' : (modal.mode === 'edit' ? '<?php echo esc_js( __( 'Mettre à jour', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter', 'limpeed-immobilier' ) ); ?>')"></span>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Panneau de détail (drawer) -->
		<template x-if="drawer.open">
			<div>
				<div class="limpeed-app-drawer-overlay" @click="closeDrawer()"></div>
				<div class="limpeed-app-drawer" @keydown.escape.window="closeDrawer()">
					<div class="limpeed-app-drawer-header">
						<div>
							<h2 x-text="drawer.property ? (drawer.property.reference || drawer.property.address) : ''"></h2>
							<div class="limpeed-app-drawer-subtitle" x-text="drawer.property ? drawer.property.building_label : ''"></div>
						</div>
						<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
					</div>
					<div class="limpeed-app-drawer-tabs">
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'paiements' }" @click="switchDrawerTab('paiements')"><?php esc_html_e( 'Paiements', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique', 'limpeed-immobilier' ); ?></button>
					</div>
					<div class="limpeed-app-drawer-body">
						<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

						<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.property">
							<div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.owner_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.building_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.type_label"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.rent_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.charges_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.deposit_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value">
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + drawer.property.status" x-text="drawer.property.status_label"></span>
									</div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Locataire actuel', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.property.tenant_label || '—'"></div>
								</div>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'paiements'">
							<div>
								<p x-show="drawer.payments.length === 0"><?php esc_html_e( 'Aucun paiement enregistré.', 'limpeed-immobilier' ); ?></p>
								<template x-for="payment in drawer.payments" :key="payment.id">
									<div class="limpeed-app-drawer-list-item">
										<span x-text="payment.period"></span>
										<span x-text="payment.amount_formatted"></span>
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + payment.status" x-text="payment.status_label"></span>
									</div>
								</template>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'historique'">
							<div>
								<p x-show="drawer.history.length === 0"><?php esc_html_e( 'Aucun historique disponible.', 'limpeed-immobilier' ); ?></p>
								<template x-for="entry in drawer.history" :key="entry.id">
									<div class="limpeed-app-drawer-list-item">
										<span x-text="entry.action_label + ' — ' + entry.agent_name"></span>
										<span x-text="entry.created_at"></span>
									</div>
								</template>
							</div>
						</template>
					</div>
				</div>
			</div>
		</template>

		<!-- Notifications toast -->
		<div class="limpeed-app-toast-container">
			<template x-for="t in toasts" :key="t.id">
				<div class="limpeed-app-toast" :class="'limpeed-app-toast-' + t.type" x-text="t.message"></div>
			</template>
		</div>
	</div>

	<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des biens.', 'limpeed-immobilier' ); ?></p></noscript>

	<script>
	window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
	</script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<?php /* properties-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/properties-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php endif; ?>
