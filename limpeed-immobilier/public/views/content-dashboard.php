<?php
/**
 * Contenu de la section "Tableau de bord" de l'application frontend.
 * Inclus par public/views/app.php entre app-header.php et app-footer.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Indicateurs généraux.
$owners_count        = Limpeed_Owners::count();
$buildings_count     = Limpeed_Buildings::count();
$properties_count    = Limpeed_Properties::count();
$properties_vacant   = Limpeed_Properties::count( array( 'status' => 'vacant' ) );
$properties_occupied = Limpeed_Properties::count( array( 'status' => 'loue' ) );
$tenants_count       = Limpeed_Tenants::count();
$tenants_active      = Limpeed_Tenants::count( array( 'status' => 'actif' ) );
$tenants_inactive    = max( 0, $tenants_count - $tenants_active );

// Recouvrement des loyers sur les 6 derniers mois.
$monthly_summary = Limpeed_Payments::get_monthly_summary( 6 );
$chart_max       = 1;
foreach ( $monthly_summary as $month ) {
	$chart_max = max( $chart_max, $month['expected_total'] );
}

?>

<?php
$tenants_active_ratio    = $tenants_count > 0 ? round( ( $tenants_active / $tenants_count ) * 100 ) : 0;
$properties_occupied_ratio = $properties_count > 0 ? round( ( $properties_occupied / $properties_count ) * 100 ) : 0;
?>
<div class="limpeed-cards-row">
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $owners_count ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Propriétaires', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-groups"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $tenants_count ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Locataires', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-admin-users"></span></span>
		</div>
		<div class="limpeed-app-card-ratio"><span style="width: <?php echo esc_attr( $tenants_active_ratio ); ?>%; background: var(--limpeed-green);"></span></div>
		<div class="limpeed-app-card-bar limpeed-bar-red">
			<span><?php printf( esc_html__( '%1$d actifs / %2$d inactifs', 'limpeed-immobilier' ), (int) $tenants_active, (int) $tenants_inactive ); ?></span>
		</div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $buildings_count ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Édifices', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-admin-multisite"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $properties_count ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Biens (sous-édifices)', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-building"></span></span>
		</div>
		<div class="limpeed-app-card-ratio"><span style="width: <?php echo esc_attr( $properties_occupied_ratio ); ?>%; background: var(--limpeed-blue);"></span></div>
		<div class="limpeed-app-card-bar limpeed-bar-green">
			<span><?php printf( esc_html__( '%1$d disponibles / %2$d occupés', 'limpeed-immobilier' ), (int) $properties_vacant, (int) $properties_occupied ); ?></span>
		</div>
	</div>
</div>

<?php
$kpi_config = array(
	'expiringDays' => 30,
	'i18n'         => array(
		'occupancyRate'  => __( 'Taux d\'occupation', 'limpeed-immobilier' ),
		'unpaidRents'    => __( 'Loyers impayés (mois en cours)', 'limpeed-immobilier' ),
		'expiringLeases' => __( 'Baux arrivant à échéance (30 jours)', 'limpeed-immobilier' ),
	),
);
$kpi_rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>
<div class="limpeed-cards-row" x-data="limpeedDashboardKpisApp(<?php echo esc_attr( wp_json_encode( $kpi_config ) ); ?>)">
	<div class="limpeed-app-card">
		<button type="button" class="limpeed-app-card-clickable" @click="togglePanel('occupancy')">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number" x-text="loading ? '…' : occupancyRate + '%'"></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Taux d\'occupation', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-chart-pie"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue">
				<span x-text="propertiesOccupied + ' / ' + propertiesTotal + ' <?php echo esc_js( __( 'biens occupés', 'limpeed-immobilier' ) ); ?>'"></span>
			</div>
		</button>
	</div>
	<div class="limpeed-app-card">
		<button type="button" class="limpeed-app-card-clickable" @click="togglePanel('unpaid')">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number" x-text="loading ? '…' : unpaidCount"></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Loyers impayés (mois en cours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-warning"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-red">
				<span x-text="unpaidTotalFormatted"></span>
			</div>
		</button>
		<div class="limpeed-app-card-panel" x-show="expandedPanel === 'unpaid'" x-cloak>
			<p class="limpeed-app-card-panel-empty" x-show="unpaidTenants.length === 0"><?php esc_html_e( 'Aucun impayé ce mois-ci.', 'limpeed-immobilier' ); ?></p>
			<template x-for="tenant in unpaidTenants" :key="tenant.id">
				<div class="limpeed-app-card-panel-item">
					<span x-text="tenant.full_name"></span>
					<span x-text="tenant.rent_formatted"></span>
				</div>
			</template>
		</div>
	</div>
	<div class="limpeed-app-card">
		<button type="button" class="limpeed-app-card-clickable" @click="togglePanel('expiring')">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number" x-text="loading ? '…' : expiringCount"></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Baux arrivant à échéance (30 jours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-calendar-alt"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange">
				<span><?php esc_html_e( 'Cliquer pour voir le détail', 'limpeed-immobilier' ); ?></span>
			</div>
		</button>
		<div class="limpeed-app-card-panel" x-show="expandedPanel === 'expiring'" x-cloak>
			<p class="limpeed-app-card-panel-empty" x-show="expiringLeases.length === 0"><?php esc_html_e( 'Aucun bail à échéance dans les 30 prochains jours.', 'limpeed-immobilier' ); ?></p>
			<template x-for="tenant in expiringLeases" :key="tenant.id">
				<div class="limpeed-app-card-panel-item">
					<span x-text="tenant.full_name + ' — ' + tenant.property_label"></span>
					<span x-text="tenant.lease_end"></span>
				</div>
			</template>
		</div>
	</div>
</div>

<script>
window.limpeedRest = <?php echo wp_json_encode( $kpi_rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* dashboard-kpis-app.js et dashboard-lists-app.js enregistrent leur composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : ils doivent donc être chargés (et leur listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/dashboard-kpis-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/dashboard-lists-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>

<div class="limpeed-app-panel">
	<h2><?php esc_html_e( 'Statistiques de recouvrement mensuel des loyers', 'limpeed-immobilier' ); ?></h2>
	<div class="limpeed-chart">
		<?php foreach ( $monthly_summary as $month ) : ?>
			<?php
			$impaye   = max( 0, $month['expected_total'] - $month['collected'] );
			$h_total  = round( ( $month['expected_total'] / $chart_max ) * 100 );
			$h_paye   = round( ( $month['collected'] / $chart_max ) * 100 );
			$h_impaye = round( ( $impaye / $chart_max ) * 100 );
			?>
			<div class="limpeed-chart-month">
				<div class="limpeed-chart-bars">
					<div class="limpeed-chart-bar limpeed-bar-blue" style="height: <?php echo esc_attr( $h_total ); ?>%" title="<?php echo esc_attr( Limpeed_Payments::format_amount( $month['expected_total'] ) ); ?>"></div>
					<div class="limpeed-chart-bar limpeed-bar-green" style="height: <?php echo esc_attr( $h_paye ); ?>%" title="<?php echo esc_attr( Limpeed_Payments::format_amount( $month['collected'] ) ); ?>"></div>
					<div class="limpeed-chart-bar limpeed-bar-red" style="height: <?php echo esc_attr( $h_impaye ); ?>%" title="<?php echo esc_attr( Limpeed_Payments::format_amount( $impaye ) ); ?>"></div>
				</div>
				<div class="limpeed-chart-label"><?php echo esc_html( date_i18n( 'M-Y', strtotime( $month['period'] . '-01' ) ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="limpeed-chart-legend">
		<span class="limpeed-legend-item"><i class="limpeed-bar-blue"></i> <?php esc_html_e( 'Total attendu', 'limpeed-immobilier' ); ?></span>
		<span class="limpeed-legend-item"><i class="limpeed-bar-green"></i> <?php esc_html_e( 'Payé', 'limpeed-immobilier' ); ?></span>
		<span class="limpeed-legend-item"><i class="limpeed-bar-red"></i> <?php esc_html_e( 'Impayé', 'limpeed-immobilier' ); ?></span>
	</div>
</div>

<?php
$lists_config = array(
	'i18n' => array(
		'showingRange' => __( '%1$d–%2$d sur %3$d', 'limpeed-immobilier' ),
		'noResults'    => __( 'Aucun résultat.', 'limpeed-immobilier' ),
	),
);
?>
<div x-data="limpeedDashboardListsApp(<?php echo esc_attr( wp_json_encode( $lists_config ) ); ?>)">
	<div class="limpeed-tables-row">
		<div class="limpeed-app-panel">
			<h2 class="limpeed-panel-title-green"><?php esc_html_e( 'Derniers locataires', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-list-toolbar">
				<label class="limpeed-app-list-pagesize">
					<?php esc_html_e( 'Afficher', 'limpeed-immobilier' ); ?>
					<select x-model.number="lists.tenants.perPage" @change="onPerPageChange('tenants')">
						<option value="5">5</option>
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
					</select>
				</label>
				<input type="text" placeholder="<?php esc_attr_e( 'Rechercher…', 'limpeed-immobilier' ); ?>" x-model="lists.tenants.search" @input="onSearchInput('tenants')">
			</div>
			<div class="limpeed-app-table-wrap">
				<table class="limpeed-app-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Solde', 'limpeed-immobilier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<template x-if="lists.tenants.loading">
							<tr class="limpeed-app-skeleton-row"><td colspan="3"><div class="limpeed-app-skeleton-bar"></div></td></tr>
						</template>
						<template x-if="!lists.tenants.loading && lists.tenants.items.length === 0">
							<tr><td colspan="3"><?php esc_html_e( 'Aucun locataire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
						</template>
						<template x-for="tenant in lists.tenants.items" :key="tenant.id">
							<tr>
								<td>
									<span x-text="tenant.full_name"></span><br>
									<small x-text="tenant.phone"></small>
								</td>
								<td><span class="limpeed-app-badge" :class="'limpeed-app-badge-' + tenant.status" x-text="tenant.status_label"></span></td>
								<td :class="{ 'limpeed-text-danger': tenant.balance > 0 }" x-text="tenant.balance_formatted"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
			<div class="limpeed-app-pagination-bar">
				<span class="limpeed-app-pagination-range" x-text="rangeLabel('tenants')"></span>
				<div class="limpeed-app-pagination">
					<button type="button" @click="goToPage('tenants', 1)" :disabled="lists.tenants.page === 1"><?php esc_html_e( 'Premier', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('tenants', lists.tenants.page - 1)" :disabled="lists.tenants.page === 1"><?php esc_html_e( 'Précédent', 'limpeed-immobilier' ); ?></button>
					<template x-for="p in pageNumbers('tenants')" :key="p">
						<button type="button" @click="goToPage('tenants', p)" :class="{ 'is-active': p === lists.tenants.page }" x-text="p"></button>
					</template>
					<button type="button" @click="goToPage('tenants', lists.tenants.page + 1)" :disabled="lists.tenants.page === lists.tenants.totalPages"><?php esc_html_e( 'Suivant', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('tenants', lists.tenants.totalPages)" :disabled="lists.tenants.page === lists.tenants.totalPages"><?php esc_html_e( 'Dernier', 'limpeed-immobilier' ); ?></button>
				</div>
			</div>
		</div>

		<div class="limpeed-app-panel">
			<h2 class="limpeed-panel-title-orange"><?php esc_html_e( 'Derniers propriétaires', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-list-toolbar">
				<label class="limpeed-app-list-pagesize">
					<?php esc_html_e( 'Afficher', 'limpeed-immobilier' ); ?>
					<select x-model.number="lists.owners.perPage" @change="onPerPageChange('owners')">
						<option value="5">5</option>
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
					</select>
				</label>
				<input type="text" placeholder="<?php esc_attr_e( 'Rechercher…', 'limpeed-immobilier' ); ?>" x-model="lists.owners.search" @input="onSearchInput('owners')">
			</div>
			<div class="limpeed-app-table-wrap">
				<table class="limpeed-app-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<template x-if="lists.owners.loading">
							<tr class="limpeed-app-skeleton-row"><td colspan="2"><div class="limpeed-app-skeleton-bar"></div></td></tr>
						</template>
						<template x-if="!lists.owners.loading && lists.owners.items.length === 0">
							<tr><td colspan="2"><?php esc_html_e( 'Aucun propriétaire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
						</template>
						<template x-for="owner in lists.owners.items" :key="owner.id">
							<tr>
								<td x-text="owner.full_name"></td>
								<td x-text="owner.phone"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
			<div class="limpeed-app-pagination-bar">
				<span class="limpeed-app-pagination-range" x-text="rangeLabel('owners')"></span>
				<div class="limpeed-app-pagination">
					<button type="button" @click="goToPage('owners', 1)" :disabled="lists.owners.page === 1"><?php esc_html_e( 'Premier', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('owners', lists.owners.page - 1)" :disabled="lists.owners.page === 1"><?php esc_html_e( 'Précédent', 'limpeed-immobilier' ); ?></button>
					<template x-for="p in pageNumbers('owners')" :key="p">
						<button type="button" @click="goToPage('owners', p)" :class="{ 'is-active': p === lists.owners.page }" x-text="p"></button>
					</template>
					<button type="button" @click="goToPage('owners', lists.owners.page + 1)" :disabled="lists.owners.page === lists.owners.totalPages"><?php esc_html_e( 'Suivant', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('owners', lists.owners.totalPages)" :disabled="lists.owners.page === lists.owners.totalPages"><?php esc_html_e( 'Dernier', 'limpeed-immobilier' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<div class="limpeed-tables-row">
		<div class="limpeed-app-panel">
			<h2 class="limpeed-panel-title-green"><?php esc_html_e( 'Quittances soldées', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-list-toolbar">
				<label class="limpeed-app-list-pagesize">
					<?php esc_html_e( 'Afficher', 'limpeed-immobilier' ); ?>
					<select x-model.number="lists.paid.perPage" @change="onPerPageChange('paid')">
						<option value="5">5</option>
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
					</select>
				</label>
				<input type="text" placeholder="<?php esc_attr_e( 'Rechercher…', 'limpeed-immobilier' ); ?>" x-model="lists.paid.search" @input="onSearchInput('paid')">
			</div>
			<div class="limpeed-app-table-wrap">
				<table class="limpeed-app-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Période', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<template x-if="lists.paid.loading">
							<tr class="limpeed-app-skeleton-row"><td colspan="3"><div class="limpeed-app-skeleton-bar"></div></td></tr>
						</template>
						<template x-if="!lists.paid.loading && lists.paid.items.length === 0">
							<tr><td colspan="3"><?php esc_html_e( 'Aucune quittance pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
						</template>
						<template x-for="payment in lists.paid.items" :key="payment.id">
							<tr>
								<td x-text="payment.tenant_label || '—'"></td>
								<td x-text="payment.period"></td>
								<td x-text="payment.amount_formatted"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
			<div class="limpeed-app-pagination-bar">
				<span class="limpeed-app-pagination-range" x-text="rangeLabel('paid')"></span>
				<div class="limpeed-app-pagination">
					<button type="button" @click="goToPage('paid', 1)" :disabled="lists.paid.page === 1"><?php esc_html_e( 'Premier', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('paid', lists.paid.page - 1)" :disabled="lists.paid.page === 1"><?php esc_html_e( 'Précédent', 'limpeed-immobilier' ); ?></button>
					<template x-for="p in pageNumbers('paid')" :key="p">
						<button type="button" @click="goToPage('paid', p)" :class="{ 'is-active': p === lists.paid.page }" x-text="p"></button>
					</template>
					<button type="button" @click="goToPage('paid', lists.paid.page + 1)" :disabled="lists.paid.page === lists.paid.totalPages"><?php esc_html_e( 'Suivant', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('paid', lists.paid.totalPages)" :disabled="lists.paid.page === lists.paid.totalPages"><?php esc_html_e( 'Dernier', 'limpeed-immobilier' ); ?></button>
				</div>
			</div>
		</div>

		<div class="limpeed-app-panel">
			<h2 class="limpeed-panel-title-blue"><?php esc_html_e( 'Quittances en attente de paiement', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-list-toolbar">
				<label class="limpeed-app-list-pagesize">
					<?php esc_html_e( 'Afficher', 'limpeed-immobilier' ); ?>
					<select x-model.number="lists.unpaid.perPage" @change="onPerPageChange('unpaid')">
						<option value="5">5</option>
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
					</select>
				</label>
				<input type="text" placeholder="<?php esc_attr_e( 'Rechercher…', 'limpeed-immobilier' ); ?>" x-model="lists.unpaid.search" @input="onSearchInput('unpaid')">
			</div>
			<div class="limpeed-app-table-wrap">
				<table class="limpeed-app-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></th>
							<th><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<template x-if="lists.unpaid.loading">
							<tr class="limpeed-app-skeleton-row"><td colspan="2"><div class="limpeed-app-skeleton-bar"></div></td></tr>
						</template>
						<template x-if="!lists.unpaid.loading && lists.unpaid.items.length === 0">
							<tr><td colspan="2"><?php esc_html_e( 'Aucun impayé ce mois-ci.', 'limpeed-immobilier' ); ?></td></tr>
						</template>
						<template x-for="tenant in lists.unpaid.items" :key="tenant.id">
							<tr>
								<td x-text="tenant.full_name"></td>
								<td class="limpeed-text-danger" x-text="tenant.rent_formatted"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
			<div class="limpeed-app-pagination-bar">
				<span class="limpeed-app-pagination-range" x-text="rangeLabel('unpaid')"></span>
				<div class="limpeed-app-pagination">
					<button type="button" @click="goToPage('unpaid', 1)" :disabled="lists.unpaid.page === 1"><?php esc_html_e( 'Premier', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('unpaid', lists.unpaid.page - 1)" :disabled="lists.unpaid.page === 1"><?php esc_html_e( 'Précédent', 'limpeed-immobilier' ); ?></button>
					<template x-for="p in pageNumbers('unpaid')" :key="p">
						<button type="button" @click="goToPage('unpaid', p)" :class="{ 'is-active': p === lists.unpaid.page }" x-text="p"></button>
					</template>
					<button type="button" @click="goToPage('unpaid', lists.unpaid.page + 1)" :disabled="lists.unpaid.page === lists.unpaid.totalPages"><?php esc_html_e( 'Suivant', 'limpeed-immobilier' ); ?></button>
					<button type="button" @click="goToPage('unpaid', lists.unpaid.totalPages)" :disabled="lists.unpaid.page === lists.unpaid.totalPages"><?php esc_html_e( 'Dernier', 'limpeed-immobilier' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
