<?php
/**
 * CRUD pour les propriétaires (wp_limpeed_owners).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Owners {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_owners';
	}

	/**
	 * Récupère un propriétaire par son id.
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
	 * Récupère la liste des propriétaires avec recherche et pagination.
	 *
	 * @param array $args {
	 *     @type string $search
	 *     @type string $orderby
	 *     @type string $order
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'search'   => '',
			'orderby'  => 'full_name',
			'order'    => 'ASC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'full_name', 'phone', 'email', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'full_name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND (full_name LIKE %s OR phone LIKE %s OR email LIKE %s OR address LIKE %s)';
			$params  = array_merge( $params, array( $like, $like, $like, $like ) );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de propriétaires correspondant à la recherche.
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
			$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (full_name LIKE %s OR phone LIKE %s OR email LIKE %s OR address LIKE %s)';
			$params = array( $like, $like, $like, $like );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Insère un nouveau propriétaire.
	 *
	 * @param array $data
	 * @return int|false Id inséré ou false en cas d'échec.
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'address'      => sanitize_textarea_field( $data['address'] ?? '' ),
			'bank_details' => sanitize_textarea_field( $data['bank_details'] ?? '' ),
			'created_by'   => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Met à jour un propriétaire existant.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'address'      => sanitize_textarea_field( $data['address'] ?? '' ),
			'bank_details' => sanitize_textarea_field( $data['bank_details'] ?? '' ),
			'updated_by'   => get_current_user_id(),
			'updated_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		return false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	/**
	 * Supprime un propriétaire.
	 * Refuse la suppression si des biens y sont encore rattachés.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$properties_table = Limpeed_Properties::table();
		$linked            = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$properties_table} WHERE owner_id = %d", $id )
		);

		if ( $linked > 0 ) {
			return new WP_Error(
				'limpeed_owner_has_properties',
				__( 'Impossible de supprimer ce propriétaire : des biens lui sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$table = self::table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
