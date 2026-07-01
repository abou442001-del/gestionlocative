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
		if ( ! $owner ) {
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

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'owners' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

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
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
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

		<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nom complet', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Édifices', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $owners ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Aucun propriétaire pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $owners as $owner_row ) : ?>
					<?php
					$edit_url    = Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner_row->id ) );
					$delete_url  = wp_nonce_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'delete', 'id' => $owner_row->id ) ), 'limpeed_delete_owner_' . $owner_row->id );
					$buildings_n = Limpeed_Buildings::count( array( 'owner_id' => $owner_row->id ) );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $owner_row->full_name ); ?></a></td>
						<td><?php echo $owner_row->phone ? esc_html( $owner_row->phone ) : '&mdash;'; ?></td>
						<td><?php echo $owner_row->email ? esc_html( $owner_row->email ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $buildings_n ); ?></td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce propriétaire ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search ) ); ?>
	</div>
<?php endif; ?>
