<?php
/**
 * Pages frontend : connexion et inscription des agents.
 *
 * L'inscription publique ne donne AUCUN accès immédiat : le compte créé n'a
 * aucun rôle Limpeed tant qu'un administrateur ne l'a pas explicitement
 * approuvé depuis Agents > Demandes en attente (voir Limpeed_Agents::approve()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend {

	/**
	 * Erreurs du formulaire de connexion (le temps de la requête).
	 *
	 * @var array
	 */
	public static $login_errors = array();

	/**
	 * Erreurs du formulaire d'inscription (le temps de la requête).
	 *
	 * @var array
	 */
	public static $register_errors = array();

	/**
	 * Données postées de l'inscription en cas d'erreur.
	 *
	 * @var array|null
	 */
	public static $register_posted = null;

	/**
	 * Initialise les hooks frontend.
	 */
	public function init() {
		add_shortcode( 'limpeed_login', array( $this, 'render_login' ) );
		add_shortcode( 'limpeed_register', array( $this, 'render_register' ) );
		add_shortcode( 'limpeed_dashboard', array( $this, 'render_dashboard_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_login_submit' ) );
		add_action( 'template_redirect', array( $this, 'handle_register_submit' ) );
		add_action( 'template_redirect', array( $this, 'restrict_dashboard_access' ) );
		add_filter( 'template_include', array( $this, 'maybe_load_dashboard_template' ) );
	}

	/**
	 * Id de la page "Tableau de bord" créée à l'activation.
	 *
	 * @return int
	 */
	public static function dashboard_page_id() {
		return (int) get_option( 'limpeed_dashboard_page_id' );
	}

	/**
	 * URL de la page de connexion frontend.
	 *
	 * @return string
	 */
	public static function login_url() {
		$login_page_id = (int) get_option( 'limpeed_login_page_id' );
		return $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
	}

	/**
	 * Empêche l'accès au tableau de bord frontend aux visiteurs non connectés,
	 * aux comptes en attente d'approbation, et aux comptes sans capacité Limpeed.
	 * Exécuté sur template_redirect, avant tout affichage.
	 */
	public function restrict_dashboard_access() {
		$dashboard_page_id = self::dashboard_page_id();
		if ( ! $dashboard_page_id || ! is_page( $dashboard_page_id ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$redirect = add_query_arg( 'redirect_to', rawurlencode( get_permalink( $dashboard_page_id ) ), self::login_url() );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( get_user_meta( get_current_user_id(), Limpeed_Agents::PENDING_META_KEY, true ) ) {
			wp_safe_redirect( add_query_arg( 'limpeed_message', 'pending', self::login_url() ) );
			exit;
		}

		if ( ! current_user_can( 'manage_limpeed_properties' ) ) {
			wp_die(
				esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder au tableau de bord.', 'limpeed-immobilier' ),
				esc_html__( 'Accès refusé', 'limpeed-immobilier' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Remplace le template du thème par notre page "application" autonome
	 * pour la page Tableau de bord (aucun header/footer/style du thème actif).
	 *
	 * @param string $template
	 * @return string
	 */
	public function maybe_load_dashboard_template( $template ) {
		$dashboard_page_id = self::dashboard_page_id();
		if ( $dashboard_page_id && is_page( $dashboard_page_id ) ) {
			return LIMPEED_PLUGIN_DIR . 'public/views/dashboard-app.php';
		}
		return $template;
	}

	/**
	 * Shortcode [limpeed_dashboard] : filet de sécurité si le template
	 * personnalisé n'a pas pu être chargé (ex : aperçu, contexte inhabituel).
	 *
	 * @return string
	 */
	public function render_dashboard_shortcode() {
		return '<p>' . esc_html__( 'Le tableau de bord doit être consulté directement en visitant cette page.', 'limpeed-immobilier' ) . '</p>';
	}

	/**
	 * Charge le CSS uniquement sur les pages contenant l'un des shortcodes.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'limpeed_login' ) || has_shortcode( $post->post_content, 'limpeed_register' ) ) {
			wp_enqueue_style( 'limpeed-frontend', LIMPEED_PLUGIN_URL . 'public/assets/frontend.css', array(), LIMPEED_VERSION );
		}
	}

	/**
	 * Traite la soumission du formulaire de connexion.
	 * Exécuté sur template_redirect : avant tout affichage, headers non envoyés.
	 */
	public function handle_login_submit() {
		if ( ! isset( $_POST['limpeed_login_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['limpeed_login_nonce'] ) ), 'limpeed_login' ) ) {
			self::$login_errors[] = __( 'Votre session a expiré, veuillez réessayer.', 'limpeed-immobilier' );
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$user_login    = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_password = isset( $_POST['user_password'] ) ? (string) $_POST['user_password'] : '';

		if ( empty( $user_login ) || empty( $user_password ) ) {
			self::$login_errors[] = __( 'Veuillez renseigner votre identifiant et votre mot de passe.', 'limpeed-immobilier' );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $user_login,
				'user_password' => $user_password,
				'remember'      => ! empty( $_POST['remember'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::$login_errors[] = __( 'Identifiant ou mot de passe incorrect.', 'limpeed-immobilier' );
			return;
		}

		$redirect_to = isset( $_POST['limpeed_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['limpeed_redirect_to'] ) ) : '';

		if ( get_user_meta( $user->ID, Limpeed_Agents::PENDING_META_KEY, true ) ) {
			$back = $redirect_to ? $redirect_to : ( wp_get_referer() ? wp_get_referer() : home_url() );
			wp_safe_redirect( add_query_arg( 'limpeed_message', 'pending', $back ) );
			exit;
		}

		wp_safe_redirect( $redirect_to ? $redirect_to : admin_url( 'admin.php?page=limpeed-immobilier' ) );
		exit;
	}

	/**
	 * Traite la soumission du formulaire d'inscription.
	 * Crée un compte "en attente" sans aucun rôle Limpeed (voir Limpeed_Agents::create_pending()).
	 */
	public function handle_register_submit() {
		if ( ! isset( $_POST['limpeed_register_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['limpeed_register_nonce'] ) ), 'limpeed_register' ) ) {
			self::$register_errors[] = __( 'Votre session a expiré, veuillez réessayer.', 'limpeed-immobilier' );
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		// Piège à robots : ce champ est masqué en CSS, seul un bot le remplira.
		if ( ! empty( $_POST['limpeed_website'] ) ) {
			return;
		}

		$data = array(
			'user_login'   => isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '',
			'user_email'   => isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '',
			'display_name' => isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '',
		);
		$password  = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : '';
		$password2 = isset( $_POST['user_pass2'] ) ? (string) $_POST['user_pass2'] : '';

		$errors = self::validate_registration( $data, $password, $password2 );

		if ( ! empty( $errors ) ) {
			self::$register_errors = $errors;
			self::$register_posted = $data;
			return;
		}

		$data['user_pass'] = $password;
		$result            = Limpeed_Agents::create_pending( $data );

		if ( is_wp_error( $result ) ) {
			self::$register_errors = array( $result->get_error_message() );
			self::$register_posted = $data;
			return;
		}

		$redirect_to = isset( $_POST['limpeed_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['limpeed_redirect_to'] ) ) : '';
		$back        = $redirect_to ? $redirect_to : ( wp_get_referer() ? wp_get_referer() : home_url() );
		wp_safe_redirect( add_query_arg( 'limpeed_message', 'registered', $back ) );
		exit;
	}

	/**
	 * Valide les données du formulaire d'inscription.
	 *
	 * @param array  $data
	 * @param string $password
	 * @param string $password2
	 * @return array
	 */
	private static function validate_registration( $data, $password, $password2 ) {
		$errors = array();

		if ( empty( $data['user_login'] ) || ! validate_username( $data['user_login'] ) ) {
			$errors[] = __( 'L\'identifiant est obligatoire et ne doit contenir que des caractères valides.', 'limpeed-immobilier' );
		} elseif ( username_exists( $data['user_login'] ) ) {
			$errors[] = __( 'Cet identifiant est déjà utilisé.', 'limpeed-immobilier' );
		}

		if ( empty( $data['user_email'] ) || ! is_email( $data['user_email'] ) ) {
			$errors[] = __( 'L\'adresse email est obligatoire et doit être valide.', 'limpeed-immobilier' );
		} elseif ( email_exists( $data['user_email'] ) ) {
			$errors[] = __( 'Cette adresse email est déjà utilisée par un autre compte.', 'limpeed-immobilier' );
		}

		if ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Le mot de passe doit contenir au moins 8 caractères.', 'limpeed-immobilier' );
		} elseif ( $password !== $password2 ) {
			$errors[] = __( 'Les deux mots de passe ne correspondent pas.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * Shortcode [limpeed_login].
	 *
	 * @return string
	 */
	public function render_login() {
		if ( is_user_logged_in() ) {
			return self::render_logged_in_notice();
		}

		ob_start();
		$errors  = self::$login_errors;
		$message = isset( $_GET['limpeed_message'] ) ? sanitize_text_field( wp_unslash( $_GET['limpeed_message'] ) ) : '';
		include LIMPEED_PLUGIN_DIR . 'public/views/login-form.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode [limpeed_register].
	 *
	 * @return string
	 */
	public function render_register() {
		if ( is_user_logged_in() ) {
			return self::render_logged_in_notice();
		}

		ob_start();
		$errors  = self::$register_errors;
		$posted  = self::$register_posted;
		$message = isset( $_GET['limpeed_message'] ) ? sanitize_text_field( wp_unslash( $_GET['limpeed_message'] ) ) : '';
		include LIMPEED_PLUGIN_DIR . 'public/views/register-form.php';
		return ob_get_clean();
	}

	/**
	 * Message affiché à un utilisateur déjà connecté sur les pages connexion/inscription.
	 *
	 * @return string
	 */
	private static function render_logged_in_notice() {
		$user = wp_get_current_user();

		ob_start();
		include LIMPEED_PLUGIN_DIR . 'public/views/logged-in-notice.php';
		return ob_get_clean();
	}
}
