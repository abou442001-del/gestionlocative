<?php
/**
 * Comptabilité de base : grand livre et bilan simplifié (module "Finances").
 *
 * Comme Limpeed_Treasury, ne crée pas de table dédiée pour le grand livre :
 * il s'agit d'une vue consolidée (UNION SQL) des trois mouvements financiers
 * déjà tracés ailleurs — encaissements (Limpeed_Payments), reversements aux
 * propriétaires (Limpeed_Statements) et charges de l'agence
 * (Limpeed_Expenses, seule nouvelle table de ce module). Le bilan simplifié
 * (produits/charges/résultat) est calculé par période à partir des mêmes
 * sources : les produits de l'agence sont ses commissions prélevées, pas les
 * loyers encaissés qui appartiennent aux propriétaires.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Accounting {

	/**
	 * Types d'écritures du grand livre.
	 *
	 * @return array
	 */
	public static function get_entry_types() {
		return array(
			'encaissement' => __( 'Encaissement (loyer)', 'limpeed-immobilier' ),
			'reversement'  => __( 'Reversement propriétaire', 'limpeed-immobilier' ),
			'charge'       => __( 'Charge', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Requête SQL de la vue consolidée (sous-requête), sans ORDER/LIMIT.
	 *
	 * @return string
	 */
	private static function ledger_union_sql() {
		global $wpdb;

		$payments_table   = Limpeed_Payments::table();
		$tenants_table    = $wpdb->prefix . 'limpeed_tenants';
		$statements_table = Limpeed_Statements::table();
		$owners_table     = $wpdb->prefix . 'limpeed_owners';
		$expenses_table   = Limpeed_Expenses::table();

		$payments_scope   = Limpeed_Branches::property_scope_sql( 'p.property_id' );
		$statements_scope = Limpeed_Branches::owner_scope_sql( 's.owner_id' );
		$expenses_scope   = Limpeed_Branches::direct_scope_sql( 'e.branch_id' );

		return "
			SELECT p.payment_date AS entry_date, 'encaissement' AS entry_type, CONCAT('Loyer — ', t.full_name) AS label, p.amount AS amount, p.id AS reference_id
			FROM {$payments_table} p
			INNER JOIN {$tenants_table} t ON t.id = p.tenant_id
			WHERE p.status IN ('paye','partiel') AND p.payment_date IS NOT NULL {$payments_scope}

			UNION ALL

			SELECT DATE(s.created_at) AS entry_date, 'reversement' AS entry_type, CONCAT('Reversement — ', o.full_name) AS label, s.net_amount AS amount, s.id AS reference_id
			FROM {$statements_table} s
			INNER JOIN {$owners_table} o ON o.id = s.owner_id
			WHERE 1=1 {$statements_scope}

			UNION ALL

			SELECT e.expense_date AS entry_date, 'charge' AS entry_type, e.label AS label, e.amount AS amount, e.id AS reference_id
			FROM {$expenses_table} e
			WHERE 1=1 {$expenses_scope}
		"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments déjà pré-préparés par Limpeed_Branches::*_scope_sql().
	}

	/**
	 * Construit la clause WHERE appliquée à la vue consolidée.
	 *
	 * @param array $args
	 * @return array { where: string, params: array }
	 */
	private static function build_ledger_where( $args ) {
		global $wpdb;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['entry_type'] ) && array_key_exists( $args['entry_type'], self::get_entry_types() ) ) {
			$where   .= ' AND entry_type = %s';
			$params[] = $args['entry_type'];
		}

		if ( ! empty( $args['period'] ) ) {
			$where   .= ' AND DATE_FORMAT(entry_date, "%%Y-%%m") = %s';
			$params[] = sanitize_text_field( $args['period'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND label LIKE %s';
			$params[] = $like;
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Liste paginée des écritures du grand livre, de la plus récente à la plus
	 * ancienne.
	 *
	 * @param array $args {
	 *     @type string $entry_type
	 *     @type string $period      Format YYYY-MM.
	 *     @type string $search
	 *     @type int    $per_page
	 *     @type int    $paged
	 * }
	 * @return array
	 */
	public static function get_ledger( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'entry_type' => '',
			'period'     => '',
			'search'     => '',
			'per_page'   => 20,
			'paged'      => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$built  = self::build_ledger_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = 'SELECT * FROM ( ' . self::ledger_union_sql() . " ) x {$where} ORDER BY entry_date DESC, reference_id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total d'écritures correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count_ledger( $args = array() ) {
		global $wpdb;

		$built  = self::build_ledger_where( $args );
		$where  = $built['where'];
		$params = $built['params'];

		$sql = 'SELECT COUNT(*) FROM ( ' . self::ledger_union_sql() . " ) x {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Exporte le grand livre (filtré le cas échéant) en CSV, envoyé
	 * directement au navigateur, et termine la requête.
	 *
	 * @param array $args { @type string $entry_type, @type string $period, @type string $search }
	 */
	public static function stream_ledger_csv( $args = array() ) {
		$entries = self::get_ledger( wp_parse_args( $args, array( 'per_page' => 100000 ) ) );
		$entry_types = self::get_entry_types();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="grand-livre-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		// BOM UTF-8 : Excel n'affiche correctement les accents français sans lui.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite

		fputcsv( $out, array( 'Date', 'Type', 'Libellé', 'Montant' ), ',', '"', '\\' );

		foreach ( $entries as $entry ) {
			fputcsv(
				$out,
				array(
					$entry->entry_date,
					$entry_types[ $entry->entry_type ] ?? $entry->entry_type,
					$entry->label,
					$entry->amount,
				),
				',',
				'"',
				'\\'
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
	}

	/**
	 * Bilan simplifié d'une période : produits (commissions prélevées),
	 * charges (dépenses de l'agence) et résultat net.
	 *
	 * @param string $period Format YYYY-MM.
	 * @return array { period, revenue, expenses, result }
	 */
	public static function get_period_summary( $period ) {
		global $wpdb;
		$payments_table = Limpeed_Payments::table();

		$sql = $wpdb->prepare(
			"SELECT SUM(commission_amount) FROM {$payments_table} WHERE status IN ('paye','partiel') AND DATE_FORMAT(payment_date, '%%Y-%%m') = %s",
			$period
		);
		$sql .= Limpeed_Branches::property_scope_sql( 'property_id' );

		$revenue = $wpdb->get_var( $sql );
		$revenue = $revenue ? (float) $revenue : 0.0;

		$expenses = Limpeed_Expenses::get_total_for_period( $period );

		return array(
			'period'   => $period,
			'revenue'  => $revenue,
			'expenses' => $expenses,
			'result'   => $revenue - $expenses,
		);
	}

	/**
	 * Détail ligne à ligne des produits (une ligne par paiement générant une
	 * commission) et des charges (une ligne par dépense) d'une période, pour
	 * le document imprimable "Résultats financiers du mois" — reproduit le
	 * même principe que le rapport de l'ancien logiciel de l'agence : chaque
	 * honoraire perçu et chaque dépense sont listés individuellement plutôt
	 * que résumés en un seul total.
	 *
	 * @param string $period Format YYYY-MM.
	 * @return array { period, revenue_lines, expense_lines, total_revenue, total_expenses, result }
	 */
	public static function get_monthly_results( $period ) {
		global $wpdb;

		$payments_table  = Limpeed_Payments::table();
		$properties_table = Limpeed_Properties::table();

		$revenue_sql = $wpdb->prepare(
			"SELECT p.payment_date AS entry_date, p.commission_amount AS amount, pr.reference AS property_reference, pr.id AS property_id
			FROM {$payments_table} p
			LEFT JOIN {$properties_table} pr ON pr.id = p.property_id
			WHERE p.status IN ('paye','partiel') AND p.commission_amount > 0 AND DATE_FORMAT(p.payment_date, '%%Y-%%m') = %s",
			$period
		);
		$revenue_sql .= Limpeed_Branches::property_scope_sql( 'p.property_id' );
		$revenue_sql .= ' ORDER BY p.payment_date ASC';

		$revenue_rows = $wpdb->get_results( $revenue_sql );

		$revenue_lines = array_map(
			function ( $row ) {
				$label = $row->property_reference ? sprintf( __( 'Honoraire %s', 'limpeed-immobilier' ), $row->property_reference ) : __( 'Honoraire', 'limpeed-immobilier' );
				return array(
					'date'   => $row->entry_date,
					'label'  => $label,
					'amount' => (float) $row->amount,
				);
			},
			$revenue_rows
		);

		$expenses = Limpeed_Expenses::get_all(
			array(
				'period'   => $period,
				'orderby'  => 'expense_date',
				'order'    => 'ASC',
				'per_page' => 500,
			)
		);

		$expense_lines = array_map(
			function ( $expense ) {
				return array(
					'date'   => $expense->expense_date,
					'label'  => $expense->label,
					'amount' => (float) $expense->amount,
				);
			},
			$expenses
		);

		$total_revenue  = array_sum( wp_list_pluck( $revenue_lines, 'amount' ) );
		$total_expenses = array_sum( wp_list_pluck( $expense_lines, 'amount' ) );

		return array(
			'period'         => $period,
			'revenue_lines'  => $revenue_lines,
			'expense_lines'  => $expense_lines,
			'total_revenue'  => $total_revenue,
			'total_expenses' => $total_expenses,
			'result'         => $total_revenue - $total_expenses,
		);
	}
}
