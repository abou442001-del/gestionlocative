<?php
/**
 * Tableau de listing des locataires (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Tenants_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'tenant',
				'plural'   => 'tenants',
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
			'property'     => __( 'Bien loué', 'limpeed-immobilier' ),
			'phone'        => __( 'Téléphone', 'limpeed-immobilier' ),
			'email'        => __( 'Email', 'limpeed-immobilier' ),
			'lease_start'  => __( 'Début bail', 'limpeed-immobilier' ),
			'lease_end'    => __( 'Fin bail', 'limpeed-immobilier' ),
			'rent_amount'  => __( 'Loyer', 'limpeed-immobilier' ),
			'deposit_paid' => __( 'Dépôt versé', 'limpeed-immobilier' ),
			'status'       => __( 'Statut', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'full_name'   => array( 'full_name', false ),
			'lease_start' => array( 'lease_start', false ),
			'lease_end'   => array( 'lease_end', false ),
			'status'      => array( 'status', false ),
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
				'page'   => 'limpeed-tenants',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-tenants',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_tenant_' . $item->id
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
	 * Affichage des autres colonnes.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'property':
				$property = Limpeed_Properties::get( $item->property_id );
				if ( ! $property ) {
					return '&mdash;';
				}
				$url = add_query_arg(
					array(
						'page'   => 'limpeed-properties',
						'action' => 'edit',
						'id'     => $property->id,
					),
					admin_url( 'admin.php' )
				);
				return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $property->address ) );

			case 'phone':
			case 'email':
				return $item->$column_name ? esc_html( $item->$column_name ) : '&mdash;';

			case 'lease_start':
			case 'lease_end':
				return $item->$column_name ? esc_html( mysql2date( get_option( 'date_format' ), $item->$column_name ) ) : '&mdash;';

			case 'rent_amount':
			case 'deposit_paid':
				return esc_html( number_format_i18n( (float) $item->$column_name, 2 ) );

			case 'status':
				$statuses = Limpeed_Tenants::get_statuses();
				$label    = isset( $statuses[ $item->status ] ) ? $statuses[ $item->status ] : $item->status;
				return sprintf( '<span class="limpeed-badge limpeed-badge-%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );

			default:
				return '';
		}
	}

	/**
	 * Filtres additionnels au-dessus du tableau (bien, statut).
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$properties        = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );
		$selected_property = isset( $_REQUEST['property_id'] ) ? (int) $_REQUEST['property_id'] : 0;
		$selected_status   = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="property_id">
				<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( $properties as $property ) : ?>
					<option value="<?php echo esc_attr( $property->id ); ?>" <?php selected( $selected_property, $property->id ); ?>>
						<?php echo esc_html( $property->address ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( Limpeed_Tenants::get_statuses() as $key => $label ) : ?>
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
		$property_id = isset( $_REQUEST['property_id'] ) ? (int) $_REQUEST['property_id'] : 0;
		$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$orderby     = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'full_name';
		$order       = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC';
		$paged       = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'search'      => $search,
			'property_id' => $property_id,
			'status'      => $status,
			'orderby'     => $orderby,
			'order'       => $order,
			'per_page'    => $this->per_page,
			'paged'       => $paged,
		);

		$total_items = Limpeed_Tenants::count( $args );

		$this->items = Limpeed_Tenants::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
