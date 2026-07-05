<?php
/**
 * Contenu frontend de la section "Succursales" (liste + formulaire).
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
	$branch = null;
	if ( 'edit' === $action && isset( $_GET['id'] ) ) {
		$branch = Limpeed_Branches::get( (int) $_GET['id'] );
		if ( ! $branch ) {
			echo '<div class="limpeed-app-panel">' . esc_html__( 'Succursale introuvable.', 'limpeed-immobilier' ) . '</div>';
			return;
		}
	}

	$is_edit = ! empty( $branch );
	$errors  = Limpeed_Frontend_Branches::$errors;
	$posted  = Limpeed_Frontend_Branches::$posted;

	$field = function ( $name, $default = '' ) use ( $branch, $posted, $is_edit ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		if ( $is_edit && isset( $branch->$name ) ) {
			return $branch->$name;
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php echo $is_edit ? esc_html__( 'Modifier la succursale', 'limpeed-immobilier' ) : esc_html__( 'Ajouter une succursale', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'branches', $is_edit ? array( 'action' => 'edit', 'id' => $branch->id ) : array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_branch', 'limpeed_branch_nonce' ); ?>
			<?php if ( $is_edit ) : ?><input type="hidden" name="branch_id" value="<?php echo esc_attr( $branch->id ); ?>"><?php endif; ?>

			<div class="limpeed-form-row">
				<label for="branch_name"><?php esc_html_e( 'Nom de la succursale', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="branch_name" id="branch_name" required value="<?php echo esc_attr( $field( 'name' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="phone"><?php esc_html_e( 'Téléphone', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="phone" id="phone" value="<?php echo esc_attr( $field( 'phone' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="address"><?php esc_html_e( 'Adresse', 'limpeed-immobilier' ); ?></label>
				<textarea name="address" id="address" rows="3"><?php echo esc_textarea( $field( 'address' ) ); ?></textarea>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php echo $is_edit ? esc_html__( 'Mettre à jour', 'limpeed-immobilier' ) : esc_html__( 'Ajouter', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'branches' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
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

	$total_items = Limpeed_Branches::count( $args );
	$branches    = Limpeed_Branches::get_all( $args );

	$branches_total = Limpeed_Branches::count();
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $branches_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Succursales', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-location"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
	</div>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
				<input type="hidden" name="limpeed_view" value="branches">
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher une succursale...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Rechercher', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'branches', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter une succursale', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Succursale ajoutée avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Succursale mise à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Succursale supprimée avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<p class="limpeed-app-description"><?php esc_html_e( 'Chaque succursale regroupe des propriétaires, et hérite de cette affectation pour les édifices, biens, locataires, paiements et bordereaux qui en dépendent. Un agent ou un responsable de succursale ne voit que les données de sa succursale ; l\'administrateur voit toutes les succursales sans restriction.', 'limpeed-immobilier' ); ?></p>

		<div class="limpeed-entity-grid">
			<?php if ( empty( $branches ) ) : ?>
				<p class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucune succursale pour le moment.', 'limpeed-immobilier' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $branches as $branch_row ) : ?>
				<?php
				$edit_url    = Limpeed_Frontend::app_url( 'branches', array( 'action' => 'edit', 'id' => $branch_row->id ) );
				$delete_url  = wp_nonce_url( Limpeed_Frontend::app_url( 'branches', array( 'action' => 'delete', 'id' => $branch_row->id ) ), 'limpeed_delete_branch_' . $branch_row->id );
				$owners_n    = Limpeed_Branches::count_owners_in_branch( $branch_row->id );
				$agents_n    = Limpeed_Branches::count_agents_in_branch( $branch_row->id );
				?>
				<div class="limpeed-entity-card limpeed-entity-card--blue">
					<div class="limpeed-entity-card-header">
						<span class="limpeed-entity-card-avatar is-solid limpeed-icon-blue"><span class="dashicons dashicons-location"></span></span>
						<div class="limpeed-entity-card-header-text">
							<div class="limpeed-entity-card-title"><?php echo esc_html( $branch_row->name ); ?></div>
							<div class="limpeed-entity-card-subtitle"><?php echo esc_html( number_format_i18n( $owners_n ) ); ?> <?php echo esc_html( _n( 'propriétaire', 'propriétaires', $owners_n, 'limpeed-immobilier' ) ); ?> &middot; <?php echo esc_html( number_format_i18n( $agents_n ) ); ?> <?php echo esc_html( _n( 'agent', 'agents', $agents_n, 'limpeed-immobilier' ) ); ?></div>
						</div>
					</div>
					<div class="limpeed-entity-card-meta">
						<?php if ( $branch_row->phone ) : ?>
							<div class="limpeed-entity-card-meta-row">
								<span class="dashicons dashicons-phone"></span>
								<span><?php echo esc_html( $branch_row->phone ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( $branch_row->address ) : ?>
							<div class="limpeed-entity-card-meta-row">
								<span class="dashicons dashicons-admin-home"></span>
								<span><?php echo esc_html( $branch_row->address ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="limpeed-entity-card-footer">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="limpeed-app-link-btn"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-app-link-btn is-danger limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de cette succursale ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search ) ); ?>
	</div>
<?php endif; ?>
