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
		fputcsv( $f, array_map( fn( $v ) => is_float( $v ) ? number_format( $v, 2, ',', '' ) : $v, $r ), ';' );
	}
	fclose( $f ); // phpcs:ignore
	exit;
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
			$bal  += $is_md ? (float) $r['amount'] : -1 * (float) $r['amount'];
			$out[] = array( $r['entry_date'], $r['number'], $r['text'] ? $r['text'] : $r['descr'], $is_md ? $r['d'] : $r['md'], $is_md ? (float) $r['amount'] : '', $is_md ? '' : (float) $r['amount'], $bal );
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
			$amt   = (float) $r['amount'];
			$bal  += $is_md ? $amt : -$amt;
			$tmd  += $is_md ? $amt : 0;
			$td   += $is_md ? 0 : $amt;
			?>
			<tr class="<?php echo (int) $r['opening'] ? 'juc-ps' : ''; ?>">
				<td><?php echo esc_html( juc_date( $r['entry_date'] ) ); ?></td>
				<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r['eid'] ) ) ); ?>"><?php echo esc_html( $r['number'] ); ?></a></td>
				<td><?php echo esc_html( $r['text'] ? $r['text'] : $r['descr'] ); ?></td>
				<td title="<?php echo esc_attr( juc_account_name( $is_md ? $r['d'] : $r['md'] ) ); ?>"><?php echo esc_html( $is_md ? $r['d'] : $r['md'] ); ?><?php echo strlen( $acc ) === 3 && strlen( $is_md ? $r['md'] : $r['d'] ) > 3 ? ' <small>(' . esc_html( $is_md ? $r['md'] : $r['d'] ) . ')</small>' : ''; ?></td>
				<td class="n"><?php echo $is_md ? esc_html( juc_money( $amt ) ) : ''; ?></td>
				<td class="n"><?php echo $is_md ? '' : esc_html( juc_money( $amt ) ); ?></td>
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
	<p class="description">Položky sa párujú podľa partnera a párovacieho symbolu (variabilný symbol faktúry). Zostatok MD na 311 = pohľadávka, zostatok Dal na 321 (záporné číslo) = záväzok.</p>
	</div>
	<?php
}
