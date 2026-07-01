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
		self::create_pages();
		self::backfill_default_buildings();
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

		// Crée les pages frontend (connexion/inscription) si elles n'existent pas déjà.
		// Idempotent : ne recrée jamais une page déjà présente.
		self::create_pages();

		// Rattache les biens existants créés avant l'introduction des édifices
		// à un édifice par défaut, sans jamais perdre de données. Idempotent :
		// ne traite que les biens dont building_id est encore vide.
		self::backfill_default_buildings();

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
	 * Crée les pages frontend "Connexion" et "Inscription" si elles n'existent pas
	 * déjà (idempotent : ne recrée jamais une page existante, ne supprime rien).
	 */
	public static function create_pages() {
		self::create_page_if_missing(
			'limpeed_login_page_id',
			__( 'Connexion Agent', 'limpeed-immobilier' ),
			'[limpeed_login]'
		);

		self::create_page_if_missing(
			'limpeed_register_page_id',
			__( 'Inscription Agent', 'limpeed-immobilier' ),
			'[limpeed_register]'
		);
	}

	/**
	 * Crée une page contenant le shortcode donné si l'option ne pointe pas déjà
	 * vers une page existante.
	 *
	 * @param string $option_name Option stockant l'id de la page.
	 * @param string $title       Titre de la page à créer.
	 * @param string $shortcode   Contenu (shortcode) de la page.
	 */
	private static function create_page_if_missing( $option_name, $title, $shortcode ) {
		$page_id = (int) get_option( $option_name );

		if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( $option_name, $page_id );
		}
	}

	/**
	 * Crée un édifice par défaut pour chaque propriétaire ayant encore des biens
	 * sans édifice assigné (biens créés avant l'introduction des édifices), et
	 * y rattache ces biens. N'écrase et ne supprime jamais de données existantes.
	 */
	public static function backfill_default_buildings() {
		global $wpdb;

		$properties_table = $wpdb->prefix . 'limpeed_properties';
		$buildings_table   = $wpdb->prefix . 'limpeed_buildings';
		$owners_table      = $wpdb->prefix . 'limpeed_owners';

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$buildings_table}'" ) ) {
			return;
		}

		$owner_ids = $wpdb->get_col(
			"SELECT DISTINCT owner_id FROM {$properties_table} WHERE building_id IS NULL OR building_id = 0"
		);

		foreach ( $owner_ids as $owner_id ) {
			$owner_id = (int) $owner_id;
			$owner    = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$owners_table} WHERE id = %d", $owner_id ) );

			// Un owner_id introuvable indique des données déjà incohérentes
			// (ne devrait pas arriver via l'usage normal du plugin) : on laisse
			// ces biens orphelins plutôt que de créer un édifice sans propriétaire réel.
			if ( ! $owner ) {
				continue;
			}

			$inserted = $wpdb->insert(
				$buildings_table,
				array(
					'owner_id'   => $owner_id,
					'name'       => sprintf( __( 'Bâtiment principal de %s', 'limpeed-immobilier' ), $owner->full_name ),
					'address'    => '',
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

			if ( ! $inserted ) {
				continue;
			}

			$building_id = (int) $wpdb->insert_id;

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$properties_table} SET building_id = %d WHERE owner_id = %d AND (building_id IS NULL OR building_id = 0)",
					$building_id,
					$owner_id
				)
			);
		}
	}

	/**
	 * Crée ou met à jour les tables du plugin via dbDelta().
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$owners_table     = $wpdb->prefix . 'limpeed_owners';
		$buildings_table  = $wpdb->prefix . 'limpeed_buildings';
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

		$sql_buildings = "CREATE TABLE {$buildings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			address TEXT NULL,
			description TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY owner_id (owner_id)
		) {$charset_collate};";

		$sql_properties = "CREATE TABLE {$properties_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL,
			building_id BIGINT UNSIGNED NULL,
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
			KEY building_id (building_id),
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
		dbDelta( $sql_buildings );
		dbDelta( $sql_properties );
		dbDelta( $sql_tenants );
		dbDelta( $sql_payments );
		dbDelta( $sql_statements );
		dbDelta( $sql_activity_log );
	}
}
