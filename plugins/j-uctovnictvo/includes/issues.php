<?php
/**
 * Na riešenie: prehľad úloh, rozhodnutí, otázok pre účtovníka a poznámok k účtovníctvu firmy.
 *
 * Dva zdroje položiek:
 *  - automatické (kontroly programu): chýbajúci doklad k pohybu v banke, prijatá faktúra bez PDF,
 *    faktúra po splatnosti, banka nesedí s výpisom, záporná pokladňa, termíny (DPPO a závierka).
 *    Vzniknú a zavrú sa samy podľa stavu účtovníctva (kľúč auto_key).
 *  - ručné: úloha, rozhodnutie, otázka pre účtovníka, poznámka – so stavom, termínom, prioritou,
 *    väzbou na doklad / pohyb / účet / partnera / rok a záznamami (história riešenia).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function juc_issue_kinds() {
	return array(
		'task'     => 'Úloha',
		'decision' => 'Rozhodnutie',
		'question' => 'Otázka pre účtovníka',
		'note'     => 'Poznámka',
		'missing'  => 'Chýba doklad',
		'check'    => 'Kontrola',
		'deadline' => 'Termín',
	);
}

function juc_issue_statuses() {
	return array( 'open' => 'Čaká', 'doing' => 'Rieši sa', 'done' => 'Hotové', 'dismissed' => 'Netreba riešiť' );
}

/** Priorita: 1 = najvyššia. */
function juc_issue_priorities() {
	return array( 1 => 'Vysoká', 2 => 'Stredná', 3 => 'Nízka' );
}

/** Priorita chýbajúceho dokladu podľa sumy. */
function juc_issue_amount_priority( $amount ) {
	$a = abs( (float) $amount );
	return $a >= 100 ? 1 : ( $a >= 10 ? 2 : 3 );
}

function juc_issue_owners() {
	return array( 'me' => 'Ja', 'accountant' => 'Účtovník' );
}

function juc_issue_link_types() {
	return array( '' => '—', 'entry' => 'Účtovný doklad', 'banktx' => 'Pohyb v banke', 'doc' => 'Faktúra', 'account' => 'Účet', 'partner' => 'Partner', 'year' => 'Účtovné obdobie' );
}

function juc_issue( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'issues' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

function juc_issue_log( $id, $text, $user = null ) {
	global $wpdb;
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return;
	}
	$wpdb->insert( juc_t( 'issue_log' ), array( 'issue_id' => (int) $id, 'user_id' => null === $user ? get_current_user_id() : (int) $user, 'text' => $text, 'created_at' => current_time( 'mysql' ) ) );
}

/** Uloženie položky (nová alebo úprava). Vráti ID. */
function juc_issue_save( array $in, $id = 0 ) {
	global $wpdb;
	$old = $id ? juc_issue( $id ) : null;
	$row = array();
	foreach ( array( 'title', 'body', 'kind', 'owner', 'status', 'priority', 'due', 'link_type', 'link_id', 'auto_key' ) as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$row[ $k ] = $in[ $k ];
		}
	}
	if ( isset( $row['title'] ) ) {
		$row['title'] = mb_substr( sanitize_text_field( $row['title'] ), 0, 255 );
	}
	if ( isset( $row['body'] ) ) {
		$row['body'] = sanitize_textarea_field( $row['body'] );
	}
	if ( isset( $row['kind'] ) && ! isset( juc_issue_kinds()[ $row['kind'] ] ) ) {
		$row['kind'] = 'task';
	}
	if ( isset( $row['status'] ) && ! isset( juc_issue_statuses()[ $row['status'] ] ) ) {
		$row['status'] = 'open';
	}
	if ( isset( $row['owner'] ) && ! isset( juc_issue_owners()[ $row['owner'] ] ) ) {
		$row['owner'] = 'me';
	}
	if ( isset( $row['priority'] ) ) {
		$row['priority'] = max( 1, min( 3, (int) $row['priority'] ) );
	}
	if ( isset( $row['due'] ) ) {
		$row['due'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $row['due'] ) ? $row['due'] : null;
	}
	if ( isset( $row['link_type'] ) && ! isset( juc_issue_link_types()[ $row['link_type'] ] ) ) {
		$row['link_type'] = '';
	}
	if ( isset( $row['link_id'] ) ) {
		$row['link_id'] = mb_substr( preg_replace( '/[^\w\-:.]/u', '', (string) $row['link_id'] ), 0, 40 );
	}
	$row['updated_at'] = current_time( 'mysql' );
	if ( isset( $row['status'] ) ) {
		$row['closed_at'] = in_array( $row['status'], array( 'done', 'dismissed' ), true ) ? ( $old && $old['closed_at'] ? $old['closed_at'] : current_time( 'mysql' ) ) : null;
	}
	if ( $old ) {
		$wpdb->update( juc_t( 'issues' ), $row, array( 'id' => (int) $old['id'] ) );
		if ( isset( $row['status'] ) && $row['status'] !== $old['status'] ) {
			juc_issue_log( $old['id'], 'Stav: ' . juc_issue_statuses()[ $old['status'] ] . ' → ' . juc_issue_statuses()[ $row['status'] ] . '.' );
		}
		return (int) $old['id'];
	}
	$row += array( 'kind' => 'task', 'owner' => 'me', 'status' => 'open', 'priority' => 2, 'link_type' => '', 'link_id' => '', 'auto_key' => '', 'body' => '', 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) );
	$wpdb->insert( juc_t( 'issues' ), $row );
	wp_cache_delete( 'juc_issues_n', 'juc' );
	return (int) $wpdb->insert_id;
}

/* ---------------------------------------------------------------- Automatické kontroly */

/** Aktuálne nájdené problémy: [ auto_key => [ kind, title, body, priority, due, link_type, link_id, owner ] ]. */
function juc_issues_detect() {
	global $wpdb;
	$out   = array();
	$today = current_time( 'Y-m-d' );
	$start = juc_start_date();
	// 1) Pohyby v banke bez zaúčtovania (chýba doklad alebo rozhodnutie).
	if ( function_exists( 'juc_queue_bank' ) ) {
		foreach ( juc_queue_bank() as $t ) {
			$who  = trim( (string) $t['counter_name'] );
			$what = $who ? $who : trim( preg_replace( '/^(Nákup|Kredit):\s*/u', '', (string) $t['message'] ) );
			$what = mb_substr( preg_replace( '/,.*$/u', '', $what ), 0, 60 );
			if ( '' === $what && $t['vs'] ) {
				$what = 'platba VS ' . $t['vs'];
			}
			$out[ 'banktx:' . $t['id'] ] = array(
				'kind'      => 'missing',
				'title'     => 'Chýba doklad: ' . ( $what ? $what : $t['tx_type'] ) . ' ' . juc_money( $t['amount'] ) . ' € (' . juc_date( $t['tx_date'] ) . ')',
				'body'      => trim( $t['message'] . ( $t['vs'] ? ' · VS ' . $t['vs'] : '' ) . ' · ' . $t['tx_type'], ' ·' ) . "\nDoplňte faktúru (J-Fakturácia → Prijaté faktúry a spárujte s pohybom v Banke) alebo zaúčtujte pohyb v „Na zaúčtovanie“.",
				'priority'  => juc_issue_amount_priority( $t['amount'] ),
				'link_type' => 'banktx',
				'link_id'   => (string) $t['id'],
			);
		}
	}
	if ( function_exists( 'jinv_table' ) && $start ) {
		// 2) Prijaté faktúry bez PDF.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, number, customer_name, total, issue_date, data FROM ' . jinv_table() . " WHERE kind = 'received' AND status <> 'cancelled' AND issue_date >= %s", $start ), ARRAY_A ) as $d ) { // phpcs:ignore
			$data = json_decode( (string) $d['data'], true );
			if ( empty( $data['file'] ) ) {
				$out[ 'nopdf:' . $d['id'] ] = array( 'kind' => 'missing', 'title' => 'Prijatá faktúra bez PDF: ' . $d['number'] . ' – ' . $d['customer_name'] . ' ' . juc_money( $d['total'] ) . ' €', 'body' => 'K faktúre nie je priložený originál (PDF alebo sken). Doklad musí byť k dispozícii 10 rokov.', 'priority' => 3, 'link_type' => 'doc', 'link_id' => (string) $d['id'] );
			}
		}
		// 3) Faktúry po splatnosti.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, kind, number, customer_name, total, due_date FROM ' . jinv_table() . " WHERE kind IN ('issued','received') AND status NOT IN ('paid','cancelled') AND due_date < %s AND issue_date >= %s", $today, $start ), ARRAY_A ) as $d ) { // phpcs:ignore
			$out[ 'overdue:' . $d['id'] ] = array( 'kind' => 'check', 'title' => ( 'issued' === $d['kind'] ? 'Neuhradená vydaná faktúra ' : 'Neuhradená prijatá faktúra ' ) . $d['number'] . ' – ' . $d['customer_name'] . ' ' . juc_money( $d['total'] ) . ' €, splatná ' . juc_date( $d['due_date'] ), 'body' => 'issued' === $d['kind'] ? 'Pripomenúť odberateľovi alebo spárovať platbu v Banke.' : 'Uhradiť alebo spárovať platbu v Banke.', 'priority' => 1, 'link_type' => 'doc', 'link_id' => (string) $d['id'] );
		}
	}
	// 3b) Úhrady bez určenia účtu (zápočet, iné) – čakajú v „Na zaúčtovanie“.
	if ( function_exists( 'juc_queue_payments' ) ) {
		foreach ( juc_queue_payments() as $p ) {
			$out[ 'pay:' . $p['id'] ] = array(
				'kind'      => 'decision',
				'title'     => 'Nezaúčtovaná úhrada (' . ( function_exists( 'jinv_pay_methods' ) ? ( jinv_pay_methods()[ $p['method'] ] ?? $p['method'] ) : $p['method'] ) . '): ' . $p['doc_number'] . ' – ' . juc_money( $p['amount'] ) . ' € (' . juc_date( $p['pay_date'] ) . ')',
				'body'      => trim( $p['note'] . "\nÚhradu zápočtom alebo iným spôsobom program nevie zaúčtovať sám. Zaúčtujte ju v „Na zaúčtovanie“ zadaním protiúčtu (napr. 321 pri zápočte s dodávateľom, 211 pokladňa, 355 konateľ)." ),
				'priority'  => 2,
				'link_type' => 'doc',
				'link_id'   => (string) $p['doc_id'],
			);
		}
	}
	// 4) Banka: zostatok v účtovníctve vs. výpis.
	if ( function_exists( 'jinv_bank_accounts' ) && function_exists( 'jinv_bank_balance_at' ) ) {
		$map = (array) juc_opt( 'bank_map' );
		foreach ( jinv_bank_accounts() as $aid => $a ) {
			// Len čítanie: analytický účet banky založí až účtovanie prvého pohybu (juc_bank_account).
			$acc = (string) ( $map[ $aid ] ?? '' );
			$y   = juc_year_of( $today );
			if ( ! $acc || ! $y ) {
				continue;
			}
			$bank = jinv_bank_balance_at( $aid, $today );
			if ( null === $bank || false === $bank ) {
				continue;
			}
			$book = juc_account_balance( $acc, $today, (int) $y['id'] );
			if ( abs( (float) $bank - $book ) > 0.004 ) {
				$out[ 'bank:' . $aid ] = array( 'kind' => 'check', 'title' => 'Banka nesedí: účtovníctvo ' . juc_money( $book ) . ' € / výpis ' . juc_money( $bank ) . ' € (' . ( $a['name'] ?? $acc ) . ')', 'body' => 'Rozdiel ' . juc_money( $book - (float) $bank ) . ' €. Najčastejšie sú to nezaúčtované pohyby („Na zaúčtovanie“) – po ich zaúčtovaní sa kontrola uzavrie sama.', 'priority' => 1, 'link_type' => 'account', 'link_id' => $acc );
			}
		}
	}
	// 5) Záporná pokladňa a termíny podľa účtových období.
	foreach ( juc_years() as $y ) {
		if ( 'closed' === $y['status'] ) {
			continue;
		}
		$yr = (int) substr( $y['start_date'], 0, 4 );
		if ( function_exists( 'juc_cash_book' ) ) {
			$b = juc_cash_book( (int) $y['id'] );
			if ( $b['negative'] ) {
				$out[ 'cashneg:' . $yr ] = array( 'kind' => 'check', 'title' => 'Záporný zostatok pokladne v roku ' . $yr . ' (prvýkrát ' . juc_date( $b['negative'][0] ) . ')', 'body' => 'Pokladňa nesmie mať záporný zostatok – skontrolujte poradie a sumy pokladničných dokladov.', 'priority' => 1, 'link_type' => 'year', 'link_id' => (string) $yr );
			}
		}
		// Termín: účtovná závierka a daňové priznanie DPPO do 31. 3. nasledujúceho roka (roky od začiatku účtovania, pred termínom).
		$due = ( $yr + 1 ) . '-03-31';
		if ( $start && $yr >= (int) substr( $start, 0, 4 ) && $yr <= (int) current_time( 'Y' ) && $due >= $today ) {
			$out[ 'deadline:dppo:' . $yr ] = array( 'kind' => 'deadline', 'title' => 'Účtovná závierka a daňové priznanie DPPO za ' . $yr . ' – podať do 31. 3. ' . ( $yr + 1 ), 'body' => 'Do 31. 3. ' . ( $yr + 1 ) . ":\n- uzavrieť rok " . $yr . " (inventúra, zásoby, odpisy, daň z príjmov 591/341),\n- zostaviť závierku Úč MÚJ (súvaha, výkaz ziskov a strát, poznámky),\n- podať daňové priznanie DPPO s prílohou závierky na Finančnú správu (tým sa závierka uloží aj do Registra účtovných závierok),\n- zaplatiť daň z príjmov.\nLehotu na podanie priznania sa dá predĺžiť oznámením do 31. 3. najviac o 3 mesiace.", 'priority' => 1, 'due' => $due, 'link_type' => 'year', 'link_id' => (string) $yr, 'owner' => 'accountant' );
		}
	}
	return apply_filters( 'juc_issues_detect', $out );
}

/** Zosúladenie automatických položiek s aktuálnym stavom. Vráti [ new, closed ]. */
function juc_issues_refresh() {
	global $wpdb;
	if ( ! juc_lock( 'juc_issues' ) ) {
		return array( 0, 0 );
	}
	$found = juc_issues_detect();
	$have  = array();
	foreach ( (array) $wpdb->get_results( 'SELECT id, auto_key, status, title FROM ' . juc_t( 'issues' ) . " WHERE auto_key <> ''", ARRAY_A ) as $r ) { // phpcs:ignore
		$have[ $r['auto_key'] ] = $r;
	}
	$new    = 0;
	$closed = 0;
	foreach ( $found as $key => $f ) {
		if ( isset( $have[ $key ] ) ) {
			$h = $have[ $key ];
			// Problém sa vrátil po automatickom vyriešení → znova otvoriť (ručne „netreba riešiť“ ostáva).
			if ( 'done' === $h['status'] ) {
				$wpdb->update( juc_t( 'issues' ), array( 'status' => 'open', 'closed_at' => null, 'title' => $f['title'], 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $h['id'] ) );
				juc_issue_log( $h['id'], 'Znova otvorené – kontrola opäť našla problém.', 0 );
			} elseif ( $h['title'] !== $f['title'] && 'dismissed' !== $h['status'] ) {
				$wpdb->update( juc_t( 'issues' ), array( 'title' => $f['title'] ), array( 'id' => $h['id'] ) );
			}
			continue;
		}
		juc_issue_save( $f + array( 'auto_key' => $key, 'owner' => $f['owner'] ?? 'me' ) );
		++$new;
	}
	foreach ( $have as $key => $h ) {
		if ( isset( $found[ $key ] ) || ! in_array( $h['status'], array( 'open', 'doing' ), true ) ) {
			continue;
		}
		if ( 0 === strpos( $key, 'deadline:' ) ) {
			// Termín po dátume ostáva otvorený (kým ho niekto nezavrie); termín, ktorý už neplatí (napr. rok bez účtovania), sa zruší.
			$due = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT due FROM ' . juc_t( 'issues' ) . ' WHERE id = %d', $h['id'] ) ); // phpcs:ignore
			if ( $due && $due >= current_time( 'Y-m-d' ) ) {
				$wpdb->update( juc_t( 'issues' ), array( 'status' => 'dismissed', 'closed_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $h['id'] ) );
				juc_issue_log( $h['id'], 'Zrušené – termín sa netýka žiadneho účtovaného obdobia.', 0 );
				++$closed;
			}
			continue;
		}
		$wpdb->update( juc_t( 'issues' ), array( 'status' => 'done', 'closed_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $h['id'] ) );
		juc_issue_log( $h['id'], 'Vyriešené automaticky – kontrola už problém nenachádza.', 0 );
		++$closed;
	}
	juc_unlock( 'juc_issues' );
	wp_cache_delete( 'juc_issues_n', 'juc' );
	update_option( 'juc_issues_last', current_time( 'mysql' ), false );
	return array( $new, $closed );
}

/** Počty otvorených položiek: [ open, overdue ]. */
function juc_issues_count() {
	global $wpdb;
	$n = wp_cache_get( 'juc_issues_n', 'juc' );
	if ( false === $n ) {
		$t = juc_t( 'issues' );
		$n = array(
			(int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status IN ('open','doing')" ), // phpcs:ignore
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status IN ('open','doing') AND due IS NOT NULL AND due < %s", current_time( 'Y-m-d' ) ) ), // phpcs:ignore
		);
		wp_cache_set( 'juc_issues_n', $n, 'juc', 120 );
	}
	return $n;
}

// Kontroly: po automatickom účtovaní, raz za hodinu a pri otvorení stránky (najviac raz za 5 minút).
add_action( 'juc_autopost', 'juc_issues_refresh', 20 );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'juc_issues_daily' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 06:10' ), 'daily', 'juc_issues_daily' );
	}
} );
add_action( 'juc_issues_daily', function () {
	juc_issues_refresh();
	juc_issues_summary_export();
} );

/* ---------------------------------------------------------------- Súhrn do poznámok (J-Notes) */

/** Súhrn otvorených položiek v Markdowne (úlohy - [ ] / - [~]). */
function juc_issues_markdown() {
	global $wpdb;
	$rows  = (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'issues' ) . " WHERE status IN ('open','doing') ORDER BY priority, (due IS NULL), due, FIELD(kind,'deadline','check','decision','question','task','missing','note'), id", ARRAY_A ); // phpcs:ignore
	$kinds = juc_issue_kinds();
	$md    = "# Na riešenie – účtovníctvo\n\nPrehľad z J-Účtovníctva → Na riešenie (aktualizované " . date_i18n( 'j. n. Y H:i' ) . "), zoradený podľa priority. Otvorených položiek: " . count( $rows ) . ".\n";
	$group = array();
	foreach ( $rows as $r ) {
		$group[ (int) $r['priority'] ][] = $r;
	}
	foreach ( juc_issue_priorities() as $p => $pl ) {
		if ( empty( $group[ $p ] ) ) {
			continue;
		}
		$md .= "\n## Priorita: " . $pl . ' (' . count( $group[ $p ] ) . ")\n\n";
		foreach ( $group[ $p ] as $r ) {
			$md .= '- [' . ( 'doing' === $r['status'] ? '~' : ' ' ) . '] ' . ( in_array( $r['kind'], array( 'missing', 'check', 'deadline' ), true ) ? '' : $kinds[ $r['kind'] ] . ': ' ) . $r['title'] . ( $r['due'] ? ' – termín ' . juc_date( $r['due'] ) : '' ) . ( 'accountant' === $r['owner'] ? ' (účtovník)' : '' ) . "\n";
		}
	}
	return $md;
}

/** Zapíše súhrn do J-Notes (poznámka „na-riesenie-uctovnictvo“) a pošle ho ďalej cez akciu. */
function juc_issues_summary_export() {
	$md = juc_issues_markdown();
	if ( function_exists( 'jno_save' ) ) {
		jno_save( array( 'slug' => 'na-riesenie-uctovnictvo', 'title' => 'Na riešenie – účtovníctvo', 'type' => 'plan', 'project' => 'Účtovníctvo', 'body' => $md, 'now' => 1 ) );
	}
	do_action( 'juc_issues_summary', $md );
	return $md;
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_juc_issue_save', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_issue_save' );
	$in  = wp_unslash( $_POST );
	$id  = (int) ( $in['id'] ?? 0 );
	$old = $id ? juc_issue( $id ) : null;
	if ( ! $old && '' === trim( (string) ( $in['title'] ?? '' ) ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-issues', array( 'new' => 1 ) ), 'Vyplňte názov.' );
	}
	$f = array( 'status' => $in['status'] ?? 'open', 'owner' => $in['owner'] ?? 'me', 'priority' => $in['priority'] ?? 2, 'due' => $in['due'] ?? '' );
	if ( ! $old || ! $old['auto_key'] ) {
		$f += array( 'title' => $in['title'] ?? '', 'body' => $in['body'] ?? '', 'kind' => $in['kind'] ?? 'task', 'link_type' => $in['link_type'] ?? '', 'link_id' => $in['link_id'] ?? '' );
	}
	$id = juc_issue_save( $f, $id );
	juc_issue_log( $id, (string) ( $in['log'] ?? '' ) );
	if ( ! $old ) {
		juc_issue_log( $id, 'Založené.' );
	}
	wp_cache_delete( 'juc_issues_n', 'juc' );
	$back = ! empty( $in['back'] ) ? esc_url_raw( $in['back'] ) : juc_url( 'j-uctovnictvo-issues', array( 'issue' => $id ) );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'issue', $back ) );
	exit;
} );

add_action( 'admin_post_juc_issue_status', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	check_admin_referer( 'juc_issue_status_' . $id );
	$st = sanitize_key( $_GET['st'] ?? '' ); // phpcs:ignore
	if ( juc_issue( $id ) && isset( juc_issue_statuses()[ $st ] ) ) {
		juc_issue_save( array( 'status' => $st ), $id );
	}
	wp_cache_delete( 'juc_issues_n', 'juc' );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : juc_url( 'j-uctovnictvo-issues' ) );
	exit;
} );

add_action( 'admin_post_juc_issue_prio', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	check_admin_referer( 'juc_issue_status_' . $id );
	$p   = max( 1, min( 3, (int) ( $_GET['p'] ?? 2 ) ) ); // phpcs:ignore
	$old = juc_issue( $id );
	if ( $old && (int) $old['priority'] !== $p ) {
		juc_issue_save( array( 'priority' => $p ), $id );
		juc_issue_log( $id, 'Priorita: ' . juc_issue_priorities()[ (int) $old['priority'] ] . ' → ' . juc_issue_priorities()[ $p ] . '.' );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : juc_url( 'j-uctovnictvo-issues' ) );
	exit;
} );

add_action( 'admin_post_juc_issues_refresh', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_issues_refresh' );
	list( $n, $c ) = juc_issues_refresh();
	juc_issues_summary_export();
	set_transient( 'juc_err_' . get_current_user_id(), 'Kontrola hotová: nových ' . $n . ', vyriešených ' . $c . '. Súhrn je zapísaný do poznámok.', 120 );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-issues', array( 'juc_msg' => 'auto' ) ) );
	exit;
} );

/* ---------------------------------------------------------------- Zobrazenie */

/** Odkaz na naviazaný objekt. */
function juc_issue_link( array $r ) {
	$id = (string) $r['link_id'];
	switch ( $r['link_type'] ) {
		case 'entry':
			$e = juc_entry( (int) $id );
			return $e ? '<a href="' . esc_url( juc_url( 'j-uctovnictvo-entries', array( 'entry' => $e['id'] ) ) ) . '">' . esc_html( $e['number'] ) . '</a>' : '';
		case 'banktx':
			$t = function_exists( 'jinv_bank_tx' ) ? jinv_bank_tx( (int) $id ) : null;
			return $t ? '<a href="' . esc_url( juc_url( 'j-uctovnictvo-queue' ) ) . '">pohyb ' . esc_html( juc_date( $t['tx_date'] ) . ' ' . juc_money( $t['amount'] ) . ' €' ) . '</a>' : 'pohyb #' . esc_html( $id );
		case 'doc':
			$d = function_exists( 'jinv_get' ) ? jinv_get( (int) $id ) : null;
			if ( ! $d ) {
				return '';
			}
			$url = 'received' === $d['kind'] ? jinv_admin_url( array( 'tab' => 'received', 'edit' => $d['id'] ) ) : jinv_admin_url( array( 'view' => $d['id'] ) );
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $d['number'] ) . '</a>';
		case 'account':
			return '<a href="' . esc_url( juc_url( 'j-uctovnictvo-ledger', array( 'acc' => $id ) ) ) . '">účet ' . esc_html( $id ) . '</a>';
		case 'partner':
			$p = function_exists( 'jinv_partner_get' ) ? jinv_partner_get( (int) $id ) : null;
			return $p ? '<a href="' . esc_url( jinv_admin_url( array( 'tab' => 'partners', 'partner' => $p['id'] ) ) ) . '">' . esc_html( $p['name'] ) . '</a>' : '';
		case 'year':
			return 'rok ' . esc_html( $id );
	}
	return '';
}

/** Otvorené položky naviazané na objekt (pre detail dokladu a pod.). */
function juc_issues_for( $type, $id ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'issues' ) . " WHERE link_type = %s AND link_id = %s AND status IN ('open','doing') ORDER BY id", $type, (string) $id ), ARRAY_A ); // phpcs:ignore
}

/** Box „Na riešenie“ pri objekte + odkaz na pridanie poznámky. */
function juc_render_issues_box( $type, $id, $back = '' ) {
	$rows = juc_issues_for( $type, $id );
	echo '<div class="juc-issues-box"><strong>Na riešenie:</strong> ';
	foreach ( $rows as $r ) {
		echo '<a class="juc-chip juc-k-' . esc_attr( $r['kind'] ) . '" href="' . esc_url( juc_url( 'j-uctovnictvo-issues', array( 'issue' => $r['id'] ) ) ) . '">' . esc_html( juc_issue_kinds()[ $r['kind'] ] . ': ' . $r['title'] ) . '</a> ';
	}
	echo $rows ? '' : '<span class="juc-muted">nič</span> ';
	echo '<a href="' . esc_url( juc_url( 'j-uctovnictvo-issues', array( 'new' => 1, 'link_type' => $type, 'link_id' => $id, 'back' => $back ) ) ) . '">+ Pridať poznámku / úlohu</a></div>';
}

function juc_issue_badge( array $r ) {
	$today = current_time( 'Y-m-d' );
	$late  = $r['due'] && $r['due'] < $today && in_array( $r['status'], array( 'open', 'doing' ), true );
	return '<span class="juc-chip juc-k-' . esc_attr( $r['kind'] ) . '">' . esc_html( juc_issue_kinds()[ $r['kind'] ] ?? $r['kind'] ) . '</span>' . ( $late ? ' <span class="juc-chip juc-late">po termíne</span>' : '' );
}

add_action( 'admin_menu', function () {
	$n      = juc_issues_count();
	$bubble = $n[0] ? ' <span class="awaiting-mod count-' . $n[0] . '"><span class="pending-count">' . $n[0] . '</span></span>' : '';
	add_submenu_page( 'j-uctovnictvo', 'Na riešenie – J-Účtovníctvo', 'Na riešenie' . $bubble, JUC_CAP, 'j-uctovnictvo-issues', 'juc_page_issues' );
}, 12 );

add_action( 'admin_post_juc_issues_print', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$owner = sanitize_key( $_GET['owner'] ?? '' ); // phpcs:ignore
	$kind  = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
	$w     = "status IN ('open','doing')" . ( $owner ? $wpdb->prepare( ' AND owner = %s', $owner ) : '' ) . ( $kind ? $wpdb->prepare( ' AND kind = %s', $kind ) : '' );
	$rows  = (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'issues' ) . " WHERE $w ORDER BY priority, (due IS NULL), due, kind, id", ARRAY_A ); // phpcs:ignore
	$t     = 'accountant' === $owner ? 'Otázky a úlohy pre účtovníka' : 'Na riešenie – účtovníctvo';
	if ( function_exists( 'juc_cash_print_head' ) ) {
		juc_cash_print_head( $t );
	}
	echo '<h1>' . esc_html( $t ) . '</h1><p>Stav k ' . esc_html( date_i18n( 'j. n. Y' ) ) . ', otvorených ' . count( $rows ) . '.</p><table><thead><tr><th>#</th><th>Priorita</th><th>Druh</th><th>Položka</th><th>Termín</th><th style="width:35%">Poznámka / odpoveď</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		echo '<tr><td>' . (int) ( $i + 1 ) . '</td><td>' . esc_html( juc_issue_priorities()[ (int) $r['priority'] ] ?? '' ) . '</td><td>' . esc_html( juc_issue_kinds()[ $r['kind'] ] ?? '' ) . '</td><td><strong>' . esc_html( $r['title'] ) . '</strong>' . ( $r['body'] ? '<br><small>' . nl2br( esc_html( $r['body'] ) ) . '</small>' : '' ) . '</td><td>' . esc_html( $r['due'] ? juc_date( $r['due'] ) : '' ) . '</td><td></td></tr>';
	}
	echo '</tbody></table></body></html>';
	exit;
} );

function juc_page_issues() {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$last = (string) get_option( 'juc_issues_last', '' );
	if ( ! $last || strtotime( $last ) < current_time( 'timestamp' ) - 300 ) {
		juc_issues_refresh();
	}
	$id = (int) ( $_GET['issue'] ?? 0 ); // phpcs:ignore
	if ( $id || isset( $_GET['new'] ) ) { // phpcs:ignore
		juc_render_issue( $id );
		return;
	}
	$f_status = sanitize_key( $_GET['status'] ?? 'active' ); // phpcs:ignore
	$f_kind   = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
	$f_owner  = sanitize_key( $_GET['owner'] ?? '' ); // phpcs:ignore
	$s        = trim( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore
	$w        = 'active' === $f_status ? "status IN ('open','doing')" : ( 'all' === $f_status ? '1=1' : $wpdb->prepare( 'status = %s', $f_status ) );
	$w       .= $f_kind ? $wpdb->prepare( ' AND kind = %s', $f_kind ) : '';
	$w       .= $f_owner ? $wpdb->prepare( ' AND owner = %s', $f_owner ) : '';
	$w       .= '' !== $s ? $wpdb->prepare( ' AND (title LIKE %s OR body LIKE %s)', '%' . $wpdb->esc_like( $s ) . '%', '%' . $wpdb->esc_like( $s ) . '%' ) : '';
	$rows     = (array) $wpdb->get_results( 'SELECT * FROM ' . juc_t( 'issues' ) . " WHERE $w ORDER BY " . ( in_array( $f_status, array( 'active', 'open', 'doing' ), true ) ? '' : "FIELD(status,'doing','open','done','dismissed'), " ) . "priority, (due IS NULL), due, FIELD(status,'doing','open'), FIELD(kind,'deadline','check','decision','question','task','missing','note'), id DESC LIMIT 500", ARRAY_A ); // phpcs:ignore
	$cnt      = array();
	foreach ( (array) $wpdb->get_results( 'SELECT kind, COUNT(*) n FROM ' . juc_t( 'issues' ) . " WHERE status IN ('open','doing') GROUP BY kind", ARRAY_A ) as $c ) { // phpcs:ignore
		$cnt[ $c['kind'] ] = (int) $c['n'];
	}
	list( $open, $late ) = juc_issues_count();
	juc_head( 'Na riešenie', ' <a class="page-title-action" href="' . esc_url( juc_url( 'j-uctovnictvo-issues', array( 'new' => 1 ) ) ) . '">Pridať úlohu / poznámku</a>' );
	?>
	<div class="juc-cards">
		<div class="<?php echo $open ? 'is-warn' : ''; ?>"><span>Otvorené</span><strong><?php echo (int) $open; ?></strong></div>
		<div class="<?php echo $late ? 'is-neg' : ''; ?>"><span>Po termíne</span><strong><?php echo (int) $late; ?></strong></div>
		<?php foreach ( juc_issue_kinds() as $k => $l ) : if ( empty( $cnt[ $k ] ) ) { continue; } ?>
			<div><span><?php echo esc_html( $l ); ?></span><strong><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-issues', array( 'kind' => $k ) ) ); ?>"><?php echo (int) $cnt[ $k ]; ?></a></strong></div>
		<?php endforeach; ?>
	</div>
	<form method="get" class="juc-filter">
		<input type="hidden" name="page" value="j-uctovnictvo-issues">
		<select name="status"><option value="active" <?php selected( $f_status, 'active' ); ?>>Otvorené a rozpracované</option><?php foreach ( juc_issue_statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?><option value="all" <?php selected( $f_status, 'all' ); ?>>Všetky</option></select>
		<select name="kind"><option value="">Všetky druhy</option><?php foreach ( juc_issue_kinds() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_kind, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<select name="owner"><option value="">Ja aj účtovník</option><?php foreach ( juc_issue_owners() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_owner, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Hľadať">
		<button class="button">Filtrovať</button>
		<a class="button" target="_blank" href="<?php echo esc_url( admin_url( 'admin-post.php?action=juc_issues_print&owner=accountant' ) ); ?>">Tlačiť pre účtovníka</a>
		<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( array( 'action' => 'juc_issues_print', 'kind' => $f_kind, 'owner' => $f_owner ), admin_url( 'admin-post.php' ) ) ); ?>">Tlačiť zoznam</a>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="float:right;margin-top:-44px">
		<?php wp_nonce_field( 'juc_issues_refresh' ); ?><input type="hidden" name="action" value="juc_issues_refresh">
		<button class="button-link">Skontrolovať teraz</button> <span class="juc-muted"><?php echo $last ? 'naposledy ' . esc_html( date_i18n( 'j. n. H:i', strtotime( $last ) ) ) : ''; ?></span>
	</form>
	<table class="widefat striped juc-table juc-issues">
		<thead><tr><th>Priorita</th><th>Druh</th><th>Položka</th><th>Väzba</th><th>Kto</th><th>Termín</th><th>Stav</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="8" class="juc-ok">✓ Nič na riešenie.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $r ) :
			$nonce = wp_create_nonce( 'juc_issue_status_' . $r['id'] );
			$su    = function ( $st ) use ( $r, $nonce ) {
				return esc_url( admin_url( 'admin-post.php?action=juc_issue_status&id=' . (int) $r['id'] . '&st=' . $st . '&_wpnonce=' . $nonce ) );
			};
			?>
			<tr class="juc-p<?php echo (int) $r['priority']; ?> juc-st-<?php echo esc_attr( $r['status'] ); ?>">
				<td><select class="juc-prio juc-prio-<?php echo (int) $r['priority']; ?>" data-url="<?php echo esc_url( admin_url( 'admin-post.php?action=juc_issue_prio&id=' . (int) $r['id'] . '&_wpnonce=' . $nonce ) ); ?>" aria-label="Priorita"><?php foreach ( juc_issue_priorities() as $pk => $pl ) : ?><option value="<?php echo (int) $pk; ?>" <?php selected( (int) $r['priority'], $pk ); ?>><?php echo esc_html( $pl ); ?></option><?php endforeach; ?></select></td>
				<td><?php echo juc_issue_badge( $r ); // phpcs:ignore ?></td>
				<td><a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-issues', array( 'issue' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['title'] ); ?></strong></a><?php echo $r['auto_key'] ? ' <small class="juc-muted">(automaticky)</small>' : ''; ?></td>
				<td><?php echo juc_issue_link( $r ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( juc_issue_owners()[ $r['owner'] ] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['due'] ? juc_date( $r['due'] ) : '' ); ?></td>
				<td><?php echo esc_html( juc_issue_statuses()[ $r['status'] ] ?? '' ); ?></td>
				<td class="juc-actions">
					<?php if ( in_array( $r['status'], array( 'open', 'doing' ), true ) ) : ?>
						<?php if ( 'open' === $r['status'] ) : ?><a href="<?php echo $su( 'doing' ); // phpcs:ignore ?>">Riešim</a> · <?php endif; ?>
						<a href="<?php echo $su( 'done' ); // phpcs:ignore ?>">Hotové</a> · <a href="<?php echo $su( 'dismissed' ); // phpcs:ignore ?>" class="juc-muted">Netreba</a>
					<?php else : ?>
						<a href="<?php echo $su( 'open' ); // phpcs:ignore ?>">Otvoriť znova</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<script>document.querySelectorAll('.juc-prio').forEach(function(s){s.addEventListener('change',function(){location.href=s.dataset.url+'&p='+s.value;});});</script>
	<p class="description">Zoradené podľa priority (vysoká hore), potom podľa termínu. Prioritu zmeníte priamo v zozname. Chýbajúci doklad dostane prioritu podľa sumy: od 100 € vysoká, od 10 € stredná, inak nízka. Automatické položky (chýbajúci doklad, nesúlad banky, faktúry po splatnosti, termíny) vytvára a zatvára kontrola sama – po hodinovom automatickom účtovaní, každé ráno a pri otvorení tejto stránky. Súhrn otvorených položiek sa každé ráno zapíše do poznámky „Na riešenie – účtovníctvo“ v J-Notes.</p>
	</div>
	<?php
}

/** Detail / formulár položky. */
function juc_render_issue( $id ) {
	global $wpdb;
	$r    = $id ? juc_issue( $id ) : null;
	$auto = $r && $r['auto_key'];
	$v    = function ( $k, $def = '' ) use ( $r ) {
		if ( $r ) {
			return (string) $r[ $k ];
		}
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $def; // phpcs:ignore
	};
	juc_head( $r ? 'Na riešenie: ' . $r['title'] : 'Nová úloha / poznámka' );
	$back = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : ''; // phpcs:ignore
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel juc-issue-form">
		<?php wp_nonce_field( 'juc_issue_save' ); ?>
		<input type="hidden" name="action" value="juc_issue_save"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
		<div class="juc-head-fields">
			<label class="juc-wide">Názov<br><input name="title" class="large-text" required value="<?php echo esc_attr( $v( 'title' ) ); ?>" <?php disabled( $auto ); ?> placeholder="napr. Splátky PC – je počítač majetok firmy?"></label>
			<label>Druh<br><select name="kind" <?php disabled( $auto ); ?>><?php foreach ( juc_issue_kinds() as $k => $l ) : if ( ! $auto && in_array( $k, array( 'missing', 'check' ), true ) && $v( 'kind' ) !== $k ) { continue; } ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'kind', 'task' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label>Kto rieši<br><select name="owner"><?php foreach ( juc_issue_owners() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'owner', 'me' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label>Stav<br><select name="status"><?php foreach ( juc_issue_statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'status', 'open' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label>Priorita<br><select name="priority"><?php foreach ( juc_issue_priorities() as $pk => $pl ) : ?><option value="<?php echo (int) $pk; ?>" <?php selected( (int) $v( 'priority', '2' ), $pk ); ?>><?php echo esc_html( $pl ); ?></option><?php endforeach; ?></select></label>
			<label>Termín<br><input type="date" name="due" value="<?php echo esc_attr( $v( 'due' ) ); ?>"></label>
			<label>Väzba<br><select name="link_type" <?php disabled( $auto ); ?>><?php foreach ( juc_issue_link_types() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'link_type' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label>ID / číslo (doklad, účet, rok…)<br><input name="link_id" value="<?php echo esc_attr( $v( 'link_id' ) ); ?>" <?php disabled( $auto ); ?> style="width:140px"></label>
			<?php if ( $r && $r['link_type'] ) : ?><span>Väzba: <?php echo juc_issue_link( $r ); // phpcs:ignore ?></span><?php endif; ?>
			<label class="juc-wide">Popis<br><textarea name="body" rows="5" class="large-text" <?php disabled( $auto ); ?>><?php echo esc_textarea( $v( 'body' ) ); ?></textarea></label>
			<label class="juc-wide">Nový záznam (čo sa zistilo, ako sa to vyriešilo)<br><textarea name="log" rows="3" class="large-text" placeholder="napr. Účtovník potvrdil: počítač 1 250 € je drobný majetok, ide do nákladov 501."></textarea></label>
		</div>
		<p><button class="button button-primary"><?php echo $r ? 'Uložiť' : 'Založiť'; ?></button> <a class="button" href="<?php echo esc_url( $back ? $back : juc_url( 'j-uctovnictvo-issues' ) ); ?>">Späť</a>
		<?php if ( $auto ) : ?><span class="description">Automatickú položku vytvorila kontrola – meniť sa dá stav, termín, kto rieši a záznamy.</span><?php endif; ?></p>
	</form>
	<?php
	if ( $r ) {
		$log = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . juc_t( 'issue_log' ) . ' WHERE issue_id = %d ORDER BY id DESC', $r['id'] ), ARRAY_A ); // phpcs:ignore
		echo '<h2>História</h2><ul class="juc-log">';
		foreach ( $log as $l ) {
			$u = (int) $l['user_id'] ? get_userdata( (int) $l['user_id'] ) : null;
			echo '<li><span class="juc-muted">' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $l['created_at'] ) ) . ' · ' . ( $u ? $u->display_name : 'program' ) ) . '</span><br>' . nl2br( esc_html( $l['text'] ) ) . '</li>';
		}
		echo $log ? '' : '<li class="juc-muted">Zatiaľ bez záznamov.</li>';
		echo '</ul><p class="juc-muted">Založené ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $r['created_at'] ) ) ) . ( $r['closed_at'] ? ', uzavreté ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $r['closed_at'] ) ) ) : '' ) . '.</p>';
	}
	echo '</div>';
}
