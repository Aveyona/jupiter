<?php
/**
 * Jadro podvojného účtovníctva: účtové obdobia, zaúčtovanie dokladu, storno, zostatky účtov.
 *
 * Zápis = suma na stranu MD účtu „md“ a na stranu Dal účtu „d“ – každý zápis je teda vyrovnaný.
 * Zaúčtovaný doklad sa nemení; oprava = storno (zápisy so zápornými sumami, „červené storno“) a nový doklad.
 * Do uzavretého obdobia (uzavretý rok alebo dátum uzávierky) sa neúčtuje.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------- Účtové obdobia */

function juc_years() {
	global $wpdb;
	return (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'years' ) . ' ORDER BY start_date DESC', ARRAY_A ); // phpcs:ignore
}

function juc_year( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'years' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

/** Účtové obdobie (kalendárny rok) – vytvorí ho, ak neexistuje. */
function juc_ensure_year( $year ) {
	global $wpdb;
	$start = sprintf( '%04d-01-01', $year );
	$id    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . juc_t( 'years' ) . ' WHERE start_date = %s', $start ) ); // phpcs:ignore
	if ( ! $id ) {
		$wpdb->insert( juc_t( 'years' ), array( 'start_date' => $start, 'end_date' => sprintf( '%04d-12-31', $year ), 'status' => 'open' ) );
		$id = (int) $wpdb->insert_id;
	}
	return $id;
}

/** Obdobie, do ktorého patrí dátum (alebo null). */
function juc_year_of( $date ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'years' ) . ' WHERE start_date <= %s AND end_date >= %s', $date, $date ), ARRAY_A ); // phpcs:ignore
}

/** Aktuálne zobrazené obdobie (výber používateľa alebo bežný rok). */
function juc_current_year() {
	$id = (int) ( $_GET['year'] ?? 0 ); // phpcs:ignore
	$y  = $id ? juc_year( $id ) : juc_year_of( current_time( 'Y-m-d' ) );
	if ( ! $y ) {
		$y = juc_year( juc_ensure_year( (int) current_time( 'Y' ) ) );
	}
	return $y;
}

/** Dá sa účtovať k dátumu? Vráti true alebo WP_Error. */
function juc_can_post( $date ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
		return new WP_Error( 'juc_date', 'Neplatný dátum.' );
	}
	$y = juc_year_of( $date );
	if ( ! $y ) {
		// Nové kalendárne obdobie sa otvorí automaticky (len ak nadväzuje na existujúce alebo je bežný rok).
		$yr = (int) substr( $date, 0, 4 );
		if ( $yr <= (int) current_time( 'Y' ) + 1 ) {
			juc_ensure_year( $yr );
			$y = juc_year_of( $date );
		}
	}
	if ( ! $y ) {
		return new WP_Error( 'juc_year', 'Účtové obdobie pre dátum ' . juc_date( $date ) . ' neexistuje.' );
	}
	if ( 'closed' === $y['status'] ) {
		return new WP_Error( 'juc_closed', 'Účtové obdobie ' . substr( $y['start_date'], 0, 4 ) . ' je uzavreté.' );
	}
	$lock = (string) juc_opt( 'lock_date' );
	if ( $lock && $date <= $lock && empty( $GLOBALS['juc_importing'] ) ) {
		return new WP_Error( 'juc_locked', 'Účtovníctvo je uzavreté do ' . juc_date( $lock ) . ' – zápis s dátumom ' . juc_date( $date ) . ' nie je možný.' );
	}
	return true;
}

/* ---------------------------------------------------------------- Zaúčtovanie */

/**
 * Zaúčtovanie dokladu.
 * $h: series, number (prázdne = automaticky), entry_date (dátum účtovného prípadu), doc_date, descr, partner_id,
 *     partner_name, vs, source, source_type, source_id, source_hash, status
 * $lines: [ [ 'md' => '311', 'd' => '604', 'amount' => 10.5, 'text' => '', 'partner_id' => 0, 'vs' => '' ], … ]
 * Vráti ID dokladu alebo WP_Error.
 */
function juc_post( array $h, array $lines ) {
	global $wpdb;
	$h = wp_parse_args( $h, array(
		'series' => 'ID', 'number' => '', 'entry_date' => current_time( 'Y-m-d' ), 'doc_date' => '', 'descr' => '',
		'partner_id' => 0, 'partner_name' => '', 'vs' => '', 'source' => '', 'source_type' => '', 'source_id' => 0,
		'source_hash' => '', 'status' => 'posted', 'storno_of' => 0, 'opening' => 0,
	) );
	$ok = juc_can_post( $h['entry_date'] );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$clean = array();
	foreach ( $lines as $l ) {
		$amt = round( (float) ( $l['amount'] ?? 0 ), 2 );
		if ( abs( $amt ) < 0.005 ) {
			continue;
		}
		$md = preg_replace( '/\D/', '', (string) ( $l['md'] ?? '' ) );
		$d  = preg_replace( '/\D/', '', (string) ( $l['d'] ?? '' ) );
		if ( '' === $md || '' === $d ) {
			return new WP_Error( 'juc_line', 'Zápis „' . ( $l['text'] ?? '' ) . '“ nemá vyplnený účet MD aj Dal.' );
		}
		if ( $md === $d ) {
			return new WP_Error( 'juc_line', 'Zápis nemôže mať rovnaký účet na strane MD aj Dal (' . $md . ').' );
		}
		foreach ( array( $md, $d ) as $acc ) {
			$a = juc_account_ensure( $acc );
			if ( is_wp_error( $a ) ) {
				return $a;
			}
		}
		$clean[] = array(
			'md'         => $md,
			'd'          => $d,
			'amount'     => $amt,
			'text'       => mb_substr( sanitize_text_field( (string) ( $l['text'] ?? '' ) ), 0, 255 ),
			'partner_id' => (int) ( $l['partner_id'] ?? $h['partner_id'] ),
			'vs'         => mb_substr( preg_replace( '/\s+/', '', (string) ( $l['vs'] ?? $h['vs'] ) ), 0, 20 ),
		);
	}
	if ( ! $clean ) {
		return new WP_Error( 'juc_empty', 'Doklad nemá žiadny zápis s nenulovou sumou.' );
	}
	$year = juc_year_of( $h['entry_date'] );
	$ser  = preg_replace( '/[^A-Z]/', '', strtoupper( (string) $h['series'] ) );
	$ser  = $ser ? $ser : 'ID';
	// Pokladňa nesmie mať záporný zostatok – platí pre každý zápis (formulár, nový doklad, automatické účtovanie, storno).
	// Výnimka: počiatočné stavy, uzávierka a import (prenos existujúceho účtovníctva).
	if ( empty( $h['opening'] ) && empty( $GLOBALS['juc_importing'] ) && ! in_array( $ser, array( 'PS', 'UZ' ), true ) && function_exists( 'juc_cash_check' ) ) {
		$ok = juc_cash_check( $h['entry_date'], $clean );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
	}
	$num = trim( (string) $h['number'] );
	// Poradové číslo série spotrebuje len automaticky číslovaný doklad. Storno a doklad s vlastným číslom dostanú seq 0
	// (inak by v číselnom rade, napr. PD/VD, vznikali medzery).
	$auto = '' === $num && 'storno' !== $h['status'];
	$wpdb->query( 'START TRANSACTION' );
	$seq = $auto ? 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . juc_t( 'entries' ) . ' WHERE year_id = %d AND series = %s FOR UPDATE', $year['id'], $ser ) ) : 0; // phpcs:ignore
	if ( '' === $num ) {
		$fmt = (string) ( juc_opt( 'format_' . $ser ) ?? '' );
		$num = juc_format_number( $fmt ? $fmt : $ser . '{RRRR}{nnnn}', (int) substr( $year['start_date'], 0, 4 ), $seq );
	}
	$ok = $wpdb->insert( juc_t( 'entries' ), array(
		'year_id'      => (int) $year['id'],
		'series'       => $ser,
		'number'       => mb_substr( $num, 0, 40 ),
		'seq'          => $seq,
		'entry_date'   => $h['entry_date'],
		'doc_date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $h['doc_date'] ) ? $h['doc_date'] : current_time( 'Y-m-d' ),
		'descr'        => mb_substr( sanitize_text_field( (string) $h['descr'] ), 0, 255 ),
		'partner_id'   => (int) $h['partner_id'],
		'partner_name' => mb_substr( sanitize_text_field( (string) $h['partner_name'] ), 0, 200 ),
		'vs'           => mb_substr( (string) $h['vs'], 0, 20 ),
		'total'        => round( array_sum( wp_list_pluck( $clean, 'amount' ) ), 2 ),
		'source'       => (string) $h['source'],
		'source_type'  => (string) $h['source_type'],
		'source_id'    => (int) $h['source_id'],
		'source_hash'  => (string) $h['source_hash'],
		'status'       => (string) $h['status'],
		'storno_of'    => (int) $h['storno_of'],
		'user_id'      => get_current_user_id(),
		'created_at'   => current_time( 'mysql' ),
	) );
	if ( ! $ok ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'juc_db', 'Doklad sa nepodarilo uložiť: ' . $wpdb->last_error );
	}
	$id = (int) $wpdb->insert_id;
	foreach ( $clean as $l ) {
		if ( ! $wpdb->insert( juc_t( 'lines' ), $l + array( 'entry_id' => $id, 'year_id' => (int) $year['id'], 'entry_date' => $h['entry_date'], 'opening' => $h['opening'] ? 1 : 0 ) ) ) {
			$err = $wpdb->last_error;
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'juc_db', 'Zápisy dokladu sa nepodarilo uložiť: ' . $err );
		}
	}
	$wpdb->query( 'COMMIT' );
	do_action( 'juc_posted', $id, $h );
	return $id;
}

function juc_entry( $id ) {
	global $wpdb;
	$e = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'entries' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	if ( $e ) {
		$e['lines'] = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'lines' ) . ' WHERE entry_id = %d ORDER BY id', $id ), ARRAY_A ); // phpcs:ignore
	}
	return $e;
}

/**
 * Storno dokladu: nový doklad s rovnakými zápismi so zápornou sumou, pôvodný dostane stav „cancelled“.
 * Dátum storna = dátum pôvodného dokladu, ak je obdobie otvorené, inak dnešný deň.
 */
function juc_storno( $id, $reason = '', $date = '' ) {
	global $wpdb;
	$e = juc_entry( $id );
	if ( ! $e ) {
		return new WP_Error( 'juc_storno', 'Doklad neexistuje.' );
	}
	if ( 'posted' !== $e['status'] ) {
		return new WP_Error( 'juc_storno', 'Stornovať sa dá len platný zaúčtovaný doklad.' );
	}
	if ( ! $date ) {
		$date = true === juc_can_post( $e['entry_date'] ) ? $e['entry_date'] : current_time( 'Y-m-d' );
	}
	$lines = array();
	foreach ( $e['lines'] as $l ) {
		$lines[] = array( 'md' => $l['md'], 'd' => $l['d'], 'amount' => -1 * (float) $l['amount'], 'text' => 'Storno: ' . $l['text'], 'partner_id' => $l['partner_id'], 'vs' => $l['vs'] );
	}
	$r = juc_post( array(
		'series'       => $e['series'],
		'number'       => 'S-' . $e['number'],
		'entry_date'   => $date,
		'descr'        => 'Storno ' . $e['number'] . ( $reason ? ' – ' . $reason : '' ),
		'partner_id'   => $e['partner_id'],
		'partner_name' => $e['partner_name'],
		'vs'           => $e['vs'],
		'source'       => $e['source'],
		'source_type'  => $e['source_type'],
		'source_id'    => $e['source_id'],
		'status'       => 'storno',
		'storno_of'    => $e['id'],
		'opening'      => ! empty( $e['lines'][0]['opening'] ),
	), $lines );
	if ( ! is_wp_error( $r ) ) {
		$wpdb->update( juc_t( 'entries' ), array( 'status' => 'cancelled' ), array( 'id' => $e['id'] ) );
	}
	return $r;
}

/** Platný (nestornovaný) doklad zo zdroja. */
function juc_entry_by_source( $source, $type, $sid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'entries' ) . " WHERE source = %s AND source_type = %s AND source_id = %d AND status = 'posted' ORDER BY id DESC LIMIT 1", $source, $type, $sid ), ARRAY_A ); // phpcs:ignore
}

/* ---------------------------------------------------------------- Zostatky */

/**
 * Obraty a zostatky účtov za obdobie (do dátumu). Vráti [ účet => [ps_md, ps_d, md, d] ].
 * Počiatočný stav = zápisy označené ako „opening“ (doklady PS). $group = 3 → po syntetických účtoch.
 */
function juc_turnovers( $year_id, $to = '', $from = '', $group = 0 ) {
	global $wpdb;
	$l    = juc_t( 'lines' );
	$w    = $wpdb->prepare( 'year_id = %d', $year_id );
	$w   .= $to ? $wpdb->prepare( ' AND entry_date <= %s', $to ) : '';
	$acc  = $group ? 'LEFT(%s, ' . (int) $group . ')' : '%s';
	$rows = $wpdb->get_results(
		'SELECT acc, SUM(ps_md) ps_md, SUM(ps_d) ps_d, SUM(md) md, SUM(d) d FROM (' .
		'SELECT ' . sprintf( $acc, 'md' ) . ' acc, SUM(IF(opening=1,amount,0)) ps_md, 0 ps_d, SUM(IF(opening=0' . ( $from ? $wpdb->prepare( ' AND entry_date >= %s', $from ) : '' ) . ',amount,0)) md, 0 d FROM ' . $l . ' WHERE ' . $w . ' GROUP BY acc ' .
		'UNION ALL SELECT ' . sprintf( $acc, 'd' ) . ' acc, 0, SUM(IF(opening=1,amount,0)), 0, SUM(IF(opening=0' . ( $from ? $wpdb->prepare( ' AND entry_date >= %s', $from ) : '' ) . ',amount,0)) FROM ' . $l . ' WHERE ' . $w . ' GROUP BY acc' .
		') x GROUP BY acc ORDER BY acc',
		ARRAY_A
	); // phpcs:ignore
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ $r['acc'] ] = array_map( 'floatval', array( 'ps_md' => $r['ps_md'], 'ps_d' => $r['ps_d'], 'md' => $r['md'], 'd' => $r['d'] ) );
	}
	// Obraty pred „from“ (pri výbere obdobia v roku) sa pripočítajú k počiatočnému stavu.
	if ( $from ) {
		$prev = $wpdb->get_results(
			'SELECT acc, SUM(md) md, SUM(d) d FROM (' .
			'SELECT ' . sprintf( $acc, 'md' ) . ' acc, SUM(amount) md, 0 d FROM ' . $l . $wpdb->prepare( ' WHERE year_id = %d AND opening = 0 AND entry_date < %s', $year_id, $from ) . ' GROUP BY acc ' .
			'UNION ALL SELECT ' . sprintf( $acc, 'd' ) . ' acc, 0, SUM(amount) FROM ' . $l . $wpdb->prepare( ' WHERE year_id = %d AND opening = 0 AND entry_date < %s', $year_id, $from ) . ' GROUP BY acc' .
			') x GROUP BY acc',
			ARRAY_A
		); // phpcs:ignore
		foreach ( (array) $prev as $r ) {
			$out[ $r['acc'] ]           = $out[ $r['acc'] ] ?? array( 'ps_md' => 0.0, 'ps_d' => 0.0, 'md' => 0.0, 'd' => 0.0 );
			$out[ $r['acc'] ]['ps_md'] += (float) $r['md'];
			$out[ $r['acc'] ]['ps_d']  += (float) $r['d'];
		}
		ksort( $out, SORT_STRING );
	}
	return $out;
}

/** Konečný zostatok (MD − Dal) z obratov. */
function juc_balance( array $t ) {
	return round( $t['ps_md'] + $t['md'] - $t['ps_d'] - $t['d'], 2 );
}

/** Zostatok jedného účtu (aj analytiky – prefix) k dátumu. */
function juc_account_balance( $acc, $to = '', $year_id = 0 ) {
	global $wpdb;
	$year_id = $year_id ? $year_id : (int) ( juc_year_of( $to ? $to : current_time( 'Y-m-d' ) )['id'] ?? 0 );
	$like    = $wpdb->esc_like( $acc ) . '%';
	$w       = $wpdb->prepare( 'year_id = %d', $year_id ) . ( $to ? $wpdb->prepare( ' AND entry_date <= %s', $to ) : '' );
	$md      = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount),0) FROM ' . juc_t( 'lines' ) . " WHERE $w" . $wpdb->prepare( ' AND md LIKE %s', $like ) ); // phpcs:ignore
	$d       = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(amount),0) FROM ' . juc_t( 'lines' ) . " WHERE $w" . $wpdb->prepare( ' AND d LIKE %s', $like ) ); // phpcs:ignore
	return round( $md - $d, 2 );
}

/* ---------------------------------------------------------------- Formáty */

function juc_money( $v, $empty = '' ) {
	if ( null === $v || '' === $v ) {
		return $empty;
	}
	return number_format( (float) $v, 2, ',', ' ' );
}

function juc_date( $ymd ) {
	return $ymd ? date_i18n( 'j. n. Y', strtotime( $ymd ) ) : '';
}
