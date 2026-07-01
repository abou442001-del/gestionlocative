<?php
/**
 * Vue frontend : message affiché quand un utilisateur déjà connecté visite
 * la page de connexion ou d'inscription.
 *
 * @var WP_User $user
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_agent = (bool) array_intersect( $user->roles, array_keys( Limpeed_Agents::get_available_roles() ) );
$pending  = get_user_meta( $user->ID, Limpeed_Agents::PENDING_META_KEY, true );
?>
<div class="limpeed-auth-wrap">
	<div class="limpeed-auth-brand"><?php echo Limpeed_Frontend::render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balisage statique généré et échappé dans render_logo(). ?></div>
	<?php if ( $pending ) : ?>
		<div class="limpeed-notice limpeed-notice-info">
			<p>
				<?php
				printf(
					/* translators: %s: nom d'utilisateur */
					esc_html__( 'Bonjour %s, votre compte est en attente d\'approbation par un administrateur.', 'limpeed-immobilier' ),
					esc_html( $user->display_name )
				);
				?>
			</p>
		</div>
	<?php elseif ( $is_agent ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: nom d'utilisateur */
				esc_html__( 'Vous êtes déjà connecté(e) en tant que %s.', 'limpeed-immobilier' ),
				esc_html( $user->display_name )
			);
			?>
		</p>
		<p><a class="limpeed-auth-submit" href="<?php echo esc_url( Limpeed_Frontend::app_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Accéder au tableau de bord', 'limpeed-immobilier' ); ?></a></p>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: %s: nom d'utilisateur */
				esc_html__( 'Vous êtes déjà connecté(e) en tant que %s.', 'limpeed-immobilier' ),
				esc_html( $user->display_name )
			);
			?>
		</p>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Se déconnecter', 'limpeed-immobilier' ); ?></a></p>
</div>
