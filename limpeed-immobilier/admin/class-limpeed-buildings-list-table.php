<?php
/**
 * Tableau de listing des édifices (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Buildings_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'building',
				'plural'   => 'buildings',
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
			'name'       => __( 'Édifice', 'limpeed-immobilier' ),
			'owner'      => __( 'Propriétaire', 'limpeed-immobilier' ),
			'address'    => __( 'Adresse', 'limpeed-immobilier' ),
			'properties' => __( 'Sous-édifices', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name' => array( 'name', false ),
		);
	}

	/**
	 * Colonne principale avec les actions (modifier / supprimer).
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'limpeed-buildings',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-buildings',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_building_' . $item->id
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
			esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Affichage des autres colonnes.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'owner':
				$owner = Limpeed_Owners::get( $item->owner_id );
				if ( ! $owner ) {
					return '&mdash;';
				}
				$url = add_query_arg(
					array(
						'page'   => 'limpeed-owners',
						'action' => 'edit',
						'id'     => $owner->id,
					),
					admin_url( 'admin.php' )
				);
				return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $owner->full_name ) );

			case 'address':
				return $item->address ? esc_html( $item->address ) : '&mdash;';

			case 'properties':
				$count = Limpeed_Properties::count( array( 'building_id' => $item->id ) );
				if ( $count > 0 ) {
					$url = add_query_arg(
						array(
							'page'        => 'limpeed-properties',
							'building_id' => $item->id,
						),
						admin_url( 'admin.php' )
					);
					return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $count ) );
				}
				return '0';

			default:
				return '';
		}
	}

	/**
	 * Filtre par propriétaire.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$owners         = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
		$selected_owner = isset( $_REQUEST['owner_id'] ) ? (int) $_REQUEST['owner_id'] : 0;
		?>
		<div class="alignleft actions">
			<select name="owner_id">
				<option value=""><?php esc_html_e( 'Tous les propriétaires', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( $owners as $owner ) : ?>
					<option value="<?php echo esc_attr( $owner->id ); ?>" <?php selected( $selected_owner, $owner->id ); ?>>
						<?php echo esc_html( $owner->full_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filtrer', 'limpeed-immobilier' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prépare les éléments à afficher.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$owner_id = isset( $_REQUEST['owner_id'] ) ? (int) $_REQUEST['owner_id'] : 0;
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'name';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC';
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'search'   => $search,
			'owner_id' => $owner_id,
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $this->per_page,
			'paged'    => $paged,
		);

		$total_items = Limpeed_Buildings::count( $args );

		$this->items = Limpeed_Buildings::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
