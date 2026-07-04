<?php
/**
 * API REST du plugin (namespace limpeed/v1).
 *
 * Couvre les sections Locataires, Édifices et Biens : recherche/filtre en
 * direct, cascade Propriétaire → Édifice → Sous-édifice, CRUD Ajax, panneau
 * de détail.
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
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => array(
						'search'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'property_id'  => array( 'sanitize_callback' => 'absint' ),
						'status'       => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'        => array( 'sanitize_callback' => 'absint' ),
						'per_page'     => array( 'sanitize_callback' => 'absint' ),
						'with_balance' => array( 'sanitize_callback' => 'absint' ),
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
				'args'                => array(
					'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'paged'    => array( 'sanitize_callback' => 'absint' ),
					'per_page' => array( 'sanitize_callback' => 'absint' ),
				),
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
					'permission_callback' => array( $this, 'can_manage_properties' ),
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
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_building' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
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
				'permission_callback' => array( $this, 'can_manage_properties' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/properties',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_properties' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => array(
						'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'owner_id'    => array( 'sanitize_callback' => 'absint' ),
						'building_id' => array( 'sanitize_callback' => 'absint' ),
						'status'      => array( 'sanitize_callback' => 'sanitize_key' ),
						'tenant_id'   => array( 'sanitize_callback' => 'absint' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_property' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/properties/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_property' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_property' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_property' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/properties/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_property_history' ),
				'permission_callback' => array( $this, 'can_manage_properties' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/properties/(?P<id>\d+)/payments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_property_payments' ),
				'permission_callback' => array( $this, 'can_manage_properties' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/payments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_payments' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
					'args'                => array(
						'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'tenant_id'   => array( 'sanitize_callback' => 'absint' ),
						'property_id' => array( 'sanitize_callback' => 'absint' ),
						'period'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'status'      => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_payment' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/payments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_payment' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_payment' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_payment' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/payments/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_payment_history' ),
				'permission_callback' => array( $this, 'can_manage_payments' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/kpis',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard_kpis' ),
				'permission_callback' => array( $this, 'can_manage_properties' ),
				'args'                => array(
					'expiring_days' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/unpaid-tenants',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard_unpaid_tenants' ),
				'permission_callback' => array( $this, 'can_view_lookups' ),
				'args'                => array(
					'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'period'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'paged'    => array( 'sanitize_callback' => 'absint' ),
					'per_page' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/mandates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mandates' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => array(
						'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'building_id' => array( 'sanitize_callback' => 'absint' ),
						'status'      => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_mandate' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/mandates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mandate' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_mandate' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_mandate' ),
					'permission_callback' => array( $this, 'can_manage_properties' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/mandates/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mandate_history' ),
				'permission_callback' => array( $this, 'can_manage_properties' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)/amendments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant_amendments' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_tenant_amendment' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)/amendments/(?P<amendment_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_tenant_amendment' ),
				'permission_callback' => array( $this, 'can_manage_tenants' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/inspections',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_inspections' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => array(
						'tenant_id'   => array( 'sanitize_callback' => 'absint' ),
						'property_id' => array( 'sanitize_callback' => 'absint' ),
						'type'        => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_inspection' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/inspections/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_inspection' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_inspection' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_inspection' ),
					'permission_callback' => array( $this, 'can_manage_tenants' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/inspections/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_inspection_history' ),
				'permission_callback' => array( $this, 'can_manage_tenants' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tenants/(?P<id>\d+)/inspection-comparison',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_inspection_comparison' ),
				'permission_callback' => array( $this, 'can_manage_tenants' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/documents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_documents' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
					'args'                => array(
						'entity_type' => array( 'sanitize_callback' => 'sanitize_key' ),
						'entity_id'   => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_document' ),
					'permission_callback' => array( $this, 'can_view_lookups' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/documents/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_document' ),
				'permission_callback' => array( $this, 'can_view_lookups' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/expenses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_expenses' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
					'args'                => array(
						'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'category'    => array( 'sanitize_callback' => 'sanitize_key' ),
						'period'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'building_id' => array( 'sanitize_callback' => 'absint' ),
						'paged'       => array( 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_expense' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/expenses/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_expense' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_expense' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/funds/balances',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fund_balances' ),
				'permission_callback' => array( $this, 'can_manage_statements' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/funds/transactions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_fund_transactions' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
					'args'                => array(
						'category' => array( 'sanitize_callback' => 'sanitize_key' ),
						'paged'    => array( 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_fund_transaction' ),
					'permission_callback' => array( $this, 'can_manage_statements' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/funds/transactions/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_fund_transaction' ),
				'permission_callback' => array( $this, 'can_manage_statements' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/accounting/ledger',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_accounting_ledger' ),
				'permission_callback' => array( $this, 'can_manage_statements' ),
				'args'                => array(
					'entry_type' => array( 'sanitize_callback' => 'sanitize_key' ),
					'period'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'search'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'paged'      => array( 'sanitize_callback' => 'absint' ),
					'per_page'   => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/accounting/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_accounting_summary' ),
				'permission_callback' => array( $this, 'can_manage_statements' ),
				'args'                => array(
					'period' => array( 'sanitize_callback' => 'sanitize_text_field' ),
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
	 * Capacité requise pour les routes des sections Édifices et Biens (une
	 * seule et même capacité manage_limpeed_properties couvre les deux, voir
	 * Limpeed_Frontend_Buildings::handle_request() et
	 * Limpeed_Frontend_Properties::handle_request()).
	 *
	 * @return bool
	 */
	public function can_manage_properties() {
		return current_user_can( 'manage_limpeed_properties' );
	}

	/**
	 * Capacité requise pour les routes de la section Paiements.
	 *
	 * @return bool
	 */
	public function can_manage_payments() {
		return current_user_can( 'manage_limpeed_payments' );
	}

	/**
	 * Capacité requise pour les routes des sections Bordereaux/Trésorerie/
	 * Comptabilité (finances de l'agence).
	 *
	 * @return bool
	 */
	public function can_manage_statements() {
		return current_user_can( 'manage_limpeed_statements' );
	}

	/**
	 * Capacité requise pour les routes de consultation partagées (propriétaires,
	 * édifices, biens, locataires) : utilisées à la fois comme cascade de
	 * sélection par les sections Locataires/Paiements et comme listes propres
	 * à leurs sections dédiées.
	 *
	 * @return bool
	 */
	public function can_view_lookups() {
		return current_user_can( 'manage_limpeed_tenants' )
			|| current_user_can( 'manage_limpeed_properties' )
			|| current_user_can( 'manage_limpeed_payments' );
	}

	/**
	 * Capacité requise pour consulter/ajouter/supprimer les documents d'un
	 * type d'entité donné : contrairement à can_view_lookups() (large, utilisé
	 * comme porte d'entrée par le permission_callback des routes /documents),
	 * ce contrôle applique la capacité spécifique au module concerné, pour
	 * qu'un agent Paiements ne puisse pas gérer les documents des Locataires
	 * ou des Propriétaires par exemple.
	 *
	 * @param string $entity_type owner|building|property|tenant
	 * @return bool
	 */
	private function can_manage_document_entity( $entity_type ) {
		switch ( $entity_type ) {
			case 'owner':
				return current_user_can( 'manage_limpeed_owners' );
			case 'building':
			case 'property':
				return current_user_can( 'manage_limpeed_properties' );
			case 'tenant':
				return current_user_can( 'manage_limpeed_tenants' );
			default:
				return false;
		}
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

		// Solde du mois en cours (0 si un paiement "payé" existe déjà) : calculé
		// uniquement à la demande (widget "derniers locataires" du tableau de
		// bord) pour ne pas alourdir la liste complète de la section Locataires.
		if ( $request->get_param( 'with_balance' ) ) {
			$current_period = Limpeed_Payments::get_current_period();
			foreach ( $tenants as $index => $tenant ) {
				$has_paid = Limpeed_Payments::count(
					array(
						'tenant_id' => $tenant->id,
						'period'    => $current_period,
						'status'    => 'paye',
					)
				);
				$balance                       = $has_paid ? 0.0 : (float) $tenant->rent_amount;
				$items[ $index ]['balance']    = $balance;
				$items[ $index ]['balance_formatted'] = Limpeed_Payments::format_amount( $balance );
			}
		}

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

		$result = Limpeed_Tenants::delete( $id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

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
	/**
	 * GET /owners?search=&paged=&per_page= : sert à la fois de cascade légère
	 * (appel sans paramètre, per_page par défaut à 500) et de liste paginée
	 * pour le widget "derniers propriétaires" du tableau de bord.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_owners( WP_REST_Request $request ) {
		$args = array(
			'search'   => (string) $request->get_param( 'search' ),
			'paged'    => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page' => min( 500, max( 1, (int) $request->get_param( 'per_page' ) ?: 500 ) ),
			'orderby'  => 'full_name',
			'order'    => 'ASC',
		);

		$owners = Limpeed_Owners::get_all( $args );
		$total  = Limpeed_Owners::count( $args );

		$items = array_map(
			function ( $owner ) {
				return array(
					'id'        => (int) $owner->id,
					'label'     => $owner->full_name,
					'full_name' => $owner->full_name,
					'phone'     => $owner->phone,
				);
			},
			$owners
		);

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
	 * GET /properties?building_id=&tenant_id=&search=&owner_id=&status=&paged=&per_page= :
	 * sert à la fois de troisième niveau de la cascade Locataires (appel léger
	 * avec building_id + tenant_id) et de liste paginée pour la section Biens
	 * elle-même (avec recherche et filtres). Le per_page par défaut (500)
	 * préserve le comportement de la cascade quand ces paramètres ne sont pas
	 * fournis. Le paramètre tenant_id (optionnel) indique le locataire en
	 * cours de modification : son bien actuel reste sélectionnable même s'il
	 * est occupé.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_properties( WP_REST_Request $request ) {
		$editing_tenant_id = (int) $request->get_param( 'tenant_id' );

		$args = array(
			'search'      => (string) $request->get_param( 'search' ),
			'owner_id'    => (int) $request->get_param( 'owner_id' ),
			'building_id' => (int) $request->get_param( 'building_id' ),
			'status'      => (string) $request->get_param( 'status' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 500, max( 1, (int) $request->get_param( 'per_page' ) ?: 500 ) ),
			'orderby'     => 'address',
			'order'       => 'ASC',
		);

		$properties = Limpeed_Properties::get_all( $args );
		$total      = Limpeed_Properties::count( $args );

		$items = array_map(
			function ( $property ) use ( $editing_tenant_id ) {
				return $this->format_property_row( $property, $editing_tenant_id );
			},
			$properties
		);

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
	 * GET /properties/{id} : fiche complète (onglet Infos du panneau de détail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_property( WP_REST_Request $request ) {
		$property = Limpeed_Properties::get( (int) $request['id'] );
		if ( ! $property ) {
			return new WP_Error( 'limpeed_not_found', __( 'Bien introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_property_detail( $property ) );
	}

	/**
	 * POST /properties : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_property( WP_REST_Request $request ) {
		$data   = $this->extract_property_data( $request );
		$errors = $this->validate_property( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Properties::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer le bien.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_property_detail( Limpeed_Properties::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /properties/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_property( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$property = Limpeed_Properties::get( $id );
		if ( ! $property ) {
			return new WP_Error( 'limpeed_not_found', __( 'Bien introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_property_data( $request );
		$errors = $this->validate_property( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Properties::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour le bien.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_property_detail( Limpeed_Properties::get( $id ) ) );
	}

	/**
	 * DELETE /properties/{id}. Refusé si des locataires y sont encore
	 * rattachés (voir Limpeed_Properties::delete()).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_property( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$property = Limpeed_Properties::get( $id );
		if ( ! $property ) {
			return new WP_Error( 'limpeed_not_found', __( 'Bien introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$result = Limpeed_Properties::delete( $id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /properties/{id}/history : journal d'activité lié à ce bien.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_property_history( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$property = Limpeed_Properties::get( $id );
		if ( ! $property ) {
			return new WP_Error( 'limpeed_not_found', __( 'Bien introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'property',
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
	 * GET /properties/{id}/payments : historique des paiements de ce bien.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_property_payments( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$property = Limpeed_Properties::get( $id );
		if ( ! $property ) {
			return new WP_Error( 'limpeed_not_found', __( 'Bien introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$payments = Limpeed_Payments::get_all(
			array(
				'property_id' => $id,
				'orderby'     => 'period',
				'order'       => 'DESC',
				'per_page'    => 100,
			)
		);

		$statuses = Limpeed_Payments::get_statuses();

		$items = array_map(
			function ( $payment ) use ( $statuses ) {
				return array(
					'id'               => (int) $payment->id,
					'period'           => $payment->period,
					'amount_formatted' => Limpeed_Payments::format_amount( $payment->amount ),
					'status'           => $payment->status,
					'status_label'     => $statuses[ $payment->status ] ?? $payment->status,
				);
			},
			$payments
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * GET /payments?search=&tenant_id=&property_id=&period=&status=&paged=&per_page= :
	 * liste filtrée + paginée.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_payments( WP_REST_Request $request ) {
		$args = array(
			'search'      => (string) $request->get_param( 'search' ),
			'tenant_id'   => (int) $request->get_param( 'tenant_id' ),
			'property_id' => (int) $request->get_param( 'property_id' ),
			'period'      => (string) $request->get_param( 'period' ),
			'status'      => (string) $request->get_param( 'status' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$payments = Limpeed_Payments::get_all( $args );
		$total    = Limpeed_Payments::count( $args );

		$items = array_map( array( $this, 'format_payment_row' ), $payments );

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
	 * GET /payments/{id} : fiche complète (onglet Infos du panneau de détail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment( WP_REST_Request $request ) {
		$payment = Limpeed_Payments::get( (int) $request['id'] );
		if ( ! $payment ) {
			return new WP_Error( 'limpeed_not_found', __( 'Paiement introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_payment_detail( $payment ) );
	}

	/**
	 * POST /payments : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_payment( WP_REST_Request $request ) {
		$data   = $this->extract_payment_data( $request );
		$errors = $this->validate_payment( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Payments::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer le paiement.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_payment_detail( Limpeed_Payments::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /payments/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_payment( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payment = Limpeed_Payments::get( $id );
		if ( ! $payment ) {
			return new WP_Error( 'limpeed_not_found', __( 'Paiement introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_payment_data( $request );
		$errors = $this->validate_payment( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Payments::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour le paiement.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_payment_detail( Limpeed_Payments::get( $id ) ) );
	}

	/**
	 * DELETE /payments/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_payment( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payment = Limpeed_Payments::get( $id );
		if ( ! $payment ) {
			return new WP_Error( 'limpeed_not_found', __( 'Paiement introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Payments::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /payments/{id}/history : journal d'activité lié à ce paiement.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment_history( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payment = Limpeed_Payments::get( $id );
		if ( ! $payment ) {
			return new WP_Error( 'limpeed_not_found', __( 'Paiement introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'payment',
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
	 * GET /dashboard/kpis?expiring_days= : cartes KPI dynamiques du tableau de
	 * bord (taux d'occupation, loyers impayés du mois en cours, baux arrivant
	 * à échéance). Rafraîchi en Ajax par le composant Alpine
	 * public/assets/js/dashboard-kpis-app.js, sans recharger la page.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_dashboard_kpis( WP_REST_Request $request ) {
		$expiring_days = (int) $request->get_param( 'expiring_days' ) ?: 30;

		$properties_total    = Limpeed_Properties::count();
		$properties_occupied = Limpeed_Properties::count( array( 'status' => 'loue' ) );
		$occupancy_rate      = $properties_total > 0 ? round( ( $properties_occupied / $properties_total ) * 100 ) : 0;

		$period_summary = Limpeed_Payments::get_period_summary();
		$unpaid_total   = max( 0, $period_summary['expected_total'] - $period_summary['collected'] );

		$unpaid_tenants = array_map(
			function ( $tenant ) {
				return array(
					'id'              => (int) $tenant->id,
					'full_name'       => $tenant->full_name,
					'rent_formatted'  => Limpeed_Payments::format_amount( $tenant->rent_amount ),
				);
			},
			array_slice( $period_summary['unpaid_tenants'], 0, 10 )
		);

		$expiring_leases = Limpeed_Tenants::get_expiring_leases( $expiring_days );
		$today           = current_time( 'Y-m-d' );

		$expiring_items = array_map(
			function ( $tenant ) use ( $today ) {
				$property   = Limpeed_Properties::get( $tenant->property_id );
				$days_left  = (int) floor( ( strtotime( $tenant->lease_end ) - strtotime( $today ) ) / DAY_IN_SECONDS );
				return array(
					'id'             => (int) $tenant->id,
					'full_name'      => $tenant->full_name,
					'property_label' => $property ? Limpeed_Properties::get_display_label( $property ) : '',
					'lease_end'      => $tenant->lease_end,
					'days_left'      => $days_left,
				);
			},
			$expiring_leases
		);

		return new WP_REST_Response(
			array(
				'occupancy_rate'        => $occupancy_rate,
				'properties_occupied'   => $properties_occupied,
				'properties_total'      => $properties_total,
				'unpaid_count'          => count( $period_summary['unpaid_tenants'] ),
				'unpaid_total_formatted' => Limpeed_Payments::format_amount( $unpaid_total ),
				'unpaid_tenants'        => $unpaid_tenants,
				'expiring_count'        => count( $expiring_items ),
				'expiring_days'         => $expiring_days,
				'expiring_leases'       => $expiring_items,
			)
		);
	}

	/**
	 * GET /dashboard/unpaid-tenants?search=&period=&paged=&per_page= : locataires
	 * actifs sans paiement "payé" sur la période (widget "quittances en attente"
	 * du tableau de bord). Donnée synthétique (pas des lignes wp_limpeed_payments)
	 * qui nécessite donc son propre endpoint plutôt que de réutiliser /payments.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_dashboard_unpaid_tenants( WP_REST_Request $request ) {
		$args = array(
			'search'   => (string) $request->get_param( 'search' ),
			'period'   => (string) $request->get_param( 'period' ),
			'paged'    => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) ),
		);

		$result = Limpeed_Payments::get_unpaid_tenants_paged( $args );

		$items = array_map(
			function ( $tenant ) {
				return array(
					'id'              => (int) $tenant->id,
					'full_name'       => $tenant->full_name,
					'rent_amount'     => (float) $tenant->rent_amount,
					'rent_formatted'  => Limpeed_Payments::format_amount( $tenant->rent_amount ),
				);
			},
			$result['items']
		);

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $result['total'],
				'total_pages' => max( 1, (int) ceil( $result['total'] / $args['per_page'] ) ),
				'paged'       => $args['paged'],
				'period'      => $result['period'],
			)
		);
	}

	/**
	 * Extrait et pré-nettoie les données paiement d'une requête REST, mêmes
	 * champs que Limpeed_Frontend_Payments::handle_request() (le bien est
	 * déterminé automatiquement à partir du locataire sélectionné).
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_payment_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$tenant_id = isset( $params['tenant_id'] ) ? (int) $params['tenant_id'] : 0;
		$tenant    = $tenant_id ? Limpeed_Tenants::get( $tenant_id ) : null;

		return array(
			'tenant_id'      => $tenant_id,
			'property_id'    => $tenant ? (int) $tenant->property_id : 0,
			'amount'         => isset( $params['amount'] ) ? wp_unslash( $params['amount'] ) : '',
			'payment_date'   => isset( $params['payment_date'] ) ? sanitize_text_field( $params['payment_date'] ) : '',
			'period'         => isset( $params['period'] ) ? sanitize_text_field( $params['period'] ) : '',
			'status'         => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
			'payment_method' => isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : '',
		);
	}

	/**
	 * Règles de validation métier, identiques à Limpeed_Frontend_Payments::validate().
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_payment( $data ) {
		$errors = array();

		if ( empty( $data['tenant_id'] ) || ! Limpeed_Tenants::get( $data['tenant_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un locataire valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['period'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $data['period'] ) ) {
			$errors[] = __( 'Veuillez indiquer un mois concerné valide (format AAAA-MM).', 'limpeed-immobilier' );
		}

		if ( '' === $data['amount'] || ! is_numeric( $data['amount'] ) ) {
			$errors[] = __( 'Le montant du paiement doit être un nombre.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Payments::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['payment_method'], Limpeed_Payments::get_payment_methods() ) ) {
			$errors[] = __( 'Le mode de paiement sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * Formate une ligne de la liste (colonnes affichées dans le tableau Paiements).
	 *
	 * @param object $payment
	 * @return array
	 */
	private function format_payment_row( $payment ) {
		$tenant   = Limpeed_Tenants::get( $payment->tenant_id );
		$property = Limpeed_Properties::get( $payment->property_id );
		$agent    = $payment->created_by ? get_userdata( $payment->created_by ) : false;
		$statuses = Limpeed_Payments::get_statuses();
		$methods  = Limpeed_Payments::get_payment_methods();

		return array(
			'id'                    => (int) $payment->id,
			'period'                => $payment->period,
			'tenant_id'             => (int) $payment->tenant_id,
			'tenant_label'          => $tenant ? $tenant->full_name : '',
			'property_id'           => (int) $payment->property_id,
			'property_label'        => $property ? Limpeed_Properties::get_display_label( $property ) : '',
			'amount'                => (float) $payment->amount,
			'amount_formatted'      => Limpeed_Payments::format_amount( $payment->amount ),
			'commission_amount'     => (float) $payment->commission_amount,
			'commission_formatted'  => Limpeed_Payments::format_amount( $payment->commission_amount ),
			'payment_date'          => $payment->payment_date,
			'payment_method'        => $payment->payment_method,
			'payment_method_label'  => $methods[ $payment->payment_method ] ?? $payment->payment_method,
			'status'                => $payment->status,
			'status_label'          => $statuses[ $payment->status ] ?? $payment->status,
			'agent_name'            => $agent ? $agent->display_name : '',
		);
	}

	/**
	 * Formate la fiche complète d'un paiement (onglet Infos du panneau de détail).
	 *
	 * @param object $payment
	 * @return array
	 */
	private function format_payment_detail( $payment ) {
		return $this->format_payment_row( $payment );
	}

	/**
	 * Extrait et pré-nettoie les données bien d'une requête REST, mêmes champs
	 * que Limpeed_Frontend_Properties::handle_request().
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_property_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'building_id'    => isset( $params['building_id'] ) ? (int) $params['building_id'] : 0,
			'reference'      => isset( $params['reference'] ) ? wp_unslash( $params['reference'] ) : '',
			'address'        => isset( $params['address'] ) ? wp_unslash( $params['address'] ) : '',
			'type'           => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '',
			'monthly_rent'   => isset( $params['monthly_rent'] ) ? wp_unslash( $params['monthly_rent'] ) : '',
			'charges'        => isset( $params['charges'] ) ? wp_unslash( $params['charges'] ) : '',
			'deposit_amount' => isset( $params['deposit_amount'] ) ? wp_unslash( $params['deposit_amount'] ) : '',
			'status'         => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
		);
	}

	/**
	 * Règles de validation métier, identiques à Limpeed_Frontend_Properties::validate()
	 * (types valides = types actuels + anciens types conservés pour compatibilité).
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_property( $data ) {
		$errors = array();

		if ( empty( $data['building_id'] ) || ! Limpeed_Buildings::get( $data['building_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un édifice valide.', 'limpeed-immobilier' );
		}

		if ( '' === trim( (string) $data['reference'] ) && '' === trim( (string) $data['address'] ) ) {
			$errors[] = __( 'Indiquez au moins un identifiant ou une adresse pour ce bien.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['type'], Limpeed_Properties::get_types() + Limpeed_Properties::get_legacy_types() ) ) {
			$errors[] = __( 'Le type de bien sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['status'], Limpeed_Properties::get_statuses() ) ) {
			$errors[] = __( 'Le statut sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		foreach ( array( 'monthly_rent', 'charges', 'deposit_amount' ) as $field ) {
			if ( '' !== $data[ $field ] && ! is_numeric( $data[ $field ] ) ) {
				$errors[] = __( 'Les montants (loyer, charges, dépôt) doivent être des nombres.', 'limpeed-immobilier' );
				break;
			}
		}

		return $errors;
	}

	/**
	 * Formate une ligne de la liste (colonnes affichées dans le tableau Biens),
	 * et sert aussi de forme allégée pour la cascade de sélection de la section
	 * Locataires (les champs id/label/building_id/monthly_rent/status/occupied
	 * y suffisent, le reste est ignoré).
	 *
	 * @param object $property
	 * @param int    $editing_tenant_id Id du locataire en cours de modification
	 *                                  côté cascade Locataires (0 sinon) : son
	 *                                  bien actuel reste sélectionnable même occupé.
	 * @return array
	 */
	private function format_property_row( $property, $editing_tenant_id = 0 ) {
		$building       = Limpeed_Buildings::get( $property->building_id );
		$owner          = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$current_tenant = Limpeed_Properties::get_current_tenant( $property->id );
		$statuses       = Limpeed_Properties::get_statuses();

		return array(
			'id'                => (int) $property->id,
			'label'             => Limpeed_Properties::get_display_label( $property ),
			'reference'         => $property->reference,
			'address'           => $property->address,
			'building_id'       => (int) $property->building_id,
			'building_label'    => $building ? $building->name : '',
			'owner_id'          => $building ? (int) $building->owner_id : 0,
			'owner_label'       => $owner ? $owner->full_name : '',
			'type'              => $property->type,
			'type_label'        => Limpeed_Properties::get_type_label( $property->type ),
			'monthly_rent'      => (float) $property->monthly_rent,
			'rent_formatted'    => Limpeed_Payments::format_amount( $property->monthly_rent ),
			'status'            => $property->status,
			'status_label'      => $statuses[ $property->status ] ?? $property->status,
			'tenant_id'         => $current_tenant ? (int) $current_tenant->id : 0,
			'tenant_label'      => $current_tenant ? $current_tenant->full_name : '',
			'occupied'          => $current_tenant && (int) $current_tenant->id !== $editing_tenant_id,
		);
	}

	/**
	 * Formate la fiche complète d'un bien (onglet Infos du panneau de détail).
	 *
	 * @param object $property
	 * @return array
	 */
	private function format_property_detail( $property ) {
		$building       = Limpeed_Buildings::get( $property->building_id );
		$owner          = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$current_tenant = Limpeed_Properties::get_current_tenant( $property->id );
		$statuses       = Limpeed_Properties::get_statuses();

		return array(
			'id'                 => (int) $property->id,
			'building_id'        => (int) $property->building_id,
			'building_label'     => $building ? $building->name : '',
			'owner_id'           => $building ? (int) $building->owner_id : 0,
			'owner_label'        => $owner ? $owner->full_name : '',
			'reference'          => $property->reference,
			'address'            => $property->address,
			'type'               => $property->type,
			'type_label'         => Limpeed_Properties::get_type_label( $property->type ),
			'monthly_rent'       => (float) $property->monthly_rent,
			'rent_formatted'     => Limpeed_Payments::format_amount( $property->monthly_rent ),
			'charges'            => (float) $property->charges,
			'charges_formatted'  => Limpeed_Payments::format_amount( $property->charges ),
			'deposit_amount'     => (float) $property->deposit_amount,
			'deposit_formatted'  => Limpeed_Payments::format_amount( $property->deposit_amount ),
			'status'             => $property->status,
			'status_label'       => $statuses[ $property->status ] ?? $property->status,
			'tenant_id'          => $current_tenant ? (int) $current_tenant->id : 0,
			'tenant_label'       => $current_tenant ? $current_tenant->full_name : '',
		);
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
			'contract_url'       => Limpeed_Frontend::app_url( 'tenants', array( 'action' => 'download_contract', 'id' => $tenant->id, '_wpnonce' => wp_create_nonce( 'limpeed_download_lease_contract_' . $tenant->id ) ) ),
		);
	}

	/**
	 * GET /mandates?search=&building_id=&status=&paged=&per_page= : liste
	 * filtrée + paginée des mandats de gestion.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_mandates( WP_REST_Request $request ) {
		$args = array(
			'search'      => (string) $request->get_param( 'search' ),
			'building_id' => (int) $request->get_param( 'building_id' ),
			'status'      => (string) $request->get_param( 'status' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$mandates = Limpeed_Mandates::get_all( $args );
		$total    = Limpeed_Mandates::count( $args );

		$items = array_map( array( $this, 'format_mandate_row' ), $mandates );

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
	 * GET /mandates/{id} : fiche complète (onglet Infos du panneau de détail).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_mandate( WP_REST_Request $request ) {
		$mandate = Limpeed_Mandates::get( (int) $request['id'] );
		if ( ! $mandate ) {
			return new WP_Error( 'limpeed_not_found', __( 'Mandat introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_mandate_detail( $mandate ) );
	}

	/**
	 * POST /mandates : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_mandate( WP_REST_Request $request ) {
		$data   = $this->extract_mandate_data( $request );
		$errors = $this->validate_mandate( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Mandates::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer le mandat.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_mandate_detail( Limpeed_Mandates::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /mandates/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_mandate( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$mandate = Limpeed_Mandates::get( $id );
		if ( ! $mandate ) {
			return new WP_Error( 'limpeed_not_found', __( 'Mandat introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_mandate_data( $request );
		$errors = $this->validate_mandate( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Mandates::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour le mandat.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_mandate_detail( Limpeed_Mandates::get( $id ) ) );
	}

	/**
	 * DELETE /mandates/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_mandate( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$mandate = Limpeed_Mandates::get( $id );
		if ( ! $mandate ) {
			return new WP_Error( 'limpeed_not_found', __( 'Mandat introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Mandates::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /mandates/{id}/history.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_mandate_history( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$mandate = Limpeed_Mandates::get( $id );
		if ( ! $mandate ) {
			return new WP_Error( 'limpeed_not_found', __( 'Mandat introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'mandate',
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
					'agent_name'   => $user ? $user->display_name : __( 'Agent supprimé', 'limpeed-immobilier' ),
					'created_at'   => date_i18n( 'd/m/Y H:i', strtotime( $entry->created_at ) ),
				);
			},
			$entries
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * Formate une ligne de mandat pour la liste.
	 *
	 * @param object $mandate
	 * @return array
	 */
	private function format_mandate_row( $mandate ) {
		$building = Limpeed_Buildings::get( $mandate->building_id );
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$statuses = Limpeed_Mandates::get_statuses();
		$status   = Limpeed_Mandates::get_display_status( $mandate );

		return array(
			'id'                    => (int) $mandate->id,
			'building_id'           => (int) $mandate->building_id,
			'building_label'        => $building ? $building->name : '',
			'owner_label'           => $owner ? $owner->full_name : '',
			'start_date'            => $mandate->start_date,
			'end_date'              => $mandate->end_date,
			'commission_rate'       => (float) $mandate->commission_rate,
			'commission_rate_label' => number_format_i18n( (float) $mandate->commission_rate, 2 ) . '%',
			'status'                => $status,
			'status_label'          => $statuses[ $status ] ?? $status,
		);
	}

	/**
	 * Formate la fiche complète d'un mandat.
	 *
	 * @param object $mandate
	 * @return array
	 */
	private function format_mandate_detail( $mandate ) {
		$building = Limpeed_Buildings::get( $mandate->building_id );
		$owner    = $building ? Limpeed_Owners::get( $building->owner_id ) : null;
		$statuses = Limpeed_Mandates::get_statuses();
		$status   = Limpeed_Mandates::get_display_status( $mandate );

		return array(
			'id'                    => (int) $mandate->id,
			'building_id'           => (int) $mandate->building_id,
			'building_label'        => $building ? $building->name : '',
			'owner_label'           => $owner ? $owner->full_name : '',
			'start_date'            => $mandate->start_date,
			'end_date'              => $mandate->end_date,
			'commission_rate'       => (float) $mandate->commission_rate,
			'commission_rate_label' => number_format_i18n( (float) $mandate->commission_rate, 2 ) . '%',
			'status'                => $mandate->status,
			'status_label'          => $statuses[ $mandate->status ] ?? $mandate->status,
			'display_status'        => $status,
			'display_status_label'  => $statuses[ $status ] ?? $status,
			'signed_date'           => $mandate->signed_date,
			'notes'                 => $mandate->notes,
			'contract_url'          => $building ? Limpeed_Frontend::app_url( 'mandates', array( 'action' => 'download_contract', 'id' => $mandate->id, '_wpnonce' => wp_create_nonce( 'limpeed_download_mandate_contract_' . $mandate->id ) ) ) : '',
		);
	}

	/**
	 * Extrait et pré-nettoie les données d'un mandat depuis une requête REST.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_mandate_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'building_id'     => isset( $params['building_id'] ) ? (int) $params['building_id'] : 0,
			'start_date'      => isset( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : '',
			'end_date'        => isset( $params['end_date'] ) ? sanitize_text_field( $params['end_date'] ) : '',
			'commission_rate' => isset( $params['commission_rate'] ) ? wp_unslash( $params['commission_rate'] ) : '',
			'status'          => isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'actif',
			'signed_date'     => isset( $params['signed_date'] ) ? sanitize_text_field( $params['signed_date'] ) : '',
			'notes'           => isset( $params['notes'] ) ? wp_unslash( $params['notes'] ) : '',
		);
	}

	/**
	 * Règles de validation métier d'un mandat.
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_mandate( $data ) {
		$errors = array();

		if ( empty( $data['building_id'] ) || ! Limpeed_Buildings::get( $data['building_id'] ) ) {
			$errors[] = __( 'Veuillez sélectionner un édifice valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['start_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['start_date'] ) ) {
			$errors[] = __( 'La date de début est obligatoire et doit être une date valide.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['end_date'] ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['end_date'] ) ) {
			$errors[] = __( 'La date de fin doit être une date valide.', 'limpeed-immobilier' );
		}

		if ( empty( $errors ) && ! empty( $data['end_date'] ) && $data['end_date'] < $data['start_date'] ) {
			$errors[] = __( 'La date de fin doit être postérieure à la date de début.', 'limpeed-immobilier' );
		}

		if ( '' !== $data['commission_rate'] && ( ! is_numeric( $data['commission_rate'] ) || $data['commission_rate'] < 0 || $data['commission_rate'] > 100 ) ) {
			$errors[] = __( 'Le taux de commission doit être un nombre entre 0 et 100.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * GET /tenants/{id}/amendments : liste des avenants au bail.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tenant_amendments( WP_REST_Request $request ) {
		$tenant_id = (int) $request['id'];
		$tenant    = Limpeed_Tenants::get( $tenant_id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$amendments = Limpeed_Lease_Amendments::get_for_tenant( $tenant_id );

		$items = array_map(
			function ( $amendment ) {
				return array(
					'id'                     => (int) $amendment->id,
					'amendment_date'         => $amendment->amendment_date,
					'description'            => $amendment->description,
					'new_rent_amount'        => null !== $amendment->new_rent_amount ? (float) $amendment->new_rent_amount : null,
					'new_rent_formatted'     => null !== $amendment->new_rent_amount ? Limpeed_Payments::format_amount( $amendment->new_rent_amount ) : '',
					'new_lease_end'          => $amendment->new_lease_end,
				);
			},
			$amendments
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * POST /tenants/{id}/amendments : enregistrement d'un avenant.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_tenant_amendment( WP_REST_Request $request ) {
		$tenant_id = (int) $request['id'];
		$tenant    = Limpeed_Tenants::get( $tenant_id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$description = isset( $params['description'] ) ? trim( wp_unslash( $params['description'] ) ) : '';
		if ( '' === $description ) {
			return new WP_Error( 'limpeed_invalid', __( 'La description de l\'avenant est obligatoire.', 'limpeed-immobilier' ), array( 'status' => 400 ) );
		}

		$data = array(
			'tenant_id'       => $tenant_id,
			'amendment_date'  => isset( $params['amendment_date'] ) ? sanitize_text_field( $params['amendment_date'] ) : current_time( 'Y-m-d' ),
			'description'     => $description,
			'new_rent_amount' => isset( $params['new_rent_amount'] ) ? wp_unslash( $params['new_rent_amount'] ) : '',
			'new_lease_end'   => isset( $params['new_lease_end'] ) ? sanitize_text_field( $params['new_lease_end'] ) : '',
		);

		$id = Limpeed_Lease_Amendments::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer l\'avenant.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * DELETE /tenants/{id}/amendments/{amendment_id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_tenant_amendment( WP_REST_Request $request ) {
		$amendment = Limpeed_Lease_Amendments::get( (int) $request['amendment_id'] );
		if ( ! $amendment || (int) $amendment->tenant_id !== (int) $request['id'] ) {
			return new WP_Error( 'limpeed_not_found', __( 'Avenant introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Lease_Amendments::delete( $amendment->id );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * GET /inspections?tenant_id=&property_id=&type=&paged=&per_page= : liste
	 * filtrée + paginée des états des lieux.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_inspections( WP_REST_Request $request ) {
		$args = array(
			'tenant_id'   => (int) $request->get_param( 'tenant_id' ),
			'property_id' => (int) $request->get_param( 'property_id' ),
			'type'        => (string) $request->get_param( 'type' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$inspections = Limpeed_Inspections::get_all( $args );
		$total       = Limpeed_Inspections::count( $args );

		$items = array_map( array( $this, 'format_inspection_row' ), $inspections );

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
	 * GET /inspections/{id} : fiche complète.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_inspection( WP_REST_Request $request ) {
		$inspection = Limpeed_Inspections::get( (int) $request['id'] );
		if ( ! $inspection ) {
			return new WP_Error( 'limpeed_not_found', __( 'État des lieux introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_inspection_detail( $inspection ) );
	}

	/**
	 * POST /inspections : création.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_inspection( WP_REST_Request $request ) {
		$data   = $this->extract_inspection_data( $request );
		$errors = $this->validate_inspection( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Inspections::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer l\'état des lieux.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_inspection_detail( Limpeed_Inspections::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /inspections/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_inspection( WP_REST_Request $request ) {
		$id         = (int) $request['id'];
		$inspection = Limpeed_Inspections::get( $id );
		if ( ! $inspection ) {
			return new WP_Error( 'limpeed_not_found', __( 'État des lieux introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_inspection_data( $request );
		$errors = $this->validate_inspection( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Inspections::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour l\'état des lieux.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_inspection_detail( Limpeed_Inspections::get( $id ) ) );
	}

	/**
	 * DELETE /inspections/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_inspection( WP_REST_Request $request ) {
		$id         = (int) $request['id'];
		$inspection = Limpeed_Inspections::get( $id );
		if ( ! $inspection ) {
			return new WP_Error( 'limpeed_not_found', __( 'État des lieux introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Inspections::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /inspections/{id}/history.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_inspection_history( WP_REST_Request $request ) {
		$id         = (int) $request['id'];
		$inspection = Limpeed_Inspections::get( $id );
		if ( ! $inspection ) {
			return new WP_Error( 'limpeed_not_found', __( 'État des lieux introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$entries = Limpeed_Activity_Log::get_all(
			array(
				'object_type' => 'inspection',
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
					'agent_name'   => $user ? $user->display_name : __( 'Agent supprimé', 'limpeed-immobilier' ),
					'created_at'   => date_i18n( 'd/m/Y H:i', strtotime( $entry->created_at ) ),
				);
			},
			$entries
		);

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * GET /tenants/{id}/inspection-comparison : comparaison pièce par pièce
	 * des états des lieux d'entrée et de sortie les plus récents du locataire.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_inspection_comparison( WP_REST_Request $request ) {
		$tenant_id = (int) $request['id'];
		$tenant    = Limpeed_Tenants::get( $tenant_id );
		if ( ! $tenant ) {
			return new WP_Error( 'limpeed_not_found', __( 'Locataire introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$comparison = Limpeed_Inspections::compare_for_tenant( $tenant_id );

		if ( null === $comparison ) {
			return new WP_REST_Response( array( 'available' => false ) );
		}

		$conditions = Limpeed_Inspections::get_room_conditions();

		$rows = array_map(
			function ( $row ) use ( $conditions ) {
				return array(
					'name'              => $row['name'],
					'entree'            => $row['entree'],
					'entree_label'      => $row['entree'] ? ( $conditions[ $row['entree'] ] ?? $row['entree'] ) : '—',
					'sortie'            => $row['sortie'],
					'sortie_label'      => $row['sortie'] ? ( $conditions[ $row['sortie'] ] ?? $row['sortie'] ) : '—',
					'has_changed'       => $row['has_changed'],
				);
			},
			$comparison['rooms']
		);

		return new WP_REST_Response(
			array(
				'available'   => true,
				'entree_id'   => $comparison['entree_id'],
				'sortie_id'   => $comparison['sortie_id'],
				'entree_date' => $comparison['entree_date'],
				'sortie_date' => $comparison['sortie_date'],
				'rooms'       => $rows,
			)
		);
	}

	/**
	 * Formate une ligne d'état des lieux pour la liste.
	 *
	 * @param object $inspection
	 * @return array
	 */
	private function format_inspection_row( $inspection ) {
		$tenant   = Limpeed_Tenants::get( $inspection->tenant_id );
		$property = Limpeed_Properties::get( $inspection->property_id );
		$types    = Limpeed_Inspections::get_types();

		return array(
			'id'              => (int) $inspection->id,
			'tenant_id'       => (int) $inspection->tenant_id,
			'tenant_label'    => $tenant ? $tenant->full_name : '',
			'property_id'     => (int) $inspection->property_id,
			'property_label'  => $property ? Limpeed_Properties::get_display_label( $property ) : '',
			'type'            => $inspection->type,
			'type_label'      => $types[ $inspection->type ] ?? $inspection->type,
			'inspection_date' => $inspection->inspection_date,
			'rooms_count'     => count( Limpeed_Inspections::get_rooms( $inspection ) ),
		);
	}

	/**
	 * Formate la fiche complète d'un état des lieux.
	 *
	 * @param object $inspection
	 * @return array
	 */
	private function format_inspection_detail( $inspection ) {
		$tenant   = Limpeed_Tenants::get( $inspection->tenant_id );
		$property = Limpeed_Properties::get( $inspection->property_id );
		$types    = Limpeed_Inspections::get_types();

		return array(
			'id'              => (int) $inspection->id,
			'tenant_id'       => (int) $inspection->tenant_id,
			'tenant_label'    => $tenant ? $tenant->full_name : '',
			'property_id'     => (int) $inspection->property_id,
			'property_label'  => $property ? Limpeed_Properties::get_display_label( $property ) : '',
			'type'            => $inspection->type,
			'type_label'      => $types[ $inspection->type ] ?? $inspection->type,
			'inspection_date' => $inspection->inspection_date,
			'rooms'           => Limpeed_Inspections::get_rooms( $inspection ),
			'general_notes'   => $inspection->general_notes,
			'pdf_url'         => Limpeed_Frontend::app_url( 'inspections', array( 'action' => 'download_pdf', 'id' => $inspection->id, '_wpnonce' => wp_create_nonce( 'limpeed_download_inspection_pdf_' . $inspection->id ) ) ),
		);
	}

	/**
	 * Extrait et pré-nettoie les données d'un état des lieux depuis une
	 * requête REST.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_inspection_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'tenant_id'       => isset( $params['tenant_id'] ) ? (int) $params['tenant_id'] : 0,
			'property_id'     => isset( $params['property_id'] ) ? (int) $params['property_id'] : 0,
			'type'            => isset( $params['type'] ) ? sanitize_key( $params['type'] ) : 'entree',
			'inspection_date' => isset( $params['inspection_date'] ) ? sanitize_text_field( $params['inspection_date'] ) : '',
			'rooms'           => isset( $params['rooms'] ) && is_array( $params['rooms'] ) ? $params['rooms'] : array(),
			'general_notes'   => isset( $params['general_notes'] ) ? wp_unslash( $params['general_notes'] ) : '',
		);
	}

	/**
	 * Règles de validation métier d'un état des lieux.
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_inspection( $data ) {
		$errors = array();

		$tenant = ! empty( $data['tenant_id'] ) ? Limpeed_Tenants::get( $data['tenant_id'] ) : null;
		if ( ! $tenant ) {
			$errors[] = __( 'Veuillez sélectionner un locataire valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['property_id'] ) || ! Limpeed_Properties::get( $data['property_id'] ) ) {
			$errors[] = __( 'Bien introuvable pour ce locataire.', 'limpeed-immobilier' );
		}

		if ( empty( $data['inspection_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['inspection_date'] ) ) {
			$errors[] = __( 'La date de l\'état des lieux est obligatoire et doit être une date valide.', 'limpeed-immobilier' );
		}

		if ( ! array_key_exists( $data['type'], Limpeed_Inspections::get_types() ) ) {
			$errors[] = __( 'Le type d\'état des lieux sélectionné n\'est pas valide.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * GET /documents?entity_type=&entity_id= : liste des documents d'une entité.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_documents( WP_REST_Request $request ) {
		$entity_type = (string) $request->get_param( 'entity_type' );
		$entity_id   = (int) $request->get_param( 'entity_id' );

		if ( empty( $entity_type ) || empty( $entity_id ) || ! array_key_exists( $entity_type, Limpeed_Documents::get_entity_types() ) ) {
			return new WP_Error( 'limpeed_invalid', __( 'Type et identifiant d\'entité requis.', 'limpeed-immobilier' ), array( 'status' => 400 ) );
		}

		if ( ! $this->can_manage_document_entity( $entity_type ) ) {
			return new WP_Error( 'limpeed_forbidden', __( "Vous n'avez pas les droits pour consulter les documents de ce module.", 'limpeed-immobilier' ), array( 'status' => 403 ) );
		}

		$documents = Limpeed_Documents::get_for_entity( $entity_type, $entity_id );

		$items = array_map( array( $this, 'format_document_row' ), $documents );

		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * POST /documents (multipart/form-data : entity_type, entity_id, title, file).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_document( WP_REST_Request $request ) {
		$params = $request->get_params();
		$files  = $request->get_file_params();

		$entity_type = isset( $params['entity_type'] ) ? sanitize_key( $params['entity_type'] ) : '';
		$entity_id   = isset( $params['entity_id'] ) ? (int) $params['entity_id'] : 0;
		$title       = isset( $params['title'] ) ? sanitize_text_field( wp_unslash( $params['title'] ) ) : '';

		if ( empty( $entity_type ) || empty( $entity_id ) ) {
			return new WP_Error( 'limpeed_invalid', __( 'Type et identifiant d\'entité requis.', 'limpeed-immobilier' ), array( 'status' => 400 ) );
		}

		if ( ! $this->can_manage_document_entity( $entity_type ) ) {
			return new WP_Error( 'limpeed_forbidden', __( "Vous n'avez pas les droits pour ajouter un document à ce module.", 'limpeed-immobilier' ), array( 'status' => 403 ) );
		}

		$result = Limpeed_Documents::upload( $files['file'] ?? array(), $entity_type, $entity_id, $title );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $this->format_document_row( Limpeed_Documents::get( $result ) ), 201 );
	}

	/**
	 * DELETE /documents/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_document( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$document = Limpeed_Documents::get( $id );
		if ( ! $document ) {
			return new WP_Error( 'limpeed_not_found', __( 'Document introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_manage_document_entity( $document->entity_type ) ) {
			return new WP_Error( 'limpeed_forbidden', __( "Vous n'avez pas les droits pour supprimer les documents de ce module.", 'limpeed-immobilier' ), array( 'status' => 403 ) );
		}

		Limpeed_Documents::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * Formate une ligne de document.
	 *
	 * @param object $document
	 * @return array
	 */
	private function format_document_row( $document ) {
		return array(
			'id'                => (int) $document->id,
			'title'             => $document->title,
			'file_name'         => $document->file_name,
			'file_size'         => (int) $document->file_size,
			'file_size_label'   => size_format( (int) $document->file_size ),
			'mime_type'         => $document->mime_type,
			'created_at'        => date_i18n( 'd/m/Y H:i', strtotime( $document->created_at ) ),
			'download_url'      => Limpeed_Frontend::app_url( 'documents', array( 'action' => 'download', 'id' => $document->id, '_wpnonce' => wp_create_nonce( 'limpeed_download_document_' . $document->id ) ) ),
		);
	}

	/**
	 * GET /expenses?search=&category=&period=&building_id=&paged=&per_page= :
	 * liste filtrée + paginée des charges.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_expenses( WP_REST_Request $request ) {
		$args = array(
			'search'      => (string) $request->get_param( 'search' ),
			'category'    => (string) $request->get_param( 'category' ),
			'period'      => (string) $request->get_param( 'period' ),
			'building_id' => (int) $request->get_param( 'building_id' ),
			'paged'       => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'    => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$expenses = Limpeed_Expenses::get_all( $args );
		$total    = Limpeed_Expenses::count( $args );

		$items = array_map( array( $this, 'format_expense_row' ), $expenses );

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
	 * POST /expenses : création d'une charge.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_expense( WP_REST_Request $request ) {
		$data   = $this->extract_expense_data( $request );
		$errors = $this->validate_expense( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Expenses::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible d\'enregistrer la charge.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_expense_row( Limpeed_Expenses::get( $id ) ), 201 );
	}

	/**
	 * PUT/PATCH /expenses/{id} : modification.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_expense( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$expense = Limpeed_Expenses::get( $id );
		if ( ! $expense ) {
			return new WP_Error( 'limpeed_not_found', __( 'Charge introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		$data   = $this->extract_expense_data( $request );
		$errors = $this->validate_expense( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$result = Limpeed_Expenses::update( $id, $data );
		if ( ! $result ) {
			return new WP_Error( 'limpeed_save_failed', __( 'Impossible de mettre à jour la charge.', 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_expense_row( Limpeed_Expenses::get( $id ) ) );
	}

	/**
	 * DELETE /expenses/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_expense( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$expense = Limpeed_Expenses::get( $id );
		if ( ! $expense ) {
			return new WP_Error( 'limpeed_not_found', __( 'Charge introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Expenses::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * Formate une ligne de charge pour la liste.
	 *
	 * @param object $expense
	 * @return array
	 */
	private function format_expense_row( $expense ) {
		$categories = Limpeed_Expenses::get_categories();
		$building   = $expense->building_id ? Limpeed_Buildings::get( $expense->building_id ) : null;

		return array(
			'id'             => (int) $expense->id,
			'expense_date'   => $expense->expense_date,
			'category'       => $expense->category,
			'category_label' => $categories[ $expense->category ] ?? $expense->category,
			'label'          => $expense->label,
			'amount'         => (float) $expense->amount,
			'amount_label'   => Limpeed_Payments::format_amount( (float) $expense->amount ),
			'building_id'    => $expense->building_id ? (int) $expense->building_id : 0,
			'building_label' => $building ? $building->name : '',
			'notes'          => $expense->notes,
		);
	}

	/**
	 * Extrait et pré-nettoie les données d'une charge depuis une requête REST.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_expense_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'expense_date' => isset( $params['expense_date'] ) ? sanitize_text_field( $params['expense_date'] ) : '',
			'category'     => isset( $params['category'] ) ? sanitize_key( $params['category'] ) : 'autre',
			'label'        => isset( $params['label'] ) ? sanitize_text_field( wp_unslash( $params['label'] ) ) : '',
			'amount'       => isset( $params['amount'] ) ? wp_unslash( $params['amount'] ) : '',
			'building_id'  => isset( $params['building_id'] ) ? (int) $params['building_id'] : 0,
			'property_id'  => isset( $params['property_id'] ) ? (int) $params['property_id'] : 0,
			'notes'        => isset( $params['notes'] ) ? wp_unslash( $params['notes'] ) : '',
		);
	}

	/**
	 * Règles de validation métier d'une charge.
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_expense( $data ) {
		$errors = array();

		if ( empty( $data['expense_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['expense_date'] ) ) {
			$errors[] = __( 'La date de la charge est obligatoire et doit être une date valide.', 'limpeed-immobilier' );
		}

		if ( empty( $data['label'] ) ) {
			$errors[] = __( 'Le libellé de la charge est obligatoire.', 'limpeed-immobilier' );
		}

		if ( '' === $data['amount'] || ! is_numeric( $data['amount'] ) || $data['amount'] < 0 ) {
			$errors[] = __( 'Le montant doit être un nombre positif.', 'limpeed-immobilier' );
		}

		if ( ! empty( $data['building_id'] ) && ! Limpeed_Buildings::get( $data['building_id'] ) ) {
			$errors[] = __( 'Édifice sélectionné invalide.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * GET /funds/balances : solde de chaque caisse + solde global.
	 *
	 * @return WP_REST_Response
	 */
	public function get_fund_balances() {
		$categories = Limpeed_Funds::get_categories();
		$data       = Limpeed_Funds::get_all_balances();

		$items = array();
		foreach ( $categories as $key => $category ) {
			$balance   = $data['balances'][ $key ];
			$items[]   = array(
				'key'              => $key,
				'label'            => $category['label'],
				'color'            => $category['color'],
				'mode'             => $category['mode'] ?? 'manual',
				'auto_description' => $category['auto_description'] ?? '',
				'balance'          => $balance,
				'balance_label'    => Limpeed_Payments::format_amount( $balance ),
			);
		}

		return new WP_REST_Response(
			array(
				'items'        => $items,
				'total'        => $data['total'],
				'total_label'  => Limpeed_Payments::format_amount( $data['total'] ),
			)
		);
	}

	/**
	 * GET /funds/transactions?category=&paged=&per_page= : liste paginée des
	 * mouvements d'une caisse (ou de toutes si category est vide).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_fund_transactions( WP_REST_Request $request ) {
		$args = array(
			'category' => (string) $request->get_param( 'category' ),
			'paged'    => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$transactions = Limpeed_Funds::get_all( $args );
		$total        = Limpeed_Funds::count( $args );

		$items = array_map( array( $this, 'format_fund_transaction_row' ), $transactions );

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
	 * POST /funds/transactions : enregistre un mouvement de caisse (entrée/sortie).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_fund_transaction( WP_REST_Request $request ) {
		$data   = $this->extract_fund_transaction_data( $request );
		$errors = $this->validate_fund_transaction( $data );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'limpeed_invalid', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		$id = Limpeed_Funds::insert( $data );
		if ( ! $id ) {
			return new WP_Error( 'limpeed_save_failed', __( "Impossible d'enregistrer le mouvement de caisse.", 'limpeed-immobilier' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $this->format_fund_transaction_row( Limpeed_Funds::get( $id ) ), 201 );
	}

	/**
	 * DELETE /funds/transactions/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_fund_transaction( WP_REST_Request $request ) {
		$id          = (int) $request['id'];
		$transaction = Limpeed_Funds::get( $id );
		if ( ! $transaction ) {
			return new WP_Error( 'limpeed_not_found', __( 'Mouvement de caisse introuvable.', 'limpeed-immobilier' ), array( 'status' => 404 ) );
		}

		Limpeed_Funds::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * Formate un mouvement de caisse pour la liste.
	 *
	 * @param object $transaction
	 * @return array
	 */
	private function format_fund_transaction_row( $transaction ) {
		$categories = Limpeed_Funds::get_categories();
		$category   = $categories[ $transaction->fund_category ] ?? array( 'label' => $transaction->fund_category, 'color' => 'blue' );

		return array(
			'id'               => (int) $transaction->id,
			'fund_category'    => $transaction->fund_category,
			'fund_label'       => $category['label'],
			'direction'        => $transaction->direction,
			'amount'           => (float) $transaction->amount,
			'amount_label'     => Limpeed_Payments::format_amount( (float) $transaction->amount ),
			'label'            => $transaction->label,
			'transaction_date' => $transaction->transaction_date,
			'created_at'       => date_i18n( 'd/m/Y H:i', strtotime( $transaction->created_at ) ),
		);
	}

	/**
	 * Extrait et pré-nettoie les données d'un mouvement de caisse depuis une requête REST.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function extract_fund_transaction_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		return array(
			'fund_category'    => isset( $params['fund_category'] ) ? sanitize_key( $params['fund_category'] ) : '',
			'direction'        => isset( $params['direction'] ) ? sanitize_key( $params['direction'] ) : 'in',
			'amount'           => isset( $params['amount'] ) ? wp_unslash( $params['amount'] ) : '',
			'label'            => isset( $params['label'] ) ? sanitize_text_field( wp_unslash( $params['label'] ) ) : '',
			'transaction_date' => isset( $params['transaction_date'] ) ? sanitize_text_field( $params['transaction_date'] ) : '',
		);
	}

	/**
	 * Règles de validation métier d'un mouvement de caisse.
	 *
	 * @param array $data
	 * @return array Liste de messages d'erreur (vide si valide).
	 */
	private function validate_fund_transaction( $data ) {
		$errors     = array();
		$categories = Limpeed_Funds::get_categories();

		if ( ! array_key_exists( $data['fund_category'], $categories ) ) {
			$errors[] = __( 'Caisse sélectionnée invalide.', 'limpeed-immobilier' );
		} elseif ( 'auto' === ( $categories[ $data['fund_category'] ]['mode'] ?? 'manual' ) ) {
			$errors[] = __( 'Cette caisse est calculée automatiquement et ne peut pas recevoir de mouvement manuel.', 'limpeed-immobilier' );
		}

		if ( ! in_array( $data['direction'], array( 'in', 'out' ), true ) ) {
			$errors[] = __( 'Le sens du mouvement doit être une entrée ou une sortie.', 'limpeed-immobilier' );
		}

		if ( empty( $data['transaction_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['transaction_date'] ) ) {
			$errors[] = __( 'La date du mouvement est obligatoire et doit être une date valide.', 'limpeed-immobilier' );
		}

		if ( '' === $data['amount'] || ! is_numeric( $data['amount'] ) || $data['amount'] <= 0 ) {
			$errors[] = __( 'Le montant doit être un nombre positif.', 'limpeed-immobilier' );
		}

		return $errors;
	}

	/**
	 * GET /accounting/ledger?entry_type=&period=&search=&paged=&per_page= :
	 * grand livre consolidé (encaissements, reversements, charges).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_accounting_ledger( WP_REST_Request $request ) {
		$args = array(
			'entry_type' => (string) $request->get_param( 'entry_type' ),
			'period'     => (string) $request->get_param( 'period' ),
			'search'     => (string) $request->get_param( 'search' ),
			'paged'      => max( 1, (int) $request->get_param( 'paged' ) ?: 1 ),
			'per_page'   => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);

		$entries = Limpeed_Accounting::get_ledger( $args );
		$total   = Limpeed_Accounting::count_ledger( $args );

		$entry_types = Limpeed_Accounting::get_entry_types();

		$items = array_map(
			function ( $entry ) use ( $entry_types ) {
				return array(
					'entry_date'     => $entry->entry_date,
					'entry_type'     => $entry->entry_type,
					'entry_type_label' => $entry_types[ $entry->entry_type ] ?? $entry->entry_type,
					'label'          => $entry->label,
					'amount'         => (float) $entry->amount,
					'amount_label'   => Limpeed_Payments::format_amount( (float) $entry->amount ),
					'reference_id'   => (int) $entry->reference_id,
				);
			},
			$entries
		);

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
	 * GET /accounting/summary?period= : bilan simplifié (produits, charges,
	 * résultat) d'une période. La période par défaut est le mois en cours.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_accounting_summary( WP_REST_Request $request ) {
		$period = (string) $request->get_param( 'period' );
		if ( empty( $period ) || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
			$period = current_time( 'Y-m' );
		}

		$summary = Limpeed_Accounting::get_period_summary( $period );

		return new WP_REST_Response(
			array(
				'period'         => $summary['period'],
				'revenue'        => $summary['revenue'],
				'revenue_label'  => Limpeed_Payments::format_amount( $summary['revenue'] ),
				'expenses'       => $summary['expenses'],
				'expenses_label' => Limpeed_Payments::format_amount( $summary['expenses'] ),
				'result'         => $summary['result'],
				'result_label'   => Limpeed_Payments::format_amount( $summary['result'] ),
			)
		);
	}
}
