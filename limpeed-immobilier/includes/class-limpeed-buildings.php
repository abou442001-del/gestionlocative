<?php
/**
 * CRUD pour les édifices (wp_limpeed_buildings).
 *
 * Un édifice appartient à un propriétaire et peut contenir plusieurs
 * sous-édifices (biens, voir Limpeed_Properties).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Buildings {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_buildings';
	}

	/**
	 * Récupère un édifice par son id.
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
	 * Récupère la liste des édifices avec recherche, filtres et pagination.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'search'   => '',
			'owner_id' => 0,
			'orderby'  => 'name',
			'order'    => 'ASC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'name', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1' . Limpeed_Branches::owner_scope_sql( 'owner_id' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND (name LIKE %s OR address LIKE %s)';
			$params  = array_merge( $params, array( $like, $like ) );
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
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
	 * Compte le nombre total d'édifices correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = 'WHERE 1=1' . Limpeed_Branches::owner_scope_sql( 'owner_id' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (name LIKE %s OR address LIKE %s)';
			$params   = array( $like, $like );
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Taux de commission moyen tous édifices confondus (carte de synthèse).
	 *
	 * @return float
	 */
	public static function get_average_commission_rate() {
		global $wpdb;
		$table = self::table();
		$avg   = $wpdb->get_var( "SELECT AVG(commission_rate) FROM {$table}" );
		return $avg ? (float) $avg : 0.0;
	}

	/**
	 * Insère un nouvel édifice.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'owner_id'        => (int) $data['owner_id'],
			'name'            => sanitize_text_field( $data['name'] ),
			'address'         => sanitize_textarea_field( $data['address'] ?? '' ),
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'commission_rate' => max( 0, min( 100, (float) ( $data['commission_rate'] ?? 0 ) ) ),
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'building', $id, sprintf( 'Édifice créé : %s', $record['name'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour un édifice existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'owner_id'        => (int) $data['owner_id'],
			'name'            => sanitize_text_field( $data['name'] ),
			'address'         => sanitize_textarea_field( $data['address'] ?? '' ),
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'commission_rate' => max( 0, min( 100, (float) ( $data['commission_rate'] ?? 0 ) ) ),
			'updated_by'      => get_current_user_id(),
			'updated_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%f', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'building', $id, sprintf( 'Édifice modifié : %s', $record['name'] ) );
		}

		return $result;
	}

	/**
	 * Supprime un édifice.
	 * Refuse la suppression si des sous-édifices (biens) y sont encore rattachés.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$properties_table = Limpeed_Properties::table();
		$linked            = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$properties_table} WHERE building_id = %d", $id )
		);

		if ( $linked > 0 ) {
			return new WP_Error(
				'limpeed_building_has_properties',
				__( 'Impossible de supprimer cet édifice : des sous-édifices (biens) y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'building', $id );
		}

		return $result;
	}
}
