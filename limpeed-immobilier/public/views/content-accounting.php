<?php
/**
 * Contenu frontend de la section "Comptabilité" (menu Finances) : bilan
 * simplifié par période, grand livre consolidé (encaissements/reversements/
 * charges) et gestion des charges de l'agence. Contrairement à Trésorerie
 * (purement en lecture, rendu côté serveur), ce module a une vraie action
 * d'écriture (les charges) et bénéficie donc du même socle REST/Alpine que
 * les autres sections dynamiques.
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
	'buildingOptions'        => $building_options,
	'categories'             => Limpeed_Expenses::get_categories(),
	'entryTypes'             => Limpeed_Accounting::get_entry_types(),
	'currentPeriod'          => current_time( 'Y-m' ),
	'currentDate'            => current_time( 'Y-m-d' ),
	'financialResultsUrlBase' => Limpeed_Frontend::app_url( 'accounting', array( 'action' => 'download_financial_results', '_wpnonce' => wp_create_nonce( 'limpeed_download_financial_results' ) ) ),
	'ledgerExportUrlBase'    => Limpeed_Frontend::app_url( 'accounting', array( 'action' => 'export_ledger_csv', '_wpnonce' => wp_create_nonce( 'limpeed_export_ledger_csv' ) ) ),
	'i18n'                   => array(
		'expenseCreated' => __( 'Charge ajoutée avec succès.', 'limpeed-immobilier' ),
		'expenseUpdated' => __( 'Charge mise à jour avec succès.', 'limpeed-immobilier' ),
		'expenseDeleted' => __( 'Charge supprimée avec succès.', 'limpeed-immobilier' ),
		'confirmDelete'  => __( 'Confirmez-vous la suppression de cette charge ?', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>

<div class="limpeed-app-panel" x-data="limpeedAccountingApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<div class="limpeed-app-drawer-tabs">
		<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': activeTab === 'bilan' }" @click="switchTab('bilan')"><?php esc_html_e( 'Bilan', 'limpeed-immobilier' ); ?></button>
		<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': activeTab === 'ledger' }" @click="switchTab('ledger')"><?php esc_html_e( 'Grand livre', 'limpeed-immobilier' ); ?></button>
		<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': activeTab === 'expenses' }" @click="switchTab('expenses')"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></button>
	</div>

	<!-- Onglet Bilan -->
	<template x-if="activeTab === 'bilan'">
		<div style="padding: 20px 4px;">
			<div class="limpeed-form-row" style="max-width:220px;">
				<label><?php esc_html_e( 'Période', 'limpeed-immobilier' ); ?></label>
				<input type="month" x-model="bilan.period" @change="onBilanPeriodChange()">
			</div>

			<p x-show="bilan.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

			<template x-if="!bilan.loading && bilan.data">
				<div class="limpeed-cards-row">
					<div class="limpeed-app-card">
						<div class="limpeed-app-card-top">
							<div>
								<div class="limpeed-app-card-number" x-text="bilan.data.revenue_label"></div>
								<div class="limpeed-app-card-label"><?php esc_html_e( 'Produits (commissions de la période)', 'limpeed-immobilier' ); ?></div>
							</div>
							<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-money-alt"></span></span>
						</div>
					</div>
					<div class="limpeed-app-card">
						<div class="limpeed-app-card-top">
							<div>
								<div class="limpeed-app-card-number" x-text="bilan.data.expenses_label"></div>
								<div class="limpeed-app-card-label"><?php esc_html_e( 'Charges de la période', 'limpeed-immobilier' ); ?></div>
							</div>
							<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-money"></span></span>
						</div>
					</div>
					<div class="limpeed-app-card">
						<div class="limpeed-app-card-top">
							<div>
								<div class="limpeed-app-card-number" x-text="bilan.data.result_label"></div>
								<div class="limpeed-app-card-label"><?php esc_html_e( 'Résultat net', 'limpeed-immobilier' ); ?></div>
							</div>
							<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-chart-bar"></span></span>
						</div>
					</div>
				</div>
			</template>
			<p class="limpeed-app-description"><?php esc_html_e( 'Les produits de l\'agence sont ses commissions prélevées sur les loyers, pas les loyers eux-mêmes (qui appartiennent aux propriétaires). Le résultat net = produits − charges.', 'limpeed-immobilier' ); ?></p>
			<p><a :href="financialResultsUrl()" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Télécharger le PDF « Résultats financiers du mois »', 'limpeed-immobilier' ); ?></a></p>
		</div>
	</template>

	<!-- Onglet Grand livre -->
	<template x-if="activeTab === 'ledger'">
		<div>
			<div class="limpeed-app-toolbar">
				<div class="limpeed-app-search">
					<input type="text" x-model="ledger.search" @input="onLedgerSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher...', 'limpeed-immobilier' ); ?>">
					<select x-model="ledger.entryType" @change="onLedgerFilterChange()">
						<option value=""><?php esc_html_e( 'Tous les types', 'limpeed-immobilier' ); ?></option>
						<template x-for="(label, key) in entryTypes" :key="key">
							<option :value="key" x-text="label"></option>
						</template>
					</select>
					<input type="month" x-model="ledger.period" @change="onLedgerFilterChange()">
				</div>
				<a :href="ledgerExportUrl()" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Exporter en CSV', 'limpeed-immobilier' ); ?></a>
			</div>

			<div class="limpeed-app-table-wrap">
				<table class="limpeed-app-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Libellé', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<template x-if="ledger.loading">
							<template x-for="n in 5" :key="n">
								<tr class="limpeed-app-skeleton-row">
									<td><div class="limpeed-app-skeleton-bar" style="width:50%"></div></td>
									<td><div class="limpeed-app-skeleton-bar" style="width:60%"></div></td>
									<td><div class="limpeed-app-skeleton-bar" style="width:70%"></div></td>
									<td><div class="limpeed-app-skeleton-bar" style="width:40%"></div></td>
								</tr>
							</template>
						</template>
						<tr x-show="!ledger.loading && ledger.items.length === 0">
							<td colspan="4" class="limpeed-app-empty-state"><?php esc_html_e( 'Aucune écriture pour le moment.', 'limpeed-immobilier' ); ?></td>
						</tr>
						<template x-for="(row, index) in ledger.items" :key="row.entry_type + '-' + row.reference_id + '-' + index">
							<tr>
								<td x-text="row.entry_date"></td>
								<td><span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.entry_type" x-text="row.entry_type_label"></span></td>
								<td x-text="row.label"></td>
								<td x-text="row.amount_label"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>

			<div class="limpeed-app-pagination" x-show="ledger.totalPages > 1" x-cloak>
				<template x-for="p in ledger.totalPages" :key="p">
					<a href="#" @click.prevent="goToLedgerPage(p)" :class="{ 'is-active': p === ledger.paged }" x-text="p"></a>
				</template>
			</div>
		</div>
	</template>

	<!-- Onglet Charges -->
	<template x-if="activeTab === 'expenses'">
		<div>
			<div class="limpeed-app-toolbar">
				<div class="limpeed-app-search">
					<input type="text" x-model="expenses.search" @input="onExpensesSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher une charge...', 'limpeed-immobilier' ); ?>">
					<select x-model="expenses.category" @change="onExpensesFilterChange()">
						<option value=""><?php esc_html_e( 'Toutes les catégories', 'limpeed-immobilier' ); ?></option>
						<template x-for="(label, key) in categories" :key="key">
							<option :value="key" x-text="label"></option>
						</template>
					</select>
				</div>
				<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter une charge', 'limpeed-immobilier' ); ?></button>
			</div>

			<div class="limpeed-entity-grid">
				<template x-if="expenses.loading">
					<template x-for="n in 6" :key="n">
						<div class="limpeed-entity-card-skeleton"></div>
					</template>
				</template>
				<p x-show="!expenses.loading && expenses.items.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucune charge pour le moment.', 'limpeed-immobilier' ); ?></p>
				<template x-for="row in expenses.items" :key="row.id">
					<div class="limpeed-entity-card limpeed-entity-card--red" @click="openEditModal(row)">
						<div class="limpeed-entity-card-header">
							<span class="limpeed-entity-card-avatar limpeed-icon-red"><span class="dashicons dashicons-money-alt"></span></span>
							<div class="limpeed-entity-card-header-text">
								<div class="limpeed-entity-card-title" x-text="row.label"></div>
								<div class="limpeed-entity-card-subtitle" x-text="row.building_label || '—'"></div>
							</div>
							<span class="limpeed-app-badge" x-text="row.category_label"></span>
						</div>
						<div class="limpeed-entity-card-meta">
							<div class="limpeed-entity-card-meta-row">
								<span class="dashicons dashicons-calendar-alt"></span>
								<span x-text="row.expense_date"></span>
							</div>
						</div>
						<div>
							<div class="limpeed-entity-card-hero-label"><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></div>
							<div class="limpeed-entity-card-hero" x-text="row.amount_label"></div>
						</div>
						<div class="limpeed-entity-card-footer">
							<button type="button" class="limpeed-app-link-btn" @click.stop="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
							<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deleteExpense(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
						</div>
					</div>
				</template>
			</div>

			<div class="limpeed-app-pagination" x-show="expenses.totalPages > 1" x-cloak>
				<template x-for="p in expenses.totalPages" :key="p">
					<a href="#" @click.prevent="goToExpensesPage(p)" :class="{ 'is-active': p === expenses.paged }" x-text="p"></a>
				</template>
			</div>
		</div>
	</template>

	<!-- Modale ajout / modification d'une charge -->
	<div class="limpeed-app-modal-overlay" x-show="modal.open" x-cloak @keydown.escape.window="closeModal()">
		<div class="limpeed-app-modal" @click.outside="closeModal()" x-show="modal.open" x-transition>
			<div class="limpeed-app-modal-header">
				<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier la charge', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter une charge', 'limpeed-immobilier' ) ); ?>'"></h2>
				<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
			</div>
			<form class="limpeed-app-form" @submit.prevent="saveExpense()">
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
							<label><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<input type="date" x-model="modal.data.expense_date" required>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Catégorie', 'limpeed-immobilier' ); ?></label>
							<select x-model="modal.data.category">
								<template x-for="(label, key) in categories" :key="key">
									<option :value="key" x-text="label"></option>
								</template>
							</select>
						</div>
					</div>
					<div class="limpeed-form-row">
						<label><?php esc_html_e( 'Libellé', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
						<input type="text" x-model="modal.data.label" required>
					</div>
					<div class="limpeed-app-modal-grid">
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Montant (FCFA)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<input type="number" step="1" min="0" x-model="modal.data.amount" required>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Édifice concerné', 'limpeed-immobilier' ); ?></label>
							<select x-model="modal.data.building_id">
								<option value=""><?php esc_html_e( '— Charge générale de l\'agence —', 'limpeed-immobilier' ); ?></option>
								<template x-for="building in buildingOptions" :key="building.id">
									<option :value="building.id" x-text="building.label"></option>
								</template>
							</select>
						</div>
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

	<!-- Notifications toast -->
	<div class="limpeed-app-toast-container">
		<template x-for="t in toasts" :key="t.id">
			<div class="limpeed-app-toast" :class="'limpeed-app-toast-' + t.type" x-text="t.message"></div>
		</template>
	</div>
</div>

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la comptabilité.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* accounting-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/accounting-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
