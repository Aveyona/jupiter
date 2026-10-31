<?php
/**
 * Pomôcky pre majiteľa (neúčtovníka):
 * – prehľad v ľudskej reči (zisk od začiatku roka, odhad dane, pohľadávky a záväzky po splatnosti, najbližšie termíny),
 * – kalendár termínov (daňové priznanie, závierka, register, valné zhromaždenie, preddavky),
 * – kontrolný zoznam uzávierky mesiaca,
 * – odsúhlasenie banky (zostatok podľa výpisu oproti účtu 221 po mesiacoch),
 * – daň z príjmov PO – pomôcka k priznaniu (riadky z účtovníctva, preddavky),
 * – rozdelenie výsledku hospodárenia podľa rozhodnutia valného zhromaždenia (431 → 421 / 428 / 364, 429 → 431).
 * Všetky výpočty sú pomôcka – pred podaním priznania a závierky odporúčame kontrolu účtovníkom.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Termíny a daň – J-Účtovníctvo', 'Termíny a daň', JUC_CAP, 'j-uctovnictvo-tools', 'juc_page_tools' );
	add_submenu_page( 'j-uctovnictvo', 'Odsúhlasenie banky – J-Účtovníctvo', 'Odsúhlasenie banky', JUC_CAP, 'j-uctovnictvo-recon', 'juc_page_recon' );
}, 20 );

/** Presmerovanie s oznamom (zobrazí juc_notice ako informáciu). */
function juc_back_msg( $url, $msg ) {
	set_transient( 'juc_err_' . get_current_user_id(), $msg, 120 );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'auto', $url ) );
	exit;
}

/* ---------------------------------------------------------------- Termíny */

/**
 * Termíny pre s. r. o. (kalendárny rok, neplatiteľ DPH) za účtovné obdobie $year: [ dátum, text, popis ].
 * Lehoty podľa zákona o dani z príjmov a zákona o účtovníctve – overte pri zmene zákonov.
 */
function juc_deadlines( $year ) {
	$n    = (int) $year + 1;
	$ext  = (string) get_option( 'juc_deadline_ext_' . (int) $year, '' ); // '' | '3' | '6' – predĺženie lehoty na priznanie
	$dppo = '6' === $ext ? $n . '-09-30' : ( '3' === $ext ? $n . '-06-30' : $n . '-03-31' );
	$out  = array(
		array( $n . '-03-31', 'Predĺženie lehoty na daňové priznanie', 'Ak chcete podať priznanie neskôr, oznámte to správcovi dane najneskôr do 31. 3. (predĺženie o 3 mesiace, pri príjmoch zo zahraničia o 6 mesiacov).' ),
		array( $dppo, 'Daňové priznanie k dani z príjmov PO a zaplatenie dane', 'Podáva sa elektronicky cez finančnú správu spolu s účtovnou závierkou (súvaha, výkaz ziskov a strát, poznámky). V tej istej lehote sa platí daň alebo daňová licencia.' ),
		array( $dppo, 'Účtovná závierka do registra účtovných závierok', 'Závierka podaná s daňovým priznaním sa do registra uloží automaticky.' ),
		array( $n . '-06-30', 'Schválenie účtovnej závierky valným zhromaždením', 'Valné zhromaždenie schvaľuje závierku a rozhoduje o rozdelení zisku alebo úhrade straty (lehotu overte v spoločenskej zmluve). Rozdelenie zaúčtujete v Termíny a daň → Rozdelenie výsledku.' ),
		array( $n . '-07-31', 'Uloženie schválenia závierky a rozdelenia výsledku', 'Do registra účtovných závierok sa ukladá aj rozhodnutie o schválení závierky a o rozdelení zisku (overte lehotu s účtovníkom).' ),
	);
	// Preddavky na daň v nasledujúcom roku podľa poslednej známej daňovej povinnosti.
	$adv = juc_tax_advances( $year );
	if ( $adv['freq'] ) {
		$months = 'month' === $adv['freq'] ? range( 1, 12 ) : array( 3, 6, 9, 12 );
		foreach ( $months as $m ) {
			$out[] = array( gmdate( 'Y-m-t', strtotime( $n . '-' . sprintf( '%02d', $m ) . '-01' ) ), 'Preddavok na daň z príjmov ' . juc_money( $adv['amount'] ) . ' €', 'Preddavky podľa dane za rok ' . (int) $year . ' (' . ( 'month' === $adv['freq'] ? 'mesačne' : 'štvrťročne' ) . '), platia od mesiaca po podaní priznania.' );
		}
	}
	usort( $out, fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
	return $out;
}

/**
 * Preddavky na daň z príjmov PO (§ 42 ZDP): posledná známa daňová povinnosť nad 16 600 € → mesačne 1/12,
 * nad 5 000 € → štvrťročne 1/4, inak sa neplatia. Vráti [ freq (''|quarter|month), amount, tax ].
 */
function juc_tax_advances( $year ) {
	$y   = function_exists( 'juc_year_by_num' ) ? juc_year_by_num( $year ) : null;
	$tax = 0.0;
	if ( $y && function_exists( 'juc_close_tax' ) ) {
		$t   = juc_close_tax( $y );
		$tax = max( 0.0, (float) $t['calc'] ); // licencia (minimálna daň) sa do preddavkov nezapočítava
	}
	if ( $tax > 16600 ) {
		return array( 'freq' => 'month', 'amount' => round( $tax / 12, 2 ), 'tax' => $tax );
	}
	if ( $tax > 5000 ) {
		return array( 'freq' => 'quarter', 'amount' => round( $tax / 4, 2 ), 'tax' => $tax );
	}
	return array( 'freq' => '', 'amount' => 0.0, 'tax' => $tax );
}

/** Účtové obdobie podľa roka (bez vytvárania nového). */
function juc_year_by_num( $year ) {
	foreach ( (array) juc_years() as $y ) {
		if ( (int) substr( $y['start_date'], 0, 4 ) === (int) $year ) {
			return $y;
		}
	}
	return null;
}

/** Najbližšie termíny (budúce, najviac $n) z minulého a bežného roka. */
function juc_next_deadlines( $n = 3 ) {
	$today = current_time( 'Y-m-d' );
	$yr    = (int) current_time( 'Y' );
	$all   = array_merge( juc_deadlines( $yr - 1 ), juc_deadlines( $yr ) );
	$all   = array_values( array_filter( $all, fn( $d ) => $d[0] >= $today ) );
	usort( $all, fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
	return array_slice( $all, 0, $n );
}

/* ---------------------------------------------------------------- Prehľad v ľudskej reči */

/** Pohľadávky / záväzky po splatnosti z J-Fakturácie: [ suma, počet ]. */
function juc_overdue_docs( $received = false ) {
	global $wpdb;
	if ( ! function_exists( 'jinv_table' ) ) {
		return array( 0.0, 0 );
	}
	$p    = jinv_table( 'payments' );
	$kind = $received ? "kind = 'received'" : "kind = 'issued'";
	$cod  = $received ? '' : $wpdb->prepare( ' AND data NOT LIKE %s AND data NOT LIKE %s', '%"pay_code":"cod"%', '%"pay_code":"jetpack_custom_gateway"%' );
	$row  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, COALESCE(SUM(i.total - COALESCE((SELECT SUM(amount) FROM $p WHERE doc_id = i.id),0)),0) s FROM " . jinv_table() . " i WHERE $kind AND status = 'issued' AND due_date < %s$cod", current_time( 'Y-m-d' ) ), ARRAY_A ); // phpcs:ignore
	return array( round( (float) $row['s'], 2 ), (int) $row['n'] );
}

/** Blok „Ako sa darí firme“ na prehľade účtovníctva. */
function juc_render_owner_summary() {
	$y = juc_current_year();
	if ( ! $y ) {
		return;
	}
	$t      = function_exists( 'juc_close_tax' ) ? juc_close_tax( $y ) : null;
	$recv   = juc_overdue_docs( false );
	$pay    = juc_overdue_docs( true );
	$dead   = juc_next_deadlines( 3 );
	$yr     = substr( $y['start_date'], 0, 4 );
	$profit = $t ? (float) $t['vh'] : 0.0;
	echo '<div class="juc-owner"><h2>Ako sa darí firme v roku ' . esc_html( $yr ) . '</h2><ul>';
	if ( $t ) {
		echo '<li>' . ( $profit >= 0 ? 'Zisk' : 'Strata' ) . ' od začiatku roka (pred zdanením): <strong>' . esc_html( juc_money( abs( $profit ) ) ) . ' €</strong> – výnosy ' . esc_html( juc_money( $t['revenue'] ) ) . ' €, náklady ' . esc_html( juc_money( $t['cost'] ) ) . ' €.</li>';
		echo '<li>Odhad dane z príjmov za rok (ak by rok skončil dnes): <strong>' . esc_html( juc_money( $t['final'] ) ) . ' €</strong>' . ( $t['final'] > $t['calc'] ? ' (daňová licencia – minimálna daň)' : ' (sadzba ' . esc_html( (string) round( $t['rate'] * 100 ) ) . ' %)' ) . '. <a href="' . esc_url( juc_url( 'j-uctovnictvo-tools', array( 'year' => $yr ) ) ) . '">Výpočet</a></li>';
	}
	echo '<li>Zákazníci nezaplatili po splatnosti: <strong>' . esc_html( juc_money( $recv[0] ) ) . ' €</strong>' . ( $recv[1] ? ' (' . (int) $recv[1] . ' ' . ( 1 === $recv[1] ? 'faktúra' : ( $recv[1] < 5 ? 'faktúry' : 'faktúr' ) ) . ') – <a href="' . esc_url( admin_url( 'admin.php?page=j-invoices&st=overdue' ) ) . '">upomienky</a>' : '' ) . '.</li>';
	echo '<li>Vy ste nezaplatili dodávateľom po splatnosti: <strong>' . esc_html( juc_money( $pay[0] ) ) . ' €</strong>' . ( $pay[1] ? ' (' . (int) $pay[1] . ') – <a href="' . esc_url( admin_url( 'admin.php?page=j-invoices-received' ) ) . '">prijaté faktúry</a>' : '' ) . '.</li>';
	if ( $dead ) {
		echo '<li>Najbližšie termíny: ';
		$parts = array();
		foreach ( $dead as $d ) {
			$parts[] = '<strong>' . esc_html( juc_date( $d[0] ) ) . '</strong> ' . esc_html( $d[1] );
		}
		echo implode( '; ', $parts ) . ' – <a href="' . esc_url( juc_url( 'j-uctovnictvo-tools' ) ) . '">všetky termíny</a>.</li>'; // phpcs:ignore
	}
	echo '</ul><p class="description">Orientačné údaje z účtovníctva – nie sú náhradou za účtovnú závierku.</p></div>';
}

/* ---------------------------------------------------------------- Odsúhlasenie banky */

/** Zostatok podľa bankového výpisu (Fio) k dátumu: [ zostatok alebo null, od kedy sú pohyby ]. */
function juc_statement_balance( $account_id, $date ) {
	global $wpdb;
	if ( ! function_exists( 'jinv_bank_account' ) ) {
		return array( null, '' );
	}
	$acc     = jinv_bank_account( $account_id );
	$opening = (array) ( $acc['opening'] ?? array() );
	ksort( $opening );
	$base = null;
	$from = '';
	foreach ( $opening as $d => $v ) {
		if ( $d <= $date ) {
			$base = (float) $v;
			$from = $d;
		}
	}
	if ( null === $base ) {
		return array( null, $opening ? (string) array_key_first( $opening ) : '' );
	}
	$sum = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . jinv_table( 'banktx' ) . ' WHERE account_id = %s AND tx_date >= %s AND tx_date <= %s', $account_id, $from, $date ) ); // phpcs:ignore
	return array( round( $base + $sum, 2 ), $from );
}

/**
 * Odsúhlasenie banky k dátumu: [ [ názov, analytický účet, výpis, účtovníctvo, rozdiel ], … ].
 * Výpis = počiatočný zostatok z Fio + pohyby; účtovníctvo = zostatok analytického účtu 221xxx.
 */
function juc_bank_recon( $date ) {
	$out = array();
	if ( ! function_exists( 'jinv_bank_accounts' ) ) {
		return $out;
	}
	$map = (array) juc_opt( 'bank_map' );
	foreach ( jinv_bank_accounts() as $id => $a ) {
		if ( empty( $map[ $id ] ) ) {
			continue;
		}
		list( $st ) = juc_statement_balance( $id, $date );
		$led        = juc_account_balance( $map[ $id ], $date );
		$out[]      = array( (string) ( $a['name'] ?? $id ), $map[ $id ], $st, $led, null === $st ? null : round( $led - $st, 2 ) );
	}
	return $out;
}

function juc_page_recon() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y  = juc_current_year();
	$yr = (int) substr( $y['start_date'], 0, 4 );
	juc_head( 'Odsúhlasenie banky' );
	juc_year_select( 'j-uctovnictvo-recon' );
	echo '<p>Zostatok bankového účtu podľa výpisu (počiatočný zostatok z banky + načítané pohyby) oproti zostatku účtu 221 v účtovníctve ku koncu každého mesiaca. Rozdiel znamená pohyb, ktorý ešte nie je zaúčtovaný (pozrite <a href="' . esc_url( juc_url( 'j-uctovnictvo-queue' ) ) . '">Na zaúčtovanie</a>), alebo ručný zápis na 221 bez pohybu v banke.</p>';
	$today = current_time( 'Y-m-d' );
	$rows  = array();
	for ( $m = 1; $m <= 12; $m++ ) {
		$end = gmdate( 'Y-m-t', strtotime( $yr . '-' . sprintf( '%02d', $m ) . '-01' ) );
		if ( $end > $today ) {
			$end = $today;
		}
		if ( $end < $y['start_date'] ) {
			continue;
		}
		foreach ( juc_bank_recon( $end ) as $r ) {
			$rows[] = array_merge( array( $end ), $r );
		}
		if ( $end === $today ) {
			break;
		}
	}
	if ( ! $rows ) {
		echo '<div class="notice notice-info inline"><p>Žiadny pripojený bankový účet (J-Fakturácia → Banka) alebo banka ešte nemá zaúčtované pohyby.</p></div></div>';
		return;
	}
	echo '<table class="widefat striped juc-table"><thead><tr><th>K dátumu</th><th>Účet</th><th class="n">Podľa výpisu</th><th class="n">V účtovníctve</th><th class="n">Rozdiel</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$diff = $r[5];
		$ok   = null !== $diff && abs( $diff ) < 0.005;
		echo '<tr><td>' . esc_html( juc_date( $r[0] ) ) . '</td><td>' . esc_html( $r[1] . ' (' . $r[2] . ')' ) . '</td><td class="n">' . ( null === $r[3] ? '<span class="juc-muted">pohyby nenačítané</span>' : esc_html( juc_money( $r[3] ) ) ) . '</td><td class="n">' . esc_html( juc_money( $r[4] ) ) . '</td>'
			. '<td class="n" style="color:' . ( $ok ? '#1e7e34' : '#b32d2e' ) . ';font-weight:600">' . ( null === $diff ? '—' : ( $ok ? 'sedí' : esc_html( juc_money( $diff ) ) ) ) . '</td>'
			. '<td><a href="' . esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $r[2], 'to' => $r[0] ) ) ) . '">karta účtu</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ---------------------------------------------------------------- Kontrolný zoznam uzávierky mesiaca */

/** Kontroly pred uzavretím mesiaca $ym (RRRR-MM): [ [ 'ok'|'warn'|'err', text, odkaz ], … ]. */
function juc_month_checks( $ym ) {
	global $wpdb;
	$end = gmdate( 'Y-m-t', strtotime( $ym . '-01' ) );
	$out = array();
	// Na zaúčtovanie (pohyby v banke do konca mesiaca).
	$q = function_exists( 'juc_queue_bank' ) ? count( array_filter( juc_queue_bank(), fn( $t ) => $t['tx_date'] <= $end ) ) : 0;
	$out[] = array( $q ? 'err' : 'ok', $q ? 'Na zaúčtovanie čaká ' . $q . ' pohybov v banke do ' . juc_date( $end ) . '.' : 'Všetky pohyby v banke do konca mesiaca sú zaúčtované.', juc_url( 'j-uctovnictvo-queue' ) );
	// Chyby automatického účtovania.
	$errs = count( (array) get_option( 'juc_autopost_errors', array() ) );
	$out[] = array( $errs ? 'err' : 'ok', $errs ? 'Automatické účtovanie hlási ' . $errs . ' chýb.' : 'Automatické účtovanie bez chýb.', juc_url( 'j-uctovnictvo-queue' ) );
	// Banka sedí s výpisom.
	$bad = array_filter( juc_bank_recon( $end ), fn( $r ) => null !== $r[4] && abs( $r[4] ) >= 0.005 );
	$out[] = array( $bad ? 'err' : 'ok', $bad ? 'Banka nesedí s výpisom k ' . juc_date( $end ) . ' (rozdiel ' . juc_money( reset( $bad )[4] ) . ' €).' : 'Banka sedí s výpisom k ' . juc_date( $end ) . '.', juc_url( 'j-uctovnictvo-recon' ) );
	// Pokladňa nie je záporná v žiadny deň mesiaca.
	$cash = juc_acc_opt( 'acc_cash', '211' );
	$neg  = '';
	$yid  = (int) ( juc_year_of( $end )['id'] ?? 0 );
	if ( $yid ) {
		$days = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT entry_date FROM ' . juc_t( 'lines' ) . ' WHERE year_id = %d AND entry_date BETWEEN %s AND %s AND (md LIKE %s OR d LIKE %s) ORDER BY entry_date', $yid, $ym . '-01', $end, $cash . '%', $cash . '%' ) ); // phpcs:ignore
		foreach ( $days as $d ) {
			if ( juc_account_balance( $cash, $d, $yid ) < -0.004 ) {
				$neg = $d;
				break;
			}
		}
	}
	$out[] = array( $neg ? 'err' : 'ok', $neg ? 'Pokladňa je záporná ' . juc_date( $neg ) . ' – chýba príjmový doklad alebo je výdaj zaúčtovaný zle.' : 'Pokladňa nie je záporná.', juc_url( 'j-uctovnictvo-cash' ) );
	// Úlohy s vysokou prioritou.
	if ( function_exists( 'juc_issues_count' ) ) {
		list( $open, $late ) = juc_issues_count();
		$out[] = array( $late ? 'warn' : 'ok', $late ? $late . ' úloh na riešenie je po termíne.' : ( $open ? $open . ' otvorených úloh na riešenie (bez omeškania).' : 'Žiadne úlohy na riešenie.' ), juc_url( 'j-uctovnictvo-issues' ) );
	}
	// Ručné zmeny zásob v J-Sklade (úprava produktu, import, iný doplnok) sa neúčtujú – pri spôsobe A rozídu 132 so skladom.
	if ( 'A' === juc_opt( 'stock_method' ) && function_exists( 'jsk_t' ) ) {
		$man = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(m.qty * COALESCE(m.cost, CAST(c.meta_value AS DECIMAL(14,4)), 0)),0) FROM ' . jsk_t( 'moves' ) . " m LEFT JOIN {$wpdb->postmeta} c ON c.post_id = m.product_id AND c.meta_key = '_jsk_cost' WHERE m.source IN ('manual','other') AND m.created_at BETWEEN %s AND %s", $ym . '-01 00:00:00', $end . ' 23:59:59' ) ); // phpcs:ignore
		$out[] = array( abs( $man ) >= 0.01 ? 'warn' : 'ok', abs( $man ) >= 0.01 ? sprintf( 'Ručné zmeny zásob v J-Sklade za mesiac (%s € v nákupných cenách) nie sú v účtovníctve – zaúčtujte ich príjemkou, výdajkou alebo inventúrou, inak účet 132 nesedí so skladom.', juc_money( $man ) ) : 'Žiadne nezaúčtované ručné zmeny zásob.', admin_url( 'admin.php?page=j-sklad' ) );
	}
	// Odpisy majetku za mesiac.
	if ( function_exists( 'juc_assets' ) ) {
		$posted = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . juc_t( 'entries' ) . " WHERE source = 'asset' AND status = 'posted' AND entry_date BETWEEN %s AND %s", $ym . '-01', $end ) ); // phpcs:ignore
		$cards  = count( (array) juc_assets() );
		$out[]  = array( $cards && ! $posted ? 'warn' : 'ok', $cards ? ( $posted ? 'Odpisy majetku za mesiac sú zaúčtované.' : 'Odpisy majetku za mesiac ešte nie sú zaúčtované.' ) : 'Firma neeviduje majetok na odpisovanie.', juc_url( 'j-uctovnictvo-assets' ) );
	}
	return $out;
}

/** Kontrolný zoznam (HTML) – zobrazuje sa na stránke Uzávierka pri uzávierke mesiaca. */
function juc_render_month_checks( $ym = '' ) {
	$lock = (string) juc_opt( 'lock_date' );
	$ym   = $ym ? $ym : ( $lock ? gmdate( 'Y-m', strtotime( $lock . ' +1 day' ) ) : current_time( 'Y-m' ) );
	if ( $ym > current_time( 'Y-m' ) ) {
		$ym = current_time( 'Y-m' );
	}
	$icons = array( 'ok' => array( '✔', '#1e7e34' ), 'warn' => array( '!', '#9a6700' ), 'err' => array( '✖', '#b32d2e' ) );
	echo '<div class="juc-checks"><p><strong>Kontrola pred uzavretím mesiaca ' . esc_html( substr( $ym, 5, 2 ) . '/' . substr( $ym, 0, 4 ) ) . '</strong></p><ul style="margin:0 0 8px">';
	$err = false;
	foreach ( juc_month_checks( $ym ) as $c ) {
		$err = $err || 'err' === $c[0];
		echo '<li><span style="display:inline-block;width:18px;font-weight:700;color:' . esc_attr( $icons[ $c[0] ][1] ) . '">' . esc_html( $icons[ $c[0] ][0] ) . '</span> ' . esc_html( $c[1] ) . ( 'ok' !== $c[0] && $c[2] ? ' <a href="' . esc_url( $c[2] ) . '">Otvoriť</a>' : '' ) . '</li>';
	}
	echo '</ul>';
	if ( ! $err ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'juc_month_lock' );
		echo '<input type="hidden" name="action" value="juc_month_lock"><input type="hidden" name="month" value="' . esc_attr( $ym ) . '"><button class="button button-primary" onclick="return confirm(\'Uzavrieť účtovníctvo do konca mesiaca? Do tohto dňa sa potom nedá účtovať ani stornovať (uzávierku môžete zrušiť).\');">Všetko v poriadku – uzavrieť mesiac ' . esc_html( substr( $ym, 5, 2 ) . '/' . substr( $ym, 0, 4 ) ) . '</button></form>';
	} else {
		echo '<p class="description">Mesiac uzavrite až po vyriešení červených bodov.</p>';
	}
	echo '</div>';
}

add_action( 'admin_post_juc_month_lock', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_month_lock' );
	$ym   = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_POST['month'] ?? '' ) ) ? $_POST['month'] : ''; // phpcs:ignore
	$back = juc_url( 'j-uctovnictvo-closing' );
	if ( ! $ym ) {
		juc_back_err( $back, 'Neplatný mesiac.' );
	}
	foreach ( juc_month_checks( $ym ) as $c ) {
		if ( 'err' === $c[0] ) {
			juc_back_err( $back, 'Mesiac sa neuzavrel: ' . $c[1] );
		}
	}
	$end = gmdate( 'Y-m-t', strtotime( $ym . '-01' ) );
	if ( (string) juc_opt( 'lock_date' ) < $end ) {
		juc_settings_update( array( 'lock_date' => $end ) );
	}
	juc_back_msg( $back, 'Účtovníctvo je uzavreté do ' . juc_date( $end ) . '.' );
} );

/* ---------------------------------------------------------------- Stránka Termíny a daň */

function juc_page_tools() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y  = juc_current_year();
	$yr = (int) substr( $y['start_date'], 0, 4 );
	juc_head( 'Termíny a daň' );
	juc_year_select( 'j-uctovnictvo-tools' );
	$t     = juc_close_tax( $y );
	$adv   = juc_tax_advances( $yr );
	$today = current_time( 'Y-m-d' );
	$ext   = (string) get_option( 'juc_deadline_ext_' . $yr, '' );
	?>
	<h2>Termíny za rok <?php echo (int) $yr; ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 8px">
		<?php wp_nonce_field( 'juc_deadline_ext' ); ?><input type="hidden" name="action" value="juc_deadline_ext"><input type="hidden" name="year" value="<?php echo (int) $yr; ?>">
		Lehota na daňové priznanie: <select name="ext"><option value="">31. 3. (bez predĺženia)</option><option value="3" <?php selected( $ext, '3' ); ?>>predĺžená o 3 mesiace</option><option value="6" <?php selected( $ext, '6' ); ?>>predĺžená o 6 mesiacov (príjmy zo zahraničia)</option></select> <button class="button">Uložiť</button>
	</form>
	<table class="widefat striped juc-table" style="max-width:980px"><thead><tr><th style="width:110px">Termín</th><th>Čo</th><th>Poznámka</th></tr></thead><tbody>
	<?php foreach ( juc_deadlines( $yr ) as $d ) : ?>
		<tr<?php echo $d[0] < $today ? ' style="opacity:.55"' : ''; ?>><td><strong><?php echo esc_html( juc_date( $d[0] ) ); ?></strong></td><td><?php echo esc_html( $d[1] ); ?></td><td><?php echo esc_html( $d[2] ); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<p class="description">Termíny pre s. r. o. s kalendárnym rokom, ktorá nie je platiteľom DPH. Ak 31. 3. pripadne na víkend, lehota sa posúva na najbližší pracovný deň. Overte ich s účtovníkom.</p>

	<h2>Daň z príjmov PO – pomôcka k priznaniu (<?php echo (int) $yr; ?>)</h2>
	<table class="widefat juc-table" style="max-width:720px"><tbody>
		<tr><td>Výnosy (účtová trieda 6)</td><td class="n"><?php echo esc_html( juc_money( $t['revenue'] ) ); ?> €</td></tr>
		<tr><td>Náklady (účtová trieda 5, bez dane z príjmov)</td><td class="n"><?php echo esc_html( juc_money( $t['cost'] ) ); ?> €</td></tr>
		<tr><th>Výsledok hospodárenia pred zdanením (r. 100 priznania)</th><th class="n"><?php echo esc_html( juc_money( $t['vh'] ) ); ?> €</th></tr>
		<tr><td>+ nedaňové náklady z účtov (napr. 513, 543, 545, 549…)</td><td class="n"><?php echo esc_html( juc_money( $t['nondeduct_acc'] ) ); ?> €</td></tr>
		<tr><td>+ ďalšie pripočítateľné položky (zadané v Uzávierke)</td><td class="n"><?php echo esc_html( juc_money( $t['nondeduct'] ) ); ?> €</td></tr>
		<tr><td>− výnosy, ktoré nie sú predmetom dane</td><td class="n"><?php echo esc_html( juc_money( $t['nontax_rev'] ) ); ?> €</td></tr>
		<tr><th>Základ dane</th><th class="n"><?php echo esc_html( juc_money( $t['base'] ) ); ?> €</th></tr>
		<tr><td>− odpočet daňovej straty z minulých rokov</td><td class="n"><?php echo esc_html( juc_money( $t['loss'] ) ); ?> €</td></tr>
		<tr><th>Základ dane po odpočte straty</th><th class="n"><?php echo esc_html( juc_money( $t['base2'] ) ); ?> €</th></tr>
		<tr><td>Daň podľa sadzby <?php echo esc_html( (string) round( $t['rate'] * 100 ) ); ?> %</td><td class="n"><?php echo esc_html( juc_money( $t['calc'] ) ); ?> €</td></tr>
		<tr><td>Daňová licencia (minimálna daň)</td><td class="n"><?php echo esc_html( juc_money( $t['min'] ) ); ?> €</td></tr>
		<tr><th>Daň na úhradu</th><th class="n"><?php echo esc_html( juc_money( $t['final'] ) ); ?> €</th></tr>
		<tr><td>Zaúčtované na 591 (daň z príjmov)</td><td class="n"><?php echo esc_html( juc_money( $t['posted'] ) ); ?> €</td></tr>
		<tr><td>Preddavky na ďalší rok</td><td class="n"><?php echo $adv['freq'] ? esc_html( ( 'month' === $adv['freq'] ? 'mesačne ' : 'štvrťročne ' ) . juc_money( $adv['amount'] ) . ' €' ) : 'neplatia sa (daň do 5 000 €)'; ?></td></tr>
	</tbody></table>
	<p class="description">Čísla vyplníte do daňového priznania k dani z príjmov PO (typ B) na portáli finančnej správy. Pripočítateľné položky a odpočet straty zadáte a daň zaúčtujete v <a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-closing' ) ); ?>">Uzávierke</a>. Ide o pomôcku – priznanie pred podaním skontrolujte s účtovníkom.</p>

	<?php juc_render_result_split( $yr ); ?>
	</div>
	<?php
}

add_action( 'admin_post_juc_deadline_ext', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_deadline_ext' );
	$yr  = (int) ( $_POST['year'] ?? 0 );
	$ext = in_array( (string) ( $_POST['ext'] ?? '' ), array( '3', '6' ), true ) ? (string) $_POST['ext'] : ''; // phpcs:ignore
	update_option( 'juc_deadline_ext_' . $yr, $ext, false );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-tools', array( 'year' => $yr ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Rozdelenie výsledku hospodárenia */

/**
 * Rozdelenie výsledku minulého roka (účet 431) podľa rozhodnutia valného zhromaždenia:
 * zisk → 421 (rezervný fond), 428 (nerozdelený zisk), 364 (podiely na zisku – dividendy);
 * strata → 429 (neuhradená strata minulých rokov) alebo 428 (úhrada z nerozdeleného zisku).
 */
function juc_render_result_split( $yr ) {
	$nx  = $yr + 1;
	$ny  = juc_year_by_num( $nx );
	$bal = $ny ? juc_account_balance( '431', '', (int) $ny['id'] ) : 0.0; // Dal = zisk (záporný zostatok)
	echo '<h2>Rozdelenie výsledku hospodárenia za rok ' . (int) $yr . '</h2>';
	if ( ! $ny ) {
		echo '<p>Rozdelenie sa účtuje v roku ' . (int) $nx . ' po ročnej uzávierke roka ' . (int) $yr . ' (výsledok sa prenesie na účet 431).</p>';
		return;
	}
	if ( abs( $bal ) < 0.005 ) {
		echo '<p>Na účte 431 v roku ' . (int) $nx . ' nie je nerozdelený výsledok (ešte nebola ročná uzávierka roka ' . (int) $yr . ', alebo je už rozdelený).</p>';
		return;
	}
	$profit = $bal < 0;
	$amt    = abs( $bal );
	?>
	<p><?php echo $profit ? 'Zisk' : 'Strata'; ?> za rok <?php echo (int) $yr; ?> na účte 431: <strong><?php echo esc_html( juc_money( $amt ) ); ?> €</strong>. Zaúčtujte rozhodnutie valného zhromaždenia (dátum rozhodnutia).</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel" style="max-width:720px">
		<?php wp_nonce_field( 'juc_result_split' ); ?><input type="hidden" name="action" value="juc_result_split"><input type="hidden" name="year" value="<?php echo (int) $yr; ?>">
		<p><label>Dátum rozhodnutia valného zhromaždenia<br><input type="date" name="date" required min="<?php echo esc_attr( $ny['start_date'] ); ?>" max="<?php echo esc_attr( $ny['end_date'] ); ?>"></label></p>
		<?php if ( $profit ) : ?>
			<p><label>Do rezervného fondu (421) <input type="text" name="a421" inputmode="decimal" style="width:120px"> €</label></p>
			<p><label>Podiely na zisku – dividendy spoločníkom (364) <input type="text" name="a364" inputmode="decimal" style="width:120px"> €</label><br><small>Z dividend sa zráža daň – výšku a odvod overte s účtovníkom.</small></p>
			<p>Zvyšok do nerozdeleného zisku (428).</p>
		<?php else : ?>
			<p><label>Uhradiť z nerozdeleného zisku minulých rokov (428) <input type="text" name="a428" inputmode="decimal" style="width:120px"> €</label></p>
			<p>Zvyšok ako neuhradená strata minulých rokov (429).</p>
		<?php endif; ?>
		<p><button class="button button-primary" onclick="return confirm('Zaúčtovať rozdelenie výsledku?');">Zaúčtovať rozdelenie</button></p>
	</form>
	<?php
}

add_action( 'admin_post_juc_result_split', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_result_split' );
	$yr   = (int) ( $_POST['year'] ?? 0 );
	$back = juc_url( 'j-uctovnictvo-tools', array( 'year' => $yr ) );
	$ny   = juc_year_by_num( $yr + 1 );
	$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['date'] ?? '' ) ) ? $_POST['date'] : ''; // phpcs:ignore
	if ( ! $ny || ! $date || $date < $ny['start_date'] || $date > $ny['end_date'] ) {
		juc_back_err( $back, 'Zadajte dátum rozhodnutia v roku ' . ( $yr + 1 ) . '.' );
	}
	$num  = fn( $k ) => round( max( 0, (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) wp_unslash( $_POST[ $k ] ?? '0' ) ) ), 2 ); // phpcs:ignore
	$bal  = juc_account_balance( '431', '', (int) $ny['id'] );
	$amt  = round( abs( $bal ), 2 );
	$text = 'Rozdelenie výsledku hospodárenia za rok ' . $yr;
	$lines = array();
	if ( $bal < 0 ) { // zisk
		$a421 = $num( 'a421' );
		$a364 = $num( 'a364' );
		if ( $a421 + $a364 > $amt + 0.005 ) {
			juc_back_err( $back, 'Rozdelené sumy sú vyššie ako zisk ' . juc_money( $amt ) . ' €.' );
		}
		foreach ( array( '421' => $a421, '364' => $a364, '428' => round( $amt - $a421 - $a364, 2 ) ) as $acc => $v ) {
			if ( $v >= 0.005 ) {
				juc_account_ensure( $acc );
				$lines[] = array( 'md' => '431', 'd' => $acc, 'amount' => $v, 'text' => $text );
			}
		}
	} else { // strata
		$a428 = min( $num( 'a428' ), $amt );
		foreach ( array( '428' => $a428, '429' => round( $amt - $a428, 2 ) ) as $acc => $v ) {
			if ( $v >= 0.005 ) {
				juc_account_ensure( $acc );
				$lines[] = array( 'md' => $acc, 'd' => '431', 'amount' => $v, 'text' => $text );
			}
		}
	}
	if ( ! $lines ) {
		juc_back_err( $back, 'Nie je čo zaúčtovať.' );
	}
	$r = juc_post( array( 'series' => 'ID', 'entry_date' => $date, 'descr' => $text . ' (rozhodnutie valného zhromaždenia)', 'source' => 'result', 'source_type' => 'split', 'source_id' => $yr ), $lines );
	if ( is_wp_error( $r ) ) {
		juc_back_err( $back, $r->get_error_message() );
	}
	juc_back_msg( $back, 'Rozdelenie výsledku za rok ' . $yr . ' je zaúčtované.' );
} );
