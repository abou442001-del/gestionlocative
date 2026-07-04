<?php
/**
 * Contenu frontend de la section "Paiements" (liste + formulaire).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

if ( in_array( $action, array( 'add', 'edit' ), true ) ) :
	// -----------------------------------------------------------------
	// Formulaire d'ajout / modification.
	// -----------------------------------------------------------------
	$payment = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$payment = Limpeed_Payments::get( (int) $_GET['id'] );
		if ( ! $payment ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Paiement introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit             = ! empty( $payment );
	$errors              = Limpeed_Frontend_Payments::$errors;
	$posted              = Limpeed_Frontend_Payments::$posted;
	$tenants             = Limpeed_Tenants::get_all( array( 'per_page' => 9999 ) );
	$preselected_tenant  = isset( $_GET['tenant_id'] ) ? (int) $_GET['tenant_id'] : 0;
	$preselected_period  = isset( $_GET['period'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '';

	$field = function ( $name, $default = '' ) use ( $payment, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $payment->$name ) ) {
			return $payment->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le paiement', 'limpeed-immobilier' ) : esc_html__( 'Enregistrer un paiement', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', $is_edit ? array( 'action' => 'edit', 'id' => $payment->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_payment', 'limpeed_payment_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="tenant_id"><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="tenant_id" id="tenant_id" required>
					<option value=""><?php esc_html_e( '— Choisir un locataire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $tenants as $tenant_option ) : ?>
						<?php
						$property_option = Limpeed_Properties::get( $tenant_option->property_id );
						$label            = $property_option
							? sprintf( '%s — %s', $tenant_option->full_name, Limpeed_Properties::get_display_label( $property_option ) )
							: $tenant_option->full_name;
						?>
						<option value="<?php echo esc_attr( $tenant_option->id ); ?>" <?php selected( (int) $field( 'tenant_id', $preselected_tenant ), $tenant_option->id ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Le bien associé est déterminé automatiquement à partir du locataire sélectionné.', 'limpeed-immobilier' ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="period"><?php esc_html_e( 'Mois concerné', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="month" name="period" id="period" required value="<?php echo esc_attr( $field( 'period', $preselected_period ? $preselected_period : Limpeed_Payments::get_current_period() ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="amount"><?php esc_html_e( 'Montant payé', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="number" step="0.01" min="0" name="amount" id="amount" required value="<?php echo esc_attr( $field( 'amount', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="payment_date"><?php esc_html_e( 'Date de paiement', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="payment_date" id="payment_date" value="<?php echo esc_attr( $field( 'payment_date' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="payment_method"><?php esc_html_e( 'Mode de paiement', 'limpeed-immobilier' ); ?></label>
				<select name="payment_method" id="payment_method">
					<?php foreach ( Limpeed_Payments::get_payment_methods() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'payment_method', 'especes' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label><?php esc_html_e( 'Commission agence', 'limpeed-immobilier' ); ?></label>
				<?php if ( $is_edit ) : ?>
					<p><strong><?php echo esc_html( Limpeed_Payments::format_amount( $payment->commission_amount ) ); ?></strong></p>
				<?php endif; ?>
				<p class="limpeed-app-description"><?php esc_html_e( 'Calculée automatiquement selon le taux de commission défini sur l\'édifice du bien concerné (voir Édifices).', 'limpeed-immobilier' ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
				<select name="status" id="status">
					<?php foreach ( Limpeed_Payments::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'paye' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Enregistrer', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste dynamique (Alpine.js) : recherche/filtres en direct, modale
	// d'enregistrement rapide (locataire peuplé en Ajax), actions Ajax,
	// panneau de détail. Toutes les données transitent par l'API REST
	// limpeed/v1 (voir includes/class-limpeed-rest-api.php) ; ce fichier ne
	// fait que fournir le balisage et la configuration initiale, y compris
	// les filtres tenant_id/property_id reçus par navigation croisée depuis
	// une fiche locataire ou un bien ("Voir l'historique des paiements").
	// -----------------------------------------------------------------
	$preset_tenant_id   = isset( $_GET['tenant_id'] ) ? (int) $_GET['tenant_id'] : 0;
	$preset_property_id = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : 0;
	$filter_properties  = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );

	$property_options = array_map(
		function ( $property ) {
			return array(
				'id'    => (int) $property->id,
				'label' => Limpeed_Properties::get_display_label( $property ),
			);
		},
		$filter_properties
	);

	$app_config = array(
		'propertyOptions'   => $property_options,
		'paymentStatuses'   => Limpeed_Payments::get_statuses(),
		'paymentMethods'    => Limpeed_Payments::get_payment_methods(),
		'currentPeriod'     => Limpeed_Payments::get_current_period(),
		'presetTenantId'    => $preset_tenant_id,
		'presetPropertyId'  => $preset_property_id,
		'i18n'              => array(
			'created'       => __( 'Paiement enregistré avec succès.', 'limpeed-immobilier' ),
			'updated'       => __( 'Paiement mis à jour avec succès.', 'limpeed-immobilier' ),
			'deleted'       => __( 'Paiement supprimé avec succès.', 'limpeed-immobilier' ),
			'confirmDelete' => __( 'Confirmez-vous la suppression de ce paiement ?', 'limpeed-immobilier' ),
		),
	);

	$rest_config = array(
		'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);

	// Cartes de synthèse (mois en cours).
	$period_summary = Limpeed_Payments::get_period_summary();
	$unpaid_count   = count( $period_summary['unpaid_tenants'] );
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $period_summary['collected'] ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Encaissé (mois en cours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-money-alt"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-green"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $period_summary['commission'] ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Commissions (mois en cours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-chart-bar"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $unpaid_count ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Locataires impayés (mois en cours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-warning"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-red"></div>
		</div>
	</div>

	<div class="limpeed-app-panel" x-data="limpeedPaymentsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
		<div class="limpeed-app-toolbar">
			<div class="limpeed-app-search">
				<input type="text" x-model="search" @input="onSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher un locataire...', 'limpeed-immobilier' ); ?>">
				<select x-model="filterPropertyId" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
					<template x-for="property in propertyOptions" :key="property.id">
						<option :value="property.id" x-text="property.label"></option>
					</template>
				</select>
				<select x-model="filterStatus" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<template x-for="[key, label] in Object.entries(paymentStatuses)" :key="key">
						<option :value="key" x-text="label"></option>
					</template>
				</select>
				<input type="month" x-model="filterPeriod" @change="onFilterChange()">
			</div>
			<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Enregistrer un paiement', 'limpeed-immobilier' ); ?></button>
		</div>

		<div class="limpeed-entity-grid">
			<template x-if="loading">
				<template x-for="n in 6" :key="n">
					<div class="limpeed-entity-card-skeleton"></div>
				</template>
			</template>
			<p x-show="!loading && items.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun paiement pour le moment.', 'limpeed-immobilier' ); ?></p>
			<template x-for="row in items" :key="row.id">
				<div class="limpeed-entity-card" :class="'limpeed-entity-card-status-' + row.status" @click="openDrawer(row)">
					<div class="limpeed-entity-card-header">
						<span class="limpeed-entity-card-avatar limpeed-icon-green"><span class="dashicons dashicons-money-alt"></span></span>
						<div class="limpeed-entity-card-header-text">
							<div class="limpeed-entity-card-title" x-text="row.tenant_label || '—'"></div>
							<div class="limpeed-entity-card-subtitle" x-text="row.period"></div>
						</div>
						<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.status" x-text="row.status_label"></span>
					</div>
					<div class="limpeed-entity-card-meta">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-building"></span>
							<span x-text="row.property_label || '—'"></span>
						</div>
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-chart-bar"></span>
							<span x-text="'Commission ' + row.commission_formatted"></span>
						</div>
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-admin-users"></span>
							<span x-text="row.agent_name || '—'"></span>
						</div>
					</div>
					<div>
						<div class="limpeed-entity-card-hero-label"><?php esc_html_e( 'Montant payé', 'limpeed-immobilier' ); ?></div>
						<div class="limpeed-entity-card-hero" x-text="row.amount_formatted"></div>
					</div>
					<div class="limpeed-entity-card-footer">
						<button type="button" class="limpeed-app-link-btn" @click.stop="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deletePayment(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
					</div>
				</div>
			</template>
		</div>

		<div class="limpeed-app-pagination" x-show="totalPages > 1" x-cloak>
			<template x-for="p in totalPages" :key="p">
				<a href="#" @click.prevent="goToPage(p)" :class="{ 'is-active': p === paged }" x-text="p"></a>
			</template>
		</div>

		<!-- Modale ajout / modification -->
		<div class="limpeed-app-modal-overlay" x-show="modal.open" x-cloak @keydown.escape.window="closeModal()">
			<div class="limpeed-app-modal" @click.outside="closeModal()" x-show="modal.open" x-transition>
				<div class="limpeed-app-modal-header">
					<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier le paiement', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Enregistrer un paiement', 'limpeed-immobilier' ) ); ?>'"></h2>
					<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<form class="limpeed-app-form" @submit.prevent="savePayment()">
					<div class="limpeed-app-modal-body">
						<div class="limpeed-app-notice limpeed-app-notice-error" x-show="modal.errors.length">
							<ul>
								<template x-for="(error, index) in modal.errors" :key="index">
									<li x-text="error"></li>
								</template>
							</ul>
						</div>

						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.tenant_id" :disabled="modal.loadingTenants">
								<option value=""><?php esc_html_e( '— Choisir un locataire —', 'limpeed-immobilier' ); ?></option>
								<template x-for="tenant in modal.tenants" :key="tenant.id">
									<option :value="tenant.id" x-text="tenant.label"></option>
								</template>
							</select>
							<p class="limpeed-app-form-hint"><?php esc_html_e( 'Le bien associé est déterminé automatiquement à partir du locataire sélectionné.', 'limpeed-immobilier' ); ?></p>
						</div>
						<div class="limpeed-app-modal-grid">
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Mois concerné', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
								<input type="month" x-model="modal.data.period" required>
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Montant payé', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.amount" required>
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Date de paiement', 'limpeed-immobilier' ); ?></label>
								<input type="date" x-model="modal.data.payment_date">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Mode de paiement', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.payment_method">
									<template x-for="[key, label] in Object.entries(paymentMethods)" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
							<div class="limpeed-form-row is-full" x-show="modal.mode === 'edit'">
								<label><?php esc_html_e( 'Commission agence', 'limpeed-immobilier' ); ?></label>
								<p><strong x-text="modal.commissionFormatted"></strong></p>
								<p class="limpeed-app-form-hint"><?php esc_html_e( 'Calculée automatiquement selon le taux de commission défini sur l\'édifice du bien concerné (voir Édifices).', 'limpeed-immobilier' ); ?></p>
							</div>
							<div class="limpeed-form-row is-full">
								<label><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.status">
									<template x-for="[key, label] in Object.entries(paymentStatuses)" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
						</div>
					</div>
					<div class="limpeed-app-modal-footer">
						<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="closeModal()"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></button>
						<button type="submit" class="limpeed-app-btn" :disabled="modal.saving">
							<span x-text="modal.saving ? '<?php echo esc_js( __( 'Enregistrement...', 'limpeed-immobilier' ) ); ?>' : (modal.mode === 'edit' ? '<?php echo esc_js( __( 'Mettre à jour', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Enregistrer', 'limpeed-immobilier' ) ); ?>')"></span>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Panneau de détail (drawer) -->
		<template x-if="drawer.open">
			<div>
				<div class="limpeed-app-drawer-overlay" @click="closeDrawer()"></div>
				<div class="limpeed-app-drawer" @keydown.escape.window="closeDrawer()">
					<div class="limpeed-app-drawer-header">
						<div>
							<h2 x-text="drawer.payment ? drawer.payment.period : ''"></h2>
							<div class="limpeed-app-drawer-subtitle" x-text="drawer.payment ? drawer.payment.tenant_label : ''"></div>
						</div>
						<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
					</div>
					<div class="limpeed-app-drawer-tabs">
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique', 'limpeed-immobilier' ); ?></button>
					</div>
					<div class="limpeed-app-drawer-body">
						<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

						<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.payment">
							<div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.tenant_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Bien', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.property_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.amount_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Commission', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.commission_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Date de paiement', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.payment_date || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Mode de paiement', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.payment_method_label"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value">
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + drawer.payment.status" x-text="drawer.payment.status_label"></span>
									</div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Enregistré par', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.payment.agent_name || '—'"></div>
								</div>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'historique'">
							<div>
								<p x-show="drawer.history.length === 0"><?php esc_html_e( 'Aucun historique disponible.', 'limpeed-immobilier' ); ?></p>
								<template x-for="entry in drawer.history" :key="entry.id">
									<div class="limpeed-app-drawer-list-item">
										<span x-text="entry.action_label + ' — ' + entry.agent_name"></span>
										<span x-text="entry.created_at"></span>
									</div>
								</template>
							</div>
						</template>
					</div>
				</div>
			</div>
		</template>

		<!-- Notifications toast -->
		<div class="limpeed-app-toast-container">
			<template x-for="t in toasts" :key="t.id">
				<div class="limpeed-app-toast" :class="'limpeed-app-toast-' + t.type" x-text="t.message"></div>
			</template>
		</div>
	</div>

	<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des paiements.', 'limpeed-immobilier' ); ?></p></noscript>

	<script>
	window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
	</script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<?php /* payments-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/payments-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php endif; ?>
