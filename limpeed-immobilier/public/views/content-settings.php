<?php
/**
 * Contenu frontend de la section "Réglages".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$confirm = '1' === get_option( 'limpeed_confirm_data_deletion', '0' );
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
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
