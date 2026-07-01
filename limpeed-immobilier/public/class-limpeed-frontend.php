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
	 * Rendu du logo Limpeed Immobilier (icône + libellé), réutilisé sur
	 * l'application frontend et les pages de connexion/inscription.
	 *
	 * @param bool $with_text Affiche le libellé texte à côté de l'icône.
	 * @return string Balisage HTML (déjà échappé), à afficher directement.
	 */
	public static function render_logo( $with_text = true ) {
		$custom_logo_url = Limpeed_Branding::get_logo_url();

		if ( $custom_logo_url ) {
			$icon = '<img src="' . esc_url( $custom_logo_url ) . '" alt="' . esc_attr__( 'Limpeed Immobilier', 'limpeed-immobilier' ) . '" class="limpeed-brand-mark limpeed-brand-mark-custom">';
		} else {
			// Reproduction du logo Limpeed Immobilier par défaut : ligne diagonale
			// traversant un empilement de carrés verts en escalier (motif
			// "bâtiment/graphique"), utilisée tant qu'aucun logo personnalisé
			// n'a été téléversé depuis Réglages.
			$icon = '<svg class="limpeed-brand-mark" viewBox="0 0 48 48" width="34" height="34" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
				. '<line x1="3" y1="45" x2="31" y2="7" stroke="#2c3e50" stroke-width="2" stroke-linecap="round"/>'
				. '<rect x="21" y="27" width="10" height="10" rx="1.5" fill="#8fd6a4"/>'
				. '<rect x="32" y="27" width="10" height="10" rx="1.5" fill="#4caf7d"/>'
				. '<rect x="21" y="16" width="10" height="10" rx="1.5" fill="#3aa655"/>'
				. '<rect x="32" y="16" width="10" height="10" rx="1.5" fill="#1f7a41"/>'
				. '<rect x="32" y="5" width="10" height="10" rx="1.5" fill="#175c31"/>'
				. '</svg>';
		}

		$text = '';
		if ( $with_text && ! $custom_logo_url ) {
			$text = '<span class="limpeed-brand-text">'
				. '<span class="limpeed-brand-name">' . esc_html__( 'Limpeed', 'limpeed-immobilier' ) . '</span>'
				. '<span class="limpeed-brand-sub">' . esc_html__( 'Immobilier', 'limpeed-immobilier' ) . '</span>'
				. '</span>';
		}

		return '<span class="limpeed-brand">' . $icon . $text . '</span>';
	}

	/**
	 * Déclare les sections de l'application frontend : libellé, capacité
	 * requise, icône dashicons et fichier de contenu associé (dans public/views/).
	 * Toute nouvelle section migrée depuis wp-admin doit être ajoutée ici.
	 *
	 * @return array
	 */
	public static function get_sections() {
		return array(
			'dashboard'    => array(
				'label' => __( 'Tableau de bord', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_properties',
				'icon'  => 'dashicons-chart-bar',
			),
			'owners'       => array(
				'label' => __( 'Propriétaires', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_owners',
				'icon'  => 'dashicons-groups',
			),
			'buildings'    => array(
				'label' => __( 'Édifices', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_properties',
				'icon'  => 'dashicons-admin-multisite',
			),
			'properties'   => array(
				'label' => __( 'Biens', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_properties',
				'icon'  => 'dashicons-building',
			),
			'tenants'      => array(
				'label' => __( 'Locataires', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_tenants',
				'icon'  => 'dashicons-admin-users',
			),
			'payments'     => array(
				'label' => __( 'Paiements', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_payments',
				'icon'  => 'dashicons-money-alt',
			),
			'statements'   => array(
				'label' => __( 'Bordereaux', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_statements',
				'icon'  => 'dashicons-media-document',
			),
			'agents'       => array(
				'label' => __( 'Agents', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_agents',
				'icon'  => 'dashicons-id',
			),
			'activity-log' => array(
				'label' => __( 'Journal d\'activité', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_agents',
				'icon'  => 'dashicons-list-view',
			),
			'settings'     => array(
				'label' => __( 'Réglages', 'limpeed-immobilier' ),
				'cap'   => 'manage_limpeed_agents',
				'icon'  => 'dashicons-admin-generic',
			),
		);
	}

	/**
	 * Détermine la section demandée (paramètre limpeed_view), repliée sur
	 * "dashboard" si absente ou inconnue.
	 *
	 * @return string
	 */
	public static function current_view() {
		$view     = isset( $_GET['limpeed_view'] ) ? sanitize_key( wp_unslash( $_GET['limpeed_view'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sections = self::get_sections();
		return isset( $sections[ $view ] ) ? $view : 'dashboard';
	}

	/**
	 * Construit l'URL d'une section de l'application frontend.
	 *
	 * @param string $view
	 * @param array  $args Paramètres de requête additionnels.
	 * @return string
	 */
	public static function app_url( $view = 'dashboard', $args = array() ) {
		$dashboard_page_id = self::dashboard_page_id();
		$base              = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/' );
		return add_query_arg( array_merge( array( 'limpeed_view' => $view ), $args ), $base );
	}

	/**
	 * Redirige vers une section de l'application frontend et termine la requête.
	 *
	 * @param string $view
	 * @param array  $args
	 */
	public static function redirect_to( $view, $args = array() ) {
		wp_safe_redirect( self::app_url( $view, $args ) );
		exit;
	}

	/**
	 * Affiche la pagination d'une liste de section frontend.
	 *
	 * @param int   $total_items
	 * @param int   $per_page
	 * @param int   $paged
	 * @param array $base_args Paramètres de requête à conserver (filtres, recherche...).
	 */
	public static function render_pagination( $total_items, $per_page, $paged, $base_args = array() ) {
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="limpeed-app-pagination">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url   = self::app_url( self::current_view(), array_merge( $base_args, array( 'paged' => $i ) ) );
			$class = $i === (int) $paged ? 'is-active' : '';
			printf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $i ) );
		}
		echo '</div>';
	}

	/**
	 * Affiche un message de succès/erreur en haut d'une section frontend.
	 *
	 * @param string $message Valeur du paramètre limpeed_message.
	 * @param array  $texts   Tableau message => texte affiché.
	 */
	public static function render_notice( $message, $texts = array() ) {
		if ( ! $message ) {
			return;
		}

		if ( 'error' === $message ) {
			$text = isset( $_GET['error_text'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? sanitize_text_field( wp_unslash( $_GET['error_text'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				: ( $texts['error'] ?? __( 'Une erreur est survenue.', 'limpeed-immobilier' ) );
			printf( '<div class="limpeed-app-notice limpeed-app-notice-error">%s</div>', esc_html( $text ) );
			return;
		}

		if ( empty( $texts[ $message ] ) ) {
			return;
		}

		printf( '<div class="limpeed-app-notice limpeed-app-notice-success">%s</div>', esc_html( $texts[ $message ] ) );
	}

	/**
	 * Empêche l'accès à l'application frontend aux visiteurs non connectés,
	 * aux comptes en attente d'approbation, et aux comptes sans la capacité
	 * requise par la section demandée. Exécuté sur template_redirect, avant
	 * tout affichage.
	 */
	public function restrict_dashboard_access() {
		$dashboard_page_id = self::dashboard_page_id();
		if ( ! $dashboard_page_id || ! is_page( $dashboard_page_id ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$redirect = add_query_arg( 'redirect_to', rawurlencode( home_url( add_query_arg( array() ) ) ), self::login_url() );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( get_user_meta( get_current_user_id(), Limpeed_Agents::PENDING_META_KEY, true ) ) {
			wp_safe_redirect( add_query_arg( 'limpeed_message', 'pending', self::login_url() ) );
			exit;
		}

		$sections = self::get_sections();
		$view     = self::current_view();

		if ( ! current_user_can( $sections[ $view ]['cap'] ) ) {
			wp_die(
				esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette section.', 'limpeed-immobilier' ),
				esc_html__( 'Accès refusé', 'limpeed-immobilier' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Remplace le template du thème par notre application autonome pour la
	 * page Tableau de bord (aucun header/footer/style du thème actif).
	 *
	 * @param string $template
	 * @return string
	 */
	public function maybe_load_dashboard_template( $template ) {
		$dashboard_page_id = self::dashboard_page_id();
		if ( $dashboard_page_id && is_page( $dashboard_page_id ) ) {
			return LIMPEED_PLUGIN_DIR . 'public/views/app.php';
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

		wp_safe_redirect( $redirect_to ? $redirect_to : self::app_url( 'dashboard' ) );
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
