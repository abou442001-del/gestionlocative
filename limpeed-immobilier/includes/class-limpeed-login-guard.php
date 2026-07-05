<?php
/**
 * Protection contre les tentatives de connexion par force brute.
 *
 * Verrouille temporairement un couple (IP, identifiant) après plusieurs
 * échecs consécutifs, via les transients WordPress (aucune table
 * supplémentaire). S'applique à toute tentative de connexion — le
 * formulaire [limpeed_login] comme wp-login.php natif — puisque les deux
 * passent par le filtre 'authenticate' de WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Login_Guard {

	/**
	 * Nombre d'échecs consécutifs tolérés avant verrouillage.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Durée du verrouillage, en minutes.
	 */
	const LOCKOUT_MINUTES = 15;

	/**
	 * Fenêtre de comptage des échecs, en minutes (au-delà, le compteur repart à zéro).
	 */
	const ATTEMPT_WINDOW_MINUTES = 15;

	/**
	 * Enregistre les hooks. Appelé sur 'plugins_loaded', indépendamment du
	 * contexte admin/frontend, pour couvrir wp-login.php comme les
	 * formulaires du plugin.
	 */
	public static function init() {
		// Priorité 30, volontairement après wp_authenticate_username_password()
		// (priorité 20) : ce callback de WordPress core ne renvoie l'erreur
		// qu'on lui transmet que si les identifiants sont vides, sinon il
		// effectue son propre wp_check_password() et écrase toute WP_Error
		// reçue en entrée par un WP_User si le mot de passe s'avère correct.
		// En s'exécutant après, ce filtre écrase à son tour ce résultat par
		// une erreur si l'identifiant/IP est verrouillé, quel que soit le
		// mot de passe fourni.
		add_filter( 'authenticate', array( __CLASS__, 'block_if_locked_out' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'register_failed_attempt' ) );
		add_action( 'wp_login', array( __CLASS__, 'clear_attempts_on_success' ) );
	}

	/**
	 * Clé de limitation basée sur l'IP et l'identifiant tenté (et non sur
	 * l'utilisateur réel, puisqu'un identifiant invalide doit aussi compter).
	 *
	 * @param string $username
	 * @return string
	 */
	private static function get_identifier( $username ) {
		return md5( self::get_client_ip() . '|' . strtolower( trim( (string) $username ) ) );
	}

	/**
	 * IP du client, utilisée uniquement comme clé de comptage interne (jamais
	 * affichée ni journalisée) : une éventuelle usurpation d'en-tête ne fait
	 * au pire que partager un compteur entre deux origines, elle ne permet
	 * pas de contourner le verrouillage.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '0.0.0.0';
	}

	/**
	 * Filtre 'authenticate' : bloque la tentative avant toute vérification de
	 * mot de passe si l'identifiant/IP est actuellement verrouillé.
	 *
	 * @param WP_User|WP_Error|null $user
	 * @param string                $username
	 * @param string                $password
	 * @return WP_User|WP_Error|null
	 */
	public static function block_if_locked_out( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		$locked_until = get_transient( 'limpeed_lockout_' . self::get_identifier( $username ) );

		if ( $locked_until && (int) $locked_until > time() ) {
			$minutes_left = (int) ceil( ( (int) $locked_until - time() ) / MINUTE_IN_SECONDS );
			return new WP_Error(
				'limpeed_too_many_attempts',
				sprintf(
					/* translators: %d: nombre de minutes avant de pouvoir réessayer */
					__( 'Trop de tentatives de connexion. Réessayez dans %d minute(s).', 'limpeed-immobilier' ),
					$minutes_left
				)
			);
		}

		return $user;
	}

	/**
	 * Action 'wp_login_failed' : incrémente le compteur d'échecs et déclenche
	 * le verrouillage une fois le seuil atteint.
	 *
	 * @param string $username
	 */
	public static function register_failed_attempt( $username ) {
		if ( empty( $username ) ) {
			return;
		}

		$identifier   = self::get_identifier( $username );
		$attempts_key = 'limpeed_login_attempts_' . $identifier;
		$attempts     = (int) get_transient( $attempts_key ) + 1;

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			set_transient(
				'limpeed_lockout_' . $identifier,
				time() + ( self::LOCKOUT_MINUTES * MINUTE_IN_SECONDS ),
				self::LOCKOUT_MINUTES * MINUTE_IN_SECONDS
			);
			delete_transient( $attempts_key );
			return;
		}

		set_transient( $attempts_key, $attempts, self::ATTEMPT_WINDOW_MINUTES * MINUTE_IN_SECONDS );
	}

	/**
	 * Action 'wp_login' : une connexion réussie efface tout compteur en cours
	 * pour cet identifiant.
	 *
	 * @param string $user_login
	 */
	public static function clear_attempts_on_success( $user_login ) {
		$identifier = self::get_identifier( $user_login );
		delete_transient( 'limpeed_login_attempts_' . $identifier );
		delete_transient( 'limpeed_lockout_' . $identifier );
	}
}
