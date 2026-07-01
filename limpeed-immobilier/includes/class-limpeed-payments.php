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

		$unpaid_tenants = array();
		foreach ( $active_tenants as $tenant ) {
			$has_paid = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE tenant_id = %d AND period = %s AND status = 'paye'",
					$tenant->id,
					$period
				)
			);
			if ( ! $has_paid ) {
				$unpaid_tenants[] = $tenant;
			}
		}

		return array(
			'collected'      => $collected ? (float) $collected : 0.0,
			'commission'     => $commission ? (float) $commission : 0.0,
			'unpaid_tenants' => $unpaid_tenants,
		);
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

		return $result ? (int) $wpdb->insert_id : false;
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

		return false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	/**
	 * Supprime un paiement.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
