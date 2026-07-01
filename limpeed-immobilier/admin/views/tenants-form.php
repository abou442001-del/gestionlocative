<?php
/**
 * Vue : formulaire d'ajout / modification d'un locataire.
 * Sélection en cascade : propriétaire → édifice → sous-édifice (bien loué).
 *
 * @var object|null $tenant
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $owners
 * @var array       $buildings
 * @var array       $properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $tenant );

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
// pour pré-remplir correctement la cascade dès le premier affichage (avant même
// l'exécution du JS de filtrage).
$current_property_id = (int) $field( 'property_id' );
$current_building_id = 0;
$current_owner_id     = 0;

if ( $current_property_id ) {
	$current_property = Limpeed_Properties::get( $current_property_id );
	if ( $current_property ) {
		$current_building_id = (int) $current_property->building_id;
		$current_building     = Limpeed_Buildings::get( $current_building_id );
		if ( $current_building ) {
			$current_owner_id = (int) $current_building->owner_id;
		}
	}
}

$buildings_for_owner    = array_filter( $buildings, function ( $b ) use ( $current_owner_id ) {
	return (int) $b->owner_id === $current_owner_id;
} );
$properties_for_building = array_filter( $properties, function ( $p ) use ( $current_building_id ) {
	return (int) $p->building_id === $current_building_id;
} );

$buildings_json = array_map(
	function ( $b ) {
		return array( 'id' => (int) $b->id, 'owner_id' => (int) $b->owner_id, 'label' => $b->name );
	},
	$buildings
);
$properties_json = array_map(
	function ( $p ) {
		return array( 'id' => (int) $p->id, 'building_id' => (int) $p->building_id, 'label' => $p->address );
	},
	$properties
);

$list_url = add_query_arg( array( 'page' => 'limpeed-tenants' ), admin_url( 'admin.php' ) );
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier le locataire', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $is_edit ) : ?>
		<div class="limpeed-cross-nav">
			<p>
				<?php
				$property = Limpeed_Properties::get( $tenant->property_id );
				if ( $property ) {
					$property_url = add_query_arg(
						array(
							'page'   => 'limpeed-properties',
							'action' => 'edit',
							'id'     => $property->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers le bien loué */
						esc_html__( 'Bien loué : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $property_url ) . '">' . esc_html( $property->address ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php
				$payments_url = add_query_arg(
					array(
						'page'      => 'limpeed-payments',
						'tenant_id' => $tenant->id,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $payments_url ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</p>
		</div>

		<?php
		$advance_status  = Limpeed_Tenants::get_advance_status( $tenant );
		$calendar_year   = isset( $_GET['calendar_year'] ) ? (int) $_GET['calendar_year'] : (int) current_time( 'Y' );
		$payment_calendar = Limpeed_Tenants::get_payment_calendar( $tenant, $calendar_year );

		$advance_labels = array(
			'aucun_paiement' => __( 'Aucun paiement enregistré', 'limpeed-immobilier' ),
			'en_retard'      => __( 'En retard', 'limpeed-immobilier' ),
			'a_jour'         => __( 'À jour', 'limpeed-immobilier' ),
			'en_avance'      => __( 'En avance', 'limpeed-immobilier' ),
		);
		?>
		<h2><?php esc_html_e( 'Suivi des paiements', 'limpeed-immobilier' ); ?></h2>

		<div class="limpeed-advance-panel">
			<div class="limpeed-advance-stat">
				<span class="limpeed-advance-stat-label"><?php esc_html_e( 'Avance requise', 'limpeed-immobilier' ); ?></span>
				<span class="limpeed-advance-stat-value"><?php echo esc_html( number_format_i18n( $advance_status['advance_amount'], 2 ) ); ?> <small>(<?php echo esc_html( $advance_status['advance_months'] ); ?> <?php esc_html_e( 'mois', 'limpeed-immobilier' ); ?>)</small></span>
			</div>
			<div class="limpeed-advance-stat">
				<span class="limpeed-advance-stat-label"><?php esc_html_e( 'Payé jusqu\'à', 'limpeed-immobilier' ); ?></span>
				<span class="limpeed-advance-stat-value"><?php echo $advance_status['paid_until'] ? esc_html( $advance_status['paid_until'] ) : '&mdash;'; ?></span>
			</div>
			<div class="limpeed-advance-stat">
				<span class="limpeed-advance-stat-label"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></span>
				<span class="limpeed-advance-stat-value">
					<span class="limpeed-badge limpeed-badge-<?php echo esc_attr( $advance_status['status'] ); ?>"><?php echo esc_html( $advance_labels[ $advance_status['status'] ] ); ?></span>
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
		</div>

		<div class="limpeed-calendar-nav">
			<a href="<?php echo esc_url( add_query_arg( 'calendar_year', $calendar_year - 1 ) ); ?>" class="button">&laquo; <?php echo esc_html( $calendar_year - 1 ); ?></a>
			<strong><?php echo esc_html( $calendar_year ); ?></strong>
			<a href="<?php echo esc_url( add_query_arg( 'calendar_year', $calendar_year + 1 ) ); ?>" class="button"><?php echo esc_html( $calendar_year + 1 ); ?> &raquo;</a>
		</div>

		<div class="limpeed-payment-calendar">
			<?php foreach ( $payment_calendar as $month => $entry ) : ?>
				<?php
				$month_label = date_i18n( 'M', mktime( 0, 0, 0, $month, 1, $calendar_year ) );
				$css_class   = 'limpeed-calendar-month limpeed-calendar-month-' . $entry['status'];

				if ( 'hors_bail' === $entry['status'] ) {
					?>
					<span class="<?php echo esc_attr( $css_class ); ?>">
						<span class="limpeed-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
					</span>
					<?php
				} elseif ( $entry['payment'] ) {
					$payment_url = add_query_arg(
						array( 'page' => 'limpeed-payments', 'action' => 'edit', 'id' => $entry['payment']->id ),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $payment_url ); ?>" class="<?php echo esc_attr( $css_class ); ?>">
						<span class="limpeed-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
						<span class="limpeed-calendar-month-amount"><?php echo esc_html( number_format_i18n( (float) $entry['payment']->amount, 0 ) ); ?></span>
					</a>
					<?php
				} else {
					$payment_url = add_query_arg(
						array( 'page' => 'limpeed-payments', 'action' => 'add', 'tenant_id' => $tenant->id, 'period' => $entry['period'] ),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $payment_url ); ?>" class="<?php echo esc_attr( $css_class ); ?>">
						<span class="limpeed-calendar-month-label"><?php echo esc_html( $month_label ); ?></span>
						<span class="limpeed-calendar-month-amount"><?php esc_html_e( 'Enregistrer', 'limpeed-immobilier' ); ?></span>
					</a>
					<?php
				}
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	$form_action = add_query_arg(
		array_filter(
			array(
				'page'   => 'limpeed-tenants',
				'action' => $is_edit ? 'edit' : 'add',
				'id'     => $is_edit ? $tenant->id : null,
			)
		),
		admin_url( 'admin.php' )
	);
	?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_tenant', 'limpeed_tenant_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="limpeed_ui_owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select id="limpeed_ui_owner_id">
							<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $owners as $owner_option ) : ?>
								<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $current_owner_id, $owner_option->id ); ?>>
									<?php echo esc_html( $owner_option->full_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="limpeed_ui_building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select id="limpeed_ui_building_id" <?php disabled( empty( $current_owner_id ) ); ?>>
							<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $buildings_for_owner as $building_option ) : ?>
								<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( $current_building_id, $building_option->id ); ?>>
									<?php echo esc_html( $building_option->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="property_id"><?php esc_html_e( 'Sous-édifice (bien loué)', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select name="property_id" id="property_id" required <?php disabled( empty( $current_building_id ) ); ?>>
							<option value=""><?php esc_html_e( '— Choisir un sous-édifice —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $properties_for_building as $property_option ) : ?>
								<option value="<?php echo esc_attr( $property_option->id ); ?>" <?php selected( $current_property_id, $property_option->id ); ?>>
									<?php echo esc_html( $property_option->address ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Choisissez d\'abord le propriétaire, puis l\'édifice, pour afficher ses sous-édifices disponibles.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="full_name"><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="full_name" type="text" id="full_name" class="regular-text" required value="<?php echo esc_attr( $field( 'full_name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="phone"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="phone" type="text" id="phone" class="regular-text" value="<?php echo esc_attr( $field( 'phone' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="email" type="email" id="email" class="regular-text" value="<?php echo esc_attr( $field( 'email' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lease_start"><?php esc_html_e( 'Date début bail', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="lease_start" type="date" id="lease_start" value="<?php echo esc_attr( $field( 'lease_start' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lease_end"><?php esc_html_e( 'Date fin bail', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="lease_end" type="date" id="lease_end" value="<?php echo esc_attr( $field( 'lease_end' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="rent_amount"><?php esc_html_e( 'Montant du loyer', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="rent_amount" type="number" step="0.01" min="0" id="rent_amount" class="regular-text" value="<?php echo esc_attr( $field( 'rent_amount', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="deposit_paid"><?php esc_html_e( 'Dépôt versé', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="deposit_paid" type="number" step="0.01" min="0" id="deposit_paid" class="regular-text" value="<?php echo esc_attr( $field( 'deposit_paid', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Limpeed_Tenants::get_statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'actif' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Le statut du bien associé (loué/vacant) sera mis à jour automatiquement.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
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
