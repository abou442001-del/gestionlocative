<?php
/**
 * CRUD pour les succursales (wp_limpeed_branches) et cloisonnement des
 * données par succursale.
 *
 * Chaque propriétaire est rattaché à une succursale (branch_id sur
 * wp_limpeed_owners, 0 = non assigné). Les édifices/biens héritent de la
 * succursale de leur propriétaire (owner_id direct), les locataires/paiements
 * de celle du bien concerné (via property_id → owner_id).
 *
 * Un agent ou un responsable de succursale (limpeed_branch_manager) ne voit
 * que les données de sa propre succursale (assignée via le usermeta
 * limpeed_branch_id) ; un administrateur (administrator ou limpeed_admin)
 * voit tout, sans restriction — voir current_user_branch_id().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Branches {

	/**
	 * Clé du usermeta stockant la succursale assignée à un agent.
	 */
	const USER_META_KEY = 'limpeed_branch_id';

	/**
	 * Clé du usermeta stockant le filtre d'affichage optionnel d'un
	 * utilisateur non restreint (administrateur) : simple confort de
	 * navigation pour consulter une succursale à la fois, jamais une
	 * restriction de droits (voir current_view_branch_id()).
	 */
	const VIEW_FILTER_META_KEY = 'limpeed_branch_view_filter';

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_branches';
	}

	/**
	 * Récupère une succursale par son id.
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
	 * Récupère la liste des succursales.
	 *
	 * @param array $args { @type string $search, @type int $per_page, @type int $paged }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'search'   => '',
			'per_page' => 100,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND (name LIKE %s OR address LIKE %s)';
			$params  = array( $like, $like );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY name ASC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de succursales correspondant à la recherche.
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
			$where .= ' AND (name LIKE %s OR address LIKE %s)';
			$params = array( $like, $like );
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Crée une nouvelle succursale.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'address'    => sanitize_textarea_field( $data['address'] ?? '' ),
			'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id = (int) $wpdb->insert_id;
			Limpeed_Activity_Log::log( 'created', 'branch', $id, sprintf( 'Succursale créée : %s', $record['name'] ) );
			return $id;
		}

		return false;
	}

	/**
	 * Met à jour une succursale existante.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();

		$record = array(
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'address'    => sanitize_textarea_field( $data['address'] ?? '' ),
			'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
			'updated_by' => get_current_user_id(),
			'updated_at' => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%d', '%s' );

		$result = false !== $wpdb->update( $table, $record, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'updated', 'branch', $id, sprintf( 'Succursale modifiée : %s', $record['name'] ) );
		}

		return $result;
	}

	/**
	 * Supprime une succursale. Refuse la suppression si des propriétaires y
	 * sont encore rattachés (jamais d'orphelin de cloisonnement).
	 *
	 * @param int $id
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$owners_table  = Limpeed_Owners::table();
		$linked_owners = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$owners_table} WHERE branch_id = %d", $id )
		);

		if ( $linked_owners > 0 ) {
			return new WP_Error(
				'limpeed_branch_has_owners',
				__( 'Impossible de supprimer cette succursale : des propriétaires y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$agents_in_branch = get_users(
			array(
				'meta_key'   => self::USER_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ids',
			)
		);

		if ( ! empty( $agents_in_branch ) ) {
			return new WP_Error(
				'limpeed_branch_has_agents',
				__( 'Impossible de supprimer cette succursale : des agents y sont encore rattachés.', 'limpeed-immobilier' )
			);
		}

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'branch', $id );
		}

		return $result;
	}

	/**
	 * Succursale assignée à l'utilisateur courant (0 = aucune restriction :
	 * administrateur WordPress ou administrateur Limpeed, qui voient toutes
	 * les données de l'agence sans distinction de succursale).
	 *
	 * Les agents (limpeed_agent) et responsables de succursale
	 * (limpeed_branch_manager) sont eux restreints à la succursale assignée
	 * via leur usermeta ; un agent sans succursale assignée (0) ne voit
	 * encore aucune donnée tant qu'un administrateur ne l'a pas rattaché à
	 * une succursale — choix volontairement restrictif par défaut plutôt que
	 * de risquer d'exposer toutes les données par erreur de configuration.
	 *
	 * @param int $user_id 0 pour l'utilisateur actuellement connecté.
	 * @return int
	 */
	public static function current_user_branch_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return 0;
		}

		// Seuls les rôles "administrateur WordPress" et "administrateur
		// Limpeed" voient l'agence sans restriction. Le responsable de
		// succursale (limpeed_branch_manager) a les mêmes capacités que
		// l'administrateur Limpeed mais reste, lui, cantonné à sa succursale
		// — la distinction se fait donc sur le rôle, pas sur une capacité
		// (les deux rôles partagent les mêmes capacités par ailleurs).
		if ( in_array( 'administrator', $user->roles, true ) || in_array( 'limpeed_admin', $user->roles, true ) ) {
			return 0;
		}

		return (int) get_user_meta( $user_id, self::USER_META_KEY, true );
	}

	/**
	 * Succursale à utiliser pour filtrer les LISTES (get_all()/count()) pour
	 * l'utilisateur courant : contrairement à current_user_branch_id(), ceci
	 * tient compte du filtre d'affichage optionnel qu'un administrateur non
	 * restreint peut activer depuis le sélecteur de succursale de la barre du
	 * haut, pour consulter une succursale à la fois sans changer ses droits.
	 *
	 * N'est JAMAIS utilisé pour les contrôles d'accès à une fiche précise
	 * (can_access_owner()/can_access_property()) ni pour les capacités
	 * administrateur : un administrateur qui filtre son affichage sur une
	 * succursale garde la possibilité d'ouvrir/modifier une fiche d'une autre
	 * succursale (ex : depuis la recherche globale) — le filtre est un
	 * confort de navigation, jamais une restriction de droits.
	 *
	 * @return int
	 */
	public static function current_view_branch_id() {
		$branch_id = self::current_user_branch_id();

		if ( 0 !== $branch_id ) {
			// Agent/responsable déjà cantonné à sa succursale : le filtre ne s'applique pas.
			return $branch_id;
		}

		$filter = (int) get_user_meta( get_current_user_id(), self::VIEW_FILTER_META_KEY, true );

		return ( $filter && self::get( $filter ) ) ? $filter : 0;
	}

	/**
	 * Fragment SQL "AND owner_id IN (...)" à ajouter à une requête pour la
	 * restreindre à la succursale de l'utilisateur courant, ou chaîne vide si
	 * l'utilisateur n'est pas restreint (administrateur). Utilisé partout où
	 * owner_id est une colonne directe de la table interrogée (Édifices,
	 * Biens, Bordereaux) — voir owner_scope_join() pour les tables qui n'ont
	 * qu'un property_id (Locataires, Paiements).
	 *
	 * @param string $owner_id_column Nom (éventuellement préfixé d'un alias) de la colonne owner_id.
	 * @return string
	 */
	public static function owner_scope_sql( $owner_id_column = 'owner_id' ) {
		$branch_id = self::current_view_branch_id();

		if ( 0 === $branch_id ) {
			return '';
		}

		global $wpdb;
		$owners_table = Limpeed_Owners::table();

		return $wpdb->prepare(
			" AND {$owner_id_column} IN ( SELECT id FROM {$owners_table} WHERE branch_id = %d )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $owner_id_column est un nom de colonne fixe fourni par le code appelant, jamais une entrée utilisateur.
			$branch_id
		);
	}

	/**
	 * Variante de owner_scope_sql() pour les tables qui ne portent qu'un
	 * property_id (Locataires, Paiements) : passe par une sous-requête sur
	 * wp_limpeed_properties, qui porte déjà owner_id en colonne directe.
	 *
	 * @param string $property_id_column Nom (éventuellement préfixé d'un alias) de la colonne property_id.
	 * @return string
	 */
	public static function property_scope_sql( $property_id_column = 'property_id' ) {
		$branch_id = self::current_view_branch_id();

		if ( 0 === $branch_id ) {
			return '';
		}

		global $wpdb;
		$properties_table = Limpeed_Properties::table();
		$owners_table      = Limpeed_Owners::table();

		return $wpdb->prepare(
			" AND {$property_id_column} IN ( SELECT id FROM {$properties_table} WHERE owner_id IN ( SELECT id FROM {$owners_table} WHERE branch_id = %d ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $property_id_column est un nom de colonne fixe fourni par le code appelant, jamais une entrée utilisateur.
			$branch_id
		);
	}

	/**
	 * Nombre de propriétaires rattachés à une succursale donnée.
	 *
	 * @param int $branch_id
	 * @return int
	 */
	public static function count_owners_in_branch( $branch_id ) {
		global $wpdb;
		$owners_table = Limpeed_Owners::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$owners_table} WHERE branch_id = %d", $branch_id )
		);
	}

	/**
	 * Nombre d'agents/responsables rattachés à une succursale donnée.
	 *
	 * @param int $branch_id
	 * @return int
	 */
	public static function count_agents_in_branch( $branch_id ) {
		$agents = get_users(
			array(
				'meta_key'   => self::USER_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $branch_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ids',
			)
		);
		return count( $agents );
	}

	/**
	 * Vrai si le propriétaire donné est visible par l'utilisateur courant
	 * (utilisé en défense en profondeur sur les accès à une fiche précise —
	 * évite qu'un agent accède à une fiche d'une autre succursale en devinant
	 * son id dans l'URL/API, même si elle n'apparaît dans aucune liste).
	 *
	 * @param int $owner_id
	 * @return bool
	 */
	public static function can_access_owner( $owner_id ) {
		$branch_id = self::current_user_branch_id();
		if ( 0 === $branch_id ) {
			return true;
		}

		$owner = Limpeed_Owners::get( (int) $owner_id );
		return $owner && (int) $owner->branch_id === $branch_id;
	}

	/**
	 * Vrai si le bien donné (et donc son propriétaire) est visible par
	 * l'utilisateur courant.
	 *
	 * @param int $property_id
	 * @return bool
	 */
	public static function can_access_property( $property_id ) {
		$branch_id = self::current_user_branch_id();
		if ( 0 === $branch_id ) {
			return true;
		}

		$property = Limpeed_Properties::get( (int) $property_id );
		return $property && self::can_access_owner( (int) $property->owner_id );
	}
}
