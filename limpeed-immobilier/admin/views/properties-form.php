<?php
/**
 * Vue : formulaire d'ajout / modification d'un bien (sous-édifice).
 *
 * @var object|null $property
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $buildings
 * @var int         $preselected_building
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $property );

$field = function ( $name, $default = '' ) use ( $property, $posted, $is_edit ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	if ( $is_edit && isset( $property->$name ) ) {
		return $property->$name;
	}
	return $default;
};

$list_url       = add_query_arg( array( 'page' => 'limpeed-properties' ), admin_url( 'admin.php' ) );
$current_tenant = $is_edit ? Limpeed_Properties::get_current_tenant( $property->id ) : null;
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier le bien (sous-édifice)', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un bien (sous-édifice)', 'limpeed-immobilier' ); ?></h1>

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
				$building = Limpeed_Buildings::get( $property->building_id );
				$owner    = Limpeed_Owners::get( $property->owner_id );
				if ( $building ) {
					$building_url = add_query_arg(
						array(
							'page'   => 'limpeed-buildings',
							'action' => 'edit',
							'id'     => $building->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers l'édifice */
						esc_html__( 'Édifice : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $building_url ) . '">' . esc_html( $building->name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php
				if ( $owner ) {
					$owner_url = add_query_arg(
						array(
							'page'   => 'limpeed-owners',
							'action' => 'edit',
							'id'     => $owner->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $owner_url ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php if ( $current_tenant ) : ?>
					<?php
					$tenant_url = add_query_arg(
						array(
							'page'   => 'limpeed-tenants',
							'action' => 'edit',
							'id'     => $current_tenant->id,
						),
						admin_url( 'admin.php' )
					);
					printf(
						/* translators: %s: lien vers le locataire actuel */
						esc_html__( 'Locataire actuel : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( $tenant_url ) . '">' . esc_html( $current_tenant->full_name ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Aucun locataire actuel', 'limpeed-immobilier' ); ?>
				<?php endif; ?>
				&nbsp;|&nbsp;
				<?php
				$payments_url = add_query_arg(
					array(
						'page'        => 'limpeed-payments',
						'property_id' => $property->id,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $payments_url ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php
	$form_action = add_query_arg(
		array_filter(
			array(
				'page'   => 'limpeed-properties',
				'action' => $is_edit ? 'edit' : 'add',
				'id'     => $is_edit ? $property->id : null,
			)
		),
		admin_url( 'admin.php' )
	);
	?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_property', 'limpeed_property_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<?php if ( empty( $buildings ) ) : ?>
							<p class="description">
								<?php
								$add_building_url = add_query_arg( array( 'page' => 'limpeed-buildings', 'action' => 'add' ), admin_url( 'admin.php' ) );
								printf(
									/* translators: %s: lien vers l'ajout d'un édifice */
									esc_html__( 'Aucun édifice enregistré. %s avant d\'ajouter un sous-édifice.', 'limpeed-immobilier' ),
									'<a href="' . esc_url( $add_building_url ) . '">' . esc_html__( 'Créez-en un', 'limpeed-immobilier' ) . '</a>'
								);
								?>
							</p>
						<?php else : ?>
							<select name="building_id" id="building_id" required>
								<option value=""><?php esc_html_e( '— Choisir un édifice —', 'limpeed-immobilier' ); ?></option>
								<?php foreach ( $buildings as $building_option ) : ?>
									<?php
									$owner_option = Limpeed_Owners::get( $building_option->owner_id );
									$label        = $owner_option
										? sprintf( '%s — %s', $building_option->name, $owner_option->full_name )
										: $building_option->name;
									?>
									<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( (int) $field( 'building_id', $preselected_building ), $building_option->id ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Le propriétaire est déterminé automatiquement à partir de l\'édifice sélectionné.', 'limpeed-immobilier' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="reference"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="reference" type="text" id="reference" class="regular-text" value="<?php echo esc_attr( $field( 'reference' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : A1, RDC Gauche...', 'limpeed-immobilier' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="address"><?php esc_html_e( 'Adresse / repère', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><textarea name="address" id="address" class="large-text" rows="3" required><?php echo esc_textarea( $field( 'address' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="type"><?php esc_html_e( 'Type de bien', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="type" id="type">
							<?php foreach ( Limpeed_Properties::get_types() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'type', 'appartement' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="monthly_rent"><?php esc_html_e( 'Loyer mensuel', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<input name="monthly_rent" type="number" step="0.01" min="0" id="monthly_rent" class="regular-text" value="<?php echo esc_attr( $field( 'monthly_rent', 0 ) ); ?>">
						<p class="description" id="limpeed-rent-hint"></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="charges"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="charges" type="number" step="0.01" min="0" id="charges" class="regular-text" value="<?php echo esc_attr( $field( 'charges', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="deposit_amount"><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="deposit_amount" type="number" step="0.01" min="0" id="deposit_amount" class="regular-text" value="<?php echo esc_attr( $field( 'deposit_amount', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'vacant' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Le statut "Loué"/"Vacant" est mis à jour automatiquement selon la présence d\'un locataire actif.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Ajouter', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>

<script type="application/json" id="limpeed-average-rent-data">
<?php echo wp_json_encode( $average_rent_by_type ); ?>
</script>
<script>
( function () {
	var dataEl = document.getElementById( 'limpeed-average-rent-data' );
	var typeSelect = document.getElementById( 'type' );
	var hintEl = document.getElementById( 'limpeed-rent-hint' );
	if ( ! dataEl || ! typeSelect || ! hintEl ) {
		return;
	}
	var averages = JSON.parse( dataEl.textContent );

	function updateHint() {
		var avg = averages[ typeSelect.value ];
		if ( avg ) {
			hintEl.textContent = '<?php echo esc_js( __( 'Loyer moyen constaté pour ce type de bien : ', 'limpeed-immobilier' ) ); ?>' + Math.round( avg ).toLocaleString();
		} else {
			hintEl.textContent = '';
		}
	}

	typeSelect.addEventListener( 'change', updateHint );
	updateHint();
} )();
</script>
