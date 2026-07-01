<?php
/**
 * Vue : liste des locataires.
 *
 * @var Limpeed_Tenants_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$add_url = add_query_arg(
	array(
		'page'   => 'limpeed-tenants',
		'action' => 'add',
	),
	admin_url( 'admin.php' )
);

$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>
<div class="wrap limpeed-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Locataires', 'limpeed-immobilier' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Ajouter un locataire', 'limpeed-immobilier' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( 'created' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Locataire ajouté avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Locataire mis à jour avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Locataire supprimé avec succès.', 'limpeed-immobilier' ); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="limpeed-tenants">
		<?php $list_table->search_box( __( 'Rechercher un locataire', 'limpeed-immobilier' ), 'limpeed-tenant-search' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
