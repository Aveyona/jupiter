<?php
/**
 * Automatické účtovanie dokladov z J-Fakturácie, banky a J-Skladu.
 *
 * Princíp: pre každý zdrojový doklad (od dátumu „start_date“) sa zostavia zápisy a ich odtlačok (hash).
 *  - doklad ešte nie je zaúčtovaný → zaúčtuje sa,
 *  - zaúčtovaný s rovnakým odtlačkom → nič,
 *  - zmenil sa (suma, stav, spárovanie) → storno pôvodného a nový doklad (v uzavretom období len upozornenie),
 *  - zrušený / stornovaný zdroj → storno.
 * Opakované spustenie nič nezdvojí. Čo sa nedá zaúčtovať samo (pohyb v banke bez dokladu, úhrada bez určenia účtu),
 * čaká v zozname „Na zaúčtovanie“.
 *
 * Predkontácie (predvolené, menia sa v Nastaveniach):
 *  vydaná faktúra 311/604 (tovar), 311/602 (doprava, poplatky); dobropis rovnako so zápornou sumou;
 *  úhrada zálohy alebo objednávky na účet 221/324, pri faktúre zúčtovanie 324/311;
 *  prijatá faktúra náklad podľa kategórie (Tovar 131 pri metóde A) / 321;
 *  banka: príjem 221/311, výdaj 321/221, poplatok 568/221, transakčná daň 538/221, úrok 221/662;
 *  hotovosť 211, karta / brána 261; J-Sklad: príjemka 132/131, predaj (mesačne) 504/132, manko 549/132, prebytok 132/648.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------- Pomocné */

function juc_acc_opt( $k, $def ) {
	$v = preg_replace( '/\D/', '', (string) juc_opt( $k ) );
	return $v ? $v : $def;
}

/** Analytický účet 221xxx pre bankový účet J-Fakturácie. */
function juc_bank_account( $jinv_account_id ) {
	$map = (array) juc_opt( 'bank_map' );
	if ( ! empty( $map[ $jinv_account_id ] ) ) {
		return $map[ $jinv_account_id ];
	}
	$base = juc_acc_opt( 'acc_bank', '221' );
	$n    = count( $map ) + 1;
	$num  = substr( $base, 0, 3 ) . sprintf( '%03d', $n );
	$acc  = function_exists( 'jinv_bank_account' ) ? jinv_bank_account( $jinv_account_id ) : null;
	juc_account_ensure( $num, $acc ? ( $acc['name'] ?? 'Bankový účet' ) . ( ! empty( $acc['iban'] ) ? ' ' . substr( $acc['iban'], -4 ) : '' ) : 'Bankový účet' );
	$map[ $jinv_account_id ] = $num;
	juc_settings_update( array( 'bank_map' => $map ) );
	return $num;
}

/** Druh položky vydanej faktúry: product (tovar) | virtual (služba – virtuálny produkt) | service (doprava, dobierka, poplatky). */
function juc_item_kind( array $it ) {
	if ( ! empty( $it['type'] ) ) {
		return array( 'product' => 'product', 'service' => 'virtual' )[ $it['type'] ] ?? 'service';
	}
	$name = (string) ( $it['name'] ?? '' );
	if ( 0 === strpos( $name, 'Doprava:' ) ) {
		return 'service';
	}
	$cod = function_exists( 'jinv_opt' ) ? (string) jinv_opt( 'cod_item_name' ) : '';
	if ( $cod && $name === $cod ) {
		return 'service';
	}
	return '' === (string) ( $it['sku'] ?? '' ) && preg_match( '/poplat|dobier|balné|balenie|služb/iu', $name ) ? 'service' : 'product';
}

/** Účet nákladov prijatej faktúry podľa kategórie. */
function juc_expense_account( $category ) {
	$map = (array) juc_opt( 'cat_map' );
	$acc = isset( $map[ $category ] ) ? preg_replace( '/\D/', '', (string) $map[ $category ] ) : '';
	$acc = $acc ? $acc : juc_acc_opt( 'cat_default', '518' );
	if ( 'B' === juc_opt( 'stock_method' ) && 0 === strpos( $acc, '131' ) ) {
		$acc = juc_acc_opt( 'acc_cogs', '504' ); // metóda B: tovar priamo do nákladov
	}
	return $acc;
}

function juc_start_date() {
	return (string) juc_opt( 'start_date' );
}

/* ---------------------------------------------------------------- Zostavenie zápisov zo zdrojov */

/** Vydaná faktúra / dobropis. */
function juc_build_issued( array $d ) {
	$cust  = juc_acc_opt( 'acc_customers', '311' );
	$goods = juc_acc_opt( 'acc_sales_goods', '604' );
	$serv  = juc_acc_opt( 'acc_sales_serv', '602' );
	$virt  = juc_acc_opt( 'acc_sales_virtual', $serv );
	$sum   = array( $goods => 0.0, $virt => 0.0, $serv => 0.0 );
	foreach ( (array) ( $d['data']['items'] ?? array() ) as $it ) {
		if ( ! empty( $it['info'] ) ) {
			continue;
		}
		$k          = juc_item_kind( (array) $it );
		$acc        = 'product' === $k ? $goods : ( 'virtual' === $k ? $virt : $serv );
		$sum[ $acc ] = ( $sum[ $acc ] ?? 0 ) + (float) ( $it['total'] ?? 0 );
	}
	$diff = round( (float) $d['total'] - array_sum( $sum ), 2 );
	if ( abs( $diff ) >= 0.005 ) {
		$sum[ $goods ] += $diff; // zaokrúhlenie / položky bez rozpisu
	}
	$name  = (string) $d['customer_name'];
	$vs    = $d['vs'] ? $d['vs'] : $d['number'];
	$lines = array();
	foreach ( $sum as $acc => $amt ) {
		if ( abs( $amt ) >= 0.005 ) {
			$lines[] = array( 'md' => $cust, 'd' => $acc, 'amount' => round( $amt, 2 ), 'text' => ( $acc === $goods ? 'Tržba za tovar' : ( $acc === $virt && $virt !== $serv ? 'Tržba za služby' : 'Tržba za dopravu a poplatky' ) ) . ' – ' . $d['number'], 'vs' => $vs );
		}
	}
	// Zúčtovanie prijatých preddavkov (zálohová faktúra / platba objednávky) k faktúre.
	if ( 'issued' === $d['kind'] && (int) $d['order_id'] ) {
		$adv = juc_advance_balance( (int) $d['order_id'], (int) $d['id'] );
		$use = min( $adv, (float) $d['total'] );
		if ( $use >= 0.005 ) {
			$lines[] = array( 'md' => juc_acc_opt( 'acc_adv_recv', '324' ), 'd' => $cust, 'amount' => round( $use, 2 ), 'text' => 'Zúčtovanie prijatého preddavku – ' . $d['number'], 'vs' => $vs );
		}
	}
	return array(
		'head'  => array(
			'series'       => 'creditnote' === $d['kind'] ? 'OD' : 'OF',
			'number'       => $d['number'],
			'entry_date'   => $d['supply_date'] && '0000-00-00' !== $d['supply_date'] ? $d['supply_date'] : $d['issue_date'],
			'doc_date'     => $d['issue_date'],
			'descr'        => ( 'creditnote' === $d['kind'] ? 'Dobropis ' : 'Vydaná faktúra ' ) . $d['number'] . ( $name ? ' – ' . $name : '' ),
			'partner_name' => $name,
			'vs'           => $vs,
		),
		'lines' => $lines,
	);
}

/**
 * Prijaté preddavky k objednávke (platby zálohovej faktúry alebo objednávky na 324), ktoré ešte nezúčtovala
 * iná faktúra tej istej objednávky. Vlastné zúčtovanie dokladu $doc_id sa nepočíta (inak by sa zápis pri každom
 * behu menil).
 */
function juc_advance_balance( $order_id, $doc_id = 0 ) {
	$keys = array( 'got:order:' . (int) $order_id );
	if ( function_exists( 'jinv_docs_for_order' ) ) {
		foreach ( jinv_docs_for_order( $order_id ) as $x ) {
			if ( 'proforma' === $x['kind'] ) {
				$keys[] = 'got:jinv:' . (int) $x['id'];
			}
		}
	}
	$link = (array) get_option( 'juc_advances', array() );
	$got  = 0.0;
	foreach ( $keys as $k ) {
		$got += (float) ( $link[ $k ] ?? 0 );
	}
	$used = 0.0;
	foreach ( $link as $k => $v ) {
		if ( 0 === strpos( $k, 'used:' . (int) $order_id . ':' ) && 'used:' . (int) $order_id . ':' . (int) $doc_id !== $k ) {
			$used += (float) $v;
		}
	}
	return max( 0, round( $got - $used, 2 ) );
}

/** Prijatá faktúra. */
function juc_build_received( array $d ) {
	$cat = (string) ( $d['data']['category'] ?? '' );
	if ( '' === $cat && (int) $d['partner_id'] && function_exists( 'jinv_partner_get' ) ) {
		$p   = jinv_partner_get( (int) $d['partner_id'] );
		$cat = $p ? (string) $p['category'] : '';
	}
	$acc = juc_expense_account( $cat );
	$vs  = $d['vs'] ? $d['vs'] : $d['number'];
	return array(
		'head'  => array(
			'series'       => 'DF',
			'number'       => 'DF-' . $d['number'],
			'entry_date'   => $d['supply_date'] && '0000-00-00' !== $d['supply_date'] ? $d['supply_date'] : $d['issue_date'],
			'doc_date'     => $d['issue_date'],
			'descr'        => 'Prijatá faktúra ' . $d['number'] . ' – ' . $d['customer_name'] . ( $cat ? ' (' . $cat . ')' : '' ),
			'partner_id'   => (int) $d['partner_id'],
			'partner_name' => $d['customer_name'],
			'vs'           => $vs,
		),
		'lines' => array( array( 'md' => $acc, 'd' => juc_acc_opt( 'acc_suppliers', '321' ), 'amount' => (float) $d['total'], 'text' => ( $cat ? $cat : 'Prijatá faktúra' ) . ' – ' . $d['number'], 'vs' => $vs ) ),
	);
}

/** Pohyb v banke. Vráti zápis, alebo null (čaká na rozhodnutie v „Na zaúčtovanie“). */
function juc_build_banktx( array $t ) {
	$bank = juc_bank_account( $t['account_id'] );
	$amt  = (float) $t['amount'];
	$abs  = abs( $amt );
	$head = array(
		'series'       => 'BV',
		'number'       => 'BV-' . $t['ext_id'],
		'entry_date'   => $t['tx_date'],
		'descr'        => trim( ( $amt > 0 ? 'Príjem na účet' : 'Platba z účtu' ) . ( $t['counter_name'] ? ' – ' . $t['counter_name'] : '' ) . ( $t['message'] ? ' – ' . $t['message'] : '' ) ),
		'partner_name' => $t['counter_name'],
		'vs'           => $t['vs'],
	);
	$line = null;
	if ( 'matched' === $t['status'] && (int) $t['doc_id'] && function_exists( 'jinv_get' ) && ( $d = jinv_get( (int) $t['doc_id'] ) ) ) {
		$vs = $d['vs'] ? $d['vs'] : $d['number'];
		if ( 'received' === $d['kind'] ) {
			$line = array( 'md' => juc_acc_opt( 'acc_suppliers', '321' ), 'd' => $bank, 'amount' => $abs, 'text' => 'Úhrada prijatej faktúry ' . $d['number'], 'vs' => $vs, 'partner_id' => (int) $d['partner_id'] );
		} elseif ( 'proforma' === $d['kind'] ) {
			$line = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_adv_recv', '324' ), 'amount' => $abs, 'text' => 'Prijatý preddavok – ' . $d['number'], 'vs' => $vs );
			$head['adv_key'] = 'jinv:' . (int) $d['id'];
		} else {
			$line = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_customers', '311' ), 'amount' => $abs, 'text' => 'Úhrada faktúry ' . $d['number'], 'vs' => $vs );
		}
		$head['partner_name'] = $d['customer_name'];
	} elseif ( 'matched' === $t['status'] && (int) $t['order_id'] ) {
		$line            = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_adv_recv', '324' ), 'amount' => $abs, 'text' => 'Platba za objednávku (preddavok) – VS ' . $t['vs'], 'vs' => $t['vs'] );
		$head['adv_key'] = 'order:' . (int) $t['order_id'];
	} elseif ( preg_match( '/transak\S* da/iu', $t['tx_type'] ) ) {
		$line = array( 'md' => juc_acc_opt( 'acc_tx_tax', '538' ), 'd' => $bank, 'amount' => $amt < 0 ? $abs : -$abs, 'text' => 'Transakčná daň' );
	} elseif ( preg_match( '/poplat/iu', $t['tx_type'] ) ) {
		$line = array( 'md' => juc_acc_opt( 'acc_bank_fees', '568' ), 'd' => $bank, 'amount' => $amt < 0 ? $abs : -$abs, 'text' => 'Bankový poplatok – ' . $t['tx_type'] );
	} elseif ( preg_match( '/úrok|urok/iu', $t['tx_type'] ) && $amt > 0 ) {
		$line = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_interest', '662' ), 'amount' => $abs, 'text' => 'Úrok' );
	}
	if ( ! $line ) {
		return null;
	}
	return array( 'head' => $head, 'lines' => array( $line ) );
}

/** Úhrada mimo banky (hotovosť, karta, ručne). Bankové úhrady sa účtujú z pohybov v banke. */
function juc_build_payment( array $p, array $d ) {
	$m = $p['method'];
	if ( 'bank' === $m && juc_bank_connected() ) {
		return null; // účtuje sa z pohybu v banke
	}
	$acc = array( 'cash' => juc_acc_opt( 'acc_cash', '211' ), 'card' => juc_acc_opt( 'acc_card', '261' ), 'bank' => juc_acc_opt( 'acc_bank', '221' ) )[ $m ] ?? '';
	if ( ! $acc ) {
		return null; // zápočet / iné – rozhodne používateľ
	}
	$vs   = $d['vs'] ? $d['vs'] : $d['number'];
	$amt  = (float) $p['amount'];
	$cust = juc_acc_opt( 'acc_customers', '311' );
	if ( 'received' === $d['kind'] ) {
		$line = array( 'md' => juc_acc_opt( 'acc_suppliers', '321' ), 'd' => $acc, 'amount' => $amt, 'text' => 'Úhrada prijatej faktúry ' . $d['number'], 'vs' => $vs, 'partner_id' => (int) $d['partner_id'] );
	} elseif ( 'proforma' === $d['kind'] ) {
		$line = array( 'md' => $acc, 'd' => juc_acc_opt( 'acc_adv_recv', '324' ), 'amount' => $amt, 'text' => 'Prijatý preddavok – ' . $d['number'], 'vs' => $vs );
	} elseif ( 'creditnote' === $d['kind'] ) {
		$line = array( 'md' => $cust, 'd' => $acc, 'amount' => $amt, 'text' => 'Vrátenie peňazí – ' . $d['number'], 'vs' => $vs );
	} else {
		$line = array( 'md' => $acc, 'd' => $cust, 'amount' => $amt, 'text' => 'Úhrada faktúry ' . $d['number'], 'vs' => $vs );
	}
	return array(
		'head'  => array(
			'series'       => 'cash' === $m ? 'PD' : 'ID',
			'number'       => '',
			'entry_date'   => $p['pay_date'],
			'descr'        => jinv_pay_methods()[ $m ] . ' – úhrada ' . $d['number'],
			'partner_name' => $d['customer_name'],
			'vs'           => $vs,
			'adv_key'      => 'proforma' === $d['kind'] ? 'jinv:' . (int) $d['id'] : '',
		),
		'lines' => array( $line ),
	);
}

function juc_bank_connected() {
	return function_exists( 'jinv_bank_accounts' ) && (bool) array_filter( jinv_bank_accounts(), fn( $a ) => ! empty( $a['token'] ) || ! empty( $a['last_sync'] ) );
}

/** Doklad J-Skladu (príjemka, výdajka, inventúra). */
function juc_build_stock_doc( array $d ) {
	global $wpdb;
	$goods = juc_acc_opt( 'acc_goods', '132' );
	$lines = array();
	if ( 'receipt' === $d['type'] ) {
		$val = 0.0;
		foreach ( $d['items'] as $it ) {
			$val += (float) $it['qty'] * (float) $it['price'];
		}
		$lines[] = array( 'md' => $goods, 'd' => juc_acc_opt( 'acc_goods_buy', '131' ), 'amount' => round( $val, 2 ), 'text' => 'Príjem tovaru na sklad – ' . $d['number'] . ( $d['partner'] ? ' (' . $d['partner'] . ')' : '' ), 'vs' => (string) $d['partner_doc'] );
	} elseif ( 'issue' === $d['type'] ) {
		$val = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(-qty * COALESCE(cost,0)),0) FROM ' . jsk_t( 'moves' ) . " WHERE doc_id = %d AND source = 'issue'", $d['id'] ) ); // phpcs:ignore
		$r   = (string) $d['reason'];
		$map = (array) juc_opt( 'issue_map' );
		$acc = ! empty( $map[ $r ] ) ? preg_replace( '/\D/', '', $map[ $r ] ) : ( preg_match( '/reprez/iu', $r ) ? '513' : ( preg_match( '/dar/iu', $r ) ? '543' : ( preg_match( '/spotreb/iu', $r ) ? '501' : juc_acc_opt( 'acc_inv_minus', '549' ) ) ) );
		$lines[] = array( 'md' => $acc, 'd' => $goods, 'amount' => round( $val, 2 ), 'text' => 'Výdaj tovaru – ' . $d['number'] . ( $r ? ' (' . $r . ')' : '' ) );
	} elseif ( 'inventory' === $d['type'] ) {
		$minus = 0.0;
		$plus  = 0.0;
		foreach ( $d['items'] as $it ) {
			$v = (float) $it['qty'] * (float) $it['price'];
			if ( $v < 0 ) {
				$minus += -$v;
			} else {
				$plus += $v;
			}
		}
		$lines[] = array( 'md' => juc_acc_opt( 'acc_inv_minus', '549' ), 'd' => $goods, 'amount' => round( $minus, 2 ), 'text' => 'Inventúrne manko – ' . $d['number'] );
		$lines[] = array( 'md' => $goods, 'd' => juc_acc_opt( 'acc_inv_plus', '648' ), 'amount' => round( $plus, 2 ), 'text' => 'Inventúrny prebytok – ' . $d['number'] );
	} else {
		return null;
	}
	return array(
		'head'  => array(
			'series'       => 'SK',
			'number'       => (string) $d['number'],
			'entry_date'   => $d['doc_date'],
			'descr'        => jsk_doc_types()[ $d['type'] ][0] . ' ' . $d['number'],
			'partner_name' => (string) $d['partner'],
		),
		'lines' => $lines,
	);
}

/** Predaj tovaru za mesiac (výdaj zo skladu v nákupných cenách – vážený priemer). */
function juc_build_sales_month( $ym ) {
	global $wpdb;
	$from = substr( $ym, 0, 4 ) . '-' . substr( $ym, 4, 2 ) . '-01';
	$to   = gmdate( 'Y-m-t', strtotime( $from ) );
	// Pohyby bez nákupnej ceny (cena doplnená neskôr) sa ocenia aktuálnou priemernou nákupnou cenou produktu.
	$val  = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(-m.qty * COALESCE(m.cost, CAST(c.meta_value AS DECIMAL(14,4)), 0)),0) FROM ' . jsk_t( 'moves' ) . " m LEFT JOIN {$wpdb->postmeta} c ON c.post_id = m.product_id AND c.meta_key = '_jsk_cost' WHERE m.source IN ('order','restore') AND m.created_at >= %s AND m.created_at <= %s", $from . ' 00:00:00', $to . ' 23:59:59' ) ); // phpcs:ignore
	return array(
		'head'  => array( 'series' => 'SK', 'number' => 'PRED-' . $ym, 'entry_date' => $to, 'descr' => 'Predaný tovar ' . substr( $ym, 4, 2 ) . '/' . substr( $ym, 0, 4 ) . ' – výdaj zo skladu v nákupných cenách' ),
		'lines' => array( array( 'md' => juc_acc_opt( 'acc_cogs', '504' ), 'd' => juc_acc_opt( 'acc_goods', '132' ), 'amount' => round( $val, 2 ), 'text' => 'Predaj tovaru ' . substr( $ym, 4, 2 ) . '/' . substr( $ym, 0, 4 ) ) ),
	);
}

/* ---------------------------------------------------------------- Synchronizácia */

/**
 * Zaúčtuje / aktualizuje jeden zdroj. $built = null → zdroj sa neúčtuje (zrušený); pri existujúcom zápise storno.
 * Vráti 'posted' | 'updated' | 'cancelled' | 'same' | 'skip' | WP_Error.
 */
function juc_sync_source( $source, $type, $sid, $built, $label = '' ) {
	$cur = juc_entry_by_source( $source, $type, $sid );
	if ( ! $built || ! array_filter( $built['lines'], fn( $l ) => abs( (float) $l['amount'] ) >= 0.005 ) ) {
		if ( $cur && 'manual' !== $cur['source_hash'] ) {
			$r = juc_storno( (int) $cur['id'], 'zdrojový doklad bol zrušený alebo zmenený' );
			return is_wp_error( $r ) ? $r : 'cancelled';
		}
		return 'skip';
	}
	$hash = md5( wp_json_encode( array( $built['head']['entry_date'], $built['lines'] ) ) );
	if ( $cur && ( $cur['source_hash'] === $hash || 'manual' === $cur['source_hash'] && 'bank' !== $source ) ) {
		return 'same';
	}
	if ( $cur ) {
		$ok = juc_can_post( $cur['entry_date'] );
		if ( is_wp_error( $ok ) ) {
			return new WP_Error( 'juc_changed_locked', 'Doklad ' . $cur['number'] . ' sa zmenil, ale je v uzavretom období – opravte ho ručne. ' . $ok->get_error_message() );
		}
		$r = juc_storno( (int) $cur['id'], 'zmena zdrojového dokladu' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		juc_advances_recount();
	}
	$adv = ! empty( $built['head']['adv_key'] ) || (bool) array_filter( $built['lines'], fn( $l ) => 0 === strpos( (string) $l['md'], juc_acc_opt( 'acc_adv_recv', '324' ) ) || 0 === strpos( (string) $l['d'], juc_acc_opt( 'acc_adv_recv', '324' ) ) );
	unset( $built['head']['adv_key'] );
	$r = juc_post( $built['head'] + array( 'source' => $source, 'source_type' => $type, 'source_id' => $sid, 'source_hash' => $hash ), $built['lines'] );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( $adv ) {
		juc_advances_recount();
	}
	return $cur ? 'updated' : 'posted';
}

/** Evidencia prijatých preddavkov (324) podľa zálohovej faktúry / objednávky – prepočíta sa zo zaúčtovaných dokladov. */
function juc_advances_recount() {
	global $wpdb;
	$adv  = juc_acc_opt( 'acc_adv_recv', '324' );
	$out  = array();
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT e.source, e.source_type, e.source_id, l.amount, l.md, l.d FROM ' . juc_t( 'lines' ) . ' l JOIN ' . juc_t( 'entries' ) . ' e ON e.id = l.entry_id WHERE (l.d LIKE %s OR l.md LIKE %s)', $adv . '%', $adv . '%' ), ARRAY_A ); // phpcs:ignore
	foreach ( (array) $rows as $r ) {
		$key = '';
		if ( 'bank' === $r['source'] && function_exists( 'jinv_bank_tx' ) && ( $t = jinv_bank_tx( (int) $r['source_id'] ) ) ) {
			$key = (int) $t['doc_id'] ? 'got:jinv:' . (int) $t['doc_id'] : ( (int) $t['order_id'] ? 'got:order:' . (int) $t['order_id'] : '' );
		} elseif ( 'jinv' === $r['source'] && 'payment' === $r['source_type'] && function_exists( 'jinv_payments' ) ) {
			$doc = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT doc_id FROM ' . jinv_table( 'payments' ) . ' WHERE id = %d', $r['source_id'] ) ); // phpcs:ignore
			$key = $doc ? 'got:jinv:' . $doc : '';
		} elseif ( 'jinv' === $r['source'] && 'doc' === $r['source_type'] && 0 === strpos( $r['md'], $adv ) && ( $d = jinv_get( (int) $r['source_id'] ) ) ) {
			$key = 'used:' . (int) $d['order_id'] . ':' . (int) $d['id'];
		}
		if ( $key ) {
			$sign        = 0 === strpos( $key, 'got:' ) ? ( 0 === strpos( $r['d'], $adv ) ? 1 : -1 ) : ( 0 === strpos( $r['md'], $adv ) ? 1 : -1 );
			$out[ $key ] = round( ( $out[ $key ] ?? 0 ) + $sign * (float) $r['amount'], 2 );
		}
	}
	update_option( 'juc_advances', $out, false );
}

/** Hlavný beh automatického účtovania. Vráti súhrn [posted, updated, cancelled, errors[]]. */
function juc_autopost_run( $limit = 2000 ) {
	global $wpdb;
	$start = juc_start_date();
	$res   = array( 'posted' => 0, 'updated' => 0, 'cancelled' => 0, 'errors' => array() );
	if ( ! $start || ! juc_opt( 'auto' ) && ! defined( 'JUC_FORCE_RUN' ) ) {
		return $res;
	}
	if ( ! juc_lock( 'juc_autopost' ) ) {
		return $res;
	}
	juc_advances_recount();
	$count = function ( $r, $label ) use ( &$res ) {
		if ( is_wp_error( $r ) ) {
			$res['errors'][ $label ] = $r->get_error_message();
		} elseif ( isset( $res[ $r ] ) ) {
			$res[ $r ]++;
		}
	};
	try {
		if ( function_exists( 'jinv_table' ) ) {
			// Úhrady (preddavky) skôr ako faktúry – zúčtovanie preddavku pri faktúre potrebuje ich zostatok.
			$pays = $wpdb->get_results( $wpdb->prepare( 'SELECT p.* FROM ' . jinv_table( 'payments' ) . " p WHERE p.source <> 'bank' AND p.pay_date >= %s ORDER BY p.pay_date, p.id LIMIT %d", $start, $limit ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $pays as $p ) {
				$d = jinv_get( (int) $p['doc_id'] );
				if ( $d ) {
					$count( juc_sync_source( 'jinv', 'payment', (int) $p['id'], juc_build_payment( $p, $d ) ), 'Úhrada ' . $d['number'] );
				}
			}
			// Bankové pohyby.
			if ( function_exists( 'jinv_bank_tx' ) ) {
				$txs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jinv_table( 'banktx' ) . ' WHERE tx_date >= %s ORDER BY tx_date, id LIMIT %d', $start, $limit * 2 ), ARRAY_A ); // phpcs:ignore
				foreach ( (array) $txs as $t ) {
					$cur = juc_entry_by_source( 'bank', 'tx', (int) $t['id'] );
					if ( $cur && 'manual' === $cur['source_hash'] && 'matched' !== $t['status'] ) {
						continue; // zaúčtované ručne z „Na zaúčtovanie“
					}
					$b = juc_build_banktx( $t );
					if ( $b || $cur ) {
						$count( juc_sync_source( 'bank', 'tx', (int) $t['id'], $b ), 'Pohyb ' . $t['ext_id'] );
					}
				}
			}
			// Vydané faktúry a dobropisy, prijaté faktúry.
			$docs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . jinv_table() . " WHERE kind IN ('issued','creditnote','received') AND issue_date >= %s ORDER BY issue_date, id LIMIT %d", $start, $limit ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $docs as $d ) {
				$d['data'] = json_decode( (string) $d['data'], true );
				$built     = 'cancelled' === $d['status'] ? null : ( 'received' === $d['kind'] ? juc_build_received( $d ) : juc_build_issued( $d ) );
				$count( juc_sync_source( 'jinv', 'doc', (int) $d['id'], $built ), ( 'received' === $d['kind'] ? 'Prijatá faktúra ' : 'Faktúra ' ) . $d['number'] );
			}
		}
		// J-Sklad (metóda A).
		if ( 'A' === juc_opt( 'stock_method' ) && function_exists( 'jsk_t' ) ) {
			$docs = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . jsk_t( 'docs' ) . " WHERE type IN ('receipt','issue','inventory') AND status IN ('posted','cancelled') AND doc_date >= %s ORDER BY doc_date, id LIMIT %d", $start, $limit ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $docs as $x ) {
				$d = jsk_doc( (int) $x['id'] );
				$count( juc_sync_source( 'jsk', 'doc', (int) $d['id'], 'posted' === $d['status'] ? juc_build_stock_doc( $d ) : null ), 'Doklad skladu ' . $d['number'] );
			}
			// Predaj tovaru – ukončené mesiace od začiatku účtovania.
			$m = gmdate( 'Ym', strtotime( $start ) );
			while ( $m < current_time( 'Ym' ) ) {
				$count( juc_sync_source( 'jsk', 'sales', (int) $m, juc_build_sales_month( $m ) ), 'Predaj tovaru ' . $m );
				$m = gmdate( 'Ym', strtotime( substr( $m, 0, 4 ) . '-' . substr( $m, 4, 2 ) . '-01 +1 month' ) );
			}
		}
	} finally {
		juc_unlock( 'juc_autopost' );
	}
	update_option( 'juc_autopost_last', array( 'at' => current_time( 'mysql' ) ) + $res, false );
	return $res;
}

/** Zámok behu (MySQL GET_LOCK) – nezávisle od J-Skladu. */
function juc_lock( $name ) {
	global $wpdb;
	return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $wpdb->prefix . $name ) );
}

function juc_unlock( $name ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . $name ) );
}

/* ---------------------------------------------------------------- Plánovanie */

add_action( 'init', function () {
	if ( juc_start_date() && juc_opt( 'auto' ) && ! wp_next_scheduled( 'juc_autopost' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'juc_autopost' );
	}
} );
add_action( 'juc_autopost', 'juc_autopost_run' );

// Po zmene dokladu v J-Fakturácii, banke alebo sklade sa účtovanie spustí o minútu (nie hneď – zmien býva viac naraz).
foreach ( array( 'jinv_document_created', 'jinv_document_regenerated', 'jinv_payment_added', 'jinv_payment_deleted', 'jinv_bank_paired', 'jinv_bank_paired_order', 'jsk_doc_posted' ) as $juc_hook ) {
	add_action( $juc_hook, function () {
		if ( juc_start_date() && juc_opt( 'auto' ) && ! wp_next_scheduled( 'juc_autopost_soon' ) ) {
			wp_schedule_single_event( time() + 60, 'juc_autopost_soon' );
		}
	} );
}
add_action( 'juc_autopost_soon', 'juc_autopost_run' );

add_action( 'admin_post_juc_autopost', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_autopost' );
	if ( ! defined( 'JUC_FORCE_RUN' ) ) {
		define( 'JUC_FORCE_RUN', true );
	}
	@set_time_limit( 300 ); // phpcs:ignore
	$r = juc_autopost_run( 5000 );
	set_transient( 'juc_err_' . get_current_user_id(), sprintf( 'Zaúčtované nové: %d, aktualizované: %d, stornované: %d.', $r['posted'], $r['updated'], $r['cancelled'] ) . ( $r['errors'] ? ' Chyby: ' . count( $r['errors'] ) . ' – pozri Na zaúčtovanie.' : '' ), 120 );
	update_option( 'juc_autopost_errors', $r['errors'], false );
	wp_safe_redirect( add_query_arg( 'juc_msg', 'auto', wp_get_referer() ? wp_get_referer() : juc_url() ) );
	exit;
} );

/** Box na prehľade: posledný beh a tlačidlo. */
function juc_render_autopost_box() {
	$last = (array) get_option( 'juc_autopost_last', array() );
	$st   = juc_start_date();
	?>
	<div class="juc-panel juc-auto">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-inline">
			<?php wp_nonce_field( 'juc_autopost' ); ?><input type="hidden" name="action" value="juc_autopost">
			<strong>Automatické účtovanie</strong>
			<?php if ( $st ) : ?>
				<span class="juc-muted">doklady od <?php echo esc_html( juc_date( $st ) ); ?> · <?php echo juc_opt( 'auto' ) ? 'zapnuté (každú hodinu a po zmene dokladu)' : 'vypnuté'; ?><?php echo ! empty( $last['at'] ) ? ' · naposledy ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( $last['at'] ) ) ) : ''; ?></span>
				<button class="button">Zaúčtovať nové doklady teraz</button>
			<?php else : ?>
				<span class="juc-muted">nezačaté – dátum začiatku účtovania sa nastaví v sprievodcovi založením.</span>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Na zaúčtovanie */

/** Pohyby v banke bez zápisu (čakajú na určenie účtu) – od začiatku účtovania. */
function juc_queue_bank() {
	global $wpdb;
	$st = juc_start_date();
	if ( ! $st || ! function_exists( 'jinv_table' ) || ! function_exists( 'jinv_bank_tx' ) ) {
		return array();
	}
	$e = juc_t( 'entries' );
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.* FROM ' . jinv_table( 'banktx' ) . " t WHERE t.tx_date >= %s AND NOT EXISTS (SELECT 1 FROM $e e WHERE e.source = 'bank' AND e.source_type = 'tx' AND e.source_id = t.id AND e.status = 'posted') ORDER BY t.tx_date, t.id LIMIT 300", $st ), ARRAY_A ); // phpcs:ignore
}

function juc_queue_count() {
	$n = wp_cache_get( 'juc_queue_n', 'juc' );
	if ( false === $n ) {
		$n = count( juc_queue_bank() ) + count( (array) get_option( 'juc_autopost_errors', array() ) );
		wp_cache_set( 'juc_queue_n', $n, 'juc', 60 );
	}
	return (int) $n;
}

function juc_page_queue() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	juc_head( 'Na zaúčtovanie' );
	juc_render_autopost_box();
	$errs = (array) get_option( 'juc_autopost_errors', array() );
	if ( $errs ) {
		echo '<div class="juc-panel"><h3>Doklady, ktoré sa nepodarilo zaúčtovať</h3><ul class="juc-errs">';
		foreach ( $errs as $k => $m ) {
			echo '<li><strong>' . esc_html( $k ) . '</strong>: ' . esc_html( $m ) . '</li>';
		}
		echo '</ul><p class="description">Po oprave (napr. doplnení účtu do rozvrhu alebo otvorení obdobia) kliknite Zaúčtovať nové doklady teraz.</p></div>';
	}
	$rows = juc_queue_bank();
	$tpl  = juc_templates();
	?>
	<h2>Pohyby v banke bez zaúčtovania</h2>
	<p class="description">Platby bez faktúry (nákup kartou, vklad, prevod, výplata dobierok…). Vyberte predkontáciu alebo účet protistrany – banka sa doplní sama. Ak k pohybu patrí faktúra, spárujte ho radšej v J-Fakturácia → Banka; zaúčtuje sa automaticky.</p>
	<?php if ( ! $rows ) : ?>
		<p class="juc-ok">✓ Všetky pohyby v banke sú zaúčtované.</p>
	<?php else : ?>
		<?php juc_accounts_datalist(); ?>
		<table class="widefat striped juc-table juc-queue">
			<thead><tr><th>Dátum</th><th class="n">Suma €</th><th>Protistrana / správa</th><th>Zaúčtovať</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $t ) : $in = (float) $t['amount'] > 0; ?>
				<tr>
					<td><?php echo esc_html( juc_date( $t['tx_date'] ) ); ?></td>
					<td class="n <?php echo $in ? 'juc-plus' : 'juc-minus'; ?>"><?php echo esc_html( ( $in ? '+' : '' ) . juc_money( $t['amount'] ) ); ?></td>
					<td><?php echo esc_html( $t['counter_name'] ); ?><?php echo $t['vs'] ? ' · VS ' . esc_html( $t['vs'] ) : ''; ?><br><small><?php echo esc_html( trim( $t['message'] . ' · ' . $t['tx_type'], ' ·' ) ); ?></small></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-qform">
							<?php wp_nonce_field( 'juc_queue_tx_' . $t['id'] ); ?><input type="hidden" name="action" value="juc_queue_tx"><input type="hidden" name="tx" value="<?php echo (int) $t['id']; ?>">
							<select name="tpl" class="juc-qtpl"><option value="">Účet protistrany…</option>
								<?php foreach ( $tpl as $k => $x ) : $other = $in ? $x[2] : $x[1]; if ( ! $other || ( $in ? ! preg_match( '/^2[12]/', $x[1] ) : ! preg_match( '/^2[12]/', $x[2] ) ) ) { continue; } ?>
									<option value="<?php echo esc_attr( $other ); ?>"><?php echo esc_html( $other . ' – ' . $x[0] ); ?></option>
								<?php endforeach; ?>
							</select>
							<input name="acc" list="juc-accs" placeholder="alebo účet" class="juc-qacc" inputmode="numeric">
							<input name="text" placeholder="Text zápisu" value="<?php echo esc_attr( $t['counter_name'] ? $t['counter_name'] : $t['tx_type'] ); ?>">
							<button class="button button-small">Zaúčtovať</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<script>document.querySelectorAll('.juc-qtpl').forEach(function(s){s.addEventListener('change',function(){s.parentNode.querySelector('.juc-qacc').value=s.value;});});</script>
	<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_juc_queue_tx', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['tx'] ?? 0 );
	check_admin_referer( 'juc_queue_tx_' . $id );
	$t   = function_exists( 'jinv_bank_tx' ) ? jinv_bank_tx( $id ) : null;
	$acc = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['acc'] ?? '' ) ); // phpcs:ignore
	$acc = $acc ? $acc : preg_replace( '/\D/', '', (string) wp_unslash( $_POST['tpl'] ?? '' ) ); // phpcs:ignore
	if ( ! $t || ! $acc ) {
		juc_back_err( juc_url( 'j-uctovnictvo-queue' ), 'Vyberte účet protistrany.' );
	}
	$bank = juc_bank_account( $t['account_id'] );
	$amt  = (float) $t['amount'];
	$text = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) ); // phpcs:ignore
	$r    = juc_post( array(
		'series'       => 'BV',
		'number'       => 'BV-' . $t['ext_id'],
		'entry_date'   => $t['tx_date'],
		'descr'        => trim( ( $amt > 0 ? 'Príjem na účet' : 'Platba z účtu' ) . ' – ' . $text ),
		'partner_name' => $t['counter_name'],
		'vs'           => $t['vs'],
		'source'       => 'bank',
		'source_type'  => 'tx',
		'source_id'    => $id,
		'source_hash'  => 'manual',
	), array( $amt > 0 ? array( 'md' => $bank, 'd' => $acc, 'amount' => $amt, 'text' => $text ) : array( 'md' => $acc, 'd' => $bank, 'amount' => -$amt, 'text' => $text ) ) );
	if ( is_wp_error( $r ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-queue' ), $r->get_error_message() );
	}
	wp_cache_delete( 'juc_queue_n', 'juc' );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-queue', array( 'juc_msg' => 'posted' ) ) );
	exit;
} );
