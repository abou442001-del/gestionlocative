<?php
/**
 * CRUD pour les demandes de travaux (wp_limpeed_work_requests).
 *
 * Un agent peut demander la réalisation de travaux sur un édifice (montant +
 * motif) ; la demande reste "en_attente" jusqu'à ce qu'un administrateur
 * l'approuve ou la refuse. Une demande approuvée crée automatiquement la
 * charge correspondante dans Comptabilité (catégorie "Réparation"), pour que
 * le montant validé apparaisse dans les comptes sans ressaisie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Work_Requests {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_work_requests';
	}

	/**
	 * Statuts possibles d'une demande de travaux.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'en_attente' => __( 'En attente', 'limpeed-immobilier' ),
			'approuve'   => __( 'Approuvée', 'limpeed-immobilier' ),
			'refuse'     => __( 'Refusée', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Récupère une demande par son id.
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
	 * Récupère la liste des demandes de travaux avec filtres et pagination.
	 *
	 * @param array $args {
	 *     @type int    $building_id
	 *     @type string $status
	 *     @type int    $requested_by
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'building_id'  => 0,
			'status'       => '',
			'requested_by' => 0,
			'per_page'     => 20,
			'paged'        => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND building_id = %d';
			$params[] = (int) $args['building_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['requested_by'] ) ) {
			$where   .= ' AND requested_by = %d';
			$params[] = (int) $args['requested_by'];
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY FIELD(status, 'en_attente', 'approuve', 'refuse'), created_at DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de demandes correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'building_id'  => 0,
			'status'       => '',
			'requested_by' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND building_id = %d';
			$params[] = (int) $args['building_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['requested_by'] ) ) {
			$where   .= ' AND requested_by = %d';
			$params[] = (int) $args['requested_by'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Crée une nouvelle demande de travaux (statut "en_attente").
	 *
	 * @param array $data { building_id, amount, reason }
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;

		$building_id = (int) ( $data['building_id'] ?? 0 );
		if ( ! $building_id || ! Limpeed_Buildings::get( $building_id ) ) {
			return new WP_Error( 'limpeed_invalid_building', __( 'Édifice introuvable.', 'limpeed-immobilier' ) );
		}

		$reason = sanitize_textarea_field( $data['reason'] ?? '' );
		if ( '' === $reason ) {
			return new WP_Error( 'limpeed_missing_reason', __( 'Le motif est obligatoire.', 'limpeed-immobilier' ) );
		}

		$table  = self::table();
		$record = array(
			'building_id'   => $building_id,
			'amount'        => max( 0, (float) ( $data['amount'] ?? 0 ) ),
			'reason'        => $reason,
			'status'        => 'en_attente',
			'requested_by'  => get_current_user_id(),
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $record, array( '%d', '%f', '%s', '%s', '%d', '%s' ) );

		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'La demande n\'a pas pu être enregistrée.', 'limpeed-immobilier' ) );
		}

		$id       = (int) $wpdb->insert_id;
		$building = Limpeed_Buildings::get( $building_id );
		Limpeed_Activity_Log::log(
			'created',
			'work_request',
			$id,
			sprintf(
				/* translators: 1: édifice, 2: montant formaté */
				__( 'Demande de travaux créée pour %1$s (%2$s)', 'limpeed-immobilier' ),
				$building ? $building->name : '#' . $building_id,
				Limpeed_Payments::format_amount( $record['amount'] )
			)
		);

		return $id;
	}

	/**
	 * Approuve une demande de travaux : change son statut et crée
	 * automatiquement la charge correspondante dans Comptabilité.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function approve( $id ) {
		global $wpdb;
		$table  = self::table();
		$request = self::get( $id );

		if ( ! $request ) {
			return new WP_Error( 'limpeed_not_found', __( 'Demande introuvable.', 'limpeed-immobilier' ) );
		}

		if ( 'en_attente' !== $request->status ) {
			return new WP_Error( 'limpeed_already_reviewed', __( 'Cette demande a déjà été traitée.', 'limpeed-immobilier' ) );
		}

		$building   = Limpeed_Buildings::get( $request->building_id );
		$expense_id = Limpeed_Expenses::insert(
			array(
				'expense_date' => current_time( 'Y-m-d' ),
				'category'     => 'reparation',
				'label'        => sprintf(
					/* translators: %s: motif de la demande de travaux */
					__( 'Travaux : %s', 'limpeed-immobilier' ),
					wp_trim_words( $request->reason, 12 )
				),
				'amount'       => $request->amount,
				'building_id'  => $request->building_id,
				'notes'        => $request->reason,
			)
		);

		$result = $wpdb->update(
			$table,
			array(
				'status'       => 'approuve',
				'reviewed_by'  => get_current_user_id(),
				'reviewed_at'  => current_time( 'mysql' ),
				'expense_id'   => $expense_id ?: null,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'La validation a échoué.', 'limpeed-immobilier' ) );
		}

		Limpeed_Activity_Log::log(
			'updated',
			'work_request',
			$id,
			sprintf(
				/* translators: %s: édifice */
				__( 'Demande de travaux approuvée pour %s', 'limpeed-immobilier' ),
				$building ? $building->name : '#' . $request->building_id
			)
		);

		return true;
	}

	/**
	 * Refuse une demande de travaux.
	 *
	 * @param int    $id
	 * @param string $notes Motif du refus (optionnel).
	 * @return bool|WP_Error
	 */
	public static function reject( $id, $notes = '' ) {
		global $wpdb;
		$table   = self::table();
		$request = self::get( $id );

		if ( ! $request ) {
			return new WP_Error( 'limpeed_not_found', __( 'Demande introuvable.', 'limpeed-immobilier' ) );
		}

		if ( 'en_attente' !== $request->status ) {
			return new WP_Error( 'limpeed_already_reviewed', __( 'Cette demande a déjà été traitée.', 'limpeed-immobilier' ) );
		}

		$result = $wpdb->update(
			$table,
			array(
				'status'       => 'refuse',
				'reviewed_by'  => get_current_user_id(),
				'reviewed_at'  => current_time( 'mysql' ),
				'review_notes' => sanitize_textarea_field( $notes ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Le refus a échoué.', 'limpeed-immobilier' ) );
		}

		$building = Limpeed_Buildings::get( $request->building_id );
		Limpeed_Activity_Log::log(
			'updated',
			'work_request',
			$id,
			sprintf(
				/* translators: %s: édifice */
				__( 'Demande de travaux refusée pour %s', 'limpeed-immobilier' ),
				$building ? $building->name : '#' . $request->building_id
			)
		);

		return true;
	}

	/**
	 * Supprime une demande de travaux encore en attente.
	 * Une demande déjà traitée (approuvée/refusée) ne peut plus être
	 * supprimée : elle fait foi de l'historique de décision.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$request = self::get( $id );

		if ( ! $request ) {
			return new WP_Error( 'limpeed_not_found', __( 'Demande introuvable.', 'limpeed-immobilier' ) );
		}

		if ( 'en_attente' !== $request->status ) {
			return new WP_Error( 'limpeed_already_reviewed', __( 'Impossible de supprimer une demande déjà traitée.', 'limpeed-immobilier' ) );
		}

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'work_request', $id );
		}

		return $result;
	}

	/**
	 * Nombre de demandes en attente (utilisé par le centre de notifications
	 * et la carte KPI, réservé aux administrateurs).
	 *
	 * @return int
	 */
	public static function count_pending() {
		return self::count( array( 'status' => 'en_attente' ) );
	}
}
