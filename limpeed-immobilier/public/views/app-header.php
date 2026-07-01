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
	<script>
	// Applique le thème mémorisé avant le rendu de la page pour éviter un
	// flash de thème clair au chargement (l'attribut doit être posé avant
	// que app.css ne soit interprété).
	( function () {
		try {
			var stored = localStorage.getItem( 'limpeedTheme' );
			var theme  = stored || ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' );
			document.documentElement.setAttribute( 'data-theme', theme );
		} catch ( e ) {}
	} )();
	</script>
	<link rel="stylesheet" href="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/app.css' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
</head>
<body class="limpeed-app">
	<div class="limpeed-app-shell">
		<aside class="limpeed-app-sidebar">
			<div class="limpeed-app-logo"><?php echo Limpeed_Frontend::render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balisage statique généré et échappé dans render_logo(). ?></div>
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
					<button type="button" id="limpeed-theme-toggle" class="limpeed-app-theme-toggle" aria-label="<?php esc_attr_e( 'Changer de thème (clair/sombre)', 'limpeed-immobilier' ); ?>" title="<?php esc_attr_e( 'Changer de thème (clair/sombre)', 'limpeed-immobilier' ); ?>">
						<svg class="limpeed-theme-icon limpeed-theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><line x1="12" y1="1.5" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22.5"/><line x1="1.5" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22.5" y2="12"/><line x1="4.5" y1="4.5" x2="6.2" y2="6.2"/><line x1="17.8" y1="17.8" x2="19.5" y2="19.5"/><line x1="4.5" y1="19.5" x2="6.2" y2="17.8"/><line x1="17.8" y1="6.2" x2="19.5" y2="4.5"/></g></svg>
						<svg class="limpeed-theme-icon limpeed-theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1Z"/></svg>
					</button>
					<?php echo get_avatar( $current_user->ID, 32 ); ?>
					<span><?php echo esc_html( $current_user->display_name ); ?></span>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="limpeed-app-logout"><?php esc_html_e( 'Déconnexion', 'limpeed-immobilier' ); ?></a>
				</div>
			</header>
			<main class="limpeed-app-content">
