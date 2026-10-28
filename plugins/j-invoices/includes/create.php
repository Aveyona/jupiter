<?php
/** Vystavenie faktúry z objednávky WooCommerce: číslovanie, snímka údajov, automatika podľa stavu objednávky. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Číslo faktúry podľa formátu, napr. {RRRR}{nnn} → 2026001. */
function jinv_format_number( $year, $seq, $format = null ) {
	$format = $format ? $format : (string) jinv_opt( 'format' );
	$map    = array(
		'{RRRR}' => sprintf( '%04d', $year ),
		'{RR}'   => substr( sprintf( '%04d', $year ), 2 ),
		'{nnnnn}' => sprintf( '%05d', $seq ),
		'{nnnn}' => sprintf( '%04d', $seq ),
		'{nnn}'  => sprintf( '%03d', $seq ),
	);
	return strtr( $format, $map );
}

/**
 * Prvá neprázdna hodnota z viacerých polí objednávky.
 * IČO/DIČ/IČ DPH ukladajú rôzne doplnky pod rôznymi kľúčmi (napr. Woo Faktura: billing_company_wi_id / _wi_tax / _wi_vat).
 */
function jinv_order_meta_any( WC_Order $o, array $keys ) {
	$keys = apply_filters( 'jinv_order_meta_keys', $keys, $o );
	foreach ( $keys as $k ) {
		$v = trim( (string) $o->get_meta( $k ) );
		if ( '' !== $v ) {
			return $v;
		}
	}
	return '';
}

/** Snímka odberateľa z objednávky. */
function jinv_customer_from_order( WC_Order $o ) {
	$name = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
	return array(
		'name'    => $name,
		'company' => $o->get_billing_company(),
		'street'  => trim( $o->get_billing_address_1() . ' ' . $o->get_billing_address_2() ),
		'zip'     => $o->get_billing_postcode(),
		'city'    => $o->get_billing_city(),
		'country' => WC()->countries && isset( WC()->countries->countries[ $o->get_billing_country() ] ) ? WC()->countries->countries[ $o->get_billing_country() ] : $o->get_billing_country(),
		'ico'     => jinv_order_meta_any( $o, array( '_billing_ico', 'billing_ico', '_billing_company_wi_id', 'billing_company_wi_id' ) ),
		'dic'     => jinv_order_meta_any( $o, array( '_billing_dic', 'billing_dic', '_billing_company_wi_tax', 'billing_company_wi_tax' ) ),
		'icdph'   => jinv_order_meta_any( $o, array( '_billing_icdph', 'billing_icdph', '_billing_company_wi_vat', 'billing_company_wi_vat' ) ),
		'email'   => $o->get_billing_email(),
		'phone'   => $o->get_billing_phone(),
	);
}

/** Položky faktúry z objednávky (tovar, doprava, poplatky). Ceny sú konečné (neplatiteľ DPH). */
function jinv_items_from_order( WC_Order $o ) {
	$items = array();
	foreach ( $o->get_items() as $it ) {
		$qty     = max( 1, (float) $it->get_quantity() );
		$total   = (float) $it->get_total() + (float) $it->get_total_tax();
		$product = $it->get_product();
		$items[] = array(
			'name'  => $it->get_name(),
			'sku'   => $product ? $product->get_sku() : '',
			'qty'   => $qty,
			'unit'  => 'ks',
			'price' => round( $total / $qty, 2 ),
			'total' => round( $total, 2 ),
		);
	}
	foreach ( $o->get_shipping_methods() as $sh ) {
		$total   = (float) $sh->get_total() + (float) $sh->get_total_tax();
		$items[] = array( 'name' => 'Doprava: ' . $sh->get_name(), 'sku' => '', 'qty' => 1, 'unit' => '', 'price' => round( $total, 2 ), 'total' => round( $total, 2 ) );
	}
	foreach ( $o->get_fees() as $fee ) {
		$total   = (float) $fee->get_total() + (float) $fee->get_total_tax();
		$items[] = array( 'name' => $fee->get_name(), 'sku' => '', 'qty' => 1, 'unit' => '', 'price' => round( $total, 2 ), 'total' => round( $total, 2 ) );
	}
	if ( (float) $o->get_discount_total() > 0 ) {
		// Zľava je už započítaná v položkách tovaru (get_total po zľave) – len informačný riadok.
		$items[] = array( 'name' => 'Uplatnená zľava (už v cenách položiek)', 'sku' => '', 'qty' => 0, 'unit' => '', 'price' => 0, 'total' => 0, 'info' => 1 );
	}
	return $items;
}

/**
 * Vystaví faktúru k objednávke (ak ešte nemá platnú). Vráti ID faktúry alebo WP_Error.
 *
 * @param int|WC_Order $order
 */
function jinv_create_from_order( $order ) {
	global $wpdb;
	$o = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( ! $o ) {
		return new WP_Error( 'jinv_no_order', 'Objednávka neexistuje.' );
	}
	$existing = jinv_by_order( $o->get_id() );
	if ( $existing ) {
		return (int) $existing['id'];
	}
	$s     = jinv_settings();
	$today = current_time( 'Y-m-d' );
	$year  = (int) current_time( 'Y' );
	$paid  = $o->is_paid() || $o->has_status( 'completed' );
	$supply = $o->get_date_completed() ? $o->get_date_completed()->date_i18n( 'Y-m-d' ) : ( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'Y-m-d' ) : $today );
	$data  = array(
		'supplier' => array_intersect_key( $s, array_flip( array( 'company', 'street', 'zip', 'city', 'country', 'ico', 'dic', 'icdph', 'register', 'vat_payer', 'vat_note', 'iban', 'swift', 'bank', 'email', 'phone', 'web', 'issued_by', 'footer' ) ) ),
		'customer' => jinv_customer_from_order( $o ),
		'items'    => jinv_items_from_order( $o ),
		'payment'  => $o->get_payment_method_title(),
		'pay_code' => $o->get_payment_method(),
		'order_no' => $o->get_order_number(),
		'note'     => '',
	);
	$total = round( (float) $o->get_total(), 2 );
	for ( $try = 0; $try < 5; $try++ ) {
		$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jinv_table() . " WHERE kind = 'issued' AND year = %d", $year ) ); // phpcs:ignore
		$num = jinv_format_number( $year, $seq, $s['format'] );
		$ok  = $wpdb->insert( jinv_table(), array(
			'kind'           => 'issued',
			'number'         => $num,
			'year'           => $year,
			'seq'            => $seq,
			'order_id'       => $o->get_id(),
			'status'         => $paid ? 'paid' : 'issued',
			'issue_date'     => $today,
			'supply_date'    => $supply,
			'due_date'       => gmdate( 'Y-m-d', strtotime( $today . ' +' . (int) $s['due_days'] . ' days' ) ),
			'vs'             => preg_replace( '/\D/', '', $num ),
			'currency'       => $o->get_currency(),
			'total'          => $total,
			'customer_name'  => $data['customer']['company'] ? $data['customer']['company'] : $data['customer']['name'],
			'customer_email' => $data['customer']['email'],
			'data'           => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'paid_at'        => $paid ? current_time( 'mysql' ) : null,
			'created_at'     => current_time( 'mysql' ),
		) );
		if ( $ok ) {
			$id = (int) $wpdb->insert_id;
			$o->update_meta_data( '_jinv_invoice', $num );
			$o->save();
			$o->add_order_note( sprintf( 'J-Fakturácia: vystavená faktúra č. %s.', $num ) );
			do_action( 'jinv_invoice_created', $id, $o );
			return $id;
		}
		// Kolízia čísla (súbežné vystavenie) – skúsiť ďalšie poradie.
	}
	return new WP_Error( 'jinv_insert', 'Faktúru sa nepodarilo uložiť: ' . $wpdb->last_error );
}

/**
 * Automatika: prevod na účet → keď je objednávka zaplatená (Spracováva sa s dátumom platby);
 * dobierka, osobný odber a ostatné → keď je objednávka vybavená.
 */
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( ! jinv_opt( 'auto' ) || ! $order instanceof WC_Order ) {
		return;
	}
	$cod_like = in_array( $order->get_payment_method(), array( 'cod', 'cheque' ), true );
	if ( 'completed' === $to || ( 'processing' === $to && ! $cod_like && $order->is_paid() ) ) {
		jinv_create_from_order( $order );
	}
}, 20, 4 );

// Ak už faktúra existuje a objednávka sa neskôr zaplatí/vybaví → označiť faktúru ako uhradenú.
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
	$inv = jinv_by_order( $order_id );
	if ( $inv && 'issued' === $inv['status'] ) {
		jinv_update( $inv['id'], array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ) );
	}
}, 30 );

function jinv_statuses() {
	return array(
		'issued'    => 'Vystavená',
		'paid'      => 'Uhradená',
		'overdue'   => 'Po splatnosti',
		'cancelled' => 'Stornovaná',
	);
}

/** Stav na zobrazenie (vystavená po dátume splatnosti = po splatnosti). */
function jinv_display_status( array $inv ) {
	if ( 'issued' === $inv['status'] && $inv['due_date'] < current_time( 'Y-m-d' ) ) {
		return 'overdue';
	}
	return $inv['status'];
}
