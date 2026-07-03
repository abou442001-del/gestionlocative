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
	 * Clé de métadonnée marquant un compte en attente d'approbation.
	 * Un compte "en attente" n'a aucun rôle Limpeed tant qu'il n'est pas approuvé.
	 */
	const PENDING_META_KEY = 'limpeed_pending_approval';

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
	 * Nombre de comptes agents créés depuis le début du mois en cours (carte
	 * de synthèse).
	 *
	 * @return int
	 */
	public static function count_created_this_month() {
		$query = new WP_User_Query(
			array(
				'role__in'   => array_keys( self::get_available_roles() ),
				'fields'     => 'ID',
				'date_query' => array(
					array(
						'after'     => current_time( 'Y-m-01 00:00:00' ),
						'inclusive' => true,
					),
				),
			)
		);
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

	/**
	 * Crée un compte WordPress via l'inscription publique en frontend.
	 * Le compte n'a AUCUN rôle Limpeed tant qu'un administrateur ne l'a pas
	 * explicitement approuvé (voir approve()) : c'est le mot de passe choisi
	 * par le candidat qui est utilisé, aucun accès n'est accordé avant validation.
	 *
	 * @param array $data { 'user_login', 'user_email', 'display_name', 'user_pass' }
	 * @return int|WP_Error Id utilisateur créé, ou erreur.
	 */
	public static function create_pending( $data ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $data['user_login'],
				'user_email'   => $data['user_email'],
				'display_name' => ! empty( $data['display_name'] ) ? $data['display_name'] : $data['user_login'],
				'user_pass'    => $data['user_pass'],
				'role'         => '',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::PENDING_META_KEY, '1' );

		Limpeed_Activity_Log::log(
			'created',
			'agent',
			$user_id,
			sprintf( 'Demande d\'inscription reçue : %s (en attente d\'approbation)', $data['user_login'] )
		);

		$admin_email = get_option( 'admin_email' );
		if ( $admin_email ) {
			wp_mail(
				$admin_email,
				sprintf( '[%s] Nouvelle demande d\'inscription agent', get_bloginfo( 'name' ) ),
				sprintf(
					"Une nouvelle demande de compte agent a été soumise sur Limpeed Immobilier.\n\nIdentifiant : %s\nEmail : %s\n\nApprouvez ou rejetez cette demande depuis : %s",
					$data['user_login'],
					$data['user_email'],
					admin_url( 'admin.php?page=limpeed-agents' )
				)
			);
		}

		return $user_id;
	}

	/**
	 * Récupère les comptes en attente d'approbation.
	 *
	 * @return array
	 */
	public static function get_pending() {
		$query = new WP_User_Query(
			array(
				'meta_key'   => self::PENDING_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'registered',
				'order'      => 'DESC',
			)
		);
		return $query->get_results();
	}

	/**
	 * Approuve une demande d'inscription : attribue le rôle Limpeed choisi
	 * et retire le marqueur "en attente".
	 *
	 * @param int    $user_id
	 * @param string $role
	 * @return bool
	 */
	public static function approve( $user_id, $role ) {
		if ( ! array_key_exists( $role, self::get_available_roles() ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! get_user_meta( $user_id, self::PENDING_META_KEY, true ) ) {
			return false;
		}

		$user->set_role( $role );
		delete_user_meta( $user_id, self::PENDING_META_KEY );

		Limpeed_Activity_Log::log(
			'updated',
			'agent',
			$user_id,
			sprintf( 'Demande d\'inscription approuvée : %s (%s)', $user->user_login, self::get_available_roles()[ $role ] )
		);

		wp_mail(
			$user->user_email,
			sprintf( '[%s] Votre compte agent a été approuvé', get_bloginfo( 'name' ) ),
			sprintf(
				"Bonjour %s,\n\nVotre demande de compte agent sur Limpeed Immobilier a été approuvée. Vous pouvez maintenant vous connecter : %s",
				$user->display_name,
				wp_login_url()
			)
		);

		return true;
	}

	/**
	 * Rejette une demande d'inscription : supprime le compte WordPress créé.
	 * Sans danger car un compte en attente n'a jamais eu de capacité Limpeed
	 * et ne peut donc avoir créé aucune donnée (bien, locataire, paiement...).
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function reject( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! get_user_meta( $user_id, self::PENDING_META_KEY, true ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$login = $user->user_login;
		$result = wp_delete_user( $user_id );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'agent', $user_id, sprintf( 'Demande d\'inscription rejetée : %s', $login ) );
		}

		return (bool) $result;
	}
}
