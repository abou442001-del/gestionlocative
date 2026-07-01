<?php
/**
 * Vue : formulaire de génération d'un bordereau.
 *
 * @var array      $errors
 * @var array|null $posted
 * @var array      $owners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field = function ( $name, $default = '' ) use ( $posted ) {
	if ( null !== $posted && isset( $posted[ $name ] ) ) {
		return $posted[ $name ];
	}
	return $default;
};

$list_url    = add_query_arg( array( 'page' => 'limpeed-statements' ), admin_url( 'admin.php' ) );
$form_action = add_query_arg( array( 'page' => 'limpeed-statements', 'action' => 'add' ), admin_url( 'admin.php' ) );
$current_month = current_time( 'Y-m' );
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Générer un bordereau', 'limpeed-immobilier' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<p class="description"><?php esc_html_e( 'Le bordereau calcule automatiquement, pour la période choisie : loyers encaissés − commission agence = net à reverser, détaillé bien par bien.', 'limpeed-immobilier' ); ?></p>

	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'limpeed_generate_statement', 'limpeed_statement_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td>
						<select name="owner_id" id="owner_id" required>
							<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
							<?php foreach ( $owners as $owner_option ) : ?>
								<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id' ), $owner_option->id ); ?>>
									<?php echo esc_html( $owner_option->full_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="period_start"><?php esc_html_e( 'Du mois', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="period_start" type="month" id="period_start" required value="<?php echo esc_attr( $field( 'period_start', $current_month ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="period_end"><?php esc_html_e( 'Au mois', 'limpeed-immobilier' ); ?> <span class="required">*</span></label></th>
					<td><input name="period_end" type="month" id="period_end" required value="<?php echo esc_attr( $field( 'period_end', $current_month ) ); ?>"></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Générer le bordereau PDF', 'limpeed-immobilier' ) ); ?>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
	</form>
</div>
