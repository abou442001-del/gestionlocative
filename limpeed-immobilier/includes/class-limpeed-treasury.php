<?php
/**
 * Indicateurs de trésorerie (module "Trésorerie & Finance").
 *
 * Purement analytique : ne crée aucune nouvelle table, s'appuie entièrement
 * sur les paiements déjà enregistrés (Limpeed_Payments) et les bordereaux
 * déjà générés (Limpeed_Statements, qui matérialisent les sommes reversées
 * aux propriétaires). La "trésorerie" de l'agence est simplement la
 * différence entre ce qui a été encaissé pour le compte des propriétaires et
 * ce qui leur a déjà été reversé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Treasury {

	/**
	 * Total encaissé depuis toujours (paiements "payé" ou "partiel").
	 *
	 * @return float
	 */
	public static function get_total_collected() {
		global $wpdb;
		$table = Limpeed_Payments::table();
		$total = $wpdb->get_var( "SELECT SUM(amount) FROM {$table} WHERE status IN ('paye','partiel')" );
		return $total ? (float) $total : 0.0;
	}

	/**
	 * Total des commissions prélevées depuis toujours.
	 *
	 * @return float
	 */
	public static function get_total_commission() {
		global $wpdb;
		$table = Limpeed_Payments::table();
		$total = $wpdb->get_var( "SELECT SUM(commission_amount) FROM {$table} WHERE status IN ('paye','partiel')" );
		return $total ? (float) $total : 0.0;
	}

	/**
	 * Total déjà reversé aux propriétaires (somme des bordereaux générés).
	 *
	 * @return float
	 */
	public static function get_total_reversed() {
		global $wpdb;
		$table = Limpeed_Statements::table();
		$total = $wpdb->get_var( "SELECT SUM(net_amount) FROM {$table}" );
		return $total ? (float) $total : 0.0;
	}

	/**
	 * Solde de trésorerie actuel de l'agence : montant net déjà encaissé pour
	 * le compte des propriétaires (loyers moins commission) mais pas encore
	 * reversé. Une valeur élevée signale des reversements en retard plutôt
	 * qu'un "bénéfice" — cette somme appartient aux propriétaires.
	 *
	 * @return float
	 */
	public static function get_cash_position() {
		$net_collected = self::get_total_collected() - self::get_total_commission();
		return $net_collected - self::get_total_reversed();
	}

	/**
	 * Série mensuelle encaissements / prévisions pour le graphique de
	 * trésorerie : $past_months mois écoulés (données réelles, mois en cours
	 * inclus) suivis de $future_months mois à venir (projection basée sur les
	 * locataires actifs actuels, en supposant un portefeuille inchangé).
	 *
	 * @param int $past_months
	 * @param int $future_months
	 * @return array Liste de { period, expected_total, collected, is_forecast }.
	 */
	public static function get_cashflow_series( $past_months = 6, $future_months = 3 ) {
		$series      = array();
		$first_of_month = new DateTime( current_time( 'Y-m-01' ) );
		$current_period = current_time( 'Y-m' );

		for ( $i = $past_months - 1; $i >= -$future_months; $i-- ) {
			$date   = clone $first_of_month;
			$date->modify( "-{$i} months" );
			$period = $date->format( 'Y-m' );
			$summary = Limpeed_Payments::get_period_summary( $period );

			$series[] = array(
				'period'         => $period,
				'expected_total' => $summary['expected_total'],
				'collected'      => $summary['collected'],
				'is_forecast'    => $period > $current_period,
			);
		}

		return $series;
	}

	/**
	 * Historique des reversements (décaissements) aux propriétaires, du plus
	 * récent au plus ancien.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_disbursements( $limit = 10 ) {
		return Limpeed_Statements::get_all( array( 'per_page' => $limit, 'paged' => 1 ) );
	}
}
