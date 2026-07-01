<?php
/**
 * Vue : tableau de bord.
 *
 * @var int    $owners_count
 * @var int    $properties_count
 * @var int    $tenants_count
 * @var int    $vacant_count
 * @var string $current_period
 * @var array  $period_summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$unpaid_count = count( $period_summary['unpaid_tenants'] );
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Tableau de bord — Limpeed Immobilier', 'limpeed-immobilier' ); ?></h1>

	<div class="limpeed-dashboard-cards">
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( $owners_count ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Propriétaires', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-owners' ) ); ?>"><?php esc_html_e( 'Voir la liste', 'limpeed-immobilier' ); ?></a>
		</div>
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( $properties_count ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Biens', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-properties' ) ); ?>"><?php esc_html_e( 'Voir la liste', 'limpeed-immobilier' ); ?></a>
		</div>
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( $tenants_count ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Locataires actifs', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-tenants' ) ); ?>"><?php esc_html_e( 'Voir la liste', 'limpeed-immobilier' ); ?></a>
		</div>
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( $vacant_count ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Biens vacants', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-properties&status=vacant' ) ); ?>"><?php esc_html_e( 'Voir la liste', 'limpeed-immobilier' ); ?></a>
		</div>
	</div>

	<h2><?php printf( /* translators: %s: mois en cours, ex. 2026-07 */ esc_html__( 'Paiements — %s', 'limpeed-immobilier' ), esc_html( $current_period ) ); ?></h2>

	<div class="limpeed-dashboard-cards">
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( number_format_i18n( $period_summary['collected'], 2 ) ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Loyers encaissés ce mois', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-payments&period=' . $current_period ) ); ?>"><?php esc_html_e( 'Voir les paiements', 'limpeed-immobilier' ); ?></a>
		</div>
		<div class="limpeed-card">
			<span class="limpeed-card-number"><?php echo esc_html( number_format_i18n( $period_summary['commission'], 2 ) ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Commission agence ce mois', 'limpeed-immobilier' ); ?></span>
		</div>
		<div class="limpeed-card <?php echo $unpaid_count > 0 ? 'limpeed-card-alert' : ''; ?>">
			<span class="limpeed-card-number"><?php echo esc_html( $unpaid_count ); ?></span>
			<span class="limpeed-card-label"><?php esc_html_e( 'Locataires sans paiement ce mois', 'limpeed-immobilier' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-payments&action=add' ) ); ?>"><?php esc_html_e( 'Enregistrer un paiement', 'limpeed-immobilier' ); ?></a>
		</div>
	</div>

	<?php if ( $unpaid_count > 0 ) : ?>
		<h3><?php esc_html_e( 'Locataires actifs sans paiement enregistré ce mois-ci', 'limpeed-immobilier' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Bien', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $period_summary['unpaid_tenants'] as $tenant ) : ?>
					<?php $property = Limpeed_Properties::get( $tenant->property_id ); ?>
					<tr>
						<td><?php echo esc_html( $tenant->full_name ); ?></td>
						<td><?php echo $property ? esc_html( $property->address ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $tenant->rent_amount, 2 ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-payments&action=add&tenant_id=' . $tenant->id ) ); ?>">
								<?php esc_html_e( 'Enregistrer le paiement', 'limpeed-immobilier' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
