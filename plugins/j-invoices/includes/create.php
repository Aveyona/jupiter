<?php
/** Údaje pre doklady z objednávky WooCommerce: odberateľ, položky, dobierka. Vystavenie a automatika sú v documents.php. */
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

/** Beží doplnok Woo Faktura? (režim prechodu – automatika J-Fakturácie je vtedy vypnutá) */
function jinv_woo_faktura_active() {
	return class_exists( 'WC_WI_Invoice_Manager' ) || function_exists( 'wi_invoicemanager_init' );
}

/** Je objednávka platená na dobierku? Spôsoby platby sa dajú doplniť filtrom jinv_cod_methods. */
function jinv_is_cod_order( WC_Order $o ) {
	$methods = apply_filters( 'jinv_cod_methods', array( 'cod', 'jetpack_custom_gateway' ) );
	return in_array( $o->get_payment_method(), (array) $methods, true );
}

/** Je poplatok v objednávke poplatkom za dobierku? (názov obsahuje „dobierk“, „dobírk“ alebo „cash on delivery“) */
function jinv_is_cod_fee_name( $name ) {
	return (bool) preg_match( '/dobierk|dobírk|cash on delivery|\bcod\b/iu', (string) $name );
}

/** Položky faktúry z objednávky (tovar, doprava, poplatky – aj poplatok za dobierku). Ceny sú konečné (neplatiteľ DPH). */
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
	$is_cod  = jinv_is_cod_order( $o );
	$has_cod = false;
	foreach ( $o->get_fees() as $fee ) {
		$total = (float) $fee->get_total() + (float) $fee->get_total_tax();
		$name  = $fee->get_name();
		if ( $is_cod && jinv_is_cod_fee_name( $name ) ) {
			$has_cod = true;
			$name    = (string) jinv_opt( 'cod_item_name' );
		}
		$items[] = array( 'name' => $name, 'sku' => '', 'qty' => 1, 'unit' => '', 'price' => round( $total, 2 ), 'total' => round( $total, 2 ) );
	}
	// Dobierka bez poplatku v objednávke (napr. ručne vytvorená objednávka): položka s nulovou cenou, aby bola na faktúre uvedená.
	if ( $is_cod && ! $has_cod && jinv_opt( 'cod_item' ) ) {
		$items[] = array( 'name' => (string) jinv_opt( 'cod_item_name' ), 'sku' => '', 'qty' => 1, 'unit' => '', 'price' => 0, 'total' => 0 );
	}
	if ( (float) $o->get_discount_total() > 0 ) {
		// Zľava je už započítaná v položkách tovaru (get_total po zľave) – len informačný riadok.
		$items[] = array( 'name' => 'Uplatnená zľava (už v cenách položiek)', 'sku' => '', 'qty' => 0, 'unit' => '', 'price' => 0, 'total' => 0, 'info' => 1 );
	}
	return $items;
}


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
	if ( 'issued' === $inv['status'] && 'creditnote' !== $inv['kind'] && $inv['due_date'] < current_time( 'Y-m-d' ) ) {
		return 'overdue';
	}
	return $inv['status'];
}
