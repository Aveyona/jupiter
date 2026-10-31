<?php
/**
 * Pokladňa: príjmové a výdavkové pokladničné doklady (séria PD / VD), pokladničná kniha so zostatkom, tlač.
 *
 * Pokladničný doklad = účtovný doklad s jedným zápisom: príjem 211/protiúčet, výdaj protiúčet/211.
 * Zostatok pokladne nesmie byť záporný – výdaj nad aktuálny zostatok sa nezaúčtuje.
 * Stornované doklady a ich storná sa v pokladničnej knihe nezobrazujú (navzájom sa rušia).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Pokladňa – J-Účtovníctvo', 'Pokladňa', JUC_CAP, 'j-uctovnictvo-cash', 'juc_page_cash' );
}, 15 );

/** Účet pokladne (analytika z nastavení, napr. 211001). */
function juc_cash_account() {
	$a = preg_replace( '/\D/', '', (string) juc_opt( 'acc_cash' ) );
	return $a ? $a : '211';
}

/** Účely pokladničných dokladov: kľúč => [ názov, protiúčet, druh in|out ]. */
function juc_cash_purposes() {
	$p = array(
		'in-bank'    => array( 'Výber z bankového účtu do pokladne', '261', 'in' ),
		'in-owner'   => array( 'Vrátenie peňazí od konateľa / spoločníka', '355', 'in' ),
		'in-loan'    => array( 'Pôžička alebo vklad spoločníka', '365', 'in' ),
		'in-cust'    => array( 'Úhrada faktúry od odberateľa', '311', 'in' ),
		'in-goods'   => array( 'Tržba za tovar v hotovosti', '604', 'in' ),
		'in-serv'    => array( 'Tržba za služby v hotovosti', '602', 'in' ),
		'in-other'   => array( 'Iný príjem', '', 'in' ),
		'out-bank'   => array( 'Odvod hotovosti na bankový účet', '261', 'out' ),
		'out-supp'   => array( 'Úhrada faktúry dodávateľovi', '321', 'out' ),
		'out-mat'    => array( 'Nákup materiálu', '501', 'out' ),
		'out-serv'   => array( 'Platba za službu', '518', 'out' ),
		'out-post'   => array( 'Poštovné', '518', 'out' ),
		'out-travel' => array( 'Cestovné náhrady', '512', 'out' ),
		'out-owner'  => array( 'Výdaj konateľovi / spoločníkovi (pohľadávka)', '355', 'out' ),
		'out-loan'   => array( 'Vrátenie pôžičky spoločníkovi', '365', 'out' ),
		'out-other'  => array( 'Iný výdaj', '', 'out' ),
	);
	// Analytiky z nastavení predkontácií (napr. 501001, 518004), ak existujú.
	$map = (array) juc_opt( 'cat_map' );
	foreach ( array( 'out-mat' => 'Materiál', 'out-serv' => 'Služby', 'out-post' => 'Doprava a poštovné' ) as $k => $cat ) {
		if ( ! empty( $map[ $cat ] ) ) {
			$p[ $k ][1] = $map[ $cat ];
		}
	}
	return apply_filters( 'juc_cash_purposes', $p );
}

/** Pohyby pokladne v roku: [ ps, rows[ entry, date, number, text, partner, in, out, balance ], in, out, end, negative ]. */
function juc_cash_book( $year_id, $from = '', $to = '' ) {
	global $wpdb;
	$acc  = juc_cash_account();
	$like = $wpdb->esc_like( $acc ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT l.*, e.number, e.descr, e.partner_name, e.status, e.series, e.id eid FROM ' . juc_t( 'lines' ) . ' l JOIN ' . juc_t( 'entries' ) . ' e ON e.id = l.entry_id WHERE l.year_id = %d AND e.status = \'posted\' AND (l.md LIKE %s OR l.d LIKE %s) ORDER BY l.opening DESC, l.entry_date, e.id, l.id',
		$year_id, $like, $like
	), ARRAY_A ); // phpcs:ignore
	$out = array( 'ps' => 0.0, 'rows' => array(), 'in' => 0.0, 'out' => 0.0, 'end' => 0.0, 'negative' => array() );
	$bal = 0.0;
	foreach ( (array) $rows as $r ) {
		$a   = (float) $r['amount'];
		$sgn = 0 === strpos( $r['md'], $acc ) ? 1 : -1;
		if ( (int) $r['opening'] ) {
			$out['ps'] += $sgn * $a;
			$bal       += $sgn * $a;
			continue;
		}
		$bal += $sgn * $a;
		if ( ( $from && $r['entry_date'] < $from ) ) {
			$out['ps'] += $sgn * $a;
			continue;
		}
		if ( $to && $r['entry_date'] > $to ) {
			continue;
		}
		$in  = $sgn * $a > 0 ? $sgn * $a : 0.0;
		$ex  = $sgn * $a < 0 ? -$sgn * $a : 0.0;
		$out['in']  += $in;
		$out['out'] += $ex;
		$out['rows'][] = array( 'eid' => (int) $r['eid'], 'date' => $r['entry_date'], 'number' => $r['number'], 'text' => $r['text'] ? $r['text'] : $r['descr'], 'partner' => $r['partner_name'], 'in' => $in, 'out' => $ex, 'balance' => round( $bal, 2 ), 'status' => $r['status'] );
		if ( $bal < -0.004 ) {
			$out['negative'][] = $r['entry_date'];
		}
	}
	$out['ps']  = round( $out['ps'], 2 );
	$out['end'] = round( $out['ps'] + $out['in'] - $out['out'], 2 );
	return $out;
}

/**
 * Kontrola záporného zostatku pokladne pre zápisy dokladu (volá juc_post). Ak doklad pokladňu znižuje
 * (Dal 211, alebo storno príjmu = záporná suma na MD 211), zostatok k dátumu dokladu ani kedykoľvek neskôr v roku
 * nesmie klesnúť pod nulu. Vráti true alebo WP_Error.
 */
function juc_cash_check( $date, array $lines ) {
	$cash  = juc_cash_account();
	$delta = 0.0;
	foreach ( $lines as $l ) {
		if ( 0 === strpos( (string) $l['md'], $cash ) ) {
			$delta += (float) $l['amount'];
		}
		if ( 0 === strpos( (string) $l['d'], $cash ) ) {
			$delta -= (float) $l['amount'];
		}
	}
	if ( $delta > -0.005 ) {
		return true;
	}
	$y = juc_year_of( $date );
	if ( ! $y ) {
		$y = juc_year( juc_ensure_year( (int) substr( $date, 0, 4 ) ) );
	}
	if ( ! $y ) {
		return true;
	}
	$min = juc_account_balance( $cash, $date, (int) $y['id'] );
	foreach ( juc_cash_book( (int) $y['id'] )['rows'] as $r ) {
		if ( $r['date'] > $date ) {
			$min = min( $min, $r['balance'] );
		}
	}
	if ( -$delta - $min > 0.004 ) {
		return new WP_Error( 'juc_cash_negative', 'Výdaj z pokladne ' . juc_money( -$delta ) . ' € (' . juc_date( $date ) . ') je vyšší ako zostatok pokladne (' . juc_money( $min ) . ' €) – pokladňa nesmie mať záporný zostatok.' );
	}
	return true;
}

/* ---------------------------------------------------------------- Zaúčtovanie dokladu */

add_action( 'admin_post_juc_cash_save', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_cash_save' );
	$in   = wp_unslash( $_POST );
	$back = juc_url( 'j-uctovnictvo-cash', array( 'new' => sanitize_key( $in['kind'] ?? 'in' ) ) );
	$kind = 'out' === ( $in['kind'] ?? '' ) ? 'out' : 'in';
	$date = sanitize_text_field( $in['date'] ?? '' );
	$amt  = round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['amount'] ?? '0' ) ), 2 );
	$acc  = preg_replace( '/\D/', '', (string) ( $in['acc'] ?? '' ) );
	$text = trim( sanitize_text_field( $in['text'] ?? '' ) );
	$who  = trim( sanitize_text_field( $in['partner'] ?? '' ) );
	$cash = juc_cash_account();
	if ( $amt <= 0 ) {
		juc_back_err( $back, 'Zadajte sumu väčšiu ako 0.' );
	}
	if ( strlen( $acc ) < 3 ) {
		juc_back_err( $back, 'Vyberte účel alebo zadajte protiúčet.' );
	}
	if ( '' === $text ) {
		juc_back_err( $back, 'Vyplňte účel platby (text dokladu).' );
	}
	// Kontrola záporného zostatku pokladne je v juc_post() (juc_cash_check) – platí aj pre storno a automatické účtovanie.
	$ser = 'in' === $kind ? 'PD' : 'VD';
	$r   = juc_post( array(
		'series'       => $ser,
		'number'       => sanitize_text_field( $in['number'] ?? '' ),
		'entry_date'   => $date,
		'doc_date'     => $date,
		'descr'        => ( 'in' === $kind ? 'Príjmový pokladničný doklad – ' : 'Výdavkový pokladničný doklad – ' ) . $text,
		'partner_name' => $who,
		'vs'           => preg_replace( '/\s+/', '', (string) ( $in['vs'] ?? '' ) ),
		'source'       => 'cash',
		'source_type'  => $kind,
	), array( 'in' === $kind ? array( 'md' => $cash, 'd' => $acc, 'amount' => $amt, 'text' => $text ) : array( 'md' => $acc, 'd' => $cash, 'amount' => $amt, 'text' => $text ) ) );
	if ( is_wp_error( $r ) ) {
		juc_back_err( $back, $r->get_error_message() );
	}
	wp_safe_redirect( juc_url( 'j-uctovnictvo-cash', array( 'juc_msg' => 'posted', 'doc' => $r, 'year' => (int) juc_year_of( $date )['id'] ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Tlač */

function juc_cash_print_head( $title ) {
	$s = juc_settings();
	echo '<!doctype html><html lang="sk"><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title><style>
	body{font:13px/1.45 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1d2327;margin:24px}
	h1{font-size:18px;margin:0 0 4px} .firm{color:#50575e;margin-bottom:16px}
	table{border-collapse:collapse;width:100%} th,td{border:1px solid #c3c4c7;padding:5px 7px;text-align:left;vertical-align:top}
	th{background:#f0f0f1} .n{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums} .neg{color:#a3121b;font-weight:600}
	.doc{max-width:720px;border:2px solid #1d2327;padding:16px 20px} .row{display:flex;gap:24px;margin:8px 0} .row>div{flex:1}
	.lbl{font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.03em} .big{font-size:20px;font-weight:700}
	.sign{display:flex;gap:24px;margin-top:40px} .sign>div{flex:1;border-top:1px solid #1d2327;padding-top:4px;font-size:11px;color:#646970}
	.noprint{margin-bottom:16px} @media print{.noprint{display:none} body{margin:0}}
	</style></head><body><div class="noprint"><button onclick="window.print()">Tlačiť</button></div>';
	echo '<div class="firm"><strong>' . esc_html( $s['company'] ) . '</strong>, ' . esc_html( trim( $s['street'] . ', ' . $s['zip'] . ' ' . $s['city'], ', ' ) ) . ' · IČO ' . esc_html( $s['ico'] ) . ( $s['dic'] ? ' · DIČ ' . esc_html( $s['dic'] ) : '' ) . '</div>';
}

add_action( 'admin_post_juc_cash_print', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['doc'] ?? 0 ); // phpcs:ignore
	$e  = $id ? juc_entry( $id ) : null;
	if ( ! $e || ! in_array( $e['series'], array( 'PD', 'VD' ), true ) ) {
		wp_die( 'Pokladničný doklad neexistuje.' );
	}
	$cash = juc_cash_account();
	$l    = $e['lines'][0];
	$in   = 0 === strpos( $l['md'], $cash );
	$sum  = (float) $e['total'];
	$t    = ( $in ? 'Príjmový' : 'Výdavkový' ) . ' pokladničný doklad ' . $e['number'];
	juc_cash_print_head( $t );
	echo '<div class="doc"><h1>' . esc_html( $t ) . '</h1>';
	echo '<div class="row"><div><div class="lbl">Dátum</div>' . esc_html( juc_date( $e['entry_date'] ) ) . '</div><div><div class="lbl">' . ( $in ? 'Prijaté od' : 'Vyplatené komu' ) . '</div>' . esc_html( $e['partner_name'] ? $e['partner_name'] : '—' ) . '</div><div><div class="lbl">Suma</div><span class="big">' . esc_html( juc_money( $sum ) ) . ' €</span></div></div>';
	echo '<div class="row"><div><div class="lbl">Účel platby</div>' . esc_html( $l['text'] ? $l['text'] : $e['descr'] ) . '</div></div>';
	echo '<div class="row"><div><div class="lbl">Účtovanie</div>MD ' . esc_html( $l['md'] . ' ' . juc_account_name( $l['md'] ) ) . ' / Dal ' . esc_html( $l['d'] . ' ' . juc_account_name( $l['d'] ) ) . '</div>' . ( $e['vs'] ? '<div><div class="lbl">VS</div>' . esc_html( $e['vs'] ) . '</div>' : '' ) . '</div>';
	if ( 'posted' !== $e['status'] ) {
		echo '<p class="neg">Doklad je stornovaný.</p>';
	}
	echo '<div class="sign"><div>Vyhotovil</div><div>Schválil</div><div>' . ( $in ? 'Prijal (pokladník)' : 'Prijal (príjemca)' ) . '</div><div>' . ( $in ? 'Vyplatil (platiteľ)' : 'Vyplatil (pokladník)' ) . '</div></div></div></body></html>';
	exit;
} );

add_action( 'admin_post_juc_cash_book_print', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$y    = juc_year( (int) ( $_GET['year'] ?? 0 ) ); // phpcs:ignore
	$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['from'] ?? '' ) ) ? $_GET['from'] : ''; // phpcs:ignore
	$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : ''; // phpcs:ignore
	if ( ! $y ) {
		wp_die( 'Obdobie neexistuje.' );
	}
	$b = juc_cash_book( (int) $y['id'], $from, $to );
	$t = 'Pokladničná kniha ' . substr( $y['start_date'], 0, 4 ) . ( $from || $to ? ' (' . juc_date( $from ? $from : $y['start_date'] ) . ' – ' . juc_date( $to ? $to : $y['end_date'] ) . ')' : '' );
	juc_cash_print_head( $t );
	echo '<h1>' . esc_html( $t ) . '</h1><p>Pokladňa ' . esc_html( juc_cash_account() . ' ' . juc_account_name( juc_cash_account() ) ) . '</p>';
	juc_cash_book_table( $b, false );
	echo '<div class="sign"><div>Pokladník</div><div>Kontroloval</div></div></body></html>';
	exit;
} );

function juc_cash_book_table( array $b, $links = true ) {
	echo '<table class="widefat striped juc-table"><thead><tr><th>Dátum</th><th>Doklad</th><th>Text</th><th>Partner</th><th class="n">Príjem €</th><th class="n">Výdaj €</th><th class="n">Zostatok €</th></tr></thead><tbody>';
	echo '<tr class="juc-ps"><td></td><td></td><td colspan="4"><strong>Počiatočný stav</strong></td><td class="n"><strong>' . esc_html( juc_money( $b['ps'] ) ) . '</strong></td></tr>';
	foreach ( $b['rows'] as $r ) {
		$num = esc_html( $r['number'] );
		if ( $links ) {
			$num = '<a href="' . esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r['eid'] ) ) ) . '">' . $num . '</a>';
		}
		$cls = 'posted' === $r['status'] ? '' : ' class="juc-muted"';
		echo '<tr' . $cls . '><td>' . esc_html( juc_date( $r['date'] ) ) . '</td><td>' . $num . '</td><td>' . esc_html( $r['text'] ) . '</td><td>' . esc_html( $r['partner'] ) . '</td><td class="n">' . esc_html( juc_money( $r['in'] ?: null ) ) . '</td><td class="n">' . esc_html( juc_money( $r['out'] ?: null ) ) . '</td><td class="n' . ( $r['balance'] < 0 ? ' neg juc-warn' : '' ) . '">' . esc_html( juc_money( $r['balance'] ) ) . '</td></tr>';
	}
	echo '</tbody><tfoot><tr><th colspan="4">Spolu</th><th class="n">' . esc_html( juc_money( $b['in'] ) ) . '</th><th class="n">' . esc_html( juc_money( $b['out'] ) ) . '</th><th class="n">' . esc_html( juc_money( $b['end'] ) ) . '</th></tr></tfoot></table>';
}

/* ---------------------------------------------------------------- Stránka */

function juc_page_cash() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y    = juc_current_year();
	$cash = juc_cash_account();
	$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['from'] ?? '' ) ) ? $_GET['from'] : ''; // phpcs:ignore
	$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : ''; // phpcs:ignore
	$b    = juc_cash_book( (int) $y['id'], $from, $to );
	$doc  = (int) ( $_GET['doc'] ?? 0 ); // phpcs:ignore
	$pp   = juc_cash_purposes();
	$kind = 'out' === ( $_GET['new'] ?? '' ) ? 'out' : 'in'; // phpcs:ignore
	juc_head( 'Pokladňa' );
	juc_year_select( 'j-uctovnictvo-cash' );
	if ( $doc ) {
		echo '<div class="notice notice-success inline"><p><a href="' . esc_url( admin_url( 'admin-post.php?action=juc_cash_print&doc=' . $doc ) ) . '" target="_blank">Vytlačiť pokladničný doklad</a></p></div>';
	}
	$all = juc_cash_book( (int) $y['id'] );
	?>
	<div class="juc-cards">
		<div><span>Počiatočný stav</span><strong><?php echo esc_html( juc_money( $all['ps'] ) ); ?> €</strong></div>
		<div><span>Príjmy</span><strong class="juc-plus"><?php echo esc_html( juc_money( $all['in'] ) ); ?> €</strong></div>
		<div><span>Výdaje</span><strong class="juc-minus"><?php echo esc_html( juc_money( $all['out'] ) ); ?> €</strong></div>
		<div class="<?php echo $all['end'] < 0 ? 'is-neg' : ''; ?>"><span>Zostatok pokladne (<?php echo esc_html( $cash ); ?>)</span><strong><?php echo esc_html( juc_money( $all['end'] ) ); ?> €</strong></div>
	</div>
	<?php if ( $all['negative'] ) : ?>
		<div class="notice notice-error inline"><p>Zostatok pokladne bol záporný (prvýkrát <?php echo esc_html( juc_date( $all['negative'][0] ) ); ?>). Pokladňa nesmie mať záporný zostatok – skontrolujte poradie a sumy dokladov.</p></div>
	<?php endif; ?>

	<?php if ( 'closed' !== $y['status'] ) : ?>
	<div class="juc-panel">
		<h2 style="margin-top:6px">Nový pokladničný doklad</h2>
		<p class="juc-inline">
			<a class="button <?php echo 'in' === $kind ? 'button-primary' : ''; ?>" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-cash', array( 'new' => 'in', 'year' => $y['id'] ) ) ); ?>">Príjem (PD)</a>
			<a class="button <?php echo 'out' === $kind ? 'button-primary' : ''; ?>" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-cash', array( 'new' => 'out', 'year' => $y['id'] ) ) ); ?>">Výdaj (VD)</a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-head-fields" id="juc-cash">
			<?php wp_nonce_field( 'juc_cash_save' ); ?>
			<input type="hidden" name="action" value="juc_cash_save"><input type="hidden" name="kind" value="<?php echo esc_attr( $kind ); ?>">
			<label>Dátum<br><input type="date" name="date" required value="<?php echo esc_attr( min( current_time( 'Y-m-d' ), $y['end_date'] ) ); ?>"></label>
			<label>Účel<br><select id="juc-cash-purpose"><option value="">— vyberte —</option>
				<?php foreach ( $pp as $k => $p ) : if ( $p[2] !== $kind ) { continue; } ?>
					<option value="<?php echo esc_attr( $p[1] ); ?>" data-text="<?php echo esc_attr( $p[0] ); ?>"><?php echo esc_html( $p[0] . ( $p[1] ? ' (' . $p[1] . ')' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select></label>
			<label>Protiúčet<br><input name="acc" id="juc-cash-acc" list="juc-accs" required inputmode="numeric" style="width:110px"><small class="juc-accn" id="juc-cash-accn"></small></label>
			<label>Suma €<br><input name="amount" required inputmode="decimal" style="width:120px;text-align:right"></label>
			<label class="juc-wide">Text dokladu<br><input name="text" id="juc-cash-text" required class="large-text" placeholder="<?php echo 'in' === $kind ? 'napr. Vrátenie hotovosti od konateľa' : 'napr. Kancelársky papier, Tesco'; ?>"></label>
			<label><?php echo 'in' === $kind ? 'Prijaté od' : 'Vyplatené komu'; ?><br><input name="partner" class="regular-text"></label>
			<label>VS / číslo faktúry<br><input name="vs" style="width:150px"></label>
			<label>Číslo dokladu<br><input name="number" placeholder="automaticky" style="width:140px"></label>
			<div class="juc-wide"><button class="button button-primary"><?php echo 'in' === $kind ? 'Zaúčtovať príjem' : 'Zaúčtovať výdaj'; ?></button>
			<span class="description">Účtuje sa <?php echo 'in' === $kind ? 'MD ' . esc_html( $cash ) . ' / Dal protiúčet' : 'MD protiúčet / Dal ' . esc_html( $cash ); ?>. Výdaj nad zostatok pokladne sa nezaúčtuje.</span></div>
		</form>
		<?php juc_accounts_datalist(); ?>
		<script>
		(function () {
			var sel = document.getElementById('juc-cash-purpose'), acc = document.getElementById('juc-cash-acc'), txt = document.getElementById('juc-cash-text'), nm = document.getElementById('juc-cash-accn');
			var names = {}; document.querySelectorAll('#juc-accs option').forEach(function (o) { names[o.value] = o.textContent.replace(/^\d+\s/, ''); });
			function show() { nm.textContent = names[acc.value] || (acc.value ? '⚠ nie je v rozvrhu' : ''); }
			sel.addEventListener('change', function () { var o = sel.options[sel.selectedIndex]; if (sel.value) { acc.value = sel.value; } if (!txt.value && sel.value !== '') { txt.value = o.dataset.text || ''; } show(); acc.focus(); });
			acc.addEventListener('input', show);
		})();
		</script>
	</div>
	<?php endif; ?>

	<h2>Pokladničná kniha <?php echo esc_html( substr( $y['start_date'], 0, 4 ) ); ?></h2>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-cash"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<label>Od <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
		<label>Do <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
		<button class="button">Zobraziť</button>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( array( 'action' => 'juc_cash_book_print', 'year' => $y['id'], 'from' => $from, 'to' => $to ), admin_url( 'admin-post.php' ) ) ); ?>">Tlačiť knihu</a>
	</form>
	<?php
	juc_cash_book_table( $b );
	echo '<p class="description">Pokladničné doklady (séria PD a VD) sa dajú vytlačiť z detailu dokladu alebo tu: ';
	$n = 0;
	foreach ( array_reverse( $b['rows'] ) as $r ) {
		if ( preg_match( '/^(PD|VD)/', (string) $r['number'] ) && $n < 10 ) {
			echo '<a target="_blank" href="' . esc_url( admin_url( 'admin-post.php?action=juc_cash_print&doc=' . $r['eid'] ) ) . '">' . esc_html( $r['number'] ) . '</a> ';
			++$n;
		}
	}
	echo '</p></div>';
}
