<?php
/**
 * Génération et historique des bordereaux propriétaires (wp_limpeed_statements).
 *
 * Les PDF sont stockés hors de la racine web publique accessible directement :
 * le dossier de stockage est protégé par .htaccess et les fichiers ne sont
 * servis qu'via un contrôleur admin qui vérifie les capacités de l'utilisateur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Limpeed_Statements {

	/**
	 * Nom de la table (avec préfixe WordPress).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'limpeed_statements';
	}

	/**
	 * Récupère un bordereau par son id.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Récupère la liste des bordereaux générés, avec filtre par propriétaire.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$defaults = array(
			'owner_id' => 0,
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Compte le nombre total de bordereaux correspondant aux filtres.
	 *
	 * @param array $args
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['owner_id'] ) ) {
			$where   .= ' AND owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Répertoire de stockage des bordereaux PDF (hors accès web direct).
	 *
	 * @return string Chemin absolu, sans slash final.
	 */
	public static function get_storage_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'limpeed-statements';
	}

	/**
	 * Crée le répertoire de stockage et le protège contre l'accès web direct.
	 *
	 * @return bool
	 */
	private static function ensure_storage_dir() {
		$dir = self::get_storage_dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\ndeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return true;
	}

	/**
	 * Calcule le détail bien par bien (loyers encaissés / commission / net) pour
	 * un propriétaire sur une période donnée.
	 *
	 * @param int    $owner_id
	 * @param string $period_start Format YYYY-MM.
	 * @param string $period_end   Format YYYY-MM.
	 * @return array
	 */
	public static function calculate_breakdown( $owner_id, $period_start, $period_end ) {
		global $wpdb;

		$properties     = Limpeed_Properties::get_all( array( 'owner_id' => $owner_id, 'per_page' => 9999 ) );
		$payments_table = Limpeed_Payments::table();

		$breakdown        = array();
		$total_collected  = 0.0;
		$total_commission = 0.0;

		foreach ( $properties as $property ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM(CASE WHEN status IN ('paye','partiel') THEN amount ELSE 0 END) AS collected, SUM(commission_amount) AS commission
					FROM {$payments_table}
					WHERE property_id = %d AND period BETWEEN %s AND %s",
					$property->id,
					$period_start,
					$period_end
				)
			);

			$collected  = $row && $row->collected ? (float) $row->collected : 0.0;
			$commission = $row && $row->commission ? (float) $row->commission : 0.0;

			$breakdown[] = array(
				'property'   => $property,
				'collected'  => $collected,
				'commission' => $commission,
				'net'        => $collected - $commission,
			);

			$total_collected  += $collected;
			$total_commission += $commission;
		}

		return array(
			'properties'       => $breakdown,
			'total_collected'  => $total_collected,
			'total_commission' => $total_commission,
			'net_amount'       => $total_collected - $total_commission,
		);
	}

	/**
	 * Génère un bordereau PDF pour un propriétaire sur une période donnée
	 * et enregistre l'historique en base.
	 *
	 * @param int    $owner_id
	 * @param string $period_start Format YYYY-MM.
	 * @param string $period_end   Format YYYY-MM.
	 * @return object|WP_Error Le bordereau créé, ou une erreur.
	 */
	public static function generate( $owner_id, $period_start, $period_end ) {
		$autoload = LIMPEED_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return new WP_Error(
				'limpeed_dompdf_missing',
				__( 'La librairie Dompdf est introuvable. Exécutez "composer install" dans le dossier du plugin.', 'limpeed-immobilier' )
			);
		}
		require_once $autoload;

		$owner = Limpeed_Owners::get( $owner_id );
		if ( ! $owner ) {
			return new WP_Error( 'limpeed_owner_not_found', __( 'Propriétaire introuvable.', 'limpeed-immobilier' ) );
		}

		if ( ! self::ensure_storage_dir() ) {
			return new WP_Error( 'limpeed_storage_error', __( 'Impossible de créer le répertoire de stockage des bordereaux.', 'limpeed-immobilier' ) );
		}

		$data = self::calculate_breakdown( $owner_id, $period_start, $period_end );

		$html     = self::render_html( $owner, $period_start, $period_end, $data );
		$dompdf   = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		$filename = sprintf(
			'bordereau-%d-%s_%s-%s.pdf',
			$owner_id,
			$period_start,
			$period_end,
			gmdate( 'YmdHis' )
		);
		$filename = sanitize_file_name( $filename );

		$file_path = self::get_storage_dir() . '/' . $filename;
		file_put_contents( $file_path, $dompdf->output() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		global $wpdb;
		$table  = self::table();
		$record = array(
			'owner_id'         => $owner_id,
			'period_start'     => $period_start,
			'period_end'       => $period_end,
			'total_collected'  => $data['total_collected'],
			'total_commission' => $data['total_commission'],
			'net_amount'       => $data['net_amount'],
			'file_path'        => $filename,
			'generated_by'     => get_current_user_id(),
			'created_at'       => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( $table, $record, array( '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s' ) );

		if ( ! $inserted ) {
			return new WP_Error( 'limpeed_statement_save_error', __( 'Le bordereau PDF a été généré mais n\'a pas pu être enregistré en base.', 'limpeed-immobilier' ) );
		}

		$statement_id = (int) $wpdb->insert_id;

		Limpeed_Activity_Log::log(
			'created',
			'statement',
			$statement_id,
			sprintf( 'Bordereau généré pour %s (%s)', $owner->full_name, $period_start === $period_end ? $period_start : "{$period_start} — {$period_end}" )
		);

		return self::get( $statement_id );
	}

	/**
	 * Construit le HTML du bordereau (en-tête Limpeed Immobilier + détail par bien).
	 *
	 * @param object $owner
	 * @param string $period_start
	 * @param string $period_end
	 * @param array  $data
	 * @return string
	 */
	private static function render_html( $owner, $period_start, $period_end, $data ) {
		$period_label = ( $period_start === $period_end ) ? $period_start : sprintf( '%s — %s', $period_start, $period_end );

		ob_start();
		?>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				body { font-family: sans-serif; font-size: 12px; color: #1d2327; }
				h1 { color: #2271b1; margin-bottom: 0; }
				.subtitle { color: #50575e; margin-top: 4px; }
				table { width: 100%; border-collapse: collapse; margin-top: 16px; }
				th, td { border: 1px solid #c3c4c7; padding: 6px 8px; text-align: left; }
				th { background: #f0f0f1; }
				.text-right { text-align: right; }
				.totals td { font-weight: bold; }
			</style>
		</head>
		<body>
			<h1>Limpeed Immobilier</h1>
			<p class="subtitle">Bordereau propriétaire — Période : <?php echo esc_html( $period_label ); ?></p>

			<p>
				<strong>Propriétaire :</strong> <?php echo esc_html( $owner->full_name ); ?><br>
				<?php if ( ! empty( $owner->address ) ) : ?>
					<strong>Adresse :</strong> <?php echo esc_html( $owner->address ); ?><br>
				<?php endif; ?>
				<?php if ( ! empty( $owner->bank_details ) ) : ?>
					<strong>Coordonnées bancaires :</strong> <?php echo esc_html( $owner->bank_details ); ?><br>
				<?php endif; ?>
			</p>

			<table>
				<thead>
					<tr>
						<th>Bien</th>
						<th class="text-right">Loyers encaissés</th>
						<th class="text-right">Commission agence</th>
						<th class="text-right">Net à reverser</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['properties'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( Limpeed_Properties::get_display_label( $row['property'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( number_format( $row['collected'], 2 ) ); ?></td>
							<td class="text-right"><?php echo esc_html( number_format( $row['commission'], 2 ) ); ?></td>
							<td class="text-right"><?php echo esc_html( number_format( $row['net'], 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr class="totals">
						<td>Total</td>
						<td class="text-right"><?php echo esc_html( number_format( $data['total_collected'], 2 ) ); ?></td>
						<td class="text-right"><?php echo esc_html( number_format( $data['total_commission'], 2 ) ); ?></td>
						<td class="text-right"><?php echo esc_html( number_format( $data['net_amount'], 2 ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 24px; color: #50575e;">
				Bordereau généré le <?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?>.
			</p>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Chemin absolu du fichier PDF d'un bordereau.
	 *
	 * @param object $statement
	 * @return string
	 */
	public static function get_file_path( $statement ) {
		return self::get_storage_dir() . '/' . $statement->file_path;
	}

	/**
	 * Supprime un bordereau (base de données et fichier PDF).
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$statement = self::get( $id );
		if ( ! $statement ) {
			return false;
		}

		$file_path = self::get_file_path( $statement );
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		$table  = self::table();
		$result = false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );

		if ( $result ) {
			Limpeed_Activity_Log::log( 'deleted', 'statement', $id );
		}

		return $result;
	}
}
