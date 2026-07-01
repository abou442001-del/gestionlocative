<?php
/**
 * Point d'entrée de l'application frontend (chargé via template_include, en
 * dehors du thème WordPress actif — voir Limpeed_Frontend::maybe_load_dashboard_template()).
 *
 * Route vers le contenu de la section demandée (paramètre limpeed_view).
 *
 * L'accès est normalement déjà restreint par Limpeed_Frontend::restrict_dashboard_access()
 * (hook template_redirect, exécuté avant ce fichier). La vérification ci-dessous
 * est une seconde barrière : aucune section ne doit jamais afficher de données
 * sans revalider elle-même les droits d'accès.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_page = Limpeed_Frontend::current_view();
$sections    = Limpeed_Frontend::get_sections();

if ( ! is_user_logged_in() || get_user_meta( get_current_user_id(), Limpeed_Agents::PENDING_META_KEY, true ) || ! current_user_can( $sections[ $active_page ]['cap'] ) ) {
	wp_safe_redirect( Limpeed_Frontend::login_url() );
	exit;
}

$content_file = LIMPEED_PLUGIN_DIR . 'public/views/content-' . $active_page . '.php';

include LIMPEED_PLUGIN_DIR . 'public/views/app-header.php';
include $content_file;
include LIMPEED_PLUGIN_DIR . 'public/views/app-footer.php';
