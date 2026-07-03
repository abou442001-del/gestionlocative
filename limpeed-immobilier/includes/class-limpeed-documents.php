<?php
/**
 * Gestion documentaire centralisée (wp_limpeed_documents).
 *
 * Chaque document est rattaché à une entité (propriétaire, édifice, bien ou
 * locataire) par un couple entity_type/entity_id plutôt que par des colonnes
 * dédiées à chaque type : un document ne concerne toujours qu'une seule
 * entité à la fois, et le nombre de types d'entités est amené à évoluer.
 *
 * Les fichiers sont stockés hors de la racine web publique (comme les
 * bordereaux, voir Limpeed_Statements) : le dossier est protégé par
 * .htaccess et servi uniquement via un contrôleur qui vérifie les capacités
 * de l'utilisateur avant de streamer le fichier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Documents {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_documents';
	}

	/**
	 * Types d'entités pouvant porter des documents.
	 *
	 * @return array
	 */
	public static function get_entity_types() {
		return array(
			'owner'    => __( 'Propriétaire', 'limpeed-immobilier' ),
			'building' => __( 'Édifice', 'limpeed-immobilier' ),
			'property' => __( 'Bien', 'limpeed-immobilier' ),
			'tenant'   => __( 'Locataire', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Extensions de fichiers autorisées et leur type MIME (les mêmes
	 * catégories que le logo personnalisé, plus PDF/Word puisqu'il s'agit ici
	 * de pièces administratives et non d'un visuel de marque).
	 *
	 * @return array
	 */
	public static function get_allowed_mimes() {
		return array(
			'pdf'      => 'application/pdf',
			'png'      => 'image/png',
			'jpg|jpeg' => 'image/jpeg',
			'webp'     => 'image/webp',
			'doc'      => 'application/msword',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
	}

	/**
	 * Taille maximale acceptée pour un document (5 Mo).
	 *
	 * @return int Octets.
	 */
	public static function get_max_file_size() {
		return 5 * 1024 * 1024;
	}

	/**
	 * Répertoire de stockage des documents (hors accès web direct).
	 *
	 * @return string Chemin absolu, sans slash final.
	 */
	public static function get_storage_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'limpeed-documents';
	}

	/**
	 * Crée le répertoire de stockage et le protège contre l'accès web direct.
	 *
	 * @return bool
	 */
	private static function ensure_storage_dir() {
		$dir = self::get_storage_dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\ndeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return true;
	}

	/**
	 * Récupère un document par son id.
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
	 * Liste des documents d'une entité, du plus récent au plus ancien.
	 *
	 * @param string $entity_type
	 * @param int    $entity_id
	 * @return array
	 */
	public static function get_for_entity( $entity_type, $entity_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC, id DESC",
				sanitize_key( $entity_type ),
				(int) $entity_id
			)
		);
	}

	/**
	 * Chemin absolu du fichier d'un document.
	 *
	 * @param object $document
	 * @return string
	 */
	public static function get_file_path( $document ) {
		return self::get_storage_dir() . '/' . $document->file_path;
	}

	/**
	 * Traite l'upload d'un document et l'attache à une entité.
	 *
	 * @param array  $file        Une entrée de $_FILES.
	 * @param string $entity_type
	 * @param int    $entity_id
	 * @param string $title
	 * @return int|WP_Error Id du document créé, ou une erreur.
	 */
	public static function upload( $file, $entity_type, $entity_id, $title ) {
		if ( ! array_key_exists( $entity_type, self::get_entity_types() ) ) {
			return new WP_Error( 'limpeed_invalid_entity', __( 'Type d\'entité invalide.', 'limpeed-immobilier' ) );
		}

		if ( empty( $file ) || empty( $file['tmp_name'] ) || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'limpeed_no_file', __( 'Aucun fichier reçu.', 'limpeed-immobilier' ) );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'limpeed_upload_error', __( 'Le fichier n\'a pas pu être téléversé.', 'limpeed-immobilier' ) );
		}

		if ( $file['size'] > self::get_max_file_size() ) {
			return new WP_Error( 'limpeed_file_too_large', __( 'Le fichier dépasse la taille maximale autorisée (5 Mo).', 'limpeed-immobilier' ) );
		}

		if ( ! self::ensure_storage_dir() ) {
			return new WP_Error( 'limpeed_storage_error', __( 'Impossible de créer le répertoire de stockage des documents.', 'limpeed-immobilier' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		// wp_handle_upload() déplace normalement le fichier vers le dossier
		// public des médias : on lui fournit un 'upload_dir' de substitution
		// (filtre) pour qu'il l'enregistre directement dans le dossier protégé,
		// sans jamais transiter par un emplacement accessible publiquement.
		// Le chemin est calculé AVANT d'ajouter le filtre plutôt qu'à
		// l'intérieur de celui-ci : get_storage_dir() appelle lui-même
		// wp_upload_dir(), qui redéclencherait ce même filtre et boucierait
		// indéfiniment (jusqu'au timeout d'exécution) si l'appel avait lieu
		// pendant que le filtre est actif.
		$storage_dir  = self::get_storage_dir();
		$override_dir = function ( $dirs ) use ( $storage_dir ) {
			$dirs['path']   = $storage_dir;
			$dirs['url']    = '';
			$dirs['subdir'] = '';
			return $dirs;
		};

		add_filter( 'upload_dir', $override_dir );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::get_allowed_mimes(),
			)
		);
		remove_filter( 'upload_dir', $override_dir );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'limpeed_upload_failed', $result['error'] );
		}

		global $wpdb;
		$table  = self::table();
		$record = array(
			'entity_type' => sanitize_key( $entity_type ),
			'entity_id'   => (int) $entity_id,
			'title'       => sanitize_text_field( $title ?: $file['name'] ),
			'file_name'   => sanitize_file_name( $file['name'] ),
			'file_path'   => basename( $result['file'] ),
			'file_size'   => (int) $file['size'],
			'mime_type'   => sanitize_text_field( $result['type'] ?? '' ),
			'uploaded_by' => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( $table, $record, array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ) );

		if ( ! $inserted ) {
			if ( file_exists( $result['file'] ) ) {
				wp_delete_file( $result['file'] );
			}
			return new WP_Error( 'limpeed_save_failed', __( 'Le fichier a été enregistré mais n\'a pas pu être associé en base.', 'limpeed-immobilier' ) );
		}

		$id = (int) $wpdb->insert_id;

		$entity_types = self::get_entity_types();
		Limpeed_Activity_Log::log(
			'created',
			'document',
			$id,
			sprintf( 'Document "%s" ajouté (%s)', $record['title'], $entity_types[ $entity_type ] ?? $entity_type )
		);

		return $id;
	}

	/**
	 * Supprime un document (base de données et fichier).
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$document = self::get( $id );
		if ( ! $document ) {
			return false;
		}

		$file_path = self::get_file_path( $document );
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'document', $id );
		}

		return $result;
	}
}
