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
	global $wpdb;
	$base = substr( juc_acc_opt( 'acc_bank', '221' ), 0, 3 );
	// Nové analytické číslo = najvyššia existujúca analytika banky (v mape aj v rozvrhu, napr. založená ručne) + 1.
	$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(CAST(SUBSTRING(num, 4) AS UNSIGNED)) FROM ' . juc_t( 'accounts' ) . ' WHERE syn = %s AND CHAR_LENGTH(num) = 6', $base ) ); // phpcs:ignore
	foreach ( $map as $m ) {
		if ( 0 === strpos( (string) $m, $base ) && 6 === strlen( (string) $m ) ) {
			$n = max( $n, (int) substr( (string) $m, 3 ) );
		}
	}
	do {
		++$n;
		$num = $base . sprintf( '%03d', $n );
	} while ( $n < 999 && ( juc_account( $num ) || in_array( $num, $map, true ) ) );
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
	if ( ! $acc && 'Majetok' === $category ) {
		$acc = '042'; // dlhodobý majetok – obstaranie, zaradí sa z karty majetku (MD 022 / Dal 042)
	}
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
	juc_advances_flush(); // odložený prepočet evidencie preddavkov (po zaúčtovaní úhrad)
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
			// Vlastné číslo dokladu (rok + poradie v J-Fakturácii) – číslo dodávateľa sa môže u rôznych dodávateľov opakovať;
			// to ostáva v popise.
			'number'       => (int) $d['seq'] ? 'DF' . (int) $d['year'] . sprintf( '%04d', (int) $d['seq'] ) : 'DF-' . (int) $d['id'],
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

/**
 * Zúčtovací účet dobierok (peniaze na ceste): dobierka sa neúčtuje do pokladne – peniaze vyberá dopravca a pošle ich
 * hromadne na účet. Nastavenie acc_cod, inak analytika 261002 (pri analytickej karte 261001), inak účet karty.
 */
function juc_cod_account() {
	$v = preg_replace( '/\D/', '', (string) juc_opt( 'acc_cod' ) );
	if ( $v ) {
		return $v;
	}
	$card = juc_acc_opt( 'acc_card', '261' );
	if ( 6 === strlen( $card ) ) {
		$num = substr( $card, 0, 3 ) . '002';
		if ( function_exists( 'juc_account_ensure' ) ) {
			juc_account_ensure( $num, 'Peniaze na ceste – dobierky (Packeta)' );
		}
		return $num;
	}
	return $card;
}

/**
 * Výplaty platobnej brány a dopravcu na účet (príjem od Stripe / Packety) → zúčtovací účet peňazí na ceste.
 * Používa sa, keď na pohyb nie je pravidlo používateľa. Filter juc_payout_rules: [ regex => účet ].
 */
function juc_payout_account( array $t ) {
	if ( (float) $t['amount'] <= 0 ) {
		return '';
	}
	$hay   = mb_strtolower( trim( $t['counter_name'] . ' ' . $t['message'] ) );
	$rules = (array) apply_filters( 'juc_payout_rules', array(
		'/packeta|z[aá]silkovna|zasielkovna/u' => juc_cod_account(),
		'/\bstripe\b/u'                       => juc_acc_opt( 'acc_card', '261' ),
	) );
	foreach ( $rules as $re => $acc ) {
		if ( $acc && preg_match( $re, $hay ) ) {
			return (string) $acc;
		}
	}
	return '';
}

/** Účet podľa pravidla pre protiúčet (Nastavenia → Pravidlá pre pohyby v banke), alebo ''. */
function juc_bank_rule( array $t ) {
	$iban = strtoupper( str_replace( ' ', '', (string) $t['counter_account'] ) );
	$r    = (array) juc_opt( 'bank_rules' );
	if ( $iban && ! empty( $r[ $iban ] ) ) {
		return (string) $r[ $iban ];
	}
	// Pravidlá podľa názvu protistrany (n:…) a textu pohybu (t:… – správa, typ pohybu) – napr. nákupy kartou bez IBAN.
	$name = mb_strtolower( (string) $t['counter_name'] );
	$text = mb_strtolower( trim( $t['message'] . ' ' . $t['tx_type'] . ' ' . $t['counter_name'] ) );
	foreach ( $r as $k => $acc ) {
		$k = (string) $k;
		if ( 0 === strpos( $k, 'n:' ) && '' !== $name && false !== mb_strpos( $name, substr( $k, 2 ) ) ) {
			return (string) $acc;
		}
		if ( 0 === strpos( $k, 't:' ) && false !== mb_strpos( $text, substr( $k, 2 ) ) ) {
			return (string) $acc;
		}
	}
	return juc_payout_account( $t );
}

/** Kľúč pravidla pre pohyb (zapamätanie z „Na zaúčtovanie“): IBAN, inak názov protistrany, inak začiatok textu. */
function juc_bank_rule_key( array $t ) {
	$iban = strtoupper( str_replace( ' ', '', (string) $t['counter_account'] ) );
	if ( preg_match( '/^[A-Z]{2}\d{2}[0-9A-Z]{10,30}$/', $iban ) ) {
		return $iban;
	}
	$name = trim( mb_strtolower( (string) $t['counter_name'] ) );
	if ( mb_strlen( $name ) >= 3 ) {
		return 'n:' . $name;
	}
	// Text bez čísel a dátumov (napr. „nákup: google *cloud“ z „Nákup: GOOGLE *CLOUD 9D8CSJ, … dne 30.10.2026“).
	$txt = mb_strtolower( trim( preg_replace( '/[,;].*$/u', '', (string) $t['message'] ) ) );
	$txt = trim( preg_replace( '/\s+\S*\d\S*$/u', '', $txt ) );
	return mb_strlen( $txt ) >= 4 ? 't:' . mb_substr( $txt, 0, 40 ) : '';
}

/* ---------------------------------------------------------------- Vylúčenie z automatického účtovania */

/** Zdroje vylúčené z automatického účtovania („zaúčtujem ručne“): kľúč source:type:id => dátum. */
function juc_excluded( $source, $type, $sid ) {
	$x = (array) get_option( 'juc_excluded', array() );
	return isset( $x[ $source . ':' . $type . ':' . (int) $sid ] );
}

function juc_exclude_set( $source, $type, $sid, $on ) {
	$x = (array) get_option( 'juc_excluded', array() );
	$k = $source . ':' . $type . ':' . (int) $sid;
	if ( $on ) {
		$x[ $k ] = current_time( 'mysql' );
	} else {
		unset( $x[ $k ] );
	}
	update_option( 'juc_excluded', $x, false );
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
		} elseif ( 'creditnote' === $d['kind'] ) {
			// Vrátenie peňazí zákazníkovi prevodom (dobropis spárovaný s výdajom).
			$line = array( 'md' => juc_acc_opt( 'acc_customers', '311' ), 'd' => $bank, 'amount' => $abs, 'text' => 'Vrátenie peňazí – ' . $d['number'], 'vs' => $vs );
		} else {
			$line = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_customers', '311' ), 'amount' => $abs, 'text' => 'Úhrada faktúry ' . $d['number'], 'vs' => $vs );
		}
		$head['partner_name'] = $d['customer_name'];
	} elseif ( 'matched' === $t['status'] && (int) $t['order_id'] ) {
		$line            = array( 'md' => $bank, 'd' => juc_acc_opt( 'acc_adv_recv', '324' ), 'amount' => $abs, 'text' => 'Platba za objednávku (preddavok) – VS ' . $t['vs'], 'vs' => $t['vs'] );
		$head['adv_key'] = 'order:' . (int) $t['order_id'];
	} elseif ( ( $rule = juc_bank_rule( $t ) ) ) {
		$line = $amt > 0 ? array( 'md' => $bank, 'd' => $rule, 'amount' => $abs, 'text' => trim( ( $t['message'] ? $t['message'] : 'Príjem' ) . ' – ' . $t['counter_name'], ' –' ) ) : array( 'md' => $rule, 'd' => $bank, 'amount' => $abs, 'text' => trim( ( $t['message'] ? $t['message'] : 'Výdaj' ) . ' – ' . $t['counter_name'], ' –' ) );
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

/**
 * Úhrada mimo banky (hotovosť, karta, ručne). Bankové úhrady sa účtujú z pohybov v banke.
 * $acc = protiúčet zvolený používateľom („Na zaúčtovanie“ – zápočet, iné); prázdne = podľa spôsobu úhrady.
 */
function juc_build_payment( array $p, array $d, $acc = '' ) {
	$m = $p['method'];
	if ( '' === $acc ) {
		if ( 'bank' === $m && juc_bank_connected() && ! juc_bank_payment_keep( $p ) ) {
			return null; // účtuje sa z pohybu v banke
		}
		$acc = array( 'cash' => juc_acc_opt( 'acc_cash', '211' ), 'card' => juc_acc_opt( 'acc_card', '261' ), 'cod' => juc_cod_account(), 'bank' => juc_acc_opt( 'acc_bank', '221' ) )[ $m ] ?? '';
		if ( ! $acc ) {
			return null; // zápočet / iné – čaká v „Na zaúčtovanie“ (juc_queue_payments)
		}
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
	// Pokladňa: príjem PD, výdaj (Dal 211) VD.
	$cash = 0 === strpos( (string) $acc, substr( juc_acc_opt( 'acc_cash', '211' ), 0, 3 ) );
	return array(
		'head'  => array(
			'series'       => $cash ? ( (string) $line['d'] === (string) $acc ? 'VD' : 'PD' ) : 'ID',
			'number'       => '',
			'entry_date'   => $p['pay_date'],
			'descr'        => ( jinv_pay_methods()[ $m ] ?? $m ) . ' – úhrada ' . $d['number'],
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

/**
 * Ručne zapísaná úhrada „prevodom“ pri napojenej banke: účtuje sa (alebo ostáva zaúčtovaná), ak
 *  - už je zaúčtovaná (napojenie banky ju nesmie dodatočne stornovať), alebo
 *  - je z obdobia pred prvým načítaným pohybom v banke (pohyb, z ktorého by sa zaúčtovala, neexistuje).
 * Inak sa účtuje z pohybu v banke.
 */
function juc_bank_payment_keep( array $p ) {
	static $first = null;
	if ( ! empty( $p['id'] ) && juc_entry_by_source( 'jinv', 'payment', (int) $p['id'] ) ) {
		return true;
	}
	if ( null === $first ) {
		global $wpdb;
		$first = function_exists( 'jinv_table' ) ? (string) $wpdb->get_var( 'SELECT MIN(tx_date) FROM ' . jinv_table( 'banktx' ) ) : ''; // phpcs:ignore
	}
	return '' === $first || (string) $p['pay_date'] < $first;
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

/** Názov zámku (MySQL GET_LOCK) pre jeden zdroj – spoločný pre automatické účtovanie, „Na zaúčtovanie“ aj J-Platby. */
function juc_source_lock_name( $source, $type, $sid ) {
	return 'juc_src_' . substr( md5( $source . ':' . $type . ':' . (int) $sid ), 0, 20 );
}

/**
 * Zaúčtuje / aktualizuje jeden zdroj. $built = null → zdroj sa neúčtuje (zrušený); pri existujúcom zápise storno.
 * Vráti 'posted' | 'updated' | 'cancelled' | 'same' | 'skip' | WP_Error.
 * Beží pod zámkom zdroja – súbežné volanie (napr. webhook J-Platieb a hodinový beh) nezaúčtuje doklad dvakrát.
 */
function juc_sync_source( $source, $type, $sid, $built, $label = '' ) {
	$lock = juc_source_lock_name( $source, $type, $sid );
	if ( ! juc_lock( $lock, 15 ) ) {
		return new WP_Error( 'juc_busy', 'Doklad sa práve účtuje v inom procese – zaúčtuje sa pri ďalšom behu.' );
	}
	try {
		return juc_sync_source_locked( $source, $type, $sid, $built );
	} finally {
		juc_unlock( $lock );
	}
}

function juc_sync_source_locked( $source, $type, $sid, $built ) {
	if ( juc_excluded( $source, $type, $sid ) ) {
		return 'skip'; // vylúčené – používateľ účtuje ručne
	}
	$cur = juc_entry_by_source( $source, $type, $sid );
	if ( ! $built || ! array_filter( $built['lines'], fn( $l ) => abs( (float) $l['amount'] ) >= 0.005 ) ) {
		if ( $cur && 'manual' !== $cur['source_hash'] ) {
			$r = juc_storno( (int) $cur['id'], 'zdrojový doklad bol zrušený alebo zmenený' );
			juc_advances_touch();
			return is_wp_error( $r ) ? $r : 'cancelled';
		}
		return 'skip';
	}
	$hash = md5( wp_json_encode( array( $built['head']['entry_date'], $built['lines'] ) ) );
	if ( $cur && ( $cur['source_hash'] === $hash || 'manual' === $cur['source_hash'] && 'bank' !== $source ) ) {
		return 'same';
	}
	if ( ! $cur && juc_user_storno_blocks( $source, $type, $sid, $hash ) ) {
		return 'skip'; // používateľ doklad stornoval ručne a zdroj sa odvtedy nezmenil
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
		juc_advances_touch();
	}
	$adv = ! empty( $built['head']['adv_key'] ) || (bool) array_filter( $built['lines'], fn( $l ) => 0 === strpos( (string) $l['md'], juc_acc_opt( 'acc_adv_recv', '324' ) ) || 0 === strpos( (string) $l['d'], juc_acc_opt( 'acc_adv_recv', '324' ) ) );
	unset( $built['head']['adv_key'] );
	$r = juc_post( $built['head'] + array( 'source' => $source, 'source_type' => $type, 'source_id' => $sid, 'source_hash' => $hash ), $built['lines'] );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( $adv ) {
		juc_advances_touch();
	}
	return $cur ? 'updated' : 'posted';
}

/**
 * Storno zápisu zdroja, ktorý už neexistuje (zmazaná úhrada, doklad). Stornuje aj zápis zaúčtovaný ručne
 * z „Na zaúčtovanie“ – bez zdroja nemá opodstatnenie.
 */
function juc_cancel_source( $source, $type, $sid, $reason ) {
	$lock = juc_source_lock_name( $source, $type, $sid );
	if ( ! juc_lock( $lock, 15 ) ) {
		return new WP_Error( 'juc_busy', 'Doklad sa práve účtuje v inom procese – storno sa urobí pri ďalšom behu.' );
	}
	try {
		$cur = juc_entry_by_source( $source, $type, $sid );
		if ( ! $cur ) {
			return 'skip';
		}
		$r = juc_storno( (int) $cur['id'], $reason );
		juc_advances_touch();
		return is_wp_error( $r ) ? $r : 'cancelled';
	} finally {
		juc_unlock( $lock );
	}
}

/* ---- Ručné storno automaticky zaúčtovaného dokladu */

/** Zdroje, ktoré sa účtujú automaticky (ručné storno ich zápisu nesmie ďalší beh vrátiť späť). */
function juc_user_storno_sources() {
	return array( 'jinv', 'bank', 'jsk', 'jpl', 'asset' );
}

/** Zapamätá ručné storno automatického zápisu: zdroj sa znova zaúčtuje, až keď sa zmení (iný odtlačok). */
function juc_user_storno_mark( array $e ) {
	if ( ! in_array( (string) $e['source'], juc_user_storno_sources(), true ) || ! (int) $e['source_id'] || in_array( (string) $e['source_hash'], array( '', 'manual' ), true ) ) {
		return;
	}
	$flags = (array) get_option( 'juc_user_storno', array() );
	$flags[ $e['source'] . ':' . $e['source_type'] . ':' . (int) $e['source_id'] ] = (string) $e['source_hash'];
	update_option( 'juc_user_storno', $flags, false );
}

/** Má sa zdroj preskočiť (ručne stornovaný a nezmenený)? Pri zmene sa značka zruší. */
function juc_user_storno_blocks( $source, $type, $sid, $hash ) {
	$flags = (array) get_option( 'juc_user_storno', array() );
	$k     = $source . ':' . $type . ':' . (int) $sid;
	if ( ! isset( $flags[ $k ] ) ) {
		return false;
	}
	if ( $flags[ $k ] === $hash ) {
		return true;
	}
	unset( $flags[ $k ] );
	update_option( 'juc_user_storno', $flags, false );
	return false;
}

/* ---- Evidencia preddavkov */

/** Zaúčtovanie sa dotklo preddavkov – prepočet sa urobí raz, až keď je potrebný (alebo na konci požiadavky). */
function juc_advances_touch() {
	$GLOBALS['juc_adv_dirty'] = true;
}

function juc_advances_flush() {
	if ( ! empty( $GLOBALS['juc_adv_dirty'] ) ) {
		$GLOBALS['juc_adv_dirty'] = false;
		juc_advances_recount();
	}
}
add_action( 'shutdown', 'juc_advances_flush' );

/** Evidencia prijatých preddavkov (324) podľa zálohovej faktúry / objednávky – prepočíta sa zo zaúčtovaných dokladov. */
function juc_advances_recount() {
	global $wpdb;
	$GLOBALS['juc_adv_dirty'] = false;
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

/* ---- Hlavný beh */

/** Druhy zdrojov v poradí spracovania (úhrady skôr ako faktúry – zúčtovanie preddavku potrebuje ich zostatok). */
function juc_autopost_types() {
	$t = array();
	if ( function_exists( 'jinv_table' ) ) {
		$t[] = 'pay';
		if ( function_exists( 'jinv_bank_tx' ) ) {
			$t[] = 'tx';
		}
		$t[] = 'doc';
	}
	if ( 'A' === juc_opt( 'stock_method' ) && function_exists( 'jsk_t' ) && function_exists( 'jsk_doc' ) ) {
		$t[] = 'jsk';
	}
	return $t;
}

/** Riadky zdrojov daného druhu od začiatku účtovania, zoradené podľa ID. $where používa alias x. */
function juc_autopost_fetch( $type, $where, $limit ) {
	global $wpdb;
	$start = juc_start_date();
	$limit = max( 1, (int) $limit );
	switch ( $type ) {
		case 'pay':
			$sql = $wpdb->prepare( 'SELECT x.* FROM ' . jinv_table( 'payments' ) . " x WHERE x.source <> 'bank' AND x.pay_date >= %s AND $where ORDER BY x.id LIMIT %d", $start, $limit ); // phpcs:ignore
			break;
		case 'tx':
			$sql = $wpdb->prepare( 'SELECT x.* FROM ' . jinv_table( 'banktx' ) . " x WHERE x.tx_date >= %s AND $where ORDER BY x.id LIMIT %d", $start, $limit ); // phpcs:ignore
			break;
		case 'doc':
			$sql = $wpdb->prepare( 'SELECT x.* FROM ' . jinv_table() . " x WHERE x.kind IN ('issued','creditnote','received') AND x.issue_date >= %s AND $where ORDER BY x.id LIMIT %d", $start, $limit ); // phpcs:ignore
			break;
		case 'jsk':
			$sql = $wpdb->prepare( 'SELECT x.id FROM ' . jsk_t( 'docs' ) . " x WHERE x.type IN ('receipt','issue','inventory') AND x.status IN ('posted','cancelled') AND x.doc_date >= %s AND $where ORDER BY x.id LIMIT %d", $start, $limit ); // phpcs:ignore
			break;
		default:
			return array();
	}
	return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
}

/** Zaúčtovanie jedného zdroja daného druhu. */
function juc_autopost_one( $type, array $row, callable $count ) {
	if ( 'pay' === $type ) {
		$d = jinv_get( (int) $row['doc_id'] );
		if ( $d ) {
			$count( juc_sync_source( 'jinv', 'payment', (int) $row['id'], juc_build_payment( $row, $d ) ), 'Úhrada ' . $d['number'] );
		}
	} elseif ( 'tx' === $type ) {
		$cur = juc_entry_by_source( 'bank', 'tx', (int) $row['id'] );
		if ( $cur && 'manual' === $cur['source_hash'] && 'matched' !== $row['status'] ) {
			return; // zaúčtované ručne z „Na zaúčtovanie“
		}
		$b = juc_build_banktx( $row );
		if ( $b || $cur ) {
			$count( juc_sync_source( 'bank', 'tx', (int) $row['id'], $b ), 'Pohyb ' . $row['ext_id'] );
		}
	} elseif ( 'doc' === $type ) {
		$row['data'] = json_decode( (string) $row['data'], true );
		$built       = 'cancelled' === $row['status'] ? null : ( 'received' === $row['kind'] ? juc_build_received( $row ) : juc_build_issued( $row ) );
		$count( juc_sync_source( 'jinv', 'doc', (int) $row['id'], $built ), ( 'received' === $row['kind'] ? 'Prijatá faktúra ' : 'Faktúra ' ) . $row['number'] );
	} elseif ( 'jsk' === $type ) {
		$d = jsk_doc( (int) $row['id'] );
		if ( $d ) {
			$count( juc_sync_source( 'jsk', 'doc', (int) $d['id'], 'posted' === $d['status'] ? juc_build_stock_doc( $d ) : null ), 'Doklad skladu ' . $d['number'] );
		}
	}
}

/** Zápisy zdrojov J-Fakturácie a J-Skladu, ktorých zdrojový riadok už neexistuje (zmazaný) → storno. */
function juc_autopost_deleted( callable $count ) {
	global $wpdb;
	$e      = juc_t( 'entries' );
	$checks = array();
	if ( function_exists( 'jinv_table' ) ) {
		$checks[] = array( 'jinv', 'payment', jinv_table( 'payments' ), 'Úhrada' );
		$checks[] = array( 'jinv', 'doc', jinv_table(), 'Doklad J-Fakturácie' );
	}
	if ( function_exists( 'jsk_t' ) ) {
		$checks[] = array( 'jsk', 'doc', jsk_t( 'docs' ), 'Doklad skladu' );
	}
	foreach ( $checks as $c ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT e.source_id FROM $e e WHERE e.source = %s AND e.source_type = %s AND e.status = 'posted' AND NOT EXISTS (SELECT 1 FROM {$c[2]} s WHERE s.id = e.source_id) LIMIT 200", $c[0], $c[1] ) ); // phpcs:ignore
		foreach ( (array) $ids as $sid ) {
			$count( juc_cancel_source( $c[0], $c[1], (int) $sid, 'zdrojový doklad bol zmazaný' ), $c[3] . ' #' . (int) $sid . ' (zmazaná)' );
		}
	}
}

/**
 * Hlavný beh automatického účtovania. Vráti súhrn [posted, updated, cancelled, errors[]].
 *
 * Každý beh (hodinový, po zmene dokladu): 1) zdroje nahlásené cez hooky ako zmenené, 2) nové zdroje (ID väčšie ako
 * naposledy spracované), 3) zmazané zdroje → storno, 4) predaj tovaru za otvorené mesiace.
 * Úplná kontrola všetkých zdrojov (zmeny, o ktorých hook nedá vedieť – storno faktúry, úprava prijatej faktúry…)
 * beží najviac raz denne a pri ručnom spustení; pokračuje od uloženej pozície (juc_autopost_state), kým neprejde všetko.
 * Beh má časový limit (20 s, ručne 240 s) – čo nestihne, dokončí ďalší beh.
 * Chyby sa ukladajú do juc_autopost_errors (zobrazia sa v „Na zaúčtovanie“); úspešne zaúčtovaný zdroj sa z nich vymaže.
 */
function juc_autopost_run( $limit = 2000 ) {
	$start = juc_start_date();
	$res   = array( 'posted' => 0, 'updated' => 0, 'cancelled' => 0, 'errors' => array() );
	if ( ! $start || ! juc_opt( 'auto' ) && ! defined( 'JUC_FORCE_RUN' ) ) {
		return $res;
	}
	if ( ! juc_lock( 'juc_autopost' ) ) {
		return $res;
	}
	$force  = defined( 'JUC_FORCE_RUN' );
	$t0     = microtime( true );
	$budget = $force ? 240 : 20;
	$page   = 200;
	$n      = 0;
	$state  = wp_parse_args( (array) get_option( 'juc_autopost_state', array() ), array( 'max' => array(), 'cursor' => array(), 'stage' => '', 'full_at' => 0 ) );
	$errs   = (array) get_option( 'juc_autopost_errors', array() );
	$count  = function ( $r, $label ) use ( &$res, &$errs ) {
		if ( is_wp_error( $r ) ) {
			$res['errors'][ $label ] = $r->get_error_message();
			$errs[ $label ]          = $r->get_error_message();
			return;
		}
		unset( $errs[ $label ] );
		if ( isset( $res[ $r ] ) ) {
			$res[ $r ]++;
		}
	};
	$left = function () use ( $t0, $budget, &$n, $limit ) {
		return microtime( true ) - $t0 < $budget && $n < max( 1, (int) $limit );
	};
	try {
		juc_advances_recount();
		$types = juc_autopost_types();
		// 1) Zdroje zmenené podľa hookov (úhrada, spárovanie, pregenerovanie faktúry, zaúčtovanie dokladu skladu).
		$dirty = (array) get_option( 'juc_autopost_dirty', array() );
		delete_option( 'juc_autopost_dirty' );
		foreach ( $types as $type ) {
			$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $dirty[ $type ] ?? array() ) ) ) ) );
			foreach ( array_chunk( $ids, 500 ) as $chunk ) {
				foreach ( juc_autopost_fetch( $type, 'x.id IN (' . implode( ',', $chunk ) . ')', count( $chunk ) ) as $row ) {
					juc_autopost_one( $type, $row, $count );
					++$n;
				}
			}
		}
		// 2) Nové zdroje.
		foreach ( $types as $type ) {
			while ( $left() ) {
				$rows = juc_autopost_fetch( $type, 'x.id > ' . (int) ( $state['max'][ $type ] ?? 0 ), $page );
				foreach ( $rows as $row ) {
					juc_autopost_one( $type, $row, $count );
					++$n;
					$state['max'][ $type ] = (int) $row['id'];
					if ( ! $left() ) {
						break;
					}
				}
				if ( count( $rows ) < $page ) {
					break;
				}
			}
		}
		// 3) Úplná kontrola – najviac raz denne (ručne vždy), pokračuje od uloženej pozície.
		$due = $force || '' !== $state['stage'] || (int) $state['full_at'] < time() - DAY_IN_SECONDS;
		if ( $due && $types && $left() ) {
			if ( ! in_array( $state['stage'], $types, true ) ) {
				$state['stage']  = $types[0];
				$state['cursor'] = array();
			}
			$all = true;
			for ( $i = (int) array_search( $state['stage'], $types, true ); $i < count( $types ); $i++ ) {
				$type           = $types[ $i ];
				$state['stage'] = $type;
				$done           = false;
				while ( $left() ) {
					$rows = juc_autopost_fetch( $type, 'x.id > ' . (int) ( $state['cursor'][ $type ] ?? 0 ), $page );
					$cut  = false;
					foreach ( $rows as $row ) {
						juc_autopost_one( $type, $row, $count );
						++$n;
						$state['cursor'][ $type ] = (int) $row['id'];
						if ( ! $left() ) {
							$cut = true;
							break;
						}
					}
					if ( ! $cut && count( $rows ) < $page ) {
						$done                  = true;
						$state['max'][ $type ] = max( (int) ( $state['max'][ $type ] ?? 0 ), (int) ( $state['cursor'][ $type ] ?? 0 ) );
						break;
					}
				}
				if ( ! $done ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				$state['stage']   = '';
				$state['cursor']  = array();
				$state['full_at'] = time();
			}
		}
		// 4) Zmazané zdroje → storno.
		juc_autopost_deleted( $count );
		// 5) J-Sklad (metóda A): predaj tovaru – ukončené mesiace od začiatku účtovania. Mesiac v uzavretom období
		//    (uzávierka, uzavretý rok) sa už nesynchronizuje – ocenenie aktuálnou priemernou cenou by ho stále „menilo“.
		if ( in_array( 'jsk', $types, true ) ) {
			$m = gmdate( 'Ym', strtotime( $start ) );
			while ( $m < current_time( 'Ym' ) ) {
				$end = gmdate( 'Y-m-t', strtotime( substr( $m, 0, 4 ) . '-' . substr( $m, 4, 2 ) . '-01' ) );
				if ( ! ( true !== juc_can_post( $end ) && juc_entry_by_source( 'jsk', 'sales', (int) $m ) ) ) {
					$count( juc_sync_source( 'jsk', 'sales', (int) $m, juc_build_sales_month( $m ) ), 'Predaj tovaru ' . $m );
				}
				$m = gmdate( 'Ym', strtotime( substr( $m, 0, 4 ) . '-' . substr( $m, 4, 2 ) . '-01 +1 month' ) );
			}
		}
		juc_advances_flush();
	} finally {
		update_option( 'juc_autopost_state', $state, false );
		update_option( 'juc_autopost_errors', $errs, false );
		juc_queue_count_reset();
		juc_unlock( 'juc_autopost' );
	}
	update_option( 'juc_autopost_last', array( 'at' => current_time( 'mysql' ) ) + $res, false );
	return $res;
}

/** Zámok (MySQL GET_LOCK) – nezávisle od J-Skladu. $wait = počet sekúnd čakania na uvoľnenie. */
function juc_lock( $name, $wait = 0 ) {
	global $wpdb;
	return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $wpdb->prefix . $name, (int) $wait ) );
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

/** Označí zdroj ako zmenený – spracuje sa pri najbližšom behu (aj keď nie je nový). */
function juc_autopost_mark( $type, $id ) {
	$id = (int) $id;
	if ( ! $id ) {
		return;
	}
	$d            = (array) get_option( 'juc_autopost_dirty', array() );
	$d[ $type ]   = (array) ( $d[ $type ] ?? array() );
	$d[ $type ][] = $id;
	$d[ $type ]   = array_slice( array_values( array_unique( array_map( 'intval', $d[ $type ] ) ) ), -2000 );
	update_option( 'juc_autopost_dirty', $d, false );
}

/** Doklad a ostatné faktúry tej istej objednávky (zúčtovanie preddavku sa mení s úhradou zálohy). */
function juc_autopost_mark_doc( $doc_id ) {
	juc_autopost_mark( 'doc', $doc_id );
	$d = function_exists( 'jinv_get' ) ? jinv_get( (int) $doc_id ) : null;
	if ( $d && (int) $d['order_id'] ) {
		juc_autopost_mark_order( (int) $d['order_id'] );
	}
}

function juc_autopost_mark_order( $order_id ) {
	if ( function_exists( 'jinv_docs_for_order' ) ) {
		foreach ( jinv_docs_for_order( (int) $order_id ) as $x ) {
			juc_autopost_mark( 'doc', (int) $x['id'] );
		}
	}
}

add_action( 'jinv_document_created', function ( $id ) {
	juc_autopost_mark_doc( $id );
} );
add_action( 'jinv_document_regenerated', function ( $new, $old = 0 ) {
	juc_autopost_mark_doc( $new );
	juc_autopost_mark( 'doc', $old );
}, 10, 2 );
add_action( 'jinv_payment_added', function ( $id, $doc_id = 0 ) {
	juc_autopost_mark( 'pay', $id );
	juc_autopost_mark_doc( $doc_id );
	juc_queue_count_reset();
}, 10, 2 );
add_action( 'jinv_bank_paired', function ( $tx, $doc_id = 0 ) {
	juc_autopost_mark( 'tx', $tx );
	juc_autopost_mark_doc( $doc_id );
	juc_queue_count_reset();
}, 10, 2 );
add_action( 'jinv_bank_paired_order', function ( $tx, $order_id = 0 ) {
	juc_autopost_mark( 'tx', $tx );
	juc_autopost_mark_order( $order_id );
	juc_queue_count_reset();
}, 10, 2 );
add_action( 'jsk_doc_posted', function ( $id ) {
	juc_autopost_mark( 'jsk', $id );
} );
// Storno faktúry alebo skladového dokladu: zápis sa stornuje pri najbližšom behu (nie až pri dennej kontrole).
add_action( 'jinv_document_cancelled', function ( $id ) {
	juc_autopost_mark_doc( (int) $id );
	juc_queue_count_reset();
} );
add_action( 'jsk_doc_cancelled', function ( $id ) {
	juc_autopost_mark( 'jsk', (int) $id );
} );

// Zmazaná úhrada: jej zápis sa stornuje hneď (beh by zmazaný riadok už nenašiel). Pohyb v banke, z ktorého úhrada
// vznikla, J-Fakturácia vráti medzi nespárované – jeho zápis (úhrada faktúry) sa prepočíta pri najbližšom behu.
add_action( 'jinv_payment_deleted', function ( $payment_id, $doc_id = 0 ) {
	global $wpdb;
	if ( ! juc_start_date() || ! juc_opt( 'auto' ) ) {
		return;
	}
	juc_cancel_source( 'jinv', 'payment', (int) $payment_id, 'úhrada bola zmazaná' ); // pri chybe (uzavreté obdobie) to zopakuje beh
	juc_autopost_mark_doc( $doc_id );
	if ( function_exists( 'jinv_table' ) ) {
		$like = array( juc_acc_opt( 'acc_customers', '311' ) . '%', juc_acc_opt( 'acc_suppliers', '321' ) . '%', juc_acc_opt( 'acc_adv_recv', '324' ) . '%' );
		$txs  = $wpdb->get_col( $wpdb->prepare( 'SELECT e.source_id FROM ' . juc_t( 'entries' ) . ' e JOIN ' . jinv_table( 'banktx' ) . " t ON t.id = e.source_id WHERE e.source = 'bank' AND e.source_type = 'tx' AND e.status = 'posted' AND e.source_hash <> 'manual' AND t.status <> 'matched' AND EXISTS (SELECT 1 FROM " . juc_t( 'lines' ) . ' l WHERE l.entry_id = e.id AND (l.d LIKE %s OR l.md LIKE %s OR l.d LIKE %s))', $like[0], $like[1], $like[2] ) ); // phpcs:ignore
		foreach ( (array) $txs as $tx ) {
			juc_autopost_mark( 'tx', (int) $tx );
		}
	}
	juc_queue_count_reset();
}, 10, 2 );

// Po zmene dokladu v J-Fakturácii, banke alebo sklade sa účtovanie spustí o minútu (nie hneď – zmien býva viac naraz).
foreach ( array( 'jinv_document_created', 'jinv_document_regenerated', 'jinv_document_cancelled', 'jinv_payment_added', 'jinv_payment_deleted', 'jinv_bank_paired', 'jinv_bank_paired_order', 'jsk_doc_posted', 'jsk_doc_cancelled' ) as $juc_hook ) {
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
	$r    = juc_autopost_run( 5000 );
	$errs = (array) get_option( 'juc_autopost_errors', array() ); // chyby uloží sám beh
	$st   = (array) get_option( 'juc_autopost_state', array() );
	set_transient( 'juc_err_' . get_current_user_id(), sprintf( 'Zaúčtované nové: %d, aktualizované: %d, stornované: %d.', $r['posted'], $r['updated'], $r['cancelled'] ) . ( ! empty( $st['stage'] ) ? ' Kontrola všetkých dokladov ešte nie je dokončená – pokračuje automaticky (alebo kliknite znova).' : '' ) . ( $errs ? ' Chyby: ' . count( $errs ) . ' – pozri Na zaúčtovanie.' : '' ), 120 );
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

/**
 * Úhrady, ktoré program nevie zaúčtovať sám (zápočet, iné – napr. „Pôvodná úhrada“ z J-Fakturácie):
 * nezaúčtované, od začiatku účtovania. Zaúčtujú sa výberom protiúčtu v „Na zaúčtovanie“.
 */
function juc_queue_payments() {
	global $wpdb;
	$st = juc_start_date();
	if ( ! $st || ! function_exists( 'jinv_table' ) ) {
		return array();
	}
	$e = juc_t( 'entries' );
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT p.*, d.number doc_number, d.kind doc_kind, d.customer_name FROM ' . jinv_table( 'payments' ) . ' p JOIN ' . jinv_table() . " d ON d.id = p.doc_id WHERE p.source <> 'bank' AND p.pay_date >= %s AND p.method NOT IN ('bank','cash','card','cod') AND d.status <> 'cancelled' AND NOT EXISTS (SELECT 1 FROM $e e WHERE e.source = 'jinv' AND e.source_type = 'payment' AND e.source_id = p.id AND e.status = 'posted') ORDER BY p.pay_date, p.id LIMIT 300", $st ), ARRAY_A ); // phpcs:ignore
}

/** Počet položiek „Na zaúčtovanie“ (v menu na každej stránke administrácie) – uložený na 5 minút. */
function juc_queue_count() {
	$n = get_transient( 'juc_queue_n' );
	if ( false === $n ) {
		$n = count( juc_queue_bank() ) + count( juc_queue_payments() ) + count( (array) get_option( 'juc_autopost_errors', array() ) );
		set_transient( 'juc_queue_n', $n, 5 * MINUTE_IN_SECONDS );
	}
	return (int) $n;
}

/** Zmena vo fronte (zaúčtovanie, beh, spárovanie) – počet sa nabudúce prepočíta. */
function juc_queue_count_reset() {
	delete_transient( 'juc_queue_n' );
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
	$pays = juc_queue_payments();
	if ( $pays ) :
		juc_accounts_datalist( 'juc-accs-pay' );
		?>
		<h2>Úhrady bez určenia účtu</h2>
		<p class="description">Úhrady zápočtom alebo „iným“ spôsobom (aj „Pôvodná úhrada“, ktorú J-Fakturácia doplní k staršej uhradenej faktúre) program nevie zaúčtovať sám. Zadajte účet, cez ktorý bola úhrada vyrovnaná (napr. 321 pri zápočte s dodávateľom, 211 pokladňa, 221 banka, 355 konateľ) – druhá strana (odberateľ 311, dodávateľ 321, preddavok 324) sa doplní podľa faktúry.</p>
		<table class="widefat striped juc-table juc-queue">
			<thead><tr><th>Dátum</th><th class="n">Suma €</th><th>Faktúra / spôsob</th><th>Zaúčtovať</th></tr></thead>
			<tbody>
			<?php foreach ( $pays as $p ) : ?>
				<tr>
					<td><?php echo esc_html( juc_date( $p['pay_date'] ) ); ?></td>
					<td class="n"><?php echo esc_html( juc_money( $p['amount'] ) ); ?></td>
					<td><?php echo esc_html( $p['doc_number'] . ( $p['customer_name'] ? ' – ' . $p['customer_name'] : '' ) ); ?><br><small><?php echo esc_html( trim( ( function_exists( 'jinv_pay_methods' ) ? ( jinv_pay_methods()[ $p['method'] ] ?? $p['method'] ) : $p['method'] ) . ' · ' . $p['note'], ' ·' ) ); ?></small></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-qform">
							<?php wp_nonce_field( 'juc_queue_pay_' . $p['id'] ); ?><input type="hidden" name="action" value="juc_queue_pay"><input type="hidden" name="pay" value="<?php echo (int) $p['id']; ?>">
							<input name="acc" list="juc-accs-pay" placeholder="Účet" class="juc-qacc" inputmode="numeric" required>
							<button class="button button-small">Zaúčtovať</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	endif;
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
					<td><?php echo esc_html( $t['counter_name'] ); ?><?php echo $t['vs'] ? ' · VS ' . esc_html( $t['vs'] ) : ''; ?><br><small><?php echo esc_html( trim( $t['message'] . ' · ' . $t['tx_type'], ' ·' ) ); ?></small>
						<?php if ( function_exists( 'juc_issues_for' ) ) : foreach ( juc_issues_for( 'banktx', $t['id'] ) as $iss ) : if ( 'missing' === $iss['kind'] ) { continue; } ?><br><a class="juc-chip juc-k-<?php echo esc_attr( $iss['kind'] ); ?>" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-issues', array( 'issue' => $iss['id'] ) ) ); ?>"><?php echo esc_html( $iss['title'] ); ?></a><?php endforeach; ?>
						<br><a class="juc-muted" href="<?php echo esc_url( juc_url( 'j-uctovnictvo-issues', array( 'new' => 1, 'link_type' => 'banktx', 'link_id' => $t['id'], 'back' => juc_url( 'j-uctovnictvo-queue' ) ) ) ); ?>">+ poznámka / úloha</a><?php endif; ?></td>
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
							<?php $rk = juc_bank_rule_key( $t ); if ( $rk ) : ?><label class="juc-remember" title="<?php echo esc_attr( 'Pravidlo: ' . ( 0 === strpos( $rk, 'n:' ) ? 'názov „' . substr( $rk, 2 ) . '“' : ( 0 === strpos( $rk, 't:' ) ? 'text „' . substr( $rk, 2 ) . '“' : 'účet ' . $rk ) ) ); ?>"><input type="checkbox" name="remember" value="1"> zapamätať</label><?php endif; ?>
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
	// Zámok zdroja + nová kontrola: dvojklik ani druhé okno nezaúčtujú pohyb dvakrát.
	$lock = juc_source_lock_name( 'bank', 'tx', $id );
	if ( ! juc_lock( $lock, 10 ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-queue' ), 'Pohyb sa práve účtuje – skúste to o chvíľu znova.' );
	}
	if ( juc_entry_by_source( 'bank', 'tx', $id ) ) {
		juc_unlock( $lock );
		juc_queue_count_reset();
		juc_back_err( juc_url( 'j-uctovnictvo-queue' ), 'Pohyb už je zaúčtovaný.' );
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
	juc_unlock( $lock );
	juc_queue_count_reset();
	if ( is_wp_error( $r ) ) {
		juc_back_err( juc_url( 'j-uctovnictvo-queue' ), $r->get_error_message() );
	}
	// Zapamätať: pravidlo pre ďalšie pohyby rovnakej protistrany / textu – a hneď zaúčtovať čakajúce pohyby, ktoré mu zodpovedajú.
	if ( ! empty( $_POST['remember'] ) && ( $rk = juc_bank_rule_key( $t ) ) ) { // phpcs:ignore
		$rules        = (array) juc_opt( 'bank_rules' );
		$rules[ $rk ] = $acc;
		juc_settings_update( array( 'bank_rules' => $rules ) );
		$more = 0;
		foreach ( juc_queue_bank() as $q ) {
			if ( juc_bank_rule( $q ) === $acc ) {
				$res   = juc_sync_source( 'bank', 'tx', (int) $q['id'], juc_build_banktx( $q ) );
				$more += in_array( $res, array( 'posted', 'updated' ), true ) ? 1 : 0;
			}
		}
		juc_queue_count_reset();
		set_transient( 'juc_err_' . get_current_user_id(), 'Doklad je zaúčtovaný a pravidlo uložené (Nastavenia → Pravidlá pre pohyby v banke).' . ( $more ? ' Podľa pravidla sa zaúčtovali aj ďalšie pohyby: ' . $more . '.' : '' ), 120 );
		wp_safe_redirect( juc_url( 'j-uctovnictvo-queue', array( 'juc_msg' => 'auto' ) ) );
		exit;
	}
	wp_safe_redirect( juc_url( 'j-uctovnictvo-queue', array( 'juc_msg' => 'posted' ) ) );
	exit;
} );

// Úhrada bez určenia účtu (zápočet, iné) – zaúčtovanie so zvoleným protiúčtom.
add_action( 'admin_post_juc_queue_pay', function () {
	global $wpdb;
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['pay'] ?? 0 );
	check_admin_referer( 'juc_queue_pay_' . $id );
	$back = juc_url( 'j-uctovnictvo-queue' );
	$acc  = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['acc'] ?? '' ) ); // phpcs:ignore
	$p    = function_exists( 'jinv_table' ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jinv_table( 'payments' ) . ' WHERE id = %d', $id ), ARRAY_A ) : null; // phpcs:ignore
	$d    = $p ? jinv_get( (int) $p['doc_id'] ) : null;
	if ( ! $p || ! $d ) {
		juc_back_err( $back, 'Úhrada neexistuje.' );
	}
	if ( strlen( $acc ) < 3 ) {
		juc_back_err( $back, 'Zadajte účet, cez ktorý bola úhrada vyrovnaná.' );
	}
	$lock = juc_source_lock_name( 'jinv', 'payment', $id );
	if ( ! juc_lock( $lock, 10 ) ) {
		juc_back_err( $back, 'Úhrada sa práve účtuje – skúste to o chvíľu znova.' );
	}
	if ( juc_entry_by_source( 'jinv', 'payment', $id ) ) {
		juc_unlock( $lock );
		juc_queue_count_reset();
		juc_back_err( $back, 'Úhrada už je zaúčtovaná.' );
	}
	$b = juc_build_payment( $p, $d, $acc );
	if ( ! empty( $b['head']['adv_key'] ) ) {
		juc_advances_touch();
	}
	unset( $b['head']['adv_key'] );
	$r = juc_post( $b['head'] + array( 'source' => 'jinv', 'source_type' => 'payment', 'source_id' => $id, 'source_hash' => 'manual' ), $b['lines'] );
	juc_unlock( $lock );
	juc_queue_count_reset();
	if ( is_wp_error( $r ) ) {
		juc_back_err( $back, $r->get_error_message() );
	}
	juc_autopost_mark_doc( (int) $d['id'] ); // zúčtovanie preddavku pri faktúre
	wp_safe_redirect( add_query_arg( 'juc_msg', 'posted', $back ) );
	exit;
} );
