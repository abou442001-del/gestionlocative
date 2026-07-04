<?php
/**
 * Désinstallation du plugin.
 *
 * Les tables et données du plugin ne sont supprimées QUE si l'utilisateur a
 * explicitement coché la case de confirmation dans Réglages > Limpeed Immobilier
 * (option `limpeed_confirm_data_deletion` = '1'). Sans cette confirmation
 * explicite, la désinstallation ne touche à aucune table ni donnée.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$confirmed = '1' === get_option( 'limpeed_confirm_data_deletion' );

if ( ! $confirmed ) {
	return;
}

wp_clear_scheduled_hook( 'limpeed_daily_late_payment_reminder' );

global $wpdb;

$tables = array(
	$wpdb->prefix . 'limpeed_activity_log',
	$wpdb->prefix . 'limpeed_statements',
	$wpdb->prefix . 'limpeed_payments',
	$wpdb->prefix . 'limpeed_tenants',
	$wpdb->prefix . 'limpeed_properties',
	$wpdb->prefix . 'limpeed_buildings',
	$wpdb->prefix . 'limpeed_owners',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'limpeed_db_version' );
delete_option( 'limpeed_version' );
delete_option( 'limpeed_confirm_data_deletion' );

$upload_dir      = wp_upload_dir();
$statements_dir  = trailingslashit( $upload_dir['basedir'] ) . 'limpeed-statements';
if ( is_dir( $statements_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $statements_dir, true );
	}
}

// Supprime les pages frontend créées automatiquement (connexion / inscription).
foreach ( array( 'limpeed_login_page_id', 'limpeed_register_page_id', 'limpeed_dashboard_page_id' ) as $page_option ) {
	$page_id = (int) get_option( $page_option );
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
	delete_option( $page_option );
}

// Supprime les demandes d'inscription encore en attente (aucune capacité, aucune
// donnée métier n'a pu être créée par ces comptes).
$pending_users = get_users(
	array(
		'meta_key'   => 'limpeed_pending_approval', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'fields'     => 'ID',
	)
);
if ( $pending_users ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $pending_users as $pending_user_id ) {
		wp_delete_user( $pending_user_id );
	}
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-limpeed-roles.php';
Limpeed_Roles::remove_roles();
