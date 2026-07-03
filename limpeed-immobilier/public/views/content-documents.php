<?php
/**
 * Contenu frontend de la section "Documents" : sélection d'une entité
 * (propriétaire / édifice / bien / locataire) puis liste + upload de ses
 * documents. Le CRUD transite par l'API REST (voir includes/class-limpeed-rest-api.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$app_config = array(
	'entityTypes' => Limpeed_Documents::get_entity_types(),
	'i18n'        => array(
		'uploaded'      => __( 'Document ajouté avec succès.', 'limpeed-immobilier' ),
		'deleted'       => __( 'Document supprimé avec succès.', 'limpeed-immobilier' ),
		'confirmDelete' => __( 'Confirmez-vous la suppression de ce document ?', 'limpeed-immobilier' ),
	),
);

$rest_config = array(
	'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
);

// Cartes de synthèse.
$documents_total      = Limpeed_Documents::count_all();
$documents_total_size = Limpeed_Documents::get_total_size();
$documents_this_month = Limpeed_Documents::count_added_this_month();
?>

<div class="limpeed-cards-row">
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $documents_total ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Documents stockés', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-portfolio"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-green"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( size_format( $documents_total_size ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Espace de stockage utilisé', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-cloud"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
	</div>
	<div class="limpeed-app-card">
		<div class="limpeed-app-card-top">
			<div>
				<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $documents_this_month ) ); ?></div>
				<div class="limpeed-app-card-label"><?php esc_html_e( 'Ajoutés ce mois-ci', 'limpeed-immobilier' ); ?></div>
			</div>
			<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-upload"></span></span>
		</div>
		<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
	</div>
</div>

<div class="limpeed-app-panel" x-data="limpeedDocumentsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
	<h2><?php esc_html_e( 'Rechercher une fiche', 'limpeed-immobilier' ); ?></h2>
	<div class="limpeed-app-modal-grid">
		<div class="limpeed-form-row">
			<label><?php esc_html_e( 'Type de fiche', 'limpeed-immobilier' ); ?></label>
			<select x-model="entityType" @change="onEntityTypeChange()">
				<option value=""><?php esc_html_e( '— Choisir un type —', 'limpeed-immobilier' ); ?></option>
				<template x-for="(label, key) in entityTypes" :key="key">
					<option :value="key" x-text="label"></option>
				</template>
			</select>
		</div>
		<div class="limpeed-form-row" x-show="entityType">
			<label><?php esc_html_e( 'Rechercher', 'limpeed-immobilier' ); ?></label>
			<input type="text" x-model="entitySearch" @input="onEntitySearchInput()" placeholder="<?php esc_attr_e( 'Nom...', 'limpeed-immobilier' ); ?>">
		</div>
	</div>

	<div x-show="entityType && entityOptions.length > 0" x-cloak style="margin-top: 10px;">
		<div class="limpeed-entity-grid">
			<template x-for="option in entityOptions" :key="option.id">
				<div class="limpeed-entity-card" @click="selectEntity(option.id)">
					<div class="limpeed-entity-card-header">
						<div class="limpeed-entity-card-title" x-text="option.label"></div>
					</div>
					<div class="limpeed-entity-card-footer">
						<span class="limpeed-app-link-btn"><?php esc_html_e( 'Voir les documents', 'limpeed-immobilier' ); ?></span>
					</div>
				</div>
			</template>
		</div>
	</div>

	<template x-if="entityId">
		<div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--limpeed-border);">
			<h2><?php esc_html_e( 'Documents', 'limpeed-immobilier' ); ?></h2>

			<div class="limpeed-entity-grid">
				<template x-if="loadingDocuments">
					<template x-for="n in 3" :key="n">
						<div class="limpeed-entity-card-skeleton"></div>
					</template>
				</template>
				<p x-show="!loadingDocuments && documents.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun document pour le moment.', 'limpeed-immobilier' ); ?></p>
				<template x-for="doc in documents" :key="doc.id">
					<div class="limpeed-entity-card" @click="window.location.href = doc.download_url">
						<div class="limpeed-entity-card-header">
							<div class="limpeed-entity-card-title" x-text="doc.title"></div>
							<span class="limpeed-app-badge" x-text="doc.file_size_label"></span>
						</div>
						<div class="limpeed-entity-card-meta">
							<div class="limpeed-entity-card-meta-row">
								<span class="dashicons dashicons-media-default"></span>
								<span x-text="doc.file_name"></span>
							</div>
							<div class="limpeed-entity-card-meta-row">
								<span class="dashicons dashicons-clock"></span>
								<span x-text="doc.created_at"></span>
							</div>
						</div>
						<div class="limpeed-entity-card-footer">
							<a :href="doc.download_url" class="limpeed-app-link-btn" @click.stop><?php esc_html_e( 'Télécharger', 'limpeed-immobilier' ); ?></a>
							<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deleteDocument(doc)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
						</div>
					</div>
				</template>
			</div>

			<form @submit.prevent="uploadDocument()" class="limpeed-app-modal-grid" style="margin-top: 16px; align-items: end;">
				<div class="limpeed-form-row">
					<label><?php esc_html_e( 'Titre du document', 'limpeed-immobilier' ); ?></label>
					<input type="text" x-model="uploadForm.title" placeholder="<?php esc_attr_e( 'Ex : Pièce d\'identité', 'limpeed-immobilier' ); ?>">
				</div>
				<div class="limpeed-form-row">
					<label><?php esc_html_e( 'Fichier', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
					<input type="file" id="limpeed-document-file-input" @change="onFileChange($event)" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx">
					<p class="limpeed-app-form-hint"><?php esc_html_e( 'PDF, image ou document Word, 5 Mo maximum.', 'limpeed-immobilier' ); ?></p>
				</div>
				<div class="limpeed-form-row is-full">
					<button type="submit" class="limpeed-app-btn" :disabled="uploadForm.uploading || !uploadForm.file">
						<span x-text="uploadForm.uploading ? '<?php echo esc_js( __( 'Envoi...', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter le document', 'limpeed-immobilier' ) ); ?>'"></span>
					</button>
				</div>
			</form>
		</div>
	</template>

	<!-- Notifications toast -->
	<div class="limpeed-app-toast-container">
		<template x-for="t in toasts" :key="t.id">
			<div class="limpeed-app-toast" :class="'limpeed-app-toast-' + t.type" x-text="t.message"></div>
		</template>
	</div>
</div>

<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript.', 'limpeed-immobilier' ); ?></p></noscript>

<script>
window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
</script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php /* documents-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/documents-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
