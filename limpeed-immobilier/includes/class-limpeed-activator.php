<?php
/**
 * Création et migration des tables SQL du plugin.
 *
 * Règle d'or : jamais de DROP TABLE, jamais de perte de données.
 * dbDelta() ajoute/modifie les colonnes et index sans toucher aux lignes existantes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Activator {

	/**
	 * Exécuté à l'activation du plugin.
	 */
	public static function activate() {
		self::create_tables();
		Limpeed_Roles::add_roles();
		update_option( 'limpeed_db_version', LIMPEED_DB_VERSION );
		update_option( 'limpeed_version', LIMPEED_VERSION );
	}

	/**
	 * Exécuté à la désactivation du plugin.
	 * Ne supprime JAMAIS de tables ni de données : la désactivation est réversible.
	 */
	public static function deactivate() {
		// Volontairement vide : aucune donnée ni table n'est supprimée à la désactivation.
	}

	/**
	 * Migration du schéma de base de données.
	 * Appelée quand limpeed_db_version < LIMPEED_DB_VERSION.
	 * Chaque étape est non destructive : dbDelta() se charge d'ajouter/modifier
	 * les colonnes sans jamais supprimer de données existantes.
	 *
	 * @param string $from_version Version du schéma actuellement installée.
	 */
	public static function migrate( $from_version ) {
		// Recréer/mettre à jour la structure des tables via dbDelta (idempotent et non destructif).
		self::create_tables();

		// Emplacement réservé pour d'éventuelles migrations de données spécifiques
		// entre versions (ex: renommage de valeurs, backfill de colonnes).
		// Exemple :
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) {
		//     self::migrate_to_1_1_0();
		// }

		update_option( 'limpeed_db_version', LIMPEED_DB_VERSION );
		update_option( 'limpeed_version', LIMPEED_VERSION );
	}

	/**
	 * Crée ou met à jour les tables du plugin via dbDelta().
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$owners_table     = $wpdb->prefix . 'limpeed_owners';
		$properties_table = $wpdb->prefix . 'limpeed_properties';
		$tenants_table    = $wpdb->prefix . 'limpeed_tenants';

		$sql_owners = "CREATE TABLE {$owners_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			full_name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			email VARCHAR(191) NULL,
			address TEXT NULL,
			bank_details TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY full_name (full_name)
		) {$charset_collate};";

		$sql_properties = "CREATE TABLE {$properties_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL,
			address TEXT NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'appartement',
			monthly_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
			charges DECIMAL(12,2) NOT NULL DEFAULT 0,
			deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'vacant',
			created_by BIGINT UNSIGNED NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY owner_id (owner_id),
			KEY status (status)
		) {$charset_collate};";

		$sql_tenants = "CREATE TABLE {$tenants_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			property_id BIGINT UNSIGNED NOT NULL,
			full_name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			email VARCHAR(191) NULL,
			lease_start DATE NULL,
			lease_end DATE NULL,
			rent_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			deposit_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'actif',
			created_by BIGINT UNSIGNED NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY property_id (property_id),
			KEY status (status)
		) {$charset_collate};";

		$payments_table = $wpdb->prefix . 'limpeed_payments';

		$sql_payments = "CREATE TABLE {$payments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			payment_date DATE NULL,
			period VARCHAR(7) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'paye',
			payment_method VARCHAR(50) NOT NULL DEFAULT 'especes',
			commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY property_id (property_id),
			KEY period (period),
			KEY status (status)
		) {$charset_collate};";

		$statements_table = $wpdb->prefix . 'limpeed_statements';

		$sql_statements = "CREATE TABLE {$statements_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL,
			period_start VARCHAR(7) NOT NULL,
			period_end VARCHAR(7) NOT NULL,
			total_collected DECIMAL(12,2) NOT NULL DEFAULT 0,
			total_commission DECIMAL(12,2) NOT NULL DEFAULT 0,
			net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			file_path VARCHAR(255) NOT NULL,
			generated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY owner_id (owner_id)
		) {$charset_collate};";

		$activity_log_table = $wpdb->prefix . 'limpeed_activity_log';

		$sql_activity_log = "CREATE TABLE {$activity_log_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			action VARCHAR(20) NOT NULL,
			object_type VARCHAR(50) NOT NULL,
			object_id BIGINT UNSIGNED NULL,
			description TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY object_type (object_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_owners );
		dbDelta( $sql_properties );
		dbDelta( $sql_tenants );
		dbDelta( $sql_payments );
		dbDelta( $sql_statements );
		dbDelta( $sql_activity_log );
	}
}
