<?php
/**
 * Plugin Name: Limpeed Immobilier - Gestion Locative
 * Plugin URI: https://limpeed-immobilier.com
 * Description: Plugin de gestion locative pour Limpeed Immobilier : biens, propriétaires, locataires, paiements et bordereaux PDF.
 * Version: 1.3.0
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
define( 'LIMPEED_VERSION', '1.3.0' );
define( 'LIMPEED_DB_VERSION', '1.3.0' );
define( 'LIMPEED_PLUGIN_FILE', __FILE__ );
define( 'LIMPEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIMPEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LIMPEED_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Chargement des classes principales.
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-activator.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-roles.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-owners.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-properties.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-tenants.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-payments.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-activity-log.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-statements.php';
require_once LIMPEED_PLUGIN_DIR . 'includes/class-limpeed-agents.php';

if ( is_admin() ) {
	require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-admin.php';
} else {
	require_once LIMPEED_PLUGIN_DIR . 'public/class-limpeed-frontend.php';
}

/**
 * Hook d'activation : crée les tables et les rôles.
 * Ne supprime et n'écrase jamais les données existantes.
 */
function limpeed_activate_plugin() {
	Limpeed_Activator::activate();
}
register_activation_hook( __FILE__, 'limpeed_activate_plugin' );

/**
 * Hook de désactivation : ne touche JAMAIS aux tables ni aux données.
 * La suppression des données n'a lieu que dans uninstall.php, sur confirmation explicite.
 */
function limpeed_deactivate_plugin() {
	Limpeed_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'limpeed_deactivate_plugin' );

/**
 * Vérifie à chaque chargement si une migration de schéma est nécessaire.
 * Compare l'option limpeed_db_version à LIMPEED_DB_VERSION.
 */
function limpeed_check_db_version() {
	$installed_version = get_option( 'limpeed_db_version', '0' );
	if ( version_compare( $installed_version, LIMPEED_DB_VERSION, '<' ) ) {
		Limpeed_Activator::migrate( $installed_version );
	}
}
add_action( 'plugins_loaded', 'limpeed_check_db_version' );

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
