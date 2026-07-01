<?php
/**
 * CRUD pour les biens (wp_limpeed_properties).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Properties {

	/**
	 * Types de biens disponibles.
	 *
	 * @return array
	 */
	public static function get_types() {
		return array(
			'appartement'      => __( 'Appartement', 'limpeed-immobilier' ),
			'maison'           => __( 'Maison', 'limpeed-immobilier' ),
			'studio'           => __( 'Studio', 'limpeed-immobilier' ),
			'local_commercial' => __( 'Local commercial', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Statuts possibles d'un bien.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'loue'    => __( 'Loué', 'limpeed-immobilier' ),
			'vacant'  => __( 'Vacant', 'limpeed-immobilier' ),
			'travaux' => __( 'Travaux', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_properties';
	}

	/**
	 * Récupère un bien par son id.
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
	 * Récupère la liste des biens avec recherche, filtres et pagination.
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
			'status'   => '',
			'orderby'  => 'address',
			'order'    => 'ASC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'address', 'type', 'monthly_rent', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'address';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND address LIKE %s';
			$params[] = $like;
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
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
	 * Compte le nombre total de biens correspondant aux filtres.
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
			$where   .= ' AND address LIKE %s';
			$params[] = $like;
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
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
	 * Retourne le locataire actuellement actif d'un bien, s'il existe.
	 *
	 * @param int $property_id
	 * @return object|null
	 */
	public static function get_current_tenant( $property_id ) {
		global $wpdb;
		$tenants_table = Limpeed_Tenants::table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tenants_table} WHERE property_id = %d AND status = 'actif' ORDER BY lease_start DESC LIMIT 1",
				$property_id
			)
		);
	}

	/**
	 * Insère un nouveau bien.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$types    = array_keys( self::get_types() );
		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'owner_id'       => (int) $data['owner_id'],
			'address'        => sanitize_textarea_field( $data['address'] ),
			'type'           => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'appartement',
			'monthly_rent'   => (float) ( $data['monthly_rent'] ?? 0 ),
			'charges'        => (float) ( $data['charges'] ?? 0 ),
			'deposit_amount' => (float) ( $data['deposit_amount'] ?? 0 ),
			'status'         => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'vacant',
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Met à jour un bien existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$types    = array_keys( self::get_types() );
		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'owner_id'       => (int) $data['owner_id'],
			'address'        => sanitize_textarea_field( $data['address'] ),
			'type'           => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'appartement',
			'monthly_rent'   => (float) ( $data['monthly_rent'] ?? 0 ),
			'charges'        => (float) ( $data['charges'] ?? 0 ),
			'deposit_amount' => (float) ( $data['deposit_amount'] ?? 0 ),
			'status'         => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'vacant',
			'updated_by'     => get_current_user_id(),
			'updated_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' );

		return false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	/**
	 * Supprime un bien.
	 * Refuse la suppression si des locataires y sont encore rattachés.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$tenants_table = Limpeed_Tenants::table();
		$linked        = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tenants_table} WHERE property_id = %d", $id )
		);

		if ( $linked > 0 ) {
			return new WP_Error(
				'limpeed_property_has_tenants',
				__( 'Impossible de supprimer ce bien : des locataires y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$table = self::table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
