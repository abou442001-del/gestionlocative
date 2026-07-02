<?php
/**
 * Contenu frontend de la section "Réglages".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$confirm        = '1' === get_option( 'limpeed_confirm_data_deletion', '0' );
$advance_months = (int) get_option( 'limpeed_advance_months', 1 );
$deposit_months = (int) get_option( 'limpeed_deposit_months', 1 );
$logo_url       = Limpeed_Branding::get_logo_url();
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

	<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'settings' ) ); ?>" class="limpeed-app-form" enctype="multipart/form-data">
		<?php wp_nonce_field( 'limpeed_save_settings', 'limpeed_settings_nonce' ); ?>

		<div class="limpeed-form-row">
			<label for="limpeed_logo"><?php esc_html_e( 'Logo', 'limpeed-immobilier' ); ?></label>
			<?php if ( $logo_url ) : ?>
				<p><img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:220px;max-height:100px;display:block;margin-bottom:8px;"></p>
				<label>
					<input type="checkbox" name="limpeed_remove_logo" value="1">
					<?php esc_html_e( 'Supprimer le logo personnalisé et revenir au logo par défaut.', 'limpeed-immobilier' ); ?>
				</label>
				<p class="limpeed-app-description"><?php esc_html_e( 'Pour remplacer le logo actuel, sélectionnez simplement un nouveau fichier ci-dessous.', 'limpeed-immobilier' ); ?></p>
			<?php endif; ?>
			<input type="file" name="limpeed_logo" id="limpeed_logo" accept="image/png,image/jpeg,image/gif,image/webp">
			<p class="limpeed-app-description"><?php esc_html_e( 'Formats acceptés : PNG, JPEG, GIF, WEBP. Taille maximale : 2 Mo. Remplace le logo par défaut sur l\'application frontend et les pages de connexion/inscription.', 'limpeed-immobilier' ); ?></p>
		</div>

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
			<label for="limpeed_deposit_months"><?php esc_html_e( 'Mois de caution par défaut', 'limpeed-immobilier' ); ?></label>
			<select name="limpeed_deposit_months" id="limpeed_deposit_months">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $deposit_months, $i ); ?>>
						<?php echo esc_html( sprintf( _n( '%d mois', '%d mois', $i, 'limpeed-immobilier' ), $i ) ); ?>
					</option>
				<?php endfor; ?>
			</select>
			<p class="limpeed-app-description"><?php esc_html_e( 'Nombre de mois de loyer attendu au titre de la caution (dépôt de garantie). Utilisé pour calculer automatiquement le dépôt requis et comparer au dépôt réellement versé sur chaque fiche locataire.', 'limpeed-immobilier' ); ?></p>
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
