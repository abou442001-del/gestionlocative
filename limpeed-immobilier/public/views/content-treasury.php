<?php
/**
 * Contenu frontend de la section "Trésorerie" : vue de lecture seule
 * (aucun CRUD), rendue directement côté serveur comme le graphique de
 * recouvrement du tableau de bord — pas besoin d'Ajax pour un rapport qui
 * n'a pas d'action à déclencher.
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
?>

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
