<?php
/**
 * Vue frontend : formulaire d'inscription agent.
 * La création d'un compte ici ne donne aucun accès immédiat : une validation
 * par un administrateur est nécessaire (voir Agents > Demandes en attente).
 *
 * @var array      $errors
 * @var array|null $posted
 * @var string     $message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field = function ( $name, $default = '' ) use ( $posted ) {
	return ( null !== $posted && isset( $posted[ $name ] ) ) ? $posted[ $name ] : $default;
};

$form_url    = get_permalink() ? get_permalink() : home_url( '/' );
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
$login_id    = (int) get_option( 'limpeed_login_page_id' );
$login_url   = $login_id ? get_permalink( $login_id ) : wp_login_url();
?>
<div class="limpeed-auth-wrap">
	<h2><?php esc_html_e( 'Inscription Agent', 'limpeed-immobilier' ); ?></h2>

	<?php if ( 'registered' === $message ) : ?>
		<div class="limpeed-notice limpeed-notice-success">
			<p><?php esc_html_e( 'Votre demande a bien été envoyée. Un administrateur doit valider votre compte avant que vous puissiez vous connecter ; vous recevrez un email de confirmation.', 'limpeed-immobilier' ); ?></p>
		</div>
	<?php else : ?>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="limpeed-notice limpeed-notice-error">
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<p class="description"><?php esc_html_e( 'Votre compte sera examiné par un administrateur avant de pouvoir accéder à Limpeed Immobilier.', 'limpeed-immobilier' ); ?></p>

		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="limpeed-auth-form">
			<?php wp_nonce_field( 'limpeed_register', 'limpeed_register_nonce' ); ?>
			<input type="hidden" name="limpeed_redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

			<div class="limpeed-honeypot" aria-hidden="true">
				<label for="limpeed_website">Site web</label>
				<input type="text" name="limpeed_website" id="limpeed_website" tabindex="-1" autocomplete="off">
			</div>

			<p>
				<label for="limpeed_reg_login"><?php esc_html_e( 'Identifiant', 'limpeed-immobilier' ); ?> <span class="required">*</span></label>
				<input type="text" name="user_login" id="limpeed_reg_login" required value="<?php echo esc_attr( $field( 'user_login' ) ); ?>" autocomplete="username">
			</p>
			<p>
				<label for="limpeed_reg_name"><?php esc_html_e( 'Nom affiché', 'limpeed-immobilier' ); ?></label>
				<input type="text" name="display_name" id="limpeed_reg_name" value="<?php echo esc_attr( $field( 'display_name' ) ); ?>">
			</p>
			<p>
				<label for="limpeed_reg_email"><?php esc_html_e( 'Email', 'limpeed-immobilier' ); ?> <span class="required">*</span></label>
				<input type="email" name="user_email" id="limpeed_reg_email" required value="<?php echo esc_attr( $field( 'user_email' ) ); ?>" autocomplete="email">
			</p>
			<p>
				<label for="limpeed_reg_pass"><?php esc_html_e( 'Mot de passe', 'limpeed-immobilier' ); ?> <span class="required">*</span></label>
				<input type="password" name="user_pass" id="limpeed_reg_pass" required minlength="8" autocomplete="new-password">
			</p>
			<p>
				<label for="limpeed_reg_pass2"><?php esc_html_e( 'Confirmer le mot de passe', 'limpeed-immobilier' ); ?> <span class="required">*</span></label>
				<input type="password" name="user_pass2" id="limpeed_reg_pass2" required minlength="8" autocomplete="new-password">
			</p>
			<p>
				<button type="submit" class="limpeed-auth-submit"><?php esc_html_e( 'Envoyer ma demande', 'limpeed-immobilier' ); ?></button>
			</p>
		</form>

	<?php endif; ?>

	<p class="limpeed-auth-links">
		<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Déjà un compte ? Se connecter', 'limpeed-immobilier' ); ?></a>
	</p>
</div>
