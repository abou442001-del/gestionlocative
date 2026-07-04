<?php
/**
 * CRUD pour les paiements (wp_limpeed_payments).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Payments {

	/**
	 * Formate un montant en devise FCFA pour l'affichage.
	 *
	 * @param float $amount
	 * @param int   $decimals
	 * @return string
	 */
	public static function format_amount( $amount, $decimals = 0 ) {
		/* translators: %s: montant formaté */
		return sprintf( __( '%s FCFA', 'limpeed-immobilier' ), number_format_i18n( (float) $amount, $decimals ) );
	}

	/**
	 * Statuts possibles d'un paiement.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'paye'      => __( 'Payé', 'limpeed-immobilier' ),
			'partiel'   => __( 'Partiel', 'limpeed-immobilier' ),
			'en_retard' => __( 'En retard', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Modes de paiement disponibles.
	 *
	 * @return array
	 */
	public static function get_payment_methods() {
		return array(
			'especes'      => __( 'Espèces', 'limpeed-immobilier' ),
			'virement'     => __( 'Virement bancaire', 'limpeed-immobilier' ),
			'mobile_money' => __( 'Mobile money', 'limpeed-immobilier' ),
			'cheque'       => __( 'Chèque', 'limpeed-immobilier' ),
			'autre'        => __( 'Autre', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_payments';
	}

	/**
	 * Période (mois) en cours au format YYYY-MM.
	 *
	 * @return string
	 */
	public static function get_current_period() {
		return current_time( 'Y-m' );
	}

	/**
	 * Récupère un paiement par son id.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Récupère la liste des paiements avec filtres et pagination.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'search'      => '',
			'tenant_id'   => 0,
			'property_id' => 0,
			'period'      => '',
			'status'      => '',
			'orderby'     => 'payment_date',
			'order'       => 'DESC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'payment_date', 'period', 'amount', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'payment_date';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$tenants_table = Limpeed_Tenants::table();
			$where        .= " AND tenant_id IN ( SELECT id FROM {$tenants_table} WHERE full_name LIKE %s )";
			$params[]      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! empty( $args['tenant_id'] ) ) {
			$where   .= ' AND tenant_id = %d';
			$params[] = (int) $args['tenant_id'];
		}

		if ( ! empty( $args['property_id'] ) ) {
			$where   .= ' AND property_id = %d';
			$params[] = (int) $args['property_id'];
		}

		if ( ! empty( $args['period'] ) ) {
			$where   .= ' AND period = %s';
			$params[] = sanitize_text_field( $args['period'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de paiements correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$tenants_table = Limpeed_Tenants::table();
			$where        .= " AND tenant_id IN ( SELECT id FROM {$tenants_table} WHERE full_name LIKE %s )";
			$params[]      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! empty( $args['tenant_id'] ) ) {
			$where   .= ' AND tenant_id = %d';
			$params[] = (int) $args['tenant_id'];
		}

		if ( ! empty( $args['property_id'] ) ) {
			$where   .= ' AND property_id = %d';
			$params[] = (int) $args['property_id'];
		}

		if ( ! empty( $args['period'] ) ) {
			$where   .= ' AND period = %s';
			$params[] = sanitize_text_field( $args['period'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Indicateurs financiers pour une période donnée (tableau de bord).
	 *
	 * @param string $period Format YYYY-MM. Par défaut le mois en cours.
	 * @return array {
	 *     @type float $collected      Total encaissé (payé + partiel) sur la période.
	 *     @type float $commission     Total des commissions prélevées sur la période.
	 *     @type array $unpaid_tenants Locataires actifs sans paiement "payé" enregistré sur la période.
	 * }
	 */
	public static function get_period_summary( $period = '' ) {
		global $wpdb;

		$period = $period ? $period : self::get_current_period();
		$table  = self::table();

		$collected = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table} WHERE period = %s AND status IN ('paye','partiel')",
				$period
			)
		);

		$commission = $wpdb->get_var(
			$wpdb->prepare( "SELECT SUM(commission_amount) FROM {$table} WHERE period = %s", $period )
		);

		$tenants_table  = Limpeed_Tenants::table();
		$active_tenants = $wpdb->get_results( "SELECT * FROM {$tenants_table} WHERE status = 'actif'" );

		// Une seule requête pour récupérer les locataires déjà payés sur la période,
		// plutôt qu'une requête par locataire (évite un N+1, sensible dès que
		// get_monthly_summary() appelle cette méthode plusieurs fois de suite).
		$paid_tenant_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT tenant_id FROM {$table} WHERE period = %s AND status = 'paye'", $period )
		);
		$paid_tenant_ids = array_map( 'intval', $paid_tenant_ids );

		$unpaid_tenants = array();
		foreach ( $active_tenants as $tenant ) {
			if ( ! in_array( (int) $tenant->id, $paid_tenant_ids, true ) ) {
				$unpaid_tenants[] = $tenant;
			}
		}

		$expected_unpaid = 0.0;
		foreach ( $unpaid_tenants as $tenant ) {
			$expected_unpaid += (float) $tenant->rent_amount;
		}

		return array(
			'period'         => $period,
			'collected'      => $collected ? (float) $collected : 0.0,
			'commission'     => $commission ? (float) $commission : 0.0,
			'unpaid_tenants' => $unpaid_tenants,
			'expected_total' => ( $collected ? (float) $collected : 0.0 ) + $expected_unpaid,
		);
	}

	/**
	 * Version recherchable/paginée de la liste des locataires actifs sans
	 * paiement "payé" sur la période (widget "quittances en attente" du
	 * tableau de bord). Séparée de get_period_summary() : cette dernière est
	 * appelée en boucle par get_monthly_summary() et ne doit pas porter de
	 * logique de pagination/recherche inutile à ces appels.
	 *
	 * @param array $args {
	 *     @type string $search
	 *     @type string $period   Format YYYY-MM. Par défaut le mois en cours.
	 *     @type int    $paged
	 *     @type int    $per_page
	 * }
	 * @return array { @type array $items, @type int $total, @type string $period }
	 */
	public static function get_unpaid_tenants_paged( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'period'   => '',
			'paged'    => 1,
			'per_page' => 10,
		);
		$args   = wp_parse_args( $args, $defaults );
		$period = $args['period'] ? $args['period'] : self::get_current_period();

		$tenants_table = Limpeed_Tenants::table();
		$payments_table = self::table();

		$where  = "WHERE t.status = 'actif' AND t.id NOT IN ( SELECT tenant_id FROM {$payments_table} WHERE period = %s AND status = 'paye' )";
		$params = array( $period );

		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND t.full_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$total_sql = "SELECT COUNT(*) FROM {$tenants_table} t {$where}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) );

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$items_sql = "SELECT t.* FROM {$tenants_table} t {$where} ORDER BY t.full_name ASC LIMIT %d OFFSET %d";
		$items     = $wpdb->get_results( $wpdb->prepare( $items_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

		return array(
			'items'  => $items,
			'total'  => $total,
			'period' => $period,
		);
	}

	/**
	 * Liste complète (non paginée) des locataires actifs sans paiement "payé"
	 * sur la période — utilisée par le rappel automatique quotidien
	 * (Limpeed_Reminders), qui a besoin de la liste entière plutôt que d'une
	 * page pour composer l'email récapitulatif.
	 *
	 * @param string $period Format YYYY-MM. Par défaut le mois en cours.
	 * @return array
	 */
	public static function get_unpaid_tenants( $period = '' ) {
		global $wpdb;

		$period = $period ? $period : self::get_current_period();

		$tenants_table  = Limpeed_Tenants::table();
		$payments_table = self::table();

		$sql = "SELECT t.* FROM {$tenants_table} t WHERE t.status = 'actif' AND t.id NOT IN ( SELECT tenant_id FROM {$payments_table} WHERE period = %s AND status = 'paye' ) ORDER BY t.full_name ASC";

		return $wpdb->get_results( $wpdb->prepare( $sql, $period ) );
	}

	/**
	 * Résumé des recouvrements de loyers sur les N derniers mois (le mois en
	 * cours inclus), du plus ancien au plus récent. Utilisé pour le graphique
	 * du tableau de bord.
	 *
	 * @param int $months Nombre de mois à inclure.
	 * @return array
	 */
	public static function get_monthly_summary( $months = 6 ) {
		$summaries         = array();
		$first_of_month    = new DateTime( current_time( 'Y-m-01' ) );

		// On part toujours du 1er du mois avant de soustraire des mois : soustraire
		// depuis un autre quantième (ex. le 31) provoquerait un débordement de mois
		// avec strtotime()/DateTime (le 31 mars moins 1 mois devient le 1er mai au
		// lieu du 28/29 février), ce qui dupliquerait ou sauterait un mois du graphique.
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$date = clone $first_of_month;
			$date->modify( "-{$i} months" );
			$summaries[] = self::get_period_summary( $date->format( 'Y-m' ) );
		}

		return $summaries;
	}

	/**
	 * Calcule la commission agence pour un paiement : montant × taux de
	 * commission de l'édifice auquel appartient le bien concerné. Chaque
	 * édifice peut avoir un taux différent, défini par l'administrateur
	 * sur sa fiche (voir Limpeed_Buildings).
	 *
	 * @param int   $property_id
	 * @param float $amount
	 * @return float
	 */
	private static function calculate_commission( $property_id, $amount ) {
		$property = Limpeed_Properties::get( $property_id );
		if ( ! $property ) {
			return 0.0;
		}

		$building = Limpeed_Buildings::get( $property->building_id );
		if ( ! $building ) {
			return 0.0;
		}

		return round( $amount * ( (float) $building->commission_rate / 100 ), 2 );
	}

	/**
	 * Insère un nouveau paiement.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$statuses = array_keys( self::get_statuses() );
		$methods  = array_keys( self::get_payment_methods() );

		$amount = (float) ( $data['amount'] ?? 0 );

		$record = array(
			'tenant_id'          => (int) $data['tenant_id'],
			'property_id'        => (int) $data['property_id'],
			'amount'             => $amount,
			'payment_date'       => ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : null,
			'period'             => sanitize_text_field( $data['period'] ),
			'status'             => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'paye',
			'payment_method'     => in_array( $data['payment_method'] ?? '', $methods, true ) ? $data['payment_method'] : 'especes',
			'commission_amount'  => self::calculate_commission( (int) $data['property_id'], $amount ),
			'created_by'         => get_current_user_id(),
			'created_at'         => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'payment', $id, sprintf( 'Paiement enregistré : %s (%s)', self::format_amount( $record['amount'] ), $record['period'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour un paiement existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$statuses = array_keys( self::get_statuses() );
		$methods  = array_keys( self::get_payment_methods() );

		$amount = (float) ( $data['amount'] ?? 0 );

		$record = array(
			'tenant_id'         => (int) $data['tenant_id'],
			'property_id'       => (int) $data['property_id'],
			'amount'            => $amount,
			'payment_date'      => ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : null,
			'period'            => sanitize_text_field( $data['period'] ),
			'status'            => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'paye',
			'payment_method'    => in_array( $data['payment_method'] ?? '', $methods, true ) ? $data['payment_method'] : 'especes',
			'commission_amount' => self::calculate_commission( (int) $data['property_id'], $amount ),
			'updated_by'        => get_current_user_id(),
			'updated_at'        => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'payment', $id, sprintf( 'Paiement modifié : %s (%s)', self::format_amount( $record['amount'] ), $record['period'] ) );
		}

		return $result;
	}

	/**
	 * Supprime un paiement.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'payment', $id );
		}

		return $result;
	}
}
