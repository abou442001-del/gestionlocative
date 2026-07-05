<?php
/**
 * Plugin Name: Limpeed Immobilier - Gestion Locative
 * Plugin URI: https://limpeed-immobilier.com
 * Description: Plugin de gestion locative pour Limpeed Immobilier : biens, propriétaires, locataires, paiements et bordereaux PDF.
 * Version: 1.28.0
 * Author: Limpeed Immobilier
 * Text Domain: limpeed-immobilier
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accès direct interdit.
}

// Constantes du plugin.
define( 'LIMPEED_VERSION', '1.52.0' );
define( 'LIMPEED_DB_VERSION', '1.20.0' );
define( 'LIMPEED_PLUGIN_FILE', __FILE__ );
define( 'LIMPEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIMPEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LIMPEED_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Chargement des classes principales.
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-encryption.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-login-guard.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-activator.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-roles.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-owners.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-buildings.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-properties.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-tenants.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-payments.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-activity-log.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-statements.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-agents.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-branding.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-mandates.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-lease-amendments.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-inspections.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-contracts.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-treasury.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-documents.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-expenses.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-accounting.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-funds.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-work-requests.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-reminders.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-export.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-rest-api.php';

if ( is_admin() ) {
	require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-admin.php';
} else {
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-owners.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-buildings.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-properties.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-tenants.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-mandates.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-inspections.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-documents.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-accounting.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-payments.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-statements.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-agents.php';
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend-settings.php';
}

/**
 * Hook d'activation : crée les tables et les rôles.
 * Ne supprime et n'écrase jamais les données existantes.
 */
function limpeed_activate_plugin() {
	Limpeed_Activator::activate();
	Limpeed_Reminders::schedule();
}
register_activation_hook( __FILE__, 'limpeed_activate_plugin' );

/**
 * Hook de désactivation : ne touche JAMAIS aux tables ni aux données.
 * La suppression des données n'a lieu que dans uninstall.php, sur confirmation explicite.
 */
function limpeed_deactivate_plugin() {
	Limpeed_Activator::deactivate();
	Limpeed_Reminders::unschedule();
}
register_deactivation_hook( __FILE__, 'limpeed_deactivate_plugin' );

/**
 * Rappel quotidien des loyers en retard : planifié à l'activation, mais on
 * s'assure ici qu'il l'est aussi pour les installations déjà actives lors de
 * la mise à jour du plugin (register_activation_hook ne se redéclenche pas
 * pour une mise à jour sans désactivation/réactivation).
 */
add_action( 'init', array( 'Limpeed_Reminders', 'schedule' ) );
add_action( Limpeed_Reminders::CRON_HOOK, array( 'Limpeed_Reminders', 'send_daily_reminder' ) );

/**
 * Vérifie à chaque chargement si une migration de schéma est nécessaire.
 * Compare l'option limpeed_db_version à LIMPEED_DB_VERSION.
 *
 * Exécuté sur 'init' plutôt que 'plugins_loaded' : la migration peut créer des
 * pages (wp_insert_post), ce qui nécessite $wp_rewrite, initialisé par WordPress
 * seulement après que 'plugins_loaded' se soit déclenché. L'appeler trop tôt
 * provoque une erreur fatale sur le tout premier chargement suivant une mise
 * à jour du plugin.
 */
function limpeed_check_db_version() {
	$installed_version = get_option( 'limpeed_db_version', '0' );
	if ( version_compare( $installed_version, LIMPEED_DB_VERSION, '<' ) ) {
		Limpeed_Activator::migrate( $installed_version );
	}
}
add_action( 'init', 'limpeed_check_db_version', 5 );

/**
 * Initialise l'API REST (limpeed/v1), indépendamment du contexte admin/frontend
 * puisque les requêtes REST ne passent pas par is_admin().
 */
function limpeed_init_rest_api() {
	$rest_api = new Limpeed_Rest_Api();
	$rest_api->init();
}
add_action( 'plugins_loaded', 'limpeed_init_rest_api' );

/**
 * Initialise la protection anti-brute-force sur la connexion, indépendamment
 * du contexte admin/frontend puisqu'elle doit couvrir wp-login.php comme les
 * formulaires du plugin.
 */
add_action( 'plugins_loaded', array( 'Limpeed_Login_Guard', 'init' ) );

/**
 * Initialise les pages d'administration.
 */
function limpeed_init_admin() {
	if ( is_admin() ) {
		$admin = new Limpeed_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'limpeed_init_admin' );

/**
 * Initialise les pages frontend (connexion / inscription des agents).
 */
function limpeed_init_frontend() {
	if ( ! is_admin() ) {
		$frontend = new Limpeed_Frontend();
		$frontend->init();
	}
}
add_action( 'plugins_loaded', 'limpeed_init_frontend' );
