<?php
/**
 * Gestion des rôles et capacités des agents Limpeed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Roles {

	/**
	 * Liste des capacités propres au plugin.
	 *
	 * @return array
	 */
	public static function get_capabilities() {
		return array(
			'manage_limpeed_owners',
			'manage_limpeed_properties',
			'manage_limpeed_tenants',
			'manage_limpeed_payments',
			'manage_limpeed_statements',
			'manage_limpeed_treasury',
			'manage_limpeed_agents',
		);
	}

	/**
	 * Capacités accordées au rôle agent (pas la gestion des agents, ni
	 * Trésorerie/Comptabilité qui restent réservées aux administrateurs
	 * Limpeed — mais les agents peuvent générer/consulter les bordereaux).
	 *
	 * @return array
	 */
	public static function get_agent_capabilities() {
		return array(
			'read'                       => true,
			'manage_limpeed_owners'      => true,
			'manage_limpeed_properties'  => true,
			'manage_limpeed_tenants'     => true,
			'manage_limpeed_payments'    => true,
			'manage_limpeed_statements'  => true,
		);
	}

	/**
	 * Capacités accordées au rôle admin Limpeed (toutes).
	 *
	 * @return array
	 */
	public static function get_admin_capabilities() {
		$caps = self::get_agent_capabilities();
		$caps['manage_limpeed_treasury'] = true;
		$caps['manage_limpeed_agents']   = true;
		return $caps;
	}

	/**
	 * Crée (ou met à jour) les rôles limpeed_agent, limpeed_branch_manager et
	 * limpeed_admin. Ajoute également les capacités au rôle administrateur
	 * WordPress natif.
	 *
	 * limpeed_branch_manager a les mêmes capacités que limpeed_admin
	 * (Trésorerie, Comptabilité, gestion des agents) mais reste, à la
	 * différence de ce dernier, cantonné à sa succursale assignée : voir
	 * Limpeed_Branches::current_user_branch_id(), qui fait la distinction sur
	 * le rôle plutôt que sur une capacité (les deux partagent les mêmes).
	 */
	public static function add_roles() {
		remove_role( 'limpeed_agent' );
		remove_role( 'limpeed_branch_manager' );
		remove_role( 'limpeed_admin' );

		add_role(
			'limpeed_agent',
			__( 'Agent Limpeed', 'limpeed-immobilier' ),
			self::get_agent_capabilities()
		);

		add_role(
			'limpeed_branch_manager',
			__( 'Responsable de succursale', 'limpeed-immobilier' ),
			self::get_admin_capabilities()
		);

		add_role(
			'limpeed_admin',
			__( 'Administrateur Limpeed', 'limpeed-immobilier' ),
			self::get_admin_capabilities()
		);

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::get_capabilities() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * Supprime les rôles et capacités du plugin (utilisé uniquement à la désinstallation).
	 */
	public static function remove_roles() {
		remove_role( 'limpeed_agent' );
		remove_role( 'limpeed_branch_manager' );
		remove_role( 'limpeed_admin' );

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::get_capabilities() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
	}
}
