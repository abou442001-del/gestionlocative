<?php
/**
 * CRUD pour les biens (wp_limpeed_properties).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Properties {

	/**
	 * Libellé d'affichage d'un bien : son identifiant s'il en a un, sinon son
	 * adresse, sinon un repli générique (un bien peut n'avoir ni l'un ni
	 * l'autre puisque seul l'un des deux est obligatoire à la création).
	 *
	 * @param object $property
	 * @return string
	 */
	public static function get_display_label( $property ) {
		if ( ! empty( $property->reference ) ) {
			return $property->reference;
		}
		if ( ! empty( $property->address ) ) {
			return $property->address;
		}
		/* translators: %d: identifiant numérique du bien */
		return sprintf( __( 'Bien #%d', 'limpeed-immobilier' ), (int) $property->id );
	}

	/**
	 * Types de biens disponibles.
	 *
	 * @return array
	 */
	public static function get_types() {
		return array(
			'studio'          => __( 'Studio', 'limpeed-immobilier' ),
			'2_pieces'        => __( '2 pièces', 'limpeed-immobilier' ),
			'3_pieces'        => __( '3 pièces', 'limpeed-immobilier' ),
			'4_pieces_et_plus' => __( '4 pièces et plus', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Types de biens historiques, retirés du formulaire mais toujours reconnus
	 * pour l'affichage des biens créés avant ce changement (non destructif :
	 * aucune donnée existante n'est modifiée ni perdue).
	 *
	 * @return array
	 */
	public static function get_legacy_types() {
		return array(
			'appartement'      => __( 'Appartement', 'limpeed-immobilier' ),
			'maison'           => __( 'Maison', 'limpeed-immobilier' ),
			'local_commercial' => __( 'Local commercial', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Libellé d'un type de bien, qu'il s'agisse d'un type actuel ou d'un
	 * ancien type historique encore présent en base sur des biens existants.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$types = self::get_types() + self::get_legacy_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : $type;
	}

	/**
	 * Loyer mensuel moyen constaté par type de bien, toutes agences confondues
	 * (biens à loyer renseigné uniquement). Sert d'aide contextuelle en direct
	 * lors de la saisie du loyer sur le formulaire d'un bien.
	 *
	 * @return array Tableau [type => loyer moyen (float)], types sans donnée absents.
	 */
	public static function get_average_rent_by_type() {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			"SELECT type, AVG(monthly_rent) AS avg_rent FROM {$table} WHERE monthly_rent > 0 GROUP BY type"
		);

		$averages = array();
		foreach ( $rows as $row ) {
			$averages[ $row->type ] = (float) $row->avg_rent;
		}

		return $averages;
	}

	/**
	 * Loyer mensuel moyen tous types de biens confondus (carte de synthèse).
	 *
	 * @return float
	 */
	public static function get_average_rent() {
		global $wpdb;
		$table = self::table();
		$avg   = $wpdb->get_var( "SELECT AVG(monthly_rent) FROM {$table} WHERE monthly_rent > 0" );
		return $avg ? (float) $avg : 0.0;
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
			'search'      => '',
			'owner_id'    => 0,
			'building_id' => 0,
			'status'      => '',
			'orderby'     => 'address',
			'order'       => 'ASC',
			'per_page'    => 20,
			'paged'       => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'reference', 'address', 'type', 'monthly_rent', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'address';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where  = 'WHERE 1=1' . Limpeed_Branches::owner_scope_sql( 'owner_id' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (address LIKE %s OR reference LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND building_id = %d';
			$params[] = (int) $args['building_id'];
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

		$where  = 'WHERE 1=1' . Limpeed_Branches::owner_scope_sql( 'owner_id' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (address LIKE %s OR reference LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}

		if ( ! empty( $args['building_id'] ) ) {
			$where   .= ' AND building_id = %d';
			$params[] = (int) $args['building_id'];
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
	 * Indique si une ligne de sous-édifice postée en même temps qu'un édifice
	 * est vide (l'utilisateur a ajouté une ligne sans la remplir). Seuls
	 * l'identifiant et l'adresse sont pris en compte : les champs numériques
	 * ont toujours une valeur par défaut ("0") et ne doivent donc jamais,
	 * à eux seuls, transformer une ligne inutilisée en ligne "remplie".
	 *
	 * @param array $row
	 * @return bool
	 */
	private static function is_sub_unit_row_empty( $row ) {
		foreach ( array( 'reference', 'address' ) as $field ) {
			if ( '' !== trim( (string) ( $row[ $field ] ?? '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Valide une liste de sous-édifices postés en même temps qu'un édifice
	 * (ajout groupé depuis le formulaire Édifices). Les lignes entièrement
	 * vides sont ignorées silencieusement.
	 *
	 * @param array $rows
	 * @return array Messages d'erreur (vide si tout est valide).
	 */
	public static function validate_sub_units( $rows ) {
		$errors = array();
		$index  = 0;

		foreach ( $rows as $row ) {
			$index++;

			if ( self::is_sub_unit_row_empty( $row ) ) {
				continue;
			}

			foreach ( array( 'monthly_rent', 'charges', 'deposit_amount' ) as $field ) {
				if ( isset( $row[ $field ] ) && '' !== $row[ $field ] && ! is_numeric( $row[ $field ] ) ) {
					/* translators: %d: numéro de la ligne de sous-édifice */
					$errors[] = sprintf( __( 'Sous-édifice #%d : les montants (loyer, charges, dépôt) doivent être des nombres.', 'limpeed-immobilier' ), $index );
					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Crée en une fois tous les biens (sous-édifices) non vides rattachés à
	 * l'édifice donné. Ne doit être appelée qu'après validate_sub_units().
	 *
	 * @param int   $building_id
	 * @param array $rows
	 * @return int Nombre de biens créés.
	 */
	public static function insert_sub_units( $building_id, $rows ) {
		$created = 0;

		foreach ( $rows as $row ) {
			if ( self::is_sub_unit_row_empty( $row ) ) {
				continue;
			}

			$row['building_id'] = $building_id;

			if ( self::insert( $row ) ) {
				$created++;
			}
		}

		return $created;
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

		$building = Limpeed_Buildings::get( (int) ( $data['building_id'] ?? 0 ) );
		if ( ! $building ) {
			return false;
		}

		$types    = array_keys( self::get_types() + self::get_legacy_types() );
		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'owner_id'       => (int) $building->owner_id,
			'building_id'    => (int) $building->id,
			'reference'      => sanitize_text_field( $data['reference'] ?? '' ),
			'address'        => sanitize_textarea_field( $data['address'] ),
			'type'           => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'studio',
			'monthly_rent'   => (float) ( $data['monthly_rent'] ?? 0 ),
			'charges'        => (float) ( $data['charges'] ?? 0 ),
			'deposit_amount' => (float) ( $data['deposit_amount'] ?? 0 ),
			'status'         => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'vacant',
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'property', $id, sprintf( 'Bien créé : %s', $record['address'] ) );
			return $id;
		}

		return false;
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

		$building = Limpeed_Buildings::get( (int) ( $data['building_id'] ?? 0 ) );
		if ( ! $building ) {
			return false;
		}

		$types    = array_keys( self::get_types() + self::get_legacy_types() );
		$statuses = array_keys( self::get_statuses() );

		$record = array(
			'owner_id'       => (int) $building->owner_id,
			'building_id'    => (int) $building->id,
			'reference'      => sanitize_text_field( $data['reference'] ?? '' ),
			'address'        => sanitize_textarea_field( $data['address'] ),
			'type'           => in_array( $data['type'] ?? '', $types, true ) ? $data['type'] : 'studio',
			'monthly_rent'   => (float) ( $data['monthly_rent'] ?? 0 ),
			'charges'        => (float) ( $data['charges'] ?? 0 ),
			'deposit_amount' => (float) ( $data['deposit_amount'] ?? 0 ),
			'status'         => in_array( $data['status'] ?? '', $statuses, true ) ? $data['status'] : 'vacant',
			'updated_by'     => get_current_user_id(),
			'updated_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'property', $id, sprintf( 'Bien modifié : %s', $record['address'] ) );
		}

		return $result;
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

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'property', $id );
		}

		return $result;
	}
}
