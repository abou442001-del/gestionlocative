<?php
/**
 * Rappel quotidien par email des loyers non encore encaissés (agence en
 * Côte d'Ivoire : pas d'intégration SMS ici, celle-ci nécessiterait la clé
 * API d'un fournisseur tiers — Orange Money, MTN, Wave... — que le client
 * devra fournir séparément le cas échéant). Repose sur WP-Cron, natif à
 * WordPress, sans dépendance externe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Reminders {

	const CRON_HOOK = 'limpeed_daily_late_payment_reminder';

	/**
	 * Planifie l'événement cron quotidien s'il ne l'est pas déjà (appelé à
	 * l'activation du plugin). Idempotent.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:00' ), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Retire l'événement cron (appelé à la désactivation du plugin).
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Callback de l'événement cron : envoie un email récapitulatif des
	 * locataires en attente de paiement sur la période en cours à tous les
	 * agents et administrateurs. N'envoie rien s'il n'y a aucun impayé.
	 */
	public static function send_daily_reminder() {
		$period = Limpeed_Payments::get_current_period();
		$unpaid = Limpeed_Payments::get_unpaid_tenants( $period );

		if ( empty( $unpaid ) ) {
			return;
		}

		$recipients = self::get_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		$lines = array();
		foreach ( $unpaid as $tenant ) {
			$property   = Limpeed_Properties::get( $tenant->property_id );
			$lines[] = sprintf(
				'- %1$s (%2$s) : loyer %3$s en attente pour %4$s',
				$tenant->full_name,
				$property ? ( $property->reference ?: $property->address ) : '—',
				Limpeed_Payments::format_amount( (float) $tenant->rent_amount ),
				$period
			);
		}

		$subject = sprintf(
			/* translators: 1: nombre de locataires, 2: période YYYY-MM */
			__( '[%1$s] %2$d locataire(s) en retard de paiement (%3$s)', 'limpeed-immobilier' ),
			get_bloginfo( 'name' ),
			count( $unpaid ),
			$period
		);

		$body = sprintf(
			__( "Récapitulatif quotidien des loyers non encore encaissés pour la période %1\$s :\n\n%2\$s\n\nConsultez la liste complète : %3\$s", 'limpeed-immobilier' ),
			$period,
			implode( "\n", $lines ),
			Limpeed_Frontend::app_url( 'payments' )
		);

		foreach ( $recipients as $email ) {
			wp_mail( $email, $subject, $body );
		}
	}

	/**
	 * Adresses email des agents/administrateurs devant recevoir le rappel.
	 *
	 * @return array
	 */
	private static function get_recipients() {
		$users = get_users(
			array(
				'role__in' => array( 'limpeed_agent', 'limpeed_admin', 'administrator' ),
				'fields'   => array( 'user_email' ),
			)
		);

		$emails = array();
		foreach ( $users as $user ) {
			if ( ! empty( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}

		return array_unique( $emails );
	}
}
