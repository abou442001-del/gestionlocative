<?php
/**
 * Vue : formulaire d'ajout / modification d'un paiement.
 *
 * @var object|null $payment
 * @var array       $errors
 * @var array|null  $posted
 * @var array       $tenants
 * @var int         $preselected_tenant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $payment );
$preselected_tenant = $preselected_tenant ?? 0;
$preselected_period = $preselected_period ?? '';

$field = function ( $name, $default = '' ) use ( $payment, $posted, $is_edit ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	if ( $is_edit && isset( $payment->$name ) ) {
		return $payment->$name;
	}
	return $default;
};

$list_url = add_query_arg( array( 'page' => 'limpeed-payments' ), admin_url( 'admin.php' ) );

$form_action = add_query_arg(
	array_filter(
		array(
			'page'   => 'limpeed-payments',
			'action' => $is_edit ? 'edit' : 'add',
			'id'     => $is_edit ? $payment->id : null,
		)
	),
	admin_url( 'admin.php' )
);
?>
<div class="wrap limpeed-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Modifier le paiement', 'limpeed-immobilier' ) : esc_html__( 'Enregistrer un paiement', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_save_payment', 'limpeed_payment_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment->id ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="tenant_id"><?php esc_html_e( 'Locataire', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
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
						<p class="description"><?php esc_html_e( 'Le bien associé est déterminé automatiquement à partir du locataire sélectionné.', 'limpeed-immobilier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="period"><?php esc_html_e( 'Mois concerné', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="period" type="month" id="period" required value="<?php echo esc_attr( $field( 'period', $preselected_period ? $preselected_period : Limpeed_Payments::get_current_period() ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="amount"><?php esc_html_e( 'Montant payé', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="amount" type="number" step="0.01" min="0" id="amount" class="regular-text" required value="<?php echo esc_attr( $field( 'amount', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="payment_date"><?php esc_html_e( 'Date de paiement', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="payment_date" type="date" id="payment_date" value="<?php echo esc_attr( $field( 'payment_date' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="payment_method"><?php esc_html_e( 'Mode de paiement', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="payment_method" id="payment_method">
							<?php foreach ( Limpeed_Payments::get_payment_methods() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'payment_method', 'especes' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="commission_amount"><?php esc_html_e( 'Commission agence prélevée', 'limpeed-immobilier' ); ?></label></th>
					<td><input name="commission_amount" type="number" step="0.01" min="0" id="commission_amount" class="regular-text" value="<?php echo esc_attr( $field( 'commission_amount', 0 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="status"><?php esc_html_e( 'Statut', 'limpeed-immobilier' ); ?></label></th>
					<td>
						<select name="status" id="status">
							<?php foreach ( Limpeed_Payments::get_statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status', 'paye' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_edit ? __( 'Mettre à jour', 'limpeed-immobilier' ) : __( 'Enregistrer', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
