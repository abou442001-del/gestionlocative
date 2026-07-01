<?php
/**
 * Vue : réglages du plugin.
 *
 * @var bool   $confirm
 * @var int    $advance_months
 * @var string $logo_url
 * @var string $message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Réglages — Limpeed Immobilier', 'limpeed-immobilier' ); ?></h1>

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'error' === $message ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( isset( $_GET['error_text'] ) ? sanitize_text_field( wp_unslash( $_GET['error_text'] ) ) : __( 'Une erreur est survenue.', 'limpeed-immobilier' ) ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-settings' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'limpeed_save_settings', 'limpeed_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="limpeed_logo"><?php esc_html_e( 'Logo', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<?php if ( $logo_url ) : ?>
							<p><img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:220px;max-height:100px;display:block;margin-bottom:8px;"></p>
							<label>
								<input type="checkbox" name="limpeed_remove_logo" value="1">
								<?php esc_html_e( 'Supprimer le logo personnalisé et revenir au logo par défaut.', 'limpeed-immobilier' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Pour remplacer le logo actuel, sélectionnez simplement un nouveau fichier ci-dessous.', 'limpeed-immobilier' ); ?></p>
						<?php endif; ?>
						<p><input type="file" name="limpeed_logo" id="limpeed_logo" accept="image/png,image/jpeg,image/gif,image/webp"></p>
						<p class="description"><?php esc_html_e( 'Formats acceptés : PNG, JPEG, GIF, WEBP. Taille maximale : 2 Mo. Remplace le logo par défaut sur l\'application frontend et les pages de connexion/inscription.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="limpeed_advance_months"><?php esc_html_e( 'Mois d\'avance par défaut', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="limpeed_advance_months" id="limpeed_advance_months">
							<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $advance_months, $i ); ?>>
									<?php echo esc_html( sprintf( _n( '%d mois', '%d mois', $i, 'limpeed-immobilier' ), $i ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Nombre de mois de loyer d\'avance attendu de chaque locataire. Utilisé pour calculer automatiquement le montant d\'avance et le statut (à jour / en avance / en retard) affichés sur chaque fiche locataire.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Suppression des données', 'limpeed-immobilier' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="limpeed_confirm_data_deletion" value="1" <?php checked( $confirm ); ?>>
							<?php esc_html_e( 'Je confirme vouloir supprimer définitivement toutes les données du plugin (propriétaires, biens, locataires, paiements, bordereaux) lors de sa désinstallation.', 'limpeed-immobilier' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Par défaut, désactiver puis désinstaller le plugin ne supprime jamais vos données. Cochez cette case uniquement si vous souhaitez explicitement autoriser la suppression définitive des données lors de la désinstallation.', 'limpeed-immobilier' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Enregistrer les réglages', 'limpeed-immobilier' ) ); ?>
	</form>
</div>
