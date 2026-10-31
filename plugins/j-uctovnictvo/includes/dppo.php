<?php
/**
 * Daňové priznanie k dani z príjmov právnickej osoby (DPPO) a účtovná závierka mikro účtovnej jednotky (Úč MÚJ)
 * ako súbory XML na import do eDane / portálu Finančnej správy (elektronické formuláre DPPOv25 a UZMUJv14).
 *
 * Štruktúra XML zodpovedá elektronickým formulárom Finančnej správy (pfseform.financnasprava.sk, form.622 –
 * DPPOv25, form.301 – UZMUJv14): koreň <dokument>, bez menného priestoru, dátumy dd.mm.rrrr, desatinná bodka.
 * J-Účtovníctvo nič neodosiela – súbor nahrá a podpíše používateľ na portáli FS (Podanie → Import XML).
 *
 * Pozn.: formulár za rok 2026 (DPPOv26) Finančná správa zverejní začiatkom roka 2027 – do vtedy sa používa
 * štruktúra DPPOv25 (pri zmene sa upraví juc_dppo_xml()).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------- Pomocné: XML */

/** Strom poľa → XML (kľúč = element; číselné kľúče = opakované elementy rodiča). */
function juc_xml_node( $name, $value, $depth = 0 ) {
	$tab = str_repeat( "\t", $depth );
	if ( is_array( $value ) ) {
		// Zoznam opakovaných elementov (napr. riadok, riadok, riadok).
		if ( $value && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
			$out = '';
			foreach ( $value as $v ) {
				$out .= juc_xml_node( $name, $v, $depth );
			}
			return $out;
		}
		$out = $tab . '<' . $name . ">\n";
		foreach ( $value as $k => $v ) {
			$out .= juc_xml_node( (string) $k, $v, $depth + 1 );
		}
		return $out . $tab . '</' . $name . ">\n";
	}
	return $tab . '<' . $name . '>' . juc_xml_val( $value ) . '</' . $name . ">\n";
}

function juc_xml_val( $v ) {
	if ( null === $v ) {
		return '';
	}
	return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function juc_xml_doc( array $tree ) {
	return '<?xml version="1.0" encoding="utf-8"?>' . "\n" . juc_xml_node( 'dokument', $tree );
}

/** Suma pre XML: desatinná bodka, 2 miesta; prázdna pri null. */
function juc_xml_num( $v, $dec = 2 ) {
	return null === $v || '' === $v ? '' : number_format( (float) $v, $dec, '.', '' );
}

/** Dátum Y-m-d → dd.mm.rrrr. */
function juc_xml_date( $ymd ) {
	return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) ? $m[3] . '.' . $m[2] . '.' . $m[1] : '';
}

/** Ulica a súpisné/orientačné číslo zo „Sídlo – ulica a číslo“ (napr. „Hlavná 12/A“ → [Hlavná, 12/A]). */
function juc_split_street( $street ) {
	$street = trim( (string) $street );
	if ( preg_match( '/^(.*?)[\s,]+(\d[\w\/\-]*)$/u', $street, $m ) ) {
		return array( trim( $m[1] ), $m[2] );
	}
	return array( $street, '' );
}

/** SK NACE „47.91.0“ → [ '47', '91', '0' ]. */
function juc_split_nace( $nace ) {
	$p = preg_split( '/[.\s]+/', trim( (string) $nace ) );
	return array( $p[0] ?? '', $p[1] ?? '', $p[2] ?? '' );
}

/* ---------------------------------------------------------------- Údaje priznania (zadané používateľom) */

function juc_dppo_defaults() {
	return array(
		'nace'        => '',  // SK NACE, napr. 47.91.0
		'activity'    => '',  // hlavná činnosť slovom
		'micro'       => 1,   // mikrodaňovník (§ 2 písm. w ZDP)
		'related'     => 0,   // transakcie so závislými osobami (prepojenie)
		'last'        => '',  // priezvisko štatutára (oprávnenej osoby)
		'first'       => '',
		'title'       => '',
		'title_after' => '',
		'role'        => 'konateľ',
		'p_street'    => '',  // trvalý pobyt oprávnenej osoby
		'p_number'    => '',
		'p_zip'       => '',
		'p_city'      => '',
		'phone'       => '',
		'email'       => '',
		'advances'    => '',  // zaplatené preddavky (prázdne = z účtu 341)
		'refund'      => 0,   // žiadosť o vrátenie preplatku
		'iban'        => '',
		'declared'    => '',  // dátum vyhlásenia (prázdne = dnes)
		'approved'    => '',  // dátum schválenia účtovnej závierky
		'compiled'    => '',  // dátum zostavenia účtovnej závierky
		'tabA'        => array(), // ručné pripočítateľné položky: [ riadok => suma ]
		'tabB'        => array(), // ručné odpočítateľné položky: [ riadok => suma ]
		'losses'      => array(), // daňové straty minulých rokov: [ [ rok, suma, odpočítané doteraz ] ]
		'note'        => '',  // osobitné záznamy
	);
}

function juc_dppo_opts( $year ) {
	$all = (array) get_option( 'juc_dppo', array() );
	$o   = wp_parse_args( (array) ( $all[ (int) $year ] ?? array() ), juc_dppo_defaults() );
	// Predvyplnenie zo staršieho roka (údaje, ktoré sa nemenia).
	if ( '' === $o['nace'] && $all ) {
		krsort( $all );
		foreach ( $all as $y => $prev ) {
			if ( (int) $y < (int) $year ) {
				foreach ( array( 'nace', 'activity', 'last', 'first', 'title', 'title_after', 'role', 'p_street', 'p_number', 'p_zip', 'p_city', 'phone', 'email', 'iban' ) as $k ) {
					if ( '' === $o[ $k ] && ! empty( $prev[ $k ] ) ) {
						$o[ $k ] = $prev[ $k ];
					}
				}
				break;
			}
		}
	}
	// Oprávnená osoba zo „Štatutár“ v nastaveniach (napr. „Ján Novák, konateľ“).
	if ( '' === $o['last'] && function_exists( 'juc_settings' ) ) {
		$st = trim( (string) ( juc_settings()['statutory'] ?? '' ) );
		if ( $st ) {
			$parts = array_map( 'trim', explode( ',', $st, 2 ) );
			$names = preg_split( '/\s+/u', $parts[0] );
			if ( count( $names ) >= 2 ) {
				$o['last']  = (string) array_pop( $names );
				$o['first'] = implode( ' ', $names );
			}
			if ( ! empty( $parts[1] ) ) {
				$o['role'] = $parts[1];
			}
		}
	}
	return $o;
}

function juc_dppo_opts_save( $year, array $v ) {
	$all                = (array) get_option( 'juc_dppo', array() );
	$all[ (int) $year ] = array_merge( juc_dppo_opts( $year ), $v );
	update_option( 'juc_dppo', $all, false );
}

/* ---------------------------------------------------------------- Účtovná závierka Úč MÚJ */

/**
 * Súvaha a výkaz ziskov a strát roka $y: [ bs, bs_prev, pl, pl_prev, prev_year|null, warnings ].
 * Rovnaký výpočet ako stránka Účtovná závierka (juc_page_muj).
 */
function juc_muj_statement( array $y, $to = '' ) {
	$to     = $to ? $to : $y['end_date'];
	$cur    = juc_muj_balances( (int) $y['id'], $to );
	$prev_y = juc_year_of( gmdate( 'Y-m-d', strtotime( $y['start_date'] . ' -1 day' ) ) );
	if ( $prev_y && ! juc_year_has_lines( (int) $prev_y['id'] ) ) {
		$prev_y = null;
	}
	$prev    = $prev_y ? juc_muj_balances( (int) $prev_y['id'], $prev_y['end_date'] ) : juc_muj_balances( (int) $y['id'], '', true );
	$prev_pl = $prev_y ? juc_muj_balances( (int) $prev_y['id'], $prev_y['end_date'] ) : array();
	list( $bs, , $w1 ) = juc_muj_balance_sheet( $cur );
	list( $bs2 )       = juc_muj_balance_sheet( $prev );
	list( $pl, , $w2 ) = juc_muj_pl( $cur );
	$pl2               = $prev_pl ? juc_muj_pl( $prev_pl )[0] : array();
	$warn              = array_merge( $w1, $w2 );
	if ( abs( ( $bs[1] ?? 0 ) - ( $bs[24] ?? 0 ) ) > 0.5 ) {
		$warn[] = 'Aktíva sa nerovnajú pasívam (súvaha r. 01 ≠ r. 24).';
	}
	if ( abs( ( $pl[38] ?? 0 ) - ( $bs[33] ?? 0 ) ) > 1.5 ) {
		$warn[] = 'Výsledok hospodárenia vo výkaze (r. 38) sa nerovná súvahe (r. 33).';
	}
	return array( $bs, $bs2, $pl, $pl2, $prev_y, $warn );
}

/** XML účtovnej závierky (UZMUJv14). */
function juc_uzmuj_xml( array $y ) {
	$s   = juc_settings();
	$yr  = (int) substr( $y['start_date'], 0, 4 );
	$o   = juc_dppo_opts( $yr );
	list( $bs, $bs2, $pl, $pl2, $prev_y ) = juc_muj_statement( $y );
	list( $k1, $k2, $k3 ) = juc_split_nace( $o['nace'] );
	list( $ul, $cis )     = juc_split_street( $s['street'] );
	$reg                  = (string) $s['register'];
	$reg_lines            = array( mb_substr( $reg, 0, 60 ), mb_substr( $reg, 60, 60 ) );
	$name                 = (string) $s['company'];
	$num                  = fn( $v ) => null === $v ? '' : (string) (int) round( (float) $v );
	$ps                   = strtotime( $y['start_date'] );
	$pe                   = strtotime( $y['end_date'] );
	// Bezprostredne predchádzajúce obdobie sa uvádza vždy (aj keď sa v ňom v J-Účtovníctve neúčtovalo).
	$bp_from              = $prev_y ? strtotime( $prev_y['start_date'] ) : strtotime( $y['start_date'] . ' -1 year' );
	$bp_to                = $prev_y ? strtotime( $prev_y['end_date'] ) : strtotime( $y['start_date'] . ' -1 day' );
	$suv                  = array();
	foreach ( range( 1, 45 ) as $r ) {
		$suv[ sprintf( 'r%02d', $r ) ] = array( 's1' => $num( $bs[ $r ] ?? 0 ), 's2' => $num( $bs2[ $r ] ?? 0 ) );
	}
	$vzs = array();
	foreach ( range( 1, 38 ) as $r ) {
		$vzs[ sprintf( 'r%02d', $r ) ] = array( 's1' => $num( $pl[ $r ] ?? 0 ), 's2' => $prev_y ? $num( $pl2[ $r ] ?? 0 ) : '' );
	}
	$tree = array(
		'hlavicka' => array(
			'datumK'            => array( 'den' => gmdate( 'd', $pe ), 'mesiac' => gmdate( 'm', $pe ), 'rok' => gmdate( 'Y', $pe ) ),
			'dic'               => preg_replace( '/\D/', '', (string) $s['dic'] ),
			'ico'               => preg_replace( '/\D/', '', (string) $s['ico'] ),
			'skNace'            => array( 'k1' => $k1, 'k2' => $k2, 'k3' => $k3 ),
			'typUzavierky'      => array( 'riadna' => '1', 'mimoriadna' => '0', 'priebezna' => '0' ),
			'obdobie'           => array( 'od' => array( 'mesiac' => gmdate( 'm', $ps ), 'rok' => gmdate( 'Y', $ps ) ), 'do' => array( 'mesiac' => gmdate( 'm', $pe ), 'rok' => gmdate( 'Y', $pe ) ) ),
			'bPredObdobie'      => array( 'od' => array( 'mesiac' => $bp_from ? gmdate( 'm', $bp_from ) : '', 'rok' => $bp_from ? gmdate( 'Y', $bp_from ) : '' ), 'do' => array( 'mesiac' => $bp_to ? gmdate( 'm', $bp_to ) : '', 'rok' => $bp_to ? gmdate( 'Y', $bp_to ) : '' ) ),
			'prilozeneSucasti'  => array( 'suvaha' => '1', 'vykazZiskovStrat' => '1', 'poznamky' => '1' ),
			'uctJednotka'       => array(
				'obchMeno' => array( 'riadok' => array( mb_substr( $name, 0, 60 ), mb_substr( $name, 60, 60 ) ) ),
				'sidlo'    => array( 'ulica' => $ul, 'cislo' => $cis, 'psc' => preg_replace( '/\s+/', '', (string) $s['zip'] ), 'obec' => (string) $s['city'], 'oznObchodReg' => array( 'riadok' => $reg_lines ), 'telefon' => $o['phone'], 'fax' => '', 'email' => $o['email'] ),
			),
			'datZostavenia'     => juc_xml_date( $o['compiled'] ? $o['compiled'] : current_time( 'Y-m-d' ) ),
			'datSchvalenia'     => juc_xml_date( $o['approved'] ),
		),
		'telo'     => array( 'ucMuj1Suvaha' => $suv, 'ucMuj2VykazZS' => $vzs ),
	);
	return juc_xml_doc( $tree );
}

/* ---------------------------------------------------------------- Výpočet priznania */

/** Preddavky na daň zaplatené za rok: MD 341 proti peniazom (banka, pokladňa) v roku, mimo počiatočných stavov. */
function juc_dppo_paid_advances( array $y ) {
	global $wpdb;
	$tax = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(l.amount),0) FROM ' . juc_t( 'lines' ) . ' l JOIN ' . juc_t( 'entries' ) . " e ON e.id = l.entry_id WHERE l.year_id = %d AND l.opening = 0 AND e.series <> 'UZ' AND l.md LIKE '341%%' AND (l.d LIKE '221%%' OR l.d LIKE '211%%')", $y['id'] ) ); // phpcs:ignore
	return round( $tax, 2 );
}

/**
 * Odpočet daňových strát (tab. D): straty z najviac 5 predchádzajúcich rokov od najstaršej, každá len do zostatku;
 * spolu najviac do r. 400, u nemikrodaňovníka do 50 % r. 400. Vráti [ odpočet spolu, riadky ].
 */
function juc_dppo_losses( array $losses, $r400, $micro, $year ) {
	$rows  = array();
	$limit = $r400 > 0 ? ( $micro ? $r400 : round( $r400 * 0.5, 2 ) ) : 0.0;
	usort( $losses, fn( $a, $b ) => (int) $a[0] <=> (int) $b[0] );
	$total = 0.0;
	foreach ( $losses as $l ) {
		$yr   = (int) ( $l[0] ?? 0 );
		$sum  = round( (float) ( $l[1] ?? 0 ), 2 );
		$used = round( (float) ( $l[2] ?? 0 ), 2 );
		if ( ! $yr || $sum <= 0 || $yr < $year - 5 || $yr >= $year ) {
			continue;
		}
		$left = max( 0.0, round( $sum - $used, 2 ) );
		$now  = min( $left, max( 0.0, round( $limit - $total, 2 ) ) );
		$total += $now;
		$rows[] = array( 'year' => $yr, 'total' => $sum, 'now' => $now, 'rest' => round( $left - $now, 2 ) );
	}
	return array( round( $total, 2 ), $rows );
}

/**
 * Všetky riadky priznania za rok $y: [ 'r100' => …, 'tabA' => [...], 'tabB' => [...], 'tabF' => [...], 'tabD' => [...],
 * 'checks' => [ [ ok|warn|err, text ] ], … ]. Podklad: účtovníctvo (juc_close_tax) + údaje zadané pri priznaní.
 */
function juc_dppo_compute( array $y ) {
	$yr = (int) substr( $y['start_date'], 0, 4 );
	$o  = juc_dppo_opts( $yr );
	$c  = juc_close_opts( $yr );
	$t  = juc_close_tax( $y );
	$n  = fn( $v ) => round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) $v ), 2 );

	// Tab. F: výnosy a náklady z účtovníctva (bez dane z príjmov), r. 100 = výsledok hospodárenia.
	$f = array( 'r01' => round( $t['revenue'], 2 ), 'r02' => round( $t['cost'], 2 ) );
	// Daňová strata z minulého roka sa predvyplní z jeho priznania (ak používateľ straty nezadal).
	if ( ! $o['losses'] && empty( $GLOBALS['juc_dppo_nested'] ) ) {
		$prev = juc_year_by_num( $yr - 1 );
		if ( $prev ) {
			$GLOBALS['juc_dppo_nested'] = true;
			$pr                         = juc_dppo_compute( $prev )['rows']['r400'];
			$GLOBALS['juc_dppo_nested'] = false;
			if ( $pr < 0 ) {
				$o['losses'] = array( array( $yr - 1, (string) round( -$pr, 2 ), '0' ) );
			}
		}
	}
	$f['r03'] = round( $f['r01'] - $f['r02'], 2 );

	// Tab. A: nedaňové náklady – r. 16 z účtov označených ako nedaňové + ďalšie pripočítateľné z Uzávierky, ostatné ručne.
	$a = array();
	foreach ( range( 1, 16 ) as $i ) {
		$a[ sprintf( 'r%02d', $i ) ] = isset( $o['tabA'][ $i ] ) && '' !== (string) $o['tabA'][ $i ] ? $n( $o['tabA'][ $i ] ) : 0.0;
	}
	if ( ! isset( $o['tabA'][16] ) || '' === (string) $o['tabA'][16] ) {
		$a['r16'] = round( $t['nondeduct_acc'] + (float) $t['nondeduct'], 2 );
	}
	$a['r17'] = round( array_sum( $a ), 2 );

	// Tab. B: odpisy – J-Účtovníctvo odpisuje podľa daňového plánu, účtovné = daňové (ručne sa dá zmeniť).
	$dep = 0.0;
	if ( function_exists( 'juc_close_dep_status' ) ) {
		$dep = (float) juc_close_dep_status( $y )[1];
	}
	$b = array();
	foreach ( range( 1, 5 ) as $i ) {
		$b[ sprintf( 'r%02d', $i ) ] = isset( $o['tabB'][ $i ] ) && '' !== (string) $o['tabB'][ $i ] ? $n( $o['tabB'][ $i ] ) : ( 1 === $i || 2 === $i ? round( $dep, 2 ) : 0.0 );
	}
	$b['r06'] = round( $b['r02'] - $b['r03'] - $b['r04'] + $b['r05'], 2 );

	$r = array();
	$r['r100'] = $f['r03'];
	$r['r110'] = 0.0;
	$r['r130'] = $a['r17'];
	$r['r140'] = isset( $o['tabA']['r140'] ) ? $n( $o['tabA']['r140'] ) : 0.0;
	$r['r150'] = max( 0.0, round( $b['r01'] - $b['r06'], 2 ) );
	$r['r170'] = 0.0;
	$r['r180'] = 0.0;
	$r['r200'] = round( $r['r110'] + $r['r130'] + $r['r140'] + $r['r150'] + $r['r170'] + $r['r180'], 2 );
	// Odpočítateľné: výnosy z účtov označených ako nedaňové (napr. úroky zdanené zrážkou) → r. 210.
	$r['r210'] = round( (float) $t['nontax_rev'], 2 );
	$r['r250'] = max( 0.0, round( $b['r06'] - $b['r01'], 2 ) );
	$r['r270'] = isset( $o['tabB']['r270'] ) ? $n( $o['tabB']['r270'] ) : 0.0;
	$r['r290'] = isset( $o['tabB']['r290'] ) ? $n( $o['tabB']['r290'] ) : 0.0;
	$r['r300'] = round( $r['r210'] + $r['r250'] + $r['r270'] + $r['r290'], 2 );
	$r['r301'] = round( $r['r100'] + $r['r200'] - $r['r300'], 2 );
	$r['r310'] = $r['r301'];
	$r['r400'] = $r['r310'];
	$taxable   = round( $f['r01'] - $r['r210'], 2 );
	$micro     = ! empty( $o['micro'] );
	list( $r['r410'], $loss_rows ) = juc_dppo_losses( (array) $o['losses'], $r['r400'], $micro, $yr );
	$r['r500'] = max( 0.0, round( $r['r400'] - $r['r410'], 2 ) );
	$r['r510'] = $r['r500'];
	$r['r560'] = $taxable;
	$r['r550'] = (int) round( juc_tax_rate( $taxable ) * 100 );
	$r['r600'] = floor( $r['r510'] * $r['r550'] ) / 100;
	$r['r700'] = $r['r600'];
	$r['r710'] = 0.0;
	$r['r800'] = round( $r['r700'] - $r['r710'], 2 );
	$exempt    = ! empty( $c['exempt'] );
	$r['r810'] = $exempt ? 0.0 : (float) juc_min_tax( $taxable, $yr );
	$r['r820'] = $r['r810'] > $r['r800'] ? $r['r810'] : 0.0;
	$r['r830'] = max( 0.0, round( $r['r810'] - $r['r800'], 2 ) );
	$r['r900'] = $r['r820'];
	$r['r920'] = 0.0;
	$r['r1000'] = round( $r['r800'] - $r['r920'], 2 );
	// Preddavky zadáva používateľ – platby na 341 obsahujú aj doplatok dane za minulý rok, preto sa neberú automaticky.
	$adv        = '' !== (string) $o['advances'] ? $n( $o['advances'] ) : 0.0;
	$r['r1010'] = $adv;
	$r['r1040'] = $adv;
	$r['r1050'] = $r['r810'] > $r['r800'] ? $r['r900'] : ( $r['r1000'] > 0 ? $r['r1000'] : $r['r800'] );
	$r['r1080'] = $r['r1050'];
	$diff       = round( $r['r1080'] - $r['r1040'], 2 );
	$r['r1100'] = $diff >= 0 ? ( $diff <= 5 ? 0.0 : $diff ) : 0.0;
	$r['r1101'] = $diff < 0 ? $diff : 0.0;
	$r['r1110'] = round( floor( $r['r500'] * $r['r550'] ) / 100, 2 );

	// Kontroly.
	$chk = array();
	$chk[] = 'closed' === $y['status'] ? array( 'ok', 'Rok je uzavretý.' ) : array( 'warn', 'Rok ešte nie je uzavretý – čísla sa môžu zmeniť (ročná uzávierka: odpisy, zásoby, daň).' );
	$chk[] = abs( (float) $t['vh'] - $r['r100'] ) < 0.01 ? array( 'ok', 'Výsledok hospodárenia (r. 100) sedí s účtovníctvom.' ) : array( 'err', 'Výsledok hospodárenia nesedí s účtovníctvom.' );
	list( , , $pl ) = juc_muj_statement( $y );
	if ( isset( $pl[38] ) && abs( round( $r['r100'] - $t['posted'] ) - (float) $pl[38] ) > 1.5 ) {
		$chk[] = array( 'warn', sprintf( 'Výsledok po zdanení vo výkaze ziskov a strát (r. 38: %s €) nezodpovedá r. 100 mínus zaúčtovaná daň – skontrolujte zaúčtovanie.', number_format( (float) $pl[38], 0, ',', ' ' ) ) );
	}
	if ( $taxable > 60000 && $micro ) {
		$chk[] = array( 'err', 'Zdaniteľné príjmy sú nad 60 000 € – firma nie je mikrodaňovník (§ 2 písm. w) ZDP).' );
	}
	if ( $micro && ! empty( $o['related'] ) ) {
		$chk[] = array( 'warn', 'Pri kontrolovaných transakciách so závislou osobou (napr. pôžička konateľovi alebo od konateľa) firma podľa § 2 písm. w) ZDP nie je mikrodaňovník – overte s účtovníkom.' );
	}
	if ( '' === trim( (string) $o['nace'] ) ) {
		$chk[] = array( 'err', 'Chýba kód SK NACE (hlavná činnosť).' );
	}
	if ( '' === trim( (string) $o['last'] ) ) {
		$chk[] = array( 'err', 'Chýba meno oprávnenej osoby (konateľa), ktorá priznanie podáva.' );
	}
	$s = juc_settings();
	if ( '' === preg_replace( '/\D/', '', (string) $s['dic'] ) || '' === preg_replace( '/\D/', '', (string) $s['ico'] ) ) {
		$chk[] = array( 'err', 'Chýba DIČ alebo IČO firmy (Nastavenia J-Účtovníctva / sprievodca, krok 1).' );
	}
	if ( abs( (float) $t['posted'] - $r['r1050'] ) >= 0.01 ) {
		$chk[] = array( 'warn', sprintf( 'Zaúčtovaná daň (591: %s €) sa líši od dane v priznaní (r. 1050: %s €) – zaúčtujte daň v ročnej uzávierke.', juc_money( $t['posted'] ), juc_money( $r['r1050'] ) ) );
	}
	if ( $r['r400'] < 0 ) {
		$chk[] = array( 'warn', sprintf( 'Daňová strata %s € – v priznaní za ďalší rok ju zadajte do odpočtu straty (tab. D).', juc_money( -$r['r400'] ) ) );
	}
	return array( 'year' => $yr, 'rows' => $r, 'tabA' => $a, 'tabB' => $b, 'tabF' => $f, 'losses' => $loss_rows, 'micro' => $micro, 'taxable' => $taxable, 'checks' => $chk, 'opts' => $o, 'exempt' => $exempt );
}

/* ---------------------------------------------------------------- XML DPPO (štruktúra DPPOv25) */

function juc_dppo_xml( array $y ) {
	$d   = juc_dppo_compute( $y );
	$r   = $d['rows'];
	$o   = $d['opts'];
	$s   = juc_settings();
	$num = fn( $k ) => isset( $r[ $k ] ) ? juc_xml_num( $r[ $k ] ) : '';
	list( $k1, $k2, $k3 ) = juc_split_nace( $o['nace'] );
	list( $ul, $cis )     = juc_split_street( $s['street'] );
	$name                 = (string) $s['company'];
	$telo                 = array();
	foreach ( array( 'r100', 'r110', 'r130', 'r140', 'r150', 'r170', 'r180', 'r200', 'r210', 'r220', 'r230', 'r240', 'r250', 'r260', 'r270', 'r280', 'r290', 'r300', 'r301', 'r302', 'r303', 'r304', 'r305', 'r306', 'r307', 'r308', 'r310', 'r320', 'r330', 'r400', 'r410', 'r500', 'r501', 'r502', 'r503', 'r510', 'r550', 'r560', 'r600' ) as $k ) {
		$telo[ $k ] = 'r550' === $k ? (string) $r['r550'] : ( in_array( $k, array( 'r100', 'r200', 'r300', 'r301', 'r310', 'r400', 'r410', 'r500', 'r510', 'r560', 'r600' ), true ) ? $num( $k ) : ( empty( $r[ $k ] ) ? '' : $num( $k ) ) );
	}
	$telo['r610'] = array( 'text' => '', 'suma' => '' );
	foreach ( array( 'r700', 'r710', 'r800', 'r810', 'r820', 'r830', 'r840', 'r900', 'r910', 'r920', 'r1000', 'r1010', 'r1020', 'r1030', 'r1040', 'r1050', 'r1060', 'r1061', 'r1062', 'r1070', 'r1071', 'r1080', 'r1090', 'r1100', 'r1101', 'r1110' ) as $k ) {
		$always       = in_array( $k, array( 'r700', 'r800', 'r1000', 'r1040', 'r1050', 'r1080', 'r1100', 'r1110' ), true ) || ( 'r810' === $k && ! $d['exempt'] ) || ( in_array( $k, array( 'r820', 'r900' ), true ) && $r['r820'] > 0 ) || ( 'r830' === $k && $r['r830'] > 0 ) || ( 'r1010' === $k && $r['r1010'] > 0 ) || ( 'r1101' === $k && $r['r1101'] < 0 );
		$telo[ $k ]   = $always ? $num( $k ) : '';
	}
	$telo['ddpDatum'] = '';
	foreach ( array( 'r1120', 'r1130', 'r1140', 'r1150', 'r1160', 'r1170', 'r1180', 'r1190', 'r1191', 'r1192' ) as $k ) {
		$telo[ $k ] = '';
	}
	$ta = array();
	foreach ( $d['tabA'] as $k => $v ) {
		$ta[ $k ] = $v ? juc_xml_num( $v ) : ( 'r17' === $k ? juc_xml_num( 0 ) : '' );
	}
	$telo['tabA'] = $ta;
	$tb           = array();
	foreach ( $d['tabB'] as $k => $v ) {
		$tb[ $k ] = $v ? juc_xml_num( $v ) : '';
	}
	$telo['tabB']  = $tb;
	$empty6        = array( 'r01' => '', 'r02' => '', 'r03' => '', 'r04' => '', 'r05' => '', 'r06' => '' );
	$telo['tabC1'] = $empty6;
	$telo['tabC2'] = $empty6;
	// Tab. D: straty podľa § 30 v stĺpcoch tabDs04–tabDs08 (od najstaršej), tabDs09 spolu.
	$td = array(
		'tabDs01' => array( 'r01' => '', 'r02' => '', 'r03' => '' ),
		'tabDs02' => array( 'datumOd' => '', 'datumDo' => '', 'r01' => '', 'r02' => '', 'r03' => '' ),
		'tabDs03' => array( 'datumOd' => '', 'datumDo' => '', 'r01' => '', 'r02' => '', 'r03' => '' ),
	);
	$lr  = array_values( array_slice( $d['losses'], -5 ) );
	foreach ( range( 4, 8 ) as $i => $col ) {
		$l = $lr[ $i ] ?? null;
		$td[ sprintf( 'tabDs%02d', $col ) ] = $l ? array( 'datumOd' => '01.01.' . $l['year'], 'datumDo' => '31.12.' . $l['year'], 'r01' => juc_xml_num( $l['total'] ), 'r02' => juc_xml_num( $l['now'] ), 'r03' => juc_xml_num( $l['rest'] ) )
			: array( 'datumOd' => '', 'datumDo' => '', 'r01' => '', 'r02' => '', 'r03' => '' );
	}
	$td['tabDs09'] = $lr ? array( 'r01' => juc_xml_num( array_sum( wp_list_pluck( $lr, 'total' ) ) ), 'r02' => juc_xml_num( array_sum( wp_list_pluck( $lr, 'now' ) ) ), 'r03' => juc_xml_num( array_sum( wp_list_pluck( $lr, 'rest' ) ) ) ) : array( 'r01' => '', 'r02' => '', 'r03' => '' );
	$telo['tabD'] = $td;
	$telo['tabE'] = array_fill_keys( array( 'r01', 'r02', 'r03', 'r04', 'r05', 'r06', 'r07', 'r08', 'r09', 'r10' ), '' );
	$telo['tabF'] = array( 'r01' => juc_xml_num( $d['tabF']['r01'] ), 'r02' => juc_xml_num( $d['tabF']['r02'] ), 'r03' => juc_xml_num( $d['tabF']['r03'] ) );
	$telo['tabG1'] = array( 'r01' => '', 'r02' => '', 'r03' => '' );
	$telo['tabG2'] = array( 'r01' => '', 'r02' => '', 'r03' => '' );
	$telo['tabG3'] = array( 'r01' => '', 'r02' => '', 'r03' => '', 'r04' => '' );
	$h9            = array_fill_keys( array( 'r02', 'r03', 'r04', 'r05', 'r06', 'r07', 'r08', 'r09', 'r10' ), '' );
	$telo['tabH']  = array( 'r01' => '', 'prijmy' => $h9, 'vydavky' => $h9, 'r11' => '' );
	$ti            = array();
	foreach ( range( 1, 18 ) as $i ) {
		$ti[ sprintf( 'r%02d', $i ) ] = array( 'druh' => '', 'vplyvNaR100' => '', 'nazovZavislejOsoby' => '', 'kodStatu' => '' );
	}
	$telo['tabI']                     = $ti;
	$telo['tabJ']                     = array( 'r01' => '', 'r02' => '' );
	$telo['obmedzenaDanovaPovinnost'] = array( 'datumOd' => '', 'datumDo' => '', 'TIN' => '' );
	$ob                               = array( 'datumOd' => '', 'datumDo' => '', 'vyskaKladnehoRozdielu' => '', 'zapocitanaVPredObdobiach' => '', 'zapocitanaVDanomObdobi' => '', 'zostavajucaCast' => '' );
	$telo['tabK']                     = array( 'obdobiaMinimalDane' => array( 'obdobie1' => $ob, 'obdobie2' => $ob, 'obdobie3' => $ob, 'obdobie4' => $ob ), 'spolu' => array( 'zapocitanaVDanomObdobi' => '', 'zostavajucaCast' => '' ) );
	$z                                = array();
	foreach ( range( 1, 31 ) as $i ) {
		$z[ sprintf( 'r%02d', $i ) ] = ( $i <= 6 || ( $i >= 14 && $i <= 19 ) ) ? array( 's1' => '', 's2' => '' ) : '';
	}
	$telo['zdaneniePar17f']      = $z;
	$telo['podielyNaZiskuPar51e'] = array( 'r01' => '', 'r02' => '', 'r03' => '', 'r04' => array( 's1' => '', 's2' => '' ), 'r05' => array( 's1' => '', 's2' => '' ), 'r06' => array( 's1' => '', 's2' => '' ), 'r07' => '', 'r08' => '', 'r09' => '' );
	$vd                          = array();
	foreach ( range( 1, 14 ) as $i ) {
		$vd[ 'r' . $i ] = 4 === $i ? array( 's1' => '16', 's2' => '13' ) : array( 's1' => '', 's2' => '' );
	}
	$telo['vynosyZoStatnychDlhopisov'] = $vd;
	// VII. časť: podiel zaplatenej dane (§ 50) – zatiaľ neuplatňuje (prijímateľa doplní používateľ pri podaní).
	$telo['c7paragraf50']  = '1';
	$telo['suhlasZasl']    = '0';
	foreach ( array( 'c7r1', 'c7r2', 'c7r3', 'c7r4', 'c7r5' ) as $k ) {
		$telo[ $k ] = '';
	}
	$telo['c7prijimatel1']  = array( 'suma' => '', 'ico' => '', 'obchodneMeno' => array( 'riadok' => array( '', '' ) ) );
	$telo['osobitneZaznamy'] = (string) $o['note'];
	$telo['opravnenaOsoba']  = array(
		'priezvisko'  => $o['last'],
		'meno'        => $o['first'],
		'titul'       => $o['title'],
		'titulZa'     => $o['title_after'],
		'postavenie'  => $o['role'],
		'trvalyPobyt' => array( 'ulica' => $o['p_street'], 'cislo' => $o['p_number'], 'psc' => preg_replace( '/\s+/', '', (string) $o['p_zip'] ), 'obec' => $o['p_city'], 'stat' => 'Slovenská republika', 'tel' => $o['phone'], 'emailFax' => $o['email'] ),
	);
	$telo['pocetPriloh']     = '5';
	$telo['datumVyhlasenia'] = juc_xml_date( $o['declared'] ? $o['declared'] : current_time( 'Y-m-d' ) );
	$spl                     = array( 'datumSplat' => '', 'suma' => '' );
	$telo['platenieDanePar17f'] = array( 'ziadam17f' => '0', 'splatka' => array( $spl, $spl, $spl, $spl, $spl ), 'datum' => '' );
	$refund                     = ! empty( $o['refund'] ) && $r['r1101'] < 0;
	$telo['vrateniePreplatku']  = array( 'vratit' => $refund ? '1' : '0', 'sposobPlatby' => array( 'poukazka' => '0', 'ucet' => $refund ? '1' : '0' ), 'bankovyUcet' => array( 'IBAN' => $refund ? preg_replace( '/\s+/', '', strtoupper( (string) $o['iban'] ) ) : '' ), 'datum' => $refund ? juc_xml_date( $o['declared'] ? $o['declared'] : current_time( 'Y-m-d' ) ) : '' );
	$telo['prilPar13aA13b']     = array( 'r01' => '', 'cisloPatentuSoftver' => '', 'r02' => '', 'cisloPatentu' => '', 'r03' => '' );
	$p4                         = array( 's01Od' => '', 's01Do' => '', 's02' => '', 's03' => '' );
	$telo['prilPar30cOdpocetVydavkov']        = array( 'projektCislo' => '', 'pocetProjektov' => '', 'datumRealizacie' => '', 'r01' => $p4, 'r02' => $p4, 'r03' => $p4, 'r04' => $p4, 'r05' => $p4, 'r06' => '', 'ciele' => '', 'r07' => '', 'r08' => '', 'r09' => '' );
	$telo['prilPar30caOdpocetVydavkovSport']  = array( 'r01' => $p4, 'r02' => $p4, 'r03' => $p4, 'r04' => $p4, 'r05' => $p4, 'r06' => '', 'r07' => '', 'r08' => '' );
	$p5                                       = array( 's01Od' => '', 's01Do' => '', 's02' => '', 's03' => '', 's04' => '' );
	$inv                                      = array();
	foreach ( range( 1, 10 ) as $i ) {
		$inv[ sprintf( 'r%02d', $i ) ] = $p5;
	}
	$telo['prilPar30eVydavkyInvesticie'] = $inv + array( 'r11' => '', 'r12' => '' );
	$telo['priloha']                     = array( 'prijimatel' => array( 'poradoveCislo' => '', 'suma' => '', 'ico' => '', 'obchodneMeno' => array( 'riadok' => array( '', '' ) ) ) );

	$tree = array(
		'hlavicka' => array(
			'dic'                          => preg_replace( '/\D/', '', (string) $s['dic'] ),
			'ico'                          => preg_replace( '/\D/', '', (string) $s['ico'] ),
			'pravnaForma'                  => '112',
			'typDP'                        => array( 'rdp' => '1', 'odp' => '0', 'ddp' => '0' ),
			'zdanovacieObdobie'            => array( 'od' => juc_xml_date( $y['start_date'] ), 'do' => juc_xml_date( $y['end_date'] ) ),
			'skNace'                       => array( 'k1' => $k1, 'k2' => $k2, 'k3' => $k3, 'cinnost' => (string) $o['activity'] ),
			'obchodneMeno'                 => array( 'riadok' => array( mb_substr( $name, 0, 37 ), mb_substr( $name, 37, 37 ), mb_substr( $name, 74, 37 ) ) ),
			'sidlo'                        => array( 'ulica' => $ul, 'cislo' => $cis, 'psc' => preg_replace( '/\s+/', '', (string) $s['zip'] ), 'obec' => (string) $s['city'], 'stat' => 'Slovenská republika', 'tel' => $o['phone'], 'emailFax' => $o['email'] ),
			'uplatnujemPar17'              => '0',
			'ukoncujemUplatnovaniePar17'   => '0',
			'obmedzenie'                   => '0',
			'prepojenie'                   => empty( $o['related'] ) ? '0' : '1',
			'uplatnenieOslobodeniaPar13ab' => '0',
			'mikrodanovnikPar2w'           => $d['micro'] ? '1' : '0',
			'polovicnaVyskaDanePar46b3'    => '0',
			'neplatenieMinDanePar46b7'     => $d['exempt'] ? '1' : '0',
			'zapocitanieMinDanePar46b5'    => '0',
			'stalaPrevadzkaren'            => array( 'ulica' => '', 'cislo' => '', 'psc' => '', 'obec' => '', 'pocetSp' => '' ),
		),
		'telo'     => $telo,
	);
	return juc_xml_doc( $tree );
}

/* ---------------------------------------------------------------- Stránka Daňové priznanie */

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Daňové priznanie – J-Účtovníctvo', 'Daňové priznanie', JUC_CAP, 'j-uctovnictvo-dppo', 'juc_page_dppo' );
}, 20 );

/** Názvy riadkov priznania (II. časť) pre prehľad. */
function juc_dppo_row_labels() {
	return array(
		'r100'  => 'Výsledok hospodárenia pred zdanením (tab. F r. 3)',
		'r130'  => '+ daňovo neuznané výdavky (tab. A r. 17)',
		'r140'  => '+ výdavky zaplatené až po skončení roka (§ 17 ods. 19)',
		'r150'  => '+ účtovné odpisy nad daňové (tab. B)',
		'r200'  => 'Pripočítateľné položky spolu',
		'r210'  => '− príjmy, ktoré nie sú predmetom dane / zdanené zrážkou',
		'r250'  => '− daňové odpisy nad účtovné (tab. B)',
		'r270'  => '− výdavky z minulého roka zaplatené v tomto roku (§ 17 ods. 19)',
		'r290'  => '− ostatné odpočítateľné položky',
		'r300'  => 'Odpočítateľné položky spolu',
		'r301'  => 'Základ dane (+) / daňová strata (−)',
		'r400'  => 'Základ dane / strata po úpravách',
		'r410'  => '− odpočet daňovej straty (tab. D)',
		'r500'  => 'Základ dane znížený o stratu',
		'r510'  => 'Základ dane po odpočtoch',
		'r550'  => 'Sadzba dane (%)',
		'r560'  => 'Zdaniteľné príjmy (výnosy)',
		'r600'  => 'Daň',
		'r700'  => 'Daň po úľavách',
		'r800'  => 'Daň po zápočte zahraničnej dane',
		'r810'  => 'Minimálna daň (daňová licencia)',
		'r820'  => 'Minimálna daň na úhradu',
		'r830'  => 'Kladný rozdiel na započítanie v ďalších rokoch',
		'r900'  => 'Minimálna daň spolu',
		'r1000' => 'Daň po zápočte minimálnej dane',
		'r1040' => 'Zaplatené preddavky',
		'r1050' => 'Daň alebo minimálna daň',
		'r1080' => 'Daň celkom',
		'r1100' => 'Daň na úhradu',
		'r1101' => 'Daňový preplatok',
		'r1110' => 'Daň na výpočet preddavkov na ďalší rok',
	);
}

function juc_page_dppo() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y     = juc_current_year();
	$yr    = (int) substr( $y['start_date'], 0, 4 );
	$d     = juc_dppo_compute( $y );
	$o     = $d['opts'];
	$r     = $d['rows'];
	$s     = juc_settings();
	$print = juc_print_mode();
	$dl    = fn( $what ) => wp_nonce_url( admin_url( 'admin-post.php?action=juc_dppo_xml&what=' . $what . '&year=' . (int) $y['id'] ), 'juc_dppo_xml' );
	$money = fn( $v ) => esc_html( juc_money( $v ) ) . ' €';
	juc_head( 'Daňové priznanie a účtovná závierka', $print ? '' : ' <a class="page-title-action" href="' . esc_url( add_query_arg( 'print', 1 ) ) . '">Tlačiť</a>' );
	if ( $print ) {
		juc_print_header( 'Daňové priznanie k dani z príjmov PO', $y );
	} else {
		juc_year_select( 'j-uctovnictvo-dppo' );
	}
	?>
	<p class="juc-noprint" style="max-width:900px">Daňové priznanie k dani z príjmov právnickej osoby (DPPO) a účtovná závierka mikro účtovnej jednotky (Úč MÚJ) za rok <?php echo (int) $yr; ?>. Údaje sú z účtovníctva, doplníte len to, čo účtovníctvo nevie. Súbory XML nahráte na portáli Finančnej správy (<strong>Moja schránka → Podanie → Nahrať XML</strong>), skontrolujete a podpíšete. Termín: do <?php echo esc_html( juc_date( ( $yr + 1 ) . '-03-31' ) ); ?> (ak ste lehotu nepredĺžili).</p>
	<?php if ( $yr >= 2026 ) : ?>
		<div class="notice notice-warning inline juc-noprint"><p>Finančná správa zverejní formulár priznania za rok <?php echo (int) $yr; ?> (DPPOv26) začiatkom roka <?php echo (int) $yr + 1; ?>. Dovtedy sa súbor vytvára v štruktúre formulára za rok 2025 – pred podaním aktualizujte J-Účtovníctvo a súbor vytvorte znova.</p></div>
	<?php endif; ?>

	<h2>Kontroly</h2>
	<ul class="juc-checks">
	<?php foreach ( $d['checks'] as $c ) : ?>
		<li><span class="dashicons <?php echo 'ok' === $c[0] ? 'dashicons-yes-alt" style="color:#00a32a' : ( 'warn' === $c[0] ? 'dashicons-warning" style="color:#dba617' : 'dashicons-dismiss" style="color:#d63638' ); ?>"></span> <?php echo esc_html( $c[1] ); ?></li>
	<?php endforeach; ?>
	</ul>

	<?php if ( ! $print ) : ?>
	<p class="juc-noprint">
		<a class="button button-primary" href="<?php echo esc_url( $dl( 'dppo' ) ); ?>">Stiahnuť XML – daňové priznanie (DPPO)</a>
		<a class="button" href="<?php echo esc_url( $dl( 'uzmuj' ) ); ?>">Stiahnuť XML – účtovná závierka (Úč MÚJ)</a>
	</p>
	<p class="description juc-noprint">Účtovná závierka sa podáva spolu s priznaním (Finančná správa ju postúpi do registra účtovných závierok). Poznámky k závierke (textová časť) vložíte na portáli ako prílohu PDF.</p>
	<?php endif; ?>

	<h2>Výpočet dane (II. časť priznania)</h2>
	<table class="widefat striped juc-table" style="max-width:820px"><thead><tr><th style="width:70px">Riadok</th><th>Popis</th><th class="n">Suma</th></tr></thead><tbody>
	<?php
	foreach ( juc_dppo_row_labels() as $k => $label ) {
		$v = $r[ $k ] ?? 0;
		if ( ! $v && ! in_array( $k, array( 'r100', 'r200', 'r300', 'r301', 'r400', 'r500', 'r510', 'r600', 'r800', 'r1050', 'r1080', 'r1100' ), true ) ) {
			continue;
		}
		$strong = in_array( $k, array( 'r301', 'r500', 'r1050', 'r1100', 'r1101' ), true );
		echo '<tr><td>' . esc_html( substr( $k, 1 ) ) . '</td><td>' . ( $strong ? '<strong>' : '' ) . esc_html( $label ) . ( $strong ? '</strong>' : '' ) . '</td><td class="n">' . ( 'r550' === $k ? (int) $v . ' %' : ( $strong ? '<strong>' . $money( $v ) . '</strong>' : $money( $v ) ) ) . '</td></tr>'; // phpcs:ignore
	}
	?>
	</tbody></table>
	<p class="description">Tab. F: výnosy <?php echo $money( $d['tabF']['r01'] ); // phpcs:ignore ?>, náklady <?php echo $money( $d['tabF']['r02'] ); // phpcs:ignore ?>. Mikrodaňovník: <?php echo $d['micro'] ? 'áno' : 'nie'; ?>. Podiel zaplatenej dane (2 %) – v súbore je vyznačené „neuplatňujem“; prijímateľa doplníte pri podaní na portáli.</p>

	<?php if ( ! $print ) : ?>
	<h2 class="juc-noprint">Údaje k priznaniu</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel juc-noprint" style="max-width:900px">
		<?php wp_nonce_field( 'juc_dppo_save' ); ?><input type="hidden" name="action" value="juc_dppo_save"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<h3>Firma</h3>
		<p><?php echo esc_html( $s['company'] ); ?> · IČO <?php echo esc_html( $s['ico'] ); ?> · DIČ <?php echo esc_html( $s['dic'] ); ?> · <?php echo esc_html( trim( $s['street'] . ', ' . $s['zip'] . ' ' . $s['city'], ', ' ) ); ?> <a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-settings' ) ); ?>">zmeniť</a></p>
		<p><label>SK NACE (hlavná činnosť) <input type="text" name="nace" value="<?php echo esc_attr( $o['nace'] ); ?>" placeholder="47.91.0" style="width:100px"></label>
			<label style="margin-left:12px">Činnosť slovom <input type="text" name="activity" value="<?php echo esc_attr( $o['activity'] ); ?>" placeholder="internetový predaj" style="width:300px"></label></p>
		<p><label><input type="checkbox" name="micro" value="1" <?php checked( $o['micro'] ); ?>> Mikrodaňovník (zdaniteľné príjmy do 60 000 € a žiadne transakcie so závislými osobami)</label><br>
			<label><input type="checkbox" name="related" value="1" <?php checked( $o['related'] ); ?>> Firma mala transakcie so závislou osobou (napr. pôžička od konateľa alebo konateľovi, nájom od spoločníka) – mimo odmeny konateľa</label><br>
			<small>Pôžička od konateľa či konateľovi je podľa zákona kontrolovaná transakcia – vtedy firma nie je mikrodaňovník. Overte s účtovníkom.</small></p>

		<h3>Kto podáva (oprávnená osoba)</h3>
		<p><label>Titul <input type="text" name="title" value="<?php echo esc_attr( $o['title'] ); ?>" style="width:60px"></label>
			<label>Meno <input type="text" name="first" value="<?php echo esc_attr( $o['first'] ); ?>" style="width:130px"></label>
			<label>Priezvisko <input type="text" name="last" value="<?php echo esc_attr( $o['last'] ); ?>" style="width:160px"></label>
			<label>Titul za <input type="text" name="title_after" value="<?php echo esc_attr( $o['title_after'] ); ?>" style="width:60px"></label>
			<label>Postavenie <input type="text" name="role" value="<?php echo esc_attr( $o['role'] ); ?>" style="width:100px"></label></p>
		<p>Trvalý pobyt: <label>Ulica <input type="text" name="p_street" value="<?php echo esc_attr( $o['p_street'] ); ?>" style="width:180px"></label>
			<label>Číslo <input type="text" name="p_number" value="<?php echo esc_attr( $o['p_number'] ); ?>" style="width:70px"></label>
			<label>PSČ <input type="text" name="p_zip" value="<?php echo esc_attr( $o['p_zip'] ); ?>" style="width:70px"></label>
			<label>Obec <input type="text" name="p_city" value="<?php echo esc_attr( $o['p_city'] ); ?>" style="width:150px"></label></p>
		<p><label>Telefón <input type="text" name="phone" value="<?php echo esc_attr( $o['phone'] ); ?>" style="width:140px"></label>
			<label>E-mail <input type="email" name="email" value="<?php echo esc_attr( $o['email'] ); ?>" style="width:220px"></label></p>

		<h3>Úpravy základu dane</h3>
		<p><label>Ostatné daňovo neuznané výdavky (tab. A r. 16) <input type="text" name="tabA16" value="<?php echo esc_attr( (string) ( $o['tabA'][16] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( juc_money( $d['tabA']['r16'] ) ); ?>" inputmode="decimal" style="width:110px"> €</label><br>
			<small>Prázdne = nedaňové účty (513, 543, 545…) + pripočítateľné položky z Uzávierky: <?php echo $money( $d['tabA']['r16'] ); // phpcs:ignore ?>.</small></p>
		<p><label>Reprezentácia, reklamné predmety (tab. A r. 6) <input type="text" name="tabA6" value="<?php echo esc_attr( (string) ( $o['tabA'][6] ?? '' ) ); ?>" inputmode="decimal" style="width:110px"> €</label>
			<label style="margin-left:12px">Dary (tab. A r. 8) <input type="text" name="tabA8" value="<?php echo esc_attr( (string) ( $o['tabA'][8] ?? '' ) ); ?>" inputmode="decimal" style="width:110px"> €</label></p>
		<p><label>Nezaplatené výdavky podľa § 17 ods. 19 – nájom, účtovník, licencie… (r. 140) <input type="text" name="r140" value="<?php echo esc_attr( (string) ( $o['tabA']['r140'] ?? '' ) ); ?>" inputmode="decimal" style="width:110px"> €</label><br>
			<label>Také výdavky z minulého roka zaplatené v tomto roku (r. 270) <input type="text" name="r270" value="<?php echo esc_attr( (string) ( $o['tabB']['r270'] ?? '' ) ); ?>" inputmode="decimal" style="width:110px"> €</label><br>
			<label>Ostatné odpočítateľné položky (r. 290) <input type="text" name="r290" value="<?php echo esc_attr( (string) ( $o['tabB']['r290'] ?? '' ) ); ?>" inputmode="decimal" style="width:110px"> €</label></p>
		<p><label>Účtovné odpisy (tab. B r. 1) <input type="text" name="tabB1" value="<?php echo esc_attr( (string) ( $o['tabB'][1] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( juc_money( $d['tabB']['r01'] ) ); ?>" inputmode="decimal" style="width:110px"> €</label>
			<label style="margin-left:12px">Daňové odpisy (tab. B r. 2) <input type="text" name="tabB2" value="<?php echo esc_attr( (string) ( $o['tabB'][2] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( juc_money( $d['tabB']['r02'] ) ); ?>" inputmode="decimal" style="width:110px"> €</label><br>
			<small>Prázdne = odpisy z karty majetku (J-Účtovníctvo odpisuje podľa daňového odpisového plánu, účtovné = daňové).</small></p>

		<h3>Daňové straty minulých rokov (tab. D)</h3>
		<table class="widefat juc-table" style="max-width:560px"><thead><tr><th>Rok vzniku</th><th>Strata spolu</th><th>Už odpočítané v minulých rokoch</th></tr></thead><tbody>
		<?php
		$losses = array_values( (array) $o['losses'] );
		for ( $i = 0; $i < max( 3, count( $losses ) + 1 ); $i++ ) {
			$l = $losses[ $i ] ?? array( '', '', '' );
			echo '<tr><td><input type="number" name="loss_y[]" value="' . esc_attr( (string) $l[0] ) . '" min="' . ( $yr - 5 ) . '" max="' . ( $yr - 1 ) . '" style="width:80px"></td><td><input type="text" name="loss_s[]" value="' . esc_attr( (string) $l[1] ) . '" inputmode="decimal" style="width:110px"> €</td><td><input type="text" name="loss_u[]" value="' . esc_attr( (string) $l[2] ) . '" inputmode="decimal" style="width:110px"> €</td></tr>';
		}
		?>
		</tbody></table>
		<p><small>Strata sa odpočítava najviac 5 rokov; mikrodaňovník do 100 % základu dane, ostatní do 50 %.</small></p>

		<h3>Preddavky a preplatok</h3>
		<p><label>Zaplatené preddavky na daň za rok <?php echo (int) $yr; ?> <input type="text" name="advances" value="<?php echo esc_attr( (string) $o['advances'] ); ?>" placeholder="0,00" inputmode="decimal" style="width:110px"> €</label><br><small>Len preddavky na daň za tento rok (platia sa, ak daň za minulý rok bola nad 5 000 €). Platby na účet 341 z banky a pokladne v roku spolu: <?php echo $money( juc_dppo_paid_advances( $y ) ); // phpcs:ignore ?> – obsahujú aj doplatok dane za minulý rok.</small></p>
		<p><label><input type="checkbox" name="refund" value="1" <?php checked( $o['refund'] ); ?>> Pri preplatku žiadam o vrátenie na účet</label>
			<label style="margin-left:12px">IBAN <input type="text" name="iban" value="<?php echo esc_attr( (string) $o['iban'] ); ?>" style="width:250px" autocomplete="off"></label></p>

		<h3>Dátumy a poznámka</h3>
		<p><label>Zostavenie závierky <input type="date" name="compiled" value="<?php echo esc_attr( $o['compiled'] ); ?>"></label>
			<label style="margin-left:12px">Schválenie závierky <input type="date" name="approved" value="<?php echo esc_attr( $o['approved'] ); ?>"></label>
			<label style="margin-left:12px">Dátum priznania <input type="date" name="declared" value="<?php echo esc_attr( $o['declared'] ); ?>"></label></p>
		<p><small>Prázdny dátum zostavenia a priznania = deň stiahnutia súboru. Schválenie závierky (valné zhromaždenie) môže byť aj neskôr – vtedy ostane prázdne.</small></p>
		<p><label>Osobitné záznamy (VIII. časť)<br><textarea name="note" rows="3" style="width:100%"><?php echo esc_textarea( (string) $o['note'] ); ?></textarea></label></p>
		<p><button class="button button-primary">Uložiť údaje</button></p>
	</form>
	<p class="description juc-noprint">Ide o pomôcku – priznanie a závierku pred podaním skontrolujte (najlepšie s účtovníkom). Daň zaúčtujete v <a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-closing', array( 'year' => $y['id'] ) ) ); ?>">Uzávierke</a>.</p>
	<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_juc_dppo_save', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_dppo_save' );
	$in = wp_unslash( $_POST );
	$y  = juc_year( (int) ( $in['year'] ?? 0 ) );
	if ( ! $y ) {
		wp_die( 'Obdobie neexistuje.' );
	}
	$yr   = (int) substr( $y['start_date'], 0, 4 );
	$num  = function ( $v ) {
		$v = trim( str_replace( array( ' ', "\xc2\xa0", ',' ), array( '', '', '.' ), (string) $v ) );
		return '' === $v ? '' : (string) round( (float) $v, 2 );
	};
	$txt  = fn( $k ) => sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
	$date = fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in[ $k ] ?? '' ) ) ? (string) $in[ $k ] : '';
	$tab_a = array_filter( array( 6 => $num( $in['tabA6'] ?? '' ), 8 => $num( $in['tabA8'] ?? '' ), 16 => $num( $in['tabA16'] ?? '' ), 'r140' => $num( $in['r140'] ?? '' ) ), 'strlen' );
	$tab_b = array_filter( array( 1 => $num( $in['tabB1'] ?? '' ), 2 => $num( $in['tabB2'] ?? '' ), 'r270' => $num( $in['r270'] ?? '' ), 'r290' => $num( $in['r290'] ?? '' ) ), 'strlen' );
	$losses = array();
	foreach ( (array) ( $in['loss_y'] ?? array() ) as $i => $ly ) {
		$ly = (int) $ly;
		$ls = $num( $in['loss_s'][ $i ] ?? '' );
		if ( $ly && '' !== $ls ) {
			$losses[] = array( $ly, $ls, $num( $in['loss_u'][ $i ] ?? '' ) );
		}
	}
	juc_dppo_opts_save( $yr, array(
		'nace'        => preg_replace( '/[^0-9.]/', '', $txt( 'nace' ) ),
		'activity'    => $txt( 'activity' ),
		'micro'       => empty( $in['micro'] ) ? 0 : 1,
		'related'     => empty( $in['related'] ) ? 0 : 1,
		'last'        => $txt( 'last' ),
		'first'       => $txt( 'first' ),
		'title'       => $txt( 'title' ),
		'title_after' => $txt( 'title_after' ),
		'role'        => $txt( 'role' ),
		'p_street'    => $txt( 'p_street' ),
		'p_number'    => $txt( 'p_number' ),
		'p_zip'       => $txt( 'p_zip' ),
		'p_city'      => $txt( 'p_city' ),
		'phone'       => $txt( 'phone' ),
		'email'       => sanitize_email( (string) ( $in['email'] ?? '' ) ),
		'advances'    => $num( $in['advances'] ?? '' ),
		'refund'      => empty( $in['refund'] ) ? 0 : 1,
		'iban'        => strtoupper( preg_replace( '/\s+/', '', $txt( 'iban' ) ) ),
		'declared'    => $date( 'declared' ),
		'approved'    => $date( 'approved' ),
		'compiled'    => $date( 'compiled' ),
		'tabA'        => $tab_a,
		'tabB'        => $tab_b,
		'losses'      => $losses,
		'note'        => sanitize_textarea_field( (string) ( $in['note'] ?? '' ) ),
	) );
	juc_back_msg( juc_url( 'j-uctovnictvo-dppo', array( 'year' => $y['id'] ) ), 'Údaje k priznaniu sú uložené.' );
} );

add_action( 'admin_post_juc_dppo_xml', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_dppo_xml' );
	$y = juc_year( (int) ( $_GET['year'] ?? 0 ) ); // phpcs:ignore
	if ( ! $y ) {
		wp_die( 'Obdobie neexistuje.' );
	}
	$yr   = (int) substr( $y['start_date'], 0, 4 );
	$what = 'uzmuj' === ( $_GET['what'] ?? '' ) ? 'uzmuj' : 'dppo'; // phpcs:ignore
	$xml  = 'uzmuj' === $what ? juc_uzmuj_xml( $y ) : juc_dppo_xml( $y );
	$ico  = preg_replace( '/\D/', '', (string) juc_opt( 'ico' ) );
	nocache_headers();
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . ( 'uzmuj' === $what ? 'UZMUJ' : 'DPPO' ) . '-' . $yr . ( $ico ? '-' . $ico : '' ) . '.xml"' );
	echo $xml; // phpcs:ignore
	exit;
} );
