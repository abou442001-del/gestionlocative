<?php
/**
 * Contenu frontend de la section "Trésorerie" : deux onglets.
 * - Vue d'ensemble : rapport en lecture seule (solde net, graphique de flux,
 *   derniers décaissements), rendu directement côté serveur sans Ajax, comme
 *   avant l'introduction des Caisses.
 * - Caisses : caisses de l'agence (Commission agence, Caution, Timbres
 *   fiscaux...) tenues manuellement par l'agent, à l'image de l'ancien
 *   logiciel du client — chaque caisse est un petit livre de mouvements
 *   (entrées/sorties) avec un solde calculé automatiquement.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cash_position     = Limpeed_Treasury::get_cash_position();
$total_collected   = Limpeed_Treasury::get_total_collected();
$total_commission  = Limpeed_Treasury::get_total_commission();
$total_reversed    = Limpeed_Treasury::get_total_reversed();
$cashflow_series   = Limpeed_Treasury::get_cashflow_series( 6, 3 );
$recent_disbursements = Limpeed_Treasury::get_recent_disbursements( 10 );

$chart_max = 1;
foreach ( $cashflow_series as $month ) {
	$chart_max = max( $chart_max, $month['expected_total'] );
}

$app_config = array(
	'categories'  => Limpeed_Funds::get_categories(),
	'currentDate' => current_time( 'Y-m-d' ),
	'i18n'        => array(
		'transactionAdded'   => __( 'Mouvement enregistré avec succès.', 'limpeed-immobilier' ),
		'transactionDeleted' => __( 'Mouvement supprimé avec succès.', 'limpeed-immobilier' ),
		'confirmDelete'      => __( 'Confirmez-vous la suppression de ce mouvement ?', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>

<div x-data="limpeedTreasuryApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<div class="limpeed-app-drawer-tabs">
		<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': activeTab === 'overview' }" @click="switchTab('overview')"><?php esc_html_e( "Vue d'ensemble", 'limpeed-immobilier' ); ?></button>
		<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': activeTab === 'caisses' }" @click="switchTab('caisses')"><?php esc_html_e( 'Caisses', 'limpeed-immobilier' ); ?></button>
	</div>

	<!-- Onglet Vue d'ensemble -->
	<div x-show="activeTab === 'overview'">
		<div class="limpeed-cards-row">
			<div class="limpeed-app-card">
				<div class="limpeed-app-card-top">
					<div>
						<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $cash_position ) ); ?></div>
						<div class="limpeed-app-card-label"><?php esc_html_e( 'Solde de trésorerie', 'limpeed-immobilier' ); ?></div>
					</div>
					<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-chart-line"></span></span>
				</div>
				<div class="limpeed-app-card-bar limpeed-bar-green"><span><?php esc_html_e( 'Encaissé net, en attente de reversement', 'limpeed-immobilier' ); ?></span></div>
			</div>
			<div class="limpeed-app-card">
				<div class="limpeed-app-card-top">
					<div>
						<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $total_collected ) ); ?></div>
						<div class="limpeed-app-card-label"><?php esc_html_e( 'Total encaissé (depuis toujours)', 'limpeed-immobilier' ); ?></div>
					</div>
					<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-money-alt"></span></span>
				</div>
			</div>
			<div class="limpeed-app-card">
				<div class="limpeed-app-card-top">
					<div>
						<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $total_commission ) ); ?></div>
						<div class="limpeed-app-card-label"><?php esc_html_e( 'Total commissions agence', 'limpeed-immobilier' ); ?></div>
					</div>
					<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-portfolio"></span></span>
				</div>
			</div>
			<div class="limpeed-app-card">
				<div class="limpeed-app-card-top">
					<div>
						<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $total_reversed ) ); ?></div>
						<div class="limpeed-app-card-label"><?php esc_html_e( 'Total reversé aux propriétaires', 'limpeed-immobilier' ); ?></div>
					</div>
					<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-upload"></span></span>
				</div>
			</div>
		</div>

		<div class="limpeed-app-panel">
			<h2><?php esc_html_e( 'Flux de trésorerie mensuel (réel et prévisionnel)', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-chart">
				<?php foreach ( $cashflow_series as $month ) : ?>
					<?php
					$h_total = round( ( $month['expected_total'] / $chart_max ) * 100 );
					$h_paid  = round( ( $month['collected'] / $chart_max ) * 100 );
					?>
					<div class="limpeed-chart-month">
						<div class="limpeed-chart-bars">
							<div class="limpeed-chart-bar <?php echo $month['is_forecast'] ? 'limpeed-bar-orange' : 'limpeed-bar-blue'; ?>" style="height: <?php echo esc_attr( $h_total ); ?>%" title="<?php echo esc_attr( Limpeed_Payments::format_amount( $month['expected_total'] ) ); ?>"></div>
							<div class="limpeed-chart-bar limpeed-bar-green" style="height: <?php echo esc_attr( $h_paid ); ?>%" title="<?php echo esc_attr( Limpeed_Payments::format_amount( $month['collected'] ) ); ?>"></div>
						</div>
						<div class="limpeed-chart-label"><?php echo esc_html( date_i18n( 'M-Y', strtotime( $month['period'] . '-01' ) ) ); ?><?php echo $month['is_forecast'] ? ' *' : ''; ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="limpeed-chart-legend">
				<span class="limpeed-legend-item"><i class="limpeed-bar-blue"></i> <?php esc_html_e( 'Attendu (mois passés)', 'limpeed-immobilier' ); ?></span>
				<span class="limpeed-legend-item"><i class="limpeed-bar-orange"></i> <?php esc_html_e( 'Prévision (mois à venir)', 'limpeed-immobilier' ); ?></span>
				<span class="limpeed-legend-item"><i class="limpeed-bar-green"></i> <?php esc_html_e( 'Encaissé', 'limpeed-immobilier' ); ?></span>
			</div>
			<p class="limpeed-app-description">* <?php esc_html_e( 'Prévision basée sur les locataires actifs actuels, en supposant un portefeuille inchangé.', 'limpeed-immobilier' ); ?></p>
		</div>

		<div class="limpeed-app-panel">
			<h2 class="limpeed-panel-title-blue"><?php esc_html_e( 'Derniers décaissements (reversements propriétaires)', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-table-wrap">
		<table class="limpeed-app-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Période', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Encaissé', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Commission', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Net reversé', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_disbursements ) ) : ?>
						<tr><td colspan="6" class="limpeed-app-empty-state"><?php esc_html_e( 'Aucun reversement pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $recent_disbursements as $statement ) : ?>
						<?php $statement_owner = Limpeed_Owners::get( $statement->owner_id ); ?>
						<tr>
							<td><?php echo esc_html( $statement_owner ? $statement_owner->full_name : '—' ); ?></td>
							<td><?php echo esc_html( $statement->period_start === $statement->period_end ? $statement->period_start : $statement->period_start . ' — ' . $statement->period_end ); ?></td>
							<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement->total_collected ) ); ?></td>
							<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement->total_commission ) ); ?></td>
							<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement->net_amount ) ); ?></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $statement->created_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
			<p><a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements' ) ); ?>" class="limpeed-app-link-btn"><?php esc_html_e( 'Voir tous les bordereaux', 'limpeed-immobilier' ); ?> &rarr;</a></p>
		</div>
	</div>

	<!-- Onglet Caisses -->
	<template x-if="activeTab === 'caisses'">
		<div class="limpeed-app-panel">
			<p class="limpeed-app-description"><?php esc_html_e( "Chaque caisse est un petit livre de mouvements tenu manuellement par l'agent (entrées/sorties d'argent). Le Solde est la somme de toutes les caisses ci-dessous. Cliquez sur une caisse pour voir son historique et ajouter un mouvement.", 'limpeed-immobilier' ); ?></p>

			<p x-show="funds.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

			<template x-if="!funds.loading">
				<div class="limpeed-fund-grid">
					<div class="limpeed-fund-card limpeed-fund-card--green is-static">
						<span class="dashicons dashicons-chart-line limpeed-fund-card-icon-bg"></span>
						<div class="limpeed-fund-card-label"><?php esc_html_e( 'Solde', 'limpeed-immobilier' ); ?></div>
						<div class="limpeed-fund-card-amount" x-text="funds.totalLabel"></div>
					</div>
					<template x-for="item in funds.items" :key="item.key">
						<button type="button" class="limpeed-fund-card" :class="'limpeed-fund-card--' + item.color" @click="openDrawer(item)">
							<span class="dashicons dashicons-money limpeed-fund-card-icon-bg"></span>
							<span class="limpeed-fund-card-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></span>
							<div class="limpeed-fund-card-label" x-text="item.label"></div>
							<div class="limpeed-fund-card-amount" x-text="item.balance_label"></div>
						</button>
					</template>
				</div>
			</template>
		</div>
	</template>

	<!-- Panneau de détail (drawer) d'une caisse -->
	<template x-if="drawer.open">
		<div>
			<div class="limpeed-app-drawer-overlay" @click="closeDrawer()"></div>
			<div class="limpeed-app-drawer" @keydown.escape.window="closeDrawer()">
				<div class="limpeed-app-drawer-header">
					<div>
						<h2 x-text="drawer.label"></h2>
						<div class="limpeed-app-drawer-subtitle" x-text="drawer.balanceLabel"></div>
					</div>
					<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<div class="limpeed-app-drawer-body">
					<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

					<template x-if="!drawer.loading">
						<div>
							<p x-show="drawer.items.length === 0"><?php esc_html_e( 'Aucun mouvement enregistré.', 'limpeed-immobilier' ); ?></p>
							<template x-for="row in drawer.items" :key="row.id">
								<div class="limpeed-app-drawer-list-item">
									<span>
										<span x-text="row.transaction_date"></span> —
										<span :style="{ color: row.direction === 'out' ? 'var(--limpeed-danger)' : 'var(--limpeed-primary)' }" x-text="(row.direction === 'out' ? '-' : '+') + row.amount_label"></span>
										<template x-if="row.label"><span> (<span x-text="row.label"></span>)</span></template>
									</span>
									<button type="button" class="limpeed-app-link-btn is-danger" @click="deleteTransaction(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
								</div>
							</template>

							<div class="limpeed-app-pagination" x-show="drawer.totalPages > 1" x-cloak>
								<template x-for="p in drawer.totalPages" :key="p">
									<a href="#" @click.prevent="goToDrawerPage(p)" :class="{ 'is-active': p === drawer.paged }" x-text="p"></a>
								</template>
							</div>

							<form @submit.prevent="addTransaction()" style="margin-top: 16px;">
								<div class="limpeed-app-notice limpeed-app-notice-error" x-show="form.errors.length">
									<ul>
										<template x-for="(error, index) in form.errors" :key="index">
											<li x-text="error"></li>
										</template>
									</ul>
								</div>
								<div class="limpeed-app-modal-grid">
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Sens', 'limpeed-immobilier' ); ?></label>
										<select x-model="form.direction">
											<option value="in"><?php esc_html_e( 'Entrée', 'limpeed-immobilier' ); ?></option>
											<option value="out"><?php esc_html_e( 'Sortie', 'limpeed-immobilier' ); ?></option>
										</select>
									</div>
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Montant (FCFA)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
										<input type="number" step="1" min="0" x-model="form.amount" required>
									</div>
								</div>
								<div class="limpeed-app-modal-grid">
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
										<input type="date" x-model="form.transaction_date" required>
									</div>
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Libellé (optionnel)', 'limpeed-immobilier' ); ?></label>
										<input type="text" x-model="form.label">
									</div>
								</div>
								<button type="submit" class="limpeed-app-btn" :disabled="form.saving"><?php esc_html_e( 'Ajouter le mouvement', 'limpeed-immobilier' ); ?></button>
							</form>
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

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher les caisses.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* treasury-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/treasury-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
