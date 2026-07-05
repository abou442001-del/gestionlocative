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

$current_user   = wp_get_current_user();
$logout_url     = wp_logout_url( Limpeed_Frontend::login_url() );
$all_sections   = Limpeed_Frontend::get_sections();
$current_label  = isset( $all_sections[ $active_page ] ) ? $all_sections[ $active_page ]['label'] : '';
$notifications  = Limpeed_Frontend::get_notifications();
$notif_count    = count( $notifications['unpaid_tenants'] ) + count( $notifications['expiring_leases'] );

// Barre de recherche globale : visible si l'utilisateur a accès à au moins
// une des sections consultables (même condition que can_view_lookups côté
// API REST, voir includes/class-limpeed-rest-api.php).
$can_global_search = current_user_can( 'manage_limpeed_tenants' ) || current_user_can( 'manage_limpeed_properties' ) || current_user_can( 'manage_limpeed_payments' );

// Menu "+ Ajouter" : un lien par section à laquelle l'utilisateur peut ajouter
// une entrée, masqué entièrement si aucune n'est accessible.
$quick_add_links = array();
if ( current_user_can( 'manage_limpeed_tenants' ) ) {
	$quick_add_links['tenants'] = array( 'label' => __( 'Locataire', 'limpeed-immobilier' ), 'icon' => 'dashicons-admin-users' );
}
if ( current_user_can( 'manage_limpeed_owners' ) ) {
	$quick_add_links['owners'] = array( 'label' => __( 'Propriétaire', 'limpeed-immobilier' ), 'icon' => 'dashicons-groups' );
}
if ( current_user_can( 'manage_limpeed_properties' ) ) {
	$quick_add_links['buildings']  = array( 'label' => __( 'Édifice', 'limpeed-immobilier' ), 'icon' => 'dashicons-admin-multisite' );
	$quick_add_links['properties'] = array( 'label' => __( 'Bien', 'limpeed-immobilier' ), 'icon' => 'dashicons-building' );
}
if ( current_user_can( 'manage_limpeed_payments' ) ) {
	$quick_add_links['payments'] = array( 'label' => __( 'Paiement', 'limpeed-immobilier' ), 'icon' => 'dashicons-money-alt' );
}

$global_search_rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $current_label ); ?> — <?php esc_html_e( 'Limpeed Immobilier', 'limpeed-immobilier' ); ?></title>
	<script>
	window.limpeedRest = <?php echo wp_json_encode( $global_search_rest_config ); ?>;
	</script>
	<script>
	// Applique le thème et l'état de la sidebar mémorisés avant le rendu de
	// la page pour éviter un flash au chargement (les attributs doivent être
	// posés avant que app.css ne soit interprété).
	( function () {
		try {
			var stored = localStorage.getItem( 'limpeedTheme' );
			var theme  = stored || ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' );
			document.documentElement.setAttribute( 'data-theme', theme );
			if ( 'true' === localStorage.getItem( 'limpeedSidebarCollapsed' ) ) {
				document.documentElement.setAttribute( 'data-sidebar', 'collapsed' );
			}
		} catch ( e ) {}
	} )();
	</script>
	<link rel="stylesheet" href="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/app.css' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
</head>
<body class="limpeed-app">
	<header class="limpeed-app-topbar">
		<div class="limpeed-app-topbar-left">
			<button type="button" id="limpeed-sidebar-toggle" class="limpeed-app-hamburger" aria-label="<?php esc_attr_e( 'Réduire/agrandir le menu', 'limpeed-immobilier' ); ?>" title="<?php esc_attr_e( 'Réduire/agrandir le menu', 'limpeed-immobilier' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="13" y2="18"/></g></svg>
			</button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'dashboard' ) ); ?>" class="limpeed-app-logo-box"><?php echo Limpeed_Frontend::render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balisage statique généré et échappé dans render_logo(). ?></a>
		</div>
		<?php if ( $can_global_search ) : ?>
			<div class="limpeed-app-topbar-search">
				<div class="limpeed-app-global-search">
					<span class="dashicons dashicons-search limpeed-app-global-search-icon"></span>
					<input type="text" id="limpeed-global-search-input" autocomplete="off" placeholder="<?php esc_attr_e( 'Rechercher un locataire, propriétaire, édifice, bien...', 'limpeed-immobilier' ); ?>">
					<div class="limpeed-app-global-search-results" id="limpeed-global-search-results"></div>
				</div>
			</div>
		<?php endif; ?>
		<div class="limpeed-app-topbar-right">
			<?php if ( ! empty( $quick_add_links ) ) : ?>
				<div class="limpeed-app-user-menu">
					<button type="button" id="limpeed-quick-add-toggle" class="limpeed-app-quick-add-toggle" aria-label="<?php esc_attr_e( 'Ajouter', 'limpeed-immobilier' ); ?>" title="<?php esc_attr_e( 'Ajouter', 'limpeed-immobilier' ); ?>">
						<span class="dashicons dashicons-plus-alt2"></span>
					</button>
					<div class="limpeed-app-user-menu-panel" id="limpeed-quick-add-panel">
						<div class="limpeed-app-user-menu-header"><?php esc_html_e( 'Ajouter', 'limpeed-immobilier' ); ?></div>
						<?php foreach ( $quick_add_links as $section_key => $link ) : ?>
							<a href="<?php echo esc_url( Limpeed_Frontend::app_url( $section_key, array( 'action' => 'add' ) ) ); ?>">
								<span class="dashicons <?php echo esc_attr( $link['icon'] ); ?>"></span> <?php echo esc_html( $link['label'] ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( current_user_can( 'manage_limpeed_payments' ) || current_user_can( 'manage_limpeed_tenants' ) ) : ?>
			<div class="limpeed-app-user-menu">
				<button type="button" id="limpeed-notif-toggle" class="limpeed-app-notif-toggle" aria-label="<?php esc_attr_e( 'Notifications', 'limpeed-immobilier' ); ?>" title="<?php esc_attr_e( 'Notifications', 'limpeed-immobilier' ); ?>">
					<span class="dashicons dashicons-bell"></span>
					<?php if ( $notif_count > 0 ) : ?>
						<span class="limpeed-app-notif-badge"><?php echo esc_html( $notif_count > 99 ? '99+' : $notif_count ); ?></span>
					<?php endif; ?>
				</button>
				<div class="limpeed-app-user-menu-panel" id="limpeed-notif-panel">
					<div class="limpeed-app-user-menu-header"><?php esc_html_e( 'Notifications', 'limpeed-immobilier' ); ?></div>
					<?php if ( 0 === $notif_count ) : ?>
						<p class="limpeed-app-notif-empty"><?php esc_html_e( 'Aucune alerte pour le moment.', 'limpeed-immobilier' ); ?></p>
					<?php endif; ?>
					<?php if ( $notifications['unpaid_tenants'] ) : ?>
						<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments' ) ); ?>">
							<span class="dashicons dashicons-warning"></span>
							<?php
							printf(
								/* translators: %d: nombre de locataires en retard */
								esc_html( _n( '%d locataire en retard de paiement', '%d locataires en retard de paiement', count( $notifications['unpaid_tenants'] ), 'limpeed-immobilier' ) ),
								count( $notifications['unpaid_tenants'] )
							);
							?>
						</a>
					<?php endif; ?>
					<?php if ( $notifications['expiring_leases'] ) : ?>
						<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants' ) ); ?>">
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php
							printf(
								/* translators: %d: nombre de baux */
								esc_html( _n( '%d bail expirant sous 30 jours', '%d baux expirant sous 30 jours', count( $notifications['expiring_leases'] ), 'limpeed-immobilier' ) ),
								count( $notifications['expiring_leases'] )
							);
							?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
			<button type="button" id="limpeed-theme-toggle" class="limpeed-app-theme-toggle" aria-label="<?php esc_attr_e( 'Changer de thème (clair/sombre)', 'limpeed-immobilier' ); ?>" title="<?php esc_attr_e( 'Changer de thème (clair/sombre)', 'limpeed-immobilier' ); ?>">
				<svg class="limpeed-theme-icon limpeed-theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><line x1="12" y1="1.5" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22.5"/><line x1="1.5" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22.5" y2="12"/><line x1="4.5" y1="4.5" x2="6.2" y2="6.2"/><line x1="17.8" y1="17.8" x2="19.5" y2="19.5"/><line x1="4.5" y1="19.5" x2="6.2" y2="17.8"/><line x1="17.8" y1="6.2" x2="19.5" y2="4.5"/></g></svg>
				<svg class="limpeed-theme-icon limpeed-theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1Z"/></svg>
			</button>
			<div class="limpeed-app-user-menu">
				<button type="button" id="limpeed-user-menu-toggle" class="limpeed-app-user-menu-toggle">
					<?php echo get_avatar( $current_user->ID, 32 ); ?>
					<span class="limpeed-app-user-menu-name"><?php echo esc_html( $current_user->display_name ); ?></span>
					<svg viewBox="0 0 24 24" width="14" height="14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
				</button>
				<div class="limpeed-app-user-menu-panel" id="limpeed-user-menu-panel">
					<div class="limpeed-app-user-menu-header"><?php echo esc_html( $current_user->display_name ); ?></div>
					<?php if ( current_user_can( 'manage_limpeed_agents' ) ) : ?>
						<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'settings' ) ); ?>"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Réglages', 'limpeed-immobilier' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $logout_url ); ?>"><span class="dashicons dashicons-migrate"></span> <?php esc_html_e( 'Déconnexion', 'limpeed-immobilier' ); ?></a>
				</div>
			</div>
		</div>
	</header>
	<div class="limpeed-app-shell">
		<aside class="limpeed-app-sidebar">
			<nav class="limpeed-app-nav">
				<?php $current_group = null; ?>
				<?php foreach ( $all_sections as $key => $item ) : ?>
					<?php if ( ! current_user_can( $item['cap'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php if ( ! empty( $item['group'] ) && $item['group'] !== $current_group ) : ?>
						<?php $current_group = $item['group']; ?>
						<div class="limpeed-app-nav-group-label"><?php echo esc_html( $current_group ); ?></div>
					<?php endif; ?>
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( $key ) ); ?>" class="limpeed-app-nav-item<?php echo $active_page === $key ? ' is-active' : ''; ?>" title="<?php echo esc_attr( $item['label'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
						<span class="limpeed-app-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<div class="limpeed-app-main">
			<main class="limpeed-app-content">
				<h1 class="limpeed-app-page-title"><?php echo esc_html( $current_label ); ?></h1>
