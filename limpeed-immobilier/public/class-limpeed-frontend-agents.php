<?php
/**
 * Contrôleur frontend de la section "Agents".
 * Traitement des formulaires (création, changement de rôle, révocation,
 * approbation/rejet des demandes en attente), miroir du contrôleur admin
 * (admin/class-limpeed-agents-page.php) mais routé via l'application
 * frontend (voir Limpeed_Frontend).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Frontend_Agents {

	/**
	 * Erreurs de validation du formulaire.
	 *
	 * @var array
	 */
	public static $errors = array();

	/**
	 * Données postées en cas d'erreur.
	 *
	 * @var array|null
	 */
	public static $posted = null;

	/**
	 * Traite les actions avant tout affichage (hook template_redirect).
	 */
	public static function handle_request() {
		if ( ! is_page( Limpeed_Frontend::dashboard_page_id() ) || 'agents' !== Limpeed_Frontend::current_view() ) {
			return;
		}

		if ( ! current_user_can( 'manage_limpeed_agents' ) ) {
			return;
		}

		// Révocation de l'accès.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'revoke' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_revoke_agent_' . $id );

			if ( get_current_user_id() === $id ) {
				Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'error', 'error_text' => rawurlencode( __( 'Vous ne pouvez pas révoquer votre propre accès.', 'limpeed-immobilier' ) ) ) );
			}

			Limpeed_Agents::revoke_access( $id );
			Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'revoked' ) );
		}

		// Rejet d'une demande d'inscription en attente.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'reject' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'limpeed_reject_agent_' . $id );

			Limpeed_Agents::reject( $id );
			Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'rejected' ) );
		}

		// Approbation d'une demande d'inscription en attente.
		if ( isset( $_POST['limpeed_approve_nonce'] ) ) {
			$pending_id = isset( $_POST['pending_id'] ) ? (int) $_POST['pending_id'] : 0;
			check_admin_referer( 'limpeed_approve_agent_' . $pending_id, 'limpeed_approve_nonce' );

			$role      = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
			$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

			if ( ! array_key_exists( $role, Limpeed_Agents::get_available_roles() ) ) {
				Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'error', 'error_text' => rawurlencode( __( 'Le rôle sélectionné n\'est pas valide.', 'limpeed-immobilier' ) ) ) );
			}

			Limpeed_Agents::approve( $pending_id, $role, $branch_id );
			Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'approved' ) );
		}

		// Création d'un agent ou modification de rôle.
		if ( isset( $_POST['limpeed_agent_nonce'] ) ) {
			check_admin_referer( 'limpeed_save_agent', 'limpeed_agent_nonce' );

			$agent_id = isset( $_POST['agent_id'] ) ? (int) $_POST['agent_id'] : 0;

			if ( $agent_id > 0 ) {
				if ( get_current_user_id() === $agent_id ) {
					Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'error', 'error_text' => rawurlencode( __( 'Vous ne pouvez pas modifier votre propre rôle.', 'limpeed-immobilier' ) ) ) );
				}

				$data = array(
					'role'      => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '',
					'branch_id' => isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0,
				);

				$errors = self::validate_role( $data );

				if ( ! empty( $errors ) ) {
					self::$errors = $errors;
					self::$posted = $data;
					return;
				}

				Limpeed_Agents::update_role( $agent_id, $data['role'], $data['branch_id'] );
				Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'updated' ) );
			}

			$data = array(
				'user_login'   => isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '',
				'user_email'   => isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '',
				'display_name' => isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '',
				'role'         => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '',
				'branch_id'    => isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0,
			);

			$errors = self::validate_create( $data );

			if ( ! empty( $errors ) ) {
				self::$errors = $errors;
				self::$posted = $data;
				return;
			}

			$result = Limpeed_Agents::create( $data );

			if ( is_wp_error( $result ) ) {
				self::$errors = array( $result->get_error_message() );
				self::$posted = $data;
				return;
			}

			Limpeed_Frontend::redirect_to( 'agents', array( 'message' => 'created' ) );
		}
	}

	/**
	 * Valide les données de création d'un agent.
	 *
	 * @param array $data
	 * @return array
	 */
	private static function validate_create( $data ) {
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

		if ( ! array_key_exists( $data['role'], Limpeed_Agents::get_available_roles() ) ) {
			$errors[] = __( 'Le rôle sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		} elseif ( in_array( $data['role'], Limpeed_Agents::get_branch_scoped_roles(), true ) && empty( $data['branch_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner une succursale pour ce rôle : sans succursale assignée, cet agent ne verra aucune donnée.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * Valide le changement de rôle d'un agent existant.
	 *
	 * @param array $data
	 * @return array
	 */
	private static function validate_role( $data ) {
		$errors = array();

		if ( ! array_key_exists( $data['role'], Limpeed_Agents::get_available_roles() ) ) {
			$errors[] = __( 'Le rôle sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		} elseif ( in_array( $data['role'], Limpeed_Agents::get_branch_scoped_roles(), true ) && empty( $data['branch_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner une succursale pour ce rôle : sans succursale assignée, cet agent ne verra aucune donnée.', 'limpeed-immobilier' );
		}

		return $errors;
	}
}

add_action( 'template_redirect', array( 'Limpeed_Frontend_Agents', 'handle_request' ) );
