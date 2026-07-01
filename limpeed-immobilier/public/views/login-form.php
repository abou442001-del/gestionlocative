<?php
/**
 * Vue frontend : formulaire de connexion agent.
 *
 * @var array  $errors
 * @var string $message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_url     = get_permalink() ? get_permalink() : home_url( '/' );
$redirect_to  = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$register_id  = (int) get_option( 'limpeed_register_page_id' );
$register_url = $register_id ? get_permalink( $register_id ) : '';
?>
<div class="limpeed-auth-wrap">
	<h2><?php esc_html_e( 'Connexion Agent', 'limpeed-immobilier' ); ?></h2>

	<?php if ( 'pending' === $message ) : ?>
		<div class="limpeed-notice limpeed-notice-info">
			<p><?php esc_html_e( 'Votre compte a bien été créé mais il est en attente d\'approbation par un administrateur. Vous recevrez un email dès que votre accès sera validé.', 'limpeed-immobilier' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="limpeed-notice limpeed-notice-error">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="limpeed-auth-form">
		<?php wp_nonce_field( 'limpeed_login', 'limpeed_login_nonce' ); ?>
		<input type="hidden" name="limpeed_redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

		<p>
			<label for="limpeed_user_login"><?php esc_html_e( 'Identifiant ou email', 'limpeed-immobilier' ); ?></label>
			<input type="text" name="user_login" id="limpeed_user_login" required autocomplete="username">
		</p>
		<p>
			<label for="limpeed_user_password"><?php esc_html_e( 'Mot de passe', 'limpeed-immobilier' ); ?></label>
			<input type="password" name="user_password" id="limpeed_user_password" required autocomplete="current-password">
		</p>
		<p class="limpeed-auth-remember">
			<label>
				<input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Se souvenir de moi', 'limpeed-immobilier' ); ?>
			</label>
		</p>
		<p>
			<button type="submit" class="limpeed-auth-submit"><?php esc_html_e( 'Se connecter', 'limpeed-immobilier' ); ?></button>
		</p>
	</form>

	<p class="limpeed-auth-links">
		<a href="<?php echo esc_url( wp_lostpassword_url( $form_url ) ); ?>"><?php esc_html_e( 'Mot de passe oublié ?', 'limpeed-immobilier' ); ?></a>
		<?php if ( $register_url ) : ?>
			&nbsp;|&nbsp;
			<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Créer un compte agent', 'limpeed-immobilier' ); ?></a>
		<?php endif; ?>
	</p>
</div>
