<?php
/**
 * Gestion du logo personnalisé du plugin (upload, stockage, suppression).
 * Le logo par défaut (SVG intégré) reste toujours disponible en secours :
 * aucune fonctionnalité ne dépend d'un logo personnalisé présent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Branding {

	/**
	 * Types de fichiers autorisés pour le logo. Les SVG sont volontairement
	 * exclus : un SVG uploadé peut contenir du script exécutable et n'est pas
	 * sanitizé ici, ce qui exposerait à une XSS stockée si le fichier est
	 * affiché directement dans une balise <img> ou ouvert dans un onglet.
	 *
	 * @return array Extension => type MIME.
	 */
	public static function get_allowed_mimes() {
		return array(
			'png'  => 'image/png',
			'jpg|jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
	}

	/**
	 * Taille maximale acceptée pour le fichier de logo (2 Mo).
	 *
	 * @return int Octets.
	 */
	public static function get_max_file_size() {
		return 2 * 1024 * 1024;
	}

	/**
	 * URL publique du logo personnalisé, ou chaîne vide si aucun n'est défini
	 * (dans ce cas, l'appelant doit utiliser le logo SVG par défaut).
	 *
	 * @return string
	 */
	public static function get_logo_url() {
		$url = get_option( 'limpeed_logo_url', '' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Chemin absolu local du fichier de logo personnalisé, ou chaîne vide si
	 * aucun n'est défini. Utilisé pour les PDF générés par Dompdf (isRemoteEnabled
	 * désactivé) : le fichier doit être encodé en data URI plutôt que référencé
	 * par son URL publique.
	 *
	 * @return string
	 */
	public static function get_logo_path() {
		$path = get_option( 'limpeed_logo_path', '' );
		return ( is_string( $path ) && file_exists( $path ) ) ? $path : '';
	}

	/**
	 * Logo encodé en data URI (base64), prêt à être inséré dans une balise
	 * <img> d'un document PDF généré par Dompdf. Chaîne vide si aucun logo
	 * personnalisé n'est défini ou si le fichier est illisible : l'appelant
	 * doit alors utiliser un repli textuel/SVG.
	 *
	 * @return string
	 */
	public static function get_logo_data_uri() {
		$path = self::get_logo_path();
		if ( ! $path ) {
			return '';
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
		if ( false === $contents ) {
			return '';
		}

		$mime_types = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime      = $mime_types[ $extension ] ?? 'image/png';

		return 'data:' . $mime . ';base64,' . base64_encode( $contents ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Traite l'upload d'un logo personnalisé.
	 *
	 * @param array $file Une entrée de $_FILES (ex. $_FILES['limpeed_logo']).
	 * @return true|WP_Error
	 */
	public static function save_uploaded_logo( $file ) {
		if ( empty( $file ) || empty( $file['tmp_name'] ) || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'limpeed_no_file', __( 'Aucun fichier reçu.', 'limpeed-immobilier' ) );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'limpeed_upload_error', __( 'Le fichier n\'a pas pu être téléversé.', 'limpeed-immobilier' ) );
		}

		if ( $file['size'] > self::get_max_file_size() ) {
			return new WP_Error( 'limpeed_file_too_large', __( 'Le fichier du logo dépasse la taille maximale autorisée (2 Mo).', 'limpeed-immobilier' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => self::get_allowed_mimes(),
		);

		$result = wp_handle_upload( $file, $overrides );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'limpeed_upload_failed', $result['error'] );
		}

		if ( empty( $result['file'] ) || ! wp_getimagesize( $result['file'] ) ) {
			if ( ! empty( $result['file'] ) && file_exists( $result['file'] ) ) {
				wp_delete_file( $result['file'] );
			}
			return new WP_Error( 'limpeed_not_an_image', __( 'Le fichier envoyé n\'est pas une image valide.', 'limpeed-immobilier' ) );
		}

		// Supprime l'ancien logo personnalisé, le cas échéant, avant d'enregistrer le nouveau.
		self::delete_logo_file();

		update_option( 'limpeed_logo_url', $result['url'] );
		update_option( 'limpeed_logo_path', $result['file'] );

		return true;
	}

	/**
	 * Supprime le logo personnalisé (fichier + réglages) ; le logo par défaut
	 * (SVG intégré) redevient utilisé.
	 */
	public static function remove_logo() {
		self::delete_logo_file();
		delete_option( 'limpeed_logo_url' );
		delete_option( 'limpeed_logo_path' );
	}

	/**
	 * Supprime uniquement le fichier physique du logo personnalisé, s'il existe.
	 */
	private static function delete_logo_file() {
		$path = get_option( 'limpeed_logo_path', '' );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
