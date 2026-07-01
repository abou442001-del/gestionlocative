<?php
/**
 * Vue : liste des bordereaux générés.
 *
 * @var Limpeed_Statements_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$add_url = add_query_arg(
	array(
		'page'   => 'limpeed-statements',
		'action' => 'add',
	),
	admin_url( 'admin.php' )
);

$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>
<div class="wrap limpeed-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Bordereaux', 'limpeed-immobilier' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Générer un bordereau', 'limpeed-immobilier' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( 'created' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bordereau généré avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bordereau supprimé avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="limpeed-statements">
		<?php $list_table->display(); ?>
	</form>
</div>
