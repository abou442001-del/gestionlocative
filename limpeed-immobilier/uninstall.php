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

global $wpdb;

$tables = array(
	$wpdb->prefix . 'limpeed_activity_log',
	$wpdb->prefix . 'limpeed_statements',
	$wpdb->prefix . 'limpeed_payments',
	$wpdb->prefix . 'limpeed_tenants',
	$wpdb->prefix . 'limpeed_properties',
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

require_once plugin_dir_path( __FILE__ ) . 'includes/class-limpeed-roles.php';
Limpeed_Roles::remove_roles();
