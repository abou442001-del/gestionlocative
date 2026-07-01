<?php
/**
 * CRUD pour les paiements (wp_limpeed_payments).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Payments {

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

		$record = array(
			'tenant_id'          => (int) $data['tenant_id'],
			'property_id'        => (int) $data['property_id'],
			'amount'             => (float) ( $data['amount'] ?? 0 ),
			'payment_date'       => ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : null,
			'period'             => sanitize_text_field( $data['period'] ),
			'status'             => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'paye',
			'payment_method'     => in_array( $data['payment_method'] ?? '', $methods, true ) ? $data['payment_method'] : 'especes',
			'commission_amount'  => (float) ( $data['commission_amount'] ?? 0 ),
			'created_by'         => get_current_user_id(),
			'created_at'         => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'payment', $id, sprintf( 'Paiement enregistré : %s (%s)', number_format( $record['amount'], 2 ), $record['period'] ) );
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

		$record = array(
			'tenant_id'         => (int) $data['tenant_id'],
			'property_id'       => (int) $data['property_id'],
			'amount'            => (float) ( $data['amount'] ?? 0 ),
			'payment_date'      => ! empty( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : null,
			'period'            => sanitize_text_field( $data['period'] ),
			'status'            => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'paye',
			'payment_method'    => in_array( $data['payment_method'] ?? '', $methods, true ) ? $data['payment_method'] : 'especes',
			'commission_amount' => (float) ( $data['commission_amount'] ?? 0 ),
			'updated_by'        => get_current_user_id(),
			'updated_at'        => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'payment', $id, sprintf( 'Paiement modifié : %s (%s)', number_format( $record['amount'], 2 ), $record['period'] ) );
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
