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
	 * Types de pièce d'identité proposés pour un locataire.
	 *
	 * @return array
	 */
	public static function get_id_document_types() {
		return array(
			'cni'       => __( 'Carte d\'identité', 'limpeed-immobilier' ),
			'passeport' => __( 'Passeport', 'limpeed-immobilier' ),
			'permis'    => __( 'Permis de conduire', 'limpeed-immobilier' ),
			'autre'     => __( 'Autre', 'limpeed-immobilier' ),
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
	 * Somme des dépôts de garantie versés par les locataires actuellement
	 * actifs (photo à l'instant présent, pas un historique de mouvements).
	 *
	 * @return float
	 */
	public static function get_total_deposits_held() {
		global $wpdb;
		$table = self::table();

		$total = $wpdb->get_var( "SELECT SUM(deposit_paid) FROM {$table} WHERE status = 'actif'" );

		return $total ? (float) $total : 0.0;
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

		$id_document_types = array_keys( self::get_id_document_types() );

		$record = array(
			'property_id'         => (int) $data['property_id'],
			'full_name'           => sanitize_text_field( $data['full_name'] ),
			'phone'               => sanitize_text_field( $data['phone'] ?? '' ),
			'email'               => sanitize_email( $data['email'] ?? '' ),
			'lease_start'         => ! empty( $data['lease_start'] ) ? sanitize_text_field( $data['lease_start'] ) : null,
			'lease_end'           => ! empty( $data['lease_end'] ) ? sanitize_text_field( $data['lease_end'] ) : null,
			'rent_amount'         => (float) ( $data['rent_amount'] ?? 0 ),
			'deposit_paid'        => (float) ( $data['deposit_paid'] ?? 0 ),
			'status'              => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'actif',
			'id_document_type'    => in_array( $data['id_document_type'] ?? '', $id_document_types, true ) ? $data['id_document_type'] : null,
			'id_document_number'  => sanitize_text_field( $data['id_document_number'] ?? '' ),
			'date_of_birth'       => ! empty( $data['date_of_birth'] ) ? sanitize_text_field( $data['date_of_birth'] ) : null,
			'profession'          => sanitize_text_field( $data['profession'] ?? '' ),
			'dependents_count'    => max( 0, (int) ( $data['dependents_count'] ?? 0 ) ),
			'guarantor_name'      => sanitize_text_field( $data['guarantor_name'] ?? '' ),
			'guarantor_phone'     => sanitize_text_field( $data['guarantor_phone'] ?? '' ),
			'is_new_tenant'       => ! empty( $data['is_new_tenant'] ) ? 1 : 0,
			'created_by'          => get_current_user_id(),
			'created_at'          => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			self::sync_property_status( $record['property_id'] );
			self::generate_advance_payments( $id, $record );
			Limpeed_Activity_Log::log( 'created', 'tenant', $id, sprintf( 'Locataire créé : %s', $record['full_name'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Crée automatiquement les paiements "payé" du mois d'avance à la création
	 * d'un nouveau locataire : le nombre de mois d'avance réglé dans les
	 * Réglages, à partir du mois de début de bail (ou du mois en cours si
	 * aucune date de début n'est renseignée).
	 *
	 * @param int   $tenant_id
	 * @param array $record Enregistrement locataire tel qu'inséré (property_id, lease_start, rent_amount).
	 */
	private static function generate_advance_payments( $tenant_id, $record ) {
		if ( (float) $record['rent_amount'] <= 0 ) {
			return;
		}

		$advance_months = max( 1, (int) get_option( 'limpeed_advance_months', 1 ) );
		$start_period   = ! empty( $record['lease_start'] ) ? substr( $record['lease_start'], 0, 7 ) : Limpeed_Payments::get_current_period();

		for ( $i = 0; $i < $advance_months; $i++ ) {
			Limpeed_Payments::insert(
				array(
					'tenant_id'      => $tenant_id,
					'property_id'    => $record['property_id'],
					'amount'         => $record['rent_amount'],
					'payment_date'   => current_time( 'mysql' ),
					'period'         => self::add_months_to_period( $start_period, $i ),
					'status'         => 'paye',
					'payment_method' => 'especes',
				)
			);
		}
	}

	/**
	 * Ajoute un nombre de mois à une période YYYY-MM.
	 *
	 * @param string $period Format YYYY-MM.
	 * @param int    $months_to_add
	 * @return string
	 */
	private static function add_months_to_period( $period, $months_to_add ) {
		list( $year, $month ) = array_map( 'intval', explode( '-', $period ) );

		$month += $months_to_add;
		$year  += intdiv( $month - 1, 12 );
		$month  = ( ( $month - 1 ) % 12 ) + 1;

		return sprintf( '%04d-%02d', $year, $month );
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

		$existing          = self::get( $id );
		$statuses          = array_keys( self::get_statuses() );
		$id_document_types = array_keys( self::get_id_document_types() );

		$record = array(
			'property_id'         => (int) $data['property_id'],
			'full_name'           => sanitize_text_field( $data['full_name'] ),
			'phone'               => sanitize_text_field( $data['phone'] ?? '' ),
			'email'               => sanitize_email( $data['email'] ?? '' ),
			'lease_start'         => ! empty( $data['lease_start'] ) ? sanitize_text_field( $data['lease_start'] ) : null,
			'lease_end'           => ! empty( $data['lease_end'] ) ? sanitize_text_field( $data['lease_end'] ) : null,
			'rent_amount'         => (float) ( $data['rent_amount'] ?? 0 ),
			'deposit_paid'        => (float) ( $data['deposit_paid'] ?? 0 ),
			'status'              => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'actif',
			'id_document_type'    => in_array( $data['id_document_type'] ?? '', $id_document_types, true ) ? $data['id_document_type'] : null,
			'id_document_number'  => sanitize_text_field( $data['id_document_number'] ?? '' ),
			'date_of_birth'       => ! empty( $data['date_of_birth'] ) ? sanitize_text_field( $data['date_of_birth'] ) : null,
			'profession'          => sanitize_text_field( $data['profession'] ?? '' ),
			'dependents_count'    => max( 0, (int) ( $data['dependents_count'] ?? 0 ) ),
			'guarantor_name'      => sanitize_text_field( $data['guarantor_name'] ?? '' ),
			'guarantor_phone'     => sanitize_text_field( $data['guarantor_phone'] ?? '' ),
			'is_new_tenant'       => ! empty( $data['is_new_tenant'] ) ? 1 : 0,
			'updated_by'          => get_current_user_id(),
			'updated_at'          => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' );

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
	 * Supprime un locataire. Bloque la suppression tant que des paiements,
	 * documents, états des lieux ou avenants lui sont encore rattachés (même
	 * principe que pour Propriétaires/Édifices/Biens) : un locataire ayant
	 * un historique ne doit jamais être effacé, sous peine d'orpheliner ces
	 * enregistrements et de perdre la traçabilité comptable.
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$payments_table = Limpeed_Payments::table();
		$linked_payments = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$payments_table} WHERE tenant_id = %d", $id )
		);

		if ( $linked_payments > 0 ) {
			return new WP_Error(
				'limpeed_tenant_has_payments',
				__( 'Impossible de supprimer ce locataire : des paiements y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$documents_table = Limpeed_Documents::table();
		$linked_documents = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$documents_table} WHERE entity_type = 'tenant' AND entity_id = %d", $id )
		);

		if ( $linked_documents > 0 ) {
			return new WP_Error(
				'limpeed_tenant_has_documents',
				__( 'Impossible de supprimer ce locataire : des documents y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$inspections_table = Limpeed_Inspections::table();
		$linked_inspections = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$inspections_table} WHERE tenant_id = %d", $id )
		);

		if ( $linked_inspections > 0 ) {
			return new WP_Error(
				'limpeed_tenant_has_inspections',
				__( 'Impossible de supprimer ce locataire : des états des lieux y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$amendments_table = Limpeed_Lease_Amendments::table();
		$linked_amendments = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$amendments_table} WHERE tenant_id = %d", $id )
		);

		if ( $linked_amendments > 0 ) {
			return new WP_Error(
				'limpeed_tenant_has_amendments',
				__( 'Impossible de supprimer ce locataire : des avenants y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

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
	 * Calcule le statut de la caution (dépôt de garantie) d'un locataire :
	 * montant requis (loyer × nombre de mois de caution réglé dans les
	 * Réglages) comparé au dépôt réellement versé.
	 *
	 * @param object $tenant
	 * @return array {
	 *     @type int    $deposit_months Nombre de mois de caution réglé dans les paramètres.
	 *     @type float  $required       Loyer × nombre de mois de caution.
	 *     @type float  $paid           Dépôt réellement versé (deposit_paid).
	 *     @type string $status         'aucun' | 'insuffisant' | 'suffisant'.
	 * }
	 */
	public static function get_deposit_status( $tenant ) {
		$deposit_months = max( 1, (int) get_option( 'limpeed_deposit_months', 1 ) );
		$required       = (float) $tenant->rent_amount * $deposit_months;
		$paid           = (float) $tenant->deposit_paid;

		if ( $paid <= 0 ) {
			$status = 'aucun';
		} elseif ( $paid < $required ) {
			$status = 'insuffisant';
		} else {
			$status = 'suffisant';
		}

		return array(
			'deposit_months' => $deposit_months,
			'required'       => $required,
			'paid'           => $paid,
			'status'         => $status,
		);
	}

	/**
	 * Calcule le montant des honoraires d'agence dus pour un locataire marqué
	 * "nouveau locataire" (première location) : loyer × nombre de mois réglé
	 * dans les Réglages. Ce montant est purement informatif (affiché sur la
	 * fiche du locataire) : il n'est jamais ajouté automatiquement à une
	 * caisse, la caisse "Honoraire agence" restant un enregistrement manuel.
	 *
	 * @param object $tenant
	 * @return array {
	 *     @type bool  $applicable  Vrai si le locataire est marqué "nouveau locataire".
	 *     @type int   $fee_months  Nombre de mois d'honoraires réglé dans les paramètres.
	 *     @type float $fee_amount  Loyer × nombre de mois d'honoraires (0 si non applicable).
	 * }
	 */
	public static function get_agency_fee_status( $tenant ) {
		$fee_months  = max( 1, (int) get_option( 'limpeed_agency_fee_months', 1 ) );
		$applicable  = ! empty( $tenant->is_new_tenant );
		$fee_amount  = $applicable ? (float) $tenant->rent_amount * $fee_months : 0.0;

		return array(
			'applicable' => $applicable,
			'fee_months' => $fee_months,
			'fee_amount' => $fee_amount,
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
	 * Locataires actifs dont le bail se termine dans les N prochains jours
	 * (aujourd'hui inclus), du plus proche au plus lointain. Utilisé par la
	 * carte KPI "Baux arrivant à échéance" du tableau de bord.
	 *
	 * @param int $days Fenêtre en nombre de jours.
	 * @return array
	 */
	public static function get_expiring_leases( $days = 30 ) {
		global $wpdb;
		$table = self::table();

		$today       = current_time( 'Y-m-d' );
		$limit_date  = gmdate( 'Y-m-d', strtotime( $today . " +{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'actif' AND lease_end IS NOT NULL AND lease_end BETWEEN %s AND %s ORDER BY lease_end ASC",
				$today,
				$limit_date
			)
		);
	}

	/**
	 * Vérifie quelles informations du dossier locataire sont encore manquantes
	 * (pièce d'identité, garant, profession...), pour l'aide contextuelle
	 * affichée sur la fiche du locataire.
	 *
	 * @param object $tenant
	 * @return array {
	 *     @type array $items    Liste de { 'label' => string, 'complete' => bool }.
	 *     @type int   $percent  Pourcentage de complétude (0-100).
	 * }
	 */
	public static function get_dossier_completeness( $tenant ) {
		$items = array(
			array(
				'label'    => __( 'Téléphone', 'limpeed-immobilier' ),
				'complete' => '' !== trim( (string) $tenant->phone ),
			),
			array(
				'label'    => __( 'Email', 'limpeed-immobilier' ),
				'complete' => '' !== trim( (string) $tenant->email ),
			),
			array(
				'label'    => __( 'Pièce d\'identité', 'limpeed-immobilier' ),
				'complete' => ! empty( $tenant->id_document_type ) && '' !== trim( (string) $tenant->id_document_number ),
			),
			array(
				'label'    => __( 'Date de naissance', 'limpeed-immobilier' ),
				'complete' => ! empty( $tenant->date_of_birth ),
			),
			array(
				'label'    => __( 'Profession', 'limpeed-immobilier' ),
				'complete' => '' !== trim( (string) $tenant->profession ),
			),
			array(
				'label'    => __( 'Garant', 'limpeed-immobilier' ),
				'complete' => '' !== trim( (string) $tenant->guarantor_name ),
			),
			array(
				'label'    => __( 'Dates de bail', 'limpeed-immobilier' ),
				'complete' => ! empty( $tenant->lease_start ) && ! empty( $tenant->lease_end ),
			),
		);

		$complete_count = count( array_filter( $items, function ( $item ) {
			return $item['complete'];
		} ) );

		return array(
			'items'   => $items,
			'percent' => round( ( $complete_count / count( $items ) ) * 100 ),
		);
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
