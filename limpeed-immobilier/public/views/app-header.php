<?php
/**
 * Shell "application" frontend réutilisable : barre du haut + menu latéral.
 * Page HTML autonome, indépendante du thème WordPress actif.
 *
 * @var string $active_page Identifiant de la page active (pour surligner le menu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$logout_url   = wp_logout_url( Limpeed_Frontend::login_url() );

$nav_items = array(
	'dashboard'  => array(
		'label' => __( 'Tableau de bord', 'limpeed-immobilier' ),
		'url'   => get_permalink( Limpeed_Frontend::dashboard_page_id() ),
		'icon'  => 'dashicons-chart-bar',
	),
	'owners'     => array(
		'label' => __( 'Propriétaires', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-owners' ),
		'icon'  => 'dashicons-groups',
	),
	'buildings'  => array(
		'label' => __( 'Édifices', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-buildings' ),
		'icon'  => 'dashicons-admin-multisite',
	),
	'properties' => array(
		'label' => __( 'Biens', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-properties' ),
		'icon'  => 'dashicons-building',
	),
	'tenants'    => array(
		'label' => __( 'Locataires', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-tenants' ),
		'icon'  => 'dashicons-admin-users',
	),
	'payments'   => array(
		'label' => __( 'Paiements', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-payments' ),
		'icon'  => 'dashicons-money-alt',
	),
	'statements' => array(
		'label' => __( 'Bordereaux', 'limpeed-immobilier' ),
		'url'   => admin_url( 'admin.php?page=limpeed-statements' ),
		'icon'  => 'dashicons-media-document',
	),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Tableau de bord — Limpeed Immobilier', 'limpeed-immobilier' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/app.css' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
</head>
<body class="limpeed-app">
	<div class="limpeed-app-shell">
		<aside class="limpeed-app-sidebar">
			<div class="limpeed-app-logo">Limpeed<span>Immobilier</span></div>
			<nav class="limpeed-app-nav">
				<?php foreach ( $nav_items as $key => $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="limpeed-app-nav-item<?php echo $active_page === $key ? ' is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
						<span class="limpeed-app-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<div class="limpeed-app-main">
			<header class="limpeed-app-topbar">
				<div class="limpeed-app-topbar-title"><?php esc_html_e( 'Tableau de bord', 'limpeed-immobilier' ); ?></div>
				<div class="limpeed-app-topbar-user">
					<?php echo get_avatar( $current_user->ID, 32 ); ?>
					<span><?php echo esc_html( $current_user->display_name ); ?></span>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="limpeed-app-logout"><?php esc_html_e( 'Déconnexion', 'limpeed-immobilier' ); ?></a>
				</div>
			</header>
			<main class="limpeed-app-content">
