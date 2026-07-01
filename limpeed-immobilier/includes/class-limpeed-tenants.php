<?php
/**
 * CRUD pour les locataires (wp_limpeed_tenants).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Tenants {

	/**
	 * Statuts possibles d'un locataire.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'actif'   => __( 'Actif', 'limpeed-immobilier' ),
			'termine' => __( 'Bail terminé', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_tenants';
	}

	/**
	 * Récupère un locataire par son id.
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
	 * Récupère la liste des locataires avec recherche, filtres et pagination.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'search'      => '',
			'property_id' => 0,
			'status'      => '',
			'orderby'     => 'full_name',
			'order'       => 'ASC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'full_name', 'lease_start', 'lease_end', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'full_name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (full_name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params   = array_merge( $params, array( $like, $like, $like ) );
		}

		if ( ! empty( $args['property_id'] ) ) {
			$where   .= ' AND property_id = %d';
			$params[] = (int) $args['property_id'];
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
	 * Compte le nombre total de locataires correspondant aux filtres.
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
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (full_name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params   = array( $like, $like, $like );
		}

		if ( ! empty( $args['property_id'] ) ) {
			$where   .= ' AND property_id = %d';
			$params[] = (int) $args['property_id'];
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
	 * Insère un nouveau locataire.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'property_id'  => (int) $data['property_id'],
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'lease_start'  => ! empty( $data['lease_start'] ) ? sanitize_text_field( $data['lease_start'] ) : null,
			'lease_end'    => ! empty( $data['lease_end'] ) ? sanitize_text_field( $data['lease_end'] ) : null,
			'rent_amount'  => (float) ( $data['rent_amount'] ?? 0 ),
			'deposit_paid' => (float) ( $data['deposit_paid'] ?? 0 ),
			'status'       => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'actif',
			'created_by'   => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			self::sync_property_status( $record['property_id'] );
			Limpeed_Activity_Log::log( 'created', 'tenant', $id, sprintf( 'Locataire créé : %s', $record['full_name'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour un locataire existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$existing = self::get( $id );
		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'property_id'  => (int) $data['property_id'],
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'lease_start'  => ! empty( $data['lease_start'] ) ? sanitize_text_field( $data['lease_start'] ) : null,
			'lease_end'    => ! empty( $data['lease_end'] ) ? sanitize_text_field( $data['lease_end'] ) : null,
			'rent_amount'  => (float) ( $data['rent_amount'] ?? 0 ),
			'deposit_paid' => (float) ( $data['deposit_paid'] ?? 0 ),
			'status'       => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'actif',
			'updated_by'   => get_current_user_id(),
			'updated_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			self::sync_property_status( $record['property_id'] );
			if ( $existing && (int) $existing->property_id !== $record['property_id'] ) {
				self::sync_property_status( (int) $existing->property_id );
			}
			Limpeed_Activity_Log::log( 'updated', 'tenant', $id, sprintf( 'Locataire modifié : %s', $record['full_name'] ) );
		}

		return $result;
	}

	/**
	 * Supprime un locataire.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$tenant = self::get( $id );
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result && $tenant ) {
			self::sync_property_status( (int) $tenant->property_id );
			Limpeed_Activity_Log::log( 'deleted', 'tenant', $id );
		}

		return $result;
	}

	/**
	 * Différence en nombre de mois entre deux périodes YYYY-MM ($to - $from).
	 * Un résultat positif signifie que $to est postérieur à $from.
	 *
	 * @param string $from Format YYYY-MM.
	 * @param string $to   Format YYYY-MM.
	 * @return int
	 */
	private static function month_diff( $from, $to ) {
		list( $from_year, $from_month ) = array_map( 'intval', explode( '-', $from ) );
		list( $to_year, $to_month )     = array_map( 'intval', explode( '-', $to ) );

		return ( $to_year - $from_year ) * 12 + ( $to_month - $from_month );
	}

	/**
	 * Calcule le statut d'avance de loyer d'un locataire : montant attendu
	 * pour le nombre de mois d'avance réglé dans les Réglages, mois jusqu'auquel
	 * il est à jour (d'après le dernier mois marqué "payé"), et écart en mois
	 * par rapport au mois en cours (positif = avance, négatif = retard).
	 *
	 * @param object $tenant
	 * @return array {
	 *     @type int         $advance_months Nombre de mois d'avance réglé dans les paramètres.
	 *     @type float       $advance_amount Loyer × nombre de mois d'avance.
	 *     @type string|null $paid_until     Dernier mois marqué "payé" (YYYY-MM), ou null si aucun.
	 *     @type int|null    $months_ahead   Écart en mois entre $paid_until et le mois en cours (null si aucun paiement).
	 *     @type string      $status         'aucun_paiement' | 'en_retard' | 'a_jour' | 'en_avance'.
	 * }
	 */
	public static function get_advance_status( $tenant ) {
		global $wpdb;

		$advance_months = max( 1, (int) get_option( 'limpeed_advance_months', 1 ) );
		$advance_amount = (float) $tenant->rent_amount * $advance_months;

		$payments_table = Limpeed_Payments::table();
		$paid_until     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(period) FROM {$payments_table} WHERE tenant_id = %d AND status = 'paye'",
				$tenant->id
			)
		);

		if ( ! $paid_until ) {
			return array(
				'advance_months' => $advance_months,
				'advance_amount' => $advance_amount,
				'paid_until'     => null,
				'months_ahead'   => null,
				'status'         => 'aucun_paiement',
			);
		}

		$current_period = Limpeed_Payments::get_current_period();

		$months_ahead = self::month_diff( $current_period, $paid_until );

		if ( $months_ahead < 0 ) {
			$status = 'en_retard';
		} elseif ( $months_ahead >= $advance_months ) {
			$status = 'en_avance';
		} else {
			$status = 'a_jour';
		}

		return array(
			'advance_months' => $advance_months,
			'advance_amount' => $advance_amount,
			'paid_until'     => $paid_until,
			'months_ahead'   => $months_ahead,
			'status'         => $status,
		);
	}

	/**
	 * Construit le calendrier annuel de suivi des paiements d'un locataire :
	 * pour chacun des 12 mois de l'année donnée, indique s'il est payé,
	 * partiellement payé, en retard, à venir, ou hors période de bail.
	 *
	 * @param object $tenant
	 * @param int    $year
	 * @return array Tableau indexé de 1 à 12, chaque entrée contenant
	 *               'period', 'status' et 'payment' (objet paiement ou null).
	 */
	public static function get_payment_calendar( $tenant, $year ) {
		global $wpdb;

		$payments_table = Limpeed_Payments::table();
		$year_payments  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$payments_table} WHERE tenant_id = %d AND period LIKE %s",
				$tenant->id,
				$year . '-%'
			)
		);

		$payments_by_period = array();
		foreach ( $year_payments as $payment ) {
			$payments_by_period[ $payment->period ] = $payment;
		}

		$current_period = Limpeed_Payments::get_current_period();
		$lease_start    = $tenant->lease_start ? substr( $tenant->lease_start, 0, 7 ) : null;
		$lease_end      = $tenant->lease_end ? substr( $tenant->lease_end, 0, 7 ) : null;

		$calendar = array();

		for ( $month = 1; $month <= 12; $month++ ) {
			$period  = sprintf( '%04d-%02d', $year, $month );
			$payment = isset( $payments_by_period[ $period ] ) ? $payments_by_period[ $period ] : null;

			if ( $payment && 'paye' === $payment->status ) {
				$status = 'paye';
			} elseif ( $payment && 'partiel' === $payment->status ) {
				$status = 'partiel';
			} elseif ( $lease_start && $period < $lease_start ) {
				$status = 'hors_bail';
			} elseif ( $lease_end && $period > $lease_end ) {
				$status = 'hors_bail';
			} elseif ( $period < $current_period ) {
				$status = 'retard';
			} else {
				$status = 'a_venir';
			}

			$calendar[ $month ] = array(
				'period'  => $period,
				'status'  => $status,
				'payment' => $payment,
			);
		}

		return $calendar;
	}

	/**
	 * Met à jour automatiquement le statut du bien (loué/vacant) selon
	 * la présence ou non d'un locataire actif.
	 *
	 * @param int $property_id
	 */
	private static function sync_property_status( $property_id ) {
		$property = Limpeed_Properties::get( $property_id );
		if ( ! $property || 'travaux' === $property->status ) {
			return;
		}

		$current_tenant = Limpeed_Properties::get_current_tenant( $property_id );
		$new_status     = $current_tenant ? 'loue' : 'vacant';

		if ( $new_status !== $property->status ) {
			global $wpdb;
			$wpdb->update(
				Limpeed_Properties::table(),
				array(
					'status'     => $new_status,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $property_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}
}
