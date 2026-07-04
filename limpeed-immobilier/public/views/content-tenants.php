<?php
/**
 * Contenu frontend de la section "Locataires" (liste + formulaire).
 * Sélection en cascade : propriétaire → édifice → sous-édifice (bien loué).
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
	$tenant = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$tenant = Limpeed_Tenants::get( (int) $_GET['id'] );
		if ( ! $tenant ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Locataire introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit    = ! empty( $tenant );
	$errors     = Limpeed_Frontend_Tenants::$errors;
	$posted     = Limpeed_Frontend_Tenants::$posted;
	$owners     = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$buildings  = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
	$properties = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );

	$field = function ( $name, $default = '' ) use ( $tenant, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $tenant->$name ) ) {
			return $tenant->$name;
		}
		return $default;
	};

	// Détermine l'édifice et le propriétaire actuellement liés au bien sélectionné,
	// pour pré-remplir correctement la cascade dès le premier affichage.
	$current_property_id = (int) $field( 'property_id' );
	$current_building_id = 0;
	$current_owner_id    = 0;

	if ( $current_property_id ) {
		$current_property = Limpeed_Properties::get( $current_property_id );
		if ( $current_property ) {
			$current_building_id = (int) $current_property->building_id;
			$current_building    = Limpeed_Buildings::get( $current_building_id );
			if ( $current_building ) {
				$current_owner_id = (int) $current_building->owner_id;
			}
		}
	}

	$buildings_for_owner = array_filter(
		$buildings,
		function ( $b ) use ( $current_owner_id ) {
			return (int) $b->owner_id === $current_owner_id;
		}
	);
	$properties_for_building = array_filter(
		$properties,
		function ( $p ) use ( $current_building_id ) {
			return (int) $p->building_id === $current_building_id;
		}
	);

	$buildings_json = array_map(
		function ( $b ) {
			return array( 'id' => (int) $b->id, 'owner_id' => (int) $b->owner_id, 'label' => $b->name );
		},
		$buildings
	);
	$properties_json = array_map(
		function ( $p ) {
			return array( 'id' => (int) $p->id, 'building_id' => (int) $p->building_id, 'label' => Limpeed_Properties::get_display_label( $p ) );
		},
		$properties
	);
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le locataire', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$property = Limpeed_Properties::get( $tenant->property_id );
				if ( $property ) {
					printf(
						/* translators: %s: lien vers le bien loué */
						esc_html__( 'Bien loué : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $property->id ) ) ) . '">' . esc_html( Limpeed_Properties::get_display_label( $property ) ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', array( 'tenant_id' => $tenant->id ) ) ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</div>

			<?php $dossier = Limpeed_Tenants::get_dossier_completeness( $tenant ); ?>
			<h3><?php esc_html_e( 'Dossier locataire', 'limpeed-immobilier' ); ?></h3>
			<div class="limpeed-app-dossier-panel">
				<div class="limpeed-app-dossier-progress">
					<div class="limpeed-app-dossier-progress-bar"><span style="width: <?php echo esc_attr( $dossier['percent'] ); ?>%"></span></div>
					<strong><?php echo esc_html( $dossier['percent'] ); ?>%</strong>
				</div>
				<ul class="limpeed-app-dossier-checklist">
					<?php foreach ( $dossier['items'] as $item ) : ?>
						<li class="<?php echo $item['complete'] ? 'is-complete' : 'is-missing'; ?>">
							<?php echo $item['complete'] ? '&#10003;' : '&#9675;'; ?> <?php echo esc_html( $item['label'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php
			$advance_status   = Limpeed_Tenants::get_advance_status( $tenant );
			$deposit_status   = Limpeed_Tenants::get_deposit_status( $tenant );
			$calendar_year    = isset( $_GET['calendar_year'] ) ? (int) $_GET['calendar_year'] : (int) current_time( 'Y' );
			$payment_calendar = Limpeed_Tenants::get_payment_calendar( $tenant, $calendar_year );

			$advance_labels = array(
				'aucun_paiement' => __( 'Aucun paiement enregistré', 'limpeed-immobilier' ),
				'en_retard'      => __( 'En retard', 'limpeed-immobilier' ),
				'a_jour'         => __( 'À jour', 'limpeed-immobilier' ),
				'en_avance'      => __( 'En avance', 'limpeed-immobilier' ),
			);
			$deposit_labels = array(
				'aucun'       => __( 'Aucun dépôt versé', 'limpeed-immobilier' ),
				'insuffisant' => __( 'Insuffisant', 'limpeed-immobilier' ),
				'suffisant'   => __( 'Suffisant', 'limpeed-immobilier' ),
			);
			?>
			<h3><?php esc_html_e( 'Suivi des paiements', 'limpeed-immobilier' ); ?></h3>

			<div class="limpeed-app-advance-panel">
				<div class="limpeed-app-advance-stat">
					<span class="limpeed-app-advance-label"><?php esc_html_e( 'Avance requise', 'limpeed-immobilier' ); ?></span>
					<span class="limpeed-app-advance-value"><?php echo esc_html( Limpeed_Payments::format_amount( $advance_status['advance_amount'] ) ); ?> <small>(<?php echo esc_html( $advance_status['advance_months'] ); ?> <?php esc_html_e( 'mois', 'limpeed-immobilier' ); ?>)</small></span>
				</div>
				<div class="limpeed-app-advance-stat">
					<span class="limpeed-app-advance-label"><?php esc_html_e( 'Payé jusqu\'à', 'limpeed-immobilier' ); ?></span>
					<span class="limpeed-app-advance-value"><?php echo $advance_status['paid_until'] ? esc_html( $advance_status['paid_until'] ) : '&mdash;'; ?></span>
				</div>
				<div class="limpeed-app-advance-stat">
					<span class="limpeed-app-advance-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
					<span class="limpeed-app-advance-value">
						<span class="limpeed-app-badge limpeed-app-badge-<?php echo esc_attr( $advance_status['status'] ); ?>"><?php echo esc_html( $advance_labels[ $advance_status['status'] ] ); ?></span>
						<?php if ( null !== $advance_status['months_ahead'] ) : ?>
							<?php if ( $advance_status['months_ahead'] >= 0 ) : ?>
								<?php
								/* translators: %d: nombre de mois d'avance */
								printf( ' ' . esc_html__( '(%d mois d\'avance)', 'limpeed-immobilier' ), (int) $advance_status['months_ahead'] );
								?>
							<?php else : ?>
								<?php
								/* translators: %d: nombre de mois de retard */
								printf( ' ' . esc_html__( '(%d mois de retard)', 'limpeed-immobilier' ), abs( (int) $advance_status['months_ahead'] ) );
								?>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="limpeed-app-advance-stat">
					<span class="limpeed-app-advance-label"><?php esc_html_e( 'Caution', 'limpeed-immobilier' ); ?></span>
					<span class="limpeed-app-advance-value">
						<?php echo esc_html( Limpeed_Payments::format_amount( $deposit_status['paid'] ) ); ?> / <?php echo esc_html( Limpeed_Payments::format_amount( $deposit_status['required'] ) ); ?>
						<small>(<?php echo esc_html( $deposit_status['deposit_months'] ); ?> <?php esc_html_e( 'mois', 'limpeed-immobilier' ); ?>)</small>
						<span class="limpeed-app-badge limpeed-app-badge-<?php echo esc_attr( $deposit_status['status'] ); ?>"><?php echo esc_html( $deposit_labels[ $deposit_status['status'] ] ); ?></span>
					</span>
				</div>
			</div>

			<div class="limpeed-app-calendar-nav">
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant->id, 'calendar_year' => $calendar_year - 1 ) ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary">&laquo; <?php echo esc_html( $calendar_year - 1 ); ?></a>
				<strong><?php echo esc_html( $calendar_year ); ?></strong>
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant->id, 'calendar_year' => $calendar_year + 1 ) ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php echo esc_html( $calendar_year + 1 ); ?> &raquo;</a>
			</div>

			<div class="limpeed-app-payment-calendar">
				<?php foreach ( $payment_calendar as $month => $entry ) : ?>
					<?php
					$month_label = date_i18n( 'M', mktime( 0, 0, 0, $month, 1, $calendar_year ) );
					$css_class   = 'limpeed-app-calendar-month limpeed-app-calendar-month-' . $entry['status'];

					if ( 'hors_bail' === $entry['status'] ) {
						?>
						<span class="<?php echo esc_attr( $css_class ); ?>">
							<span class="limpeed-app-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
						</span>
						<?php
					} elseif ( $entry['payment'] ) {
						$payment_url = Limpeed_Frontend::app_url( 'payments', array( 'action' => 'edit', 'id' => $entry['payment']->id ) );
						?>
						<a href="<?php echo esc_url( $payment_url ); ?>" class="<?php echo esc_attr( $css_class ); ?>">
							<span class="limpeed-app-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
							<span class="limpeed-app-calendar-month-amount"><?php echo esc_html( Limpeed_Payments::format_amount( $entry['payment']->amount ) ); ?></span>
						</a>
						<?php
					} else {
						$payment_url = Limpeed_Frontend::app_url( 'payments', array( 'action' => 'add', 'tenant_id' => $tenant->id, 'period' => $entry['period'] ) );
						?>
						<a href="<?php echo esc_url( $payment_url ); ?>" class="<?php echo esc_attr( $css_class ); ?>">
							<span class="limpeed-app-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
							<span class="limpeed-app-calendar-month-amount"><?php esc_html_e( 'Enregistrer', 'limpeed-immobilier' ); ?></span>
						</a>
						<?php
					}
					?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', $is_edit ? array( 'action' => 'edit', 'id' => $tenant->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_tenant', 'limpeed_tenant_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="limpeed_ui_owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select id="limpeed_ui_owner_id">
					<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $current_owner_id, $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="limpeed_ui_building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select id="limpeed_ui_building_id" <?php disabled( empty( $current_owner_id ) ); ?>>
					<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $buildings_for_owner as $building_option ) : ?>
						<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( $current_building_id, $building_option->id ); ?>>
							<?php echo esc_html( $building_option->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="property_id"><?php esc_html_e( 'Sous-édifice (bien loué)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="property_id" id="property_id" required <?php disabled( empty( $current_building_id ) ); ?>>
					<option value=""><?php esc_html_e( '— Choisir un sous-édifice —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $properties_for_building as $property_option ) : ?>
						<option value="<?php echo esc_attr( $property_option->id ); ?>" <?php selected( $current_property_id, $property_option->id ); ?>>
							<?php echo esc_html( Limpeed_Properties::get_display_label( $property_option ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Choisissez d\'abord le propriétaire, puis l\'édifice, pour afficher ses sous-édifices disponibles.', 'limpeed-immobilier' ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="full_name"><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="full_name" id="full_name" required value="<?php echo esc_attr( $field( 'full_name' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="phone"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="phone" id="phone" value="<?php echo esc_attr( $field( 'phone' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label>
				<input type="email" name="email" id="email" value="<?php echo esc_attr( $field( 'email' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="lease_start"><?php esc_html_e( 'Date début bail', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="lease_start" id="lease_start" value="<?php echo esc_attr( $field( 'lease_start' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="lease_end"><?php esc_html_e( 'Date fin bail', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="lease_end" id="lease_end" value="<?php echo esc_attr( $field( 'lease_end' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="rent_amount"><?php esc_html_e( 'Montant du loyer', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="rent_amount" id="rent_amount" value="<?php echo esc_attr( $field( 'rent_amount', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="deposit_paid"><?php esc_html_e( 'Dépôt versé', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="deposit_paid" id="deposit_paid" value="<?php echo esc_attr( $field( 'deposit_paid', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
				<select name="status" id="status">
					<?php foreach ( Limpeed_Tenants::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'actif' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Le statut du bien associé (loué/vacant) sera mis à jour automatiquement.', 'limpeed-immobilier' ); ?></p>
			</div>

			<h3><?php esc_html_e( 'Informations complémentaires', 'limpeed-immobilier' ); ?></h3>

			<div class="limpeed-form-row">
				<label for="id_document_type"><?php esc_html_e( 'Pièce d\'identité', 'limpeed-immobilier' ); ?></label>
				<select name="id_document_type" id="id_document_type">
					<option value=""><?php esc_html_e( '— Non renseigné —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( Limpeed_Tenants::get_id_document_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'id_document_type' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="id_document_number" id="id_document_number" placeholder="<?php esc_attr_e( 'Numéro du document', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $field( 'id_document_number' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="date_of_birth"><?php esc_html_e( 'Date de naissance', 'limpeed-immobilier' ); ?></label>
				<input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo esc_attr( $field( 'date_of_birth' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="profession"><?php esc_html_e( 'Profession', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="profession" id="profession" value="<?php echo esc_attr( $field( 'profession' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="dependents_count"><?php esc_html_e( 'Personnes à charge', 'limpeed-immobilier' ); ?></label>
				<input type="number" min="0" step="1" name="dependents_count" id="dependents_count" value="<?php echo esc_attr( $field( 'dependents_count', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="guarantor_name"><?php esc_html_e( 'Garant', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="guarantor_name" id="guarantor_name" placeholder="<?php esc_attr_e( 'Nom complet du garant', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $field( 'guarantor_name' ) ); ?>">
				<input type="text" name="guarantor_phone" id="guarantor_phone" placeholder="<?php esc_attr_e( 'Téléphone du garant', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $field( 'guarantor_phone' ) ); ?>">
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

	<script type="application/json" id="limpeed-tenant-cascade-data">
	<?php echo wp_json_encode( array( 'buildings' => $buildings_json, 'properties' => $properties_json ) ); ?>
	</script>
	<script>
	( function () {
		var dataEl = document.getElementById( 'limpeed-tenant-cascade-data' );
		if ( ! dataEl ) {
			return;
		}
		var data = JSON.parse( dataEl.textContent );

		var ownerSelect    = document.getElementById( 'limpeed_ui_owner_id' );
		var buildingSelect = document.getElementById( 'limpeed_ui_building_id' );
		var propertySelect = document.getElementById( 'property_id' );

		if ( ! ownerSelect || ! buildingSelect || ! propertySelect ) {
			return;
		}

		function clearOptions( select, placeholder ) {
			select.innerHTML = '';
			var opt = document.createElement( 'option' );
			opt.value = '';
			opt.textContent = placeholder;
			select.appendChild( opt );
		}

		function populateBuildings( ownerId, selectedBuildingId ) {
			clearOptions( buildingSelect, '<?php echo esc_js( __( '— Choisir un édifice —', 'limpeed-immobilier' ) ); ?>' );
			buildingSelect.disabled = ! ownerId;
			if ( ! ownerId ) {
				return;
			}
			data.buildings.forEach( function ( building ) {
				if ( String( building.owner_id ) === String( ownerId ) ) {
					var opt = document.createElement( 'option' );
					opt.value = building.id;
					opt.textContent = building.label;
					if ( selectedBuildingId && String( building.id ) === String( selectedBuildingId ) ) {
						opt.selected = true;
					}
					buildingSelect.appendChild( opt );
				}
			} );
		}

		function populateProperties( buildingId, selectedPropertyId ) {
			clearOptions( propertySelect, '<?php echo esc_js( __( '— Choisir un sous-édifice —', 'limpeed-immobilier' ) ); ?>' );
			propertySelect.disabled = ! buildingId;
			if ( ! buildingId ) {
				return;
			}
			data.properties.forEach( function ( property ) {
				if ( String( property.building_id ) === String( buildingId ) ) {
					var opt = document.createElement( 'option' );
					opt.value = property.id;
					opt.textContent = property.label;
					if ( selectedPropertyId && String( property.id ) === String( selectedPropertyId ) ) {
						opt.selected = true;
					}
					propertySelect.appendChild( opt );
				}
			} );
		}

		ownerSelect.addEventListener( 'change', function () {
			populateBuildings( ownerSelect.value, null );
			populateProperties( null, null );
		} );

		buildingSelect.addEventListener( 'change', function () {
			populateProperties( buildingSelect.value, null );
		} );
	} )();
	</script>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste dynamique (Alpine.js) : recherche/filtres en direct, modale
	// d'ajout/modification, actions Ajax, panneau de détail. Toutes les
	// données transitent par l'API REST limpeed/v1 (voir
	// includes/class-limpeed-rest-api.php) ; ce fichier ne fait que fournir
	// le balisage et la configuration initiale (statuts, biens pour le
	// filtre, textes traduits, URL + nonce REST).
	// -----------------------------------------------------------------
	$statuses           = Limpeed_Tenants::get_statuses();
	$id_document_types  = Limpeed_Tenants::get_id_document_types();
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

	$month_names = array();
	for ( $m = 1; $m <= 12; $m++ ) {
		$month_names[] = date_i18n( 'M', mktime( 0, 0, 0, $m, 1, (int) current_time( 'Y' ) ) );
	}

	$app_config = array(
		'statuses'         => $statuses,
		'idDocumentTypes'  => $id_document_types,
		'propertyOptions'  => $property_options,
		'monthNames'       => $month_names,
		'i18n'             => array(
			'created'                => __( 'Locataire ajouté avec succès.', 'limpeed-immobilier' ),
			'updated'                => __( 'Locataire mis à jour avec succès.', 'limpeed-immobilier' ),
			'deleted'                => __( 'Locataire supprimé avec succès.', 'limpeed-immobilier' ),
			'confirmDelete'          => __( 'Confirmez-vous la suppression de ce locataire ?', 'limpeed-immobilier' ),
			'amendmentAdded'         => __( 'Avenant ajouté avec succès.', 'limpeed-immobilier' ),
			'confirmDeleteAmendment' => __( 'Confirmez-vous la suppression de cet avenant ?', 'limpeed-immobilier' ),
		),
	);

	$rest_config = array(
		'root'  => esc_url_raw( rest_url( 'limpeed/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);

	// Cartes de synthèse (mêmes indicateurs que les cartes "Locataires" et
	// KPI du tableau de bord, recalculés ici directement en PHP puisque
	// cette vue est déjà rendue côté serveur).
	$tenants_total    = Limpeed_Tenants::count();
	$tenants_active   = Limpeed_Tenants::count( array( 'status' => 'actif' ) );
	$tenants_inactive = max( 0, $tenants_total - $tenants_active );
	$period_summary   = Limpeed_Payments::get_period_summary();
	$unpaid_tenants   = $period_summary['unpaid_tenants'];
	$unpaid_total     = 0.0;
	foreach ( $unpaid_tenants as $unpaid_tenant ) {
		$unpaid_total += (float) $unpaid_tenant->rent_amount;
	}
	$expiring_leases_count = count( Limpeed_Tenants::get_expiring_leases( 30 ) );
	$tenants_active_ratio  = $tenants_total > 0 ? round( ( $tenants_active / $tenants_total ) * 100 ) : 0;
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $tenants_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Locataires', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-admin-users"></span></span>
			</div>
			<div class="limpeed-app-card-ratio"><span style="width: <?php echo esc_attr( $tenants_active_ratio ); ?>%; background: var(--limpeed-green);"></span></div>
			<div class="limpeed-app-card-bar limpeed-bar-red">
				<span><?php printf( esc_html__( '%1$d actifs / %2$d inactifs', 'limpeed-immobilier' ), (int) $tenants_active, (int) $tenants_inactive ); ?></span>
			</div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( count( $unpaid_tenants ) ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Loyers impayés (mois en cours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-warning"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange">
				<span><?php echo esc_html( Limpeed_Payments::format_amount( $unpaid_total ) ); ?></span>
			</div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $expiring_leases_count ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Baux arrivant à échéance (30 jours)', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-calendar-alt"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
	</div>

	<div class="limpeed-app-panel" x-data="limpeedTenantsApp(<?php echo esc_attr( wp_json_encode( $app_config ) ); ?>)">
		<div class="limpeed-app-toolbar">
			<div class="limpeed-app-search">
				<input type="text" x-model="search" @input="onSearchInput()" placeholder="<?php esc_attr_e( 'Rechercher un locataire...', 'limpeed-immobilier' ); ?>">
				<select x-model="filterPropertyId" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
					<template x-for="option in propertyOptions" :key="option.id">
						<option :value="option.id" x-text="option.label"></option>
					</template>
				</select>
				<select x-model="filterStatus" @change="onFilterChange()">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<template x-for="[key, label] in Object.entries(statuses)" :key="key">
						<option :value="key" x-text="label"></option>
					</template>
				</select>
			</div>
			<button type="button" class="limpeed-app-btn" @click="openAddModal()"><?php esc_html_e( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></button>
		</div>

		<div class="limpeed-entity-grid">
			<template x-if="loading">
				<template x-for="n in 6" :key="n">
					<div class="limpeed-entity-card-skeleton"></div>
				</template>
			</template>
			<p x-show="!loading && items.length === 0" class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun locataire pour le moment.', 'limpeed-immobilier' ); ?></p>
			<template x-for="row in items" :key="row.id">
				<div class="limpeed-entity-card" :class="'limpeed-entity-card-status-' + row.status" @click="openDrawer(row)">
					<div class="limpeed-entity-card-header">
						<span class="limpeed-entity-card-avatar is-solid limpeed-icon-red" x-text="window.limpeedInitials(row.full_name)"></span>
						<div class="limpeed-entity-card-header-text">
							<div class="limpeed-entity-card-title" x-text="row.full_name"></div>
							<div class="limpeed-entity-card-subtitle" x-text="row.property_label || '—'"></div>
						</div>
						<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + row.status" x-text="row.status_label"></span>
					</div>
					<div class="limpeed-entity-card-meta">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-admin-users"></span>
							<span x-text="row.owner_label || '—'"></span>
						</div>
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-phone"></span>
							<span x-text="row.phone || '—'"></span>
						</div>
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-money-alt"></span>
							<span x-text="row.rent_formatted"></span>
						</div>
					</div>
					<div class="limpeed-entity-card-footer">
						<button type="button" class="limpeed-app-link-btn" @click.stop="openEditModal(row)"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-link-btn is-danger" @click.stop="deleteTenant(row)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
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
					<h2 x-text="modal.mode === 'edit' ? '<?php echo esc_js( __( 'Modifier le locataire', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter un locataire', 'limpeed-immobilier' ) ); ?>'"></h2>
					<button type="button" class="limpeed-app-modal-close" @click="closeModal()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
				</div>
				<form class="limpeed-app-form" @submit.prevent="saveTenant()">
					<div class="limpeed-app-modal-body">
						<div class="limpeed-app-notice limpeed-app-notice-error" x-show="modal.errors.length">
							<ul>
								<template x-for="(error, index) in modal.errors" :key="index">
									<li x-text="error"></li>
								</template>
							</ul>
						</div>

						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.owner_id" @change="onModalOwnerChange()" :disabled="modal.loadingCascade">
								<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
								<template x-for="owner in modal.owners" :key="owner.id">
									<option :value="owner.id" x-text="owner.label"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.building_id" @change="onModalBuildingChange()" :disabled="! modal.data.owner_id || modal.loadingCascade">
								<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
								<template x-for="building in modal.buildings" :key="building.id">
									<option :value="building.id" x-text="building.label"></option>
								</template>
							</select>
						</div>
						<div class="limpeed-form-row">
							<label><?php esc_html_e( 'Sous-édifice (bien loué)', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
							<select x-model="modal.data.property_id" :disabled="! modal.data.building_id || modal.loadingCascade">
								<option value=""><?php esc_html_e( '— Choisir un sous-édifice —', 'limpeed-immobilier' ); ?></option>
								<template x-for="property in modal.properties" :key="property.id">
									<option :value="property.id" :disabled="property.occupied" x-text="property.label + (property.occupied ? ' (<?php echo esc_js( __( 'occupé', 'limpeed-immobilier' ) ); ?>)' : '')"></option>
								</template>
							</select>
							<p class="limpeed-app-form-hint"><?php esc_html_e( 'Choisissez d\'abord le propriétaire, puis l\'édifice, pour afficher ses sous-édifices disponibles.', 'limpeed-immobilier' ); ?></p>
						</div>

						<div class="limpeed-app-modal-grid">
							<div class="limpeed-form-row is-full">
								<label><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
								<input type="text" x-model="modal.data.full_name" required>
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="modal.data.phone">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label>
								<input type="email" x-model="modal.data.email">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Date début bail', 'limpeed-immobilier' ); ?></label>
								<input type="date" x-model="modal.data.lease_start">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Date fin bail', 'limpeed-immobilier' ); ?></label>
								<input type="date" x-model="modal.data.lease_end">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Montant du loyer', 'limpeed-immobilier' ); ?></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.rent_amount">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Dépôt versé', 'limpeed-immobilier' ); ?></label>
								<input type="number" step="0.01" min="0" x-model="modal.data.deposit_paid">
							</div>
							<div class="limpeed-form-row is-full">
								<label><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.status">
									<template x-for="[key, label] in Object.entries(statuses)" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
						</div>

						<h3><?php esc_html_e( 'Informations complémentaires', 'limpeed-immobilier' ); ?></h3>

						<div class="limpeed-app-modal-grid">
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Pièce d\'identité', 'limpeed-immobilier' ); ?></label>
								<select x-model="modal.data.id_document_type">
									<option value=""><?php esc_html_e( '— Non renseigné —', 'limpeed-immobilier' ); ?></option>
									<template x-for="[key, label] in Object.entries(idDocumentTypes)" :key="key">
										<option :value="key" x-text="label"></option>
									</template>
								</select>
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Numéro du document', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="modal.data.id_document_number">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Date de naissance', 'limpeed-immobilier' ); ?></label>
								<input type="date" x-model="modal.data.date_of_birth">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Profession', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="modal.data.profession">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Personnes à charge', 'limpeed-immobilier' ); ?></label>
								<input type="number" min="0" step="1" x-model="modal.data.dependents_count">
							</div>
							<div class="limpeed-form-row">
								<label><?php esc_html_e( 'Garant', 'limpeed-immobilier' ); ?></label>
								<input type="text" x-model="modal.data.guarantor_name" placeholder="<?php esc_attr_e( 'Nom complet du garant', 'limpeed-immobilier' ); ?>">
							</div>
							<div class="limpeed-form-row">
								<label>&nbsp;</label>
								<input type="text" x-model="modal.data.guarantor_phone" placeholder="<?php esc_attr_e( 'Téléphone du garant', 'limpeed-immobilier' ); ?>">
							</div>
						</div>
					</div>
					<div class="limpeed-app-modal-footer">
						<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="closeModal()"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></button>
						<button type="submit" class="limpeed-app-btn" :disabled="modal.saving">
							<span x-text="modal.saving ? '<?php echo esc_js( __( 'Enregistrement...', 'limpeed-immobilier' ) ); ?>' : (modal.mode === 'edit' ? '<?php echo esc_js( __( 'Mettre à jour', 'limpeed-immobilier' ) ); ?>' : '<?php echo esc_js( __( 'Ajouter', 'limpeed-immobilier' ) ); ?>')"></span>
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
							<h2 x-text="drawer.tenant ? drawer.tenant.full_name : ''"></h2>
							<div class="limpeed-app-drawer-subtitle" x-text="drawer.tenant ? drawer.tenant.property_label : ''"></div>
						</div>
						<button type="button" class="limpeed-app-modal-close" @click="closeDrawer()" aria-label="<?php esc_attr_e( 'Fermer', 'limpeed-immobilier' ); ?>">&times;</button>
					</div>
					<div class="limpeed-app-drawer-tabs">
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'infos' }" @click="switchDrawerTab('infos')"><?php esc_html_e( 'Infos', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'paiements' }" @click="switchDrawerTab('paiements')"><?php esc_html_e( 'Paiements', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'avenants' }" @click="switchDrawerTab('avenants')"><?php esc_html_e( 'Avenants', 'limpeed-immobilier' ); ?></button>
						<button type="button" class="limpeed-app-drawer-tab" :class="{ 'is-active': drawer.tab === 'historique' }" @click="switchDrawerTab('historique')"><?php esc_html_e( 'Historique du bail', 'limpeed-immobilier' ); ?></button>
					</div>
					<div class="limpeed-app-drawer-body">
						<p x-show="drawer.loading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>

						<template x-if="! drawer.loading && drawer.tab === 'infos' && drawer.tenant">
							<div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.tenant.owner_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.tenant.building_label || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.tenant.phone || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.tenant.email || '—'"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value" x-text="drawer.tenant.rent_formatted"></div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value">
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + drawer.tenant.status" x-text="drawer.tenant.status_label"></span>
									</div>
								</div>
								<div class="limpeed-app-drawer-field">
									<span class="limpeed-app-drawer-field-label"><?php esc_html_e( 'Bail', 'limpeed-immobilier' ); ?></span>
									<div class="limpeed-app-drawer-field-value">
										<span x-text="drawer.tenant.lease_start || '—'"></span> &rarr; <span x-text="drawer.tenant.lease_end || '—'"></span>
									</div>
								</div>
								<p><a :href="'<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => '' ) ) ); ?>' + drawer.tenant.id"><?php esc_html_e( 'Voir la fiche complète (dossier, calendrier de paiement)', 'limpeed-immobilier' ); ?> &rarr;</a></p>
								<p><a :href="drawer.tenant.contract_url" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Générer le contrat de bail (PDF)', 'limpeed-immobilier' ); ?></a></p>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'paiements'">
							<div>
								<div class="limpeed-app-drawer-calendar-nav">
									<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="changeCalendarYear(-1)">&laquo;</button>
									<strong x-text="drawer.calendarYear"></strong>
									<button type="button" class="limpeed-app-btn limpeed-app-btn-secondary" @click="changeCalendarYear(1)">&raquo;</button>
								</div>
								<p x-show="drawer.calendarLoading"><?php esc_html_e( 'Chargement...', 'limpeed-immobilier' ); ?></p>
								<template x-for="entry in drawer.calendar" :key="entry.period">
									<div class="limpeed-app-drawer-list-item" x-show="! drawer.calendarLoading">
										<span x-text="monthLabel(entry.period)"></span>
										<span x-show="entry.amount_formatted" x-text="entry.amount_formatted"></span>
										<span class="limpeed-app-badge" :class="'limpeed-app-badge-' + entry.status" x-text="entry.status_label"></span>
									</div>
								</template>
							</div>
						</template>

						<template x-if="! drawer.loading && drawer.tab === 'avenants'">
							<div>
								<p x-show="drawer.amendments.length === 0"><?php esc_html_e( 'Aucun avenant enregistré.', 'limpeed-immobilier' ); ?></p>
								<template x-for="amendment in drawer.amendments" :key="amendment.id">
									<div class="limpeed-app-drawer-list-item">
										<span>
											<span x-text="amendment.amendment_date"></span> — <span x-text="amendment.description"></span>
											<template x-if="amendment.new_rent_formatted"><span> (<?php esc_html_e( 'nouveau loyer', 'limpeed-immobilier' ); ?> : <span x-text="amendment.new_rent_formatted"></span>)</span></template>
										</span>
										<button type="button" class="limpeed-app-link-btn is-danger" @click="deleteAmendment(amendment)"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></button>
									</div>
								</template>

								<form @submit.prevent="addAmendment()" style="margin-top: 16px;">
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Date', 'limpeed-immobilier' ); ?></label>
										<input type="date" x-model="amendmentForm.amendment_date" required>
									</div>
									<div class="limpeed-form-row">
										<label><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
										<textarea x-model="amendmentForm.description" rows="2" placeholder="<?php esc_attr_e( 'Ex : révision annuelle du loyer', 'limpeed-immobilier' ); ?>" required></textarea>
									</div>
									<div class="limpeed-app-modal-grid">
										<div class="limpeed-form-row">
											<label><?php esc_html_e( 'Nouveau loyer (optionnel)', 'limpeed-immobilier' ); ?></label>
											<input type="number" step="0.01" min="0" x-model="amendmentForm.new_rent_amount">
										</div>
										<div class="limpeed-form-row">
											<label><?php esc_html_e( 'Nouvelle date de fin de bail (optionnel)', 'limpeed-immobilier' ); ?></label>
											<input type="date" x-model="amendmentForm.new_lease_end">
										</div>
									</div>
									<button type="submit" class="limpeed-app-btn" :disabled="amendmentForm.saving"><?php esc_html_e( 'Ajouter l\'avenant', 'limpeed-immobilier' ); ?></button>
								</form>
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

	<noscript><p><?php esc_html_e( 'Cette section nécessite JavaScript pour afficher la liste des locataires.', 'limpeed-immobilier' ); ?></p></noscript>

	<script>
	window.limpeedRest = <?php echo wp_json_encode( $rest_config ); ?>;
	</script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<?php /* tenants-app.js enregistre son composant via l'événement "alpine:init", déclenché de façon synchrone dès l'exécution du script Alpine ci-dessous : il doit donc être chargé (et son listener attaché) AVANT le script Alpine, pas après. */ ?>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/tenants-app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/vendor/alpinejs/alpine.min.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>" defer></script>
<?php endif; ?>
