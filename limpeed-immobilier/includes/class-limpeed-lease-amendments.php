<?php
/**
 * Avenants au bail (wp_limpeed_lease_amendments).
 *
 * Journal append-only des modifications formelles apportées au bail d'un
 * locataire (révision de loyer, prolongation, etc.), distinct du journal
 * d'activité général : un avenant est un document métier daté et motivé,
 * pas une trace technique de qui a modifié quoi dans l'interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Lease_Amendments {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_lease_amendments';
	}

	/**
	 * Récupère un avenant par son id.
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
	 * Liste des avenants d'un locataire, du plus récent au plus ancien.
	 *
	 * @param int $tenant_id
	 * @return array
	 */
	public static function get_for_tenant( $tenant_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tenant_id = %d ORDER BY amendment_date DESC, id DESC",
				$tenant_id
			)
		);
	}

	/**
	 * Enregistre un nouvel avenant.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();

		$tenant_id = (int) $data['tenant_id'];

		$record = array(
			'tenant_id'       => $tenant_id,
			'amendment_date'  => sanitize_text_field( $data['amendment_date'] ?? current_time( 'Y-m-d' ) ),
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'new_rent_amount' => '' !== ( $data['new_rent_amount'] ?? '' ) ? (float) $data['new_rent_amount'] : null,
			'new_lease_end'   => ! empty( $data['new_lease_end'] ) ? sanitize_text_field( $data['new_lease_end'] ) : null,
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%f', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( ! $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		// Un avenant qui révise le loyer ou prolonge le bail met aussi à jour
		// la fiche locataire elle-même, pour que le reste de l'application
		// (calcul des paiements, alerte d'échéance) reflète immédiatement le
		// nouvel avenant plutôt qu'un simple historique déconnecté des données.
		$tenant_updates = array();
		if ( null !== $record['new_rent_amount'] ) {
			$tenant_updates['rent_amount'] = $record['new_rent_amount'];
		}
		if ( null !== $record['new_lease_end'] ) {
			$tenant_updates['lease_end'] = $record['new_lease_end'];
		}

		if ( ! empty( $tenant_updates ) ) {
			$tenant = Limpeed_Tenants::get( $tenant_id );
			if ( $tenant ) {
				Limpeed_Tenants::update(
					$tenant_id,
					array_merge(
						array(
							'property_id'        => $tenant->property_id,
							'full_name'          => $tenant->full_name,
							'phone'              => $tenant->phone,
							'email'              => $tenant->email,
							'lease_start'        => $tenant->lease_start,
							'lease_end'          => $tenant->lease_end,
							'rent_amount'        => $tenant->rent_amount,
							'deposit_paid'       => $tenant->deposit_paid,
							'status'             => $tenant->status,
							'id_document_type'   => $tenant->id_document_type,
							'id_document_number' => $tenant->id_document_number,
							'date_of_birth'      => $tenant->date_of_birth,
							'profession'         => $tenant->profession,
							'dependents_count'   => $tenant->dependents_count,
							'guarantor_name'     => $tenant->guarantor_name,
							'guarantor_phone'    => $tenant->guarantor_phone,
						),
						$tenant_updates
					)
				);
			}
		}

		Limpeed_Activity_Log::log( 'created', 'tenant', $tenant_id, sprintf( 'Avenant enregistré : %s', wp_trim_words( $record['description'], 12 ) ) );

		return $id;
	}

	/**
	 * Supprime un avenant (ne revient pas sur la mise à jour éventuelle du
	 * bail : seul le journal est retiré, en cas d'erreur de saisie).
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
