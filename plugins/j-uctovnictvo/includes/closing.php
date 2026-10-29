<?php
/**
 * Uzávierka: uzávierka mesiaca (dátum, do ktorého sa už neúčtuje), ročná uzávierka a poznámky Úč MÚJ 3-01.
 *
 * Ročná uzávierka (§ 16 a § 17 zákona č. 431/2002 Z. z.):
 *  1. kontroly – banka, pokladňa, predvaha, doklady na zaúčtovanie, odpisy,
 *  2. odpisy majetku do decembra,
 *  3. zásoby pri spôsobe B: počiatočný stav 132 do nákladov (504/132), konečný stav podľa inventúry (132/504),
 *  4. splatná daň z príjmov (591/341) – sadzba 10 % / 21 % / 24 %, minimálna daň (daňová licencia § 46b),
 *  5. uzavretie účtovných kníh – výsledkové účty na 710, súvahové na 702 (séria UZ, nevstupuje do výkazov),
 *     uzavretie obdobia a otvorenie nasledujúceho roka cez 701 (počiatočné stavy, výsledok hospodárenia na 431).
 * Zápisy uzávierky sú zdroj „closing“ – opakované spustenie nič nezdvojí, zmena sumy = storno a nový zápis.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Uzávierka – J-Účtovníctvo', 'Uzávierka', JUC_CAP, 'j-uctovnictvo-closing', 'juc_page_closing' );
}, 16 );

/* ---------------------------------------------------------------- Výpočty */

/** Zostatky účtov k 31. 12. bez dokladov UZ: [ 'acc|partner|vs' => [acc, partner_id, vs, bal] ]; saldokontné účty po partneroch a VS. */
function juc_close_balances( $year_id, $saldo = true ) {
	global $wpdb;
	$l    = juc_t( 'lines' );
	$e    = juc_t( 'entries' );
	$w    = $wpdb->prepare( "l.year_id = %d AND e.series <> 'UZ'", $year_id );
	$rows = $wpdb->get_results( "SELECT acc, pid, vs, SUM(v) bal FROM (
		SELECT l.md acc, l.partner_id pid, l.vs, SUM(l.amount) v FROM $l l JOIN $e e ON e.id = l.entry_id WHERE $w GROUP BY l.md, l.partner_id, l.vs
		UNION ALL SELECT l.d acc, l.partner_id pid, l.vs, -SUM(l.amount) FROM $l l JOIN $e e ON e.id = l.entry_id WHERE $w GROUP BY l.d, l.partner_id, l.vs
	) x GROUP BY acc, pid, vs", ARRAY_A ); // phpcs:ignore
	$out = array();
	foreach ( (array) $rows as $r ) {
		// Saldokontné účty po VS (úhrada z banky nemusí mať partnera – partner sa doplní z faktúry), bez VS po partnerovi.
		$per = $saldo && preg_match( '/^(311|314|321|324|391)/', $r['acc'] );
		$key = $per ? $r['acc'] . '|' . ( '' !== (string) $r['vs'] ? 'vs:' . $r['vs'] : 'p:' . (int) $r['pid'] ) : $r['acc'] . '|';
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'acc' => $r['acc'], 'partner_id' => 0, 'vs' => $per ? (string) $r['vs'] : '', 'bal' => 0.0 );
		}
		if ( $per && (int) $r['pid'] && ! $out[ $key ]['partner_id'] ) {
			$out[ $key ]['partner_id'] = (int) $r['pid'];
		}
		$out[ $key ]['bal'] = round( $out[ $key ]['bal'] + (float) $r['bal'], 2 );
	}
	ksort( $out, SORT_STRING );
	return array_filter( $out, fn( $r ) => abs( $r['bal'] ) >= 0.005 );
}

function juc_is_result_acc( $acc ) {
	return in_array( $acc[0], array( '5', '6' ), true );
}

function juc_is_balance_acc( $acc ) {
	return in_array( $acc[0], array( '0', '1', '2', '3', '4' ), true );
}

/** Daňové parametre uzávierky roka (uložené hodnoty z formulára). */
function juc_close_opts( $year ) {
	$all = (array) get_option( 'juc_close', array() );
	return wp_parse_args( (array) ( $all[ $year ] ?? array() ), array( 'stock' => '', 'nondeduct' => '0', 'loss' => '0', 'exempt' => 0, 'tax_override' => '', 'notes' => array() ) );
}

function juc_close_opts_save( $year, array $v ) {
	$all          = (array) get_option( 'juc_close', array() );
	$all[ $year ] = array_merge( juc_close_opts( $year ), $v );
	update_option( 'juc_close', $all, false );
}

/** Minimálna daň (daňová licencia) podľa zdaniteľných príjmov – § 46b, pre obdobia od 2025 (2026: najvyššie pásmo 11 520 €). */
function juc_min_tax( $income, $year ) {
	$bands = array( array( 50000, 340 ), array( 250000, 960 ), array( 500000, 1920 ), array( 5000000, 3840 ) );
	foreach ( $bands as $b ) {
		if ( $income <= $b[0] ) {
			return $b[1];
		}
	}
	return $year >= 2026 ? 11520 : 3840;
}

/** Sadzba dane PO podľa zdaniteľných príjmov (§ 15 písm. b): do 100 000 € 10 %, do 5 mil. 21 %, nad 24 %. */
function juc_tax_rate( $income ) {
	return $income <= 100000 ? 0.10 : ( $income <= 5000000 ? 0.21 : 0.24 );
}

/** Výpočet dane z príjmov za rok. */
function juc_close_tax( array $y ) {
	$yr   = (int) substr( $y['start_date'], 0, 4 );
	$o    = juc_close_opts( $yr );
	$bals = juc_close_balances( (int) $y['id'], false );
	$rev  = 0.0;
	$cost = 0.0;
	$nd   = 0.0;
	$nt   = 0.0;
	$tax  = 0.0;
	foreach ( $bals as $b ) {
		$a = $b['acc'];
		if ( '6' === $a[0] ) {
			$rev += -$b['bal'];
			$acc  = juc_account( $a );
			if ( $acc && ! (int) $acc['tax'] ) {
				$nt += -$b['bal'];
			}
		} elseif ( '5' === $a[0] ) {
			if ( preg_match( '/^59[1-5]/', $a ) ) {
				// Daň z príjmov sa do základu nezapočítava; už zaúčtovaná daň za tento rok (591) sa zohľadní pri porovnaní.
				if ( 0 === strpos( $a, '591' ) ) {
					$tax += $b['bal'];
				}
				continue;
			}
			$cost += $b['bal'];
			$acc   = juc_account( $a );
			if ( $acc && ! (int) $acc['tax'] ) {
				$nd += $b['bal'];
			}
		}
	}
	$vh     = round( $rev - $cost, 2 );
	$base   = round( $vh + $nd + (float) $o['nondeduct'] - $nt, 2 );
	$base2  = max( 0.0, round( $base - (float) $o['loss'], 2 ) );
	$rate   = juc_tax_rate( $rev );
	$calc   = floor( $base2 * $rate * 100 ) / 100;
	$min    = $o['exempt'] ? 0.0 : (float) juc_min_tax( $rev, $yr );
	$final  = '' !== (string) $o['tax_override'] ? round( (float) $o['tax_override'], 2 ) : max( $calc, $min );
	return array(
		'revenue' => round( $rev, 2 ), 'cost' => round( $cost, 2 ), 'vh' => $vh, 'nondeduct_acc' => round( $nd, 2 ), 'nondeduct' => (float) $o['nondeduct'], 'nontax_rev' => round( $nt, 2 ),
		'base' => $base, 'loss' => (float) $o['loss'], 'base2' => $base2, 'rate' => $rate, 'calc' => $calc, 'min' => $min, 'final' => $final, 'posted' => round( $tax, 2 ),
		'vh_after' => round( $vh - $final, 2 ),
	);
}

/** Kontroly pred uzávierkou: [ [ ok(bool|null), text, link ] ]. */
function juc_close_checks( array $y ) {
	global $wpdb;
	$out = array();
	$end = $y['end_date'];
	// Predvaha.
	$t = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) s, COUNT(*) n FROM ' . juc_t( 'lines' ) . ' WHERE year_id = %d', $y['id'] ), ARRAY_A ); // phpcs:ignore
	$out[] = array( true, 'Predvaha je vyrovnaná (každý zápis má MD aj Dal) – ' . (int) $t['n'] . ' zápisov.', juc_url( 'j-uctovnictvo-trial', array( 'year' => $y['id'] ) ) );
	// Banka bez zaúčtovania.
	if ( function_exists( 'jinv_table' ) ) {
		$n = count( array_filter( juc_queue_bank(), fn( $r ) => $r['tx_date'] <= $end && $r['tx_date'] >= $y['start_date'] ) );
		$out[] = array( 0 === $n, $n ? $n . ' pohybov v banke nie je zaúčtovaných.' : 'Všetky pohyby v banke sú zaúčtované.', juc_url( 'j-uctovnictvo-queue' ) );
		$bank = juc_acc_opt( 'acc_bank', '221' );
		$book = juc_account_balance( $bank, $end, (int) $y['id'] );
		$real = null;
		if ( function_exists( 'jinv_bank_accounts' ) && function_exists( 'jinv_bank_balance_at' ) ) {
			$real = 0.0;
			foreach ( (array) jinv_bank_accounts() as $bid => $ba ) {
				$real += (float) jinv_bank_balance_at( is_array( $ba ) && ! empty( $ba['id'] ) ? $ba['id'] : $bid, min( $end, current_time( 'Y-m-d' ) ) );
			}
		}
		if ( null !== $real ) {
			$ok    = abs( $book - $real ) < 0.01;
			$out[] = array( $ok, 'Banka ' . $bank . ' k ' . juc_date( $end ) . ': účtovníctvo ' . juc_money( $book ) . ' €, výpis ' . juc_money( $real ) . ' €' . ( $ok ? ' – súhlasí.' : ' – nesúhlasí.' ), juc_url( 'j-uctovnictvo-queue' ) );
		}
	}
	// Pokladňa.
	if ( function_exists( 'juc_cash_book' ) ) {
		$cb    = juc_cash_book( (int) $y['id'] );
		$out[] = array( ! $cb['negative'], 'Pokladňa: zostatok ' . juc_money( $cb['end'] ) . ' €' . ( $cb['negative'] ? ' – počas roka bol záporný zostatok!' : '. Inventarizácia pokladne k 31. 12. (zápis o inventúre hotovosti).' ), juc_url( 'j-uctovnictvo-cash', array( 'year' => $y['id'] ) ) );
	}
	// Chyby automatického účtovania.
	$errs  = (array) get_option( 'juc_autopost_errors', array() );
	$out[] = array( ! $errs, $errs ? count( $errs ) . ' dokladov sa nepodarilo zaúčtovať automaticky.' : 'Automatické účtovanie bez chýb.', juc_url( 'j-uctovnictvo-queue' ) );
	// Odpisy.
	if ( function_exists( 'juc_assets' ) ) {
		$need = 0.0;
		$done = 0.0;
		foreach ( juc_assets() as $a ) {
			foreach ( juc_asset_months( $a ) as $m => $v ) {
				if ( substr( $m, 0, 4 ) === substr( $end, 0, 4 ) ) {
					$need += $v;
				}
			}
		}
		$done  = round( juc_account_balance( '551', $end, (int) $y['id'] ), 2 );
		$out[] = array( abs( $need - $done ) < 0.01, 'Odpisy majetku za rok: podľa plánu ' . juc_money( $need ) . ' €, zaúčtované ' . juc_money( $done ) . ' €.', juc_url( 'j-uctovnictvo-assets' ) );
	}
	// Otvorené položky Na riešenie s vysokou prioritou.
	if ( function_exists( 'juc_t' ) ) {
		$n     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . juc_t( 'issues' ) . " WHERE status IN ('open','doing') AND priority = 1" ); // phpcs:ignore
		$out[] = array( 0 === $n ? true : null, $n ? $n . ' položiek s vysokou prioritou v Na riešenie (chýbajúce doklady, otázky pre účtovníka).' : 'Žiadne naliehavé položky v Na riešenie.', juc_url( 'j-uctovnictvo-issues' ) );
	}
	// Výsledok hospodárenia minulého roka (431) – rozdelenie po schválení závierky.
	$b431 = juc_account_balance( '431', $end, (int) $y['id'] );
	if ( abs( $b431 ) >= 0.01 ) {
		$out[] = array( null, 'Na účte 431 je ' . juc_money( abs( $b431 ) ) . ' € (' . ( $b431 > 0 ? 'strata' : 'zisk' ) . ' minulého roka) – po schválení závierky valným zhromaždením rozdeľte (zisk 431/421, 428; strata 429/431).', juc_url( 'j-uctovnictvo-new' ) );
	}
	return $out;
}

/* ---------------------------------------------------------------- Zápisy uzávierky */

function juc_close_build_stock( array $y, $value ) {
	$goods = juc_acc_opt( 'acc_goods', '132' );
	$cogs  = juc_acc_opt( 'acc_cogs', '504' );
	$ps    = 0.0;
	foreach ( juc_muj_balances( (int) $y['id'], '', true ) as $acc => $b ) {
		if ( 0 === strpos( $acc, $goods ) ) {
			$ps += $b['bal'];
		}
	}
	$lines = array();
	if ( $ps > 0.004 ) {
		$lines[] = array( 'md' => $cogs, 'd' => $goods, 'amount' => round( $ps, 2 ), 'text' => 'Zásoby – počiatočný stav do nákladov (spôsob B)' );
	}
	if ( (float) $value > 0.004 ) {
		$lines[] = array( 'md' => $goods, 'd' => $cogs, 'amount' => round( (float) $value, 2 ), 'text' => 'Zásoby – konečný stav podľa inventúry k ' . juc_date( $y['end_date'] ) );
	}
	return $lines ? array( 'head' => array( 'series' => 'ID', 'entry_date' => $y['end_date'], 'doc_date' => $y['end_date'], 'descr' => 'Zásoby tovaru k ' . juc_date( $y['end_date'] ) . ' (spôsob B)' ), 'lines' => $lines ) : null;
}

function juc_close_build_tax( array $y, $amount ) {
	$amount = round( (float) $amount, 2 );
	return $amount > 0 ? array(
		'head'  => array( 'series' => 'ID', 'entry_date' => $y['end_date'], 'doc_date' => $y['end_date'], 'descr' => 'Splatná daň z príjmov za ' . substr( $y['start_date'], 0, 4 ) ),
		'lines' => array( array( 'md' => '591', 'd' => '341', 'amount' => $amount, 'text' => 'Daň z príjmov právnických osôb ' . substr( $y['start_date'], 0, 4 ) ) ),
	) : null;
}

/** Uzavretie výsledkových (710) a súvahových (702) účtov. Vráti [ built_pl, built_bs, vh ]. */
function juc_close_build_books( array $y ) {
	$bals = juc_close_balances( (int) $y['id'] );
	$end  = $y['end_date'];
	$pl   = array();
	$bs   = array();
	$vh   = 0.0;
	foreach ( $bals as $b ) {
		$a = $b['acc'];
		if ( juc_is_result_acc( $a ) ) {
			$vh  -= $b['bal'];
			$pl[] = $b['bal'] > 0 ? array( 'md' => '710', 'd' => $a, 'amount' => $b['bal'], 'text' => 'Uzavretie účtu ' . $a ) : array( 'md' => $a, 'd' => '710', 'amount' => -$b['bal'], 'text' => 'Uzavretie účtu ' . $a );
		} elseif ( juc_is_balance_acc( $a ) ) {
			$bs[] = ( $b['bal'] > 0 ? array( 'md' => '702', 'd' => $a, 'amount' => $b['bal'] ) : array( 'md' => $a, 'd' => '702', 'amount' => -$b['bal'] ) ) + array( 'text' => 'Konečný zostatok ' . $a, 'partner_id' => $b['partner_id'], 'vs' => $b['vs'] );
		}
	}
	$vh = round( $vh, 2 );
	if ( abs( $vh ) >= 0.005 ) {
		$pl[] = $vh > 0 ? array( 'md' => '710', 'd' => '702', 'amount' => $vh, 'text' => 'Výsledok hospodárenia – zisk' ) : array( 'md' => '702', 'd' => '710', 'amount' => -$vh, 'text' => 'Výsledok hospodárenia – strata' );
	}
	$yr = substr( $y['start_date'], 0, 4 );
	return array(
		$pl ? array( 'head' => array( 'series' => 'UZ', 'number' => 'UZ' . $yr . '-710', 'entry_date' => $end, 'doc_date' => $end, 'descr' => 'Uzavretie výsledkových účtov ' . $yr . ' (účet ziskov a strát 710)' ), 'lines' => $pl ) : null,
		$bs ? array( 'head' => array( 'series' => 'UZ', 'number' => 'UZ' . $yr . '-702', 'entry_date' => $end, 'doc_date' => $end, 'descr' => 'Uzavretie súvahových účtov ' . $yr . ' (konečný účet súvahový 702)' ), 'lines' => $bs ) : null,
		$vh,
	);
}

/** Otvorenie nasledujúceho roka: počiatočné stavy cez 701, výsledok hospodárenia na 431. */
function juc_close_build_open( array $y ) {
	$bals  = juc_close_balances( (int) $y['id'] );
	$next  = (int) substr( $y['start_date'], 0, 4 ) + 1;
	$date  = $next . '-01-01';
	$lines = array();
	$vh    = 0.0;
	foreach ( $bals as $b ) {
		$a = $b['acc'];
		if ( juc_is_result_acc( $a ) ) {
			$vh -= $b['bal'];
		} elseif ( juc_is_balance_acc( $a ) ) {
			$lines[] = ( $b['bal'] > 0 ? array( 'md' => $a, 'd' => '701', 'amount' => $b['bal'] ) : array( 'md' => '701', 'd' => $a, 'amount' => -$b['bal'] ) ) + array( 'text' => 'Počiatočný stav ' . $a, 'partner_id' => $b['partner_id'], 'vs' => $b['vs'] );
		}
	}
	$vh = round( $vh, 2 );
	if ( abs( $vh ) >= 0.005 ) {
		$lines[] = $vh > 0 ? array( 'md' => '701', 'd' => '431', 'amount' => $vh, 'text' => 'Výsledok hospodárenia ' . ( $next - 1 ) . ' v schvaľovaní – zisk' ) : array( 'md' => '431', 'd' => '701', 'amount' => -$vh, 'text' => 'Výsledok hospodárenia ' . ( $next - 1 ) . ' v schvaľovaní – strata' );
	}
	return $lines ? array( 'head' => array( 'series' => 'PS', 'number' => 'PS' . $next . '-UZ', 'entry_date' => $date, 'doc_date' => $date, 'descr' => 'Počiatočné stavy ' . $next . ' z uzávierky ' . ( $next - 1 ) . ' (otvorenie účtovných kníh cez 701)', 'opening' => 1 ), 'lines' => $lines ) : null;
}

/** Ročná uzávierka: uzavretie kníh, uzavretie obdobia a otvorenie ďalšieho roka. */
function juc_close_year_run( array $y ) {
	global $wpdb;
	$yr = (int) substr( $y['start_date'], 0, 4 );
	if ( 'closed' === $y['status'] ) {
		return new WP_Error( 'juc_close', 'Rok ' . $yr . ' je už uzavretý.' );
	}
	$GLOBALS['juc_importing'] = true; // uzávierka môže účtovať aj po dátume uzávierky mesiaca
	list( $pl, $bs ) = juc_close_build_books( $y );
	foreach ( array( 'pl' => $pl, 'bs' => $bs ) as $k => $b ) {
		$r = juc_sync_source( 'closing', $k, $yr, $b );
		if ( is_wp_error( $r ) ) {
			unset( $GLOBALS['juc_importing'] );
			return $r;
		}
	}
	$open = juc_close_build_open( $y );
	$r    = juc_sync_source( 'closing', 'open', $yr, $open );
	unset( $GLOBALS['juc_importing'] );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$wpdb->update( juc_t( 'years' ), array( 'status' => 'closed', 'closed_at' => current_time( 'mysql' ) ), array( 'id' => (int) $y['id'] ) );
	if ( (string) juc_opt( 'lock_date' ) < $y['end_date'] ) {
		juc_settings_update( array( 'lock_date' => $y['end_date'] ) );
	}
	do_action( 'juc_year_closed', (int) $y['id'] );
	return true;
}

/** Zrušenie uzávierky (oprava po uzavretí): otvorí rok, stornuje uzávierkové doklady a počiatočné stavy ďalšieho roka. */
function juc_close_year_undo( array $y ) {
	global $wpdb;
	$yr = (int) substr( $y['start_date'], 0, 4 );
	$wpdb->update( juc_t( 'years' ), array( 'status' => 'open', 'closed_at' => null ), array( 'id' => (int) $y['id'] ) );
	$GLOBALS['juc_importing'] = true;
	foreach ( array( 'pl', 'bs', 'open' ) as $k ) {
		$cur = juc_entry_by_source( 'closing', $k, $yr );
		if ( $cur ) {
			$r = juc_storno( (int) $cur['id'], 'zrušenie uzávierky ' . $yr );
			if ( is_wp_error( $r ) ) {
				unset( $GLOBALS['juc_importing'] );
				return $r;
			}
		}
	}
	unset( $GLOBALS['juc_importing'] );
	if ( (string) juc_opt( 'lock_date' ) >= $y['start_date'] ) {
		juc_settings_update( array( 'lock_date' => gmdate( 'Y-m-d', strtotime( $y['start_date'] . ' -1 day' ) ) ) );
	}
	return true;
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_juc_close', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_close' );
	$in   = wp_unslash( $_POST );
	$y    = juc_year( (int) ( $in['year'] ?? 0 ) );
	$step = sanitize_key( $in['step'] ?? '' );
	if ( ! $y ) {
		wp_die( 'Obdobie neexistuje.' );
	}
	$yr   = (int) substr( $y['start_date'], 0, 4 );
	$back = juc_url( 'j-uctovnictvo-closing', array( 'year' => $y['id'] ) );
	$num  = fn( $k ) => str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in[ $k ] ?? '' ) );
	$msg  = '';
	switch ( $step ) {
		case 'lock':
			$d = preg_match( '/^\d{4}-\d{2}$/', (string) ( $in['month'] ?? '' ) ) ? gmdate( 'Y-m-t', strtotime( $in['month'] . '-01' ) ) : '';
			juc_settings_update( array( 'lock_date' => $d ) );
			$msg = $d ? 'Účtovníctvo je uzavreté do ' . juc_date( $d ) . '.' : 'Uzávierka mesiaca je zrušená.';
			break;
		case 'assets':
			$r   = function_exists( 'juc_assets_sync' ) ? juc_assets_sync( $yr . '12' ) : array( 'posted' => 0, 'updated' => 0, 'errors' => array() );
			if ( $r['errors'] ) {
				juc_back_err( $back, implode( ' · ', $r['errors'] ) );
			}
			$msg = 'Odpisy do 12/' . $yr . ' zaúčtované (nové ' . $r['posted'] . ', opravené ' . $r['updated'] . ').';
			break;
		case 'stock':
			$v = round( (float) $num( 'stock' ), 2 );
			juc_close_opts_save( $yr, array( 'stock' => $v ) );
			$r = juc_sync_source( 'closing', 'stock', $yr, juc_close_build_stock( $y, $v ) );
			if ( is_wp_error( $r ) ) {
				juc_back_err( $back, $r->get_error_message() );
			}
			$msg = 'Zásoby k ' . juc_date( $y['end_date'] ) . ' zaúčtované (' . juc_money( $v ) . ' €).';
			break;
		case 'tax':
			juc_close_opts_save( $yr, array( 'nondeduct' => (float) $num( 'nondeduct' ), 'loss' => (float) $num( 'loss' ), 'exempt' => ! empty( $in['exempt'] ) ? 1 : 0, 'tax_override' => '' === trim( (string) ( $in['tax_override'] ?? '' ) ) ? '' : (float) $num( 'tax_override' ) ) );
			if ( ! empty( $in['post'] ) ) {
				$t = juc_close_tax( $y );
				$r = juc_sync_source( 'closing', 'tax', $yr, juc_close_build_tax( $y, $t['final'] ) );
				if ( is_wp_error( $r ) ) {
					juc_back_err( $back, $r->get_error_message() );
				}
				$msg = 'Daň z príjmov ' . juc_money( $t['final'] ) . ' € zaúčtovaná (591/341).';
			} else {
				$msg = 'Údaje pre výpočet dane sú uložené.';
			}
			break;
		case 'close':
			$r = juc_close_year_run( $y );
			if ( is_wp_error( $r ) ) {
				juc_back_err( $back, $r->get_error_message() );
			}
			$msg = 'Rok ' . $yr . ' je uzavretý a počiatočné stavy ' . ( $yr + 1 ) . ' sú zaúčtované.';
			break;
		case 'undo':
			$r = juc_close_year_undo( $y );
			if ( is_wp_error( $r ) ) {
				juc_back_err( $back, $r->get_error_message() );
			}
			$msg = 'Uzávierka ' . $yr . ' je zrušená – rok je znova otvorený.';
			break;
		case 'notes':
			$n = array();
			foreach ( array_keys( juc_notes_fields() ) as $k ) {
				$n[ $k ] = sanitize_textarea_field( (string) ( $in['n'][ $k ] ?? '' ) );
			}
			juc_close_opts_save( $yr, array( 'notes' => $n ) );
			$msg = 'Texty poznámok sú uložené.';
			break;
	}
	set_transient( 'juc_err_' . get_current_user_id(), $msg, 120 );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'auto', $back ) );
	exit;
} );

/* ---------------------------------------------------------------- Poznámky Úč MÚJ 3-01 */

/** Upraviteľné časti poznámok: kľúč => [ nadpis, predvolený text ]. */
function juc_notes_fields() {
	return array(
		'activity'  => array( 'Čl. I – predmet činnosti', 'Činnosti v oblasti informačných technológií, predaj tovaru a služieb cez internetový obchod.' ),
		'employees' => array( 'Čl. I – priemerný prepočítaný počet zamestnancov', '0' ),
		'changes'   => array( 'Čl. II.4 – zmeny účtovných zásad a metód', 'Spoločnosť nemenila účtovné zásady a účtovné metódy.' ),
		'errors'    => array( 'Čl. II.6 – oprava významných chýb minulých období', 'Spoločnosť neúčtovala o opravách chýb minulých účtovných období.' ),
		'unusual'   => array( 'Čl. III.1 – výnimočné položky nákladov a výnosov', 'Spoločnosť nemala položky nákladov a výnosov výnimočného rozsahu alebo výskytu.' ),
		'secured'   => array( 'Čl. III.2b – zabezpečené záväzky', 'Spoločnosť nemá zabezpečené záväzky.' ),
		'organs'    => array( 'Čl. III.4 – podmienky pôžičiek a plnení členom štatutárneho orgánu', '' ),
		'duties'    => array( 'Čl. III.5 – finančné povinnosti a podmienené záväzky mimo súvahy', 'Spoločnosť nemá významné finančné povinnosti ani podmienené záväzky, ktoré sa nevykazujú v súvahe.' ),
		'after'     => array( 'Udalosti po dni zostavenia účtovnej závierky', 'Po dni, ku ktorému sa zostavuje účtovná závierka, nenastali udalosti, ktoré by mali významný vplyv na verné zobrazenie skutočností.' ),
	);
}

/** HTML poznámok k účtovnej závierke mikro účtovnej jednotky (štruktúra podľa Úč MÚJ 3-01). */
function juc_notes_html( array $y ) {
	$s    = juc_settings();
	$yr   = (int) substr( $y['start_date'], 0, 4 );
	$o    = juc_close_opts( $yr );
	$nf   = juc_notes_fields();
	$n    = fn( $k ) => trim( (string) ( $o['notes'][ $k ] ?? '' ) ) !== '' ? $o['notes'][ $k ] : $nf[ $k ][1];
	$cur  = juc_muj_balances( (int) $y['id'], $y['end_date'] );
	$prev = juc_muj_balances( (int) $y['id'], '', true );
	$sum  = function ( array $b, $re, $credit = true ) {
		$t = 0.0;
		foreach ( $b as $acc => $v ) {
			if ( preg_match( $re, $acc ) ) {
				$t += $credit ? -$v['bal'] : $v['bal'];
			}
		}
		return round( $t, 2 );
	};
	// Záväzky: krátkodobé (32x, 33x, 34x, 36x, 37x, 379, 231, 241, 249, 461 do roka) a dlhodobé (47x, 461).
	$short = fn( $b ) => max( 0, $sum( $b, '/^(32|33|34|36|37|23|24)/' ) );
	$long  = fn( $b ) => max( 0, $sum( $b, '/^(47|46)/' ) );
	$own   = fn( $b ) => max( 0, $sum( $b, '/^355/', false ) );
	$m     = fn( $v ) => abs( $v ) < 0.005 ? '–' : juc_money( $v );
	$assets = function_exists( 'juc_assets' ) ? array_filter( juc_assets(), fn( $a ) => 'small' !== $a['kind'] ) : array();
	ob_start();
	?>
	<div class="juc-notes">
	<p style="text-align:right">Poznámky Úč MÚJ 3-01 · IČO <?php echo esc_html( $s['ico'] ); ?> · DIČ <?php echo esc_html( $s['dic'] ); ?></p>
	<h1>Poznámky k účtovnej závierke zostavenej k <?php echo esc_html( juc_date( $y['end_date'] ) ); ?></h1>
	<h2>Čl. I – Všeobecné údaje</h2>
	<p><strong>1. Základné informácie o účtovnej jednotke</strong><br>Obchodné meno: <?php echo esc_html( $s['company'] ); ?><br>Sídlo: <?php echo esc_html( trim( $s['street'] . ', ' . $s['zip'] . ' ' . $s['city'], ', ' ) ); ?><br>Dátum vzniku: <?php echo esc_html( $s['founded'] ? juc_date( $s['founded'] ) : '' ); ?><?php echo $s['register'] ? '<br>Zápis: ' . esc_html( $s['register'] ) : ''; ?><br>Predmet činnosti: <?php echo esc_html( $n( 'activity' ) ); ?><?php echo $s['statutory'] ? '<br>Štatutárny orgán: ' . esc_html( $s['statutory'] ) : ''; ?></p>
	<p><strong>2. Údaje o konsolidovanom celku</strong><br>Účtovná jednotka nie je súčasťou konsolidovaného celku.</p>
	<p><strong>3. Priemerný prepočítaný počet zamestnancov</strong></p>
	<table><tr><th>Položka</th><th class="n">Bežné obdobie</th><th class="n">Predchádzajúce obdobie</th></tr><tr><td>Priemerný prepočítaný počet zamestnancov</td><td class="n"><?php echo esc_html( $n( 'employees' ) ); ?></td><td class="n"><?php echo esc_html( $n( 'employees' ) ); ?></td></tr></table>
	<h2>Čl. II – Informácie o použitých účtovných zásadách a metódach</h2>
	<p><strong>1. Nepretržité pokračovanie v činnosti</strong><br>Účtovná závierka bola zostavená za predpokladu, že účtovná jednotka bude nepretržite pokračovať vo svojej činnosti.</p>
	<p><strong>2. Spôsob oceňovania</strong><br>
	a) Dlhodobý nehmotný a hmotný majetok obstaraný kúpou sa oceňuje obstarávacou cenou vrátane nákladov súvisiacich s obstaraním. Nehmotný majetok do 2 400 € a hmotný majetok do 1 700 € sa účtuje priamo do nákladov.<br>
	b) Zásoby sa oceňujú obstarávacou cenou; nakupované zásoby váženým aritmetickým priemerom. <?php echo 'B' === juc_opt( 'stock_method' ) ? 'Zásoby tovaru sa účtujú spôsobom B – nákup priamo do nákladov, stav zásob sa zaúčtuje pri uzávierke podľa inventúry.' : 'Zásoby tovaru sa účtujú spôsobom A.'; ?><br>
	c) Pohľadávky a záväzky sa pri vzniku oceňujú menovitou hodnotou; pohľadávky sa znižujú o pochybné a nevymožiteľné pohľadávky.<br>
	d) Peňažné prostriedky a ceniny sa oceňujú menovitou hodnotou.<br>
	e) Časové rozlíšenie nákladov a výnosov sa vykazuje vo výške potrebnej na dodržanie zásady vecnej a časovej súvislosti s účtovným obdobím.<br>
	f) Majetok a záväzky v cudzej mene sa prepočítavajú na eurá referenčným kurzom ECB alebo NBS v deň predchádzajúci dňu uskutočnenia účtovného prípadu; platby kartou v cudzej mene sa účtujú v sume odpísanej z účtu.<br>
	g) <?php echo juc_opt( 'vat_payer' ) ? 'Výnosy neobsahujú daň z pridanej hodnoty.' : 'Účtovná jednotka nie je platiteľom DPH – náklady a obstarávacia cena majetku obsahujú DPH, výnosy sa účtujú v sume bez DPH.'; ?> Výnosy sú znížené o zľavy a dobropisy.</p>
	<p><strong>3. Odpisový plán</strong><br>Odpisovať sa začína v mesiaci zaradenia majetku do používania. Účtovné odpisy sa rovnajú daňovým odpisom podľa § 26 až § 28 zákona o dani z príjmov (odpisové skupiny, rovnomerné alebo zrýchlené odpisovanie).<?php echo $assets ? '' : ' Účtovná jednotka k ' . esc_html( juc_date( $y['end_date'] ) ) . ' neeviduje dlhodobý majetok.'; ?></p>
	<?php if ( $assets ) : ?>
		<table><tr><th>Majetok</th><th>Zaradenie</th><th>Odpisovanie</th><th class="n">Vstupná cena €</th><th class="n">Oprávky €</th><th class="n">Zostatková cena €</th></tr>
		<?php foreach ( $assets as $a ) : $acc = juc_asset_acc_to( $a, $yr . '12' ); ?>
			<tr><td><?php echo esc_html( $a['inv_no'] . ' ' . $a['name'] ); ?></td><td><?php echo esc_html( juc_date( $a['in_use'] ) ); ?></td><td><?php echo esc_html( ( 'dnm' === $a['kind'] ? '' : 'sk. ' . (int) $a['dep_group'] . ', ' ) . juc_asset_years( $a ) . ' r., ' . mb_strtolower( juc_asset_methods()[ $a['method'] ] ?? '' ) ); ?></td><td class="n"><?php echo esc_html( juc_money( $a['price'] ) ); ?></td><td class="n"><?php echo esc_html( juc_money( $acc ) ); ?></td><td class="n"><?php echo esc_html( juc_money( (float) $a['price'] - $acc ) ); ?></td></tr>
		<?php endforeach; ?></table>
	<?php endif; ?>
	<p><strong>4. Zmeny účtovných zásad a metód</strong><br><?php echo nl2br( esc_html( $n( 'changes' ) ) ); ?></p>
	<p><strong>5. Dotácie</strong><br>Účtovnej jednotke neboli poskytnuté dotácie na obstaranie majetku.</p>
	<p><strong>6. Oprava významných chýb minulých účtovných období</strong><br><?php echo nl2br( esc_html( $n( 'errors' ) ) ); ?></p>
	<h2>Čl. III – Informácie, ktoré vysvetľujú a dopĺňajú súvahu a výkaz ziskov a strát</h2>
	<p><strong>1. Položky výnimočného rozsahu alebo výskytu</strong><br><?php echo nl2br( esc_html( $n( 'unusual' ) ) ); ?></p>
	<p><strong>2. a) Záväzky podľa zostatkovej doby splatnosti</strong></p>
	<table><tr><th>Položka</th><th class="n">Bežné obdobie €</th><th class="n">Predchádzajúce obdobie €</th></tr>
		<tr><td>Dlhodobé záväzky spolu</td><td class="n"><?php echo esc_html( $m( $long( $cur ) ) ); ?></td><td class="n"><?php echo esc_html( $m( $long( $prev ) ) ); ?></td></tr>
		<tr><td>Krátkodobé záväzky spolu (splatnosť do 1 roka)</td><td class="n"><?php echo esc_html( $m( $short( $cur ) ) ); ?></td><td class="n"><?php echo esc_html( $m( $short( $prev ) ) ); ?></td></tr>
	</table>
	<p><strong>b) Zabezpečené záväzky</strong><br><?php echo nl2br( esc_html( $n( 'secured' ) ) ); ?></p>
	<p><strong>3. Vlastné akcie a obchodné podiely</strong><br>Účtovná jednotka nenadobudla vlastné akcie ani vlastné obchodné podiely.</p>
	<p><strong>4. Plnenia členom štatutárneho orgánu</strong></p>
	<table><tr><th>Druh plnenia</th><th class="n">Štatutárny orgán – bežné obdobie €</th><th class="n">Predchádzajúce obdobie €</th></tr>
		<tr><td>Poskytnuté záruky alebo zabezpečenia</td><td class="n">–</td><td class="n">–</td></tr>
		<tr><td>Poskytnuté pôžičky (ku dňu účtovnej závierky)</td><td class="n">–</td><td class="n">–</td></tr>
		<tr><td>Finančné prostriedky použité na súkromné účely na vyúčtovanie (pohľadávka 355)</td><td class="n"><?php echo esc_html( $m( $own( $cur ) ) ); ?></td><td class="n"><?php echo esc_html( $m( $own( $prev ) ) ); ?></td></tr>
	</table>
	<?php if ( trim( $n( 'organs' ) ) ) : ?><p><?php echo nl2br( esc_html( $n( 'organs' ) ) ); ?></p><?php endif; ?>
	<p><strong>5. Finančné povinnosti a podmienené záväzky</strong><br><?php echo nl2br( esc_html( $n( 'duties' ) ) ); ?></p>
	<p><strong>6. Výlučné alebo osobitné práva</strong><br>Účtovnej jednotke nebolo udelené výlučné ani osobitné právo poskytovať služby vo verejnom záujme.</p>
	<p><strong>Udalosti po dni zostavenia účtovnej závierky</strong><br><?php echo nl2br( esc_html( $n( 'after' ) ) ); ?></p>
	<p style="margin-top:32px">Zostavené dňa: ____________ &nbsp; Schválené dňa: ____________ &nbsp; Podpis štatutárneho orgánu: ______________________</p>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_juc_notes_print', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$y = juc_year( (int) ( $_GET['year'] ?? 0 ) ); // phpcs:ignore
	if ( ! $y ) {
		wp_die( 'Obdobie neexistuje.' );
	}
	juc_cash_print_head( 'Poznámky Úč MÚJ 3-01 ' . substr( $y['start_date'], 0, 4 ) );
	echo '<style>.juc-notes h1{font-size:17px} .juc-notes h2{font-size:15px;margin-top:20px} .juc-notes table{margin:6px 0 12px}</style>';
	echo juc_notes_html( $y ); // phpcs:ignore
	echo '</body></html>';
	exit;
} );

/* ---------------------------------------------------------------- Stránka */

function juc_page_closing() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y      = juc_current_year();
	$yr     = (int) substr( $y['start_date'], 0, 4 );
	$o      = juc_close_opts( $yr );
	$closed = 'closed' === $y['status'];
	$lock   = (string) juc_opt( 'lock_date' );
	juc_head( 'Uzávierka' );
	juc_year_select( 'j-uctovnictvo-closing' );
	$form = function ( $step, $inner, $btn, $confirm = '', $primary = true ) use ( $y ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="juc-head-fields">';
		wp_nonce_field( 'juc_close' );
		echo '<input type="hidden" name="action" value="juc_close"><input type="hidden" name="step" value="' . esc_attr( $step ) . '"><input type="hidden" name="year" value="' . (int) $y['id'] . '">';
		echo $inner; // phpcs:ignore
		echo '<div class="juc-wide"><button class="button' . ( $primary ? ' button-primary' : '' ) . '"' . ( $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>' . esc_html( $btn ) . '</button></div></form>';
	};
	?>
	<div class="juc-panel">
		<h2 style="margin-top:4px">Uzávierka mesiaca</h2>
		<p><?php echo $lock ? 'Účtovníctvo je uzavreté do <strong>' . esc_html( juc_date( $lock ) ) . '</strong> – do tohto dňa sa už nedá účtovať ani stornovať.' : 'Žiadny mesiac nie je uzavretý.'; ?> Mesiac uzavrite po kontrole banky a dokladov (výpis z banky = účet 221, pokladňa, doklady na zaúčtovanie).</p>
		<?php $form( 'lock', '<label>Uzavrieť do konca mesiaca<br><input type="month" name="month" value="' . esc_attr( $lock ? substr( $lock, 0, 7 ) : '' ) . '"></label>', 'Uložiť', '', false ); ?>
	</div>

	<h2>Ročná uzávierka <?php echo (int) $yr; ?><?php echo $closed ? ' – uzavretá ' . esc_html( juc_date( substr( (string) $y['closed_at'], 0, 10 ) ) ) : ''; ?></h2>
	<div class="juc-panel">
		<h3 style="margin-top:4px">1. Kontroly</h3>
		<ul class="juc-checks">
		<?php foreach ( juc_close_checks( $y ) as $c ) : ?>
			<li class="<?php echo true === $c[0] ? 'juc-ok' : ( false === $c[0] ? 'juc-warn' : '' ); ?>"><?php echo true === $c[0] ? '✓' : ( false === $c[0] ? '⚠' : '•' ); ?> <?php echo esc_html( $c[1] ); ?> <?php if ( $c[2] ) : ?><a href="<?php echo esc_url( $c[2] ); ?>">otvoriť</a><?php endif; ?></li>
		<?php endforeach; ?>
		</ul>
		<p class="description">Pred uzávierkou: inventarizácia majetku, pokladne, pohľadávok a záväzkov k 31. 12. (§ 29 a § 30 zákona o účtovníctve), kontrola saldokonta 311/321 a dokladov v Na riešenie.</p>
	</div>
	<?php if ( ! $closed ) : ?>
	<div class="juc-panel">
		<h3 style="margin-top:4px">2. Odpisy majetku</h3>
		<?php $form( 'assets', '<p class="juc-wide">Zaúčtuje odpisy všetkých kariet majetku za mesiace do 12/' . (int) $yr . '.</p>', 'Zaúčtovať odpisy do decembra', '', false ); ?>
	</div>
	<?php if ( 'B' === juc_opt( 'stock_method' ) ) : $st = juc_entry_by_source( 'closing', 'stock', $yr ); ?>
	<div class="juc-panel">
		<h3 style="margin-top:4px">3. Zásoby tovaru (spôsob B)</h3>
		<p>Počiatočný stav zásob (132) sa preúčtuje do nákladov a konečný stav podľa inventúry k <?php echo esc_html( juc_date( $y['end_date'] ) ); ?> sa zaúčtuje 132 / 504.<?php echo $st ? ' Zaúčtované: ' . esc_html( $st['number'] ) . '.' : ''; ?></p>
		<?php $form( 'stock', '<label>Hodnota zásob podľa inventúry €<br><input name="stock" inputmode="decimal" style="width:140px;text-align:right" value="' . esc_attr( '' !== (string) $o['stock'] ? $o['stock'] : '' ) . '"></label>', 'Zaúčtovať zásoby' ); ?>
	</div>
	<?php endif; ?>
	<div class="juc-panel">
		<?php $t = juc_close_tax( $y ); ?>
		<h3 style="margin-top:4px">4. Daň z príjmov <?php echo (int) $yr; ?></h3>
		<table class="widefat juc-table" style="max-width:720px"><tbody>
			<tr><td>Výnosy (zdaniteľné príjmy)</td><td class="n"><?php echo esc_html( juc_money( $t['revenue'] ) ); ?> €</td></tr>
			<tr><td>Náklady (bez dane z príjmov)</td><td class="n"><?php echo esc_html( juc_money( $t['cost'] ) ); ?> €</td></tr>
			<tr><th>Výsledok hospodárenia pred zdanením</th><th class="n"><?php echo esc_html( juc_money( $t['vh'] ) ); ?> €</th></tr>
			<tr><td>+ nedaňové náklady (účty označené ako nedaňové)</td><td class="n"><?php echo esc_html( juc_money( $t['nondeduct_acc'] ) ); ?> €</td></tr>
			<tr><td>+ ďalšie pripočítateľné položky / − odpočítateľné (zadané)</td><td class="n"><?php echo esc_html( juc_money( $t['nondeduct'] ) ); ?> €</td></tr>
			<tr><td>− výnosy nepodliehajúce dani</td><td class="n"><?php echo esc_html( juc_money( $t['nontax_rev'] ) ); ?> €</td></tr>
			<tr><td>Základ dane</td><td class="n"><?php echo esc_html( juc_money( $t['base'] ) ); ?> €</td></tr>
			<tr><td>− odpočet daňovej straty</td><td class="n"><?php echo esc_html( juc_money( $t['loss'] ) ); ?> €</td></tr>
			<tr><td>Daň <?php echo (int) ( $t['rate'] * 100 ); ?> % zo základu <?php echo esc_html( juc_money( $t['base2'] ) ); ?> €</td><td class="n"><?php echo esc_html( juc_money( $t['calc'] ) ); ?> €</td></tr>
			<tr><td>Minimálna daň (daňová licencia § 46b)</td><td class="n"><?php echo esc_html( juc_money( $t['min'] ) ); ?> €</td></tr>
			<tr><th>Daň na zaúčtovanie (591/341)</th><th class="n"><?php echo esc_html( juc_money( $t['final'] ) ); ?> €</th></tr>
			<tr><td>Už zaúčtované na 591 za tento rok</td><td class="n"><?php echo esc_html( juc_money( $t['posted'] ) ); ?> €</td></tr>
			<tr><th>Výsledok hospodárenia po zdanení</th><th class="n"><?php echo esc_html( juc_money( $t['vh_after'] ) ); ?> €</th></tr>
		</tbody></table>
		<?php
		$form( 'tax',
			'<label>Pripočítateľné (+) / odpočítateľné (−) položky €<br><input name="nondeduct" style="width:140px;text-align:right" value="' . esc_attr( $o['nondeduct'] ) . '"></label>' .
			'<label>Odpočet daňovej straty €<br><input name="loss" style="width:140px;text-align:right" value="' . esc_attr( $o['loss'] ) . '"></label>' .
			'<label>Daň zadaná ručne €<br><input name="tax_override" style="width:140px;text-align:right" value="' . esc_attr( $o['tax_override'] ) . '" placeholder="podľa výpočtu"></label>' .
			'<label><br><input type="checkbox" name="exempt" value="1" ' . checked( (int) $o['exempt'], 1, false ) . '> bez minimálnej dane (prvé zdaňovacie obdobie, likvidácia…)</label>' .
			'<label><br><input type="checkbox" name="post" value="1" checked> zaúčtovať daň</label>',
			'Uložiť a zaúčtovať daň'
		);
		?>
		<p class="description">Sadzba: 10 % pri zdaniteľných príjmoch do 100 000 €, 21 % do 5 mil. €, 24 % nad 5 mil. €. Minimálna daň: do 50 000 € 340 €, do 250 000 € 960 €, do 500 000 € 1 920 €, do 5 mil. € 3 840 €, nad 5 mil. € 11 520 € – platí aj pri strate; rozdiel medzi minimálnou a vypočítanou daňou sa dá započítať v nasledujúcich 3 rokoch. Daňové priznanie DPPO sa podáva do 31. 3. nasledujúceho roka (s účtovnou závierkou). Výpočet je pomôcka – priznanie overte s účtovníkom.</p>
	</div>
	<div class="juc-panel">
		<h3 style="margin-top:4px">5. Uzavretie účtovných kníh</h3>
		<p>Výsledkové účty (5xx, 6xx) sa uzavrú na účet 710, súvahové účty na 702 (doklady UZ<?php echo (int) $yr; ?>-710 a UZ<?php echo (int) $yr; ?>-702), rok sa uzavrie a v roku <?php echo (int) $yr + 1; ?> sa zaúčtujú počiatočné stavy cez 701 (pohľadávky a záväzky po partneroch a VS, výsledok hospodárenia na 431). Výkazy (súvaha, výkaz ziskov a strát) sa zostavujú bez uzávierkových dokladov.</p>
		<?php $form( 'close', '', 'Uzavrieť rok ' . $yr, 'Uzavrieť rok ' . $yr . '? Potom sa do roka nedá účtovať (uzávierka sa dá zrušiť).' ); ?>
	</div>
	<?php else : ?>
	<div class="juc-panel">
		<p>Rok je uzavretý. Opravu po uzavretí urobte zrušením uzávierky – uzávierkové doklady a počiatočné stavy <?php echo (int) $yr + 1; ?> sa stornujú a po oprave rok znova uzavrite.</p>
		<?php $form( 'undo', '', 'Zrušiť uzávierku ' . $yr, 'Zrušiť uzávierku ' . $yr . '? Stornujú sa doklady UZ a počiatočné stavy ' . ( $yr + 1 ) . '.', false ); ?>
	</div>
	<?php endif; ?>

	<h2>Poznámky Úč MÚJ 3-01</h2>
	<div class="juc-panel">
		<p>Poznámky sa zostavia z údajov firmy, účtovníctva a evidencie majetku; texty nižšie môžete upraviť. Súvaha a výkaz ziskov a strát sú v časti <a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-muj', array( 'year' => $y['id'] ) ) ); ?>">Účtovná závierka</a>.</p>
		<p><a class="button" target="_blank" href="<?php echo esc_url( admin_url( 'admin-post.php?action=juc_notes_print&year=' . (int) $y['id'] ) ); ?>">Zobraziť a vytlačiť poznámky</a></p>
		<?php
		$inner = '';
		foreach ( juc_notes_fields() as $k => $f ) {
			$inner .= '<label class="juc-wide">' . esc_html( $f[0] ) . '<br><textarea name="n[' . esc_attr( $k ) . ']" rows="2" class="large-text" placeholder="' . esc_attr( $f[1] ) . '">' . esc_textarea( (string) ( $o['notes'][ $k ] ?? '' ) ) . '</textarea></label>';
		}
		$form( 'notes', $inner, 'Uložiť texty poznámok', '', false );
		?>
	</div>
	</div>
	<?php
}
