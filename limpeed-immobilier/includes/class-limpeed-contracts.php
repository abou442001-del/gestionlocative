<?php
/**
 * Génération à la volée des documents contractuels PDF (bail de location,
 * mandat de gestion), via Dompdf (déjà vendorisé pour les bordereaux).
 *
 * Contrairement aux bordereaux, ces documents ne sont pas stockés : ils sont
 * régénérés à chaque téléchargement à partir des données courantes (bail,
 * bien, locataire, propriétaire ou mandat), ce qui garantit qu'ils reflètent
 * toujours l'état actuel du dossier sans avoir à gérer un historique de
 * fichiers PDF supplémentaire.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Contracts {

	/**
	 * Charge Dompdf.
	 *
	 * @return true|WP_Error
	 */
	private static function load_dompdf() {
		$autoload = LIMPEED_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return new WP_Error(
				'limpeed_dompdf_missing',
				__( 'La librairie Dompdf est introuvable. Exécutez "composer install" dans le dossier du plugin.', 'limpeed-immobilier' )
			);
		}
		require_once $autoload;
		return true;
	}

	/**
	 * Génère et envoie au navigateur le PDF du contrat de bail d'un locataire.
	 * Termine la requête (exit) comme tout téléchargement de fichier.
	 *
	 * @param int $tenant_id
	 */
	public static function stream_lease_contract( $tenant_id ) {
		$loaded = self::load_dompdf();
		if ( is_wp_error( $loaded ) ) {
			wp_die( esc_html( $loaded->get_error_message() ) );
		}

		$tenant = Limpeed_Tenants::get( $tenant_id );
		if ( ! $tenant ) {
			wp_die( esc_html__( 'Locataire introuvable.', 'limpeed-immobilier' ) );
		}

		$property = Limpeed_Properties::get( $tenant->property_id );
		$building = $property ? Limpeed_Buildings::get( $property->building_id ) : null;
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;

		$html   = self::render_lease_html( $tenant, $property, $building, $owner );
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		Limpeed_Activity_Log::log( 'created', 'tenant', $tenant_id, sprintf( 'Contrat de bail généré pour %s', $tenant->full_name ) );

		self::send_pdf( $dompdf->output(), sanitize_file_name( 'bail-' . $tenant->full_name . '.pdf' ) );
	}

	/**
	 * Génère et envoie au navigateur le PDF d'un mandat de gestion.
	 *
	 * @param int $mandate_id
	 */
	public static function stream_mandate_contract( $mandate_id ) {
		$loaded = self::load_dompdf();
		if ( is_wp_error( $loaded ) ) {
			wp_die( esc_html( $loaded->get_error_message() ) );
		}

		$mandate = Limpeed_Mandates::get( $mandate_id );
		if ( ! $mandate ) {
			wp_die( esc_html__( 'Mandat introuvable.', 'limpeed-immobilier' ) );
		}

		$building = Limpeed_Buildings::get( $mandate->building_id );
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;

		$html   = self::render_mandate_html( $mandate, $building, $owner );
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		Limpeed_Activity_Log::log( 'created', 'mandate', $mandate_id, sprintf( 'Mandat de gestion (PDF) généré pour %s', $building ? $building->name : '#' . $mandate->building_id ) );

		self::send_pdf( $dompdf->output(), sanitize_file_name( 'mandat-' . ( $building ? $building->name : $mandate_id ) . '.pdf' ) );
	}

	/**
	 * Envoie un flux PDF au navigateur en téléchargement et termine la requête.
	 *
	 * @param string $content
	 * @param string $filename
	 */
	private static function send_pdf( $content, $filename ) {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- flux binaire PDF, pas du HTML.
		exit;
	}

	/**
	 * Styles communs aux documents contractuels.
	 *
	 * @return string
	 */
	private static function common_styles() {
		return '
			body { font-family: sans-serif; font-size: 12px; color: #1d2327; line-height: 1.5; }
			h1 { color: #0e7c55; margin-bottom: 0; font-size: 20px; }
			.subtitle { color: #50575e; margin-top: 4px; margin-bottom: 24px; }
			h2 { font-size: 14px; border-bottom: 1px solid #c3c4c7; padding-bottom: 4px; margin-top: 24px; }
			table { width: 100%; border-collapse: collapse; margin-top: 8px; }
			td { padding: 4px 0; vertical-align: top; }
			td.label { width: 220px; color: #50575e; }
			.signatures { margin-top: 60px; }
			.signatures table td { width: 50%; padding-top: 40px; border-top: 1px solid #1d2327; }
		';
	}

	/**
	 * Construit le HTML du contrat de bail.
	 *
	 * @param object      $tenant
	 * @param object|null $property
	 * @param object|null $building
	 * @param object|null $owner
	 * @return string
	 */
	private static function render_lease_html( $tenant, $property, $building, $owner ) {
		ob_start();
		?>
		<html>
		<head>
			<meta charset="utf-8">
			<style><?php echo self::common_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		</head>
		<body>
			<h1>Limpeed Immobilier</h1>
			<p class="subtitle">Contrat de bail de location</p>

			<h2>Entre les soussignés</h2>
			<table>
				<tr><td class="label">Bailleur (propriétaire)</td><td><?php echo esc_html( $owner ? $owner->full_name : '—' ); ?><?php echo $owner && $owner->address ? ' — ' . esc_html( $owner->address ) : ''; ?></td></tr>
				<tr><td class="label">Preneur (locataire)</td><td><?php echo esc_html( $tenant->full_name ); ?><?php echo $tenant->phone ? ' — ' . esc_html( $tenant->phone ) : ''; ?></td></tr>
			</table>

			<h2>Objet de la location</h2>
			<table>
				<tr><td class="label">Bien loué</td><td><?php echo esc_html( $property ? Limpeed_Properties::get_display_label( $property ) : '—' ); ?></td></tr>
				<tr><td class="label">Édifice</td><td><?php echo esc_html( $building ? $building->name . ( $building->address ? ' — ' . $building->address : '' ) : '—' ); ?></td></tr>
			</table>

			<h2>Conditions financières</h2>
			<table>
				<tr><td class="label">Loyer mensuel</td><td><?php echo esc_html( Limpeed_Payments::format_amount( $tenant->rent_amount ) ); ?></td></tr>
				<tr><td class="label">Charges</td><td><?php echo esc_html( Limpeed_Payments::format_amount( $property ? $property->charges : 0 ) ); ?></td></tr>
				<tr><td class="label">Dépôt de garantie versé</td><td><?php echo esc_html( Limpeed_Payments::format_amount( $tenant->deposit_paid ) ); ?></td></tr>
			</table>

			<h2>Durée</h2>
			<table>
				<tr><td class="label">Date de début du bail</td><td><?php echo esc_html( $tenant->lease_start ? date_i18n( 'd/m/Y', strtotime( $tenant->lease_start ) ) : '—' ); ?></td></tr>
				<tr><td class="label">Date de fin du bail</td><td><?php echo esc_html( $tenant->lease_end ? date_i18n( 'd/m/Y', strtotime( $tenant->lease_end ) ) : 'Durée indéterminée' ); ?></td></tr>
			</table>

			<p style="margin-top: 24px;">
				Le présent contrat est établi conformément aux conditions convenues entre les parties. Le preneur s'engage à occuper les lieux
				en bon père de famille, à régler le loyer et les charges aux échéances convenues, et à respecter les clauses d'usage
				applicables aux baux d'habitation.
			</p>

			<div class="signatures">
				<table>
					<tr>
						<td>Le bailleur (ou son mandataire)</td>
						<td>Le preneur</td>
					</tr>
				</table>
			</div>

			<p style="margin-top: 24px; color: #50575e;">Document généré le <?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?>.</p>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Construit le HTML du mandat de gestion.
	 *
	 * @param object      $mandate
	 * @param object|null $building
	 * @param object|null $owner
	 * @return string
	 */
	private static function render_mandate_html( $mandate, $building, $owner ) {
		ob_start();
		?>
		<html>
		<head>
			<meta charset="utf-8">
			<style><?php echo self::common_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		</head>
		<body>
			<h1>Limpeed Immobilier</h1>
			<p class="subtitle">Mandat de gestion locative</p>

			<h2>Entre les soussignés</h2>
			<table>
				<tr><td class="label">Mandant (propriétaire)</td><td><?php echo esc_html( $owner ? $owner->full_name : '—' ); ?><?php echo $owner && $owner->address ? ' — ' . esc_html( $owner->address ) : ''; ?></td></tr>
				<tr><td class="label">Mandataire</td><td>Limpeed Immobilier</td></tr>
			</table>

			<h2>Objet du mandat</h2>
			<table>
				<tr><td class="label">Édifice concerné</td><td><?php echo esc_html( $building ? $building->name . ( $building->address ? ' — ' . $building->address : '' ) : '—' ); ?></td></tr>
			</table>

			<h2>Conditions</h2>
			<table>
				<tr><td class="label">Taux de commission</td><td><?php echo esc_html( number_format_i18n( (float) $mandate->commission_rate, 2 ) ); ?>%</td></tr>
				<tr><td class="label">Date de début</td><td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $mandate->start_date ) ) ); ?></td></tr>
				<tr><td class="label">Date de fin</td><td><?php echo esc_html( $mandate->end_date ? date_i18n( 'd/m/Y', strtotime( $mandate->end_date ) ) : 'Durée indéterminée' ); ?></td></tr>
				<?php if ( ! empty( $mandate->notes ) ) : ?>
				<tr><td class="label">Notes</td><td><?php echo nl2br( esc_html( $mandate->notes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
				<?php endif; ?>
			</table>

			<p style="margin-top: 24px;">
				Par le présent mandat, le mandant confie au mandataire la gestion locative des biens composant l'édifice désigné ci-dessus
				(mise en location, encaissement des loyers, reversement net de commission), selon les conditions énoncées.
			</p>

			<div class="signatures">
				<table>
					<tr>
						<td>Le mandant</td>
						<td>Le mandataire</td>
					</tr>
				</table>
			</div>

			<p style="margin-top: 24px; color: #50575e;">Document généré le <?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?>.</p>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
