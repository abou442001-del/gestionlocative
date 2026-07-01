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
$all_sections = Limpeed_Frontend::get_sections();
$current_label = isset( $all_sections[ $active_page ] ) ? $all_sections[ $active_page ]['label'] : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $current_label ); ?> — <?php esc_html_e( 'Limpeed Immobilier', 'limpeed-immobilier' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/app.css' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
</head>
<body class="limpeed-app">
	<div class="limpeed-app-shell">
		<aside class="limpeed-app-sidebar">
			<div class="limpeed-app-logo">Limpeed<span>Immobilier</span></div>
			<nav class="limpeed-app-nav">
				<?php foreach ( $all_sections as $key => $item ) : ?>
					<?php if ( ! current_user_can( $item['cap'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( $key ) ); ?>" class="limpeed-app-nav-item<?php echo $active_page === $key ? ' is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
						<span class="limpeed-app-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<div class="limpeed-app-main">
			<header class="limpeed-app-topbar">
				<div class="limpeed-app-topbar-title"><?php echo esc_html( $current_label ); ?></div>
				<div class="limpeed-app-topbar-user">
					<?php echo get_avatar( $current_user->ID, 32 ); ?>
					<span><?php echo esc_html( $current_user->display_name ); ?></span>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="limpeed-app-logout"><?php esc_html_e( 'Déconnexion', 'limpeed-immobilier' ); ?></a>
				</div>
			</header>
			<main class="limpeed-app-content">
