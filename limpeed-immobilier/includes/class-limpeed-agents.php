<?php
/**
 * Gestion des comptes agents (basée sur les comptes WordPress natifs).
 *
 * Aucune suppression de compte WordPress n'est effectuée par cette classe :
 * "retirer l'accès" signifie retirer le rôle Limpeed, jamais supprimer le
 * compte utilisateur ni les données qu'il a créées (cohérent avec la règle
 * de non-perte de données du plugin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Agents {

	/**
	 * Rôles gérés par cette page (hors administrateur WordPress natif).
	 *
	 * @return array
	 */
	public static function get_available_roles() {
		return array(
			'limpeed_agent' => __( 'Agent Limpeed', 'limpeed-immobilier' ),
			'limpeed_admin' => __( 'Administrateur Limpeed', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Récupère un agent par son id utilisateur WordPress.
	 *
	 * @param int $user_id
	 * @return WP_User|null
	 */
	public static function get( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! array_intersect( $user->roles, array_keys( self::get_available_roles() ) ) ) {
			return null;
		}
		return $user;
	}

	/**
	 * Récupère la liste des agents avec recherche et pagination.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		$defaults = array(
			'search'   => '',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'role__in' => array_keys( self::get_available_roles() ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'number'   => max( 1, (int) $args['per_page'] ),
			'offset'   => ( max( 1, (int) $args['paged'] ) - 1 ) * max( 1, (int) $args['per_page'] ),
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['search']         = '*' . $args['search'] . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new WP_User_Query( $query_args );
		return $query->get_results();
	}

	/**
	 * Compte le nombre total d'agents correspondant à la recherche.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		$query_args = array(
			'role__in' => array_keys( self::get_available_roles() ),
			'fields'   => 'ID',
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['search']         = '*' . $args['search'] . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new WP_User_Query( $query_args );
		return (int) $query->get_total();
	}

	/**
	 * Crée un nouveau compte agent WordPress avec le rôle choisi.
	 * Un mot de passe aléatoire est généré ; l'agent reçoit un email
	 * l'invitant à définir son propre mot de passe.
	 *
	 * @param array $data { 'user_login', 'user_email', 'display_name', 'role' }
	 * @return int|WP_Error Id utilisateur créé, ou erreur.
	 */
	public static function create( $data ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $data['user_login'],
				'user_email'   => $data['user_email'],
				'display_name' => ! empty( $data['display_name'] ) ? $data['display_name'] : $data['user_login'],
				'user_pass'    => wp_generate_password( 20, true ),
				'role'         => $data['role'],
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_new_user_notification( $user_id, null, 'user' );

		Limpeed_Activity_Log::log(
			'created',
			'agent',
			$user_id,
			sprintf( 'Compte agent créé : %s (%s)', $data['user_login'], self::get_available_roles()[ $data['role'] ] ?? $data['role'] )
		);

		return $user_id;
	}

	/**
	 * Change le rôle Limpeed d'un agent existant.
	 *
	 * @param int    $user_id
	 * @param string $role
	 * @return bool
	 */
	public static function update_role( $user_id, $role ) {
		if ( ! array_key_exists( $role, self::get_available_roles() ) ) {
			return false;
		}

		$user = self::get( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->set_role( $role );

		Limpeed_Activity_Log::log(
			'updated',
			'agent',
			$user_id,
			sprintf( 'Rôle de l\'agent %s changé en %s', $user->user_login, self::get_available_roles()[ $role ] )
		);

		return true;
	}

	/**
	 * Retire le rôle Limpeed d'un agent (révoque l'accès au plugin).
	 * Le compte WordPress et les données créées par cet agent sont conservés.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function revoke_access( $user_id ) {
		$user = self::get( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user->set_role( '' );

		Limpeed_Activity_Log::log( 'updated', 'agent', $user_id, sprintf( 'Accès Limpeed révoqué pour %s', $user->user_login ) );

		return true;
	}
}
