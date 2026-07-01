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
							? sprintf( '%s — %s', $tenant_option->full_name, $property_option->address )
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
				<input type="month" name="period" id="period" required value="<?php echo esc_attr( $field( 'period', Limpeed_Payments::get_current_period() ) ); ?>">
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
				<label for="commission_amount"><?php esc_html_e( 'Commission agence prélevée', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="0.01" min="0" name="commission_amount" id="commission_amount" value="<?php echo esc_attr( $field( 'commission_amount', 0 ) ); ?>">
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
	// Liste.
	// -----------------------------------------------------------------
	$tenant_id   = isset( $_GET['tenant_id'] ) ? (int) $_GET['tenant_id'] : 0;
	$property_id = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : 0;
	$period      = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '';
	$status      = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page    = 20;

	$args = array(
		'tenant_id'   => $tenant_id,
		'property_id' => $property_id,
		'period'      => $period,
		'status'      => $status,
		'per_page'    => $per_page,
		'paged'       => $paged,
	);

	$total_items       = Limpeed_Payments::count( $args );
	$payments          = Limpeed_Payments::get_all( $args );
	$filter_properties = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );
	?>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="limpeed_view" value="payments">
				<?php if ( $tenant_id ) : ?><input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant_id ); ?>"><?php endif; ?>
				<select name="property_id">
					<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $filter_properties as $property_option ) : ?>
						<option value="<?php echo esc_attr( $property_option->id ); ?>" <?php selected( $property_id, $property_option->id ); ?>>
							<?php echo esc_html( $property_option->address ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( Limpeed_Payments::get_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="month" name="period" value="<?php echo esc_attr( $period ); ?>">
				<button type="submit" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Filtrer', 'limpeed-immobilier' ); ?></button>
			</form>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'payments', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Enregistrer un paiement', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Paiement enregistré avec succès.', 'limpeed-immobilier' ),
				'updated' => __( 'Paiement mis à jour avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Paiement supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mois concerné', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Bien', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Montant', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Date de paiement', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Mode de paiement', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $payments ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Aucun paiement pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php
				$statuses = Limpeed_Payments::get_statuses();
				$methods  = Limpeed_Payments::get_payment_methods();
				foreach ( $payments as $payment_row ) :
					$edit_url   = Limpeed_Frontend::app_url( 'payments', array( 'action' => 'edit', 'id' => $payment_row->id ) );
					$delete_url = wp_nonce_url( Limpeed_Frontend::app_url( 'payments', array( 'action' => 'delete', 'id' => $payment_row->id ) ), 'limpeed_delete_payment_' . $payment_row->id );
					$tenant     = Limpeed_Tenants::get( $payment_row->tenant_id );
					$property   = Limpeed_Properties::get( $payment_row->property_id );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $payment_row->period ); ?></a></td>
						<td><?php echo $tenant ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant->id ) ) ) . '">' . esc_html( $tenant->full_name ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo $property ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'properties', array( 'action' => 'edit', 'id' => $property->id ) ) ) . '">' . esc_html( $property->address ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $payment_row->amount, 2 ) ); ?></td>
						<td><?php echo $payment_row->payment_date ? esc_html( mysql2date( get_option( 'date_format' ), $payment_row->payment_date ) ) : '&mdash;'; ?></td>
						<td><?php echo isset( $methods[ $payment_row->payment_method ] ) ? esc_html( $methods[ $payment_row->payment_method ] ) : esc_html( $payment_row->payment_method ); ?></td>
						<td><span class="limpeed-app-badge"><?php echo isset( $statuses[ $payment_row->status ] ) ? esc_html( $statuses[ $payment_row->status ] ) : esc_html( $payment_row->status ); ?></span></td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce paiement ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'tenant_id' => $tenant_id, 'property_id' => $property_id, 'period' => $period, 'status' => $status ) ); ?>
	</div>
<?php endif; ?>
