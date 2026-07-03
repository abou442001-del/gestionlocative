<?php
/**
 * Contenu frontend de la section "Bordereaux" (liste + génération).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

if ( 'add' === $action ) :
	// -----------------------------------------------------------------
	// Formulaire de génération.
	// -----------------------------------------------------------------
	$errors        = Limpeed_Frontend_Statements::$errors;
	$posted        = Limpeed_Frontend_Statements::$posted;
	$owners        = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
	$current_month = current_time( 'Y-m' );

	$field = function ( $name, $default = '' ) use ( $posted ) {
		if ( null !== $posted && isset( $posted[ $name ] ) ) {
			return $posted[ $name ];
		}
		return $default;
	};
	?>

	<div class="limpeed-app-panel">
		<h2><?php esc_html_e( 'Générer un bordereau', 'limpeed-immobilier' ); ?></h2>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-app-notice limpeed-app-notice-error">
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<p class="limpeed-app-description"><?php esc_html_e( 'Le bordereau calcule automatiquement, pour la période choisie : loyers encaissés − commission agence = net à reverser, détaillé bien par bien.', 'limpeed-immobilier' ); ?></p>

		<form method="post" action="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-form">
			<?php wp_nonce_field( 'limpeed_generate_statement', 'limpeed_statement_nonce' ); ?>

			<div class="limpeed-form-row">
				<label for="owner_id"><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<select name="owner_id" id="owner_id" required>
					<option value=""><?php esc_html_e( '— Choisir un propriétaire —', 'limpeed-immobilier' ); ?></option>
					<?php foreach ( $owners as $owner_option ) : ?>
						<option value="<?php echo esc_attr( $owner_option->id ); ?>" <?php selected( (int) $field( 'owner_id' ), $owner_option->id ); ?>>
							<?php echo esc_html( $owner_option->full_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="limpeed-form-row">
				<label for="period_start"><?php esc_html_e( 'Du mois', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="month" name="period_start" id="period_start" required value="<?php echo esc_attr( $field( 'period_start', $current_month ) ); ?>">
			</div>
			<div class="limpeed-form-row">
				<label for="period_end"><?php esc_html_e( 'Au mois', 'limpeed-immobilier' ); ?> <span class="limpeed-app-required">*</span></label>
				<input type="month" name="period_end" id="period_end" required value="<?php echo esc_attr( $field( 'period_end', $current_month ) ); ?>">
			</div>

			<div class="limpeed-form-row">
				<label for="other_deduction_label"><?php esc_html_e( 'Déduction supplémentaire (optionnel)', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="other_deduction_label" id="other_deduction_label" placeholder="<?php esc_attr_e( 'Ex : Redevance CIE, réparation avancée...', 'limpeed-immobilier' ); ?>" value="<?php echo esc_attr( $field( 'other_deduction_label' ) ); ?>">
				<p class="limpeed-app-form-hint"><?php esc_html_e( 'Une charge ponctuelle à retenir sur ce reversement précis (électricité avancée, réparation...), en plus des honoraires d\'agence. Pour les charges récurrentes de l\'agence, utilisez plutôt la section Comptabilité.', 'limpeed-immobilier' ); ?></p>
			</div>
			<div class="limpeed-form-row">
				<label for="other_deduction_amount"><?php esc_html_e( 'Montant de la déduction (FCFA)', 'limpeed-immobilier' ); ?></label>
				<input type="number" step="1" min="0" name="other_deduction_amount" id="other_deduction_amount" value="<?php echo esc_attr( $field( 'other_deduction_amount' ) ); ?>">
			</div>

			<button type="submit" class="limpeed-app-btn"><?php esc_html_e( 'Générer le bordereau PDF', 'limpeed-immobilier' ); ?></button>
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements' ) ); ?>" class="limpeed-app-btn limpeed-app-btn-secondary"><?php esc_html_e( 'Annuler', 'limpeed-immobilier' ); ?></a>
		</form>
	</div>

<?php else : ?>
	<?php
	// -----------------------------------------------------------------
	// Liste.
	// -----------------------------------------------------------------
	$owner_id = isset( $_GET['owner_id'] ) ? (int) $_GET['owner_id'] : 0;
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;

	$args = array(
		'owner_id' => $owner_id,
		'per_page' => $per_page,
		'paged'    => $paged,
	);

	$total_items   = Limpeed_Statements::count( $args );
	$statements    = Limpeed_Statements::get_all( $args );
	$filter_owners = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );

	// Cartes de synthèse.
	$statements_total   = Limpeed_Statements::count();
	$total_reversed     = Limpeed_Treasury::get_total_reversed();
	$total_commission   = Limpeed_Treasury::get_total_commission();
	?>

	<div class="limpeed-cards-row">
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( number_format_i18n( $statements_total ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Bordereaux générés', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-blue"><span class="dashicons dashicons-media-document"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-blue"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $total_reversed ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Total reversé aux propriétaires', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-red"><span class="dashicons dashicons-upload"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-red"></div>
		</div>
		<div class="limpeed-app-card">
			<div class="limpeed-app-card-top">
				<div>
					<div class="limpeed-app-card-number"><?php echo esc_html( Limpeed_Payments::format_amount( $total_commission ) ); ?></div>
					<div class="limpeed-app-card-label"><?php esc_html_e( 'Total commissions agence', 'limpeed-immobilier' ); ?></div>
				</div>
				<span class="limpeed-app-card-icon limpeed-icon-orange"><span class="dashicons dashicons-portfolio"></span></span>
			</div>
			<div class="limpeed-app-card-bar limpeed-bar-orange"></div>
		</div>
	</div>

	<div class="limpeed-app-panel">
		<div class="limpeed-app-toolbar">
			<form method="get" class="limpeed-app-search">
				<input type="hidden" name="page_id" value="<?php echo (int) Limpeed_Frontend::dashboard_page_id(); ?>">
				<input type="hidden" name="limpeed_view" value="statements">
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
			<a href="<?php echo esc_url( Limpeed_Frontend::app_url( 'statements', array( 'action' => 'add' ) ) ); ?>" class="limpeed-app-btn"><?php esc_html_e( 'Générer un bordereau', 'limpeed-immobilier' ); ?></a>
		</div>

		<?php
		Limpeed_Frontend::render_notice(
			$message,
			array(
				'created' => __( 'Bordereau généré avec succès.', 'limpeed-immobilier' ),
				'deleted' => __( 'Bordereau supprimé avec succès.', 'limpeed-immobilier' ),
			)
		);
		?>

		<div class="limpeed-app-table-wrap">
<table class="limpeed-app-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Période', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Propriétaire', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Loyers encaissés', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Commission', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Net reversé', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Généré le', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Généré par', 'limpeed-immobilier' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'limpeed-immobilier' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $statements ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Aucun bordereau pour le moment.', 'limpeed-immobilier' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $statements as $statement_row ) : ?>
					<?php
					$download_url = wp_nonce_url( Limpeed_Frontend::app_url( 'statements', array( 'action' => 'download', 'id' => $statement_row->id ) ), 'limpeed_download_statement_' . $statement_row->id );
					$delete_url   = wp_nonce_url( Limpeed_Frontend::app_url( 'statements', array( 'action' => 'delete', 'id' => $statement_row->id ) ), 'limpeed_delete_statement_' . $statement_row->id );
					$owner        = Limpeed_Owners::get( $statement_row->owner_id );
					$user         = $statement_row->generated_by ? get_userdata( $statement_row->generated_by ) : false;
					$period_label = ( $statement_row->period_start === $statement_row->period_end ) ? $statement_row->period_start : sprintf( '%s — %s', $statement_row->period_start, $statement_row->period_end );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( $period_label ); ?></a></td>
						<td><?php echo $owner ? '<a href="' . esc_url( Limpeed_Frontend::app_url( 'owners', array( 'action' => 'edit', 'id' => $owner->id ) ) ) . '">' . esc_html( $owner->full_name ) . '</a>' : '&mdash;'; ?></td>
						<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement_row->total_collected ) ); ?></td>
						<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement_row->total_commission ) ); ?></td>
						<td><?php echo esc_html( Limpeed_Payments::format_amount( $statement_row->net_amount ) ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $statement_row->created_at ) ); ?></td>
						<td><?php echo $user ? esc_html( $user->display_name ) : '&mdash;'; ?></td>
						<td class="limpeed-app-actions">
							<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Télécharger', 'limpeed-immobilier' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="limpeed-confirm-delete" data-confirm="<?php esc_attr_e( 'Confirmez-vous la suppression de ce bordereau ?', 'limpeed-immobilier' ); ?>"><?php esc_html_e( 'Supprimer', 'limpeed-immobilier' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
</div>

		<?php Limpeed_Frontend::render_pagination( $total_items, $per_page, $paged, array( 'owner_id' => $owner_id ) ); ?>
	</div>
<?php endif; ?>
