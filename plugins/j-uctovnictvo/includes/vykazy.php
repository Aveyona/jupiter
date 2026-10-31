<?php
/**
 * Účtová závierka mikro účtovnej jednotky – Súvaha Úč MÚJ 1-01 a Výkaz ziskov a strát Úč MÚJ 2-01
 * podľa opatrenia MF SR č. MF/15464/2013-74 v znení MF/18008/2014-74 a MF/14775/2017-74 (platné pre 2025 a 2026).
 * Väzby riadkov na účty sú prevzaté zo vzoru tlačiva (docs/vykazy-muj.md).
 *
 * Výpočet:
 *  - zostatky účtov k dátumu bez dokladov série UZ (uzávierkové zápisy 710/702 by zostatky vynulovali),
 *  - každý účet (aj analytický) patrí do jedného riadku; účty s pohľadávkou aj záväzkom (316, 33x, 34x, 221, 398…)
 *    podľa charakteru zostatku na strane aktív alebo pasív (§ 2 ods. 4 opatrenia),
 *  - aktíva netto (oprávky a opravné položky sa odpočítajú – majú zostatok Dal), pasíva = zostatok Dal,
 *  - r. 33 (výsledok hospodárenia po zdanení) je rozdielový riadok: r. 01 − (r. 26 + 29 + 30 + 31 + 32 + 34),
 *  - sumy v celých eurách; delenie na dlhodobé a krátkodobé: pohľadávky a záväzky z obchodného styku sa vykazujú
 *    ako krátkodobé (dlhodobé časti treba viesť na analytických účtoch a zaradiť ručne – pozri upozornenia).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Účtovná závierka – J-Účtovníctvo', 'Účtovná závierka', JUC_CAP, 'j-uctovnictvo-muj', 'juc_page_muj' );
}, 15 );

/** Riadky súvahy: [označenie, text, číslo riadku, vzorec (súčet riadkov) alebo null, úroveň]. */
function juc_muj_balance_rows() {
	return array(
		array( '', 'SPOLU MAJETOK', 1, '2+14', 0 ),
		array( 'A.', 'Neobežný majetok', 2, '3+4+9', 1 ),
		array( 'A.I.', 'Dlhodobý nehmotný majetok', 3, null, 2 ),
		array( 'A.II.', 'Dlhodobý hmotný majetok', 4, '5+6+7+8', 2 ),
		array( 'A.II.1.', 'Pozemky a stavby', 5, null, 3 ),
		array( 'A.II.2.', 'Samostatné hnuteľné veci a súbory hnuteľných vecí', 6, null, 3 ),
		array( 'A.II.3.', 'Ostatný dlhodobý hmotný majetok', 7, null, 3 ),
		array( 'A.II.4.', 'Opravná položka k nadobudnutému majetku', 8, null, 3 ),
		array( 'A.III.', 'Dlhodobý finančný majetok', 9, '10+11+12+13', 2 ),
		array( 'A.III.1.', 'Podielové cenné papiere', 10, null, 3 ),
		array( 'A.III.2.', 'Ostatný dlhodobý finančný majetok', 11, null, 3 ),
		array( 'A.III.3.', 'Účty v bankách s dobou viazanosti dlhšou ako jeden rok', 12, null, 3 ),
		array( 'A.III.4.', 'Ostatný dlhodobý finančný majetok so zostatkovou dobou splatnosti najviac jeden rok', 13, null, 3 ),
		array( 'B.', 'Obežný majetok', 14, '15+16+17+21', 1 ),
		array( 'B.I.', 'Zásoby', 15, null, 2 ),
		array( 'B.II.', 'Dlhodobé pohľadávky', 16, null, 2 ),
		array( 'B.III.', 'Krátkodobé pohľadávky', 17, '18+19+20', 2 ),
		array( 'B.III.1.', 'Pohľadávky z obchodného styku', 18, null, 3 ),
		array( 'B.III.2.', 'Sociálne poistenie, daňové pohľadávky a dotácie', 19, null, 3 ),
		array( 'B.III.3.', 'Ostatné pohľadávky', 20, null, 3 ),
		array( 'B.IV.', 'Finančný majetok', 21, '22+23', 2 ),
		array( 'B.IV.1.', 'Peniaze a účty v bankách', 22, null, 3 ),
		array( 'B.IV.2.', 'Ostatné finančné účty', 23, null, 3 ),
		array( '', 'SPOLU VLASTNÉ IMANIE A ZÁVÄZKY', 24, '25+34', 0 ),
		array( 'A.', 'Vlastné imanie', 25, '26+29+30+31+32+33', 1 ),
		array( 'A.I.', 'Základné imanie', 26, '27+28', 2 ),
		array( 'A.I.1.', 'Základné imanie a zmeny základného imania', 27, null, 3 ),
		array( 'A.I.2.', 'Pohľadávky za upísané vlastné imanie', 28, null, 3 ),
		array( 'A.II.', 'Kapitálové fondy', 29, null, 2 ),
		array( 'A.III.', 'Fondy zo zisku', 30, null, 2 ),
		array( 'A.IV.', 'Oceňovacie rozdiely', 31, null, 2 ),
		array( 'A.V.', 'Nerozdelený zisk alebo neuhradená strata minulých rokov', 32, null, 2 ),
		array( 'A.VI.', 'Výsledok hospodárenia za účtovné obdobie po zdanení', 33, 'VH', 2 ),
		array( 'B.', 'Záväzky', 34, '35+36+37+38+43+44+45', 1 ),
		array( 'B.I.', 'Dlhodobé záväzky okrem rezerv a úverov', 35, null, 2 ),
		array( 'B.II.', 'Dlhodobé rezervy', 36, null, 2 ),
		array( 'B.III.', 'Dlhodobé bankové úvery', 37, null, 2 ),
		array( 'B.IV.', 'Krátkodobé záväzky okrem rezerv, úverov a výpomoci', 38, '39+40+41+42', 2 ),
		array( 'B.IV.1.', 'Krátkodobé záväzky z obchodného styku', 39, null, 3 ),
		array( 'B.IV.2.', 'Záväzky voči zamestnancom a zo sociálneho poistenia', 40, null, 3 ),
		array( 'B.IV.3.', 'Daňové záväzky a dotácie', 41, null, 3 ),
		array( 'B.IV.4.', 'Ostatné krátkodobé záväzky', 42, null, 3 ),
		array( 'B.V.', 'Krátkodobé rezervy', 43, null, 2 ),
		array( 'B.VI.', 'Bežné bankové úvery', 44, null, 2 ),
		array( 'B.VII.', 'Krátkodobé finančné výpomoci', 45, null, 2 ),
	);
}

/**
 * Zaradenie účtu do riadku súvahy. Pravidlá: [predpona, riadok pri zostatku MD, riadok pri zostatku Dal].
 * Prvé zhodné pravidlo (najdlhšia predpona má prednosť) rozhoduje. null = do súvahy sa nezaraďuje.
 */
function juc_muj_balance_rules() {
	$r = array(
		// Dlhodobý nehmotný majetok (aj oprávky 07x, opravné položky 091, 093, obstaranie 041, preddavky 051).
		'01' => array( 3, 3 ), '041' => array( 3, 3 ), '051' => array( 3, 3 ), '07' => array( 3, 3 ), '091' => array( 3, 3 ), '093' => array( 3, 3 ),
		// Dlhodobý hmotný majetok.
		'021' => array( 5, 5 ), '031' => array( 5, 5 ), '081' => array( 5, 5 ),
		'02' => array( 6, 6 ), '042' => array( 6, 6 ), '052' => array( 6, 6 ), '08' => array( 6, 6 ), '092' => array( 6, 6 ), '094' => array( 6, 6 ), '095' => array( 6, 6 ),
		'025' => array( 7, 7 ), '026' => array( 7, 7 ), '029' => array( 7, 7 ), '032' => array( 7, 7 ), '085' => array( 7, 7 ), '086' => array( 7, 7 ), '089' => array( 7, 7 ),
		'097' => array( 8, 8 ), '098' => array( 8, 8 ),
		// Dlhodobý finančný majetok.
		'061' => array( 10, 10 ), '062' => array( 10, 10 ), '063' => array( 10, 10 ), '043' => array( 10, 10 ), '053' => array( 10, 10 ),
		'06' => array( 11, 11 ), '096' => array( 11, 11 ),
		// Zásoby (111 a 131 pri spôsobe A nemajú mať zostatok – ak majú, zahrnú sa a zobrazí sa upozornenie).
		'11' => array( 15, 15 ), '12' => array( 15, 15 ), '13' => array( 15, 15 ), '19' => array( 15, 15 ),
		// Pohľadávky z obchodného styku a opravné položky 391.
		'31' => array( 18, 39 ), '391' => array( 18, 18 ),
		// Sociálne poistenie a dane (pohľadávka / záväzok podľa zostatku).
		'336' => array( 19, 40 ), '34' => array( 19, 41 ),
		// Ostatné pohľadávky.
		'33' => array( 20, 40 ), '35' => array( 20, 42 ), '37' => array( 20, 42 ), '381' => array( 20, 20 ), '382' => array( 20, 20 ), '385' => array( 20, 20 ), '398' => array( 20, 42 ),
		// Finančný majetok.
		'21' => array( 22, 22 ), '22' => array( 22, 44 ), '261' => array( 22, 22 ),
		'25' => array( 23, 23 ), '255' => array( 45, 45 ), '29' => array( 23, 23 ),
		// Vlastné imanie.
		'411' => array( 27, 27 ), '419' => array( 27, 27 ), '491' => array( 27, 27 ), '353' => array( 28, 28 ),
		'412' => array( 29, 29 ), '413' => array( 29, 29 ), '417' => array( 29, 29 ), '418' => array( 29, 29 ),
		'42' => array( 30, 30 ), '414' => array( 31, 31 ), '415' => array( 31, 31 ), '416' => array( 31, 31 ),
		'428' => array( 32, 32 ), '429' => array( 32, 32 ),
		// 431 – výsledok hospodárenia minulého roka v schvaľovaní (z otvorenia kníh) patrí medzi výsledky minulých rokov,
		// inak by sa cez rozdielový r. 33 zlial s výsledkom bežného obdobia.
		'431' => array( 32, 32 ),
		// Záväzky.
		'47' => array( 35, 35 ), '45' => array( 36, 36 ), '46' => array( 37, 37 ),
		'32' => array( 39, 39 ), '323' => array( 43, 43 ),
		'331' => array( 40, 40 ), '333' => array( 40, 40 ),
		'36' => array( 42, 42 ), '372' => array( 42, 42 ), '379' => array( 42, 42 ), '383' => array( 42, 42 ), '384' => array( 42, 42 ),
		'23' => array( 44, 44 ), '24' => array( 45, 45 ),
	);
	return apply_filters( 'juc_muj_balance_rules', $r );
}

/** Riadok súvahy pre účet a zostatok (MD − D), alebo 0 ak účet do súvahy nepatrí (výsledkové, závierkové). */
function juc_muj_row_for( $acc, $bal ) {
	static $rules = null;
	if ( null === $rules ) {
		$rules = juc_muj_balance_rules();
		uksort( $rules, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
	}
	if ( in_array( ( (string) $acc )[0], array( '5', '6', '7', '8', '9' ), true ) ) {
		return 0;
	}
	foreach ( $rules as $p => $rows ) {
		if ( 0 === strpos( $acc, (string) $p ) ) {
			return $bal >= 0 ? $rows[0] : $rows[1];
		}
	}
	return -1; // neznámy súvahový účet
}

/** Riadky výkazu ziskov a strát: [označenie, text, riadok, vzorec alebo predpony účtov, druh N/V]. */
function juc_muj_pl_rows() {
	return array(
		array( '*', 'Výnosy z hospodárskej činnosti spolu', 1, '2+3+4+5+6+7', '' ),
		array( 'I.', 'Tržby z predaja tovaru', 2, array( '604', '607' ), 'V' ),
		array( 'II.', 'Tržby z predaja vlastných výrobkov a služieb', 3, array( '601', '602', '606' ), 'V' ),
		array( 'III.', 'Zmena stavu vnútroorganizačných zásob (+/-)', 4, array( '61' ), 'V' ),
		array( 'IV.', 'Aktivácia', 5, array( '62' ), 'V' ),
		array( 'V.', 'Tržby z predaja dlhodobého nehmotného majetku, dlhodobého hmotného majetku a materiálu', 6, array( '641', '642' ), 'V' ),
		array( 'VI.', 'Ostatné výnosy z hospodárskej činnosti', 7, array( '644', '645', '646', '648', '655', '657' ), 'V' ),
		array( '*', 'Náklady na hospodársku činnosť spolu', 8, '9+10+11+12+13+14+15+16+17', '' ),
		array( 'A.', 'Náklady vynaložené na obstaranie predaného tovaru', 9, array( '504', '507' ), 'N' ),
		array( 'B.', 'Spotreba materiálu, energie a ostatných neskladovateľných dodávok', 10, array( '501', '502', '503', '505' ), 'N' ),
		array( 'C.', 'Služby', 11, array( '51' ), 'N' ),
		array( 'D.', 'Osobné náklady', 12, array( '52' ), 'N' ),
		array( 'E.', 'Dane a poplatky', 13, array( '53' ), 'N' ),
		array( 'F.', 'Odpisy a opravné položky k dlhodobému nehmotnému majetku a dlhodobému hmotnému majetku', 14, array( '551', '553' ), 'N' ),
		array( 'G.', 'Zostatková cena predaného dlhodobého majetku a predaného materiálu', 15, array( '541', '542' ), 'N' ),
		array( 'H.', 'Opravné položky k pohľadávkam', 16, array( '547' ), 'N' ),
		array( 'I.', 'Ostatné náklady na hospodársku činnosť', 17, array( '543', '544', '545', '546', '548', '549', '555', '557' ), 'N' ),
		array( '**', 'Výsledok hospodárenia z hospodárskej činnosti (+/-)', 18, '1-8', '' ),
		array( '*', 'Pridaná hodnota', 19, '2-9+3+4+5-10-11', '' ),
		array( '*', 'Výnosy z finančnej činnosti spolu', 20, '21+22+23+24+25+26', '' ),
		array( 'VII.', 'Tržby z predaja cenných papierov a podielov', 21, array( '661' ), 'V' ),
		array( 'VIII.', 'Výnosy z dlhodobého finančného majetku', 22, array( '665' ), 'V' ),
		array( 'IX.', 'Výnosy z krátkodobého finančného majetku', 23, array( '666' ), 'V' ),
		array( 'X.', 'Výnosové úroky', 24, array( '662' ), 'V' ),
		array( 'XI.', 'Kurzové zisky', 25, array( '663' ), 'V' ),
		array( 'XII.', 'Ostatné výnosy z finančnej činnosti', 26, array( '668' ), 'V' ),
		array( '*', 'Náklady na finančnú činnosť spolu', 27, '28+29+30+31+32+33', '' ),
		array( 'J.', 'Predané cenné papiere a podiely', 28, array( '561' ), 'N' ),
		array( 'K.', 'Náklady na krátkodobý finančný majetok', 29, array( '566' ), 'N' ),
		array( 'L.', 'Opravné položky k finančnému majetku (+/-)', 30, array( '565' ), 'N' ),
		array( 'M.', 'Nákladové úroky', 31, array( '562' ), 'N' ),
		array( 'N.', 'Kurzové straty', 32, array( '563' ), 'N' ),
		array( 'O.', 'Ostatné náklady na finančnú činnosť', 33, array( '568', '569' ), 'N' ),
		array( '**', 'Výsledok hospodárenia z finančnej činnosti (+/-)', 34, '20-27', '' ),
		array( '**', 'Výsledok hospodárenia za účtovné obdobie pred zdanením (+/-)', 35, '18+34', '' ),
		array( 'P.', 'Daň z príjmov', 36, array( '591', '595' ), 'N' ),
		array( 'Q.', 'Prevod podielov na výsledku hospodárenia spoločníkom (+/-)', 37, array( '596' ), 'N' ),
		array( '***', 'Výsledok hospodárenia za účtovné obdobie po zdanení (+/-)', 38, '35-36-37', '' ),
	);
}

/**
 * Zostatky účtov za obdobie k dátumu (bez uzávierkových dokladov série UZ): [ účet => [ps, md, d, bal] ].
 * $opening_only = len počiatočné stavy (otváracia súvaha).
 */
function juc_muj_balances( $year_id, $to = '', $opening_only = false ) {
	global $wpdb;
	$l = juc_t( 'lines' );
	$e = juc_t( 'entries' );
	$w = $wpdb->prepare( "l.year_id = %d AND e.series <> 'UZ'", $year_id ) . ( $to ? $wpdb->prepare( ' AND l.entry_date <= %s', $to ) : '' ) . ( $opening_only ? ' AND l.opening = 1' : '' );
	$rows = $wpdb->get_results( "SELECT acc, SUM(md) md, SUM(d) d, SUM(ps) ps FROM (
		SELECT l.md acc, SUM(l.amount) md, 0 d, SUM(IF(l.opening=1,l.amount,0)) ps FROM $l l JOIN $e e ON e.id = l.entry_id WHERE $w GROUP BY l.md
		UNION ALL SELECT l.d acc, 0, SUM(l.amount), -SUM(IF(l.opening=1,l.amount,0)) FROM $l l JOIN $e e ON e.id = l.entry_id WHERE $w GROUP BY l.d
	) x GROUP BY acc", ARRAY_A ); // phpcs:ignore
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ $r['acc'] ] = array( 'md' => (float) $r['md'], 'd' => (float) $r['d'], 'bal' => round( (float) $r['md'] - (float) $r['d'], 2 ) );
	}
	ksort( $out, SORT_STRING );
	return $out;
}

/** Vyhodnotenie vzorca nad hodnotami riadkov (napr. "2+3-9"). */
function juc_muj_formula( $f, array $vals ) {
	$sum = 0.0;
	if ( preg_match_all( '/([+-]?)(\d+)/', $f, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$sum += ( '-' === $x[1] ? -1 : 1 ) * (float) ( $vals[ (int) $x[2] ] ?? 0 );
		}
	}
	return $sum;
}

/** Súvaha z bilancií: [ riadok => hodnota v celých eurách ], detail [ riadok => [účet => suma] ], upozornenia. */
function juc_muj_balance_sheet( array $bals ) {
	$vals   = array();
	$detail = array();
	$warn   = array();
	foreach ( $bals as $acc => $b ) {
		if ( abs( $b['bal'] ) < 0.005 ) {
			continue;
		}
		$row = juc_muj_row_for( (string) $acc, $b['bal'] );
		if ( 0 === $row ) {
			continue;
		}
		if ( -1 === $row ) {
			$warn[] = 'Účet ' . $acc . ' (' . juc_account_name( $acc ) . ') so zostatkom ' . juc_money( $b['bal'] ) . ' € nemá riadok vo výkaze – skontrolujte účtovanie.';
			continue;
		}
		$v                       = $row <= 23 ? $b['bal'] : -1 * $b['bal'];
		$vals[ $row ]            = ( $vals[ $row ] ?? 0 ) + $v;
		$detail[ $row ][ $acc ]  = $v;
		if ( preg_match( '/^(111|131)/', (string) $acc ) ) {
			$warn[] = 'Účet ' . $acc . ' (obstaranie) má zostatok ' . juc_money( $b['bal'] ) . ' € – pri spôsobe A má byť na konci obdobia nulový (tovar na ceste?).';
		}
		if ( preg_match( '/^(351|373|376|361)/', (string) $acc ) ) {
			$warn[] = 'Účet ' . $acc . ' – zaradenie do riadku ' . $row . ' vzor výkazu výslovne neuvádza; overte s účtovníkom.';
		}
	}
	foreach ( $vals as $k => $v ) {
		$vals[ $k ] = round( $v );
	}
	// Súčtové riadky v poradí od najnižšej úrovne; r. 33 ako rozdiel, potom r. 25 a 24.
	foreach ( array( 4, 9, 2, 17, 21, 14, 1, 26, 38, 34 ) as $n ) {
		$vals[ $n ] = juc_muj_formula( juc_muj_row_def( $n )[3], $vals );
	}
	$vals[33] = $vals[1] - ( ( $vals[26] ?? 0 ) + ( $vals[29] ?? 0 ) + ( $vals[30] ?? 0 ) + ( $vals[31] ?? 0 ) + ( $vals[32] ?? 0 ) + ( $vals[34] ?? 0 ) );
	$vals[25] = juc_muj_formula( '26+29+30+31+32+33', $vals );
	$vals[24] = juc_muj_formula( '25+34', $vals );
	return array( $vals, $detail, array_values( array_unique( $warn ) ) );
}

function juc_muj_row_def( $n ) {
	foreach ( juc_muj_balance_rows() as $r ) {
		if ( (int) $r[2] === (int) $n ) {
			return $r;
		}
	}
	return array( '', '', $n, '', 0 );
}

/** Výkaz ziskov a strát: [ riadok => hodnota ], detail. */
function juc_muj_pl( array $bals ) {
	$vals   = array();
	$detail = array();
	$rows   = juc_muj_pl_rows();
	$used   = array();
	foreach ( $rows as $r ) {
		if ( ! is_array( $r[3] ) ) {
			continue;
		}
		$sum = 0.0;
		foreach ( $bals as $acc => $b ) {
			foreach ( $r[3] as $p ) {
				if ( 0 === strpos( (string) $acc, $p ) ) {
					$v                       = 'V' === $r[4] ? -1 * $b['bal'] : $b['bal'];
					$sum                    += $v;
					$detail[ $r[2] ][ $acc ] = $v;
					$used[ $acc ]            = true;
					break;
				}
			}
		}
		$vals[ $r[2] ] = round( $sum );
	}
	foreach ( $rows as $r ) {
		if ( ! is_array( $r[3] ) ) {
			$vals[ $r[2] ] = juc_muj_formula( $r[3], $vals );
		}
	}
	$warn = array();
	foreach ( $bals as $acc => $b ) {
		if ( in_array( ( (string) $acc )[0], array( '5', '6' ), true ) && abs( $b['bal'] ) >= 0.005 && empty( $used[ $acc ] ) ) {
			$warn[] = 'Účet ' . $acc . ' (' . juc_account_name( $acc ) . ') so stavom ' . juc_money( $b['bal'] ) . ' € nie je vo vzore výkazu ziskov a strát (napr. 564, 567, 592, 664, 667) – skontrolujte.';
		}
	}
	return array( $vals, $detail, $warn );
}

/* ---------------------------------------------------------------- Stránka */

function juc_page_muj() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$y      = juc_current_year();
	$end    = $y['end_date'];
	$to     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['to'] ?? '' ) ) ? $_GET['to'] : min( $end, current_time( 'Y-m-d' ) ); // phpcs:ignore
	$final  = $to >= $end;
	$s      = juc_settings();
	$cur    = juc_muj_balances( (int) $y['id'], $to );
	$prev_y = juc_year_of( gmdate( 'Y-m-d', strtotime( $y['start_date'] . ' -1 day' ) ) );
	// Predchádzajúce obdobie: zostatky ku koncu minulého roka v J-Účtovníctve, inak otváracia súvaha (počiatočné stavy).
	// Prázdny minulý rok (bez zápisov – napr. založený len kontrolou) sa berie ako neexistujúci.
	if ( $prev_y && ! juc_year_has_lines( (int) $prev_y['id'] ) ) {
		$prev_y = null;
	}
	$prev    = $prev_y ? juc_muj_balances( (int) $prev_y['id'], $prev_y['end_date'] ) : juc_muj_balances( (int) $y['id'], '', true );
	$prev_pl = $prev_y ? juc_muj_balances( (int) $prev_y['id'], $prev_y['end_date'] ) : array();
	list( $bs, $bs_det, $bs_warn ) = juc_muj_balance_sheet( $cur );
	list( $bs2 )                   = juc_muj_balance_sheet( $prev );
	list( $pl, $pl_det, $pl_warn ) = juc_muj_pl( $cur );
	list( $pl2 )                   = $prev_pl ? juc_muj_pl( $prev_pl ) : array( array() );
	$view   = sanitize_key( $_GET['v'] ?? 'suvaha' ); // phpcs:ignore
	juc_head( 'Účtovná závierka mikro účtovnej jednotky' );
	$print = juc_print_mode();
	juc_year_select( 'j-uctovnictvo-muj' );
	?>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-muj"><input type="hidden" name="year" value="<?php echo (int) $y['id']; ?>">
		<select name="v"><option value="suvaha" <?php selected( $view, 'suvaha' ); ?>>Súvaha (Úč MÚJ 1-01)</option><option value="vzs" <?php selected( $view, 'vzs' ); ?>>Výkaz ziskov a strát (Úč MÚJ 2-01)</option></select>
		<label>k dátumu <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" max="<?php echo esc_attr( $end ); ?>"></label>
		<button class="button">Zobraziť</button>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'print', 1 ) ); ?>">Tlačiť</a>
	</form>
	<div class="juc-muj-head">
		<strong><?php echo esc_html( $s['company'] ); ?></strong> · IČO <?php echo esc_html( $s['ico'] ); ?> · DIČ <?php echo esc_html( $s['dic'] ); ?><br>
		<?php echo esc_html( trim( $s['street'] . ', ' . $s['zip'] . ' ' . $s['city'], ', ' ) ); ?><?php echo $s['register'] ? ' · ' . esc_html( $s['register'] ) : ''; ?><br>
		<?php echo $final ? 'Riadna účtovná závierka' : '<span class="juc-warn">Priebežný stav (nie závierka)</span>'; ?> za obdobie <?php echo esc_html( juc_date( $y['start_date'] ) . ' – ' . juc_date( $final ? $end : $to ) ); ?> · sumy v celých eurách
	</div>
	<?php
	$warn = array_merge( $bs_warn, $pl_warn );
	if ( abs( ( $bs[1] ?? 0 ) - ( $bs[24] ?? 0 ) ) > 0.5 ) {
		$warn[] = 'Aktíva sa nerovnajú pasívam – skontrolujte obratovú predvahu.';
	}
	if ( abs( ( $pl[38] ?? 0 ) - ( $bs[33] ?? 0 ) ) > 1.5 ) {
		$warn[] = sprintf( 'Výsledok hospodárenia vo výkaze ziskov a strát (%s €) sa líši od súvahy r. 33 (%s €) – rozdiel spôsobujú počiatočné stavy alebo účty 7xx; skontrolujte počiatočné stavy (výsledok minulého roka patrí na 431 alebo 428/429).', number_format( $pl[38] ?? 0, 0, ',', ' ' ), number_format( $bs[33] ?? 0, 0, ',', ' ' ) );
	}
	if ( $warn ) {
		echo '<div class="juc-panel juc-noprint"><strong>Upozornenia</strong><ul class="juc-errs">';
		foreach ( $warn as $w ) {
			echo '<li>' . esc_html( $w ) . '</li>';
		}
		echo '</ul></div>';
	}
	$num = fn( $v ) => null === $v ? '' : number_format( (float) $v, 0, ',', ' ' );
	if ( 'vzs' === $view ) :
		?>
		<h2>Výkaz ziskov a strát Úč MÚJ 2-01</h2>
		<table class="widefat juc-table juc-muj">
			<thead><tr><th>Označenie</th><th>Text</th><th class="n">Č. r.</th><th class="n">Bežné účtovné obdobie</th><th class="n">Bezprostredne predchádzajúce</th></tr></thead>
			<tbody>
			<?php foreach ( juc_muj_pl_rows() as $r ) : ?>
				<tr class="<?php echo is_array( $r[3] ) ? '' : 'juc-sub'; ?>">
					<td><?php echo esc_html( $r[0] ); ?></td>
					<td><?php echo esc_html( $r[1] ); ?><?php juc_muj_detail( $pl_det[ $r[2] ] ?? array(), $print ); ?></td>
					<td class="n"><?php echo sprintf( '%02d', (int) $r[2] ); ?></td>
					<td class="n"><?php echo esc_html( $num( $pl[ $r[2] ] ?? 0 ) ); ?></td>
					<td class="n"><?php echo esc_html( $prev_pl ? $num( $pl2[ $r[2] ] ?? 0 ) : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	else :
		?>
		<h2>Súvaha Úč MÚJ 1-01</h2>
		<table class="widefat juc-table juc-muj">
			<thead><tr><th>Označenie</th><th>Strana aktív / pasív</th><th class="n">Č. r.</th><th class="n">Bežné účtovné obdobie</th><th class="n"><?php echo $prev_y ? 'Bezprostredne predchádzajúce' : 'Otváracia súvaha (počiatočné stavy)'; ?></th></tr></thead>
			<tbody>
			<?php foreach ( juc_muj_balance_rows() as $r ) : ?>
				<?php if ( 24 === $r[2] ) : ?><tr class="juc-class"><td colspan="5"><strong>STRANA PASÍV</strong></td></tr><?php elseif ( 1 === $r[2] ) : ?><tr class="juc-class"><td colspan="5"><strong>STRANA AKTÍV</strong></td></tr><?php endif; ?>
				<tr class="<?php echo $r[3] ? 'juc-sub' : ''; ?> juc-l<?php echo (int) $r[4]; ?>">
					<td><?php echo esc_html( $r[0] ); ?></td>
					<td><?php echo esc_html( $r[1] ); ?><?php juc_muj_detail( $bs_det[ $r[2] ] ?? array(), $print ); ?></td>
					<td class="n"><?php echo sprintf( '%02d', (int) $r[2] ); ?></td>
					<td class="n"><?php echo esc_html( $num( $bs[ $r[2] ] ?? 0 ) ); ?></td>
					<td class="n"><?php echo esc_html( $num( $bs2[ $r[2] ] ?? 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	endif;
	?>
	<p class="description">Podľa opatrenia MF SR č. MF/15464/2013-74 (vzor Úč MÚJ). Výsledok hospodárenia v súvahe (r. 33) je rozdielový riadok. Dlhodobé pohľadávky a záväzky (splatnosť nad 1 rok) veďte na samostatných analytických účtoch – J-Účtovníctvo ich zatiaľ vykazuje ako krátkodobé. Pred podaním do Registra účtovných závierok odporúčame kontrolu účtovníkom.</p>
	</div>
	<?php
}

/** Rozbalenie účtov riadku. */
function juc_muj_detail( array $det, $print ) {
	if ( ! $det || $print ) {
		return;
	}
	echo '<details class="juc-muj-det"><summary>' . count( $det ) . ( count( $det ) === 1 ? ' účet' : ( count( $det ) < 5 ? ' účty' : ' účtov' ) ) . '</summary><ul>';
	foreach ( $det as $acc => $v ) {
		echo '<li><a href="' . esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $acc ) ) ) . '">' . esc_html( $acc ) . '</a> ' . esc_html( juc_account_name( $acc ) ) . ': ' . esc_html( juc_money( $v ) ) . ' €</li>';
	}
	echo '</ul></details>';
}
