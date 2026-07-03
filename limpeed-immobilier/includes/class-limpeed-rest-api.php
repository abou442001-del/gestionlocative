<?php
/**
 * API REST du plugin (namespace limpeed/v1).
 *
 * Pilote : section Locataires uniquement pour l'instant (recherche live, cascade
 * Propriétaire → Édifice → Sous-édifice, CRUD Ajax, panneau de détail).
 *
 * Sécurité :
 * - Chaque route déclare un permission_callback basé sur les capacités du plugin
 *   (voir Limpeed_Roles::get_capabilities()).
 * - Les requêtes non-GET (POST/PUT/DELETE) doivent inclure l'en-tête X-WP-Nonce
 *   avec un nonce créé côté serveur via wp_create_nonce( 'wp_rest' ) : c'est le
 *   mécanisme standard de l'API REST WordPress (voir wp_localize_script dans
 *   Limpeed_Frontend_Tenants::enqueue_assets()), vérifié automatiquement par
 *   WordPress avant l'exécution du permission_callback.
 * - Toutes les données entrantes passent par les mêmes méthodes CRUD
 *   (Limpeed_Tenants::insert()/update()) que les formulaires classiques, qui
 *   sanitizent déjà chaque champ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Rest_Api {

	const NAMESPACE_V1 = 'limpeed/v1';

	/**
	 * Initialise les hooks.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Déclare toutes les routes REST du plugin.
	 */
	public function register_routes() {
		$id_arg = array(
			'id' => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenants' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => array(
						'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'property_id' => array( 'sanitize_callback' => 'absint' ),
						'status'      => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_tenant' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_tenant' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_tenant' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)/payments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tenant_payments' ),
				'permission_callback' => array( $this, 'can_manage_tenants' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tenant_history' ),
				'permission_callback' => array( $this, 'can_manage_tenants' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/owners',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_owners' ),
				'permission_callback' => array( $this, 'can_view_lookups' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/buildings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_buildings' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => array(
						'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'owner_id' => array( 'sanitize_callback' => 'absint' ),
						'paged'    => array( 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_building' ),
					'permission_callback' => array( $this, 'can_manage_buildings' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/buildings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_building' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_building' ),
					'permission_callback' => array( $this, 'can_manage_buildings' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_building' ),
					'permission_callback' => array( $this, 'can_manage_buildings' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/buildings/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_building_history' ),
				'permission_callback' => array( $this, 'can_manage_buildings' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_properties' ),
				'permission_callback' => array( $this, 'can_view_lookups' ),
				'args'                => array(
					'building_id' => array( 'sanitize_callback' => 'absint' ),
					'tenant_id'   => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/**
	 * Capacité requise pour les routes de la section Locataires.
	 *
	 * @return bool
	 */
	public function can_manage_tenants() {
		return current_user_can( 'manage_limpeed_tenants' );
	}

	/**
	 * Capacité requise pour les routes de la section Édifices (mêmes droits
	 * que les biens : il n'existe pas de capacité manage_limpeed_buildings
	 * dédiée, voir Limpeed_Frontend_Buildings::handle_request()).
	 *
	 * @return bool
	 */
	public function can_manage_buildings() {
		return current_user_can( 'manage_limpeed_properties' );
	}

	/**
	 * Capacité requise pour les routes de consultation partagées (propriétaires,
	 * édifices, biens) : utilisées à la fois comme cascade de sélection par la
	 * section Locataires et comme listes propres à leurs sections dédiées.
	 *
	 * @return bool
	 */
	public function can_view_lookups() {
		return current_user_can( 'manage_limpeed_tenants' ) || current_user_can( 'manage_limpeed_properties' );
	}

	/**
	 * GET /tenants : liste filtrée + paginée.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_tenants( WP_REST_Request $request ) {
		$args = array(
			'search'      => (string) $request->get_param( 'search' ),
			'property_id' => (int) $request->get_param( 'property_id' ),
			'status'      => (string) $request->get_param( 'status' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$tenants = Limpeed_Tenants::get_all( $args );
		$total   = Limpeed_Tenants::count( $args );

		$items = array_map( array( $this, 'format_tenant_row' ), $tenants );

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => max( 1, (int) ceil( $total / $args['per_page'] ) ),
				'paged'       => $args['paged'],
			)
		);
	}

	/**
	 * GET /tenants/{id} : fiche complète (pour l'onglet Infos du panneau de détail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tenant( WP_REST_Request $request ) {
		$tenant = Limpeed_Tenants::get( (int) $request['id'] );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_tenant_detail( $tenant ) );
	}

	/**
	 * POST /tenants : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_tenant( WP_REST_Request $request ) {
		$data   = $this->extract_tenant_data( $request );
		$errors = $this->validate_tenant( $data, 0 );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Tenants::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer le locataire.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_tenant_detail( Limpeed_Tenants::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /tenants/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_tenant( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$tenant = Limpeed_Tenants::get( $id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_tenant_data( $request );
		$errors = $this->validate_tenant( $data, $id );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Tenants::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour le locataire.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_tenant_detail( Limpeed_Tenants::get( $id ) ) );
	}

	/**
	 * DELETE /tenants/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_tenant( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$tenant = Limpeed_Tenants::get( $id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Tenants::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /tenants/{id}/payments : historique des paiements (onglet Paiements).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tenant_payments( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$tenant = Limpeed_Tenants::get( $id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$payments = Limpeed_Payments::get_all(
			array(
				'tenant_id' => $id,
				'orderby'   => 'period',
				'order'     => 'DESC',
				'per_page'  => 100,
			)
		);

		$statuses = Limpeed_Payments::get_statuses();

		$items = array_map(
			function ( $payment ) use ( $statuses ) {
				return array(
					'id'                => (int) $payment->id,
					'period'            => $payment->period,
					'amount'            => (float) $payment->amount,
					'amount_formatted'  => Limpeed_Payments::format_amount( $payment->amount ),
					'status'            => $payment->status,
					'status_label'      => $statuses[ $payment->status ] ?? $payment->status,
					'payment_date'      => $payment->payment_date,
					'payment_method'    => $payment->payment_method,
				);
			},
			$payments
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * GET /tenants/{id}/history : journal d'activité lié à ce locataire (onglet Historique du bail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tenant_history( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$tenant = Limpeed_Tenants::get( $id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'tenant',
				'object_id'   => $id,
				'per_page'    => 50,
			)
		);

		$actions = Limpeed_Activity_Log::get_actions();

		$items = array_map(
			function ( $entry ) use ( $actions ) {
				$user = $entry->user_id ? get_userdata( $entry->user_id ) : false;
				return array(
					'id'           => (int) $entry->id,
					'action'       => $entry->action,
					'action_label' => $actions[ $entry->action ] ?? $entry->action,
					'description'  => $entry->description,
					'agent_name'   => $user ? $user->display_name : __( 'Inconnu', 'limpeed-immobilier' ),
					'created_at'   => $entry->created_at,
				);
			},
			$entries
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * GET /owners : liste complète pour peupler le premier niveau de la cascade.
	 *
	 * @return WP_REST_Response
	 */
	public function get_owners() {
		$owners = Limpeed_Owners::get_all( array( 'per_page' => 500, 'orderby' => 'full_name', 'order' => 'ASC' ) );

		$items = array_map(
			function ( $owner ) {
				return array(
					'id'    => (int) $owner->id,
					'label' => $owner->full_name,
				);
			},
			$owners
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * GET /buildings?owner_id=&search=&paged=&per_page= : sert à la fois de
	 * deuxième niveau de la cascade Locataires (appel léger avec seulement
	 * owner_id) et de liste paginée pour la section Édifices elle-même (avec
	 * recherche et pagination). Le per_page par défaut (500) préserve le
	 * comportement de la cascade quand ces paramètres ne sont pas fournis.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_buildings( WP_REST_Request $request ) {
		$args = array(
			'search'   => (string) $request->get_param( 'search' ),
			'owner_id' => (int) $request->get_param( 'owner_id' ),
			'paged'    => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page' => min( 500, max( 1, (int) $request->get_param( 'per_page' ) ?: 500 ) ),
			'orderby'  => 'name',
			'order'    => 'ASC',
		);

		$buildings = Limpeed_Buildings::get_all( $args );
		$total     = Limpeed_Buildings::count( $args );

		$items = array_map( array( $this, 'format_building_row' ), $buildings );

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => max( 1, (int) ceil( $total / $args['per_page'] ) ),
				'paged'       => $args['paged'],
			)
		);
	}

	/**
	 * GET /buildings/{id} : fiche complète (onglet Infos du panneau de détail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_building( WP_REST_Request $request ) {
		$building = Limpeed_Buildings::get( (int) $request['id'] );
		if ( ! $building ) {
			return new WP_Error( 'limpeed_not_found', __( 'Édifice introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_building_detail( $building ) );
	}

	/**
	 * POST /buildings : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_building( WP_REST_Request $request ) {
		$data   = $this->extract_building_data( $request );
		$errors = $this->validate_building( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Buildings::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer l\'édifice.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_building_detail( Limpeed_Buildings::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /buildings/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_building( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$building = Limpeed_Buildings::get( $id );
		if ( ! $building ) {
			return new WP_Error( 'limpeed_not_found', __( 'Édifice introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_building_data( $request );
		$errors = $this->validate_building( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Buildings::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour l\'édifice.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_building_detail( Limpeed_Buildings::get( $id ) ) );
	}

	/**
	 * DELETE /buildings/{id}. Refusé si des sous-édifices (biens) y sont
	 * encore rattachés (voir Limpeed_Buildings::delete()).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_building( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$building = Limpeed_Buildings::get( $id );
		if ( ! $building ) {
			return new WP_Error( 'limpeed_not_found', __( 'Édifice introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$result = Limpeed_Buildings::delete( $id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /buildings/{id}/history : journal d'activité lié à cet édifice.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_building_history( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$building = Limpeed_Buildings::get( $id );
		if ( ! $building ) {
			return new WP_Error( 'limpeed_not_found', __( 'Édifice introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'building',
				'object_id'   => $id,
				'per_page'    => 50,
			)
		);

		$actions = Limpeed_Activity_Log::get_actions();

		$items = array_map(
			function ( $entry ) use ( $actions ) {
				$user = $entry->user_id ? get_userdata( $entry->user_id ) : false;
				return array(
					'id'           => (int) $entry->id,
					'action'       => $entry->action,
					'action_label' => $actions[ $entry->action ] ?? $entry->action,
					'description'  => $entry->description,
					'agent_name'   => $user ? $user->display_name : __( 'Inconnu', 'limpeed-immobilier' ),
					'created_at'   => $entry->created_at,
				);
			},
			$entries
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * Extrait et pré-nettoie les données édifice d'une requête REST, mêmes
	 * champs que Limpeed_Frontend_Buildings::handle_request() (hors sous-édifices
	 * en masse, non gérés par ce pilote — voir la fiche complète classique).
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_building_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'owner_id'        => isset( $params['owner_id'] ) ? (int) $params['owner_id'] : 0,
			'name'            => isset( $params['name'] ) ? wp_unslash( $params['name'] ) : '',
			'address'         => isset( $params['address'] ) ? wp_unslash( $params['address'] ) : '',
			'description'     => isset( $params['description'] ) ? wp_unslash( $params['description'] ) : '',
			'commission_rate' => isset( $params['commission_rate'] ) ? wp_unslash( $params['commission_rate'] ) : '',
		);
	}

	/**
	 * Règles de validation métier, identiques à Limpeed_Frontend_Buildings::validate().
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_building( $data ) {
		$errors = array();

		if ( empty( $data['owner_id'] ) || ! Limpeed_Owners::get( $data['owner_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un propriétaire valide.', 'limpeed-immobilier' );
		}

		if ( empty( trim( (string) $data['name'] ) ) ) {
			$errors[] = __( 'Le nom de l\'édifice est obligatoire.', 'limpeed-immobilier' );
		}

		if ( '' !== $data['commission_rate'] && ( ! is_numeric( $data['commission_rate'] ) || $data['commission_rate'] < 0 || $data['commission_rate'] > 100 ) ) {
			$errors[] = __( 'Le taux de commission doit être un nombre entre 0 et 100.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * Formate une ligne de la liste (colonnes affichées dans le tableau Édifices),
	 * et sert aussi de forme allégée pour la cascade de sélection de la section
	 * Locataires (les champs id/label/owner_id y suffisent, le reste est ignoré).
	 *
	 * @param object $building
	 * @return array
	 */
	private function format_building_row( $building ) {
		$owner = Limpeed_Owners::get( $building->owner_id );

		return array(
			'id'                     => (int) $building->id,
			'label'                  => $building->name,
			'name'                   => $building->name,
			'owner_id'               => (int) $building->owner_id,
			'owner_label'            => $owner ? $owner->full_name : '',
			'address'                => $building->address,
			'commission_rate'        => (float) $building->commission_rate,
			'commission_rate_label'  => number_format_i18n( (float) $building->commission_rate, 2 ) . '%',
			'properties_count'       => Limpeed_Properties::count( array( 'building_id' => $building->id ) ),
		);
	}

	/**
	 * Formate la fiche complète d'un édifice (onglet Infos du panneau de détail).
	 *
	 * @param object $building
	 * @return array
	 */
	private function format_building_detail( $building ) {
		$owner = Limpeed_Owners::get( $building->owner_id );

		return array(
			'id'                    => (int) $building->id,
			'owner_id'              => (int) $building->owner_id,
			'owner_label'           => $owner ? $owner->full_name : '',
			'name'                  => $building->name,
			'address'               => $building->address,
			'description'           => $building->description,
			'commission_rate'       => (float) $building->commission_rate,
			'commission_rate_label' => number_format_i18n( (float) $building->commission_rate, 2 ) . '%',
			'properties_count'      => Limpeed_Properties::count( array( 'building_id' => $building->id ) ),
		);
	}

	/**
	 * GET /properties?building_id=&tenant_id= : troisième niveau de la cascade.
	 * Le paramètre tenant_id (optionnel) indique le locataire en cours de
	 * modification : son bien actuel reste sélectionnable même s'il est occupé.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_properties( WP_REST_Request $request ) {
		$building_id      = (int) $request->get_param( 'building_id' );
		$editing_tenant_id = (int) $request->get_param( 'tenant_id' );

		$properties = Limpeed_Properties::get_all(
			array(
				'building_id' => $building_id,
				'per_page'    => 500,
				'orderby'     => 'address',
				'order'       => 'ASC',
			)
		);

		$items = array_map(
			function ( $property ) use ( $editing_tenant_id ) {
				$current_tenant = Limpeed_Properties::get_current_tenant( $property->id );
				$occupied       = $current_tenant && (int) $current_tenant->id !== $editing_tenant_id;

				return array(
					'id'              => (int) $property->id,
					'label'           => Limpeed_Properties::get_display_label( $property ),
					'building_id'     => (int) $property->building_id,
					'monthly_rent'    => (float) $property->monthly_rent,
					'status'          => $property->status,
					'occupied'        => $occupied,
				);
			},
			$properties
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * Extrait et pré-nettoie les données locataire d'une requête REST (mêmes
	 * champs que Limpeed_Frontend_Tenants::handle_request(), la sanitization
	 * fine reste faite par Limpeed_Tenants::insert()/update()).
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_tenant_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'property_id'        => isset( $params['property_id'] ) ? (int) $params['property_id'] : 0,
			'full_name'          => isset( $params['full_name'] ) ? wp_unslash( $params['full_name'] ) : '',
			'phone'              => isset( $params['phone'] ) ? wp_unslash( $params['phone'] ) : '',
			'email'              => isset( $params['email'] ) ? wp_unslash( $params['email'] ) : '',
			'lease_start'        => isset( $params['lease_start'] ) ? sanitize_text_field( $params['lease_start'] ) : '',
			'lease_end'          => isset( $params['lease_end'] ) ? sanitize_text_field( $params['lease_end'] ) : '',
			'rent_amount'        => isset( $params['rent_amount'] ) ? wp_unslash( $params['rent_amount'] ) : '',
			'deposit_paid'       => isset( $params['deposit_paid'] ) ? wp_unslash( $params['deposit_paid'] ) : '',
			'status'             => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'actif',
			'id_document_type'   => isset( $params['id_document_type'] ) ? sanitize_text_field( $params['id_document_type'] ) : '',
			'id_document_number' => isset( $params['id_document_number'] ) ? wp_unslash( $params['id_document_number'] ) : '',
			'date_of_birth'      => isset( $params['date_of_birth'] ) ? sanitize_text_field( $params['date_of_birth'] ) : '',
			'profession'         => isset( $params['profession'] ) ? wp_unslash( $params['profession'] ) : '',
			'dependents_count'   => isset( $params['dependents_count'] ) ? wp_unslash( $params['dependents_count'] ) : '',
			'guarantor_name'     => isset( $params['guarantor_name'] ) ? wp_unslash( $params['guarantor_name'] ) : '',
			'guarantor_phone'    => isset( $params['guarantor_phone'] ) ? wp_unslash( $params['guarantor_phone'] ) : '',
		);
	}

	/**
	 * Règles de validation métier, identiques à Limpeed_Frontend_Tenants::validate().
	 *
	 * @param array $data
	 * @param int   $tenant_id Id du locataire en cours de modification (0 pour un ajout).
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_tenant( $data, $tenant_id = 0 ) {
		$errors = array();

		if ( empty( $data['property_id'] ) || ! Limpeed_Properties::get( $data['property_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un bien valide.', 'limpeed-immobilier' );
		} elseif ( 'actif' === ( $data['status'] ?? 'actif' ) ) {
			$current_tenant = Limpeed_Properties::get_current_tenant( $data['property_id'] );
			if ( $current_tenant && (int) $current_tenant->id !== (int) $tenant_id ) {
				$errors[] = __( 'Ce bien a déjà un locataire actif. Terminez d\'abord son bail avant d\'en ajouter un nouveau.', 'limpeed-immobilier' );
			}
		}

		if ( empty( trim( (string) $data['full_name'] ) ) ) {
			$errors[] = __( 'Le nom complet du locataire est obligatoire.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors[] = __( 'L\'adresse email n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['phone'] ) && ! preg_match( '/^[0-9+\s().-]{6,20}$/', $data['phone'] ) ) {
			$errors[] = __( 'Le numéro de téléphone n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['lease_start'] ) && ! empty( $data['lease_end'] ) && $data['lease_start'] > $data['lease_end'] ) {
			$errors[] = __( 'La date de fin de bail doit être postérieure à la date de début.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Tenants::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		foreach ( array( 'rent_amount', 'deposit_paid' ) as $field ) {
			if ( '' !== $data[ $field ] && ! is_numeric( $data[ $field ] ) ) {
				$errors[] = __( 'Les montants (loyer, dépôt) doivent être des nombres.', 'limpeed-immobilier' );
				break;
			}
		}

		return $errors;
	}

	/**
	 * Formate une ligne de la liste (colonnes affichées dans le tableau Locataires).
	 *
	 * @param object $tenant
	 * @return array
	 */
	private function format_tenant_row( $tenant ) {
		$property = Limpeed_Properties::get( $tenant->property_id );
		$building = $property ? Limpeed_Buildings::get( $property->building_id ) : null;
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$statuses = Limpeed_Tenants::get_statuses();

		return array(
			'id'               => (int) $tenant->id,
			'full_name'        => $tenant->full_name,
			'phone'            => $tenant->phone,
			'email'            => $tenant->email,
			'property_id'      => (int) $tenant->property_id,
			'property_label'   => $property ? Limpeed_Properties::get_display_label( $property ) : '',
			'owner_label'      => $owner ? $owner->full_name : '',
			'rent_amount'      => (float) $tenant->rent_amount,
			'rent_formatted'   => Limpeed_Payments::format_amount( $tenant->rent_amount ),
			'status'           => $tenant->status,
			'status_label'     => $statuses[ $tenant->status ] ?? $tenant->status,
			'lease_start'      => $tenant->lease_start,
			'lease_end'        => $tenant->lease_end,
			'edit_url'         => Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'edit', 'id' => $tenant->id ) ),
			'delete_nonce'     => wp_create_nonce( 'limpeed_delete_tenant_' . $tenant->id ),
		);
	}

	/**
	 * Formate la fiche complète d'un locataire (onglet Infos du panneau de détail).
	 *
	 * @param object $tenant
	 * @return array
	 */
	private function format_tenant_detail( $tenant ) {
		$property = Limpeed_Properties::get( $tenant->property_id );
		$building = $property ? Limpeed_Buildings::get( $property->building_id ) : null;
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$statuses = Limpeed_Tenants::get_statuses();
		$id_types = Limpeed_Tenants::get_id_document_types();

		$advance_status = Limpeed_Tenants::get_advance_status( $tenant );
		$deposit_status = Limpeed_Tenants::get_deposit_status( $tenant );

		return array(
			'id'                 => (int) $tenant->id,
			'full_name'          => $tenant->full_name,
			'phone'              => $tenant->phone,
			'email'              => $tenant->email,
			'property_id'        => (int) $tenant->property_id,
			'property_label'     => $property ? Limpeed_Properties::get_display_label( $property ) : '',
			'building_id'        => $building ? (int) $building->id : 0,
			'building_label'     => $building ? $building->name : '',
			'owner_id'           => $owner ? (int) $owner->id : 0,
			'owner_label'        => $owner ? $owner->full_name : '',
			'lease_start'        => $tenant->lease_start,
			'lease_end'          => $tenant->lease_end,
			'rent_amount'        => (float) $tenant->rent_amount,
			'rent_formatted'     => Limpeed_Payments::format_amount( $tenant->rent_amount ),
			'deposit_paid'       => (float) $tenant->deposit_paid,
			'status'             => $tenant->status,
			'status_label'       => $statuses[ $tenant->status ] ?? $tenant->status,
			'id_document_type'   => $tenant->id_document_type,
			'id_document_label'  => $id_types[ $tenant->id_document_type ] ?? '',
			'id_document_number' => $tenant->id_document_number,
			'date_of_birth'      => $tenant->date_of_birth,
			'profession'         => $tenant->profession,
			'dependents_count'   => (int) $tenant->dependents_count,
			'guarantor_name'     => $tenant->guarantor_name,
			'guarantor_phone'    => $tenant->guarantor_phone,
			'advance_status'     => array(
				'months_ahead'    => $advance_status['months_ahead'],
				'status'          => $advance_status['status'],
				'advance_amount'  => $advance_status['advance_amount'],
			),
			'deposit_status'     => array(
				'status'   => $deposit_status['status'],
				'paid'     => $deposit_status['paid'],
				'required' => $deposit_status['required'],
			),
		);
	}
}
