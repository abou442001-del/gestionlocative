<?php
/**
 * Tableau de listing des biens (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Properties_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'property',
				'plural'   => 'properties',
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
			'reference'       => __( 'Identifiant', 'limpeed-immobilier' ),
			'address'         => __( 'Adresse (sous-édifice)', 'limpeed-immobilier' ),
			'building'        => __( 'Édifice', 'limpeed-immobilier' ),
			'owner'           => __( 'Propriétaire', 'limpeed-immobilier' ),
			'type'            => __( 'Type', 'limpeed-immobilier' ),
			'monthly_rent'    => __( 'Loyer', 'limpeed-immobilier' ),
			'charges'         => __( 'Charges', 'limpeed-immobilier' ),
			'deposit_amount'  => __( 'Dépôt', 'limpeed-immobilier' ),
			'status'          => __( 'Statut', 'limpeed-immobilier' ),
			'current_tenant'  => __( 'Locataire actuel', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'reference'    => array( 'reference', false ),
			'address'      => array( 'address', false ),
			'type'         => array( 'type', false ),
			'monthly_rent' => array( 'monthly_rent', false ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Colonne principale avec les actions (modifier / supprimer).
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_reference( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'limpeed-properties',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-properties',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_property_' . $item->id
		);

		$label = $item->reference ? $item->reference : $item->address;

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
			esc_html( $label ),
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
			case 'address':
				return esc_html( $item->address );

			case 'building':
				$building = Limpeed_Buildings::get( $item->building_id );
				if ( ! $building ) {
					return '&mdash;';
				}
				$url = add_query_arg(
					array(
						'page'   => 'limpeed-buildings',
						'action' => 'edit',
						'id'     => $building->id,
					),
					admin_url( 'admin.php' )
				);
				return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $building->name ) );

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

			case 'type':
				return esc_html( Limpeed_Properties::get_type_label( $item->type ) );

			case 'monthly_rent':
			case 'charges':
			case 'deposit_amount':
				return esc_html( Limpeed_Payments::format_amount( $item->$column_name ) );

			case 'status':
				$statuses = Limpeed_Properties::get_statuses();
				$label    = isset( $statuses[ $item->status ] ) ? $statuses[ $item->status ] : $item->status;
				return sprintf( '<span class="limpeed-badge limpeed-badge-%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );

			case 'current_tenant':
				$tenant = Limpeed_Properties::get_current_tenant( $item->id );
				if ( ! $tenant ) {
					return '&mdash;';
				}
				$url = add_query_arg(
					array(
						'page'   => 'limpeed-tenants',
						'action' => 'edit',
						'id'     => $tenant->id,
					),
					admin_url( 'admin.php' )
				);
				return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $tenant->full_name ) );

			default:
				return '';
		}
	}

	/**
	 * Filtres additionnels au-dessus du tableau (propriétaire, statut).
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$owners           = Limpeed_Owners::get_all( array( 'per_page' => 9999 ) );
		$buildings        = Limpeed_Buildings::get_all( array( 'per_page' => 9999 ) );
		$selected_owner   = isset( $_REQUEST['owner_id'] ) ? (int) $_REQUEST['owner_id'] : 0;
		$selected_building = isset( $_REQUEST['building_id'] ) ? (int) $_REQUEST['building_id'] : 0;
		$selected_status  = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
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
			<select name="building_id">
				<option value=""><?php esc_html_e( 'Tous les édifices', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( $buildings as $building ) : ?>
					<option value="<?php echo esc_attr( $building->id ); ?>" <?php selected( $selected_building, $building->id ); ?>>
						<?php echo esc_html( $building->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( Limpeed_Properties::get_statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_status, $key ); ?>>
						<?php echo esc_html( $label ); ?>
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

		$search      = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$owner_id    = isset( $_REQUEST['owner_id'] ) ? (int) $_REQUEST['owner_id'] : 0;
		$building_id = isset( $_REQUEST['building_id'] ) ? (int) $_REQUEST['building_id'] : 0;
		$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$orderby     = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'address';
		$order       = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC';
		$paged       = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'search'      => $search,
			'owner_id'    => $owner_id,
			'building_id' => $building_id,
			'status'      => $status,
			'orderby'     => $orderby,
			'order'       => $order,
			'per_page'    => $this->per_page,
			'paged'       => $paged,
		);

		$total_items = Limpeed_Properties::count( $args );

		$this->items = Limpeed_Properties::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
