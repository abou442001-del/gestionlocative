<?php
/**
 * Contenu frontend de la section "Propriétaires" (liste + formulaire).
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
	$owner = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$owner = Limpeed_Owners::get( (int) $_GET['id'] );
		if ( ! $owner || ! Limpeed_Branches::can_access_owner( $owner->id ) ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Propriétaire introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit = ! empty( $owner );
	$errors  = Limpeed_Frontend_Owners::$errors;
	$posted  = Limpeed_Frontend_Owners::$posted;

	$field = function ( $name, $default = '' ) use ( $owner, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $owner->$name ) ) {
			return $owner->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier le propriétaire', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un propriétaire', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$buildings_url = Limpeed_Frontend::app_url( 'buildings', array( 'owner_id' => $owner->id ) );
				$add_building  = Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add', 'owner_id' => $owner->id ) );
				?>
				<a href="<?php echo esc_url( $buildings_url ); ?>"><?php esc_html_e( 'Voir les édifices', 'limpeed-immobilier' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $add_building ); ?>"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></a>
				<?php if ( current_user_can( 'manage_limpeed_statements' ) ) : ?>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements', array( 'owner_id' => $owner->id ) ) ); ?>"><?php esc_html_e( 'Voir les bordereaux', 'limpeed-immobilier' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners', $is_edit ? array( 'action' => 'edit', 'id' => $owner->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_owner', 'limpeed_owner_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="owner_id" value="<?php echo esc_attr( $owner->id ); ?>"><?php endif; ?>

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
				<label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label>
				<textarea name="address" id="address" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>
			<div class="limpeed-form-row">
				<label for="bank_details"><?php esc_html_e( 'Coordonnées bancaires (RIB/IBAN ou mobile money)', 'limpeed-immobilier' ); ?></label>
				<textarea name="bank_details" id="bank_details" rows="3"><?php echo esc_textarea( $field( 'bank_details' ) ); ?></textarea>
			</div>
			<?php if ( 0 === Limpeed_Branches::current_user_branch_id() ) : ?>
				<?php $branches = Limpeed_Branches::get_all(); ?>
				<div class="limpeed-form-row">
					<label for="branch_id"><?php esc_html_e( 'Succursale', 'limpeed-immobilier' ); ?></label>
					<select name="branch_id" id="branch_id">
						<option value=""><?php esc_html_e( '— Aucune —', 'limpeed-immobilier' ); ?></option>
						<?php foreach ( $branches as $branch_option ) : ?>
							<option value="<?php echo esc_attr( $branch_option->id ); ?>" <?php selected( (int) $field( 'branch_id' ), $branch_option->id ); ?>>
								<?php echo esc_html( $branch_option->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="limpeed-app-description"><?php esc_html_e( 'Les agents et responsables de la succursale choisie sont les seuls (hors administrateurs) à voir ce propriétaire et tout ce qui en dépend (édifices, biens, locataires, paiements, bordereaux).', 'limpeed-immobilier' ); ?></p>
				</div>
			<?php endif; ?>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php elseif ( 'view' === $action && isset( $_GET['id'] ) ) : ?>
	<?php
	// -----------------------------------------------------------------
	// Fiche propriétaire : vue d'ensemble par édifice/bien/locataire.
	// -----------------------------------------------------------------
	$owner = Limpeed_Owners::get( (int) $_GET['id'] );
	if ( ! $owner || ! Limpeed_Branches::can_access_owner( $owner->id ) ) {
		echo '<div class="limpeed-app-panel">' . esc_html__( 'Propriétaire introuvable.', 'limpeed-immobilier' ) . '</div>';
		return;
	}

	$owner_buildings  = Limpeed_Buildings::get_all( array( 'owner_id' => $owner->id, 'per_page' => 9999 ) );
	$owner_properties = Limpeed_Properties::get_all( array( 'owner_id' => $owner->id, 'per_page' => 9999 ) );

	$properties_by_building = array();
	foreach ( $owner_properties as $owner_property ) {
		$properties_by_building[ (int) $owner_property->building_id ][] = $owner_property;
	}

	$property_types    = Limpeed_Properties::get_types();
	$property_statuses = Limpeed_Properties::get_statuses();
	$tenant_statuses   = Limpeed_Tenants::get_statuses();
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<h2><?php echo esc_html( $owner->full_name ); ?></h2>
			<div>
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add', 'owner_id' => $owner->id ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></a>
			</div>
		</div>
		<p>
			<strong><?php esc_html_e( 'Téléphone :', 'limpeed-immobilier' ); ?></strong> <?php echo $owner->phone ? esc_html( $owner->phone ) : '&mdash;'; ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Email :', 'limpeed-immobilier' ); ?></strong> <?php echo $owner->email ? esc_html( $owner->email ) : '&mdash;'; ?>
		</p>
		<?php if ( $owner->address ) : ?>
			<p><strong><?php esc_html_e( 'Adresse :', 'limpeed-immobilier' ); ?></strong> <?php echo esc_html( $owner->address ); ?></p>
		<?php endif; ?>
		<?php if ( current_user_can( 'manage_limpeed_statements' ) ) : ?>
			<p><a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements', array( 'owner_id' => $owner->id ) ) ); ?>"><?php esc_html_e( 'Voir les bordereaux', 'limpeed-immobilier' ); ?></a></p>
		<?php endif; ?>
	</div>

	<?php if ( empty( $owner_buildings ) ) : ?>
		<div class="limpeed-app-panel">
			<p><?php esc_html_e( 'Ce propriétaire n\'a pas encore d\'édifice.', 'limpeed-immobilier' ); ?></p>
		</div>
	<?php endif; ?>

	<?php foreach ( $owner_buildings as $owner_building ) : ?>
		<?php $building_properties = $properties_by_building[ (int) $owner_building->id ] ?? array(); ?>
		<div class="limpeed-app-panel">
			<div class="limpeed-app-toolbar">
				<h3>
					<?php echo esc_html( $owner_building->name ); ?>
					<?php if ( $owner_building->address ) : ?><small class="limpeed-app-description"><?php echo esc_html( $owner_building->address ); ?></small><?php endif; ?>
				</h3>
				<div>
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $owner_building->id ) ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Modifier l\'édifice', 'limpeed-immobilier' ); ?></a>
					<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'add', 'building_id' => $owner_building->id ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></a>
				</div>
			</div>

			<?php if ( empty( $building_properties ) ) : ?>
				<p><?php esc_html_e( 'Aucun sous-édifice pour cet édifice.', 'limpeed-immobilier' ); ?></p>
			<?php else : ?>
				<div class="limpeed-entity-grid">
					<?php foreach ( $building_properties as $building_property ) : ?>
						<?php $current_tenant = Limpeed_Properties::get_current_tenant( $building_property->id ); ?>
						<div class="limpeed-entity-card limpeed-entity-card-status-<?php echo esc_attr( $building_property->status ); ?>">
							<div class="limpeed-entity-card-header">
								<span class="limpeed-entity-card-avatar limpeed-icon-green"><span class="dashicons dashicons-admin-home"></span></span>
								<div class="limpeed-entity-card-header-text">
									<div class="limpeed-entity-card-title"><?php echo esc_html( Limpeed_Properties::get_display_label( $building_property ) ); ?></div>
									<div class="limpeed-entity-card-subtitle"><?php echo isset( $property_types[ $building_property->type ] ) ? esc_html( $property_types[ $building_property->type ] ) : esc_html( $building_property->type ); ?></div>
								</div>
								<span class="limpeed-app-badge limpeed-app-badge-<?php echo esc_attr( $building_property->status ); ?>"><?php echo isset( $property_statuses[ $building_property->status ] ) ? esc_html( $property_statuses[ $building_property->status ] ) : esc_html( $building_property->status ); ?></span>
							</div>
							<div class="limpeed-entity-card-meta">
								<div class="limpeed-entity-card-meta-row">
									<span class="dashicons dashicons-money-alt"></span>
									<span><?php echo esc_html( Limpeed_Payments::format_amount( $building_property->monthly_rent ) ); ?></span>
								</div>
								<div class="limpeed-entity-card-meta-row">
									<span class="dashicons dashicons-admin-users"></span>
									<?php if ( $current_tenant ) : ?>
										<span>
											<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $current_tenant->id ) ) ); ?>"><?php echo esc_html( $current_tenant->full_name ); ?></a>
											<span class="limpeed-app-badge limpeed-app-badge-<?php echo esc_attr( $current_tenant->status ); ?>"><?php echo isset( $tenant_statuses[ $current_tenant->status ] ) ? esc_html( $tenant_statuses[ $current_tenant->status ] ) : esc_html( $current_tenant->status ); ?></span>
										</span>
									<?php else : ?>
										<span>&mdash;</span>
									<?php endif; ?>
								</div>
							</div>
							<div class="limpeed-entity-card-footer">
								<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $building_property->id ) ) ); ?>" class="limpeed-app-link-btn"><?php esc_html_e( 'Modifier le bien', 'limpeed-immobilier' ); ?></a>
								<?php if ( ! $current_tenant ) : ?>
									<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'add', 'property_id' => $building_property->id ) ) ); ?>" class="limpeed-app-link-btn"><?php esc_html_e( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;

	$args = array(
		'search'   => $search,
		'per_page' => $per_page,
		'paged'    => $paged,
	);

	$total_items = Limpeed_Owners::count( $args );
	$owners      = Limpeed_Owners::get_all( $args );

	// Cartes de synthèse.
	$owners_total    = Limpeed_Owners::count();
	$buildings_total = Limpeed_Buildings::count();
	$properties_total = Limpeed_Properties::count();
	$properties_occupied = Limpeed_Properties::count( array( 'status' => 'loue' ) );
	$properties_occupied_ratio = $properties_total > 0 ? round( ( $properties_occupied / $properties_total ) * 100 ) : 0;
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $owners_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Propriétaires', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-groups"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $buildings_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Édifices gérés', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-admin-multisite"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $properties_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Biens gérés au total', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-building"></span></span>
			</div>
			<div class="limpeed-app-card-ratio"><span style="width: <?php echo esc_attr( $properties_occupied_ratio ); ?>%; background: var(--limpeed-blue);"></span></div>
			<div class="limpeed-app-card-bar limpeed-bar-green">
				<span><?php echo esc_html( $properties_occupied_ratio ); ?>% <?php esc_html_e( 'loués', 'limpeed-immobilier' ); ?></span>
			</div>
		</div>
	</div>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
				<input type="hidden" name="limpeed_view" value="owners">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un propriétaire...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Rechercher', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un propriétaire', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Propriétaire ajouté avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Propriétaire mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Propriétaire supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<div class="limpeed-entity-grid">
			<?php if ( empty( $owners ) ) : ?>
				<p class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun propriétaire pour le moment.', 'limpeed-immobilier' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $owners as $owner_row ) : ?>
				<?php
				$view_url    = Limpeed_Frontend::app_url( 'owners', array( 'action' => 'view', 'id' => $owner_row->id ) );
				$edit_url    = Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner_row->id ) );
				$delete_url  = wp_nonce_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'delete', 'id' => $owner_row->id ) ), 'limpeed_delete_owner_' . $owner_row->id );
				$buildings_n = Limpeed_Buildings::count( array( 'owner_id' => $owner_row->id ) );
				?>
				<div class="limpeed-entity-card limpeed-entity-card--orange" data-href="<?php echo esc_url( $view_url ); ?>" role="link" tabindex="0">
					<div class="limpeed-entity-card-header">
						<span class="limpeed-entity-card-avatar is-solid limpeed-icon-orange"><?php echo esc_html( Limpeed_Frontend::initials( $owner_row->full_name ) ); ?></span>
						<div class="limpeed-entity-card-header-text">
							<div class="limpeed-entity-card-title"><?php echo esc_html( $owner_row->full_name ); ?></div>
							<div class="limpeed-entity-card-subtitle"><?php echo esc_html( number_format_i18n( $buildings_n ) ); ?> <?php echo esc_html( _n( 'édifice', 'édifices', $buildings_n, 'limpeed-immobilier' ) ); ?></div>
						</div>
					</div>
					<div class="limpeed-entity-card-meta">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-phone"></span>
							<span><?php echo $owner_row->phone ? esc_html( $owner_row->phone ) : '—'; ?></span>
						</div>
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-email"></span>
							<span><?php echo $owner_row->email ? esc_html( $owner_row->email ) : '—'; ?></span>
						</div>
						<?php if ( 0 === Limpeed_Branches::current_user_branch_id() && ! empty( $owner_row->branch_id ) ) : ?>
							<?php $owner_row_branch = Limpeed_Branches::get( $owner_row->branch_id ); ?>
							<?php if ( $owner_row_branch ) : ?>
								<div class="limpeed-entity-card-meta-row">
									<span class="dashicons dashicons-location"></span>
									<span><?php echo esc_html( $owner_row_branch->name ); ?></span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<div class="limpeed-entity-card-footer">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="limpeed-app-link-btn" onclick="event.stopPropagation();"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-app-link-btn is-danger limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce propriétaire ?', 'limpeed-immobilier' ); ?>" onclick="event.stopPropagation();"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search ) ); ?>
	</div>
<?php endif; ?>
