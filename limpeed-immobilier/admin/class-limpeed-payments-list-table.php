<?php
/**
 * Tableau de listing des paiements (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Payments_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'payment',
				'plural'   => 'payments',
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
			'period'             => __( 'Mois concerné', 'limpeed-immobilier' ),
			'tenant'             => __( 'Locataire', 'limpeed-immobilier' ),
			'property'           => __( 'Bien', 'limpeed-immobilier' ),
			'amount'             => __( 'Montant', 'limpeed-immobilier' ),
			'payment_date'       => __( 'Date de paiement', 'limpeed-immobilier' ),
			'payment_method'     => __( 'Mode de paiement', 'limpeed-immobilier' ),
			'commission_amount'  => __( 'Commission', 'limpeed-immobilier' ),
			'status'             => __( 'Statut', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'period'       => array( 'period', true ),
			'amount'       => array( 'amount', false ),
			'payment_date' => array( 'payment_date', false ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Colonne principale avec les actions (modifier / supprimer).
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_period( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'limpeed-payments',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-payments',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_payment_' . $item->id
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
			esc_html( $item->period ),
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
			case 'tenant':
				$tenant = Limpeed_Tenants::get( $item->tenant_id );
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
				return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( Limpeed_Properties::get_display_label( $property ) ) );

			case 'amount':
			case 'commission_amount':
				return esc_html( number_format_i18n( (float) $item->$column_name, 2 ) );

			case 'payment_date':
				return $item->payment_date ? esc_html( mysql2date( get_option( 'date_format' ), $item->payment_date ) ) : '&mdash;';

			case 'payment_method':
				$methods = Limpeed_Payments::get_payment_methods();
				return isset( $methods[ $item->payment_method ] ) ? esc_html( $methods[ $item->payment_method ] ) : esc_html( $item->payment_method );

			case 'status':
				$statuses = Limpeed_Payments::get_statuses();
				$label    = isset( $statuses[ $item->status ] ) ? $statuses[ $item->status ] : $item->status;
				return sprintf( '<span class="limpeed-badge limpeed-badge-%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );

			default:
				return '';
		}
	}

	/**
	 * Filtres additionnels au-dessus du tableau (locataire, bien, mois, statut).
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$properties         = Limpeed_Properties::get_all( array( 'per_page' => 9999 ) );
		$selected_property  = isset( $_REQUEST['property_id'] ) ? (int) $_REQUEST['property_id'] : 0;
		$selected_status    = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$selected_period    = isset( $_REQUEST['period'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['period'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="property_id">
				<option value=""><?php esc_html_e( 'Tous les biens', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( $properties as $property ) : ?>
					<option value="<?php echo esc_attr( $property->id ); ?>" <?php selected( $selected_property, $property->id ); ?>>
						<?php echo esc_html( Limpeed_Properties::get_display_label( $property ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value=""><?php esc_html_e( 'Tous les statuts', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( Limpeed_Payments::get_statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_status, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<input type="month" name="period" value="<?php echo esc_attr( $selected_period ); ?>" placeholder="<?php esc_attr_e( 'Mois', 'limpeed-immobilier' ); ?>">
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

		$tenant_id   = isset( $_REQUEST['tenant_id'] ) ? (int) $_REQUEST['tenant_id'] : 0;
		$property_id = isset( $_REQUEST['property_id'] ) ? (int) $_REQUEST['property_id'] : 0;
		$period      = isset( $_REQUEST['period'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['period'] ) ) : '';
		$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$orderby     = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'payment_date';
		$order       = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$paged       = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'tenant_id'   => $tenant_id,
			'property_id' => $property_id,
			'period'      => $period,
			'status'      => $status,
			'orderby'     => $orderby,
			'order'       => $order,
			'per_page'    => $this->per_page,
			'paged'       => $paged,
		);

		$total_items = Limpeed_Payments::count( $args );

		$this->items = Limpeed_Payments::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
