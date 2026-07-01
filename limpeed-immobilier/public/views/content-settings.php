<?php
/**
 * Contenu frontend de la section "Réglages".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$confirm        = '1' === get_option( 'limpeed_confirm_data_deletion', '0' );
$advance_months = (int) get_option( 'limpeed_advance_months', 1 );
$message        = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>

<div class="limpeed-app-panel">
	<h2><?php esc_html_e( 'Réglages — Limpeed Immobilier', 'limpeed-immobilier' ); ?></h2>

	<?php
	Limpeed_Frontend::render_notice(
		$message,
		array(
			'saved' => __( 'Réglages enregistrés.', 'limpeed-immobilier' ),
		)
	);
	?>

	<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'settings' ) ); ?>" class="limpeed-app-form">
		<?php wp_nonce_field( 'limpeed_save_settings', 'limpeed_settings_nonce' ); ?>

		<div class="limpeed-form-row">
			<label for="limpeed_advance_months"><?php esc_html_e( 'Mois d\'avance par défaut', 'limpeed-immobilier' ); ?></label>
			<select name="limpeed_advance_months" id="limpeed_advance_months">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $advance_months, $i ); ?>>
						<?php echo esc_html( sprintf( _n( '%d mois', '%d mois', $i, 'limpeed-immobilier' ), $i ) ); ?>
					</option>
				<?php endfor; ?>
			</select>
			<p class="limpeed-app-description"><?php esc_html_e( 'Nombre de mois de loyer d\'avance attendu de chaque locataire. Utilisé pour calculer automatiquement le montant d\'avance et le statut (à jour / en avance / en retard) affichés sur chaque fiche locataire.', 'limpeed-immobilier' ); ?></p>
		</div>

		<div class="limpeed-form-row">
			<label>
				<input type="checkbox" name="limpeed_confirm_data_deletion" value="1" <?php checked( $confirm ); ?>>
				<?php esc_html_e( 'Je confirme vouloir supprimer définitivement toutes les données du plugin (propriétaires, biens, locataires, paiements, bordereaux) lors de sa désinstallation.', 'limpeed-immobilier' ); ?>
			</label>
			<p class="limpeed-app-description">
				<?php esc_html_e( 'Par défaut, désactiver puis désinstaller le plugin ne supprime jamais vos données. Cochez cette case uniquement si vous souhaitez explicitement autoriser la suppression définitive des données lors de la désinstallation.', 'limpeed-immobilier' ); ?>
			</p>
		</div>

		<button type="submit" class="limpeed-app-btn"><?php esc_html_e( 'Enregistrer les réglages', 'limpeed-immobilier' ); ?></button>
	</form>
</div>
