<?php
/**
 * CRUD pour les mandats de gestion (wp_limpeed_mandates).
 *
 * Un mandat de gestion autorise l'agence à gérer/louer les biens d'un édifice
 * pour le compte de son propriétaire, sur une période donnée et avec un taux
 * de commission propre au mandat (indépendant du taux courant de l'édifice,
 * qui peut évoluer entre deux mandats).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Mandates {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_mandates';
	}

	/**
	 * Statuts possibles d'un mandat.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'actif'    => __( 'Actif', 'limpeed-immobilier' ),
			'expire'   => __( 'Expiré', 'limpeed-immobilier' ),
			'resilie'  => __( 'Résilié', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Statut réellement affiché : un mandat "actif" dont la date de fin est
	 * dépassée s'affiche "Expiré" sans qu'il soit nécessaire de mettre à jour
	 * la ligne en base (même logique que le statut loué/vacant des biens,
	 * dérivé à la lecture plutôt que maintenu par une tâche planifiée).
	 *
	 * @param object $mandate
	 * @return string
	 */
	public static function get_display_status( $mandate ) {
		if ( 'resilie' === $mandate->status ) {
			return 'resilie';
		}

		if ( ! empty( $mandate->end_date ) && $mandate->end_date < current_time( 'Y-m-d' ) ) {
			return 'expire';
		}

		return 'actif';
	}

	/**
	 * Récupère un mandat par son id.
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
	 * Récupère la liste des mandats avec recherche, filtres et pagination.
	 *
	 * @param array $args {
	 *     @type string $search      Recherche sur le nom de l'édifice.
	 *     @type int    $building_id
	 *     @type string $status
	 *     @type string $orderby
	 *     @type string $order
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table          = self::table();
		$buildings_table = Limpeed_Buildings::table();

		$defaults = array(
			'search'      => '',
			'building_id' => 0,
			'status'      => '',
			'orderby'     => 'end_date',
			'order'       => 'ASC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'start_date', 'end_date', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? 'm.' . $args['orderby'] : 'm.end_date';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND b.name LIKE %s';
			$params[] = $like;
		}

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND m.building_id = %d';
			$params[] = (int) $args['building_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			if ( 'expire' === $args['status'] ) {
				$where .= " AND m.status != 'resilie' AND m.end_date IS NOT NULL AND m.end_date < %s";
				$params[] = current_time( 'Y-m-d' );
			} elseif ( 'actif' === $args['status'] ) {
				$where .= " AND m.status = 'actif' AND ( m.end_date IS NULL OR m.end_date >= %s )";
				$params[] = current_time( 'Y-m-d' );
			} else {
				$where   .= ' AND m.status = %s';
				$params[] = sanitize_key( $args['status'] );
			}
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql = "SELECT m.* FROM {$table} m INNER JOIN {$buildings_table} b ON b.id = m.building_id {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de mandats correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table           = self::table();
		$buildings_table = Limpeed_Buildings::table();

		$defaults = array(
			'search'      => '',
			'building_id' => 0,
			'status'      => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND b.name LIKE %s';
			$params[] = $like;
		}

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND m.building_id = %d';
			$params[] = (int) $args['building_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			if ( 'expire' === $args['status'] ) {
				$where   .= " AND m.status != 'resilie' AND m.end_date IS NOT NULL AND m.end_date < %s";
				$params[] = current_time( 'Y-m-d' );
			} elseif ( 'actif' === $args['status'] ) {
				$where   .= " AND m.status = 'actif' AND ( m.end_date IS NULL OR m.end_date >= %s )";
				$params[] = current_time( 'Y-m-d' );
			} else {
				$where   .= ' AND m.status = %s';
				$params[] = sanitize_key( $args['status'] );
			}
		}

		$sql = "SELECT COUNT(*) FROM {$table} m INNER JOIN {$buildings_table} b ON b.id = m.building_id {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Mandats arrivant à échéance dans les N prochains jours (alerte).
	 *
	 * @param int $days
	 * @return array
	 */
	public static function get_expiring( $days = 30 ) {
		global $wpdb;
		$table = self::table();

		$today      = current_time( 'Y-m-d' );
		$limit_date = gmdate( 'Y-m-d', strtotime( $today . " +{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'actif' AND end_date IS NOT NULL AND end_date BETWEEN %s AND %s ORDER BY end_date ASC",
				$today,
				$limit_date
			)
		);
	}

	/**
	 * Insère un nouveau mandat.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'building_id'     => (int) $data['building_id'],
			'start_date'      => sanitize_text_field( $data['start_date'] ?? '' ),
			'end_date'        => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'commission_rate' => max( 0, min( 100, (float) ( $data['commission_rate'] ?? 0 ) ) ),
			'status'          => in_array( $data['status'] ?? '', array_keys( self::get_statuses() ), true ) ? $data['status'] : 'actif',
			'signed_date'     => ! empty( $data['signed_date'] ) ? sanitize_text_field( $data['signed_date'] ) : null,
			'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id      = (int) $wpdb->insert_id;
			$building = Limpeed_Buildings::get( $record['building_id'] );
			Limpeed_Activity_Log::log( 'created', 'mandate', $id, sprintf( 'Mandat de gestion créé pour %s', $building ? $building->name : '#' . $record['building_id'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour un mandat existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'building_id'     => (int) $data['building_id'],
			'start_date'      => sanitize_text_field( $data['start_date'] ?? '' ),
			'end_date'        => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'commission_rate' => max( 0, min( 100, (float) ( $data['commission_rate'] ?? 0 ) ) ),
			'status'          => in_array( $data['status'] ?? '', array_keys( self::get_statuses() ), true ) ? $data['status'] : 'actif',
			'signed_date'     => ! empty( $data['signed_date'] ) ? sanitize_text_field( $data['signed_date'] ) : null,
			'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_by'      => get_current_user_id(),
			'updated_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			$building = Limpeed_Buildings::get( $record['building_id'] );
			Limpeed_Activity_Log::log( 'updated', 'mandate', $id, sprintf( 'Mandat de gestion modifié pour %s', $building ? $building->name : '#' . $record['building_id'] ) );
		}

		return $result;
	}

	/**
	 * Supprime un mandat.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'mandate', $id );
		}

		return $result;
	}
}
