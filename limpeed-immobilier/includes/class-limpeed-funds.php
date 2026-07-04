<?php
/**
 * Caisses de l'agence (wp_limpeed_fund_transactions).
 *
 * Chaque catégorie de caisse (Commission agence, Caution, Timbres fiscaux...)
 * est un petit livre de caisse indépendant alimenté manuellement par l'agent
 * (entrées/sorties d'argent), à l'image de ce que faisait l'ancien logiciel
 * du client. Le "Solde" global est simplement la somme des soldes de toutes
 * les catégories, jamais une donnée saisie directement.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Funds {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_fund_transactions';
	}

	/**
	 * Catégories de caisses disponibles (liste fixe, à l'image de l'ancien
	 * logiciel). Chaque catégorie porte une couleur d'accent reprise du
	 * système de couleurs déjà utilisé pour les cartes de l'application.
	 *
	 * La plupart sont des livres de caisse manuels (mode "manual"), mais
	 * trois catégories ont déjà leur donnée source ailleurs dans le plugin
	 * et sont donc calculées automatiquement (mode "auto") plutôt que
	 * ressaisies : Commission agence (commissions déjà prélevées sur les
	 * paiements), Dépense (charges déjà enregistrées dans Comptabilité) et
	 * Caution (dépôts de garantie des locataires actuellement actifs).
	 *
	 * @return array clé => { label, color, mode, auto_description? }
	 */
	public static function get_categories() {
		return array(
			'commission_agence'  => array(
				'label'            => __( 'Commission agence', 'limpeed-immobilier' ),
				'color'            => 'blue',
				'mode'             => 'auto',
				'auto_description' => __( "Calculé automatiquement à partir des commissions déjà prélevées sur les paiements de loyers.", 'limpeed-immobilier' ),
			),
			'caution'            => array(
				'label'            => __( 'Caution', 'limpeed-immobilier' ),
				'color'            => 'orange',
				'mode'             => 'auto',
				'auto_description' => __( 'Calculé automatiquement : somme des dépôts de garantie versés par les locataires actuellement actifs.', 'limpeed-immobilier' ),
			),
			'tva_commission'     => array(
				'label' => __( 'Tva sur commission', 'limpeed-immobilier' ),
				'color' => 'red',
				'mode'  => 'manual',
			),
			'depense'            => array(
				'label'            => __( 'Dépense', 'limpeed-immobilier' ),
				'color'            => 'red',
				'mode'             => 'auto',
				'auto_description' => __( 'Calculé automatiquement à partir des charges déjà enregistrées dans Comptabilité → Charges.', 'limpeed-immobilier' ),
			),
			'caution_cie_sodeci' => array(
				'label' => __( 'Caution CIE/SODECI', 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'honoraire_agence'   => array(
				'label' => __( 'Honoraire agence', 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'timbres_fiscaux'    => array(
				'label' => __( "Timbres fiscaux (Légalisation bail)", 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'droit_enregistrement' => array(
				'label' => __( "Droit d'enregistrement", 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'frais_dossiers'     => array(
				'label' => __( 'Frais de dossiers', 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'frais_assurance'    => array(
				'label' => __( "Frais d'assurance", 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
			'autres_fonds'       => array(
				'label' => __( 'Autres fonds', 'limpeed-immobilier' ),
				'color' => 'blue',
				'mode'  => 'manual',
			),
		);
	}

	/**
	 * Récupère une transaction par son id.
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
	 * Construit la clause WHERE partagée par get_all() et count().
	 *
	 * @param array $args
	 * @return array { where: string, params: array }
	 */
	private static function build_where( $args ) {
		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['category'] ) && array_key_exists( $args['category'], self::get_categories() ) ) {
			$where   .= ' AND fund_category = %s';
			$params[] = $args['category'];
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Récupère la liste des transactions d'une caisse (ou de toutes) avec pagination.
	 *
	 * @param array $args {
	 *     @type string $category
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'category' => '',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$built  = self::build_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY transaction_date DESC, id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de transactions correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$built  = self::build_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Solde d'une catégorie de caisse. Pour les catégories en mode "auto",
	 * le solde est recalculé depuis sa donnée source ailleurs dans le plugin
	 * plutôt que depuis le livre de mouvements (entrées - sorties).
	 *
	 * @param string $category
	 * @return float
	 */
	public static function get_balance( $category ) {
		$categories = self::get_categories();
		$mode       = $categories[ $category ]['mode'] ?? 'manual';

		if ( 'auto' === $mode ) {
			return self::get_auto_balance( $category );
		}

		global $wpdb;
		$table = self::table();

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) FROM {$table} WHERE fund_category = %s",
				$category
			)
		);

		return $total ? (float) $total : 0.0;
	}

	/**
	 * Solde calculé d'une catégorie en mode "auto" à partir de sa donnée
	 * source (commissions, charges, dépôts de garantie...).
	 *
	 * @param string $category
	 * @return float
	 */
	private static function get_auto_balance( $category ) {
		switch ( $category ) {
			case 'commission_agence':
				return Limpeed_Treasury::get_total_commission();
			case 'depense':
				return Limpeed_Expenses::get_total();
			case 'caution':
				return Limpeed_Tenants::get_total_deposits_held();
			default:
				return 0.0;
		}
	}

	/**
	 * Solde de toutes les caisses, plus le "Solde" global (somme de toutes
	 * les caisses).
	 *
	 * @return array { balances: { clé => montant }, total: float }
	 */
	public static function get_all_balances() {
		$balances = array();
		$total    = 0.0;

		foreach ( array_keys( self::get_categories() ) as $key ) {
			$balance            = self::get_balance( $key );
			$balances[ $key ]   = $balance;
			$total             += $balance;
		}

		return array(
			'balances' => $balances,
			'total'    => $total,
		);
	}

	/**
	 * Enregistre un mouvement de caisse (entrée ou sortie).
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table      = self::table();
		$categories = self::get_categories();
		$category   = $data['fund_category'] ?? '';

		if ( ! array_key_exists( $category, $categories ) || 'auto' === ( $categories[ $category ]['mode'] ?? 'manual' ) ) {
			return false;
		}

		$record = array(
			'fund_category'    => $data['fund_category'],
			'direction'        => 'out' === ( $data['direction'] ?? 'in' ) ? 'out' : 'in',
			'amount'           => max( 0, (float) ( $data['amount'] ?? 0 ) ),
			'label'            => sanitize_text_field( $data['label'] ?? '' ),
			'transaction_date' => sanitize_text_field( $data['transaction_date'] ?? current_time( 'Y-m-d' ) ),
			'created_by'       => get_current_user_id(),
			'created_at'       => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%f', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $record, $formats );

		if ( $result ) {
			$id         = (int) $wpdb->insert_id;
			$categories = self::get_categories();
			$fund_label = $categories[ $record['fund_category'] ]['label'];
			$sign       = 'out' === $record['direction'] ? '-' : '+';
			Limpeed_Activity_Log::log(
				'created',
				'fund_transaction',
				$id,
				sprintf(
					'Mouvement de caisse "%1$s" : %2$s%3$s',
					$fund_label,
					$sign,
					Limpeed_Payments::format_amount( $record['amount'] )
				)
			);
			return $id;
		}

		return false;
	}

	/**
	 * Supprime un mouvement de caisse.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'fund_transaction', $id );
		}

		return $result;
	}
}
