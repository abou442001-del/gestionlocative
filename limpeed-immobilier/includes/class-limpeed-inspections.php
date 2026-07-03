<?php
/**
 * CRUD pour les états des lieux (wp_limpeed_inspections).
 *
 * Un état des lieux (entrée ou sortie) est rattaché à un locataire et décrit
 * pièce par pièce l'état du logement à un instant donné. Le détail par pièce
 * est stocké en JSON (rooms_data) plutôt que dans une table relationnelle
 * séparée : la structure (liste de pièces avec nom/état/notes) est simple,
 * n'a pas besoin d'être interrogée indépendamment de sa fiche parente, et
 * varie librement d'un bien à l'autre (aucun schéma fixe de pièces).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Inspections {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_inspections';
	}

	/**
	 * Types d'état des lieux.
	 *
	 * @return array
	 */
	public static function get_types() {
		return array(
			'entree' => __( 'Entrée', 'limpeed-immobilier' ),
			'sortie' => __( 'Sortie', 'limpeed-immobilier' ),
		);
	}

	/**
	 * États possibles d'une pièce.
	 *
	 * @return array
	 */
	public static function get_room_conditions() {
		return array(
			'bon'     => __( 'Bon état', 'limpeed-immobilier' ),
			'moyen'   => __( 'État moyen', 'limpeed-immobilier' ),
			'mauvais' => __( 'Mauvais état', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Récupère un état des lieux par son id.
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
	 * Décode le détail des pièces d'un état des lieux (JSON -> tableau).
	 *
	 * @param object $inspection
	 * @return array
	 */
	public static function get_rooms( $inspection ) {
		if ( empty( $inspection->rooms_data ) ) {
			return array();
		}
		$rooms = json_decode( $inspection->rooms_data, true );
		return is_array( $rooms ) ? $rooms : array();
	}

	/**
	 * Récupère la liste des états des lieux avec filtres et pagination.
	 *
	 * @param array $args {
	 *     @type int    $tenant_id
	 *     @type int    $property_id
	 *     @type string $type
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'tenant_id'   => 0,
			'property_id' => 0,
			'type'        => '',
			'orderby'     => 'inspection_date',
			'order'       => 'DESC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'inspection_date', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'inspection_date';
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

		if ( ! empty( $args['type'] ) ) {
			$where   .= ' AND type = %s';
			$params[] = sanitize_key( $args['type'] );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total d'états des lieux correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'tenant_id'   => 0,
			'property_id' => 0,
			'type'        => '',
		);
		$args = wp_parse_args( $args, $defaults );

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

		if ( ! empty( $args['type'] ) ) {
			$where   .= ' AND type = %s';
			$params[] = sanitize_key( $args['type'] );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Nettoie et ré-encode en JSON un tableau de pièces reçu du client.
	 *
	 * @param array $rooms
	 * @return string JSON.
	 */
	private static function sanitize_rooms( $rooms ) {
		$conditions = array_keys( self::get_room_conditions() );
		$clean      = array();

		if ( is_array( $rooms ) ) {
			foreach ( $rooms as $room ) {
				if ( ! is_array( $room ) || empty( trim( (string) ( $room['name'] ?? '' ) ) ) ) {
					continue;
				}
				$clean[] = array(
					'name'      => sanitize_text_field( $room['name'] ),
					'condition' => in_array( $room['condition'] ?? '', $conditions, true ) ? $room['condition'] : 'bon',
					'notes'     => sanitize_textarea_field( $room['notes'] ?? '' ),
				);
			}
		}

		return wp_json_encode( $clean );
	}

	/**
	 * Insère un nouvel état des lieux.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$types = array_keys( self::get_types() );

		$record = array(
			'tenant_id'       => (int) $data['tenant_id'],
			'property_id'     => (int) $data['property_id'],
			'type'            => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'entree',
			'inspection_date' => sanitize_text_field( $data['inspection_date'] ?? current_time( 'Y-m-d' ) ),
			'rooms_data'      => self::sanitize_rooms( $data['rooms'] ?? array() ),
			'general_notes'   => sanitize_textarea_field( $data['general_notes'] ?? '' ),
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id     = (int) $wpdb->insert_id;
			$tenant = Limpeed_Tenants::get( $record['tenant_id'] );
			$types_labels = self::get_types();
			Limpeed_Activity_Log::log(
				'created',
				'inspection',
				$id,
				sprintf( 'État des lieux (%s) créé pour %s', $types_labels[ $record['type'] ], $tenant ? $tenant->full_name : '#' . $record['tenant_id'] )
			);
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour un état des lieux existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$types = array_keys( self::get_types() );

		$record = array(
			'tenant_id'       => (int) $data['tenant_id'],
			'property_id'     => (int) $data['property_id'],
			'type'            => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'entree',
			'inspection_date' => sanitize_text_field( $data['inspection_date'] ?? current_time( 'Y-m-d' ) ),
			'rooms_data'      => self::sanitize_rooms( $data['rooms'] ?? array() ),
			'general_notes'   => sanitize_textarea_field( $data['general_notes'] ?? '' ),
			'updated_by'      => get_current_user_id(),
			'updated_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'inspection', $id, __( 'État des lieux modifié', 'limpeed-immobilier' ) );
		}

		return $result;
	}

	/**
	 * Supprime un état des lieux.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'inspection', $id );
		}

		return $result;
	}

	/**
	 * Compare les états des lieux d'entrée et de sortie les plus récents d'un
	 * locataire, pièce par pièce. Le rapprochement se fait par nom de pièce
	 * (comparaison insensible à la casse/aux espaces) : les pièces qui
	 * n'existent que d'un côté sont signalées comme telles plutôt qu'ignorées.
	 *
	 * @param int $tenant_id
	 * @return array|null Null si l'un des deux états des lieux manque.
	 */
	public static function compare_for_tenant( $tenant_id ) {
		$entree = self::get_all( array( 'tenant_id' => $tenant_id, 'type' => 'entree', 'per_page' => 1 ) );
		$sortie = self::get_all( array( 'tenant_id' => $tenant_id, 'type' => 'sortie', 'per_page' => 1 ) );

		if ( empty( $entree ) || empty( $sortie ) ) {
			return null;
		}

		$entree_rooms = self::get_rooms( $entree[0] );
		$sortie_rooms = self::get_rooms( $sortie[0] );

		$index_by_name = function ( $rooms ) {
			$indexed = array();
			foreach ( $rooms as $room ) {
				$indexed[ strtolower( trim( $room['name'] ) ) ] = $room;
			}
			return $indexed;
		};

		$entree_indexed = $index_by_name( $entree_rooms );
		$sortie_indexed = $index_by_name( $sortie_rooms );

		$all_names = array_unique( array_merge( array_keys( $entree_indexed ), array_keys( $sortie_indexed ) ) );

		$rows = array();
		foreach ( $all_names as $key ) {
			$in  = $entree_indexed[ $key ] ?? null;
			$out = $sortie_indexed[ $key ] ?? null;
			$rows[] = array(
				'name'        => $in ? $in['name'] : $out['name'],
				'entree'      => $in ? $in['condition'] : null,
				'sortie'      => $out ? $out['condition'] : null,
				'has_changed' => $in && $out && $in['condition'] !== $out['condition'],
			);
		}

		return array(
			'entree_id'   => (int) $entree[0]->id,
			'sortie_id'   => (int) $sortie[0]->id,
			'entree_date' => $entree[0]->inspection_date,
			'sortie_date' => $sortie[0]->inspection_date,
			'rooms'       => $rows,
		);
	}
}
