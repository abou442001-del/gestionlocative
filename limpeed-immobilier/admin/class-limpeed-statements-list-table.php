<?php
/**
 * Tableau de listing des bordereaux générés (WP_List_Table).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Statements_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'statement',
				'plural'   => 'statements',
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
			'period'            => __( 'Période', 'limpeed-immobilier' ),
			'owner'             => __( 'Propriétaire', 'limpeed-immobilier' ),
			'total_collected'   => __( 'Loyers encaissés', 'limpeed-immobilier' ),
			'total_commission'  => __( 'Commission', 'limpeed-immobilier' ),
			'net_amount'        => __( 'Net reversé', 'limpeed-immobilier' ),
			'created_at'        => __( 'Généré le', 'limpeed-immobilier' ),
			'generated_by'      => __( 'Généré par', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Colonne principale avec les actions (télécharger / supprimer).
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_period( $item ) {
		$download_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-statements',
					'action' => 'download',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_download_statement_' . $item->id
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'limpeed-statements',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'limpeed_delete_statement_' . $item->id
		);

		$label = ( $item->period_start === $item->period_end ) ? $item->period_start : sprintf( '%s — %s', $item->period_start, $item->period_end );

		$actions = array(
			'download' => sprintf( '<a href="%s">%s</a>', esc_url( $download_url ), esc_html__( 'Télécharger', 'limpeed-immobilier' ) ),
			'delete'   => sprintf(
				'<a href="%s" class="limpeed-confirm-delete">%s</a>',
				esc_url( $delete_url ),
				esc_html__( 'Supprimer', 'limpeed-immobilier' )
			),
		);

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $download_url ),
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

			case 'total_collected':
			case 'total_commission':
			case 'net_amount':
				return esc_html( number_format_i18n( (float) $item->$column_name, 2 ) );

			case 'created_at':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );

			case 'generated_by':
				$user = $item->generated_by ? get_userdata( $item->generated_by ) : false;
				return $user ? esc_html( $user->display_name ) : '&mdash;';

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
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$owner_id = isset( $_REQUEST['owner_id'] ) ? (int) $_REQUEST['owner_id'] : 0;
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'owner_id' => $owner_id,
			'per_page' => $this->per_page,
			'paged'    => $paged,
		);

		$total_items = Limpeed_Statements::count( $args );

		$this->items = Limpeed_Statements::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
