<?php
/**
 * Contenu frontend de la section "Édifices" (liste + formulaire).
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
	$building = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$building = Limpeed_Buildings::get( (int) $_GET['id'] );
		if ( ! $building ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Édifice introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit           = ! empty( $building );
	$errors            = Limpeed_Frontend_Buildings::$errors;
	$posted            = Limpeed_Frontend_Buildings::$posted;
	$owners            = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$preselected_owner = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;

	$field = function ( $name, $default = '' ) use ( $building, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $building->$name ) ) {
			return $building->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier l\'édifice', 'limpeed-immobilier' ) : esc_html__( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<?php if ( $is_edit ) : ?>
			<div class="limpeed-app-cross-nav">
				<?php
				$owner = Limpeed_Owners::get( $building->owner_id );
				if ( $owner ) {
					printf(
						/* translators: %s: lien vers le propriétaire */
						esc_html__( 'Propriétaire : %s', 'limpeed-immobilier' ),
						'<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>'
					);
				}
				?>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'building_id' => $building->id ) ) ); ?>"><?php esc_html_e( 'Voir les sous-édifices', 'limpeed-immobilier' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'add', 'building_id' => $building->id ) ) ); ?>"><?php esc_html_e( 'Ajouter un sous-édifice', 'limpeed-immobilier' ); ?></a>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', $is_edit ? array( 'action' => 'edit', 'id' => $building->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_building', 'limpeed_building_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="building_id" value="<?php echo esc_attr( $building->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="owner_id" id="owner_id" required>
					<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id', $preselected_owner ), $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="building_name"><?php esc_html_e( 'Nom de l\'édifice', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="building_name" id="building_name" required value="<?php echo esc_attr( $field( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex : Résidence Les Palmiers', 'limpeed-immobilier' ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label>
				<textarea name="address" id="address" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>
			<div class="limpeed-form-row">
				<label for="description"><?php esc_html_e( 'Description', 'limpeed-immobilier' ); ?></label>
				<textarea name="description" id="description" rows="3"><?php echo esc_textarea( $field( 'description' ) ); ?></textarea>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$owner_id = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;

	$args = array(
		'search'   => $search,
		'owner_id' => $owner_id,
		'per_page' => $per_page,
		'paged'    => $paged,
	);

	$total_items    = Limpeed_Buildings::count( $args );
	$buildings      = Limpeed_Buildings::get_all( $args );
	$filter_owners  = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="limpeed_view" value="buildings">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un édifice...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<select name="owner_id">
					<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( $owner_id, $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Édifice ajouté avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Édifice mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Édifice supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Édifice', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Sous-édifices', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $buildings ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Aucun édifice pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $buildings as $building_row ) : ?>
					<?php
					$edit_url       = Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'edit', 'id' => $building_row->id ) );
					$delete_url     = wp_nonce_url( Limpeed_Frontend::app_url( 'buildings', array( 'action' => 'delete', 'id' => $building_row->id ) ), 'limpeed_delete_building_' . $building_row->id );
					$owner          = Limpeed_Owners::get( $building_row->owner_id );
					$properties_n   = Limpeed_Properties::count( array( 'building_id' => $building_row->id ) );
					$properties_url = Limpeed_Frontend::app_url( 'properties', array( 'building_id' => $building_row->id ) );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $building_row->name ); ?></a></td>
						<td>
							<?php if ( $owner ) : ?>
								<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ); ?>"><?php echo esc_html( $owner->full_name ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo $building_row->address ? esc_html( $building_row->address ) : '&mdash;'; ?></td>
						<td>
							<?php if ( $properties_n > 0 ) : ?>
								<a href="<?php echo esc_url( $properties_url ); ?>"><?php echo esc_html( $properties_n ); ?></a>
							<?php else : ?>
								0
							<?php endif; ?>
						</td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de cet édifice ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search, 'owner_id' => $owner_id ) ); ?>
	</div>
<?php endif; ?>
