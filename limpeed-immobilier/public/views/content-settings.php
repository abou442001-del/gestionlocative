<?php
/**
 * Contenu frontend de la section "Réglages".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$confirm            = '1' === get_option( 'limpeed_confirm_data_deletion', '0' );
$advance_months     = (int) get_option( 'limpeed_advance_months', 1 );
$deposit_months     = (int) get_option( 'limpeed_deposit_months', 1 );
$agency_fee_months  = (int) get_option( 'limpeed_agency_fee_months', 1 );
$logo_url       = Limpeed_Branding::get_logo_url();
$message        = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

// Les dossiers de stockage des documents et bordereaux sont protégés par un
// fichier .htaccess, une mesure qui ne s'applique que sous Apache/LiteSpeed.
// Sous un autre serveur web (Nginx, IIS...), l'hébergeur doit ajouter une
// protection équivalente au niveau de la configuration du serveur.
$server_software     = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
$server_software_low = strtolower( $server_software );
$is_apache_like      = false !== strpos( $server_software_low, 'apache' ) || false !== strpos( $server_software_low, 'litespeed' );
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

	<?php if ( '' !== $server_software && ! $is_apache_like ) : ?>
		<div class="limpeed-app-notice limpeed-app-notice-warning">
			<p>
				<strong><?php esc_html_e( 'Action requise auprès de votre hébergeur', 'limpeed-immobilier' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: nom du logiciel serveur détecté */
					esc_html__( 'Votre serveur web semble être %s. Les dossiers contenant les documents (pièces d\'identité, contrats...) et les bordereaux PDF sont protégés par un fichier .htaccess, qui ne fonctionne que sous Apache ou LiteSpeed. Demandez à votre hébergeur d\'ajouter une protection équivalente pour ces deux dossiers :', 'limpeed-immobilier' ),
					esc_html( $server_software )
				);
				?>
			</p>
			<pre>location ~* /wp-content/uploads/(limpeed-documents|limpeed-statements)/ {
    deny all;
    return 404;
}</pre>
			<p class="limpeed-app-description"><?php esc_html_e( 'Ces fichiers restent en tout état de cause accessibles uniquement via l\'application (vérification des droits à chaque téléchargement) : cette protection supplémentaire empêche seulement un accès direct par leur adresse si elle venait à être devinée.', 'limpeed-immobilier' ); ?></p>
		</div>
	<?php endif; ?>

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
			<label for="limpeed_agency_fee_months"><?php esc_html_e( 'Mois d\'honoraires agence (nouveau locataire)', 'limpeed-immobilier' ); ?></label>
			<select name="limpeed_agency_fee_months" id="limpeed_agency_fee_months">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $agency_fee_months, $i ); ?>>
						<?php echo esc_html( sprintf( _n( '%d mois', '%d mois', $i, 'limpeed-immobilier' ), $i ) ); ?>
					</option>
				<?php endfor; ?>
			</select>
			<p class="limpeed-app-description"><?php esc_html_e( 'Nombre de mois de loyer facturé au titre des honoraires de l\'agence lorsqu\'un locataire est marqué "nouveau locataire" (première location) lors de son enregistrement. Ce montant est affiché sur sa fiche mais n\'est pas ajouté automatiquement à une caisse : à enregistrer manuellement dans la caisse "Honoraire agence".', 'limpeed-immobilier' ); ?></p>
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

<div class="limpeed-app-panel">
	<h2><?php esc_html_e( 'Export de toutes les données', 'limpeed-immobilier' ); ?></h2>
	<p class="limpeed-app-description"><?php esc_html_e( 'Télécharge une archive ZIP contenant un fichier CSV par type de donnée (propriétaires, édifices, biens, locataires, paiements, bordereaux, mandats, avenants, états des lieux, documents, charges, caisses et journal d\'activité) — utile pour garder une copie de sauvegarde, la transmettre à un comptable, ou l\'analyser dans un tableur.', 'limpeed-immobilier' ); ?></p>
	<a href="<?php echo esc_url( wp_nonce_url( Limpeed_Frontend::app_url( 'settings', array( 'action' => 'export_all' ) ), 'limpeed_export_all' ) ); ?>" class="limpeed-app-btn"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Exporter toutes les données (ZIP)', 'limpeed-immobilier' ); ?></a>
</div>
