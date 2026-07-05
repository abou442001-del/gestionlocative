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
	 * Exporte l'historique des bordereaux (filtré par propriétaire le cas
	 * échéant) en CSV, envoyé directement au navigateur, et termine la requête.
	 * Complète le PDF individuel de chaque bordereau : un export global est
	 * plus exploitable pour un comptable externe ou un tableur.
	 *
	 * @param array $args { @type int $owner_id }
	 */
	public static function stream_csv( $args = array() ) {
		$statements = self::get_all( wp_parse_args( $args, array( 'per_page' => 100000 ) ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bordereaux-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		// BOM UTF-8 : Excel n'affiche correctement les accents français sans lui.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite

		fputcsv( $out, array( 'Propriétaire', 'Période début', 'Période fin', 'Encaissé', 'Commission', 'Net reversé', 'Date de génération' ), ',', '"', '\\' );

		foreach ( $statements as $statement ) {
			$owner = Limpeed_Owners::get( $statement->owner_id );
			fputcsv(
				$out,
				array(
					$owner ? $owner->full_name : '—',
					$statement->period_start,
					$statement->period_end,
					$statement->total_collected,
					$statement->total_commission,
					$statement->net_amount,
					$statement->created_at,
				),
				',',
				'"',
				'\\'
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
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
	 * Nombre de mois couverts par une période AAAA-MM — AAAA-MM, bornes
	 * incluses (ex. 2026-01 à 2026-03 = 3 mois). Utilisé pour projeter le
	 * loyer attendu d'un bien loué sur la durée du bordereau.
	 *
	 * @param string $period_start
	 * @param string $period_end
	 * @return int
	 */
	private static function count_months( $period_start, $period_end ) {
		$start = DateTime::createFromFormat( 'Y-m-d', $period_start . '-01' );
		$end   = DateTime::createFromFormat( 'Y-m-d', $period_end . '-01' );
		if ( ! $start || ! $end || $end < $start ) {
			return 1;
		}
		return (int) $start->diff( $end )->m + ( (int) $start->diff( $end )->y * 12 ) + 1;
	}

	/**
	 * Convertit un montant entier en toutes lettres françaises (ex. 48000 →
	 * "quarante-huit mille"), pour la formule d'acquit du bordereau ("le
	 * client reconnaît avoir reçu... la somme de [en lettres]"), comme sur
	 * les décomptes de l'ancien logiciel de l'agence.
	 *
	 * @param float $amount
	 * @return string
	 */
	private static function amount_to_french_words( $amount ) {
		$number = (int) round( $amount );
		if ( 0 === $number ) {
			return 'zéro';
		}
		if ( $number < 0 ) {
			return 'moins ' . self::amount_to_french_words( -$number );
		}

		$units = array( '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf' );
		$tens  = array( '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix' );

		$under_100 = function ( $n ) use ( $units, $tens ) {
			if ( $n < 20 ) {
				return $units[ $n ];
			}
			$ten  = intdiv( $n, 10 );
			$unit = $n % 10;
			if ( 7 === $ten || 9 === $ten ) {
				return $tens[ $ten - 1 ] . '-' . $units[ 10 + $unit ];
			}
			if ( 0 === $unit ) {
				return ( 8 === $ten ) ? $tens[ $ten ] . 's' : $tens[ $ten ];
			}
			if ( 1 === $unit && 8 !== $ten ) {
				return $tens[ $ten ] . ' et un';
			}
			return $tens[ $ten ] . '-' . $units[ $unit ];
		};

		$under_1000 = function ( $n ) use ( $under_100 ) {
			$hundreds = intdiv( $n, 100 );
			$rest     = $n % 100;
			$result   = '';
			if ( $hundreds > 0 ) {
				$result .= ( $hundreds > 1 ) ? $under_100( $hundreds ) . ' cent' : 'cent';
				if ( $hundreds > 1 && 0 === $rest ) {
					$result .= 's';
				}
			}
			if ( $rest > 0 ) {
				$result .= ( '' !== $result ? ' ' : '' ) . $under_100( $rest );
			}
			return $result;
		};

		$scales = array(
			array( 1000000000, 'milliard', 'milliards' ),
			array( 1000000, 'million', 'millions' ),
			array( 1000, 'mille', 'mille' ),
		);

		$parts = array();
		$n     = $number;
		foreach ( $scales as $scale ) {
			list( $value, $singular, $plural ) = $scale;
			if ( $n >= $value ) {
				$count = intdiv( $n, $value );
				$n     = $n % $value;
				if ( 1000 === $value ) {
					$parts[] = ( 1 === $count ) ? 'mille' : $under_1000( $count ) . ' mille';
				} else {
					$parts[] = $under_1000( $count ) . ' ' . ( $count > 1 ? $plural : $singular );
				}
			}
		}
		if ( $n > 0 || empty( $parts ) ) {
			$parts[] = $under_1000( $n );
		}

		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Libellé de facture pour une ligne du détail, dans le même esprit que
	 * "Facture du loyer avril 2026" de l'ancien logiciel.
	 *
	 * @param string $period_start
	 * @param string $period_end
	 * @return string
	 */
	private static function invoice_label( $period_start, $period_end ) {
		if ( $period_start === $period_end ) {
			return sprintf( __( 'Facture du loyer de %s', 'limpeed-immobilier' ), date_i18n( 'F Y', strtotime( $period_start . '-01' ) ) );
		}
		return sprintf(
			__( 'Factures des loyers de %1$s à %2$s', 'limpeed-immobilier' ),
			date_i18n( 'F Y', strtotime( $period_start . '-01' ) ),
			date_i18n( 'F Y', strtotime( $period_end . '-01' ) )
		);
	}

	/**
	 * Arriérés cumulés d'un locataire sur un bien depuis le début du bail
	 * jusqu'à la fin de la période du bordereau (loyer attendu depuis le
	 * début du bail moins tout ce qui a été payé depuis lors) : une créance
	 * qui remonte à avant la période du bordereau, contrairement à "Restant"
	 * qui ne porte que sur la période couverte par ce document.
	 *
	 * @param object $tenant
	 * @param string $period_end
	 * @return float
	 */
	private static function calculate_lifetime_arrears( $tenant, $period_end ) {
		global $wpdb;

		if ( empty( $tenant->lease_start ) ) {
			return 0.0;
		}

		$lease_start_period = substr( $tenant->lease_start, 0, 7 );
		if ( $lease_start_period > $period_end ) {
			return 0.0;
		}

		$months_since_start = self::count_months( $lease_start_period, $period_end );
		$expected_lifetime  = (float) $tenant->rent_amount * $months_since_start;

		$payments_table = Limpeed_Payments::table();
		$collected_lifetime = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$payments_table} WHERE tenant_id = %d AND status IN ('paye','partiel') AND period <= %s",
				$tenant->id,
				$period_end
			)
		);
		$collected_lifetime = $collected_lifetime ? (float) $collected_lifetime : 0.0;

		return max( 0.0, $expected_lifetime - $collected_lifetime );
	}

	/**
	 * Calcule le détail bien par bien (loyer attendu / charges / payé / restant
	 * / commission) pour un propriétaire sur une période donnée, dans le même
	 * esprit que le "décompte propriétaire" bien par bien de l'ancien logiciel.
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
		$months         = self::count_months( $period_start, $period_end );
		$invoice_label  = self::invoice_label( $period_start, $period_end );

		$breakdown        = array();
		$total_collected  = 0.0;
		$total_commission = 0.0;
		$total_expected   = 0.0;
		$total_restant    = 0.0;
		$total_arrears    = 0.0;

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

			// Un bien vacant n'a pas de loyer "attendu" sur la période : seul un
			// bien effectivement loué génère une créance en cas de non-paiement.
			$expected = ( 'loue' === $property->status ) ? ( (float) $property->monthly_rent * $months ) : 0.0;
			$tenant   = Limpeed_Properties::get_current_tenant( $property->id );

			// N'affiche que les biens ayant une activité sur la période (loyer
			// attendu ou déjà encaissé) : un bien resté vacant toute la période
			// n'apporte aucune information utile sur le bordereau du propriétaire.
			if ( $expected <= 0 && $collected <= 0 ) {
				continue;
			}

			$restant = max( 0.0, $expected - $collected );
			$arrears = $tenant ? self::calculate_lifetime_arrears( $tenant, $period_end ) : $restant;

			$breakdown[] = array(
				'property'      => $property,
				'tenant'        => $tenant,
				'invoice_label' => $invoice_label,
				'expected'      => $expected,
				'caution'       => (float) $property->deposit_amount,
				'charges'       => (float) $property->charges,
				'collected'     => $collected,
				'commission'    => $commission,
				'restant'       => $restant,
				'arrears'       => $arrears,
				'net'           => $collected - $commission,
			);

			$total_collected  += $collected;
			$total_commission += $commission;
			$total_expected   += $expected;
			$total_restant    += $restant;
			$total_arrears    += $arrears;
		}

		return array(
			'properties'       => $breakdown,
			'total_expected'   => $total_expected,
			'total_collected'  => $total_collected,
			'total_commission' => $total_commission,
			// Somme des restants par bien plutôt que expected - collected
			// global : un trop-perçu sur un bien ne doit jamais masquer un
			// impayé sur un autre bien du même propriétaire.
			'total_restant'    => $total_restant,
			'total_arrears'    => $total_arrears,
			'net_amount'       => $total_collected - $total_commission,
		);
	}

	/**
	 * Génère un bordereau PDF pour un propriétaire sur une période donnée
	 * et enregistre l'historique en base.
	 *
	 * @param int    $owner_id
	 * @param string $period_start           Format YYYY-MM.
	 * @param string $period_end             Format YYYY-MM.
	 * @param string $other_deduction_label  Libellé d'une déduction additionnelle
	 *                                       optionnelle (ex. "Redevance CIE"),
	 *                                       en plus des honoraires d'agence.
	 * @param float  $other_deduction_amount Montant de cette déduction.
	 * @return object|WP_Error Le bordereau créé, ou une erreur.
	 */
	public static function generate( $owner_id, $period_start, $period_end, $other_deduction_label = '', $other_deduction_amount = 0 ) {
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

		$other_deduction_label  = sanitize_text_field( $other_deduction_label );
		$other_deduction_amount = max( 0.0, (float) $other_deduction_amount );
		if ( ! $other_deduction_label ) {
			$other_deduction_amount = 0.0;
		}

		$data              = self::calculate_breakdown( $owner_id, $period_start, $period_end );
		$data['net_amount'] = $data['net_amount'] - $other_deduction_amount;

		$html     = self::render_html( $owner, $period_start, $period_end, $data, $other_deduction_label, $other_deduction_amount );
		$dompdf   = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->render();

		// Un jeton aléatoire est ajouté au nom de fichier : la protection
		// .htaccess du dossier de stockage ne s'applique que sous Apache, un
		// nom composé uniquement de l'id propriétaire et des dates resterait
		// donc devinable par énumération sous un autre serveur web.
		$filename = sprintf(
			'bordereau-%d-%s_%s-%s-%s.pdf',
			$owner_id,
			$period_start,
			$period_end,
			gmdate( 'YmdHis' ),
			wp_generate_password( 12, false, false )
		);
		$filename = sanitize_file_name( $filename );

		$file_path = self::get_storage_dir() . '/' . $filename;
		file_put_contents( $file_path, $dompdf->output() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		global $wpdb;
		$table  = self::table();
		$record = array(
			'owner_id'               => $owner_id,
			'period_start'           => $period_start,
			'period_end'             => $period_end,
			'total_collected'        => $data['total_collected'],
			'total_commission'       => $data['total_commission'],
			'other_deduction_label'  => $other_deduction_label,
			'other_deduction_amount' => $other_deduction_amount,
			'net_amount'             => $data['net_amount'],
			'file_path'              => $filename,
			'generated_by'           => get_current_user_id(),
			'created_at'             => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( $table, $record, array( '%d', '%s', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%s' ) );

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
	 * Construit le HTML du "décompte propriétaire" : en-tête avec logo,
	 * bloc d'identification (propriétaire/période), détail bien par bien
	 * (loyer attendu, charges, payé, restant), section "À déduire"
	 * (honoraires d'agence + déduction additionnelle optionnelle), net à
	 * payer et bloc de signature — reproduit le format du logiciel
	 * précédemment utilisé par l'agence.
	 *
	 * @param object $owner
	 * @param string $period_start
	 * @param string $period_end
	 * @param array  $data
	 * @param string $other_deduction_label
	 * @param float  $other_deduction_amount
	 * @return string
	 */
	private static function render_html( $owner, $period_start, $period_end, $data, $other_deduction_label = '', $other_deduction_amount = 0 ) {
		$period_label = ( $period_start === $period_end )
			? date_i18n( 'F Y', strtotime( $period_start . '-01' ) )
			: sprintf( '%s — %s', date_i18n( 'F Y', strtotime( $period_start . '-01' ) ), date_i18n( 'F Y', strtotime( $period_end . '-01' ) ) );

		// Taux de commission effectif (moyenne pondérée constatée sur la
		// période) : les biens de cet agenda peuvent avoir des taux différents
		// (taux propre à chaque édifice), donc affiché comme une seule ligne
		// "Honoraires de l'agence" avec le pourcentage réellement appliqué.
		$commission_rate = $data['total_collected'] > 0
			? ( $data['total_commission'] / $data['total_collected'] ) * 100
			: 0.0;

		$logo_data_uri = Limpeed_Branding::get_logo_data_uri();

		ob_start();
		?>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				body { font-family: sans-serif; font-size: 11px; color: #1d2327; }
				.header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
				.header td { border: none; padding: 0; vertical-align: middle; }
				.logo-img { height: 42px; }
				.logo-text { font-size: 20px; font-weight: bold; color: #1f7a41; }
				.logo-text .sub { display: block; font-size: 11px; font-weight: normal; color: #1d2327; }
				h1 { font-size: 14px; text-align: center; text-transform: uppercase; margin: 6px 0 2px; }
				.subtitle { text-align: center; color: #50575e; margin: 0 0 14px; }
				.identity { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
				.identity td { border: 1px solid #c3c4c7; padding: 5px 8px; }
				.identity .label { font-weight: bold; width: 22%; background: #f0f0f1; }
				table.detail { width: 100%; border-collapse: collapse; margin-top: 6px; }
				table.detail th, table.detail td { border: 1px solid #c3c4c7; padding: 6px 8px; text-align: left; }
				table.detail th { background: #f0f0f1; }
				.text-right { text-align: right; }
				.totals td { font-weight: bold; background: #f7f7f7; }
				.deduct-box { width: 40%; margin-left: auto; margin-top: 18px; border-collapse: collapse; }
				.deduct-box td { padding: 4px 8px; }
				.deduct-box .deduct-title { font-weight: bold; text-transform: uppercase; padding-bottom: 6px; }
				.deduct-box .net-row td { font-weight: bold; font-size: 13px; border-top: 2px solid #1d2327; padding-top: 8px; }
				.signature { margin-top: 40px; text-align: right; }
				.signature .date { margin-bottom: 30px; }
			</style>
		</head>
		<body>
			<table class="header">
				<tr>
					<td style="width: 60px;">
						<?php if ( $logo_data_uri ) : ?>
							<img class="logo-img" src="<?php echo esc_attr( $logo_data_uri ); ?>" alt="Limpeed Immobilier">
						<?php else : ?>
							<span class="logo-text">Limpeed<span class="sub">IMMOBILIER</span></span>
						<?php endif; ?>
					</td>
					<td></td>
				</tr>
			</table>

			<h1>Décompte propriétaire N° —</h1>
			<p class="subtitle">Période du <?php echo esc_html( $period_label ); ?></p>

			<table class="identity">
				<tr>
					<td class="label">Propriétaire</td>
					<td><?php echo esc_html( $owner->full_name ); ?></td>
					<td class="label">Téléphone</td>
					<td><?php echo esc_html( $owner->phone ?: '—' ); ?></td>
				</tr>
				<?php if ( ! empty( $owner->address ) || ! empty( $owner->bank_details ) ) : ?>
					<tr>
						<?php if ( ! empty( $owner->address ) ) : ?>
							<td class="label">Adresse</td>
							<td><?php echo esc_html( $owner->address ); ?></td>
						<?php else : ?>
							<td colspan="2"></td>
						<?php endif; ?>
						<?php if ( ! empty( $owner->bank_details ) ) : ?>
							<td class="label">Coordonnées bancaires</td>
							<td><?php echo esc_html( $owner->bank_details ); ?></td>
						<?php else : ?>
							<td colspan="2"></td>
						<?php endif; ?>
					</tr>
				<?php endif; ?>
			</table>

			<table class="detail">
				<thead>
					<tr>
						<th>Bien</th>
						<th>Locataire</th>
						<th>Libellé facture</th>
						<th class="text-right">Loyer</th>
						<th class="text-right">Caution</th>
						<th class="text-right">Charges</th>
						<th class="text-right">Payé</th>
						<th class="text-right">Restant</th>
						<th class="text-right">Total arriérés</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['properties'] ) ) : ?>
						<tr><td colspan="9">Aucune activité sur cette période.</td></tr>
					<?php endif; ?>
					<?php foreach ( $data['properties'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( Limpeed_Properties::get_display_label( $row['property'] ) ); ?></td>
							<td><?php echo esc_html( $row['tenant'] ? $row['tenant']->full_name : '—' ); ?></td>
							<td><?php echo esc_html( $row['invoice_label'] ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['expected'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['caution'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['charges'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['collected'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['restant'] ) ); ?></td>
							<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $row['arrears'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr class="totals">
						<td>Total</td>
						<td></td>
						<td></td>
						<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['total_expected'] ) ); ?></td>
						<td></td>
						<td></td>
						<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['total_collected'] ) ); ?></td>
						<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['total_restant'] ) ); ?></td>
						<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['total_arrears'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<table class="deduct-box">
				<tr><td colspan="2" class="deduct-title">À déduire</td></tr>
				<tr>
					<td>Honoraires de l'agence (<?php echo esc_html( number_format_i18n( $commission_rate, 0 ) ); ?>%)</td>
					<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['total_commission'] ) ); ?></td>
				</tr>
				<?php if ( $other_deduction_amount > 0 ) : ?>
					<tr>
						<td><?php echo esc_html( $other_deduction_label ); ?></td>
						<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $other_deduction_amount ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="net-row">
					<td>Net à payer</td>
					<td class="text-right"><?php echo esc_html( Limpeed_Payments::format_amount( $data['net_amount'] ) ); ?></td>
				</tr>
			</table>

			<p style="margin-top: 24px;">
				Le client reconnaît avoir reçu un versement des loyers indiqués ci-dessus la somme de
				<strong><?php echo esc_html( self::amount_to_french_words( $data['net_amount'] ) ); ?> francs CFA</strong>
				et donne ainsi décharge.
			</p>

			<div class="signature">
				<p class="date">Fait le <?php echo esc_html( date_i18n( 'd F Y' ) ); ?></p>
				<p><?php echo esc_html( $owner->full_name ); ?></p>
			</div>
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
