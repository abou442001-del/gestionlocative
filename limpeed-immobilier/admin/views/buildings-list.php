<?php
/**
 * Vue : liste des édifices.
 *
 * @var Limpeed_Buildings_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$add_url = add_query_arg(
	array(
		'page'   => 'limpeed-buildings',
		'action' => 'add',
	),
	admin_url( 'admin.php' )
);

$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>
<div class="wrap limpeed-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Édifices', 'limpeed-immobilier' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Ajouter un édifice', 'limpeed-immobilier' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( 'created' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Édifice ajouté avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Édifice mis à jour avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Édifice supprimé avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'error' === $message ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( isset( $_GET['error_text'] ) ? sanitize_text_field( wp_unslash( $_GET['error_text'] ) ) : __( 'Une erreur est survenue.', 'limpeed-immobilier' ) ); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="limpeed-buildings">
		<?php $list_table->search_box( __( 'Rechercher un édifice', 'limpeed-immobilier' ), 'limpeed-building-search' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
