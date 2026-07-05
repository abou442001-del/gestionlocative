<?php
/**
 * Contenu frontend de la section "Travaux" (liste dynamique uniquement,
 * pas de formulaire classique en repli — même choix que Mandats).
 *
 * Un agent (capacité manage_limpeed_properties) peut soumettre une demande
 * de travaux sur un édifice (montant + motif). Seul un administrateur
 * (capacité manage_limpeed_agents) peut l'approuver ou la refuser ; une
 * demande approuvée crée automatiquement la charge correspondante dans
 * Comptabilité (voir Limpeed_Work_Requests::approve()).
 *
 * Toutes les données transitent par l'API REST limpeed/v1.
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
	'statuses'        => Limpeed_Work_Requests::get_statuses(),
	'canReview'       => current_user_can( 'manage_limpeed_agents' ),
	'i18n'            => array(
		'created'       => __( 'Demande de travaux envoyée avec succès.', 'limpeed-immobilier' ),
		'deleted'       => __( 'Demande supprimée avec succès.', 'limpeed-immobilier' ),
		'approved'      => __( 'Demande approuvée : la charge a été ajoutée en Comptabilité.', 'limpeed-immobilier' ),
		'rejected'      => __( 'Demande refusée.', 'limpeed-immobilier' ),
		'confirmDelete' => __( 'Confirmez-vous la suppression de cette demande ?', 'limpeed-immobilier' ),
		'confirmApprove' => __( 'Confirmez-vous l\'approbation de cette demande ? La charge correspondante sera ajoutée automatiquement en Comptabilité.', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);

// Cartes de synthèse.
$requests_total   = Limpeed_Work_Requests::count();
$requests_pending = Limpeed_Work_Requests::count( array( 'status' => 'en_attente' ) );
$requests_approved = Limpeed_Work_Requests::count( array( 'status' => 'approuve' ) );
?>

<div class="limpeed-cards-row">
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $requests_total ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Demandes de travaux', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-hammer"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $requests_pending ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'En attente de validation', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-clock"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $requests_approved ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Approuvées', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-yes-alt"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-green"></div>
	</div>
</div>

<div class="limpeed-app-panel" x-data="limpeedWorkRequestsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<div class="limpeed-app-toolbar">
		<div class="limpeed-app-search">
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
		<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Demander des travaux', 'limpeed-immobilier' ); ?></button>
	</div>

	<div class="limpeed-entity-grid">
		<template x-if="loading">
			<template x-for="n in 6" :key="n">
				<div class="limpeed-entity-card-skeleton"></div>
			</template>
		</template>
		<p x-show="!loading && items.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucune demande de travaux pour le moment.', 'limpeed-immobilier' ); ?></p>
		<template x-for="row in items" :key="row.id">
			<div class="limpeed-entity-card">
				<div class="limpeed-entity-card-header">
					<span class="limpeed-entity-card-avatar limpeed-icon-blue"><span class="dashicons dashicons-hammer"></span></span>
					<div class="limpeed-entity-card-header-text">
						<div class="limpeed-entity-card-title" x-text="row.building_label"></div>
						<div class="limpeed-entity-card-subtitle" x-text="row.requested_by_label || '—'"></div>
					</div>
					<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.status" x-text="row.status_label"></span>
				</div>
				<div class="limpeed-entity-card-meta">
					<div class="limpeed-entity-card-meta-row">
						<span class="dashicons dashicons-money-alt"></span>
						<span x-text="row.amount_formatted"></span>
					</div>
					<div class="limpeed-entity-card-meta-row">
						<span class="dashicons dashicons-edit-large"></span>
						<span x-text="row.reason"></span>
					</div>
					<template x-if="row.status !== 'en_attente'">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-admin-users"></span>
							<span x-text="row.reviewed_by_label"></span>
						</div>
					</template>
					<template x-if="'refuse' === row.status && row.review_notes">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-warning"></span>
							<span x-text="row.review_notes"></span>
						</div>
					</template>
				</div>
				<div class="limpeed-entity-card-footer">
					<template x-if="canReview && 'en_attente' === row.status">
						<span>
							<button type="button" class="limpeed-app-link-btn" @click.stop="approveRequest(row)"><?php esc_html_e( 'Approuver', 'limpeed-immobilier' ); ?></button>
							<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="openRejectModal(row)"><?php esc_html_e( 'Refuser', 'limpeed-immobilier' ); ?></button>
						</span>
					</template>
					<template x-if="'en_attente' === row.status">
						<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deleteRequest(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
					</template>
				</div>
			</div>
		</template>
	</div>

	<div class="limpeed-app-pagination" x-show="totalPages > 1" x-cloak>
		<template x-for="p in totalPages" :key="p">
			<a href="#" @click.prevent="goToPage(p)" :class="{ 'is-active': p === paged }" x-text="p"></a>
		</template>
	</div>

	<!-- Modale demande de travaux -->
	<div class="limpeed-app-modal-overlay" x-show="modal.open" x-cloak @keydown.escape.window="closeModal()">
		<div class="limpeed-app-modal" @click.outside="closeModal()" x-show="modal.open" x-transition>
			<div class="limpeed-app-modal-header">
				<h2><?php esc_html_e( 'Demander des travaux', 'limpeed-immobilier' ); ?></h2>
				<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
			</div>
			<form class="limpeed-app-form" @submit.prevent="saveRequest()">
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
						<select x-model="modal.data.building_id" required>
							<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
							<template x-for="building in buildingOptions" :key="building.id">
								<option :value="building.id" x-text="building.label"></option>
							</template>
						</select>
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Montant estimé (FCFA)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
						<input type="number" step="0.01" min="0" x-model="modal.data.amount" required>
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Motif', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
						<textarea x-model="modal.data.reason" rows="3" required placeholder="<?php esc_attr_e( 'Ex : Réparation de la toiture du bâtiment B suite à une fuite.', 'limpeed-immobilier' ); ?>"></textarea>
					</div>
				</div>
				<div class="limpeed-app-modal-footer">
					<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="closeModal()"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></button>
					<button type="submit" class="limpeed-app-btn" :disabled="modal.saving">
						<span x-text="modal.saving ? '<?php echo esc_js( __( 'Envoi...', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Envoyer la demande', 'limpeed-immobilier' ) ); ?>'"></span>
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modale de refus -->
	<div class="limpeed-app-modal-overlay" x-show="rejectModal.open" x-cloak @keydown.escape.window="closeRejectModal()">
		<div class="limpeed-app-modal" @click.outside="closeRejectModal()" x-show="rejectModal.open" x-transition>
			<div class="limpeed-app-modal-header">
				<h2><?php esc_html_e( 'Refuser la demande', 'limpeed-immobilier' ); ?></h2>
				<button type="button" class="limpeed-app-modal-close" @click="closeRejectModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
			</div>
			<form class="limpeed-app-form" @submit.prevent="confirmReject()">
				<div class="limpeed-app-modal-body">
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Motif du refus (optionnel)', 'limpeed-immobilier' ); ?></label>
						<textarea x-model="rejectModal.notes" rows="3"></textarea>
					</div>
				</div>
				<div class="limpeed-app-modal-footer">
					<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="closeRejectModal()"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></button>
					<button type="submit" class="limpeed-app-btn" :disabled="rejectModal.saving">
						<span x-text="rejectModal.saving ? '<?php echo esc_js( __( 'Envoi...', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Confirmer le refus', 'limpeed-immobilier' ) ); ?>'"></span>
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Notifications toast -->
	<div class="limpeed-app-toast-container">
		<template x-for="t in toasts" :key="t.id">
			<div class="limpeed-app-toast" :class="'limpeed-app-toast-' + t.type" x-text="t.message"></div>
		</template>
	</div>
</div>

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des demandes de travaux.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* work-requests-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/work-requests-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
