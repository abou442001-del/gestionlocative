<?php
/**
 * Contenu frontend de la section "États des lieux" (liste dynamique
 * uniquement, comme Mandats : pas de formulaire plein écran de repli).
 *
 * Toutes les données transitent par l'API REST limpeed/v1 (voir
 * includes/class-limpeed-rest-api.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$app_config = array(
	'types'          => Limpeed_Inspections::get_types(),
	'roomConditions' => Limpeed_Inspections::get_room_conditions(),
	'presetTenantId' => isset( $_GET['tenant_id'] ) ? (int) $_GET['tenant_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'i18n'           => array(
		'created'       => __( 'État des lieux ajouté avec succès.', 'limpeed-immobilier' ),
		'updated'       => __( 'État des lieux mis à jour avec succès.', 'limpeed-immobilier' ),
		'deleted'       => __( 'État des lieux supprimé avec succès.', 'limpeed-immobilier' ),
		'confirmDelete' => __( 'Confirmez-vous la suppression de cet état des lieux ?', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>

<div class="limpeed-app-panel" x-data="limpeedInspectionsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<div class="limpeed-app-toolbar">
		<div class="limpeed-app-search">
			<select x-model="filterType" @change="onFilterChange()">
				<option value=""><?php esc_html_e( 'Tous les types', 'limpeed-immobilier' ); ?></option>
				<template x-for="(label, key) in types" :key="key">
					<option :value="key" x-text="label"></option>
				</template>
			</select>
		</div>
		<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter un état des lieux', 'limpeed-immobilier' ); ?></button>
	</div>

	<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Bien', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Pièces', 'limpeed-immobilier' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<template x-if="loading">
				<template x-for="n in 5" :key="n">
					<tr class="limpeed-app-skeleton-row">
						<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:40%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:40%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:20%"></div></td>
						<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
					</tr>
				</template>
			</template>
			<tr x-show="!loading && items.length === 0">
				<td colspan="6" class="limpeed-app-empty-state"><?php esc_html_e( 'Aucun état des lieux pour le moment.', 'limpeed-immobilier' ); ?></td>
			</tr>
			<template x-for="row in items" :key="row.id">
				<tr>
					<td><button type="button" class="limpeed-app-link-btn" @click="openDrawer(row)" x-text="row.tenant_label || '—'"></button></td>
					<td x-text="row.property_label || '—'"></td>
					<td><span class="limpeed-app-badge" :class="row.type === 'sortie' ? 'limpeed-app-badge-en_retard' : 'limpeed-app-badge-actif'" x-text="row.type_label"></span></td>
					<td x-text="row.inspection_date"></td>
					<td x-text="row.rooms_count"></td>
					<td class="limpeed-app-actions">
						<button type="button" class="limpeed-app-link-btn" @click="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-link-btn is-danger" @click="deleteInspection(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
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
				<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier l\'état des lieux', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter un état des lieux', 'limpeed-immobilier' ) ); ?>'"></h2>
				<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
			</div>
			<form class="limpeed-app-form" @submit.prevent="saveInspection()">
				<div class="limpeed-app-modal-body">
					<div class="limpeed-app-notice limpeed-app-notice-error" x-show="modal.errors.length">
						<ul>
							<template x-for="(error, index) in modal.errors" :key="index">
								<li x-text="error"></li>
							</template>
						</ul>
					</div>

					<div class="limpeed-app-modal-grid">
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.tenant_id" @change="onTenantChange()" :disabled="modal.loadingTenants">
								<option value=""><?php esc_html_e( '— Choisir un locataire —', 'limpeed-immobilier' ); ?></option>
								<template x-for="tenant in modal.tenants" :key="tenant.id">
									<option :value="tenant.id" x-text="tenant.full_name + (tenant.property_label ? ' — ' + tenant.property_label : '')"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></label>
							<select x-model="modal.data.type">
								<template x-for="(label, key) in types" :key="key">
									<option :value="key" x-text="label"></option>
								</template>
							</select>
						</div>
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Date de l\'état des lieux', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
						<input type="date" x-model="modal.data.inspection_date" required>
					</div>

					<h3><?php esc_html_e( 'Détail par pièce', 'limpeed-immobilier' ); ?></h3>
					<template x-for="(room, index) in modal.data.rooms" :key="index">
						<div class="limpeed-app-modal-grid" style="align-items: end; margin-bottom: 8px;">
							<div class="limpeed-form-row" style="margin-bottom: 8px;">
								<label><?php esc_html_e( 'Pièce', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="room.name" placeholder="<?php esc_attr_e( 'Ex : Salon', 'limpeed-immobilier' ); ?>">
							</div>
							<div class="limpeed-form-row" style="margin-bottom: 8px;">
								<label><?php esc_html_e( 'État', 'limpeed-immobilier' ); ?></label>
								<select x-model="room.condition">
									<template x-for="(label, key) in roomConditions" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
							<div class="limpeed-form-row is-full" style="margin-bottom: 8px;">
								<label><?php esc_html_e( 'Notes', 'limpeed-immobilier' ); ?></label>
								<div style="display:flex; gap:8px;">
									<input type="text" x-model="room.notes" style="flex:1;" placeholder="<?php esc_attr_e( 'Ex : rayure au mur', 'limpeed-immobilier' ); ?>">
									<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="removeRoom(index)"><?php esc_html_e( 'Retirer', 'limpeed-immobilier' ); ?></button>
								</div>
							</div>
						</div>
					</template>
					<p><button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="addRoom()"><?php esc_html_e( '+ Ajouter une pièce', 'limpeed-immobilier' ); ?></button></p>

					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Observations générales', 'limpeed-immobilier' ); ?></label>
						<textarea x-model="modal.data.general_notes" rows="2"></textarea>
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
						<h2 x-text="drawer.inspection ? drawer.inspection.tenant_label : ''"></h2>
						<div class="limpeed-app-drawer-subtitle" x-text="drawer.inspection ? (drawer.inspection.type_label + ' — ' + drawer.inspection.inspection_date) : ''"></div>
					</div>
					<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<div class="limpeed-app-drawer-tabs">
					<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
					<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'comparaison' }" @click="switchDrawerTab('comparaison')"><?php esc_html_e( 'Comparaison', 'limpeed-immobilier' ); ?></button>
					<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique', 'limpeed-immobilier' ); ?></button>
				</div>
				<div class="limpeed-app-drawer-body">
					<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

					<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.inspection">
						<div>
							<div class="limpeed-app-drawer-field">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Bien', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.inspection.property_label || '—'"></div>
							</div>
							<div class="limpeed-app-drawer-field" x-show="drawer.inspection.rooms.length">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Détail par pièce', 'limpeed-immobilier' ); ?></span>
								<template x-for="room in drawer.inspection.rooms" :key="room.name">
									<div class="limpeed-app-drawer-list-item">
										<span x-text="room.name"></span>
										<span x-text="roomConditions[room.condition] || room.condition"></span>
									</div>
								</template>
							</div>
							<div class="limpeed-app-drawer-field" x-show="drawer.inspection.general_notes">
								<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Observations', 'limpeed-immobilier' ); ?></span>
								<div class="limpeed-app-drawer-field-value" x-text="drawer.inspection.general_notes"></div>
							</div>
							<p><a :href="drawer.inspection.pdf_url" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Télécharger le PDF', 'limpeed-immobilier' ); ?></a></p>
						</div>
					</template>

					<template x-if="! drawer.loading && drawer.tab === 'comparaison'">
						<div>
							<p x-show="drawer.loadingComparison"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>
							<template x-if="! drawer.loadingComparison && drawer.comparison && ! drawer.comparison.available">
								<p><?php esc_html_e( 'Comparaison disponible uniquement lorsqu\'un état des lieux d\'entrée ET de sortie existent pour ce locataire.', 'limpeed-immobilier' ); ?></p>
							</template>
							<template x-if="! drawer.loadingComparison && drawer.comparison && drawer.comparison.available">
								<div>
									<p class="limpeed-app-form-hint">
										<?php esc_html_e( 'Entrée', 'limpeed-immobilier' ); ?>: <span x-text="drawer.comparison.entree_date"></span> — <?php esc_html_e( 'Sortie', 'limpeed-immobilier' ); ?>: <span x-text="drawer.comparison.sortie_date"></span>
									</p>
									<template x-for="room in drawer.comparison.rooms" :key="room.name">
										<div class="limpeed-app-drawer-list-item">
											<span x-text="room.name"></span>
											<span :class="{ 'limpeed-text-danger': room.has_changed }" x-text="room.entree_label + ' → ' + room.sortie_label"></span>
										</div>
									</template>
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

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des états des lieux.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* inspections-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/inspections-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
