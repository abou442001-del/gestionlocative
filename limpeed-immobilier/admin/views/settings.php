<?php
/**
 * Vue : réglages du plugin.
 *
 * @var bool   $confirm
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
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=limpeed-settings' ) ); ?>">
		<?php wp_nonce_field( 'limpeed_save_settings', 'limpeed_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
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
