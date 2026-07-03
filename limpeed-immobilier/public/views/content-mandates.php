<?php
/**
 * Contenu frontend de la section "Mandats" (liste dynamique uniquement,
 * pas de formulaire classique en repli : le mandat est une entité simple qui
 * ne justifie pas de doubler la modale REST/Alpine avec un formulaire
 * plein écran comme pour Édifices/Biens/Locataires/Paiements).
 *
 * Toutes les données transitent par l'API REST limpeed/v1 (voir
 * includes/class-limpeed-rest-api.php) ; ce fichier ne fait que fournir le
 * balisage et la configuration initiale (édifices pour la cascade/le filtre,
 * statuts, textes traduits, URL + nonce REST).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_buildings = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );

$building_options = array_map(
	function ( $building ) {
		return array(
			'id'    => (int) $building->id,
			'label' => $building->name,
		);
	},
	$filter_buildings
);

$app_config = array(
	'buildingOptions' => $building_options,
	'statuses'        => Limpeed_Mandates::get_statuses(),
	'i18n'            => array(
		'created'       => __( 'Mandat ajouté avec succès.', 'limpeed-immobilier' ),
		'updated'       => __( 'Mandat mis à jour avec succès.', 'limpeed-immobilier' ),
		'deleted'       => __( 'Mandat supprimé avec succès.', 'limpeed-immobilier' ),
		'confirmDelete' => __( 'Confirmez-vous la suppression de ce mandat ?', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>

<div class="limpeed-app-panel" x-data="limpeedMandatesApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<div class="limpeed-app-toolbar">
		<div class="limpeed-app-search">
			<input type="text" x-model="search" @input="onSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher un édifice...', 'limpeed-immobilier' ); ?>">
			<select x-model="filterBuildingId" @change="onFilterChange()">
				<option value=""><?php esc_html_e( 'Tous les édifices', 'limpeed-immobilier' ); ?></option>
				<template x-for="building in buildingOptions" :key="building.id">
					<option :value="building.id" x-text="building.label"></option>
				</template>
			</select>
			<select x-model="filterStatus" @change="onFilterChange()">
				<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
				<template x-for="(label, key) in statuses" :key="key">
					<option :value="key" x-text="label"></option>
				</template>
			</select>
		</div>
		<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter un mandat', 'limpeed-immobilier' ); ?></button>
	</div>

	<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Début', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Fin', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Commission', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<template x-if="loading">
				<template x-for="n in 5" :key="n">
					<tr class="limpeed-app-skeleton-row">
						<td><div class="limpeed-app-skeleton-bar" style="width:70%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:30%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:40%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
					</tr>
				</template>
			</template>
			<tr x-show="!loading && items.length === 0">
				<td colspan="7" class="limpeed-app-empty-state"><?php esc_html_e( 'Aucun mandat pour le moment.', 'limpeed-immobilier' ); ?></td>
			</tr>
			<template x-for="row in items" :key="row.id">
				<tr>
					<td><button type="button" class="limpeed-app-link-btn" @click="openDrawer(row)" x-text="row.building_label"></button></td>
					<td x-text="row.owner_label || '—'"></td>
					<td x-text="row.start_date"></td>
					<td x-text="row.end_date || '—'"></td>
					<td x-text="row.commission_rate_label"></td>
					<td><span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.status" x-text="row.status_label"></span></td>
					<td class="limpeed-app-actions">
						<button type="button" class="limpeed-app-link-btn" @click="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-link-btn is-danger" @click="deleteMandate(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
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
				<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier le mandat', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter un mandat', 'limpeed-immobilier' ) ); ?>'"></h2>
				<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
			</div>
			<form class="limpeed-app-form" @submit.prevent="saveMandate()">
				<div class="limpeed-app-modal-body">
					<div class="limpeed-app-notice limpeed-app-notice-error" x-show="modal.errors.length">
						<ul>
							<template x-for="(error, index) in modal.errors" :key="index">
								<li x-text="error"></li>
							</template>
						</ul>
					</div>

					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
						<select x-model="modal.data.building_id">
							<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
							<template x-for="building in buildingOptions" :key="building.id">
								<option :value="building.id" x-text="building.label"></option>
							</template>
						</select>
					</div>
					<div class="limpeed-app-modal-grid">
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Date de début', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<input type="date" x-model="modal.data.start_date" required>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Date de fin', 'limpeed-immobilier' ); ?></label>
							<input type="date" x-model="modal.data.end_date">
							<p class="limpeed-app-form-hint"><?php esc_html_e( 'Laisser vide pour une durée indéterminée.', 'limpeed-immobilier' ); ?></p>
						</div>
					</div>
					<div class="limpeed-app-modal-grid">
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Taux de commission (%)', 'limpeed-immobilier' ); ?></label>
							<input type="number" step="0.01" min="0" max="100" x-model="modal.data.commission_rate">
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
							<select x-model="modal.data.status">
								<template x-for="(label, key) in statuses" :key="key">
									<option :value="key" x-text="label"></option>
								</template>
							</select>
						</div>
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Date de signature', 'limpeed-immobilier' ); ?></label>
						<input type="date" x-model="modal.data.signed_date">
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Notes', 'limpeed-immobilier' ); ?></label>
						<textarea x-model="modal.data.notes" rows="2"></textarea>
					</div>
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
						<h2 x-text="drawer.mandate ? drawer.mandate.building_label : ''"></h2>
						<div class="limpeed-app-drawer-subtitle" x-text="drawer.mandate ? drawer.mandate.owner_label : ''"></div>
					</div>
					<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<div class="limpeed-app-drawer-tabs">
					<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
					<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique', 'limpeed-immobilier' ); ?></button>
				</div>
				<div class="limpeed-app-drawer-body">
					<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

					<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.mandate">
						<div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.owner_label || '—'"></div>
							</div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Période', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.start_date + ' — ' + (drawer.mandate.end_date || '<?php echo esc_js( __( 'durée indéterminée', 'limpeed-immobilier' ) ); ?>')"></div>
							</div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Taux de commission', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.commission_rate_label"></div>
							</div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.display_status_label"></div>
							</div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Date de signature', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.signed_date || '—'"></div>
							</div>
							<div class="limpeed-app-drawer-field" x-show="drawer.mandate.notes">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Notes', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.mandate.notes"></div>
							</div>
							<p><a :href="drawer.mandate.contract_url" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Télécharger le mandat (PDF)', 'limpeed-immobilier' ); ?></a></p>
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

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des mandats.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* mandates-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/mandates-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
