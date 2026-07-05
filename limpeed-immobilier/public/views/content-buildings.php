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
			<div class="limpeed-form-row">
				<label for="commission_rate"><?php esc_html_e( 'Taux de commission (%)', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" max="100" name="commission_rate" id="commission_rate" value="<?php echo esc_attr( $field( 'commission_rate', 0 ) ); ?>">
				<p class="limpeed-app-description"><?php esc_html_e( 'Pourcentage prélevé par l\'agence sur les loyers encaissés pour cet édifice. Utilisé pour calculer automatiquement la commission de chaque paiement enregistré.', 'limpeed-immobilier' ); ?></p>
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
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sub_unit_field( $row, 'type', 'studio' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
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
</div>
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
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'studio', $key ); ?>><?php echo esc_html( $label ); ?></option>
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
	// Liste dynamique (Alpine.js) : recherche/filtre en direct, modale
	// d'ajout/modification, actions Ajax, panneau de détail. Toutes les
	// données transitent par l'API REST limpeed/v1 (voir
	// includes/class-limpeed-rest-api.php) ; ce fichier ne fait que fournir
	// le balisage et la configuration initiale (propriétaires pour la
	// cascade/le filtre, statuts de biens, textes traduits, URL + nonce REST).
	// -----------------------------------------------------------------
	$filter_owners = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );

	$owner_options = array_map(
		function ( $owner ) {
			return array(
				'id'    => (int) $owner->id,
				'label' => $owner->full_name,
			);
		},
		$filter_owners
	);

	$app_config = array(
		'ownerOptions'     => $owner_options,
		'propertyStatuses' => Limpeed_Properties::get_statuses(),
		'i18n'             => array(
			'created'       => __( 'Édifice ajouté avec succès.', 'limpeed-immobilier' ),
			'updated'       => __( 'Édifice mis à jour avec succès.', 'limpeed-immobilier' ),
			'deleted'       => __( 'Édifice supprimé avec succès.', 'limpeed-immobilier' ),
			'confirmDelete' => __( 'Confirmez-vous la suppression de cet édifice ?', 'limpeed-immobilier' ),
		),
	);

	$rest_config = array(
		'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);

	// Cartes de synthèse.
	$buildings_total          = Limpeed_Buildings::count();
	$properties_total         = Limpeed_Properties::count();
	$average_commission_rate  = Limpeed_Buildings::get_average_commission_rate();
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $buildings_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Édifices', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-admin-multisite"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $properties_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Biens rattachés', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-building"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-green"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $average_commission_rate, 1 ) ); ?>%</div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Commission moyenne', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-chart-bar"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
		</div>
	</div>

	<div class="limpeed-app-panel" x-data="limpeedBuildingsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
		<div class="limpeed-app-toolbar">
			<div class="limpeed-app-search">
				<input type="text" x-model="search" @input="onSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher un édifice...', 'limpeed-immobilier' ); ?>">
				<select x-model="filterOwnerId" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
					<template x-for="owner in ownerOptions" :key="owner.id">
						<option :value="owner.id" x-text="owner.label"></option>
					</template>
				</select>
			</div>
			<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></button>
		</div>

		<template x-if="loading">
			<div class="limpeed-entity-grid">
				<template x-for="n in 6" :key="n">
					<div class="limpeed-entity-card-skeleton"></div>
				</template>
			</div>
		</template>
		<p x-show="!loading && items.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun édifice pour le moment.', 'limpeed-immobilier' ); ?></p>
		<template x-for="group in groups" :key="group.ownerLabel">
			<div class="limpeed-owner-group" x-show="!loading">
				<h3 class="limpeed-owner-group-title"><span class="dashicons dashicons-groups"></span> <span x-text="group.ownerLabel"></span></h3>
				<div class="limpeed-entity-grid">
					<template x-for="row in group.items" :key="row.id">
						<div class="limpeed-entity-card limpeed-entity-card--blue" @click="openDrawer(row)">
							<div class="limpeed-entity-card-header">
								<span class="limpeed-entity-card-avatar limpeed-icon-blue"><span class="dashicons dashicons-building"></span></span>
								<div class="limpeed-entity-card-header-text">
									<div class="limpeed-entity-card-title" x-text="row.name"></div>
									<div class="limpeed-entity-card-subtitle" x-text="row.owner_label || '—'"></div>
								</div>
								<span class="limpeed-app-badge" x-text="row.commission_rate_label"></span>
							</div>
							<div class="limpeed-entity-card-meta">
								<div class="limpeed-entity-card-meta-row">
									<span class="dashicons dashicons-location"></span>
									<span x-text="row.address || '—'"></span>
								</div>
								<div class="limpeed-entity-card-meta-row">
									<span class="dashicons dashicons-building"></span>
									<span x-text="row.properties_count + ' sous-édifices'"></span>
								</div>
							</div>
							<div class="limpeed-entity-card-footer">
								<button type="button" class="limpeed-app-link-btn" @click.stop="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
								<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deleteBuilding(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
							</div>
						</div>
					</template>
				</div>
			</div>
		</template>

		<div class="limpeed-app-pagination" x-show="totalPages > 1" x-cloak>
			<template x-for="p in totalPages" :key="p">
				<a href="#" @click.prevent="goToPage(p)" :class="{ 'is-active': p === paged }" x-text="p"></a>
			</template>
		</div>

		<!-- Modale ajout / modification -->
		<div class="limpeed-app-modal-overlay" x-show="modal.open" x-cloak @keydown.escape.window="closeModal()">
			<div class="limpeed-app-modal" @click.outside="closeModal()" x-show="modal.open" x-transition>
				<div class="limpeed-app-modal-header">
					<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier l\'édifice', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter un édifice', 'limpeed-immobilier' ) ); ?>'"></h2>
					<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<form class="limpeed-app-form" @submit.prevent="saveBuilding()">
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
							<select x-model="modal.data.owner_id">
								<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
								<template x-for="owner in ownerOptions" :key="owner.id">
									<option :value="owner.id" x-text="owner.label"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Nom de l\'édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<input type="text" x-model="modal.data.name" required placeholder="<?php esc_attr_e( 'Ex : Résidence Les Palmiers', 'limpeed-immobilier' ); ?>">
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label>
							<textarea x-model="modal.data.address" rows="2"></textarea>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?></label>
							<textarea x-model="modal.data.description" rows="2"></textarea>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Taux de commission (%)', 'limpeed-immobilier' ); ?></label>
							<input type="number" step="0.01" min="0" max="100" x-model="modal.data.commission_rate">
							<p class="limpeed-app-form-hint"><?php esc_html_e( 'Pourcentage prélevé par l\'agence sur les loyers encaissés pour cet édifice.', 'limpeed-immobilier' ); ?></p>
						</div>
						<p class="limpeed-app-form-hint"><?php esc_html_e( 'Pour ajouter plusieurs sous-édifices en une fois, utilisez la fiche complète depuis le panneau de détail après création.', 'limpeed-immobilier' ); ?></p>
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
							<h2 x-text="drawer.building ? drawer.building.name : ''"></h2>
							<div class="limpeed-app-drawer-subtitle" x-text="drawer.building ? drawer.building.owner_label : ''"></div>
						</div>
						<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
					</div>
					<div class="limpeed-app-drawer-tabs">
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'biens' }" @click="switchDrawerTab('biens')"><?php esc_html_e( 'Biens', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique', 'limpeed-immobilier' ); ?></button>
					</div>
					<div class="limpeed-app-drawer-body">
						<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

						<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.building">
							<div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.building.owner_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.building.address || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.building.description || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Taux de commission', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.building.commission_rate_label"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Sous-édifices', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.building.properties_count"></div>
								</div>
								<p><a :href="'<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => '' ) ) ); ?>' + drawer.building.id"><?php esc_html_e( 'Voir la fiche complète (ajout de sous-édifices en masse)', 'limpeed-immobilier' ); ?> &rarr;</a></p>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'biens'">
							<div>
								<p x-show="drawer.properties.length === 0"><?php esc_html_e( 'Aucun sous-édifice pour le moment.', 'limpeed-immobilier' ); ?></p>
								<template x-for="property in drawer.properties" :key="property.id">
									<div class="limpeed-app-drawer-list-item">
										<span x-text="property.label"></span>
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + property.status" x-text="propertyStatuses[property.status] || property.status"></span>
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

	<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des édifices.', 'limpeed-immobilier' ); ?></p></noscript>

	<script>
	window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
	</script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-grouping.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<?php /* buildings-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/buildings-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php endif; ?>
