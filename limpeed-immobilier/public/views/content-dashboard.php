<?php
/**
 * Contenu de la section "Tableau de bord" de l'application frontend.
 * Inclus par public/views/app.php entre app-header.php et app-footer.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

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
$current_summary = end( $monthly_summary );
$chart_max       = 1;
foreach ( $monthly_summary as $month ) {
	$chart_max = max( $chart_max, $month['expected_total'] );
}

// Listes des 10 derniers éléments.
$recent_tenants = Limpeed_Tenants::get_all( array( 'orderby' => 'id', 'order' => 'DESC', 'per_page' => 10 ) );
$recent_owners  = Limpeed_Owners::get_all( array( 'orderby' => 'id', 'order' => 'DESC', 'per_page' => 10 ) );
$recent_paid    = Limpeed_Payments::get_all( array( 'status' => 'paye', 'orderby' => 'payment_date', 'order' => 'DESC', 'per_page' => 10 ) );
$unpaid_tenants = array_slice( $current_summary['unpaid_tenants'], 0, 10 );

// Solde du mois en cours pour chaque locataire récent (0 si un paiement "payé" existe déjà ce mois-ci).
$current_period  = Limpeed_Payments::get_current_period();
$payments_table  = Limpeed_Payments::table();
$tenant_balances = array();
foreach ( $recent_tenants as $recent_tenant ) {
	$has_paid = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$payments_table} WHERE tenant_id = %d AND period = %s AND status = 'paye'",
			$recent_tenant->id,
			$current_period
		)
	);
	$tenant_balances[ $recent_tenant->id ] = $has_paid ? 0.0 : (float) $recent_tenant->rent_amount;
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
<?php /* dashboard-kpis-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/dashboard-kpis-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
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

<div class="limpeed-tables-row">
	<div class="limpeed-app-panel">
		<h2 class="limpeed-panel-title-green"><?php esc_html_e( 'Liste des 10 derniers locataires', 'limpeed-immobilier' ); ?></h2>
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
				<?php if ( empty( $recent_tenants ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Aucun locataire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $recent_tenants as $recent_tenant ) : ?>
					<?php $balance = $tenant_balances[ $recent_tenant->id ]; ?>
					<tr>
						<td>
							<?php echo esc_html( $recent_tenant->full_name ); ?><br>
							<small><?php echo esc_html( $recent_tenant->phone ); ?></small>
						</td>
						<td><span class="limpeed-app-badge limpeed-app-badge-<?php echo esc_attr( $recent_tenant->status ); ?>"><?php echo esc_html( Limpeed_Tenants::get_statuses()[ $recent_tenant->status ] ?? $recent_tenant->status ); ?></span></td>
						<td class="<?php echo $balance > 0 ? 'limpeed-text-danger' : ''; ?>"><?php echo esc_html( Limpeed_Payments::format_amount( $balance ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
</div>
	</div>

	<div class="limpeed-app-panel">
		<h2 class="limpeed-panel-title-orange"><?php esc_html_e( 'Liste des 10 derniers propriétaires', 'limpeed-immobilier' ); ?></h2>
		<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recent_owners ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Aucun propriétaire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $recent_owners as $recent_owner ) : ?>
					<tr>
						<td><?php echo esc_html( $recent_owner->full_name ); ?></td>
						<td><?php echo esc_html( $recent_owner->phone ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
</div>
	</div>
</div>

<div class="limpeed-tables-row">
	<div class="limpeed-app-panel">
		<h2 class="limpeed-panel-title-green"><?php esc_html_e( 'Liste des 10 dernières quittances soldées', 'limpeed-immobilier' ); ?></h2>
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
				<?php if ( empty( $recent_paid ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Aucune quittance pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $recent_paid as $payment ) : ?>
					<?php $payment_tenant = Limpeed_Tenants::get( $payment->tenant_id ); ?>
					<tr>
						<td><?php echo $payment_tenant ? esc_html( $payment_tenant->full_name ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $payment->period ); ?></td>
						<td><?php echo esc_html( Limpeed_Payments::format_amount( $payment->amount ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
</div>
	</div>

	<div class="limpeed-app-panel">
		<h2 class="limpeed-panel-title-blue"><?php esc_html_e( 'Liste des 10 dernières quittances en attente de paiement', 'limpeed-immobilier' ); ?></h2>
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
				<?php if ( empty( $unpaid_tenants ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Aucun impayé ce mois-ci.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $unpaid_tenants as $unpaid_tenant ) : ?>
					<tr>
						<td><?php echo esc_html( $unpaid_tenant->full_name ); ?></td>
						<td><?php echo esc_html( $current_period ); ?></td>
						<td class="limpeed-text-danger"><?php echo esc_html( Limpeed_Payments::format_amount( $unpaid_tenant->rent_amount ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
</div>
	</div>
</div>
