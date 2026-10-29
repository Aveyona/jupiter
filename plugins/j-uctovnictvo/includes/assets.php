<?php
/**
 * Majetok: karty dlhodobého majetku, odpisový plán, zaradenie, mesačné odpisy a vyradenie.
 *
 * Účtovanie (zdroj „asset“, séria MA):
 *  - zaradenie do používania: MD 022/013 (majetok) / Dal 042/041 (obstaranie) alebo iný zdrojový účet,
 *  - odpisy: jeden doklad za mesiac (ukončené mesiace), MD 551 / Dal 082/073 za každú kartu,
 *  - vyradenie: MD 082/073 / Dal 022/013 (oprávky) a MD 541/551/549 / Dal 022/013 (zostatková cena).
 * Zápisy sa odvodzujú z karty – pri zmene karty sa dotknuté doklady stornujú a zaúčtujú znova (len v otvorenom období).
 *
 * Daňové odpisy podľa zákona č. 595/2003 Z. z. (§ 22 – § 29):
 *  - hmotný majetok so vstupnou cenou nad 1 700 €, nehmotný nad 2 400 €,
 *  - odpisové skupiny 0 – 6 (2, 4, 6, 8, 12, 20, 40 rokov), rovnomerné odpisovanie (§ 27), zrýchlené len skupiny 2 a 3 (§ 28),
 *  - v roku zaradenia pomerná časť ročného odpisu podľa počtu mesiacov od mesiaca zaradenia, ročný odpis sa zaokrúhľuje na celé eurá nahor,
 *  - mikrodaňovník môže v skupinách 0 – 4 uplatniť odpisy v ľubovoľnej výške do vstupnej ceny (metóda „vlastný plán“).
 * Účtovné odpisy = daňové (bežné pri mikro účtovnej jednotke); rozdiel by sa riešil v daňovom priznaní.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Majetok – J-Účtovníctvo', 'Majetok', JUC_CAP, 'j-uctovnictvo-assets', 'juc_page_assets' );
}, 15 );

/** Odpisové skupiny: skupina => [ roky, príklady ]. */
function juc_asset_groups() {
	return array(
		0 => array( 2, 'elektromobily a plug-in hybridy, elektrobicykle a kolobežky s pomocným motorom' ),
		1 => array( 4, 'počítače, notebooky, tlačiarne, servery, spotrebná elektronika, fotoaparáty, kancelárske stroje, osobné a nákladné autá' ),
		2 => array( 6, 'nábytok, domáce spotrebiče, svietidlá, prívesy, motocykle, hudobné nástroje, ostatný majetok bez zaradenia' ),
		3 => array( 8, 'elektromotory, generátory, transformátory, pece, horáky, spaľovacie motory' ),
		4 => array( 12, 'lode, lietadlá, železničné vozidlá, montované budovy z betónu alebo kovu' ),
		5 => array( 20, 'budovy, ktoré nie sú v skupine 6' ),
		6 => array( 40, 'bytové a administratívne budovy, hotely, inžinierske stavby' ),
	);
}

/** Druhy karty: kľúč => [ názov, účet majetku, oprávky, obstaranie ]. */
function juc_asset_kinds() {
	return array(
		'dhm'   => array( 'Dlhodobý hmotný majetok (nad 1 700 €)', '022', '082', '042' ),
		'dnm'   => array( 'Dlhodobý nehmotný majetok – softvér, licencie (nad 2 400 €)', '013', '073', '041' ),
		'small' => array( 'Drobný majetok – operatívna evidencia (náklad pri nákupe)', '', '', '' ),
	);
}

function juc_asset_methods() {
	return array(
		'linear' => 'Rovnomerné (§ 27)',
		'accel'  => 'Zrýchlené (§ 28, len skupina 2 a 3)',
		'own'    => 'Vlastný plán – mikrodaňovník (skupiny 0 – 4)',
	);
}

/** Spôsoby vyradenia: kľúč => [ názov, nákladový účet zostatkovej ceny ]. */
function juc_asset_disposals() {
	return array(
		'sale'   => array( 'Predaj', '541' ),
		'scrap'  => array( 'Likvidácia (vyradenie, opotrebovanie)', '551' ),
		'damage' => array( 'Škoda, krádež, manko', '549' ),
		'gift'   => array( 'Darovanie', '543' ),
	);
}

/** Zdrojové účty pri zaradení (protiúčet k 022/013). */
function juc_asset_sources() {
	return array(
		'042' => 'Obstaranie DHM – faktúra zaúčtovaná 042/321',
		'041' => 'Obstaranie DNM – faktúra zaúčtovaná 041/321',
		'321' => 'Priamo dodávateľ (faktúra bez účtu obstarania)',
		'479' => 'Nákup na splátky / úver (dlhodobý záväzok)',
		'461' => 'Bankový úver',
		'365' => 'Kúpil spoločník – záväzok voči spoločníkovi',
		'413' => 'Nepeňažný vklad spoločníka (ostatné kapitálové fondy)',
	);
}

function juc_assets( $all = false ) {
	global $wpdb;
	return (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'assets' ) . ( $all ? '' : " WHERE status <> 'deleted'" ) . ' ORDER BY in_use, id', ARRAY_A ); // phpcs:ignore
}

function juc_asset( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'assets' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

/** Doba odpisovania v rokoch. */
function juc_asset_years( array $a ) {
	if ( 'dnm' === $a['kind'] || (int) $a['years'] ) {
		return max( 1, (int) $a['years'] );
	}
	return juc_asset_groups()[ (int) $a['dep_group'] ][0] ?? 4;
}

/**
 * Odpisový plán po rokoch: [ rok => [ months, dep, acc (oprávky na konci roka), nbv ] ].
 * Počíta sa od mesiaca zaradenia; v roku vyradenia len po mesiac vyradenia.
 */
function juc_asset_plan( array $a ) {
	$plan = array();
	if ( 'small' === $a['kind'] || ! $a['in_use'] || (float) $a['price'] <= 0 ) {
		return $plan;
	}
	$price = (float) $a['price'];
	$y0    = (int) substr( $a['in_use'], 0, 4 );
	$m0    = (int) substr( $a['in_use'], 5, 2 );
	$yrs   = juc_asset_years( $a );
	$end   = $a['disposed'] ? $a['disposed'] : '';
	$own   = array_values( array_filter( array_map( fn( $v ) => (float) str_replace( array( ' ', ',' ), array( '', '.' ), $v ), preg_split( '/[;\s]+/', (string) $a['own_plan'] ) ), fn( $v ) => $v > 0 ) );
	$k     = array( 2 => array( 6, 7 ), 3 => array( 8, 9 ) );
	$acc   = 0.0;
	$n     = 0;
	for ( $y = $y0; $y < $y0 + $yrs + 2 && $acc < $price - 0.004; $y++ ) {
		$first  = $y === $y0;
		$months = $first ? 13 - $m0 : 12;
		if ( $end && (int) substr( $end, 0, 4 ) < $y ) {
			break;
		}
		$rest = round( $price - $acc, 2 );
		if ( 'own' === $a['method'] && $own ) {
			$dep = $own[ $n ] ?? 0.0;
		} elseif ( 'accel' === $a['method'] && isset( $k[ (int) $a['dep_group'] ] ) && 'dhm' === $a['kind'] ) {
			list( $k1, $k2 ) = $k[ (int) $a['dep_group'] ];
			$dep = $first ? ceil( $price / $k1 * $months / 12 ) : ( $k2 - $n > 1 ? ceil( 2 * $rest / ( $k2 - $n ) ) : $rest );
		} else {
			$dep = ceil( $price / $yrs * $months / 12 );
		}
		$dep = min( $rest, (float) $dep );
		// Vyradenie počas roka – pomerná časť ročného odpisu do mesiaca vyradenia.
		if ( $end && (int) substr( $end, 0, 4 ) === $y ) {
			$em  = (int) substr( $end, 5, 2 ) - ( $first ? $m0 - 1 : 0 );
			$dep = min( $rest, round( $dep * max( 0, $em ) / $months, 2 ) );
			$months = max( 0, $em );
		}
		$acc        = round( $acc + $dep, 2 );
		$plan[ $y ] = array( 'months' => $months, 'dep' => round( $dep, 2 ), 'acc' => $acc, 'nbv' => round( $price - $acc, 2 ) );
		++$n;
	}
	return $plan;
}

/** Mesačné odpisy karty: [ 'YYYYMM' => suma ] (ročný odpis rovnomerne na mesiace, zaokrúhlenie v poslednom mesiaci roka). */
function juc_asset_months( array $a ) {
	$out = array();
	foreach ( juc_asset_plan( $a ) as $y => $p ) {
		if ( $p['months'] < 1 || $p['dep'] <= 0 ) {
			continue;
		}
		$m1   = $y === (int) substr( $a['in_use'], 0, 4 ) ? (int) substr( $a['in_use'], 5, 2 ) : 1;
		$each = floor( $p['dep'] / $p['months'] * 100 ) / 100;
		$sum  = 0.0;
		for ( $i = 0; $i < $p['months']; $i++ ) {
			$amt                                    = $i === $p['months'] - 1 ? round( $p['dep'] - $sum, 2 ) : $each;
			$sum                                    = round( $sum + $amt, 2 );
			$out[ sprintf( '%04d%02d', $y, $m1 + $i ) ] = $amt;
		}
	}
	return $out;
}

/** Oprávky karty k mesiacu (vrátane). */
function juc_asset_acc_to( array $a, $ym ) {
	$s = 0.0;
	foreach ( juc_asset_months( $a ) as $m => $v ) {
		if ( $m <= $ym ) {
			$s += $v;
		}
	}
	return round( $s, 2 );
}

/** Účty karty s predvolenými hodnotami podľa druhu. */
function juc_asset_accounts( array $a ) {
	$k = juc_asset_kinds()[ $a['kind'] ] ?? juc_asset_kinds()['dhm'];
	return array(
		'asset'  => $a['acc_asset'] ? $a['acc_asset'] : $k[1],
		'dep'    => $a['acc_dep'] ? $a['acc_dep'] : $k[2],
		'cost'   => $a['acc_cost'] ? $a['acc_cost'] : '551',
		'source' => $a['acc_source'] ? $a['acc_source'] : $k[3],
	);
}

/* ---------------------------------------------------------------- Zaúčtovanie */

/** Doklad zaradenia karty (alebo null – drobný majetok, zrušená karta, zaradenie pred začiatkom účtovania v Jupiteri). */
function juc_asset_build_in( array $a ) {
	$start = juc_start_date();
	if ( 'small' === $a['kind'] || 'deleted' === $a['status'] || ! $a['in_use'] || ( $start && $a['in_use'] < $start ) ) {
		return null;
	}
	$acc = juc_asset_accounts( $a );
	return array(
		'head'  => array( 'series' => 'MA', 'entry_date' => $a['in_use'], 'doc_date' => $a['in_use'], 'descr' => 'Zaradenie majetku do používania – ' . $a['inv_no'] . ' ' . $a['name'], 'partner_name' => $a['supplier'], 'vs' => mb_substr( $a['doc_ref'], 0, 20 ) ),
		'lines' => array( array( 'md' => $acc['asset'], 'd' => $acc['source'], 'amount' => (float) $a['price'], 'text' => 'Zaradenie ' . $a['inv_no'] . ' ' . $a['name'] ) ),
	);
}

/** Doklad odpisov za mesiac – všetky karty. */
function juc_asset_build_dep( $ym, array $assets ) {
	$lines = array();
	foreach ( $assets as $a ) {
		if ( 'deleted' === $a['status'] ) {
			continue;
		}
		$m = juc_asset_months( $a );
		if ( ! empty( $m[ $ym ] ) ) {
			$acc     = juc_asset_accounts( $a );
			$lines[] = array( 'md' => $acc['cost'], 'd' => $acc['dep'], 'amount' => $m[ $ym ], 'text' => 'Odpis ' . $a['inv_no'] . ' ' . mb_substr( $a['name'], 0, 120 ) );
		}
	}
	if ( ! $lines ) {
		return null;
	}
	$to = gmdate( 'Y-m-t', strtotime( substr( $ym, 0, 4 ) . '-' . substr( $ym, 4, 2 ) . '-01' ) );
	return array(
		'head'  => array( 'series' => 'MA', 'number' => 'ODP-' . $ym, 'entry_date' => $to, 'doc_date' => $to, 'descr' => 'Odpisy dlhodobého majetku ' . substr( $ym, 4, 2 ) . '/' . substr( $ym, 0, 4 ) ),
		'lines' => $lines,
	);
}

/** Doklad vyradenia karty. */
function juc_asset_build_out( array $a ) {
	if ( 'disposed' !== $a['status'] || ! $a['disposed'] || 'small' === $a['kind'] ) {
		return null;
	}
	$acc  = juc_asset_accounts( $a );
	$dep  = juc_asset_acc_to( $a, gmdate( 'Ym', strtotime( $a['disposed'] ) ) );
	$nbv  = round( (float) $a['price'] - $dep, 2 );
	$how  = juc_asset_disposals()[ $a['disposed_how'] ] ?? juc_asset_disposals()['scrap'];
	$text = $how[0] . ' ' . $a['inv_no'] . ' ' . mb_substr( $a['name'], 0, 120 );
	return array(
		'head'  => array( 'series' => 'MA', 'entry_date' => $a['disposed'], 'doc_date' => $a['disposed'], 'descr' => 'Vyradenie majetku – ' . $text . ( $a['disposed_note'] ? ' (' . $a['disposed_note'] . ')' : '' ) ),
		'lines' => array(
			array( 'md' => $acc['dep'], 'd' => $acc['asset'], 'amount' => $dep, 'text' => 'Oprávky – ' . $text ),
			array( 'md' => $how[1], 'd' => $acc['asset'], 'amount' => $nbv, 'text' => 'Zostatková cena – ' . $text ),
		),
	);
}

/**
 * Zaúčtovanie majetku: zaradenia, vyradenia a odpisy za ukončené mesiace (do $through = 'YYYYMM', predvolene minulý mesiac).
 * Vráti [ posted, updated, cancelled, errors ].
 */
function juc_assets_sync( $through = '' ) {
	$res   = array( 'posted' => 0, 'updated' => 0, 'cancelled' => 0, 'errors' => array() );
	$start = juc_start_date();
	if ( ! $start ) {
		return $res;
	}
	$count = function ( $r, $label ) use ( &$res ) {
		if ( is_wp_error( $r ) ) {
			$res['errors'][ $label ] = $r->get_error_message();
		} elseif ( isset( $res[ $r ] ) ) {
			$res[ $r ]++;
		}
	};
	$all = juc_assets( true );
	foreach ( $all as $a ) {
		$count( juc_sync_source( 'asset', 'in', (int) $a['id'], juc_asset_build_in( $a ) ), 'Zaradenie ' . $a['inv_no'] );
	}
	$through = preg_match( '/^\d{6}$/', (string) $through ) ? $through : gmdate( 'Ym', strtotime( current_time( 'Y-m-01' ) . ' -1 month' ) );
	$from    = gmdate( 'Ym', strtotime( $start ) );
	for ( $m = $from; $m <= $through; $m = gmdate( 'Ym', strtotime( substr( $m, 0, 4 ) . '-' . substr( $m, 4, 2 ) . '-01 +1 month' ) ) ) {
		$built = juc_asset_build_dep( $m, $all );
		$y     = juc_year_of( substr( $m, 0, 4 ) . '-' . substr( $m, 4, 2 ) . '-01' );
		if ( $y && 'closed' === $y['status'] ) {
			continue;
		}
		if ( $built || juc_entry_by_source( 'asset', 'dep', (int) $m ) ) {
			$count( juc_sync_source( 'asset', 'dep', (int) $m, $built ), 'Odpisy ' . substr( $m, 4, 2 ) . '/' . substr( $m, 0, 4 ) );
		}
	}
	foreach ( $all as $a ) {
		$count( juc_sync_source( 'asset', 'out', (int) $a['id'], juc_asset_build_out( $a ) ), 'Vyradenie ' . $a['inv_no'] );
	}
	update_option( 'juc_assets_last', array( 'at' => current_time( 'mysql' ), 'through' => $through ) + $res, false );
	return $res;
}

// Automatické účtovanie: odpisy za ukončené mesiace spolu s ostatnými dokladmi.
add_action( 'juc_autopost', function () {
	if ( juc_opt( 'auto' ) && juc_assets( true ) ) {
		juc_assets_sync();
	}
}, 20 );

/** Nasledujúce inventárne číslo. */
function juc_asset_next_no( $year ) {
	global $wpdb;
	$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . juc_t( 'assets' ) . ' WHERE inv_no LIKE %s', 'IM' . $year . '%' ) ); // phpcs:ignore
	return sprintf( 'IM%04d%03d', $year, $n + 1 );
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_juc_asset_save', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_asset_save' );
	$in   = wp_unslash( $_POST );
	$id   = (int) ( $in['id'] ?? 0 );
	$back = juc_url( 'j-uctovnictvo-assets', $id ? array( 'asset' => $id, 'edit' => 1 ) : array( 'new' => 1 ) );
	$date = fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in[ $k ] ?? '' ) ) ? $in[ $k ] : null;
	$num  = fn( $k ) => preg_replace( '/\D/', '', (string) ( $in[ $k ] ?? '' ) );
	$row  = array(
		'name'       => mb_substr( sanitize_text_field( $in['name'] ?? '' ), 0, 200 ),
		'kind'       => isset( juc_asset_kinds()[ $in['kind'] ?? '' ] ) ? $in['kind'] : 'dhm',
		'dep_group'  => max( 0, min( 6, (int) ( $in['dep_group'] ?? 1 ) ) ),
		'method'     => isset( juc_asset_methods()[ $in['method'] ?? '' ] ) ? $in['method'] : 'linear',
		'years'      => max( 0, min( 99, (int) ( $in['years'] ?? 0 ) ) ),
		'own_plan'   => mb_substr( preg_replace( '/[^0-9,.;\s]/', '', (string) ( $in['own_plan'] ?? '' ) ), 0, 255 ),
		'price'      => round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['price'] ?? '0' ) ), 2 ),
		'acquired'   => $date( 'acquired' ),
		'in_use'     => $date( 'in_use' ),
		'acc_asset'  => $num( 'acc_asset' ),
		'acc_dep'    => $num( 'acc_dep' ),
		'acc_cost'   => $num( 'acc_cost' ),
		'acc_source' => $num( 'acc_source' ),
		'supplier'   => mb_substr( sanitize_text_field( $in['supplier'] ?? '' ), 0, 200 ),
		'doc_ref'    => mb_substr( sanitize_text_field( $in['doc_ref'] ?? '' ), 0, 100 ),
		'serial_no'  => mb_substr( sanitize_text_field( $in['serial_no'] ?? '' ), 0, 100 ),
		'place'      => mb_substr( sanitize_text_field( $in['place'] ?? '' ), 0, 100 ),
		'note'       => sanitize_textarea_field( $in['note'] ?? '' ),
		'updated_at' => current_time( 'mysql' ),
	);
	if ( '' === $row['name'] || $row['price'] <= 0 || ! $row['in_use'] ) {
		juc_back_err( $back, 'Vyplňte názov, vstupnú cenu a dátum zaradenia do používania.' );
	}
	if ( 'dnm' === $row['kind'] && ! $row['years'] ) {
		juc_back_err( $back, 'Pri nehmotnom majetku zadajte dobu odpisovania v rokoch.' );
	}
	if ( 'accel' === $row['method'] && ! in_array( $row['dep_group'], array( 2, 3 ), true ) ) {
		juc_back_err( $back, 'Zrýchlené odpisovanie sa dá použiť len v odpisovej skupine 2 alebo 3.' );
	}
	if ( 'own' === $row['method'] && $row['dep_group'] > 4 ) {
		juc_back_err( $back, 'Vlastný plán odpisov (mikrodaňovník) sa dá použiť len v skupinách 0 – 4.' );
	}
	if ( $row['acquired'] && $row['in_use'] < $row['acquired'] ) {
		juc_back_err( $back, 'Dátum zaradenia nemôže byť skôr ako dátum obstarania.' );
	}
	$old = $id ? juc_asset( $id ) : null;
	if ( $old ) {
		// Zmena karty mení zaúčtované doklady – v uzavretom období to nejde.
		$changed = abs( (float) $old['price'] - $row['price'] ) > 0.004;
		foreach ( array( 'in_use', 'kind', 'dep_group', 'method', 'years', 'own_plan', 'acc_asset', 'acc_dep', 'acc_cost', 'acc_source' ) as $k ) {
			$changed = $changed || (string) $old[ $k ] !== (string) $row[ $k ];
		}
		$from = max( (string) juc_start_date(), min( (string) $old['in_use'], (string) $row['in_use'] ) );
		if ( $changed && $old['in_use'] && is_wp_error( $ok = juc_can_post( $from ) ) ) {
			juc_back_err( $back, 'Kartu nie je možné zmeniť – zmena by menila doklady v uzavretom období. ' . $ok->get_error_message() );
		}
		$wpdb->update( juc_t( 'assets' ), $row, array( 'id' => $id ) );
	} else {
		$row['inv_no']     = sanitize_text_field( $in['inv_no'] ?? '' ) ? mb_substr( sanitize_text_field( $in['inv_no'] ), 0, 30 ) : juc_asset_next_no( (int) substr( $row['in_use'], 0, 4 ) );
		$row['status']     = 'active';
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( juc_t( 'assets' ), $row );
		$id = (int) $wpdb->insert_id;
	}
	$r = juc_assets_sync();
	if ( $r['errors'] ) {
		juc_back_err( juc_url( 'j-uctovnictvo-assets', array( 'asset' => $id ) ), 'Karta je uložená, ale niektoré doklady sa nezaúčtovali: ' . implode( ' · ', array_map( fn( $k, $v ) => $k . ': ' . $v, array_keys( $r['errors'] ), $r['errors'] ) ) );
	}
	wp_safe_redirect( juc_url( 'j-uctovnictvo-assets', array( 'asset' => $id, 'juc_msg' => 'asset' ) ) );
	exit;
} );

add_action( 'admin_post_juc_asset_dispose', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	check_admin_referer( 'juc_asset_dispose_' . $id );
	$a    = juc_asset( $id );
	$back = juc_url( 'j-uctovnictvo-assets', array( 'asset' => $id ) );
	$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) ); // phpcs:ignore
	$how  = sanitize_key( $_POST['how'] ?? '' ); // phpcs:ignore
	if ( ! $a || 'active' !== $a['status'] ) {
		juc_back_err( $back, 'Vyradiť sa dá len majetok v používaní.' );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date < (string) $a['in_use'] || ! isset( juc_asset_disposals()[ $how ] ) ) {
		juc_back_err( $back, 'Zadajte dátum vyradenia (nie skôr ako zaradenie) a spôsob vyradenia.' );
	}
	$ok = juc_can_post( $date );
	if ( is_wp_error( $ok ) ) {
		juc_back_err( $back, $ok->get_error_message() );
	}
	$wpdb->update( juc_t( 'assets' ), array( 'status' => 'disposed', 'disposed' => $date, 'disposed_how' => $how, 'disposed_note' => mb_substr( sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ), 0, 255 ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore
	// Odpisy do mesiaca vyradenia (vrátane) a doklad vyradenia.
	$r = juc_assets_sync( max( gmdate( 'Ym', strtotime( $date ) ), gmdate( 'Ym', strtotime( current_time( 'Y-m-01' ) . ' -1 month' ) ) ) );
	if ( $r['errors'] ) {
		juc_back_err( $back, implode( ' · ', $r['errors'] ) );
	}
	wp_safe_redirect( add_query_arg( 'juc_msg', 'asset', $back ) );
	exit;
} );

add_action( 'admin_post_juc_asset_delete', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	check_admin_referer( 'juc_asset_delete_' . $id );
	$a = juc_asset( $id );
	if ( $a && $a['in_use'] ) {
		$ok = juc_can_post( $a['in_use'] );
		if ( is_wp_error( $ok ) && 'small' !== $a['kind'] ) {
			juc_back_err( juc_url( 'j-uctovnictvo-assets', array( 'asset' => $id ) ), 'Kartu nie je možné zrušiť – zaradenie je v uzavretom období. Majetok vyraďte.' );
		}
	}
	$wpdb->update( juc_t( 'assets' ), array( 'status' => 'deleted', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
	juc_assets_sync();
	wp_safe_redirect( juc_url( 'j-uctovnictvo-assets', array( 'juc_msg' => 'asset' ) ) );
	exit;
} );

add_action( 'admin_post_juc_assets_run', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_assets_run' );
	$m = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['through'] ?? '' ) ); // phpcs:ignore
	$r = juc_assets_sync( 6 === strlen( $m ) ? $m : '' );
	$t = 'Zaúčtované: ' . $r['posted'] . ', opravené: ' . $r['updated'] . ', stornované: ' . $r['cancelled'] . '.';
	if ( $r['errors'] ) {
		juc_back_err( juc_url( 'j-uctovnictvo-assets' ), $t . ' Chyby: ' . implode( ' · ', array_map( fn( $k, $v ) => $k . ': ' . $v, array_keys( $r['errors'] ), $r['errors'] ) ) );
	}
	set_transient( 'juc_err_' . get_current_user_id(), $t, 120 );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-assets', array( 'juc_msg' => 'asset' ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Tlač: karta majetku a odpisový plán */

add_action( 'admin_post_juc_asset_print', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$ids = array_filter( array_map( 'intval', explode( ',', (string) ( $_GET['ids'] ?? '' ) ) ) ); // phpcs:ignore
	$all = $ids ? array_filter( array_map( 'juc_asset', $ids ) ) : juc_assets();
	juc_cash_print_head( 'Karty majetku' );
	foreach ( $all as $a ) {
		juc_asset_card( $a, false );
		echo '<div style="page-break-after:always;height:24px"></div>';
	}
	echo '</body></html>';
	exit;
} );

/** Karta majetku s odpisovým plánom. */
function juc_asset_card( array $a, $admin = true ) {
	$acc   = juc_asset_accounts( $a );
	$kinds = juc_asset_kinds();
	$grp   = juc_asset_groups();
	$plan  = juc_asset_plan( $a );
	$cur   = gmdate( 'Ym', strtotime( current_time( 'Y-m-01' ) . ' -1 month' ) );
	echo '<div class="' . ( $admin ? 'juc-panel' : 'doc' ) . '" style="max-width:none">';
	echo '<h2 style="margin-top:4px">Karta majetku ' . esc_html( $a['inv_no'] ) . ' – ' . esc_html( $a['name'] ) . '</h2>';
	$rows = array(
		'Druh'                => $kinds[ $a['kind'] ][0] ?? $a['kind'],
		'Vstupná cena'        => juc_money( $a['price'] ) . ' €',
		'Obstaranie'          => juc_date( $a['acquired'] ) . ( $a['supplier'] ? ' · ' . $a['supplier'] : '' ) . ( $a['doc_ref'] ? ' · doklad ' . $a['doc_ref'] : '' ),
		'Zaradenie do používania' => juc_date( $a['in_use'] ),
		'Výrobné číslo / umiestnenie' => trim( $a['serial_no'] . ( $a['place'] ? ' · ' . $a['place'] : '' ), ' ·' ),
	);
	if ( 'small' !== $a['kind'] ) {
		$rows['Odpisovanie'] = ( 'dnm' === $a['kind'] ? '' : 'skupina ' . (int) $a['dep_group'] . ' · ' ) . juc_asset_years( $a ) . ' r. · ' . ( juc_asset_methods()[ $a['method'] ] ?? '' );
		$rows['Účty']        = $acc['asset'] . ' majetok · ' . $acc['dep'] . ' oprávky · ' . $acc['cost'] . ' odpisy · zaradenie z ' . $acc['source'];
		$rows['Oprávky k ' . substr( $cur, 4, 2 ) . '/' . substr( $cur, 0, 4 )] = juc_money( juc_asset_acc_to( $a, $cur ) ) . ' € · zostatková cena ' . juc_money( (float) $a['price'] - juc_asset_acc_to( $a, $cur ) ) . ' €';
	}
	if ( 'disposed' === $a['status'] ) {
		$rows['Vyradenie'] = juc_date( $a['disposed'] ) . ' · ' . ( juc_asset_disposals()[ $a['disposed_how'] ][0] ?? '' ) . ( $a['disposed_note'] ? ' · ' . $a['disposed_note'] : '' );
	}
	if ( $a['note'] ) {
		$rows['Poznámka'] = $a['note'];
	}
	echo '<table class="widefat juc-table" style="margin-bottom:12px"><tbody>';
	foreach ( $rows as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<tr><th style="width:220px">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
	}
	echo '</tbody></table>';
	if ( $plan ) {
		echo '<h3>Odpisový plán (daňové = účtovné odpisy)</h3><table class="widefat striped juc-table"><thead><tr><th>Rok</th><th class="n">Mesiace</th><th class="n">Ročný odpis €</th><th class="n">Oprávky €</th><th class="n">Zostatková cena €</th></tr></thead><tbody>';
		foreach ( $plan as $y => $p ) {
			echo '<tr><td>' . (int) $y . '</td><td class="n">' . (int) $p['months'] . '</td><td class="n">' . esc_html( juc_money( $p['dep'] ) ) . '</td><td class="n">' . esc_html( juc_money( $p['acc'] ) ) . '</td><td class="n">' . esc_html( juc_money( $p['nbv'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Stránka */

function juc_page_assets() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$id   = (int) ( $_GET['asset'] ?? 0 ); // phpcs:ignore
	$edit = ! empty( $_GET['edit'] ) || ! empty( $_GET['new'] ); // phpcs:ignore
	$a    = $id ? juc_asset( $id ) : null;
	if ( 'asset' === ( $_GET['juc_msg'] ?? '' ) ) { // phpcs:ignore
		$t = get_transient( 'juc_err_' . get_current_user_id() );
		delete_transient( 'juc_err_' . get_current_user_id() );
		juc_head( 'Majetok' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t ? $t : 'Uložené a zaúčtované.' ) . '</p></div>';
	} else {
		juc_head( 'Majetok', ' <a class="page-title-action" href="' . esc_url( juc_url( 'j-uctovnictvo-assets', array( 'new' => 1 ) ) ) . '">Nová karta majetku</a>' );
	}
	if ( $edit ) {
		juc_asset_form( $a );
		echo '</div>';
		return;
	}
	if ( $a ) {
		echo '<p><a href="' . esc_url( juc_url( 'j-uctovnictvo-assets' ) ) . '">← Zoznam majetku</a></p>';
		juc_asset_card( $a );
		juc_asset_actions( $a );
		echo '</div>';
		return;
	}
	juc_assets_list();
	echo '</div>';
}

function juc_assets_list() {
	$all  = juc_assets();
	$cur  = gmdate( 'Ym', strtotime( current_time( 'Y-m-01' ) . ' -1 month' ) );
	$sum  = array( 'price' => 0.0, 'acc' => 0.0 );
	$last = get_option( 'juc_assets_last' );
	echo '<p class="description">Dlhodobý hmotný majetok so vstupnou cenou nad 1 700 € a nehmotný nad 2 400 € sa odpisuje (odpisové skupiny podľa prílohy č. 1 zákona o dani z príjmov). Lacnejší majetok je náklad pri nákupe – môžete ho viesť ako drobný majetok v operatívnej evidencii. Odpisy sa účtujú automaticky za každý ukončený mesiac (doklad ODP-RRRRMM, MD 551 / Dal 08x).</p>';
	if ( ! $all ) {
		echo '<div class="juc-panel"><p>Zatiaľ nie je evidovaný žiadny majetok. <a class="button button-primary" href="' . esc_url( juc_url( 'j-uctovnictvo-assets', array( 'new' => 1 ) ) ) . '">Pridať kartu majetku</a></p></div>';
		return;
	}
	echo '<table class="widefat striped juc-table"><thead><tr><th>Inv. číslo</th><th>Názov</th><th>Druh</th><th>Zaradenie</th><th>Odpisovanie</th><th class="n">Vstupná cena €</th><th class="n">Oprávky €</th><th class="n">Zostatková cena €</th><th>Stav</th></tr></thead><tbody>';
	foreach ( $all as $a ) {
		$acc = 'small' === $a['kind'] ? (float) $a['price'] : juc_asset_acc_to( $a, $a['disposed'] ? min( $cur, gmdate( 'Ym', strtotime( $a['disposed'] ) ) ) : $cur );
		if ( 'small' !== $a['kind'] && 'active' === $a['status'] ) {
			$sum['price'] += (float) $a['price'];
			$sum['acc']   += $acc;
		}
		$st = 'disposed' === $a['status'] ? 'vyradený ' . juc_date( $a['disposed'] ) : ( 'small' === $a['kind'] ? 'v evidencii' : 'v používaní' );
		echo '<tr' . ( 'disposed' === $a['status'] ? ' class="juc-muted"' : '' ) . '><td><a href="' . esc_url( juc_url( 'j-uctovnictvo-assets', array( 'asset' => $a['id'] ) ) ) . '">' . esc_html( $a['inv_no'] ) . '</a></td><td>' . esc_html( $a['name'] ) . '</td><td>' . esc_html( strtoupper( $a['kind'] ) ) . '</td><td>' . esc_html( juc_date( $a['in_use'] ) ) . '</td><td>' . ( 'small' === $a['kind'] ? '—' : esc_html( ( 'dnm' === $a['kind'] ? '' : 'sk. ' . (int) $a['dep_group'] . ', ' ) . juc_asset_years( $a ) . ' r.' ) ) . '</td><td class="n">' . esc_html( juc_money( $a['price'] ) ) . '</td><td class="n">' . esc_html( juc_money( $acc ) ) . '</td><td class="n">' . esc_html( juc_money( (float) $a['price'] - $acc ) ) . '</td><td>' . esc_html( $st ) . '</td></tr>';
	}
	echo '</tbody><tfoot><tr><th colspan="5">Dlhodobý majetok v používaní (k ' . esc_html( substr( $cur, 4, 2 ) . '/' . substr( $cur, 0, 4 ) ) . ')</th><th class="n">' . esc_html( juc_money( $sum['price'] ) ) . '</th><th class="n">' . esc_html( juc_money( $sum['acc'] ) ) . '</th><th class="n">' . esc_html( juc_money( $sum['price'] - $sum['acc'] ) ) . '</th><th></th></tr></tfoot></table>';
	// Kontrola evidencie s účtovníctvom.
	$y = juc_year_of( current_time( 'Y-m-d' ) );
	if ( $y ) {
		$book_price = round( juc_account_balance( '01', '', (int) $y['id'] ) + juc_account_balance( '02', '', (int) $y['id'] ), 2 );
		$book_acc   = round( -1 * ( juc_account_balance( '07', '', (int) $y['id'] ) + juc_account_balance( '08', '', (int) $y['id'] ) ), 2 );
		$ok         = abs( $book_price - $sum['price'] ) < 0.01 && abs( $book_acc - $sum['acc'] ) < 0.01;
		echo '<p class="' . ( $ok ? 'juc-ok' : 'juc-warn' ) . '">' . ( $ok ? '✓ ' : '⚠ ' ) . 'Účtovníctvo: majetok (01x, 02x) ' . esc_html( juc_money( $book_price ) ) . ' €, oprávky (07x, 08x) ' . esc_html( juc_money( $book_acc ) ) . ' €' . ( $ok ? ' – súhlasí s evidenciou.' : ' – nesúhlasí s evidenciou (skontrolujte počiatočné stavy alebo spustite zaúčtovanie).' ) . '</p>';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-filter">
		<?php wp_nonce_field( 'juc_assets_run' ); ?><input type="hidden" name="action" value="juc_assets_run">
		<label>Zaúčtovať odpisy do mesiaca <input type="month" name="through" value="<?php echo esc_attr( substr( $cur, 0, 4 ) . '-' . substr( $cur, 4, 2 ) ); ?>"></label>
		<button class="button button-primary">Zaúčtovať</button>
		<a class="button" target="_blank" href="<?php echo esc_url( admin_url( 'admin-post.php?action=juc_asset_print' ) ); ?>">Tlačiť karty majetku</a>
		<?php if ( $last ) : ?><span class="description">Naposledy <?php echo esc_html( juc_date( substr( $last['at'], 0, 10 ) ) . ' ' . substr( $last['at'], 11, 5 ) ); ?>.</span><?php endif; ?>
	</form>
	<?php
}

function juc_asset_actions( array $a ) {
	echo '<p class="juc-inline"><a class="button" href="' . esc_url( juc_url( 'j-uctovnictvo-assets', array( 'asset' => $a['id'], 'edit' => 1 ) ) ) . '">Upraviť kartu</a> <a class="button" target="_blank" href="' . esc_url( admin_url( 'admin-post.php?action=juc_asset_print&ids=' . (int) $a['id'] ) ) . '">Tlačiť kartu</a></p>';
	// Zaúčtované doklady karty.
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT e.id, e.number, e.entry_date, e.descr, e.status, l.amount, l.md, l.d FROM ' . juc_t( 'lines' ) . ' l JOIN ' . juc_t( 'entries' ) . " e ON e.id = l.entry_id WHERE e.source = 'asset' AND e.status = 'posted' AND l.text LIKE %s ORDER BY e.entry_date, e.id", '% ' . $wpdb->esc_like( $a['inv_no'] ) . ' %' ), ARRAY_A ); // phpcs:ignore
	if ( $rows ) {
		echo '<h3>Zaúčtované doklady</h3><table class="widefat striped juc-table"><thead><tr><th>Dátum</th><th>Doklad</th><th>MD</th><th>Dal</th><th class="n">Suma €</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( juc_date( $r['entry_date'] ) ) . '</td><td><a href="' . esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $r['id'] ) ) ) . '">' . esc_html( $r['number'] ) . '</a></td><td>' . esc_html( $r['md'] ) . '</td><td>' . esc_html( $r['d'] ) . '</td><td class="n">' . esc_html( juc_money( $r['amount'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( 'active' === $a['status'] && 'small' !== $a['kind'] ) :
		?>
		<div class="juc-panel">
			<h3 style="margin-top:4px">Vyradiť majetok</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-head-fields">
				<?php wp_nonce_field( 'juc_asset_dispose_' . $a['id'] ); ?><input type="hidden" name="action" value="juc_asset_dispose"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
				<label>Dátum<br><input type="date" name="date" required></label>
				<label>Spôsob<br><select name="how"><?php foreach ( juc_asset_disposals() as $k => $d ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $d[0] . ' (' . $d[1] . ')' ) . '</option>'; } ?></select></label>
				<label class="juc-wide">Poznámka (komu predaný, číslo faktúry, protokol o vyradení)<br><input name="note" class="large-text"></label>
				<div class="juc-wide"><button class="button" onclick="return confirm('Vyradiť majetok a zaúčtovať zostatkovú cenu?')">Vyradiť a zaúčtovať</button> <span class="description">Odpisy sa zaúčtujú do mesiaca vyradenia, potom MD 08x / Dal 02x (oprávky) a MD 541/551/549 / Dal 02x (zostatková cena). Tržbu z predaja vystavte faktúrou (účet 641).</span></div>
			</form>
		</div>
		<?php
	endif;
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
		<?php wp_nonce_field( 'juc_asset_delete_' . $a['id'] ); ?><input type="hidden" name="action" value="juc_asset_delete"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
		<button class="button-link juc-del" onclick="return confirm('Zrušiť kartu omylom založeného majetku? Zaúčtované zaradenie a odpisy sa stornujú.')">Zrušiť kartu (založená omylom)</button>
	</form>
	<?php
}

function juc_asset_form( $a ) {
	$a      = $a ? $a : array( 'id' => 0, 'inv_no' => '', 'name' => '', 'kind' => 'dhm', 'dep_group' => 1, 'method' => 'linear', 'years' => 0, 'own_plan' => '', 'price' => '', 'acquired' => '', 'in_use' => '', 'acc_asset' => '', 'acc_dep' => '', 'acc_cost' => '', 'acc_source' => '', 'supplier' => '', 'doc_ref' => '', 'serial_no' => '', 'place' => '', 'note' => '' );
	$groups = juc_asset_groups();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel juc-head-fields" id="juc-asset">
		<?php wp_nonce_field( 'juc_asset_save' ); ?><input type="hidden" name="action" value="juc_asset_save"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
		<h2 class="juc-wide" style="margin-top:4px"><?php echo $a['id'] ? 'Upraviť kartu ' . esc_html( $a['inv_no'] ) : 'Nová karta majetku'; ?></h2>
		<label class="juc-wide">Názov<br><input name="name" required class="large-text" value="<?php echo esc_attr( $a['name'] ); ?>" placeholder="napr. Počítač na strih videa (procesor, grafika, monitor)"></label>
		<label>Druh<br><select name="kind" id="ja-kind"><?php foreach ( juc_asset_kinds() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $a['kind'], $k, false ) . '>' . esc_html( $v[0] ) . '</option>'; } ?></select></label>
		<label>Vstupná cena €<br><input name="price" required inputmode="decimal" style="width:130px;text-align:right" value="<?php echo esc_attr( $a['price'] ); ?>"></label>
		<label>Dátum obstarania<br><input type="date" name="acquired" value="<?php echo esc_attr( $a['acquired'] ); ?>"></label>
		<label>Zaradenie do používania<br><input type="date" name="in_use" required value="<?php echo esc_attr( $a['in_use'] ); ?>"></label>
		<label class="ja-dep">Odpisová skupina<br><select name="dep_group"><?php foreach ( $groups as $g => $v ) { echo '<option value="' . (int) $g . '"' . selected( (int) $a['dep_group'], $g, false ) . '>' . (int) $g . ' – ' . (int) $v[0] . ' r. (' . esc_html( mb_substr( $v[1], 0, 60 ) ) . ')</option>'; } ?></select></label>
		<label class="ja-dep">Spôsob odpisovania<br><select name="method"><?php foreach ( juc_asset_methods() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $a['method'], $k, false ) . '>' . esc_html( $v ) . '</option>'; } ?></select></label>
		<label class="ja-dep">Doba odpisovania (roky)<br><input name="years" type="number" min="0" max="99" style="width:90px" value="<?php echo (int) $a['years'] ? (int) $a['years'] : ''; ?>"><small class="juc-muted">povinné pri nehmotnom; inak podľa skupiny</small></label>
		<label class="ja-dep juc-wide">Vlastný plán – ročné odpisy € oddelené bodkočiarkou (mikrodaňovník)<br><input name="own_plan" class="regular-text" value="<?php echo esc_attr( $a['own_plan'] ); ?>" placeholder="napr. 1000; 800; 500"></label>
		<label>Dodávateľ<br><input name="supplier" class="regular-text" value="<?php echo esc_attr( $a['supplier'] ); ?>"></label>
		<label>Doklad o obstaraní<br><input name="doc_ref" value="<?php echo esc_attr( $a['doc_ref'] ); ?>" placeholder="číslo faktúry / zmluvy"></label>
		<label>Výrobné číslo<br><input name="serial_no" value="<?php echo esc_attr( $a['serial_no'] ); ?>"></label>
		<label>Umiestnenie<br><input name="place" value="<?php echo esc_attr( $a['place'] ); ?>" placeholder="napr. kancelária Bešeňová"></label>
		<?php if ( ! $a['id'] ) : ?><label>Inventárne číslo<br><input name="inv_no" placeholder="automaticky" style="width:130px"></label><?php endif; ?>
		<fieldset class="juc-wide ja-dep"><legend><strong>Účty</strong> (prázdne = podľa druhu)</legend>
			<label>Majetok <input name="acc_asset" list="juc-accs" style="width:90px" value="<?php echo esc_attr( $a['acc_asset'] ); ?>" placeholder="022"></label>
			<label>Oprávky <input name="acc_dep" list="juc-accs" style="width:90px" value="<?php echo esc_attr( $a['acc_dep'] ); ?>" placeholder="082"></label>
			<label>Odpisy <input name="acc_cost" list="juc-accs" style="width:90px" value="<?php echo esc_attr( $a['acc_cost'] ); ?>" placeholder="551"></label>
			<label>Zaradenie z účtu <select name="acc_source"><option value="">podľa druhu (042 / 041)</option><?php foreach ( juc_asset_sources() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $a['acc_source'], $k, false ) . '>' . esc_html( $k . ' – ' . $v ) . '</option>'; } ?></select></label>
		</fieldset>
		<label class="juc-wide">Poznámka<br><textarea name="note" rows="2" class="large-text"><?php echo esc_textarea( $a['note'] ); ?></textarea></label>
		<div class="juc-wide"><button class="button button-primary">Uložiť a zaúčtovať</button> <a class="button" href="<?php echo esc_url( $a['id'] ? juc_url( 'j-uctovnictvo-assets', array( 'asset' => $a['id'] ) ) : juc_url( 'j-uctovnictvo-assets' ) ); ?>">Späť</a>
		<p class="description">Zaradenie sa zaúčtuje k dátumu zaradenia (MD 022 / Dal 042 – faktúru za majetok zaúčtujte v J-Fakturácii do kategórie „Majetok“ na 042). Odpisy sa zaúčtujú za ukončené mesiace. Ak bol majetok zaradený pred začiatkom účtovania v Jupiteri, zaradenie je v počiatočných stavoch a účtujú sa len odpisy.</p></div>
	</form>
	<?php juc_accounts_datalist(); ?>
	<script>
	(function () {
		var k = document.getElementById('ja-kind');
		function t() { document.querySelectorAll('#juc-asset .ja-dep').forEach(function (e) { e.style.display = k.value === 'small' ? 'none' : ''; }); }
		k.addEventListener('change', t); t();
	})();
	</script>
	<?php
}
