<?php
/**
 * Export global des données : génère une archive ZIP contenant un fichier
 * CSV par table du plugin, pour permettre à l'agence de garder une copie
 * complète et lisible de ses données (sauvegarde, transmission à un
 * comptable, analyse hors WordPress...).
 *
 * Lecture seule : ne modifie jamais aucune donnée.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Export {

	/**
	 * Génère l'archive ZIP et l'envoie directement au navigateur.
	 */
	public static function stream_full_export() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'L\'export est indisponible : l\'extension PHP Zip n\'est pas installée sur ce serveur.', 'limpeed-immobilier' ) );
		}

		// wp_tempnam() vit dans wp-admin/includes/file.php, non chargé par défaut
		// côté frontend.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_file = wp_tempnam( 'limpeed-export.zip' );
		$zip      = new ZipArchive();

		if ( true !== $zip->open( $tmp_file, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Impossible de créer l\'archive d\'export.', 'limpeed-immobilier' ) );
		}

		foreach ( self::get_export_definitions() as $definition ) {
			$zip->addFromString( $definition['filename'], self::build_csv( $definition ) );
		}

		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="limpeed-export-' . gmdate( 'Y-m-d' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );

		readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $tmp_file );
		exit;
	}

	/**
	 * @param array $definition Une entrée de get_export_definitions().
	 * @return string Contenu CSV (avec BOM UTF-8, pour Excel).
	 */
	private static function build_csv( $definition ) {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM {$definition['table']}", ARRAY_A );

		$out = fopen( 'php://temp', 'w+' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $definition['headers'], ',', '"', '\\' );

		foreach ( $rows as $row ) {
			fputcsv( $out, call_user_func( $definition['map_row'], $row ), ',', '"', '\\' );
		}

		rewind( $out );
		$content = stream_get_contents( $out );
		fclose( $out );

		return $content;
	}

	/**
	 * @param int|null $user_id
	 * @return string
	 */
	private static function user_label( $user_id ) {
		if ( empty( $user_id ) ) {
			return '';
		}
		$user = get_userdata( (int) $user_id );
		return $user ? $user->display_name : '';
	}

	/**
	 * @param string       $value
	 * @param array<string,string> $labels
	 * @return string
	 */
	private static function label( $value, $labels ) {
		return isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
	}

	/**
	 * Liste des exports, une entrée par table : nom de fichier, requête,
	 * en-têtes en français et fonction de mise en forme de chaque ligne.
	 *
	 * @return array
	 */
	private static function get_export_definitions() {
		global $wpdb;

		$owner_names    = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}limpeed_owners", OBJECT_K );
		$building_names = $wpdb->get_results( "SELECT id, name, owner_id FROM {$wpdb->prefix}limpeed_buildings", OBJECT_K );
		$property_labels = $wpdb->get_results( "SELECT id, reference, address, owner_id, building_id FROM {$wpdb->prefix}limpeed_properties", OBJECT_K );
		$tenant_names   = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}limpeed_tenants", OBJECT_K );

		$owner_label = function ( $id ) use ( $owner_names ) {
			return isset( $owner_names[ $id ] ) ? $owner_names[ $id ]->full_name : '';
		};
		$building_label = function ( $id ) use ( $building_names ) {
			return isset( $building_names[ $id ] ) ? $building_names[ $id ]->name : '';
		};
		$property_label = function ( $id ) use ( $property_labels ) {
			if ( ! isset( $property_labels[ $id ] ) ) {
				return '';
			}
			$property = $property_labels[ $id ];
			return $property->reference ? $property->reference : $property->address;
		};
		$tenant_label = function ( $id ) use ( $tenant_names ) {
			return isset( $tenant_names[ $id ] ) ? $tenant_names[ $id ]->full_name : '';
		};

		$property_types    = Limpeed_Properties::get_types();
		$property_statuses = Limpeed_Properties::get_statuses();
		$tenant_statuses    = Limpeed_Tenants::get_statuses();
		$payment_statuses   = Limpeed_Payments::get_statuses();
		$payment_methods    = Limpeed_Payments::get_payment_methods();
		$mandate_statuses   = Limpeed_Mandates::get_statuses();
		$inspection_types   = Limpeed_Inspections::get_types();
		$expense_categories = Limpeed_Expenses::get_categories();
		$fund_categories    = wp_list_pluck( Limpeed_Funds::get_categories(), 'label' );

		return array(
			array(
				'table'    => $wpdb->prefix . 'limpeed_owners',
				'filename' => 'proprietaires.csv',
				'headers'  => array( 'ID', 'Nom complet', 'Téléphone', 'Email', 'Adresse', 'Coordonnées bancaires', 'Créé le' ),
				'map_row'  => function ( $row ) {
					return array(
						$row['id'],
						$row['full_name'],
						$row['phone'],
						$row['email'],
						$row['address'],
						Limpeed_Encryption::decrypt( $row['bank_details'] ),
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_buildings',
				'filename' => 'edifices.csv',
				'headers'  => array( 'ID', 'Propriétaire', 'Nom', 'Adresse', 'Description', 'Taux de commission (%)', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $owner_label ) {
					return array(
						$row['id'],
						$owner_label( $row['owner_id'] ),
						$row['name'],
						$row['address'],
						$row['description'],
						$row['commission_rate'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_properties',
				'filename' => 'biens.csv',
				'headers'  => array( 'ID', 'Propriétaire', 'Édifice', 'Référence', 'Adresse', 'Type', 'Loyer mensuel', 'Charges', 'Caution', 'Statut', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $owner_label, $building_label, $property_types, $property_statuses ) {
					return array(
						$row['id'],
						$owner_label( $row['owner_id'] ),
						$building_label( $row['building_id'] ),
						$row['reference'],
						$row['address'],
						self::label( $row['type'], $property_types ),
						$row['monthly_rent'],
						$row['charges'],
						$row['deposit_amount'],
						self::label( $row['status'], $property_statuses ),
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_tenants',
				'filename' => 'locataires.csv',
				'headers'  => array( 'ID', 'Bien', 'Nom complet', 'Téléphone', 'Email', 'Début de bail', 'Fin de bail', 'Loyer', 'Caution versée', 'Statut', 'Type de pièce d\'identité', 'Numéro de pièce', 'Date de naissance', 'Profession', 'Personnes à charge', 'Garant', 'Téléphone garant', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $property_label, $tenant_statuses ) {
					return array(
						$row['id'],
						$property_label( $row['property_id'] ),
						$row['full_name'],
						$row['phone'],
						$row['email'],
						$row['lease_start'],
						$row['lease_end'],
						$row['rent_amount'],
						$row['deposit_paid'],
						self::label( $row['status'], $tenant_statuses ),
						$row['id_document_type'],
						$row['id_document_number'],
						$row['date_of_birth'],
						$row['profession'],
						$row['dependents_count'],
						$row['guarantor_name'],
						$row['guarantor_phone'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_payments',
				'filename' => 'paiements.csv',
				'headers'  => array( 'ID', 'Locataire', 'Bien', 'Montant', 'Date de paiement', 'Période', 'Statut', 'Méthode de paiement', 'Commission', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $tenant_label, $property_label, $payment_statuses, $payment_methods ) {
					return array(
						$row['id'],
						$tenant_label( $row['tenant_id'] ),
						$property_label( $row['property_id'] ),
						$row['amount'],
						$row['payment_date'],
						$row['period'],
						self::label( $row['status'], $payment_statuses ),
						self::label( $row['payment_method'], $payment_methods ),
						$row['commission_amount'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_statements',
				'filename' => 'bordereaux.csv',
				'headers'  => array( 'ID', 'Propriétaire', 'Période début', 'Période fin', 'Total encaissé', 'Total commission', 'Déduction (libellé)', 'Déduction (montant)', 'Net reversé', 'Généré par', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $owner_label ) {
					return array(
						$row['id'],
						$owner_label( $row['owner_id'] ),
						$row['period_start'],
						$row['period_end'],
						$row['total_collected'],
						$row['total_commission'],
						$row['other_deduction_label'],
						$row['other_deduction_amount'],
						$row['net_amount'],
						self::user_label( $row['generated_by'] ),
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_mandates',
				'filename' => 'mandats.csv',
				'headers'  => array( 'ID', 'Édifice', 'Date de début', 'Date de fin', 'Taux de commission (%)', 'Statut', 'Date de signature', 'Notes', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $building_label, $mandate_statuses ) {
					return array(
						$row['id'],
						$building_label( $row['building_id'] ),
						$row['start_date'],
						$row['end_date'],
						$row['commission_rate'],
						self::label( $row['status'], $mandate_statuses ),
						$row['signed_date'],
						$row['notes'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_lease_amendments',
				'filename' => 'avenants.csv',
				'headers'  => array( 'ID', 'Locataire', 'Date', 'Description', 'Nouveau loyer', 'Nouvelle fin de bail', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $tenant_label ) {
					return array(
						$row['id'],
						$tenant_label( $row['tenant_id'] ),
						$row['amendment_date'],
						$row['description'],
						$row['new_rent_amount'],
						$row['new_lease_end'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_inspections',
				'filename' => 'etats-des-lieux.csv',
				'headers'  => array( 'ID', 'Locataire', 'Bien', 'Type', 'Date', 'Notes générales', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $tenant_label, $property_label, $inspection_types ) {
					return array(
						$row['id'],
						$tenant_label( $row['tenant_id'] ),
						$property_label( $row['property_id'] ),
						self::label( $row['type'], $inspection_types ),
						$row['inspection_date'],
						$row['general_notes'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_documents',
				'filename' => 'documents.csv',
				'headers'  => array( 'ID', 'Type de fiche', 'Titre', 'Nom de fichier d\'origine', 'Taille (octets)', 'Type de fichier', 'Ajouté par', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $owner_label, $building_label, $property_label, $tenant_label ) {
					$entity_labels = array(
						'owner'    => $owner_label( $row['entity_id'] ),
						'building' => $building_label( $row['entity_id'] ),
						'property' => $property_label( $row['entity_id'] ),
						'tenant'   => $tenant_label( $row['entity_id'] ),
					);
					$entity_types = Limpeed_Documents::get_entity_types();
					$entity_type_label = self::label( $row['entity_type'], $entity_types );
					$entity_name        = $entity_labels[ $row['entity_type'] ] ?? '';
					return array(
						$row['id'],
						trim( $entity_type_label . ( $entity_name ? ' — ' . $entity_name : '' ) ),
						$row['title'],
						$row['file_name'],
						$row['file_size'],
						$row['mime_type'],
						self::user_label( $row['uploaded_by'] ),
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_expenses',
				'filename' => 'charges.csv',
				'headers'  => array( 'ID', 'Date', 'Catégorie', 'Libellé', 'Montant', 'Édifice', 'Bien', 'Notes', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $building_label, $property_label, $expense_categories ) {
					return array(
						$row['id'],
						$row['expense_date'],
						self::label( $row['category'], $expense_categories ),
						$row['label'],
						$row['amount'],
						$building_label( $row['building_id'] ),
						$property_label( $row['property_id'] ),
						$row['notes'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_fund_transactions',
				'filename' => 'caisses.csv',
				'headers'  => array( 'ID', 'Caisse', 'Sens', 'Montant', 'Libellé', 'Date', 'Créé le' ),
				'map_row'  => function ( $row ) use ( $fund_categories ) {
					return array(
						$row['id'],
						self::label( $row['fund_category'], $fund_categories ),
						'in' === $row['direction'] ? 'Entrée' : 'Sortie',
						$row['amount'],
						$row['label'],
						$row['transaction_date'],
						$row['created_at'],
					);
				},
			),
			array(
				'table'    => $wpdb->prefix . 'limpeed_activity_log',
				'filename' => 'journal-activite.csv',
				'headers'  => array( 'ID', 'Utilisateur', 'Action', 'Type d\'objet', 'Id d\'objet', 'Description', 'Date' ),
				'map_row'  => function ( $row ) {
					return array(
						$row['id'],
						self::user_label( $row['user_id'] ),
						$row['action'],
						$row['object_type'],
						$row['object_id'],
						$row['description'],
						$row['created_at'],
					);
				},
			),
		);
	}
}
