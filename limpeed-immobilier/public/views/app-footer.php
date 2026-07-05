<?php
/**
 * Fermeture du shell "application" frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			</main>
		</div>
	</div>
	<?php /* limpeed-rest-client.js est chargé globalement (pas seulement sur les pages Alpine) car la recherche globale de la barre du haut en a besoin sur toutes les pages, y compris celles encore rendues en PHP classique. */ ?>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/js/limpeed-rest-client.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( LIMPEED_PLUGIN_URL . 'public/assets/app.js' ); ?>?v=<?php echo esc_attr( LIMPEED_VERSION ); ?>"></script>
</body>
</html>
