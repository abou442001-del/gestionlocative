<?php
/**
 * Tableau de listing des agents (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Agents_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'agent',
				'plural'   => 'agents',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Colonnes du tableau.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'display_name' => __( 'Nom', 'limpeed-immobilier' ),
			'user_login'   => __( 'Identifiant', 'limpeed-immobilier' ),
			'user_email'   => __( 'Email', 'limpeed-immobilier' ),
			'role'         => __( 'Rôle', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonne principale avec les actions.
	 *
	 * @param WP_User $item
	 * @return string
	 */
	public function column_display_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'limpeed-agents',
				'action' => 'edit',
				'id'     => $item->ID,
			),
			admin_url( 'admin.php' )
		);

		$revoke_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-agents',
					'action' => 'revoke',
					'id'     => $item->ID,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_revoke_agent_' . $item->ID
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Modifier le rôle', 'limpeed-immobilier' ) ),
			'revoke' => sprintf(
				'<a href="%s" class="limpeed-confirm-delete">%s</a>',
				esc_url( $revoke_url ),
				esc_html__( 'Révoquer l\'accès', 'limpeed-immobilier' )
			),
		);

		if ( get_current_user_id() === $item->ID ) {
			unset( $actions['revoke'], $actions['edit'] );
		}

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->display_name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Affichage des autres colonnes.
	 *
	 * @param WP_User $item
	 * @param string  $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'user_login':
				return esc_html( $item->user_login );
			case 'user_email':
				return esc_html( $item->user_email );
			case 'role':
				$roles      = Limpeed_Agents::get_available_roles();
				$user_roles = array_intersect( $item->roles, array_keys( $roles ) );
				$labels     = array_map(
					function ( $role ) use ( $roles ) {
						return $roles[ $role ];
					},
					$user_roles
				);
				return esc_html( implode( ', ', $labels ) );
			default:
				return '';
		}
	}

	/**
	 * Prépare les éléments à afficher.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$paged  = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'search'   => $search,
			'per_page' => $this->per_page,
			'paged'    => $paged,
		);

		$total_items = Limpeed_Agents::count( $args );

		$this->items = Limpeed_Agents::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
