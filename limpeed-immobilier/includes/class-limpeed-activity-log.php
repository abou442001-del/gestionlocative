<?php
/**
 * Journal d'activité des agents (wp_limpeed_activity_log).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Activity_Log {

	/**
	 * Types d'action journalisés.
	 *
	 * @return array
	 */
	public static function get_actions() {
		return array(
			'created' => __( 'Création', 'limpeed-immobilier' ),
			'updated' => __( 'Modification', 'limpeed-immobilier' ),
			'deleted' => __( 'Suppression', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Types d'objets journalisés.
	 *
	 * @return array
	 */
	public static function get_object_types() {
		return array(
			'owner'     => __( 'Propriétaire', 'limpeed-immobilier' ),
			'building'  => __( 'Édifice', 'limpeed-immobilier' ),
			'property'  => __( 'Bien', 'limpeed-immobilier' ),
			'tenant'    => __( 'Locataire', 'limpeed-immobilier' ),
			'payment'   => __( 'Paiement', 'limpeed-immobilier' ),
			'statement' => __( 'Bordereau', 'limpeed-immobilier' ),
			'agent'      => __( 'Agent', 'limpeed-immobilier' ),
			'mandate'    => __( 'Mandat de gestion', 'limpeed-immobilier' ),
			'inspection' => __( 'État des lieux', 'limpeed-immobilier' ),
			'document'   => __( 'Document', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_activity_log';
	}

	/**
	 * Enregistre une entrée dans le journal d'activité.
	 * Échec silencieux : le journal ne doit jamais bloquer une action métier.
	 *
	 * @param string $action      created|updated|deleted
	 * @param string $object_type owner|property|tenant|payment|statement|agent
	 * @param int    $object_id
	 * @param string $description
	 */
	public static function log( $action, $object_type, $object_id, $description = '' ) {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'user_id'     => get_current_user_id(),
				'action'      => sanitize_text_field( $action ),
				'object_type' => sanitize_text_field( $object_type ),
				'object_id'   => (int) $object_id,
				'description' => sanitize_textarea_field( $description ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Récupère les entrées du journal avec filtres et pagination.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'user_id'     => 0,
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['user_id'] ) ) {
			$where   .= ' AND user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['object_type'] ) ) {
			$where   .= ' AND object_type = %s';
			$params[] = sanitize_text_field( $args['object_type'] );
		}

		if ( ! empty( $args['object_id'] ) ) {
			$where   .= ' AND object_id = %d';
			$params[] = (int) $args['object_id'];
		}

		if ( ! empty( $args['action'] ) ) {
			$where   .= ' AND action = %s';
			$params[] = sanitize_text_field( $args['action'] );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total d'entrées correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['user_id'] ) ) {
			$where   .= ' AND user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['object_type'] ) ) {
			$where   .= ' AND object_type = %s';
			$params[] = sanitize_text_field( $args['object_type'] );
		}

		if ( ! empty( $args['object_id'] ) ) {
			$where   .= ' AND object_id = %d';
			$params[] = (int) $args['object_id'];
		}

		if ( ! empty( $args['action'] ) ) {
			$where   .= ' AND action = %s';
			$params[] = sanitize_text_field( $args['action'] );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}
}
