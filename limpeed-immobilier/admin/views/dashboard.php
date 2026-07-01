<?php
/**
 * Vue : tableau de bord.
 * Les indicateurs détaillés (paiements, impayés) arriveront en Phase 2.
 *
 * @var int $owners_count
 * @var int $properties_count
 * @var int $tenants_count
 * @var int $vacant_count
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

	<p class="description">
		<?php esc_html_e( 'Le suivi des paiements et les indicateurs financiers (loyers encaissés, impayés) seront disponibles en Phase 2.', 'limpeed-immobilier' ); ?>
	</p>
</div>
