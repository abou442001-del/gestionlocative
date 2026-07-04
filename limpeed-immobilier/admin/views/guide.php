<?php
/**
 * Vue : Guide de formation (wp-admin).
 * Le contenu est partagé avec la section frontend équivalente, voir
 * public/views/partials/guide-content.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap limpeed-wrap">
	<h1><?php esc_html_e( 'Guide de formation — Limpeed Immobilier', 'limpeed-immobilier' ); ?></h1>
	<?php include LIMPEED_PLUGIN_DIR . 'public/views/partials/guide-content.php'; ?>
</div>
