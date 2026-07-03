<?php
/**
 * CRUD pour les charges de l'agence (wp_limpeed_expenses).
 *
 * Une charge peut être rattachée à un édifice et/ou un bien précis (entretien,
 * réparation) ou rester générale à l'agence (assurance, honoraires...), d'où
 * les deux colonnes building_id/property_id optionnelles plutôt qu'une
 * association obligatoire.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Expenses {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_expenses';
	}

	/**
	 * Catégories de charges disponibles.
	 *
	 * @return array
	 */
	public static function get_categories() {
		return array(
			'entretien'   => __( 'Entretien', 'limpeed-immobilier' ),
			'reparation'  => __( 'Réparation', 'limpeed-immobilier' ),
			'assurance'   => __( 'Assurance', 'limpeed-immobilier' ),
			'taxe'        => __( 'Taxe / impôt', 'limpeed-immobilier' ),
			'honoraires'  => __( 'Honoraires', 'limpeed-immobilier' ),
			'salaire'     => __( 'Salaire / commission agent', 'limpeed-immobilier' ),
			'autre'       => __( 'Autre', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Récupère une charge par son id.
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
	 * Construit la clause WHERE partagée par get_all() et count().
	 *
	 * @param array $args
	 * @return array { where: string, params: array }
	 */
	private static function build_where( $args ) {
		global $wpdb;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND label LIKE %s';
			$params[] = $like;
		}

		if ( ! empty( $args['category'] ) ) {
			$where   .= ' AND category = %s';
			$params[] = sanitize_key( $args['category'] );
		}

		if ( ! empty( $args['period'] ) ) {
			$where   .= ' AND DATE_FORMAT(expense_date, "%%Y-%%m") = %s';
			$params[] = sanitize_text_field( $args['period'] );
		}

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND building_id = %d';
			$params[] = (int) $args['building_id'];
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Récupère la liste des charges avec recherche, filtres et pagination.
	 *
	 * @param array $args {
	 *     @type string $search
	 *     @type string $category
	 *     @type string $period      Format YYYY-MM.
	 *     @type int    $building_id
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
			'search'      => '',
			'category'    => '',
			'period'      => '',
			'building_id' => 0,
			'orderby'     => 'expense_date',
			'order'       => 'DESC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'expense_date', 'amount', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'expense_date';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$built  = self::build_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de charges correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$built  = self::build_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Total des charges pour une période (YYYY-MM) donnée.
	 *
	 * @param string $period
	 * @return float
	 */
	public static function get_total_for_period( $period ) {
		global $wpdb;
		$table = self::table();

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table} WHERE DATE_FORMAT(expense_date, '%%Y-%%m') = %s",
				$period
			)
		);

		return $total ? (float) $total : 0.0;
	}

	/**
	 * Insère une nouvelle charge.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'expense_date' => sanitize_text_field( $data['expense_date'] ?? current_time( 'Y-m-d' ) ),
			'category'     => in_array( $data['category'] ?? '', array_keys( self::get_categories() ), true ) ? $data['category'] : 'autre',
			'label'        => sanitize_text_field( $data['label'] ?? '' ),
			'amount'       => max( 0, (float) ( $data['amount'] ?? 0 ) ),
			'building_id'  => ! empty( $data['building_id'] ) ? (int) $data['building_id'] : null,
			'property_id'  => ! empty( $data['property_id'] ) ? (int) $data['property_id'] : null,
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_by'   => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'expense', $id, sprintf( 'Charge "%s" ajoutée (%s)', $record['label'], Limpeed_Payments::format_amount( $record['amount'] ) ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour une charge existante.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'expense_date' => sanitize_text_field( $data['expense_date'] ?? current_time( 'Y-m-d' ) ),
			'category'     => in_array( $data['category'] ?? '', array_keys( self::get_categories() ), true ) ? $data['category'] : 'autre',
			'label'        => sanitize_text_field( $data['label'] ?? '' ),
			'amount'       => max( 0, (float) ( $data['amount'] ?? 0 ) ),
			'building_id'  => ! empty( $data['building_id'] ) ? (int) $data['building_id'] : null,
			'property_id'  => ! empty( $data['property_id'] ) ? (int) $data['property_id'] : null,
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_by'   => get_current_user_id(),
			'updated_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'expense', $id, sprintf( 'Charge "%s" modifiée', $record['label'] ) );
		}

		return $result;
	}

	/**
	 * Supprime une charge.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'expense', $id );
		}

		return $result;
	}
}
