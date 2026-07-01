<?php
/**
 * Tableau de listing des propriétaires (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Owners_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'owner',
				'plural'   => 'owners',
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
			'full_name'    => __( 'Nom complet', 'limpeed-immobilier' ),
			'phone'        => __( 'Téléphone', 'limpeed-immobilier' ),
			'email'        => __( 'Email', 'limpeed-immobilier' ),
			'address'      => __( 'Adresse', 'limpeed-immobilier' ),
			'bank_details' => __( 'Coordonnées bancaires', 'limpeed-immobilier' ),
			'properties'   => __( 'Biens', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'full_name' => array( 'full_name', false ),
			'phone'     => array( 'phone', false ),
			'email'     => array( 'email', false ),
		);
	}

	/**
	 * Colonne principale avec les actions (modifier / supprimer).
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_full_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'limpeed-owners',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-owners',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_owner_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Modifier', 'limpeed-immobilier' ) ),
			'delete' => sprintf(
				'<a href="%s" class="limpeed-confirm-delete">%s</a>',
				esc_url( $delete_url ),
				esc_html__( 'Supprimer', 'limpeed-immobilier' )
			),
		);

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->full_name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Affichage par défaut des autres colonnes.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'properties':
				$count = Limpeed_Properties::count( array( 'owner_id' => $item->id ) );
				if ( $count > 0 ) {
					$url = add_query_arg(
						array(
							'page'     => 'limpeed-properties',
							'owner_id' => $item->id,
						),
						admin_url( 'admin.php' )
					);
					return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $count ) );
				}
				return '0';
			case 'phone':
			case 'email':
			case 'address':
			case 'bank_details':
				return $item->$column_name ? esc_html( $item->$column_name ) : '&mdash;';
			default:
				return '';
		}
	}

	/**
	 * Prépare les éléments à afficher (recherche, tri, pagination).
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'full_name';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC';
		$paged   = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'search'   => $search,
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $this->per_page,
			'paged'    => $paged,
		);

		$total_items = Limpeed_Owners::count( $args );

		$this->items = Limpeed_Owners::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
