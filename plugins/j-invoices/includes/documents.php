<?php
/**
 * Doklady k objednávkam: faktúra (issued), zálohová faktúra (proforma) a dobropis (creditnote).
 *
 * Číslovanie (Nastavenia → Doklady a číslovanie):
 *  - „order“  – číslo dokladu = číslo objednávky (napr. 220260244), ako pri doplnku Woo Faktura; druhý doklad toho
 *               istého druhu k objednávke (napr. ďalší dobropis) dostane príponu -2, -3 …
 *  - „series“ – vlastný ročný rad pre každý druh dokladu podľa formátu ({RRRR}{nnn} → 2026001, ZF{RRRR}{nnn}, D{RRRR}{nnn}).
 *
 * Automatika (kým beží Woo Faktura, je vypnutá – režim prechodu):
 *  - prevod na účet: zálohová faktúra hneď pri objednávke (stav Čaká na platbu), faktúra po zaplatení;
 *  - dobierka, osobný odber a ostatné: faktúra, keď je objednávka vybavená.
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
	$seq = $max + 1 + $try;
	while ( jinv_number_taken( $kind, $year, $seq, jinv_format_number( $year, $seq, jinv_kind_format( $kind ) ) ) ) {
		$seq++;
	}
	return array( jinv_format_number( $year, $seq, jinv_kind_format( $kind ) ), $seq );
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
			$items[] = array( 'name' => $it->get_name(), 'sku' => '', 'qty' => -1 * max( 1, $qty ), 'unit' => 'ks', 'price' => round( abs( $total ) / max( 1, $qty ), 2 ), 'total' => round( -1 * abs( $total ), 2 ) );
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
			$items[] = array( 'name' => 'Vrátenie platby' . ( $refund->get_reason() ? ' – ' . $refund->get_reason() : '' ), 'sku' => '', 'qty' => -1, 'unit' => '', 'price' => abs( $diff ), 'total' => $diff );
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
	$today   = current_time( 'Y-m-d' );
	$year    = (int) current_time( 'Y' );
	$paid    = $o->is_paid() || $o->has_status( 'completed' );
	$refund  = ! empty( $opts['refund_id'] ) ? wc_get_order( (int) $opts['refund_id'] ) : null;
	if ( 'creditnote' === $kind && ! $refund ) {
		$refund = jinv_uncredited_refund( $o );
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
		$supply = $today;
	} else {
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
			'vs'             => substr( preg_replace( '/\D/', '', $num ), 0, 10 ),
			'currency'       => $o->get_currency(),
			'total'          => $total,
			'customer_name'  => $data['customer']['company'] ? $data['customer']['company'] : $data['customer']['name'],
			'customer_email' => $data['customer']['email'],
			'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'paid_at'        => 'paid' === $status ? current_time( 'mysql' ) : null,
			'created_at'     => current_time( 'mysql' ),
		) );
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

/** Spätná kompatibilita: faktúra z objednávky. */
function jinv_create_from_order( $order ) {
	return jinv_create_doc( $order, 'issued' );
}

/** Automatika je zapnutá (nastavenie) a nebeží Woo Faktura (režim prechodu). */
function jinv_auto_on( $key = 'auto' ) {
	return jinv_opt( $key ) && ! jinv_woo_faktura_active();
}

add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return;
	}
	$transfer = jinv_is_transfer_order( $order );
	// Zálohová faktúra pri prevode: objednávka čaká na platbu.
	if ( $transfer && jinv_auto_on( 'auto_proforma' ) && 'on-hold' === $to && ! $order->is_paid() ) {
		jinv_create_doc( $order, 'proforma' );
	}
	if ( jinv_auto_on( 'auto' ) ) {
		$paid_now = in_array( $to, array( 'processing', 'completed' ), true ) && $order->is_paid();
		if ( ( $transfer && ( $paid_now || 'completed' === $to ) ) || ( ! $transfer && 'completed' === $to ) ) {
			jinv_create_doc( $order, 'issued' );
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
}, 20, 4 );

// Objednávka zaplatená alebo vybavená → faktúra aj zálohová faktúra uhradené.
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! $order instanceof WC_Order || ! ( 'completed' === $to || ( 'processing' === $to && $order->is_paid() ) ) ) {
		return;
	}
	foreach ( array( 'issued', 'proforma' ) as $kind ) {
		$d = jinv_by_order( $order_id, $kind );
		if ( $d && 'issued' === $d['status'] ) {
			jinv_update( $d['id'], array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
		}
	}
}, 30, 4 );

// Vrátenie peňazí vo WooCommerce → dobropis (ak je zapnuté a objednávka má faktúru).
add_action( 'woocommerce_order_refunded', function ( $order_id, $refund_id ) {
	if ( jinv_auto_on( 'auto_creditnote' ) && jinv_by_order( $order_id, 'issued' ) ) {
		jinv_create_doc( $order_id, 'creditnote', array( 'refund_id' => $refund_id ) );
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
		$n = 0;
		foreach ( (array) $ids as $id ) {
			$had = jinv_by_order( (int) $id, 'issued' );
			$r   = jinv_create_doc( (int) $id, 'issued' );
			if ( ! $had && ! is_wp_error( $r ) ) {
				$n++;
			}
		}
		return add_query_arg( 'jinv_bulk', $n, $redirect );
	}, 10, 3 );
}
add_action( 'admin_notices', function () {
	if ( isset( $_GET['jinv_bulk'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>J-Fakturácia: vystavené faktúry – ' . (int) $_GET['jinv_bulk'] . '.</p></div>'; // phpcs:ignore
	}
} );
