<?php
/**
 * Tableau de listing du journal d'activité (WP_List_Table, lecture seule).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Limpeed_Activity_Log_List_Table extends WP_List_Table {

	/**
	 * Nombre d'éléments par page.
	 *
	 * @var int
	 */
	private $per_page = 30;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log_entry',
				'plural'   => 'log_entries',
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
			'created_at'  => __( 'Date', 'limpeed-immobilier' ),
			'user'        => __( 'Agent', 'limpeed-immobilier' ),
			'action'      => __( 'Action', 'limpeed-immobilier' ),
			'object_type' => __( 'Élément', 'limpeed-immobilier' ),
			'description' => __( 'Détail', 'limpeed-immobilier' ),
		);
	}

	/**
	 * Affichage des colonnes.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );

			case 'user':
				$user = $item->user_id ? get_userdata( $item->user_id ) : false;
				return $user ? esc_html( $user->display_name ) : esc_html__( 'Utilisateur supprimé', 'limpeed-immobilier' );

			case 'action':
				$actions = Limpeed_Activity_Log::get_actions();
				return isset( $actions[ $item->action ] ) ? esc_html( $actions[ $item->action ] ) : esc_html( $item->action );

			case 'object_type':
				$types = Limpeed_Activity_Log::get_object_types();
				return isset( $types[ $item->object_type ] ) ? esc_html( $types[ $item->object_type ] ) : esc_html( $item->object_type );

			case 'description':
				return esc_html( $item->description );

			default:
				return '';
		}
	}

	/**
	 * Filtres additionnels (type d'élément, action).
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$selected_type   = isset( $_REQUEST['object_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['object_type'] ) ) : '';
		$selected_action = isset( $_REQUEST['action_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_filter'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="object_type">
				<option value=""><?php esc_html_e( 'Tous les éléments', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( Limpeed_Activity_Log::get_object_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_type, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="action_filter">
				<option value=""><?php esc_html_e( 'Toutes les actions', 'limpeed-immobilier' ); ?></option>
				<?php foreach ( Limpeed_Activity_Log::get_actions() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_action, $key ); ?>>
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
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$object_type = isset( $_REQUEST['object_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['object_type'] ) ) : '';
		$action      = isset( $_REQUEST['action_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_filter'] ) ) : '';
		$paged       = isset( $_REQUEST['paged'] ) ? max( 1, (int) $_REQUEST['paged'] ) : 1;

		$args = array(
			'object_type' => $object_type,
			'action'      => $action,
			'per_page'    => $this->per_page,
			'paged'       => $paged,
		);

		$total_items = Limpeed_Activity_Log::count( $args );

		$this->items = Limpeed_Activity_Log::get_all( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
