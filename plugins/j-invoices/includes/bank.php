<?php
/**
 * Banka: bankové účty, pohyby a automatické párovanie úhrad.
 *
 * Zdroje pohybov: Fio API (token „Sledovanie účtu“, len čítanie), neskôr import výpisu (camt.053 / CSV).
 * Každý pohyb sa uloží raz (účet + ID pohybu v banke). Párovanie:
 *  - príjem: vydaná faktúra / zálohová faktúra s rovnakým VS → úhrada; inak objednávka s číslom = VS → poznámka
 *    (voliteľne zmena stavu objednávky na „Spracováva sa“),
 *  - výdaj: prijatá faktúra s rovnakým VS, inak podľa IBAN dodávateľa a sumy,
 *  - nespárované pohyby čakajú v zozname „Na spárovanie“ (ručné priradenie alebo Ignorovať).
 * Token sa ukladá v databáze (nenačítava sa automaticky), v administrácii sa nezobrazuje.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JINV_FIO_API = 'https://fioapi.fio.cz/v1/rest/';

add_action( 'admin_menu', function () {
	$n      = jinv_bank_unmatched_count();
	$bubble = $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>' : '';
	add_submenu_page( 'j-invoices', 'Banka', 'Banka' . $bubble, 'manage_woocommerce', 'j-invoices-bank', function () {
		jinv_render_admin( 'bank' );
	}, 3 );
}, 12 );

/* ---------------------------------------------------------------- Účty */

/** Bankové účty: [ id => [name, iban, currency, type (fio|manual), token, since, token_until, last_sync, last_call, last_error, balance, balance_at] ]. */
function jinv_bank_accounts() {
	$a = get_option( 'jinv_bank_accounts', array() );
	return is_array( $a ) ? $a : array();
}

function jinv_bank_save_accounts( array $a ) {
	update_option( 'jinv_bank_accounts', $a, false );
}

function jinv_bank_account( $id ) {
	$a = jinv_bank_accounts();
	return isset( $a[ $id ] ) ? $a[ $id ] : null;
}

function jinv_bank_update_account( $id, array $fields ) {
	$a = jinv_bank_accounts();
	if ( isset( $a[ $id ] ) ) {
		$a[ $id ] = array_merge( $a[ $id ], $fields );
		jinv_bank_save_accounts( $a );
	}
}

/* ---------------------------------------------------------------- Pohyby */

function jinv_bank_tx( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jinv_table( 'banktx' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

function jinv_bank_unmatched_count() {
	global $wpdb;
	$n = wp_cache_get( 'jinv_bank_unmatched', 'jinv' );
	if ( false === $n ) {
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jinv_table( 'banktx' ) . " WHERE status = 'new'" ); // phpcs:ignore
		wp_cache_set( 'jinv_bank_unmatched', $n, 'jinv', 60 );
	}
	return $n;
}

/** Uloženie pohybu (raz). $t: ext_id, date, amount, currency, vs, ks, ss, counter_account, counter_name, message, type. Vráti ID nového pohybu alebo 0, ak už existuje. */
function jinv_bank_store( $account_id, array $t ) {
	global $wpdb;
	$tbl = jinv_table( 'banktx' );
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tbl WHERE account_id = %s AND ext_id = %s", $account_id, $t['ext_id'] ) ) ) { // phpcs:ignore
		return 0;
	}
	$ok = $wpdb->insert( $tbl, array(
		'account_id'      => $account_id,
		'ext_id'          => mb_substr( (string) $t['ext_id'], 0, 64 ),
		'tx_date'         => $t['date'],
		'amount'          => round( (float) $t['amount'], 2 ),
		'currency'        => $t['currency'] ? $t['currency'] : 'EUR',
		'vs'              => mb_substr( ltrim( preg_replace( '/\D/', '', (string) $t['vs'] ), '0' ), 0, 20 ),
		'ks'              => mb_substr( (string) $t['ks'], 0, 10 ),
		'ss'              => mb_substr( (string) $t['ss'], 0, 20 ),
		'counter_account' => mb_substr( (string) $t['counter_account'], 0, 50 ),
		'counter_name'    => mb_substr( (string) $t['counter_name'], 0, 200 ),
		'message'         => mb_substr( (string) $t['message'], 0, 255 ),
		'tx_type'         => mb_substr( (string) $t['type'], 0, 60 ),
		'status'          => ( ( $mf = (string) ( jinv_bank_account( $account_id )['match_from'] ?? '' ) ) && $t['date'] < $mf ) ? 'old' : 'new', // pred začiatkom evidencie – len archív
		'created_at'      => current_time( 'mysql' ),
	) );
	return $ok ? (int) $wpdb->insert_id : 0;
}

/**
 * Zostatok bankového účtu na začiatku dňa $date (pred pohybmi toho dňa): z najbližšieho známeho počiatočného
 * zostatku (Fio „openingBalance“) a uložených pohybov. Vráti číslo alebo null, ak sa nedá zistiť.
 */
function jinv_bank_balance_at( $account_id, $date ) {
	global $wpdb;
	$acc = jinv_bank_account( $account_id );
	$ops = (array) ( $acc['opening'] ?? array() );
	ksort( $ops );
	$base = null;
	foreach ( $ops as $d => $v ) {
		if ( $d <= $date ) {
			$base = array( $d, (float) $v );
		}
	}
	if ( ! $base ) {
		return null;
	}
	$sum = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . jinv_table( 'banktx' ) . ' WHERE account_id = %s AND tx_date >= %s AND tx_date < %s', $account_id, $base[0], $date ) ); // phpcs:ignore
	return round( $base[1] + $sum, 2 );
}

/* ---------------------------------------------------------------- Fio API */

/** Volanie Fio API (bez tokenu v chybových hláškach). Vráti dekódované JSON alebo WP_Error. */
function jinv_fio_get( $account_id, $path ) {
	$acc = jinv_bank_account( $account_id );
	if ( ! $acc || empty( $acc['token'] ) ) {
		return new WP_Error( 'jinv_fio', 'Účet nemá zadaný token Fio API.' );
	}
	// Fio dovoľuje 1 dopyt za 30 sekúnd na token.
	$wait = 31 - ( time() - (int) ( $acc['last_call'] ?? 0 ) );
	if ( $wait > 0 ) {
		return new WP_Error( 'jinv_fio_wait', 'Fio dovoľuje načítanie raz za 30 sekúnd – skúste to o ' . $wait . ' s.' );
	}
	jinv_bank_update_account( $account_id, array( 'last_call' => time() ) );
	$res = wp_remote_get( JINV_FIO_API . str_replace( '{token}', rawurlencode( $acc['token'] ), $path ), array( 'timeout' => 120 ) );
	if ( is_wp_error( $res ) ) {
		// Fio pri neaktívnom alebo neplatnom tokene neodpovedá ~30 s.
		$msg = $res->get_error_message();
		return new WP_Error( 'jinv_fio_token', false !== stripos( $msg, 'timed out' ) || false !== stripos( $msg, 'cURL error 28' )
			? 'Fio neodpovedá – token ešte nie je aktívny (Fio ho zapne asi 5 minút po autorizácii) alebo je neplatný. Skúste Načítať pohyby teraz o pár minút.'
			: 'Fio API je nedostupné: ' . str_replace( $acc['token'], '…', $msg ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 500 === $code && '' === trim( wp_remote_retrieve_body( $res ) ) ) {
		return new WP_Error( 'jinv_fio_token', 'Fio token nie je aktívny alebo je neplatný – nový token začne fungovať asi 5 minút po autorizácii. Skúste Načítať pohyby teraz o pár minút.' );
	}
	if ( 409 === $code ) {
		return new WP_Error( 'jinv_fio_wait', 'Fio dovoľuje načítanie raz za 30 sekúnd – skúste to o chvíľu.' );
	}
	if ( 401 === $code || 404 === $code || 500 === $code && false !== stripos( wp_remote_retrieve_body( $res ), 'token' ) ) {
		return new WP_Error( 'jinv_fio_token', 'Fio token je neplatný alebo vypršal – vytvorte nový v internetbankingu (Nastavenia → API).' );
	}
	if ( 422 === $code ) {
		return new WP_Error( 'jinv_fio_old', 'Fio cez API vydá len pohyby za posledných 90 dní. Staršie pohyby: v internetbankingu Fio → Nastavenia → API odblokujte históriu (platí 10 minút) a hneď kliknite Načítať pohyby teraz.' );
	}
	if ( 200 !== $code ) {
		return new WP_Error( 'jinv_fio', 'Fio API vrátilo chybu ' . $code . ': ' . sanitize_text_field( mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $res ) ), 0, 160 ) ) );
	}
	$j = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $j ) || ! isset( $j['accountStatement'] ) ) {
		return new WP_Error( 'jinv_fio', 'Fio API vrátilo neočakávanú odpoveď.' );
	}
	return $j['accountStatement'];
}

/** Prevod pohybu z formátu Fio JSON (column0 … column26). */
function jinv_fio_tx( array $c ) {
	$v = function ( $n ) use ( $c ) {
		return isset( $c[ 'column' . $n ]['value'] ) ? $c[ 'column' . $n ]['value'] : '';
	};
	$acc  = (string) $v( 2 );
	$bank = (string) $v( 3 );
	return array(
		'ext_id'          => (string) $v( 22 ),
		'date'            => substr( (string) $v( 0 ), 0, 10 ),
		'amount'          => (float) $v( 1 ),
		'currency'        => (string) $v( 14 ),
		'vs'              => (string) $v( 5 ),
		'ks'              => (string) $v( 4 ),
		'ss'              => (string) $v( 6 ),
		'counter_account' => $acc . ( $bank && false === strpos( $acc, $bank ) && ! preg_match( '/^[A-Z]{2}\d{2}/', $acc ) ? '/' . $bank : '' ),
		'counter_name'    => (string) ( $v( 10 ) ? $v( 10 ) : $v( 7 ) ),
		'message'         => trim( (string) ( $v( 16 ) ? $v( 16 ) : $v( 25 ) ) ),
		'type'            => (string) $v( 8 ),
	);
}

/**
 * Načítanie pohybov z Fio: obdobie od (posledné načítanie − 3 dni) do dnes; prvé načítanie od dátumu „since“.
 * Vráti [nové, spárované] alebo WP_Error.
 */
function jinv_fio_sync( $account_id ) {
	$acc = jinv_bank_account( $account_id );
	if ( ! $acc ) {
		return new WP_Error( 'jinv_fio', 'Účet neexistuje.' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 ); // phpcs:ignore -- prvé načítanie dlhšieho obdobia
	}
	$today = current_time( 'Y-m-d' );
	$from  = ! empty( $acc['last_sync'] ) ? gmdate( 'Y-m-d', strtotime( substr( $acc['last_sync'], 0, 10 ) . ' -3 days' ) ) : ( ! empty( $acc['since'] ) ? $acc['since'] : gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) ) );
	// Bez odblokovania histórie Fio vydá najviac 90 dní dozadu; pri chybe 422 sa skúsi znova len posledných 89 dní.
	$st = jinv_fio_get( $account_id, 'periods/{token}/' . $from . '/' . $today . '/transactions.json' );
	$min = gmdate( 'Y-m-d', strtotime( $today . ' -89 days' ) );
	if ( is_wp_error( $st ) && 'jinv_fio_old' === $st->get_error_code() && $from < $min ) {
		$old_err = $st;
		jinv_bank_update_account( $account_id, array( 'last_call' => 0 ) );
		sleep( 31 ); // Fio: jeden dopyt za 30 s
		$st = jinv_fio_get( $account_id, 'periods/{token}/' . $min . '/' . $today . '/transactions.json' );
		if ( ! is_wp_error( $st ) ) {
			set_transient( 'jinv_bank_note_' . $account_id, 'Načítané pohyby od ' . jinv_date( $min ) . ' (Fio vydá cez API len 90 dní). ' . $old_err->get_error_message(), HOUR_IN_SECONDS );
		}
	}
	if ( is_wp_error( $st ) ) {
		if ( 'jinv_fio_wait' !== $st->get_error_code() ) {
			jinv_bank_update_account( $account_id, array( 'last_error' => $st->get_error_message(), 'last_error_at' => current_time( 'mysql' ) ) );
		}
		return $st;
	}
	$info = (array) ( $st['info'] ?? array() );
	$new  = 0;
	$ids  = array();
	foreach ( (array) ( $st['transactionList']['transaction'] ?? array() ) as $c ) {
		$t = jinv_fio_tx( (array) $c );
		if ( '' === $t['ext_id'] || '' === $t['date'] ) {
			continue;
		}
		$id = jinv_bank_store( $account_id, $t );
		if ( $id ) {
			$new++;
			$ids[] = $id;
		}
	}
	$matched = 0;
	foreach ( $ids as $id ) {
		$matched += jinv_bank_auto_match( $id ) ? 1 : 0;
	}
	$upd = array(
		'last_sync'  => current_time( 'mysql' ),
		'last_error' => '',
	);
	// Zostatok na začiatku načítaného obdobia (napr. k 1. 1. – pre počiatočné stavy v účtovníctve).
	if ( isset( $info['openingBalance'] ) && ! empty( $info['dateStart'] ) ) {
		$opening                                = (array) ( $acc['opening'] ?? array() );
		$opening[ substr( $info['dateStart'], 0, 10 ) ] = (float) $info['openingBalance'];
		ksort( $opening );
		$upd['opening'] = $opening;
	}
	if ( isset( $info['closingBalance'] ) ) {
		$upd['balance']    = (float) $info['closingBalance'];
		$upd['balance_at'] = current_time( 'mysql' );
	}
	if ( ! empty( $info['iban'] ) && empty( $acc['iban'] ) ) {
		$upd['iban'] = (string) $info['iban'];
	}
	if ( ! empty( $info['currency'] ) ) {
		$upd['currency'] = (string) $info['currency'];
	}
	jinv_bank_update_account( $account_id, $upd );
	wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	return array( $new, $matched );
}

/* ---------------------------------------------------------------- Párovanie */

/** Kandidáti na spárovanie pohybu: doklady (a pri príjme objednávka) zoradené podľa zhody. */
function jinv_bank_candidates( array $tx, $limit = 5 ) {
	global $wpdb;
	$t     = jinv_table();
	$p     = jinv_table( 'payments' );
	$amt   = abs( (float) $tx['amount'] );
	$kinds = (float) $tx['amount'] > 0 ? "('issued','proforma')" : "('received')";
	$rest  = "(i.total - COALESCE((SELECT SUM(amount) FROM $p WHERE doc_id = i.id), 0))";
	$where = array( "i.kind IN $kinds", "i.status = 'issued'" );
	$or    = array();
	if ( '' !== $tx['vs'] ) {
		$or[] = $wpdb->prepare( "TRIM(LEADING '0' FROM i.vs) = %s", $tx['vs'] );
		$or[] = $wpdb->prepare( 'i.number = %s', $tx['vs'] );
	}
	$or[] = $wpdb->prepare( "ABS($rest - %f) < 0.005", $amt );
	$sql  = "SELECT i.*, $rest AS rest FROM $t i WHERE " . implode( ' AND ', $where ) . ' AND (' . implode( ' OR ', $or ) . ') ORDER BY i.issue_date DESC LIMIT 50';
	$rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	$iban = strtoupper( preg_replace( '/\s+/', '', (string) $tx['counter_account'] ) );
	foreach ( $rows as &$r ) {
		$score = 0;
		$vsok  = '' !== $tx['vs'] && ( ltrim( (string) $r['vs'], '0' ) === $tx['vs'] || (string) $r['number'] === $tx['vs'] );
		$score += $vsok ? 60 : 0;
		$score += abs( (float) $r['rest'] - $amt ) < 0.005 ? 30 : 0;
		if ( 'received' === $r['kind'] && $iban && (int) $r['partner_id'] ) {
			$pp     = jinv_partner_get( (int) $r['partner_id'] );
			$score += $pp && $pp['iban'] && $pp['iban'] === $iban ? 20 : 0;
		}
		$r['score'] = $score;
		$r['vsok']  = $vsok;
	}
	unset( $r );
	$rows = array_filter( $rows, fn( $r ) => $r['score'] >= 30 );
	usort( $rows, fn( $a, $b ) => $b['score'] <=> $a['score'] );
	return array_slice( $rows, 0, $limit );
}

/** Objednávka podľa čísla (VS) – vlastné čísla J-Objednávok / My Order Numbers, inak ID. */
function jinv_bank_order_by_number( $number ) {
	$number = (string) $number;
	if ( '' === $number || ! function_exists( 'wc_get_orders' ) ) {
		return null;
	}
	$found = wc_get_orders( array( 'limit' => 1, 'meta_query' => array( array( 'key' => '_wcson_order_number', 'value' => $number ) ) ) ); // phpcs:ignore
	if ( $found ) {
		return $found[0];
	}
	$o = ctype_digit( $number ) ? wc_get_order( (int) $number ) : null;
	return $o && ! $o instanceof WC_Order_Refund && (string) $o->get_order_number() === $number ? $o : apply_filters( 'jinv_bank_order_by_number', null, $number );
}

/**
 * Automatické spárovanie nového pohybu. Spáruje len istú zhodu: VS sedí a suma nepresahuje zvyšok
 * (alebo bez VS: IBAN dodávateľa + presná suma pri výdaji). Vráti true pri spárovaní.
 */
function jinv_bank_auto_match( $tx_id ) {
	$tx = jinv_bank_tx( $tx_id );
	if ( ! $tx || 'new' !== $tx['status'] ) {
		return false;
	}
	// Bankové poplatky, transakčná daň a úroky sa nepárujú – rovno „ignorované“ (dajú sa vrátiť).
	if ( preg_match( '/poplat|transak\S* da|úrok|urok/iu', $tx['tx_type'] ) ) {
		global $wpdb;
		$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'ignored', 'note' => 'banka: ' . $tx['tx_type'], 'matched_by' => 'auto' ), array( 'id' => $tx_id ) );
		return false;
	}
	$amt  = abs( (float) $tx['amount'] );
	$cand = jinv_bank_candidates( $tx, 5 );
	$best = $cand ? $cand[0] : null;
	$sure = $best && ( ( $best['vsok'] && $amt <= (float) $best['rest'] + 0.005 ) || ( ! $best['vsok'] && $best['score'] >= 50 ) );
	if ( $sure && ( count( $cand ) < 2 || $cand[1]['score'] < $best['score'] ) ) {
		return ! is_wp_error( jinv_bank_pair( $tx_id, (int) $best['id'], 'auto' ) );
	}
	// Príjem bez dokladu: objednávka s číslom = VS.
	if ( (float) $tx['amount'] > 0 && '' !== $tx['vs'] ) {
		$o = jinv_bank_order_by_number( $tx['vs'] );
		if ( $o && abs( (float) $o->get_total() - $amt ) < 0.005 ) {
			return ! is_wp_error( jinv_bank_pair_order( $tx_id, $o->get_id(), 'auto' ) );
		}
	}
	return false;
}

/** Spárovanie pohybu s dokladom → úhrada dokladu. */
function jinv_bank_pair( $tx_id, $doc_id, $how = 'manual' ) {
	global $wpdb;
	$tx  = jinv_bank_tx( $tx_id );
	$doc = jinv_get( $doc_id );
	if ( ! $tx || ! $doc ) {
		return new WP_Error( 'jinv_bank', 'Pohyb alebo doklad neexistuje.' );
	}
	if ( ( (float) $tx['amount'] > 0 ) !== in_array( $doc['kind'], array( 'issued', 'proforma' ), true ) ) {
		return new WP_Error( 'jinv_bank', (float) $tx['amount'] > 0 ? 'Príjem sa páruje s vydanou alebo zálohovou faktúrou.' : 'Výdaj sa páruje s prijatou faktúrou.' );
	}
	$pay = jinv_add_payment( $doc_id, abs( (float) $tx['amount'] ), array(
		'date'   => $tx['tx_date'],
		'method' => 'bank',
		'source' => 'bank',
		'ref'    => $tx['account_id'] . ':' . $tx['ext_id'],
		'note'   => trim( $tx['counter_name'] . ( $tx['message'] ? ' – ' . $tx['message'] : '' ) ),
	) );
	if ( is_wp_error( $pay ) ) {
		return $pay;
	}
	$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'matched', 'doc_id' => $doc_id, 'payment_id' => (int) $pay, 'matched_by' => $how ), array( 'id' => $tx_id ) );
	wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	// Úhrada zálohovej faktúry / faktúry k objednávke: poznámka a voliteľne stav objednávky.
	if ( (int) $doc['order_id'] && (float) $tx['amount'] > 0 ) {
		jinv_bank_order_paid( (int) $doc['order_id'], $tx );
	}
	do_action( 'jinv_bank_paired', $tx_id, $doc_id, $how );
	return (int) $pay;
}

/** Spárovanie príjmu s objednávkou bez dokladu. */
function jinv_bank_pair_order( $tx_id, $order_id, $how = 'manual' ) {
	global $wpdb;
	$tx = jinv_bank_tx( $tx_id );
	$o  = wc_get_order( $order_id );
	if ( ! $tx || ! $o ) {
		return new WP_Error( 'jinv_bank', 'Pohyb alebo objednávka neexistuje.' );
	}
	$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'matched', 'order_id' => (int) $order_id, 'matched_by' => $how ), array( 'id' => $tx_id ) );
	wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	jinv_bank_order_paid( (int) $order_id, $tx );
	do_action( 'jinv_bank_paired_order', $tx_id, $order_id, $how );
	return true;
}

/** Platba k objednávke prišla: poznámka do objednávky; pri zapnutej voľbe „zaplatená“ (spustí aj automatickú faktúru). */
function jinv_bank_order_paid( $order_id, array $tx ) {
	$o = wc_get_order( $order_id );
	if ( ! $o ) {
		return;
	}
	$o->add_order_note( sprintf( 'Platba prijatá na účet: %s, %s (VS %s, %s).', jinv_money( $tx['amount'], $tx['currency'] ), jinv_date( $tx['tx_date'] ), $tx['vs'] ? $tx['vs'] : '—', $tx['counter_name'] ? $tx['counter_name'] : 'J-Fakturácia banka' ) );
	if ( jinv_opt( 'bank_order_paid' ) && ! $o->is_paid() && $o->has_status( array( 'on-hold', 'pending' ) ) && abs( (float) $o->get_total() - (float) $tx['amount'] ) < 0.005 ) {
		$o->payment_complete( 'bank:' . $tx['account_id'] . ':' . $tx['ext_id'] );
	}
	$o->save();
}

/** Zrušenie spárovania (úhrada z banky sa zmaže, pohyb ide späť na spárovanie). */
function jinv_bank_unpair( $tx_id ) {
	global $wpdb;
	$tx = jinv_bank_tx( $tx_id );
	if ( ! $tx ) {
		return;
	}
	if ( (int) $tx['payment_id'] ) {
		jinv_delete_payment( (int) $tx['payment_id'] );
	}
	$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'new', 'doc_id' => 0, 'order_id' => 0, 'payment_id' => 0, 'matched_by' => '' ), array( 'id' => $tx_id ) );
	wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
}

/* ---------------------------------------------------------------- Cron */

add_filter( 'cron_schedules', function ( $s ) {
	$s['jinv_30min'] = array( 'interval' => 1800, 'display' => 'Každých 30 minút (J-Fakturácia banka)' );
	return $s;
} );
add_action( 'init', function () {
	$has = (bool) array_filter( jinv_bank_accounts(), fn( $a ) => 'fio' === ( $a['type'] ?? '' ) && ! empty( $a['token'] ) && ! empty( $a['auto'] ) );
	$next = wp_next_scheduled( 'jinv_bank_cron' );
	if ( $has && ! $next ) {
		wp_schedule_event( time() + 120, 'jinv_30min', 'jinv_bank_cron' );
	} elseif ( ! $has && $next ) {
		wp_clear_scheduled_hook( 'jinv_bank_cron' );
	}
} );
add_action( 'jinv_bank_cron', function () {
	foreach ( jinv_bank_accounts() as $id => $a ) {
		if ( 'fio' === ( $a['type'] ?? '' ) && ! empty( $a['token'] ) && ! empty( $a['auto'] ) ) {
			jinv_fio_sync( $id );
		}
	}
} );

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_jinv_bank_account', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_bank_account' );
	$in  = wp_unslash( $_POST );
	$id  = sanitize_key( $in['id'] ?? '' );
	$all = jinv_bank_accounts();
	if ( ! $id || ! isset( $all[ $id ] ) ) {
		$id         = 'a' . substr( md5( uniqid( '', true ) ), 0, 6 );
		$all[ $id ] = array( 'type' => 'fio', 'created' => current_time( 'mysql' ) );
	}
	if ( ! empty( $in['remove'] ) ) {
		unset( $all[ $id ] );
		jinv_bank_save_accounts( $all );
		wp_safe_redirect( jinv_admin_url( array( 'tab' => 'bank', 'view' => 'accounts', 'jinv_msg' => 'bremoved' ) ) );
		exit;
	}
	$all[ $id ]['name']  = sanitize_text_field( $in['name'] ?? '' ) ?: 'Fio účet';
	$since = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['since'] ?? '' ) ) ? $in['since'] : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	if ( ! empty( $all[ $id ]['since'] ) && $since < $all[ $id ]['since'] ) {
		$all[ $id ]['last_sync'] = ''; // dátum posunutý dozadu – načítajú sa aj staršie pohyby (už uložené sa nezdvoja)
	}
	$all[ $id ]['since'] = $since;
	$all[ $id ]['auto']  = ! empty( $in['auto'] ) ? 1 : 0;
	$all[ $id ]['match_from'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['match_from'] ?? '' ) ) ? $in['match_from'] : '';
	if ( $all[ $id ]['match_from'] ) {
		global $wpdb;
		// Už načítané pohyby pred týmto dátumom (nespárované) idú do archívu, po ňom späť na spárovanie.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . jinv_table( 'banktx' ) . " SET status = 'old' WHERE account_id = %s AND status = 'new' AND tx_date < %s", $id, $all[ $id ]['match_from'] ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . jinv_table( 'banktx' ) . " SET status = 'new' WHERE account_id = %s AND status = 'old' AND tx_date >= %s", $id, $all[ $id ]['match_from'] ) ); // phpcs:ignore
		wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	}
	$tok                 = preg_replace( '/\s+/', '', (string) ( $in['token'] ?? '' ) );
	if ( '' !== $tok ) {
		$all[ $id ]['token']       = $tok;
		$all[ $id ]['token_set']   = current_time( 'Y-m-d' );
		$all[ $id ]['token_until'] = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +180 days' ) );
		$all[ $id ]['last_error']  = '';
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['token_until'] ?? '' ) ) ) {
		$all[ $id ]['token_until'] = $in['token_until'];
	}
	jinv_bank_save_accounts( $all );
	jinv_settings_update( array( 'bank_order_paid' => ! empty( $in['order_paid'] ) ? 1 : 0 ) );
	$msg = 'bsaved';
	if ( '' !== $tok || ! empty( $in['sync'] ) ) {
		$r   = jinv_fio_sync( $id );
		$msg = is_wp_error( $r ) ? 'berr' : 'bsynced';
		set_transient( 'jinv_bank_msg_' . get_current_user_id(), ( is_wp_error( $r ) ? $r->get_error_message() : sprintf( 'Nových pohybov: %d, automaticky spárovaných: %d.', $r[0], $r[1] ) ) . ( ( $nt = get_transient( 'jinv_bank_note_' . $id ) ) ? ' ' . $nt : '' ), 120 );
		delete_transient( 'jinv_bank_note_' . $id );
	}
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'bank', 'view' => 'berr' === $msg ? 'accounts' : 'new', 'jinv_msg' => $msg ) ) );
	exit;
} );

add_action( 'admin_post_jinv_bank_sync', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_bank_sync' );
	$msgs = array();
	$err  = false;
	foreach ( jinv_bank_accounts() as $id => $a ) {
		if ( 'fio' === ( $a['type'] ?? '' ) && ! empty( $a['token'] ) ) {
			$r      = jinv_fio_sync( $id );
			$err    = $err || is_wp_error( $r );
			$msgs[] = $a['name'] . ': ' . ( is_wp_error( $r ) ? $r->get_error_message() : sprintf( 'nových pohybov %d, spárovaných %d', $r[0], $r[1] ) ) . ( ( $nt = get_transient( 'jinv_bank_note_' . $id ) ) ? ' · ' . $nt : '' );
			delete_transient( 'jinv_bank_note_' . $id );
		}
	}
	set_transient( 'jinv_bank_msg_' . get_current_user_id(), $msgs ? implode( ' · ', $msgs ) : 'Žiadny účet s tokenom.', 120 );
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'bank', 'view' => sanitize_key( $_POST['view'] ?? 'new' ), 'jinv_msg' => $err ? 'berr' : 'bsynced' ) ) ); // phpcs:ignore
	exit;
} );

// Znova skúsiť automatické párovanie všetkých nespárovaných (napr. po pridaní faktúr).
add_action( 'admin_post_jinv_bank_rematch', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_bank_rematch' );
	$ids = $wpdb->get_col( 'SELECT id FROM ' . jinv_table( 'banktx' ) . " WHERE status = 'new' ORDER BY id" ); // phpcs:ignore
	$n   = 0;
	foreach ( (array) $ids as $id ) {
		$n += jinv_bank_auto_match( (int) $id ) ? 1 : 0;
	}
	wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	$left = jinv_bank_unmatched_count();
	set_transient( 'jinv_bank_msg_' . get_current_user_id(), sprintf( 'Spárovaných: %d. Na spárovanie ostáva: %d (poplatky a daň sa presunuli medzi ignorované).', $n, $left ), 120 );
	wp_safe_redirect( jinv_admin_url( array( 'tab' => 'bank', 'view' => 'new', 'jinv_msg' => 'bsynced' ) ) );
	exit;
} );

add_action( 'admin_post_jinv_bank_tx', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$in = wp_unslash( $_REQUEST ); // phpcs:ignore
	$id = (int) ( $in['tx'] ?? 0 );
	$do = sanitize_key( $in['do'] ?? '' );
	check_admin_referer( 'jinv_bank_tx_' . $id );
	$r = true;
	if ( 'pair' === $do ) {
		$doc = (int) ( $in['doc'] ?? 0 );
		if ( ! $doc && ! empty( $in['number'] ) ) {
			global $wpdb;
			$tx   = jinv_bank_tx( $id );
			$kind = $tx && (float) $tx['amount'] > 0 ? "kind IN ('issued','proforma')" : "kind = 'received'";
			$doc  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . " WHERE $kind AND status <> 'cancelled' AND (number = %s OR vs = %s) ORDER BY id DESC LIMIT 1", trim( $in['number'] ), preg_replace( '/\D/', '', $in['number'] ) ) ); // phpcs:ignore
			if ( ! $doc && $tx && (float) $tx['amount'] > 0 ) {
				$o = jinv_bank_order_by_number( trim( $in['number'] ) );
				$r = $o ? jinv_bank_pair_order( $id, $o->get_id() ) : new WP_Error( 'jinv_bank', 'Doklad ani objednávka „' . sanitize_text_field( $in['number'] ) . '“ sa nenašli.' );
			} elseif ( ! $doc ) {
				$r = new WP_Error( 'jinv_bank', 'Doklad „' . sanitize_text_field( $in['number'] ) . '“ sa nenašiel.' );
			}
		}
		if ( $doc ) {
			$r = jinv_bank_pair( $id, $doc );
		}
	} elseif ( 'ignore' === $do ) {
		global $wpdb;
		$wpdb->update( jinv_table( 'banktx' ), array( 'status' => 'ignored', 'note' => sanitize_text_field( $in['note'] ?? '' ) ), array( 'id' => $id, 'status' => 'new' ) );
		wp_cache_delete( 'jinv_bank_unmatched', 'jinv' );
	} elseif ( 'unignore' === $do || 'unpair' === $do ) {
		jinv_bank_unpair( $id );
	}
	if ( is_wp_error( $r ) ) {
		set_transient( 'jinv_bank_msg_' . get_current_user_id(), $r->get_error_message(), 120 );
	}
	wp_safe_redirect( jinv_back_url( $in['back'] ?? '', is_wp_error( $r ) ? 'berr' : 'b' . $do ) );
	exit;
} );

/* ---------------------------------------------------------------- Stránka */

function jinv_render_bank() {
	global $wpdb;
	$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore
	$accs = jinv_bank_accounts();
	if ( ! $view ) {
		$view = $accs ? ( jinv_bank_unmatched_count() ? 'new' : 'all' ) : 'accounts';
	}
	$msg = get_transient( 'jinv_bank_msg_' . get_current_user_id() );
	if ( $msg ) {
		delete_transient( 'jinv_bank_msg_' . get_current_user_id() );
		$err = isset( $_GET['jinv_msg'] ) && 'berr' === $_GET['jinv_msg']; // phpcs:ignore
		echo '<div class="notice notice-' . ( $err ? 'error' : 'info' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	$n    = jinv_bank_unmatched_count();
	$tabs = array( 'new' => 'Na spárovanie' . ( $n ? ' (' . $n . ')' : '' ), 'all' => 'Pohyby', 'accounts' => 'Účty' );
	?>
	<div class="jinv-bank-top">
		<?php foreach ( $accs as $a ) : ?>
			<div class="jinv-bank-acc">
				<span><?php echo esc_html( $a['name'] ?? 'Účet' ); ?><?php echo ! empty( $a['iban'] ) ? ' · ' . esc_html( jinv_iban_format( $a['iban'] ) ) : ''; ?></span>
				<strong><?php echo isset( $a['balance'] ) ? esc_html( jinv_money( $a['balance'], $a['currency'] ?? 'EUR' ) ) : '—'; ?></strong>
				<?php if ( ! empty( $a['opening'] ) ) : $od = array_key_first( (array) $a['opening'] ); ?><small>zostatok k <?php echo esc_html( jinv_date( $od ) ); ?>: <?php echo esc_html( jinv_money( $a['opening'][ $od ], $a['currency'] ?? 'EUR' ) ); ?></small><?php endif; ?>
				<small><?php echo ! empty( $a['last_sync'] ) ? 'načítané ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $a['last_sync'] ) ) ) : 'zatiaľ nenačítané'; ?><?php echo ! empty( $a['last_error'] ) ? ' · <span class="jinv-warn-t">' . esc_html( $a['last_error'] ) . '</span>' : ''; ?><?php echo jinv_bank_token_warning( $a ); // phpcs:ignore ?></small>
			</div>
		<?php endforeach; ?>
		<?php if ( array_filter( $accs, fn( $a ) => ! empty( $a['token'] ) ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jinv_bank_sync' ); ?><input type="hidden" name="action" value="jinv_bank_sync"><input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
				<button class="button button-primary">Načítať pohyby teraz</button>
			</form>
		<?php endif; ?>
	</div>
	<nav class="jinv-tabs">
		<?php foreach ( $tabs as $k => $l ) : ?><a href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'bank', 'view' => $k ) ) ); ?>" class="<?php echo $k === $view ? 'is-active' : ''; ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?>
	</nav>
	<?php
	if ( 'accounts' === $view ) {
		jinv_render_bank_accounts();
		return;
	}
	$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	$where = array( 'new' === $view ? "status = 'new'" : '1=1' );
	if ( '' !== $q ) {
		$like    = '%' . $wpdb->esc_like( $q ) . '%';
		$where[] = $wpdb->prepare( '(vs LIKE %s OR counter_name LIKE %s OR message LIKE %s OR counter_account LIKE %s)', $like, $like, $like, $like );
	}
	$rows = $wpdb->get_results( 'SELECT * FROM ' . jinv_table( 'banktx' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY tx_date DESC, id DESC LIMIT 300', ARRAY_A ); // phpcs:ignore
	$back = jinv_admin_url( array( 'tab' => 'bank', 'view' => $view ) );
	?>
	<form method="get" class="jinv-filter">
		<input type="hidden" name="page" value="j-invoices-bank"><input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="VS, protistrana, správa, účet">
		<button class="button">Hľadať</button>
	</form>
	<?php if ( 'new' === $view && $rows ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-inline-form">
			<?php wp_nonce_field( 'jinv_bank_rematch' ); ?><input type="hidden" name="action" value="jinv_bank_rematch">
			<button class="button">Skúsiť znova spárovať automaticky</button> <span class="description">po pridaní faktúr alebo objednávok</span>
		</form>
	<?php endif; ?>
	<?php if ( 'new' === $view && ! $rows ) : ?>
		<p class="jinv-done">Všetky pohyby sú spárované. ✓</p>
		<?php
		return;
	endif;
	?>
	<table class="widefat striped jinv-table jinv-bank">
		<thead><tr><th>Dátum</th><th class="n">Suma</th><th>VS</th><th>Protistrana</th><th>Správa</th><th><?php echo 'new' === $view ? 'Spárovať' : 'Stav'; ?></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php echo $accs ? 'Zatiaľ žiadne pohyby – kliknite Načítať pohyby teraz.' : 'Najprv pripojte účet v záložke Účty.'; ?></td></tr><?php endif; ?>
		<?php foreach ( (array) $rows as $t ) : ?>
			<tr class="<?php echo (float) $t['amount'] < 0 ? 'is-out' : 'is-in'; ?>">
				<td><?php echo esc_html( jinv_date( $t['tx_date'] ) ); ?></td>
				<td class="n jinv-amt"><?php echo esc_html( ( (float) $t['amount'] > 0 ? '+' : '' ) . jinv_money( $t['amount'], $t['currency'] ) ); ?></td>
				<td><?php echo esc_html( $t['vs'] ); ?></td>
				<td><?php echo esc_html( $t['counter_name'] ); ?><?php echo $t['counter_account'] ? '<br><small>' . esc_html( $t['counter_account'] ) . '</small>' : ''; ?></td>
				<td><?php echo esc_html( $t['message'] ); ?><?php echo $t['tx_type'] ? '<br><small>' . esc_html( $t['tx_type'] ) . '</small>' : ''; ?></td>
				<td class="jinv-bank-act"><?php jinv_bank_row_action( $t, $back ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function jinv_bank_token_warning( array $a ) {
	if ( empty( $a['token'] ) || empty( $a['token_until'] ) ) {
		return '';
	}
	$days = (int) floor( ( strtotime( $a['token_until'] ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
	if ( $days > 14 ) {
		return '';
	}
	return ' · <span class="jinv-warn-t">' . ( $days < 0 ? 'token vypršal – vytvorte nový' : 'token vyprší o ' . (int) $days . ' dní' ) . '</span>';
}

/** Stav alebo ponuka spárovania v riadku pohybu. */
function jinv_bank_row_action( array $t, $back ) {
	$url = function ( $do, $extra = array() ) use ( $t, $back ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'jinv_bank_tx', 'tx' => $t['id'], 'do' => $do, 'back' => rawurlencode( $back ) ), $extra ), admin_url( 'admin-post.php' ) ), 'jinv_bank_tx_' . $t['id'] );
	};
	if ( 'matched' === $t['status'] ) {
		if ( (int) $t['doc_id'] && ( $d = jinv_get( (int) $t['doc_id'] ) ) ) {
			$link = 'received' === $d['kind'] ? jinv_admin_url( array( 'tab' => 'received', 'edit' => $d['id'] ) ) : jinv_admin_url( array( 'view' => $d['id'] ) );
			echo '<span class="jinv-st jinv-st-paid">' . ( 'auto' === $t['matched_by'] ? 'Spárované automaticky' : 'Spárované' ) . '</span> <a href="' . esc_url( $link ) . '">' . esc_html( $d['number'] ) . '</a>';
		} elseif ( (int) $t['order_id'] && ( $o = wc_get_order( (int) $t['order_id'] ) ) ) {
			echo '<span class="jinv-st jinv-st-paid">Objednávka</span> <a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>';
		} else {
			echo '<span class="jinv-st jinv-st-paid">Spárované</span>';
		}
		echo ' · <a href="' . esc_url( $url( 'unpair' ) ) . '" onclick="return confirm(\'Zrušiť spárovanie? Úhrada z banky sa z dokladu odstráni.\');">zrušiť</a>';
		return;
	}
	if ( 'old' === $t['status'] ) {
		echo '<span class="jinv-st jinv-st-cancelled" style="text-decoration:none">Archív</span>';
		return;
	}
		if ( 'ignored' === $t['status'] ) {
		echo '<span class="jinv-st jinv-st-cancelled">Ignorované</span>' . ( $t['note'] ? ' <small>' . esc_html( $t['note'] ) . '</small>' : '' ) . ' · <a href="' . esc_url( $url( 'unignore' ) ) . '">vrátiť</a>';
		return;
	}
	foreach ( jinv_bank_candidates( $t, 3 ) as $c ) {
		$who = 'received' === $c['kind'] ? $c['customer_name'] : $c['customer_name'];
		echo '<a class="button button-small jinv-cand" href="' . esc_url( $url( 'pair', array( 'doc' => $c['id'] ) ) ) . '" title="' . esc_attr( 'Zostáva ' . jinv_money( $c['rest'], $c['currency'] ) . ( $c['vsok'] ? ' · VS sedí' : '' ) ) . '">' . esc_html( $c['number'] . ' · ' . $who . ' · ' . jinv_money( $c['rest'], $c['currency'] ) ) . '</a> ';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-bank-form">
		<?php wp_nonce_field( 'jinv_bank_tx_' . $t['id'] ); ?>
		<input type="hidden" name="action" value="jinv_bank_tx"><input type="hidden" name="tx" value="<?php echo (int) $t['id']; ?>"><input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
		<input type="text" name="number" placeholder="<?php echo (float) $t['amount'] > 0 ? 'č. faktúry / objednávky' : 'č. prijatej faktúry'; ?>" aria-label="Číslo dokladu">
		<button class="button button-small" name="do" value="pair">Spárovať</button>
		<button class="button button-small button-link" name="do" value="ignore" title="Poplatok, prevod medzi účtami, vklad…">Ignorovať</button>
	</form>
	<?php
}

function jinv_render_bank_accounts() {
	$accs = jinv_bank_accounts();
	$edit = isset( $_GET['acc'] ) ? sanitize_key( $_GET['acc'] ) : ''; // phpcs:ignore
	$list = $accs;
	if ( ! $accs || 'new' === $edit ) {
		$list = array( '' => array() ) + $accs;
	}
	?>
	<?php foreach ( $list as $id => $a ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jinv-panel jinv-rform" autocomplete="off">
			<?php wp_nonce_field( 'jinv_bank_account' ); ?>
			<input type="hidden" name="action" value="jinv_bank_account"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
			<h3><?php echo $id ? esc_html( $a['name'] ?? 'Účet' ) : 'Pripojiť účet vo Fio banke'; ?></h3>
			<table class="form-table" role="presentation">
				<tr><th><label for="ba-name-<?php echo esc_attr( $id ); ?>">Názov účtu</label></th><td><input id="ba-name-<?php echo esc_attr( $id ); ?>" name="name" class="regular-text" value="<?php echo esc_attr( $a['name'] ?? 'Fio – e-shop' ); ?>"><?php echo ! empty( $a['iban'] ) ? '<p class="description">IBAN ' . esc_html( jinv_iban_format( $a['iban'] ) ) . '</p>' : ''; ?></td></tr>
				<tr><th><label for="ba-tok-<?php echo esc_attr( $id ); ?>">Token Fio API</label></th><td>
					<input id="ba-tok-<?php echo esc_attr( $id ); ?>" type="password" name="token" class="large-text" autocomplete="new-password" spellcheck="false" placeholder="<?php echo ! empty( $a['token'] ) ? 'uložený (…' . esc_attr( substr( $a['token'], -4 ) ) . ') – nový token ho nahradí' : 'vložte 64-znakový token'; ?>">
					<p><a class="button button-secondary" href="https://ib.fio.sk/ib/login" target="_blank" rel="noopener noreferrer">Otvoriť internetbanking Fio ↗</a> <a href="https://ib.fio.cz/ib/login" target="_blank" rel="noopener noreferrer" class="description">(český účet: ib.fio.cz)</a></p>
					<p class="description">V internetbankingu Fio → <strong>Nastavenia → API → Pridať token</strong>, druh <strong>Sledovanie účtu</strong> (len čítanie, platí 180 dní). Token začne fungovať asi 5 minút po autorizácii. Neukladajte token s právom zadávať platby.</p>
				</td></tr>
				<?php if ( ! empty( $a['token'] ) ) : ?>
					<tr><th><label for="ba-until-<?php echo esc_attr( $id ); ?>">Token platí do</label></th><td><input id="ba-until-<?php echo esc_attr( $id ); ?>" type="date" name="token_until" value="<?php echo esc_attr( $a['token_until'] ?? '' ); ?>"><?php echo jinv_bank_token_warning( $a ); // phpcs:ignore ?> <span class="description">14 dní pred vypršaním upozorní; dátum nájdete v internetbankingu pri tokene.</span></td></tr>
				<?php endif; ?>
				<tr><th><label for="ba-since-<?php echo esc_attr( $id ); ?>">Načítať pohyby od</label></th><td><input id="ba-since-<?php echo esc_attr( $id ); ?>" type="date" name="since" value="<?php echo esc_attr( $a['since'] ?? gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -30 days' ) ) ); ?>"> <span class="description">prvé načítanie; ďalej sa načítavajú len nové pohyby. Fio vydá cez API 90 dní dozadu – staršie len po odblokovaní histórie v internetbankingu (Nastavenia → API).</span></td></tr>
				<tr><th><label for="ba-mf-<?php echo esc_attr( $id ); ?>">Párovať úhrady od</label></th><td><input id="ba-mf-<?php echo esc_attr( $id ); ?>" type="date" name="match_from" value="<?php echo esc_attr( $a['match_from'] ?? '' ); ?>"> <span class="description">staršie pohyby sa uložia len ako archív (história účtu) – nepárujú sa a nečakajú na spárovanie</span></td></tr>
				<tr><th>Automaticky</th><td><label><input type="checkbox" name="auto" value="1" <?php checked( $id ? ! empty( $a['auto'] ) : true ); ?>> načítať pohyby a párovať úhrady každých 30 minút</label></td></tr>
				<tr><th>Objednávky</th><td><label><input type="checkbox" name="order_paid" value="1" <?php checked( (bool) jinv_opt( 'bank_order_paid' ) ); ?>> keď príde platba za objednávku (VS = číslo objednávky alebo zálohovej faktúry, presná suma), označiť objednávku ako zaplatenú</label><p class="description">Objednávka „Čaká na platbu“ prejde na „Spracováva sa“ a v automatickom režime sa vystaví faktúra. Bez tejto voľby sa do objednávky len zapíše poznámka o platbe.</p></td></tr>
			</table>
			<p>
				<button class="button button-primary"><?php echo $id ? 'Uložiť' : 'Pripojiť a načítať pohyby'; ?></button>
				<?php if ( $id && ! empty( $a['token'] ) ) : ?><button class="button" name="sync" value="1">Uložiť a načítať pohyby</button><?php endif; ?>
				<?php if ( $id ) : ?><button class="button jinv-danger" name="remove" value="1" style="float:right" onclick="return confirm('Odpojiť účet? Token sa zmaže, načítané pohyby a úhrady ostanú.');">Odpojiť účet</button><?php endif; ?>
			</p>
		</form>
	<?php endforeach; ?>
	<?php if ( $accs && 'new' !== $edit ) : ?><p><a class="button" href="<?php echo esc_url( jinv_admin_url( array( 'tab' => 'bank', 'view' => 'accounts', 'acc' => 'new' ) ) ); ?>">+ Pripojiť ďalší účet</a></p><?php endif; ?>
	<p class="description">Pripravuje sa: import výpisu z ľubovoľnej banky (XML camt.053 / CSV) a čítanie e-mailových notifikácií banky cez Jupiter Email.</p>
	<?php
}
