<?php
/**
 * Contenu frontend de la section "Biens" (liste + formulaire).
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
	$property = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$property = Limpeed_Properties::get( (int) $_GET['id'] );
		if ( ! $property ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Bien introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit              = ! empty( $property );
	$errors               = Limpeed_Frontend_Properties::$errors;
	$posted               = Limpeed_Frontend_Properties::$posted;
	$buildings            = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
	$preselected_building = isset( $_GET['building_id'] ) ? (int) $_GET['building_id'] : 0;
	$current_tenant       = $is_edit ? Limpeed_Properties::get_current_tenant( $property->id ) : null;

	$field = function ( $name, $default = '' ) use ( $property, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $property->$name ) ) {
			return $property->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le bien (sous-édifice)', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un bien (sous-édifice)', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$building = Limpeed_Buildings::get( $property->building_id );
				$owner    = Limpeed_Owners::get( $property->owner_id );
				if ( $building ) {
					printf(
						/* translators: %s: lien vers l'édifice */
						esc_html__( 'Édifice : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $building->id ) ) ) . '">' . esc_html( $building->name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php
				if ( $owner ) {
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<?php if ( $current_tenant ) : ?>
					<?php
					printf(
						/* translators: %s: lien vers le locataire actuel */
						esc_html__( 'Locataire actuel : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $current_tenant->id ) ) ) . '">' . esc_html( $current_tenant->full_name ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Aucun locataire actuel', 'limpeed-immobilier' ); ?>
				<?php endif; ?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', array( 'property_id' => $property->id ) ) ); ?>"><?php esc_html_e( 'Voir l\'historique des paiements', 'limpeed-immobilier' ); ?></a>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', $is_edit ? array( 'action' => 'edit', 'id' => $property->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_property', 'limpeed_property_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="property_id" value="<?php echo esc_attr( $property->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="building_id"><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<?php if ( empty( $buildings ) ) : ?>
					<p class="limpeed-app-description">
						<?php
						printf(
							/* translators: %s: lien vers l'ajout d'un édifice */
							esc_html__( 'Aucun édifice enregistré. %s avant d\'ajouter un sous-édifice.', 'limpeed-immobilier' ),
							'<a href="' . esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add' ) ) ) . '">' . esc_html__( 'Créez-en un', 'limpeed-immobilier' ) . '</a>'
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
					<p class="limpeed-app-description"><?php esc_html_e( 'Le propriétaire est déterminé automatiquement à partir de l\'édifice sélectionné.', 'limpeed-immobilier' ); ?></p>
				<?php endif; ?>
			</div>
			<div class="limpeed-form-row">
				<label for="reference"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="reference" id="reference" value="<?php echo esc_attr( $field( 'reference' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : A1, RDC Gauche...', 'limpeed-immobilier' ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="address"><?php esc_html_e( 'Adresse / repère', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<textarea name="address" id="address" rows="3" required><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>
			<div class="limpeed-form-row">
				<label for="type"><?php esc_html_e( 'Type de bien', 'limpeed-immobilier' ); ?></label>
				<select name="type" id="type">
					<?php foreach ( Limpeed_Properties::get_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'type', 'appartement' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="monthly_rent"><?php esc_html_e( 'Loyer mensuel', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="monthly_rent" id="monthly_rent" value="<?php echo esc_attr( $field( 'monthly_rent', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="charges"><?php esc_html_e( 'Charges', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="charges" id="charges" value="<?php echo esc_attr( $field( 'charges', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="deposit_amount"><?php esc_html_e( 'Dépôt de garantie', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="deposit_amount" id="deposit_amount" value="<?php echo esc_attr( $field( 'deposit_amount', 0 ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label>
				<select name="status" id="status">
					<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'vacant' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="limpeed-app-description"><?php esc_html_e( 'Le statut "Loué"/"Vacant" est mis à jour automatiquement selon la présence d\'un locataire actif.', 'limpeed-immobilier' ); ?></p>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$search      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$owner_id    = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;
	$building_id = isset( $_GET['building_id'] ) ? (int) $_GET['building_id'] : 0;
	$status      = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page    = 20;

	$args = array(
		'search'      => $search,
		'owner_id'    => $owner_id,
		'building_id' => $building_id,
		'status'      => $status,
		'per_page'    => $per_page,
		'paged'       => $paged,
	);

	$total_items     = Limpeed_Properties::count( $args );
	$properties      = Limpeed_Properties::get_all( $args );
	$filter_owners   = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$filter_buildings = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="limpeed_view" value="properties">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un bien...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<select name="owner_id">
					<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $owner_id, $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="building_id">
					<option value=""><?php esc_html_e( 'Tous les édifices', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_buildings as $building_option ) : ?>
						<option value="<?php echo esc_attr( $building_option->id ); ?>" <?php selected( $building_id, $building_option->id ); ?>>
							<?php echo esc_html( $building_option->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un bien', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Bien ajouté avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Bien mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Bien supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Adresse (sous-édifice)', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Type', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyer', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Locataire actuel', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $properties ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'Aucun bien pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php
				$statuses = Limpeed_Properties::get_statuses();
				$types    = Limpeed_Properties::get_types();
				foreach ( $properties as $property_row ) :
					$edit_url   = Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $property_row->id ) );
					$delete_url = wp_nonce_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'delete', 'id' => $property_row->id ) ), 'limpeed_delete_property_' . $property_row->id );
					$building   = Limpeed_Buildings::get( $property_row->building_id );
					$owner      = Limpeed_Owners::get( $property_row->owner_id );
					$tenant     = Limpeed_Properties::get_current_tenant( $property_row->id );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo $property_row->reference ? esc_html( $property_row->reference ) : '&mdash;'; ?></a></td>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $property_row->address ); ?></a></td>
						<td><?php echo $building ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $building->id ) ) ) . '">' . esc_html( $building->name ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo $owner ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo isset( $types[ $property_row->type ] ) ? esc_html( $types[ $property_row->type ] ) : esc_html( $property_row->type ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $property_row->monthly_rent, 2 ) ); ?></td>
						<td><span class="limpeed-app-badge"><?php echo isset( $statuses[ $property_row->status ] ) ? esc_html( $statuses[ $property_row->status ] ) : esc_html( $property_row->status ); ?></span></td>
						<td><?php echo $tenant ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant->id ) ) ) . '">' . esc_html( $tenant->full_name ) . '</a>' : '&mdash;'; ?></td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce bien ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search, 'owner_id' => $owner_id, 'building_id' => $building_id, 'status' => $status ) ); ?>
	</div>
<?php endif; ?>
