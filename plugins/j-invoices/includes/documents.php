<?php
/**
 * Doklady k objednávkam: faktúra (issued), zálohová faktúra (proforma) a dobropis (creditnote).
 *
 * Číslovanie (Nastavenia → Doklady a číslovanie):
 *  - „order“  – číslo dokladu = číslo objednávky (napr. 220260244), ako pri doplnku Woo Faktura; druhý doklad toho
 *               istého druhu k objednávke (napr. ďalší dobropis) dostane príponu -2, -3 …
 *  - „series“ – vlastný ročný rad pre každý druh dokladu podľa formátu ({RRRR}{nnn} → 2026001, ZF{RRRR}{nnn}, D{RRRR}{nnn}).
 *
 * Postup (Nastavenia → Doklady a číslovanie → Postup fakturácie; kým beží Woo Faktura, automatika je vypnutá):
 *  - manuálny režim: ostrú faktúru vystaví obsluha tlačidlom; zálohová faktúra pri prevode môže vznikať automaticky;
 *  - automatický režim: prevod na účet – zálohová faktúra pri objednávke, ostrá po zaplatení alebo pri vybavení
 *    (podľa nastavenia); dobierka, osobný odber a ostatné – ostrá faktúra pri vybavení;
 *  - „vybavená“ = stav z nastavenia (napr. Vybavené a odoslané), predvolene Vybavené (completed);
 *  - Pregenerovať: faktúra sa vytvorí znova z aktuálnej objednávky – s novým číslom (pôvodná sa stornuje)
 *    alebo s pôvodným číslom; dátum vystavenia dnešný alebo pôvodný.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Druhy dokladov: kľúč => [názov, meta v objednávke]. */
function jinv_kinds() {
	return array(
		'issued'     => array( 'Faktúra', '_jinv_invoice' ),
		'proforma'   => array( 'Zálohová faktúra', '_jinv_proforma' ),
		'creditnote' => array( 'Dobropis', '_jinv_creditnote' ),
	);
}

function jinv_kind_label( $kind ) {
	$k = jinv_kinds();
	return isset( $k[ $kind ] ) ? $k[ $kind ][0] : $kind;
}

/** Spôsoby platby prevodom na účet (zálohová faktúra, faktúra po zaplatení). */
function jinv_transfer_methods() {
	$list = array_filter( array_map( 'trim', explode( ',', (string) jinv_opt( 'transfer_methods' ) ) ) );
	return apply_filters( 'jinv_transfer_methods', $list ? $list : array( 'bacs' ) );
}

/** Stav objednávky, ktorý znamená „vybavená“ (bez wc-). */
function jinv_fulfil_status() {
	$s = preg_replace( '/^wc-/', '', sanitize_key( (string) jinv_opt( 'fulfil_status' ) ) );
	return '' !== $s ? $s : 'completed';
}

/** Je objednávka vybavená (stav z nastavenia alebo štandardné Vybavené)? */
function jinv_is_fulfilled( WC_Order $o ) {
	return $o->has_status( array_unique( array( 'completed', jinv_fulfil_status() ) ) );
}

function jinv_is_transfer_order( WC_Order $o ) {
	return in_array( $o->get_payment_method(), jinv_transfer_methods(), true );
}

/** Interné poradie pre jedinečný kľúč z čísla dokladu (číslice; dlhé čísla kontrolný súčet). */
function jinv_number_seq( $number ) {
	$digits = preg_replace( '/\D/', '', (string) $number );
	if ( '' !== $digits && strlen( $digits ) <= 9 ) {
		return (int) $digits;
	}
	return (int) sprintf( '%u', crc32( (string) $number ) );
}

function jinv_number_taken( $kind, $year, $seq, $number ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . ' WHERE kind = %s AND ( ( year = %d AND seq = %d ) OR number = %s ) LIMIT 1', $kind, $year, $seq, $number ) ); // phpcs:ignore
}

/** Formát čísla pre druh dokladu (vlastný rad). */
function jinv_kind_format( $kind ) {
	$key = 'issued' === $kind ? 'format' : 'format_' . $kind;
	$f   = (string) jinv_opt( $key );
	if ( false === strpos( $f, '{n' ) ) {
		$f = 'issued' === $kind ? '{RRRR}{nnn}' : ( 'proforma' === $kind ? 'ZF{RRRR}{nnn}' : 'D{RRRR}{nnn}' );
	}
	return $f;
}

/**
 * Ďalšie voľné číslo dokladu: [ číslo, poradie ].
 * Volá sa v cykle s $try (0, 1, 2…) – pri súbežnom vystavení sa skúsi ďalšie číslo.
 */
function jinv_next_number( $kind, $o, $year, $try = 0 ) {
	global $wpdb;
	if ( 'order' === jinv_opt( 'numbering' ) && $o instanceof WC_Order ) {
		$base = (string) $o->get_order_number();
		for ( $n = 1 + $try; $n < 100; $n++ ) {
			$number = 1 === $n ? $base : $base . '-' . $n;
			$seq    = 1 === $n ? jinv_number_seq( $number ) : (int) sprintf( '%u', crc32( $kind . '|' . $number ) );
			if ( ! jinv_number_taken( $kind, $year, $seq, $number ) ) {
				return array( $number, $seq );
			}
		}
	}
	// Vlastný ročný rad: len natívne doklady s malým poradím (prevzaté a „podľa objednávky“ majú veľké čísla).
	$max = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jinv_table() . " WHERE kind = %s AND source = '' AND year = %d AND seq < 1000000", $kind, $year ) ); // phpcs:ignore
	$seq = $max + 1;
	// Pokračovanie v rade (napr. podľa počítadla Woo Faktury) – len v roku, keď bolo číslo zadané.
	$start = (int) jinv_opt( 'next_' . $kind );
	if ( $start > $seq && (int) jinv_opt( 'next_year' ) === (int) $year ) {
		$seq = $start;
	}
	while ( jinv_number_taken( $kind, $year, $seq, jinv_format_number( $year, $seq, jinv_kind_format( $kind ) ) ) ) {
		$seq++;
	}
	return array( jinv_format_number( $year, $seq, jinv_kind_format( $kind ) ), $seq );
}

/** Variabilný symbol dokladu = číslo objednávky (len číslice, max. 10) – platby sa párujú podľa objednávky; inak číslice čísla dokladu. */
function jinv_doc_vs( $o, $number ) {
	$vs = $o instanceof WC_Order ? preg_replace( '/\D/', '', (string) $o->get_order_number() ) : '';
	if ( '' === $vs ) {
		$vs = preg_replace( '/\D/', '', (string) $number );
	}
	return substr( $vs, 0, 10 );
}

/** Údaje dodávateľa z nastavení (snímka do dokladu). */
function jinv_supplier_snapshot() {
	return array_intersect_key( jinv_settings(), array_flip( array( 'company', 'street', 'zip', 'city', 'country', 'ico', 'dic', 'icdph', 'register', 'vat_payer', 'vat_note', 'iban', 'swift', 'bank', 'email', 'phone', 'web', 'issued_by', 'footer' ) ) );
}

/** Položky dobropisu z vrátenia WooCommerce (záporné sumy) alebo celý obrat faktúry. */
function jinv_creditnote_items( WC_Order $o, $refund, $invoice ) {
	$items = array();
	if ( $refund instanceof WC_Order_Refund ) {
		foreach ( $refund->get_items() as $it ) {
			$qty     = abs( (float) $it->get_quantity() );
			$total   = (float) $it->get_total() + (float) $it->get_total_tax();
			$q       = $qty > 0 ? $qty : 1;
			$items[] = array( 'name' => $it->get_name(), 'sku' => '', 'qty' => -1 * $q, 'unit' => 'ks', 'price' => round( abs( $total ) / $q, 2 ), 'total' => round( -1 * abs( $total ), 2 ) );
		}
		foreach ( array_merge( $refund->get_shipping_methods(), $refund->get_fees() ) as $it ) {
			$total = (float) $it->get_total() + (float) $it->get_total_tax();
			if ( abs( $total ) > 0 ) {
				$items[] = array( 'name' => ( $it instanceof WC_Order_Item_Shipping ? 'Doprava: ' : '' ) . $it->get_name(), 'sku' => '', 'qty' => -1, 'unit' => '', 'price' => round( abs( $total ), 2 ), 'total' => round( -1 * abs( $total ), 2 ) );
			}
		}
		$sum  = array_sum( wp_list_pluck( $items, 'total' ) );
		$diff = round( -1 * abs( (float) $refund->get_amount() ) - $sum, 2 );
		if ( abs( $diff ) >= 0.01 ) { // vrátenie len sumou (bez položiek) alebo rozdiel
			// Znamienko množstva podľa rozdielu (vrátená suma menšia ako položky → kladný vyrovnávací riadok).
			$items[] = array( 'name' => 'Vrátenie platby' . ( $refund->get_reason() ? ' – ' . $refund->get_reason() : '' ), 'sku' => '', 'qty' => $diff < 0 ? -1 : 1, 'unit' => '', 'price' => abs( $diff ), 'total' => $diff );
		}
		return $items;
	}
	foreach ( (array) ( $invoice['data']['items'] ?? array() ) as $it ) {
		if ( ! empty( $it['info'] ) ) {
			continue;
		}
		$it['qty']   = -1 * abs( (float) $it['qty'] );
		$it['total'] = -1 * abs( (float) $it['total'] );
		$items[]     = $it;
	}
	return $items;
}

/** Suma faktúry, ktorú ešte možno dobropisovať (faktúra mínus platné dobropisy k objednávke). */
function jinv_credit_remaining( $order_id, $invoice = null ) {
	global $wpdb;
	$invoice = $invoice ? $invoice : jinv_by_order( $order_id, 'issued' );
	if ( ! $invoice ) {
		return 0.0;
	}
	$credited = 0.0;
	$rows     = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT total, data FROM ' . jinv_table() . " WHERE order_id = %d AND kind = 'creditnote' AND status <> 'cancelled'", $order_id ), ARRAY_A ); // phpcs:ignore
	foreach ( $rows as $r ) {
		$d = json_decode( (string) $r['data'], true );
		// Rátajú sa dobropisy k tejto faktúre (aj staršie bez čísla faktúry, napr. prevzaté z Woo Faktura).
		if ( empty( $d['invoice'] ) || (string) $d['invoice'] === (string) $invoice['number'] ) {
			$credited += abs( (float) $r['total'] );
		}
	}
	return max( 0.0, round( abs( (float) $invoice['total'] ) - $credited, 2 ) );
}

/** Najnovšie vrátenie peňazí objednávky, ku ktorému ešte nie je dobropis (alebo null). */
function jinv_uncredited_refund( WC_Order $o ) {
	global $wpdb;
	$done = array();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT data FROM ' . jinv_table() . " WHERE order_id = %d AND kind = 'creditnote' AND status <> 'cancelled'", $o->get_id() ) ) as $json ) { // phpcs:ignore
		$d = json_decode( (string) $json, true );
		if ( ! empty( $d['refund_id'] ) ) {
			$done[] = (int) $d['refund_id'];
		}
	}
	foreach ( $o->get_refunds() as $r ) {
		if ( ! in_array( $r->get_id(), $done, true ) ) {
			return $r;
		}
	}
	return null;
}

/**
 * Vystaví doklad k objednávke. Vráti ID dokladu alebo WP_Error.
 * $opts: refund_id (dobropis z vrátenia), note, send (bool|null – null = podľa nastavení).
 * Faktúra a zálohová faktúra sú k objednávke len jedna platná – ak existuje, vráti sa jej ID.
 */
function jinv_create_doc( $order, $kind = 'issued', $opts = array() ) {
	global $wpdb;
	$o = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( ! $o || ! isset( jinv_kinds()[ $kind ] ) ) {
		return new WP_Error( 'jinv_no_order', 'Objednávka neexistuje.' );
	}
	// Doklad z Woo Faktura, ktorý ešte nie je prevzatý (vystavený po poslednom importe) – prevezme sa, nevystaví sa druhý.
	if ( function_exists( 'jinv_wf_process_order' ) ) {
		$wf_types = array( 'issued' => 'invoice', 'proforma' => 'proforma', 'creditnote' => 'creditnote' );
		if ( '' !== trim( (string) $o->get_meta( 'wc_wi_' . $wf_types[ $kind ] . '_id' ) ) && ! jinv_by_order( $o->get_id(), $kind ) ) {
			jinv_wf_process_order( $o->get_id(), true );
			$imp = jinv_by_order( $o->get_id(), $kind );
			if ( $imp && 'creditnote' !== $kind ) {
				return (int) $imp['id'];
			}
		}
	}
	// Zámok na objednávku – dve súbežné požiadavky (napr. platobná brána a klik v administrácii) nevystavia dva doklady.
	$lock   = 'jinv_doc_' . $o->get_id();
	$got    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $lock ) );
	$locked = 1 === (int) $got;
	// NULL = databáza zámky nepodporuje – pokračuje sa bez zámku (dvojitý doklad zastaví unikátny kľúč čísla).
	if ( ! $locked && null !== $got ) {
		return new WP_Error( 'jinv_locked', 'K objednávke sa práve vystavuje iný doklad. Skúste to znova o chvíľu.' );
	}
	try {
		return jinv_create_doc_locked( $o, $kind, $opts );
	} finally {
		if ( $locked ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}

/** Vystavenie dokladu (volá sa pod zámkom objednávky). */
function jinv_create_doc_locked( WC_Order $o, $kind, $opts ) {
	global $wpdb;
	if ( 'creditnote' !== $kind ) {
		$existing = jinv_by_order( $o->get_id(), $kind );
		if ( $existing ) {
			return (int) $existing['id'];
		}
	}
	$invoice = jinv_by_order( $o->get_id(), 'issued' );
	if ( 'creditnote' === $kind && ! $invoice ) {
		return new WP_Error( 'jinv_no_invoice', 'Dobropis sa vystavuje k faktúre – objednávka zatiaľ faktúru nemá.' );
	}
	$s       = jinv_settings();
	// Pregenerovanie s pôvodným dátumom: číslo z radu roka pôvodného dokladu (rad ostane v poradí dátumov).
	$today   = ! empty( $opts['issue_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $opts['issue_date'] ) ? $opts['issue_date'] : current_time( 'Y-m-d' );
	$year    = (int) substr( $today, 0, 4 );
	$cod     = jinv_is_cod_order( $o );
	// Dobierka: vybavená (odoslaná) objednávka ešte nie je zaplatená – faktúra je neuhradená, platí sa pri prevzatí.
	// Prevod odoslaný pred zaplatením (Čaká na platbu → Vybavená): faktúra je neuhradená, kým nepríde platba.
	$paid    = $cod || jinv_shipped_unpaid( $o ) ? false : ( $o->is_paid() || jinv_is_fulfilled( $o ) );
	$refund  = ! empty( $opts['refund_id'] ) ? wc_get_order( (int) $opts['refund_id'] ) : null;
	if ( 'creditnote' === $kind && ! $refund ) {
		$refund = jinv_uncredited_refund( $o );
	}
	$remaining = 'creditnote' === $kind ? jinv_credit_remaining( $o->get_id(), $invoice ) : 0.0;
	if ( 'creditnote' === $kind && $remaining < 0.01 ) {
		return new WP_Error( 'jinv_fully_credited', 'Faktúra ' . $invoice['number'] . ' je už celá dobropisovaná – ďalší dobropis sa nevystaví.' );
	}
	$data    = array(
		'supplier' => jinv_supplier_snapshot(),
		'customer' => jinv_customer_from_order( $o ),
		'items'    => 'creditnote' === $kind ? jinv_creditnote_items( $o, $refund, $invoice ) : jinv_items_from_order( $o ),
		'payment'  => $o->get_payment_method_title(),
		'pay_code' => $o->get_payment_method(),
		'order_no' => $o->get_order_number(),
		'note'     => isset( $opts['note'] ) ? sanitize_textarea_field( $opts['note'] ) : '',
	);
	$status = 'issued';
	$total  = round( (float) $o->get_total(), 2 );
	$supply = $o->get_date_completed() ? $o->get_date_completed()->date_i18n( 'Y-m-d' ) : ( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'Y-m-d' ) : $today );
	$due    = gmdate( 'Y-m-d', strtotime( $today . ' +' . (int) $s['due_days'] . ' days' ) );
	if ( 'issued' === $kind ) {
		$status   = $paid ? 'paid' : 'issued';
		$proforma = jinv_by_order( $o->get_id(), 'proforma' );
		if ( $proforma ) {
			$data['proforma'] = $proforma['number'];
		}
	} elseif ( 'proforma' === $kind ) {
		$status = $paid ? 'paid' : 'issued';
		$supply = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'Y-m-d' ) : $today; // na zálohovej faktúre „Objednávka zo dňa“
	} else {
		$sum = abs( round( array_sum( wp_list_pluck( $data['items'], 'total' ) ), 2 ) );
		if ( $refund instanceof WC_Order_Refund ) {
			if ( $sum > $remaining + 0.005 ) {
				return new WP_Error( 'jinv_credit_over', sprintf( 'Dobropis na %s by prekročil zostávajúcu sumu faktúry %s (%s). Skontrolujte vystavené dobropisy.', wc_format_decimal( $sum, 2 ), $invoice['number'], wc_format_decimal( $remaining, 2 ) ) );
			}
		} elseif ( $sum > $remaining + 0.005 ) {
			// Časť faktúry je už dobropisovaná – dobropis len na zostatok, nie znova na celú faktúru.
			$data['items'] = array( array( 'name' => 'Dobropis – zostávajúca suma faktúry ' . $invoice['number'], 'sku' => '', 'qty' => -1, 'unit' => '', 'price' => $remaining, 'total' => -1 * $remaining ) );
		}
		$data['invoice'] = $invoice['number'];
		$data['reason']  = $refund instanceof WC_Order_Refund ? (string) $refund->get_reason() : '';
		$total           = round( array_sum( wp_list_pluck( $data['items'], 'total' ) ), 2 );
		$supply          = $today;
		$due             = $today;
		$status          = $refund instanceof WC_Order_Refund ? 'paid' : 'issued'; // peniaze vrátené cez WooCommerce
		if ( $refund instanceof WC_Order_Refund ) {
			$data['refund_id'] = $refund->get_id();
		}
	}
	for ( $try = 0; $try < 5; $try++ ) {
		list( $num, $seq ) = jinv_next_number( $kind, $o, $year, $try );
		$ok = $wpdb->insert( jinv_table(), array(
			'kind'           => $kind,
			'number'         => $num,
			'year'           => $year,
			'seq'            => $seq,
			'order_id'       => $o->get_id(),
			'status'         => $status,
			'issue_date'     => $today,
			'supply_date'    => $supply,
			'due_date'       => $due,
			'vs'             => jinv_doc_vs( $o, $num ),
			'currency'       => $o->get_currency(),
			'total'          => $total,
			'customer_name'  => $data['customer']['company'] ? $data['customer']['company'] : $data['customer']['name'],
			'customer_email' => $data['customer']['email'],
			'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'paid_at'        => 'paid' === $status ? current_time( 'mysql' ) : null,
			'created_at'     => current_time( 'mysql' ),
		) );
		if ( ! $ok && 'creditnote' !== $kind && ( $again = jinv_by_order( $o->get_id(), $kind ) ) ) {
			return (int) $again['id']; // medzičasom ho vystavila iná požiadavka
		}
		if ( $ok ) {
			$id = (int) $wpdb->insert_id;
			$o->update_meta_data( jinv_kinds()[ $kind ][1], $num );
			$o->save();
			$notes = array( 'issued' => 'vystavená faktúra', 'proforma' => 'vystavená zálohová faktúra', 'creditnote' => 'vystavený dobropis' );
			$o->add_order_note( sprintf( 'J-Fakturácia: %s č. %s.', $notes[ $kind ], $num ) );
			if ( 'issued' === $kind && $paid && ! empty( $proforma ) && 'issued' === $proforma['status'] ) {
				jinv_update( $proforma['id'], array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
			}
			do_action( 'jinv_document_created', $id, $kind, $o, $opts );
			if ( 'issued' === $kind ) {
				do_action( 'jinv_invoice_created', $id, $o ); // spätná kompatibilita
			}
			return $id;
		}
	}
	return new WP_Error( 'jinv_insert', 'Doklad sa nepodarilo uložiť: ' . $wpdb->last_error );
}

/**
 * Pregenerovanie faktúry alebo zálohovej faktúry z aktuálnej objednávky (zmenené položky, adresa, doprava…).
 * Podľa nastavenia: nové číslo (pôvodný doklad sa stornuje) alebo pôvodné číslo; dátum vystavenia dnes alebo pôvodný.
 * Vráti ID (nového) dokladu alebo WP_Error.
 */
function jinv_regenerate_doc( $id ) {
	global $wpdb;
	$inv = jinv_get( $id );
	if ( ! $inv || ! in_array( $inv['kind'], array( 'issued', 'proforma' ), true ) ) {
		return new WP_Error( 'jinv_regen', 'Pregenerovať sa dá faktúra alebo zálohová faktúra.' );
	}
	if ( 'cancelled' === $inv['status'] ) {
		return new WP_Error( 'jinv_regen', 'Stornovaný doklad sa nedá pregenerovať.' );
	}
	$o = $inv['order_id'] ? wc_get_order( (int) $inv['order_id'] ) : null;
	if ( ! $o ) {
		return new WP_Error( 'jinv_regen', 'Doklad nemá objednávku – pregenerovať sa dá len doklad k objednávke.' );
	}
	if ( 'issued' === $inv['kind'] && jinv_credit_remaining( $o->get_id(), $inv ) < abs( (float) $inv['total'] ) - 0.005 ) {
		return new WP_Error( 'jinv_regen', 'K faktúre je vystavený dobropis – namiesto pregenerovania vystavte ďalší dobropis.' );
	}
	$lock = 'jinv_doc_' . $o->get_id();
	if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $lock ) ) ) {
		return new WP_Error( 'jinv_locked', 'K objednávke sa práve vystavuje iný doklad. Skúste to znova o chvíľu.' );
	}
	try {
		$kind  = $inv['kind'];
		$label = mb_strtolower( jinv_kind_label( $kind ) );
		if ( 'new' === jinv_opt( 'regen_number' ) ) {
			// Nové číslo: pôvodný doklad sa stornuje (ostáva v evidencii), vystaví sa nový.
			jinv_update( $inv['id'], array( 'status' => 'cancelled' ) );
			$meta = jinv_kinds()[ $kind ][1];
			if ( (string) $o->get_meta( $meta ) === (string) $inv['number'] ) {
				$o->delete_meta_data( $meta );
				$o->save();
			}
			$new = jinv_create_doc_locked( $o, $kind, array( 'send' => false ) + ( 'keep' === jinv_opt( 'regen_date' ) ? array( 'issue_date' => $inv['issue_date'] ) : array() ) );
			if ( is_wp_error( $new ) ) {
				jinv_update( $inv['id'], array( 'status' => $inv['status'] ) ); // vrátiť späť
				$o->update_meta_data( $meta, $inv['number'] );
				$o->save();
				return $new;
			}
			$doc = jinv_get( $new );
			if ( 'keep' === jinv_opt( 'regen_date' ) && $doc ) {
				$due = gmdate( 'Y-m-d', strtotime( $inv['issue_date'] . ' +' . (int) jinv_opt( 'due_days' ) . ' days' ) );
				jinv_update( $new, array( 'issue_date' => $inv['issue_date'], 'due_date' => $due ) );
			}
			// Úhrady (banka, karta, ručné) sa presunú na nový doklad – stornovaný doklad ich nemá a pohyb v banke ostane spárovaný správne.
			$wpdb->update( jinv_table( 'payments' ), array( 'doc_id' => (int) $new ), array( 'doc_id' => (int) $inv['id'] ) );
			$wpdb->update( jinv_table( 'banktx' ), array( 'doc_id' => (int) $new ), array( 'doc_id' => (int) $inv['id'] ) );
			jinv_sync_paid( (int) $new );
			$doc = jinv_get( $new );
			if ( 'paid' === $inv['status'] && $doc && 'paid' !== $doc['status'] ) {
				jinv_update( $new, array( 'status' => 'paid', 'paid_at' => $inv['paid_at'] ? $inv['paid_at'] : current_time( 'mysql' ) ) );
			}
			$o->add_order_note( sprintf( 'J-Fakturácia: %s č. %s pregenerovaná ako č. %s (pôvodná stornovaná).', $label, $inv['number'], $doc ? $doc['number'] : '' ) );
			do_action( 'jinv_document_regenerated', $new, $inv['id'], $o );
			return (int) $new;
		}
		// Pôvodné číslo: údaje dokladu sa prepíšu podľa aktuálnej objednávky.
		$data             = (array) $inv['data'];
		$data['supplier'] = jinv_supplier_snapshot();
		$data['customer'] = jinv_customer_from_order( $o );
		$data['items']    = jinv_items_from_order( $o );
		$data['payment']  = $o->get_payment_method_title();
		$data['pay_code'] = $o->get_payment_method();
		$data['order_no'] = $o->get_order_number();
		$data['regenerated'] = current_time( 'mysql' );
		if ( 'issued' === $kind ) {
			$pf = jinv_by_order( $o->get_id(), 'proforma' );
			if ( $pf ) {
				$data['proforma'] = $pf['number'];
			}
		}
		$today  = current_time( 'Y-m-d' );
		$issue  = 'keep' === jinv_opt( 'regen_date' ) ? $inv['issue_date'] : $today;
		$supply = 'proforma' === $kind ? $inv['supply_date'] : ( $o->get_date_completed() ? $o->get_date_completed()->date_i18n( 'Y-m-d' ) : ( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'Y-m-d' ) : $issue ) );
		$cod    = jinv_is_cod_order( $o );
		$paid   = 'paid' === $inv['status'] || ( ! $cod && ( $o->is_paid() || ( 'issued' === $kind && jinv_is_fulfilled( $o ) ) ) );
		$fields = array(
			'issue_date'     => $issue,
			'supply_date'    => $supply,
			'due_date'       => gmdate( 'Y-m-d', strtotime( $issue . ' +' . (int) jinv_opt( 'due_days' ) . ' days' ) ),
			'vs'             => jinv_doc_vs( $o, $inv['number'] ),
			'currency'       => $o->get_currency(),
			'total'          => round( (float) $o->get_total(), 2 ),
			'customer_name'  => $data['customer']['company'] ? $data['customer']['company'] : $data['customer']['name'],
			'customer_email' => $data['customer']['email'],
			'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'status'         => $paid ? 'paid' : 'issued',
			'paid_at'        => $paid ? ( $inv['paid_at'] ? $inv['paid_at'] : current_time( 'mysql' ) ) : null,
			'source'         => '',
			'file'           => '',
		);
		jinv_update( $inv['id'], $fields );
		$o->add_order_note( sprintf( 'J-Fakturácia: %s č. %s pregenerovaná podľa aktuálnej objednávky.', $label, $inv['number'] ) );
		do_action( 'jinv_document_regenerated', (int) $inv['id'], (int) $inv['id'], $o );
		return (int) $inv['id'];
	} finally {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/** Spätná kompatibilita: faktúra z objednávky. */
function jinv_create_from_order( $order ) {
	return jinv_create_doc( $order, 'issued' );
}

/** Automatika je zapnutá (nastavenie) a nebeží Woo Faktura (režim prechodu). */
function jinv_auto_on( $key = 'auto' ) {
	return jinv_opt( $key ) && ! jinv_woo_faktura_active();
}

/** Zapíše chybu automatiky do denníka WooCommerce a do poznámky objednávky – objednávka sa nikdy nezablokuje. */
function jinv_log_error( $order, \Throwable $e, $what ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->error( $what . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')', array( 'source' => 'j-invoices' ) );
	}
	if ( $order instanceof WC_Order ) {
		$order->add_order_note( 'J-Fakturácia: chyba – ' . $what . ': ' . $e->getMessage() );
	}
}

/** Prevod odoslaný pred zaplatením (Pozastavené / Čaká na platbu → vybavená) a bez zaznamenanej platby? */
function jinv_shipped_unpaid( WC_Order $o ) {
	return jinv_is_transfer_order( $o ) && $o->get_meta( '_jinv_shipped_unpaid' ) && ! $o->get_meta( '_jinv_paid_later' );
}

// Prevod: vybavenie priamo z „čaká na platbu“ (bez platby) – poznačiť, aby faktúra nebola „uhradená“.
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || ! jinv_is_transfer_order( $order ) ) {
		return;
	}
	$fulfilled = in_array( $to, array_unique( array( 'completed', jinv_fulfil_status() ) ), true );
	if ( $fulfilled && in_array( $from, array( 'on-hold', 'pending' ), true ) ) {
		$order->update_meta_data( '_jinv_shipped_unpaid', current_time( 'mysql' ) );
		$order->save_meta_data();
		$order->add_order_note( 'J-Fakturácia: objednávka s prevodom vybavená pred zaplatením – faktúra bude neuhradená, kým nepríde platba.' );
	}
}, 5, 4 );

// Platba neskôr (banka, karta) – faktúra sa môže uhradiť.
add_action( 'jinv_payment_added', function ( $pay_id, $doc_id ) {
	$d = jinv_get( (int) $doc_id );
	if ( $d && (int) $d['order_id'] && ( $o = wc_get_order( (int) $d['order_id'] ) ) && $o->get_meta( '_jinv_shipped_unpaid' ) ) {
		$o->update_meta_data( '_jinv_paid_later', current_time( 'mysql' ) );
		$o->save_meta_data();
	}
}, 10, 2 );

add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || jinv_woo_faktura_active() ) {
		return; // režim prechodu: doklady aj ich stavy rieši Woo Faktura
	}
	try {
		$transfer = jinv_is_transfer_order( $order );
		// Zálohová faktúra pri prevode: objednávka čaká na platbu.
		$note = function ( $r, $what ) use ( $order ) {
			if ( is_wp_error( $r ) ) {
				$order->add_order_note( 'J-Fakturácia: ' . $what . ' sa nevystavila – ' . $r->get_error_message() . ' Vystavte ju tlačidlom v boxe Faktúry.' );
			}
		};
		if ( $transfer && jinv_auto_on( 'auto_proforma' ) && 'on-hold' === $to && ! $order->is_paid() && ! jinv_by_order( $order_id, 'issued' ) ) {
			$note( jinv_create_doc( $order, 'proforma' ), 'zálohová faktúra' );
		}
		if ( jinv_auto_on( 'auto' ) ) {
			$fulfilled = in_array( $to, array_unique( array( 'completed', jinv_fulfil_status() ) ), true );
			$paid_now  = ( 'processing' === $to || $fulfilled ) && $order->is_paid();
			$on_paid   = 'paid' === jinv_opt( 'transfer_when' );
			if ( $fulfilled || ( $transfer && $on_paid && $paid_now ) ) {
				$note( jinv_create_doc( $order, 'issued' ), 'faktúra' );
			}
		}
		// Objednávka zrušená: zálohová faktúra sa stornuje (faktúru rieši dobropis).
		if ( in_array( $to, array( 'cancelled', 'failed' ), true ) ) {
			$pf = jinv_by_order( $order_id, 'proforma' );
			if ( $pf && 'issued' === $pf['status'] ) {
				jinv_update( $pf['id'], array( 'status' => 'cancelled' ) );
				$order->add_order_note( sprintf( 'J-Fakturácia: zálohová faktúra č. %s stornovaná (objednávka zrušená).', $pf['number'] ) );
			}
		}
	} catch ( \Throwable $e ) {
		jinv_log_error( $order, $e, 'automatické vystavenie dokladu' );
	}
}, 20, 4 );

// Objednávka zaplatená alebo vybavená → faktúra aj zálohová faktúra uhradené.
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || jinv_woo_faktura_active() || ! ( in_array( $to, array_unique( array( 'completed', jinv_fulfil_status() ) ), true ) || ( 'processing' === $to && $order->is_paid() ) ) ) {
		return;
	}
	if ( jinv_shipped_unpaid( $order ) ) {
		return; // prevod odoslaný pred zaplatením – doklady sa uhradia až platbou (banka, ručne)
	}
	foreach ( array( 'issued', 'proforma' ) as $kind ) {
		$d = jinv_by_order( $order_id, $kind );
		// Dobierka: faktúra ostáva neuhradená aj po vybavení (zákazník platí pri prevzatí); zálohová áno.
		if ( $d && 'issued' === $d['status'] && ! ( 'issued' === $kind && jinv_is_cod_order( $order ) ) ) {
			jinv_update( $d['id'], array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
		}
	}
}, 30, 4 );

// Vrátenie peňazí vo WooCommerce → dobropis (ak je zapnuté a objednávka má faktúru).
add_action( 'woocommerce_order_refunded', function ( $order_id, $refund_id ) {
	if ( jinv_auto_on( 'auto_creditnote' ) && jinv_by_order( $order_id, 'issued' ) ) {
		try {
			$r = jinv_create_doc( $order_id, 'creditnote', array( 'refund_id' => $refund_id ) );
			if ( is_wp_error( $r ) && ( $o = wc_get_order( $order_id ) ) ) {
				$o->add_order_note( 'J-Fakturácia: dobropis k vráteniu sa nevystavil – ' . $r->get_error_message() );
			}
		} catch ( \Throwable $e ) {
			jinv_log_error( wc_get_order( $order_id ), $e, 'dobropis pri vrátení' );
		}
	}
}, 20, 2 );

/** Hromadné vystavenie faktúr v zozname objednávok WooCommerce (klasický aj HPOS). */
foreach ( array( 'bulk_actions-edit-shop_order', 'bulk_actions-woocommerce_page_wc-orders' ) as $jinv_hook ) {
	add_filter( $jinv_hook, function ( $actions ) {
		$actions['jinv_bulk_invoice'] = 'Vystaviť faktúry (J-Fakturácia)';
		return $actions;
	} );
}
foreach ( array( 'handle_bulk_actions-edit-shop_order', 'handle_bulk_actions-woocommerce_page_wc-orders' ) as $jinv_hook ) {
	add_filter( $jinv_hook, function ( $redirect, $action, $ids ) {
		if ( 'jinv_bulk_invoice' !== $action || ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect;
		}
		if ( jinv_woo_faktura_active() ) {
			return add_query_arg( 'jinv_bulk', 'wf', $redirect ); // režim prechodu – faktúry vystavuje Woo Faktura
		}
		$n = 0;
		foreach ( (array) $ids as $id ) {
			$o = wc_get_order( (int) $id );
			// Len platné objednávky; bez e-mailu zákazníkovi (hromadné dopĺňanie dokladov).
			if ( ! $o || $o->has_status( array( 'cancelled', 'failed', 'pending', 'refunded', 'checkout-draft' ) ) || jinv_by_order( $o->get_id(), 'issued' ) ) {
				continue;
			}
			try {
				$r = jinv_create_doc( $o, 'issued', array( 'send' => false ) );
				if ( ! is_wp_error( $r ) ) {
					$n++;
				}
			} catch ( \Throwable $e ) {
				jinv_log_error( $o, $e, 'hromadné vystavenie' );
			}
		}
		return add_query_arg( 'jinv_bulk', $n, $redirect );
	}, 10, 3 );
}
add_action( 'admin_notices', function () {
	if ( isset( $_GET['jinv_bulk'] ) ) { // phpcs:ignore
		if ( 'wf' === $_GET['jinv_bulk'] ) { // phpcs:ignore
			echo '<div class="notice notice-warning is-dismissible"><p>J-Fakturácia: kým beží Woo Faktura, hromadné vystavenie je vypnuté.</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>J-Fakturácia: vystavené faktúry – ' . (int) $_GET['jinv_bulk'] . ' (bez e-mailu zákazníkom; zrušené a nezaplatené objednávky sa preskočili).</p></div>'; // phpcs:ignore
		}
	}
} );

/* ---------------------------------------------------------------- Dobierka, zrušená objednávka, vrátenie pred faktúrou */

// Dobierka: Packeta doručila zásielku → zákazník zaplatil pri prevzatí → faktúra uhradená.
add_action( 'jdp_status_changed', function ( $order, $code ) {
	if ( 7 !== (int) $code || ! jinv_opt( 'cod_paid_delivered' ) || ! $order instanceof WC_Order || ! jinv_is_cod_order( $order ) ) {
		return;
	}
	$inv = jinv_by_order( $order->get_id(), 'issued' );
	if ( $inv && 'issued' === $inv['status'] ) {
		$r = jinv_pay_full( (int) $inv['id'], array( 'date' => current_time( 'Y-m-d' ), 'method' => 'cash', 'source' => 'cod', 'ref' => 'packeta:' . $order->get_meta( '_jdp_packet_id' ), 'note' => 'Dobierka – Packeta doručila zásielku' ) );
		if ( ! is_wp_error( $r ) ) {
			$order->add_order_note( sprintf( 'J-Fakturácia: faktúra č. %s uhradená dobierkou (Packeta doručila zásielku).', $inv['number'] ) );
		}
	}
}, 20, 2 );

// Zrušená / vrátená objednávka s platnou faktúrou: dobropis (pri zapnutom automatickom dobropise), inak upozornenie.
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || ! in_array( $to, array( 'cancelled', 'refunded' ), true ) || jinv_woo_faktura_active() ) {
		return;
	}
	$inv = jinv_by_order( $order_id, 'issued' );
	if ( ! $inv || 'cancelled' === $inv['status'] ) {
		return;
	}
	if ( 'refunded' === $to && jinv_auto_on( 'auto_creditnote' ) ) {
		return; // vrátenie peňazí rieši dobropis z vrátenia (woocommerce_order_refunded)
	}
	$rest = jinv_credit_remaining( $order_id, $inv );
	if ( $rest < 0.01 ) {
		return;
	}
	if ( 'cancelled' === $to && jinv_auto_on( 'auto_creditnote' ) ) {
		$r = jinv_create_doc( $order, 'creditnote', array( 'note' => 'Objednávka zrušená.' ) );
		if ( ! is_wp_error( $r ) ) {
			return;
		}
	}
	$order->update_meta_data( '_jinv_needs_credit', current_time( 'mysql' ) );
	$order->save_meta_data();
	$order->add_order_note( sprintf( 'J-Fakturácia: POZOR – objednávka je %s, ale faktúra č. %s ostáva platná. Vystavte dobropis (box Doklady).', 'cancelled' === $to ? 'zrušená' : 'vrátená', $inv['number'] ) );
}, 40, 4 );

// Vrátenie peňazí pred vystavením faktúry: po vystavení faktúry sa doplní dobropis (alebo upozornenie).
add_action( 'jinv_document_created', function ( $id, $kind, $order ) {
	if ( 'issued' !== $kind || ! $order instanceof WC_Order || ! jinv_uncredited_refund( $order ) ) {
		return;
	}
	if ( jinv_auto_on( 'auto_creditnote' ) ) {
		$r = jinv_create_doc( $order, 'creditnote', array( 'send' => false ) );
		if ( ! is_wp_error( $r ) ) {
			return;
		}
	}
	$order->update_meta_data( '_jinv_needs_credit', current_time( 'mysql' ) );
	$order->save_meta_data();
	$order->add_order_note( 'J-Fakturácia: objednávka má vrátenie peňazí bez dobropisu – vystavte dobropis (box Doklady).' );
}, 30, 3 );
