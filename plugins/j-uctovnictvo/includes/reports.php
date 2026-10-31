<?php
/** Účtovné knihy a zostavy: hlavná kniha (karta účtu), obratová predvaha, saldokonto. Tlač a CSV. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function juc_print_mode() {
	if ( empty( $_GET['print'] ) ) { // phpcs:ignore
		return false;
	}
	echo '<style>#adminmenumain,#wpadminbar,#wpfooter,.notice,.juc-filter,.juc-year,.page-title-action,.juc-noprint{display:none!important}#wpcontent{margin:0!important}html.wp-toolbar{padding:0!important}@page{margin:10mm}</style><script>addEventListener("load",function(){print()})</script>';
	return true;
}

function juc_print_header( $title, $y ) {
	echo '<p class="juc-print-h"><strong>' . esc_html( juc_opt( 'company' ) ) . '</strong>' . ( juc_opt( 'ico' ) ? ' · IČO ' . esc_html( juc_opt( 'ico' ) ) : '' ) . ' · ' . esc_html( $title ) . ' · obdobie ' . esc_html( juc_date( $y['start_date'] ) . ' – ' . juc_date( $y['end_date'] ) ) . ' · vytlačené ' . esc_html( date_i18n( 'j. n. Y H:i' ) ) . '</p>';
}

/** CSV výstup (bodkočiarka, UTF-8 s BOM – otvorí sa v Exceli). */
function juc_csv( $name, array $head, array $rows ) {
	while ( ob_get_level() ) {
		ob_end_clean(); // zahodí HTML stránky (CSV sa spúšťa pred výstupom administrácie)
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '.csv"' );
	$f = fopen( 'php://output', 'w' );
	fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore
	fputcsv( $f, $head, ';' );
	foreach ( $rows as $r ) {
		fputcsv( $f, array_map( 'juc_csv_cell', $r ), ';' );
	}
	fclose( $f ); // phpcs:ignore
	exit;
}

/** Bunka CSV: čísla s desatinnou čiarkou, text začínajúci = + - @ (vzorec v Exceli) s apostrofom na začiatku. */
function juc_csv_cell( $v ) {
	if ( is_float( $v ) ) {
		return number_format( $v, 2, ',', '' );
	}
	if ( is_string( $v ) && '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		return "'" . $v;
	}
	return $v;
}

/**
 * Dá sa účtovať k dátumu? Ako juc_can_post(), ale chýbajúce účtové obdobie nezakladá (kontroly, rušenie kariet).
 * Neexistujúce obdobie nemá žiadne zápisy ani uzávierku – vráti true. Vráti true alebo WP_Error.
 */
function juc_period_check( $date, $respect_lock = true ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
		return new WP_Error( 'juc_date', 'Neplatný dátum.' );
	}
	$y = juc_year_of( $date );
	if ( $y && 'closed' === $y['status'] ) {
		return new WP_Error( 'juc_closed', 'Účtové obdobie ' . substr( $y['start_date'], 0, 4 ) . ' je uzavreté.' );
	}
	$lock = (string) juc_opt( 'lock_date' );
	if ( $respect_lock && $lock && $date <= $lock && empty( $GLOBALS['juc_importing'] ) ) {
		return new WP_Error( 'juc_locked', 'Účtovníctvo je uzavreté do ' . juc_date( $lock ) . ' – zápis s dátumom ' . juc_date( $date ) . ' nie je možný.' );
	}
	return true;
}

/** Má účtové obdobie aspoň jeden zápis? */
function juc_year_has_lines( $year_id ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . juc_t( 'lines' ) . ' WHERE year_id = %d LIMIT 1', $year_id ) ); // phpcs:ignore
}

/**
 * Párovanie saldokonta – rovnaké pre zostavu saldokonta aj ročnú uzávierku.
 * Vstup: riadky [ 'acc', 'pid', 'vs', 'bal' ] zoskupené po účte, partnerovi a VS. Vráti kľúč skupiny pre každý riadok (rovnaké indexy):
 *  - s partnerom: účet + VS + partner (rôzni partneri s rovnakým VS sa nezlúčia), bez VS účet + partner,
 *  - bez partnera (napr. úhrada z banky) s VS: pripojí sa k jedinej skupine s rovnakým účtom a VS; ak je ich viac,
 *    k tej s opačným zostatkom najbližším k sume; ak žiadna nie je, ostane samostatne (účet + VS),
 *  - bez partnera aj VS: účet + partner 0.
 */
function juc_saldo_keys( array $rows ) {
	$keys  = array();
	$by_vs = array();
	foreach ( $rows as $i => $r ) {
		$pid = (int) $r['pid'];
		$vs  = (string) $r['vs'];
		if ( ! $pid ) {
			continue;
		}
		$keys[ $i ] = $r['acc'] . '|' . ( '' !== $vs ? 'vs:' . $vs . '|' : '' ) . 'p:' . $pid;
		if ( '' !== $vs ) {
			$g                          = $r['acc'] . '|' . $vs;
			$by_vs[ $g ][ $keys[ $i ] ] = round( ( $by_vs[ $g ][ $keys[ $i ] ] ?? 0 ) + (float) $r['bal'], 2 );
		}
	}
	foreach ( $rows as $i => $r ) {
		if ( isset( $keys[ $i ] ) ) {
			continue;
		}
		$vs = (string) $r['vs'];
		if ( '' === $vs ) {
			$keys[ $i ] = $r['acc'] . '|p:0';
			continue;
		}
		$g    = $r['acc'] . '|' . $vs;
		$cand = $by_vs[ $g ] ?? array();
		if ( ! $cand ) {
			$keys[ $i ] = $r['acc'] . '|vs:' . $vs . '|p:0';
			continue;
		}
		$bal  = (float) $r['bal'];
		$pick = array_key_first( $cand );
		if ( count( $cand ) > 1 ) {
			$best = null;
			foreach ( $cand as $k => $b ) {
				if ( $b * $bal < 0 ) {
					$diff = abs( abs( $b ) - abs( $bal ) );
					if ( null === $best || $diff < $best ) {
						$best = $diff;
						$pick = $k;
					}
				}
			}
		}
		$keys[ $i ]           = $pick;
		$by_vs[ $g ][ $pick ] = round( $cand[ $pick ] + $bal, 2 );
	}
	return $keys;
}

// CSV zostáv sa generuje pred výstupom administrácie (inak by už boli odoslané hlavičky).
add_action( 'admin_init', function () {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	if ( empty( $_GET['csv'] ) || ! current_user_can( JUC_CAP ) ) { // phpcs:ignore
		return;
	}
	$fn = array( 'j-uctovnictvo-ledger' => 'juc_page_ledger', 'j-uctovnictvo-trial' => 'juc_page_trial' )[ $page ] ?? '';
	if ( $fn ) {
		ob_start();
		$fn();
		ob_end_clean();
	}
} );

/* ---------------------------------------------------------------- Hlavná kniha / karta účtu */

function juc_page_ledger() {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y   = juc_current_year();
	$acc = preg_replace( '/\D/', '', (string) ( $_GET['acc'] ?? '' ) ); // phpcs:ignore
	$t   = juc_turnovers( (int) $y['id'], '', '', 0 );
	$syn = juc_turnovers( (int) $y['id'], '', '', 3 );
	juc_head( $acc ? 'Účet ' . $acc . ' ' . juc_account_name( $acc ) : 'Hlavná kniha' );
	$print = juc_print_mode();
	if ( $print ) {
		juc_print_header( $acc ? 'Kniha analytického účtu ' . $acc : 'Hlavná kniha', $y );
	}
	juc_year_select( 'j-uctovnictvo-ledger', $acc ? array( 'acc' => $acc ) : array() );
	?>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-ledger"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<input name="acc" list="juc-accs" value="<?php echo esc_attr( $acc ); ?>" placeholder="Účet (napr. 221 alebo 221001)" inputmode="numeric">
		<?php juc_accounts_datalist(); ?>
		<button class="button">Zobraziť kartu účtu</button>
		<?php if ( $acc ) : ?><a class="button" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'year' => $y['id'] ) ) ); ?>">Hlavná kniha</a><?php endif; ?>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'print', 1 ) ); ?>">Tlačiť</a>
	</form>
	<?php
	if ( ! $acc ) {
		// Hlavná kniha: syntetické účty – počiatočný stav, obraty, konečný zostatok.
		?>
		<table class="widefat striped juc-table">
			<thead><tr><th>Účet</th><th>Názov</th><th class="n">PS MD</th><th class="n">PS Dal</th><th class="n">Obrat MD</th><th class="n">Obrat Dal</th><th class="n">Zostatok MD</th><th class="n">Zostatok Dal</th></tr></thead>
			<tbody>
			<?php if ( ! $syn ) : ?><tr><td colspan="8">V tomto období zatiaľ nie sú zápisy.</td></tr><?php endif; ?>
			<?php foreach ( $syn as $a => $r ) : $b = juc_balance( $r ); ?>
				<tr>
					<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $a, 'year' => $y['id'] ) ) ); ?>"><strong><?php echo esc_html( $a ); ?></strong></a></td>
					<td><?php echo esc_html( juc_account_name( $a ) ); ?></td>
					<td class="n"><?php echo esc_html( juc_money( $r['ps_md'] ?: null ) ); ?></td>
					<td class="n"><?php echo esc_html( juc_money( $r['ps_d'] ?: null ) ); ?></td>
					<td class="n"><?php echo esc_html( juc_money( $r['md'] ?: null ) ); ?></td>
					<td class="n"><?php echo esc_html( juc_money( $r['d'] ?: null ) ); ?></td>
					<td class="n"><?php echo $b > 0 ? esc_html( juc_money( $b ) ) : ''; ?></td>
					<td class="n"><?php echo $b < 0 ? esc_html( juc_money( -$b ) ) : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
		return;
	}
	// Karta účtu (syntetický = všetky jeho analytiky).
	$like  = $wpdb->esc_like( $acc ) . '%';
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, e.number, e.series, e.descr, e.id AS eid FROM ' . juc_t( 'lines' ) . ' l JOIN ' . juc_t( 'entries' ) . ' e ON e.id = l.entry_id WHERE l.year_id = %d AND (l.md LIKE %s OR l.d LIKE %s) ORDER BY l.opening DESC, l.entry_date, l.id', $y['id'], $like, $like ), ARRAY_A ); // phpcs:ignore
	$bal   = 0.0;
	$tmd   = 0.0;
	$td    = 0.0;
	$a     = juc_account( $acc );
	$type  = $a ? $a['type'] : '';
	if ( isset( $_GET['csv'] ) ) { // phpcs:ignore
		$out = array();
		foreach ( (array) $rows as $r ) {
			$is_md = 0 === strpos( $r['md'], $acc );
			$is_d  = 0 === strpos( $r['d'], $acc );
			$both  = $is_md && $is_d; // presun medzi analytikami toho istého účtu – na oboch stranách (netto 0)
			$bal  += ( $is_md ? (float) $r['amount'] : 0 ) - ( $is_d ? (float) $r['amount'] : 0 );
			$out[] = array( $r['entry_date'], $r['number'], $r['text'] ? $r['text'] : $r['descr'], $both ? $r['md'] . ' / ' . $r['d'] : ( $is_md ? $r['d'] : $r['md'] ), $is_md ? (float) $r['amount'] : '', $is_d ? (float) $r['amount'] : '', $bal );
		}
		juc_csv( 'ucet-' . $acc . '-' . substr( $y['start_date'], 0, 4 ), array( 'Dátum', 'Doklad', 'Text', 'Protiúčet', 'MD', 'Dal', 'Zostatok' ), $out );
	}
	?>
	<p class="juc-noprint"><a class="button" href="<?php echo esc_url( add_query_arg( 'csv', 1 ) ); ?>">Stiahnuť CSV</a> <span class="description">Typ účtu: <?php echo esc_html( juc_account_types()[ $type ] ?? '—' ); ?></span></p>
	<table class="widefat striped juc-table">
		<thead><tr><th>Dátum</th><th>Doklad</th><th>Text</th><th>Protiúčet</th><th class="n">MD</th><th class="n">Dal</th><th class="n">Zostatok</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7">Na účte nie sú v tomto období zápisy.</td></tr><?php endif; ?>
		<?php
		foreach ( (array) $rows as $r ) :
			$is_md = 0 === strpos( $r['md'], $acc );
			$is_d  = 0 === strpos( $r['d'], $acc );
			$both  = $is_md && $is_d; // presun medzi analytikami toho istého účtu – na oboch stranách (netto 0)
			$amt   = (float) $r['amount'];
			$bal  += ( $is_md ? $amt : 0 ) - ( $is_d ? $amt : 0 );
			$tmd  += $is_md ? $amt : 0;
			$td   += $is_d ? $amt : 0;
			$cp    = $both ? $r['md'] . ' / ' . $r['d'] : ( $is_md ? $r['d'] : $r['md'] );
			?>
			<tr class="<?php echo (int) $r['opening'] ? 'juc-ps' : ''; ?>">
				<td><?php echo esc_html( juc_date( $r['entry_date'] ) ); ?></td>
				<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r['eid'] ) ) ); ?>"><?php echo esc_html( $r['number'] ); ?></a></td>
				<td><?php echo esc_html( $r['text'] ? $r['text'] : $r['descr'] ); ?></td>
				<td title="<?php echo esc_attr( $both ? 'Presun medzi analytickými účtami' : juc_account_name( $cp ) ); ?>"><?php echo esc_html( $cp ); ?><?php echo ! $both && strlen( $acc ) === 3 && strlen( $is_md ? $r['md'] : $r['d'] ) > 3 ? ' <small>(' . esc_html( $is_md ? $r['md'] : $r['d'] ) . ')</small>' : ''; ?></td>
				<td class="n"><?php echo $is_md ? esc_html( juc_money( $amt ) ) : ''; ?></td>
				<td class="n"><?php echo $is_d ? esc_html( juc_money( $amt ) ) : ''; ?></td>
				<td class="n"><?php echo esc_html( juc_money( $bal ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot><tr><th colspan="4">Obraty a konečný zostatok</th><th class="n"><?php echo esc_html( juc_money( $tmd ) ); ?></th><th class="n"><?php echo esc_html( juc_money( $td ) ); ?></th><th class="n"><?php echo esc_html( juc_money( $bal ) ); ?> <?php echo $bal >= 0 ? 'MD' : 'D'; ?></th></tr></tfoot>
	</table>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Obratová predvaha */

function juc_page_trial() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y     = juc_current_year();
	$to    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : ''; // phpcs:ignore
	$from  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['from'] ?? '' ) ) ? $_GET['from'] : ''; // phpcs:ignore
	$level = isset( $_GET['an'] ) ? 0 : 3; // phpcs:ignore
	$t     = juc_turnovers( (int) $y['id'], $to, $from, $level );
	if ( isset( $_GET['csv'] ) ) { // phpcs:ignore
		$out = array();
		foreach ( $t as $a => $r ) {
			$b     = juc_balance( $r );
			$out[] = array( $a, juc_account_name( $a ), $r['ps_md'], $r['ps_d'], $r['md'], $r['d'], $b > 0 ? $b : 0.0, $b < 0 ? -$b : 0.0 );
		}
		juc_csv( 'predvaha-' . substr( $y['start_date'], 0, 4 ), array( 'Účet', 'Názov', 'PS MD', 'PS Dal', 'Obrat MD', 'Obrat Dal', 'KZ MD', 'KZ Dal' ), $out );
	}
	juc_head( 'Obratová predvaha' );
	if ( juc_print_mode() ) {
		juc_print_header( 'Obratová predvaha' . ( $to ? ' k ' . juc_date( $to ) : '' ), $y );
	}
	juc_year_select( 'j-uctovnictvo-trial' );
	?>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-trial"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<label>Obraty od <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
		<label>do <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
		<label><input type="checkbox" name="an" value="1" <?php checked( ! $level ); ?>> analytické účty</label>
		<button class="button">Zobraziť</button>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'print', 1 ) ); ?>">Tlačiť</a>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'csv', 1 ) ); ?>">CSV</a>
	</form>
	<table class="widefat striped juc-table">
		<thead><tr><th>Účet</th><th>Názov</th><th class="n">PS MD</th><th class="n">PS Dal</th><th class="n">Obrat MD</th><th class="n">Obrat Dal</th><th class="n">KZ MD</th><th class="n">KZ Dal</th></tr></thead>
		<tbody>
		<?php
		$sum   = array_fill_keys( array( 'ps_md', 'ps_d', 'md', 'd', 'kmd', 'kd' ), 0.0 );
		$class = '';
		$csum  = $sum;
		$flush = function ( $c ) use ( &$csum ) {
			echo '<tr class="juc-sub"><td colspan="2">Spolu trieda ' . esc_html( $c ) . '</td>';
			foreach ( array( 'ps_md', 'ps_d', 'md', 'd', 'kmd', 'kd' ) as $k ) {
				echo '<td class="n">' . esc_html( juc_money( $csum[ $k ] ) ) . '</td>';
			}
			echo '</tr>';
			$csum = array_fill_keys( array_keys( $csum ), 0.0 );
		};
		foreach ( $t as $a => $r ) :
			$a = (string) $a;
			if ( $class && $class !== $a[0] ) {
				$flush( $class );
			}
			$class = $a[0];
			$b     = juc_balance( $r );
			$vals  = array( 'ps_md' => $r['ps_md'], 'ps_d' => $r['ps_d'], 'md' => $r['md'], 'd' => $r['d'], 'kmd' => $b > 0 ? $b : 0.0, 'kd' => $b < 0 ? -$b : 0.0 );
			foreach ( $vals as $k => $v ) {
				$sum[ $k ]  += $v;
				$csum[ $k ] += $v;
			}
			?>
			<tr>
				<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $a, 'year' => $y['id'] ) ) ); ?>"><?php echo esc_html( $a ); ?></a></td>
				<td><?php echo esc_html( juc_account_name( $a ) ); ?></td>
				<?php foreach ( $vals as $v ) : ?><td class="n"><?php echo esc_html( juc_money( $v ?: null ) ); ?></td><?php endforeach; ?>
			</tr>
			<?php
		endforeach;
		if ( $class ) {
			$flush( $class );
		}
		?>
		</tbody>
		<tfoot><tr><th colspan="2">Spolu</th><?php foreach ( $sum as $v ) : ?><th class="n"><?php echo esc_html( juc_money( $v ) ); ?></th><?php endforeach; ?></tr></tfoot>
	</table>
	<?php
	$ok = abs( $sum['md'] - $sum['d'] ) < 0.005 && abs( $sum['ps_md'] - $sum['ps_d'] ) < 0.005;
	echo '<p class="' . ( $ok ? 'juc-ok' : 'juc-warn' ) . '">' . ( $ok ? '✓ Strany MD a Dal sa rovnajú (počiatočné stavy aj obraty).' : '⚠ Počiatočné stavy strán MD a Dal sa nerovnajú – skontrolujte doklady PS (súčet aktív musí byť rovný súčtu pasív).' ) . '</p>';
	echo '</div>';
}

/* ---------------------------------------------------------------- Saldokonto */

function juc_page_saldo() {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y    = juc_current_year();
	$acc  = preg_replace( '/\D/', '', (string) ( $_GET['acc'] ?? '311' ) ); // phpcs:ignore
	$acc  = $acc ? $acc : '311';
	$all  = ! empty( $_GET['all'] ); // phpcs:ignore
	$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : ''; // phpcs:ignore
	$like = $wpdb->esc_like( $acc ) . '%';
	$l    = juc_t( 'lines' );
	$e    = juc_t( 'entries' );
	$w    = $wpdb->prepare( 'l.year_id = %d', $y['id'] ) . ( $to ? $wpdb->prepare( ' AND l.entry_date <= %s', $to ) : '' );
	$rows = $wpdb->get_results(
		'SELECT l.partner_id, l.vs, MAX(e.partner_name) pname, MIN(l.entry_date) d1, MAX(l.entry_date) d2,' .
		$wpdb->prepare( ' SUM(IF(l.md LIKE %s, l.amount, 0)) md, SUM(IF(l.d LIKE %s, l.amount, 0)) dal', $like, $like ) .
		" FROM $l l JOIN $e e ON e.id = l.entry_id WHERE $w" . $wpdb->prepare( ' AND (l.md LIKE %s OR l.d LIKE %s)', $like, $like ) .
		' GROUP BY l.partner_id, l.vs ORDER BY pname, d1',
		ARRAY_A
	); // phpcs:ignore
	// Párovanie rovnako ako pri ročnej uzávierke: úhrada bez partnera sa pripojí k faktúre s rovnakým VS.
	$rows = (array) $rows;
	$keys = juc_saldo_keys( array_map( fn( $r ) => array( 'acc' => $acc, 'pid' => (int) $r['partner_id'], 'vs' => (string) $r['vs'], 'bal' => (float) $r['md'] - (float) $r['dal'] ), $rows ) );
	$grp  = array();
	foreach ( $rows as $i => $r ) {
		$k = $keys[ $i ];
		if ( ! isset( $grp[ $k ] ) ) {
			$grp[ $k ] = array( 'partner_id' => 0, 'vs' => (string) $r['vs'], 'pname' => '', 'd1' => $r['d1'], 'd2' => $r['d2'], 'md' => 0.0, 'dal' => 0.0 );
		}
		if ( (int) $r['partner_id'] ) {
			$grp[ $k ]['partner_id'] = (int) $r['partner_id'];
			$grp[ $k ]['pname']      = (string) $r['pname'];
		} elseif ( '' === $grp[ $k ]['pname'] ) {
			$grp[ $k ]['pname'] = (string) $r['pname'];
		}
		$grp[ $k ]['d1']   = min( $grp[ $k ]['d1'], $r['d1'] );
		$grp[ $k ]['d2']   = max( $grp[ $k ]['d2'], $r['d2'] );
		$grp[ $k ]['md']  += (float) $r['md'];
		$grp[ $k ]['dal'] += (float) $r['dal'];
	}
	$rows = array_values( $grp );
	usort( $rows, fn( $a, $b ) => array( $a['pname'], $a['d1'] ) <=> array( $b['pname'], $b['d1'] ) );
	juc_head( 'Saldokonto ' . $acc . ' – ' . juc_account_name( $acc ) );
	if ( juc_print_mode() ) {
		juc_print_header( 'Saldokonto ' . $acc . ( $to ? ' k ' . juc_date( $to ) : '' ), $y );
	}
	juc_year_select( 'j-uctovnictvo-saldo', array( 'acc' => $acc ) );
	?>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-saldo"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<select name="acc"><?php foreach ( array( '311' => '311 Odberatelia', '321' => '321 Dodávatelia', '314' => '314 Poskytnuté preddavky', '324' => '324 Prijaté preddavky', '365' => '365 Spoločníci', '261' => '261 Peniaze na ceste' ) as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $acc, (string) $k ); ?>><?php echo esc_html( $v ); ?></option><?php endforeach; ?></select>
		<label>k dátumu <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
		<label><input type="checkbox" name="all" value="1" <?php checked( $all ); ?>> aj vyrovnané položky</label>
		<button class="button">Zobraziť</button>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'print', 1 ) ); ?>">Tlačiť</a>
	</form>
	<table class="widefat striped juc-table">
		<thead><tr><th>Partner</th><th>Párovací symbol (VS)</th><th>Prvý zápis</th><th>Posledný zápis</th><th class="n">MD</th><th class="n">Dal</th><th class="n">Zostatok</th></tr></thead>
		<tbody>
		<?php
		$sum = 0.0;
		$n   = 0;
		foreach ( (array) $rows as $r ) :
			$b = round( (float) $r['md'] - (float) $r['dal'], 2 );
			if ( ! $all && abs( $b ) < 0.005 ) {
				continue;
			}
			$sum += $b;
			$n++;
			$name = $r['pname'];
			if ( (int) $r['partner_id'] && function_exists( 'jinv_partner_get' ) && ( $pp = jinv_partner_get( (int) $r['partner_id'] ) ) ) {
				$name = $pp['name'];
			}
			?>
			<tr class="<?php echo abs( $b ) < 0.005 ? 'juc-muted' : ''; ?>">
				<td><?php echo esc_html( $name ? $name : '—' ); ?></td>
				<td><?php echo esc_html( $r['vs'] ? $r['vs'] : '—' ); ?></td>
				<td><?php echo esc_html( juc_date( $r['d1'] ) ); ?></td>
				<td><?php echo esc_html( juc_date( $r['d2'] ) ); ?></td>
				<td class="n"><?php echo esc_html( juc_money( $r['md'] ) ); ?></td>
				<td class="n"><?php echo esc_html( juc_money( $r['dal'] ) ); ?></td>
				<td class="n"><strong><?php echo esc_html( juc_money( $b ) ); ?></strong></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $n ) : ?><tr><td colspan="7"><?php echo $all ? 'Na účte nie sú zápisy.' : 'Žiadne neuhradené položky.'; ?></td></tr><?php endif; ?>
		</tbody>
		<tfoot><tr><th colspan="6">Zostatok účtu (otvorené položky)</th><th class="n"><?php echo esc_html( juc_money( $sum ) ); ?></th></tr></tfoot>
	</table>
	<p class="description">Položky sa párujú podľa partnera a párovacieho symbolu (variabilný symbol faktúry); úhrada bez partnera (napr. z banky) sa pripojí k položke s rovnakým VS – rovnako ako pri ročnej uzávierke. Zostatok MD na 311 = pohľadávka, zostatok Dal na 321 (záporné číslo) = záväzok.</p>
	</div>
	<?php
}
