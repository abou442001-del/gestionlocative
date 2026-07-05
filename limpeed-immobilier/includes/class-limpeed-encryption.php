<?php
/**
 * Chiffrement symétrique au repos pour les données sensibles stockées en base
 * (ex : coordonnées bancaires des propriétaires).
 *
 * La clé est dérivée des sels WordPress (wp_salt()), déjà secrets, uniques par
 * installation et définis dans wp-config.php : cela évite d'introduire une
 * nouvelle clé à générer, stocker et sauvegarder séparément. Comme pour tout
 * chiffrement dérivé des sels WordPress, une rotation des sels rend les
 * valeurs déjà chiffrées illisibles ; decrypt() renvoie alors une chaîne vide
 * plutôt que de faire échouer l'affichage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Encryption {

	const CIPHER = 'aes-256-cbc';

	/**
	 * Préfixe marquant une valeur chiffrée par cette classe, pour la
	 * distinguer d'une valeur encore en clair (anciennes données non migrées,
	 * ou environnement sans OpenSSL).
	 */
	const PREFIX = 'lenc1:';

	/**
	 * @return string Clé binaire de 32 octets (AES-256).
	 */
	private static function get_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'openssl_encrypt' ) && in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Chiffre une chaîne. Renvoie la valeur d'origine (en clair) si OpenSSL
	 * n'est pas disponible, plutôt que de bloquer l'enregistrement.
	 *
	 * @param string $plaintext
	 * @return string
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;

		if ( '' === $plaintext || ! self::is_available() ) {
			return $plaintext;
		}

		$iv        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$encrypted = openssl_encrypt( $plaintext, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return $plaintext;
		}

		return self::PREFIX . base64_encode( $iv . $encrypted );
	}

	/**
	 * Déchiffre une chaîne précédemment chiffrée par encrypt(). Une valeur qui
	 * ne porte pas le préfixe attendu est renvoyée telle quelle : c'est le cas
	 * des données enregistrées avant l'introduction du chiffrement et pas
	 * encore migrées (voir Limpeed_Activator::encrypt_existing_bank_details()).
	 *
	 * @param string $stored
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;

		if ( '' === $stored || 0 !== strpos( $stored, self::PREFIX ) || ! self::is_available() ) {
			return $stored;
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = substr( $raw, 0, $iv_length );
		$encrypted = substr( $raw, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );

		return false !== $decrypted ? $decrypted : '';
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return 0 === strpos( (string) $value, self::PREFIX );
	}
}
