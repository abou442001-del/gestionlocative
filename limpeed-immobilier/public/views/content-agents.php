<?php
/**
 * Contenu frontend de la section "Agents" (liste + création + modification de rôle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

if ( 'edit' === $action && isset( $_GET['id'] ) ) :
	// -----------------------------------------------------------------
	// Modification du rôle d'un agent existant.
	// -----------------------------------------------------------------
	$agent = Limpeed_Agents::get( (int) $_GET['id'] );
	if ( ! $agent ) {
		echo '<div class="limpeed-app-panel">' . esc_html__( 'Agent introuvable.', 'limpeed-immobilier' ) . '</div>';
		return;
	}

	$errors = Limpeed_Frontend_Agents::$errors;
	$posted = Limpeed_Frontend_Agents::$posted;

	$field = function ( $name, $default = '' ) use ( $posted ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		return $default;
	};

	$current_roles = array_intersect( $agent->roles, array_keys( Limpeed_Agents::get_available_roles() ) );
	$current_role  = reset( $current_roles );
	?>

	<div class="limpeed-app-panel">
		<h2><?php esc_html_e( 'Modifier le rôle de l\'agent', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents', array( 'action' => 'edit', 'id' => $agent->ID ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_agent', 'limpeed_agent_nonce' ); ?>
			<input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent->ID ); ?>">

			<div class="limpeed-form-row">
				<label><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></label>
				<p><?php echo esc_html( $agent->user_login ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></label>
				<p><?php echo esc_html( $agent->user_email ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="role"><?php esc_html_e( 'Rôle', 'limpeed-immobilier' ); ?></label>
				<select name="role" id="role">
					<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'role', $current_role ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php esc_html_e( 'Mettre à jour le rôle', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php elseif ( 'add' === $action ) : ?>
	<?php
	// -----------------------------------------------------------------
	// Création d'un compte agent.
	// -----------------------------------------------------------------
	$errors = Limpeed_Frontend_Agents::$errors;
	$posted = Limpeed_Frontend_Agents::$posted;

	$field = function ( $name, $default = '' ) use ( $posted ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php esc_html_e( 'Ajouter un agent', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<p class="limpeed-app-description"><?php esc_html_e( 'Un compte WordPress est créé avec le rôle choisi. L\'agent reçoit un email pour définir son propre mot de passe.', 'limpeed-immobilier' ); ?></p>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_save_agent', 'limpeed_agent_nonce' ); ?>

			<div class="limpeed-form-row">
				<label for="user_login"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="text" name="user_login" id="user_login" required value="<?php echo esc_attr( $field( 'user_login' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="display_name"><?php esc_html_e( 'Nom affiché', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="display_name" id="display_name" value="<?php echo esc_attr( $field( 'display_name' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="user_email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="email" name="user_email" id="user_email" required value="<?php echo esc_attr( $field( 'user_email' ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="role"><?php esc_html_e( 'Rôle', 'limpeed-immobilier' ); ?></label>
				<select name="role" id="role">
					<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'role', 'limpeed_agent' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<button type="submit" class="limpeed-app-btn"><?php esc_html_e( 'Créer le compte agent', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste + demandes en attente.
	// -----------------------------------------------------------------
	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;

	$args = array(
		'search'   => $search,
		'per_page' => $per_page,
		'paged'    => $paged,
	);

	$total_items = Limpeed_Agents::count( $args );
	$agents      = Limpeed_Agents::get_all( $args );
	$pending     = Limpeed_Agents::get_pending();

	// Cartes de synthèse.
	$agents_total      = Limpeed_Agents::count();
	$agents_pending    = count( $pending );
	$agents_this_month = Limpeed_Agents::count_created_this_month();
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $agents_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Agents', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-id"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $agents_pending ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Demandes en attente', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-clock"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $agents_this_month ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Comptes créés ce mois-ci', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-green"><span class="dashicons dashicons-admin-users"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-green"></div>
		</div>
	</div>

	<?php
	Limpeed_Frontend::render_notice(
		$message,
		array(
			'created'  => __( 'Compte agent créé avec succès. Un email lui a été envoyé pour définir son mot de passe.', 'limpeed-immobilier' ),
			'updated'  => __( 'Rôle mis à jour avec succès.', 'limpeed-immobilier' ),
			'revoked'  => __( 'Accès révoqué avec succès. Le compte WordPress est conservé.', 'limpeed-immobilier' ),
			'approved' => __( 'Demande approuvée : l\'agent a été notifié par email.', 'limpeed-immobilier' ),
			'rejected' => __( 'Demande rejetée et compte supprimé.', 'limpeed-immobilier' ),
		)
	);
	?>

	<?php if ( ! empty( $pending ) ) : ?>
		<div class="limpeed-app-panel">
			<h2><?php esc_html_e( 'Demandes en attente', 'limpeed-immobilier' ); ?></h2>
			<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Date de la demande', 'limpeed-immobilier' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pending as $candidate ) : ?>
						<?php $reject_url = wp_nonce_url( Limpeed_Frontend::app_url( 'agents', array( 'action' => 'reject', 'id' => $candidate->ID ) ), 'limpeed_reject_agent_' . $candidate->ID ); ?>
						<tr>
							<td><?php echo esc_html( $candidate->user_login ); ?></td>
							<td><?php echo esc_html( $candidate->user_email ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $candidate->user_registered ) ); ?></td>
							<td class="limpeed-app-actions">
								<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents' ) ); ?>" style="display:inline-block;">
									<?php wp_nonce_field( 'limpeed_approve_agent_' . $candidate->ID, 'limpeed_approve_nonce' ); ?>
									<input type="hidden" name="pending_id" value="<?php echo esc_attr( $candidate->ID ); ?>">
									<select name="role">
										<?php foreach ( Limpeed_Agents::get_available_roles() as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'limpeed_agent', $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="limpeed-app-btn"><?php esc_html_e( 'Approuver', 'limpeed-immobilier' ); ?></button>
								</form>
								<a href="<?php echo esc_url( $reject_url ); ?>" class="limpeed-app-btn limpeed-app-btn-danger limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous le rejet de cette demande ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Rejeter', 'limpeed-immobilier' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
</div>
		</div>
	<?php endif; ?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<h2 style="margin:0;"><?php esc_html_e( 'Agents actifs', 'limpeed-immobilier' ); ?></h2>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'agents', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Ajouter un agent', 'limpeed-immobilier' ); ?></a>
		</div>
		<form method="get" class="limpeed-app-search" style="margin-bottom:16px;">
			<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
			<input type="hidden" name="limpeed_view" value="agents">
			<input type="text" name="q" placeholder="<?php esc_attr_e( 'Rechercher un agent...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $search ); ?>">
			<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Rechercher', 'limpeed-immobilier' ); ?></button>
		</form>

		<div class="limpeed-entity-grid">
			<?php if ( empty( $agents ) ) : ?>
				<p class="limpeed-entity-card-empty"><?php esc_html_e( 'Aucun agent pour le moment.', 'limpeed-immobilier' ); ?></p>
			<?php endif; ?>
			<?php
			$roles = Limpeed_Agents::get_available_roles();
			foreach ( $agents as $agent_row ) :
				$edit_url    = Limpeed_Frontend::app_url( 'agents', array( 'action' => 'edit', 'id' => $agent_row->ID ) );
				$revoke_url  = wp_nonce_url( Limpeed_Frontend::app_url( 'agents', array( 'action' => 'revoke', 'id' => $agent_row->ID ) ), 'limpeed_revoke_agent_' . $agent_row->ID );
				$user_roles  = array_intersect( $agent_row->roles, array_keys( $roles ) );
				$role_labels = array_map(
					function ( $role ) use ( $roles ) {
						return $roles[ $role ];
					},
					$user_roles
				);
				$is_self = get_current_user_id() === $agent_row->ID;
				?>
				<div class="limpeed-entity-card limpeed-entity-card--blue" <?php echo $is_self ? '' : 'data-href="' . esc_url( $edit_url ) . '" role="link" tabindex="0"'; ?>>
					<div class="limpeed-entity-card-header">
						<span class="limpeed-entity-card-avatar is-solid limpeed-icon-blue"><?php echo esc_html( Limpeed_Frontend::initials( $agent_row->display_name ) ); ?></span>
						<div class="limpeed-entity-card-header-text">
							<div class="limpeed-entity-card-title"><?php echo esc_html( $agent_row->display_name ); ?></div>
							<div class="limpeed-entity-card-subtitle"><?php echo esc_html( $agent_row->user_login ); ?></div>
						</div>
						<?php if ( $is_self ) : ?>
							<span class="limpeed-app-badge limpeed-app-badge-actif"><?php esc_html_e( 'Vous', 'limpeed-immobilier' ); ?></span>
						<?php else : ?>
							<span class="limpeed-app-badge"><?php echo esc_html( implode( ', ', $role_labels ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="limpeed-entity-card-meta">
						<div class="limpeed-entity-card-meta-row">
							<span class="dashicons dashicons-email"></span>
							<span><?php echo esc_html( $agent_row->user_email ); ?></span>
						</div>
					</div>
					<?php if ( ! $is_self ) : ?>
						<div class="limpeed-entity-card-footer">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="limpeed-app-link-btn" onclick="event.stopPropagation();"><?php esc_html_e( 'Modifier le rôle', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $revoke_url ); ?>" class="limpeed-app-link-btn is-danger limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la révocation de l\'accès de cet agent ?', 'limpeed-immobilier' ); ?>" onclick="event.stopPropagation();"><?php esc_html_e( 'Révoquer l\'accès', 'limpeed-immobilier' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'q' => $search ) ); ?>
	</div>
<?php endif; ?>
