<?php
/**
 * Prepojenie s J-Fakturáciou (úhrada faktúry kartou) a J-Účtovníctvom (poplatok Stripe 568 / 261).
 * Úhradu účtuje J-Účtovníctvo z úhrady faktúry (karta: 261 / 311). Výplata zo Stripe na účet = 221 / 261 z banky.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Zapíše úhradu kartou k faktúre objednávky (raz). */
function jpl_record_invoice_payment( $order_id ) {
	if ( ! function_exists( 'jinv_by_order' ) || ! function_exists( 'jinv_insert_payment' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || 'jpl_stripe' !== $order->get_payment_method() || ! $order->get_meta( '_jpl_pi' ) || ! $order->is_paid() ) {
		return;
	}
	if ( 'test' === $order->get_meta( '_jpl_mode' ) ) {
		return; // testovacia platba nie je skutočná úhrada
	}
	$doc = jinv_by_order( $order->get_id(), 'issued' );
	if ( ! $doc || 'cancelled' === $doc['status'] || jinv_payments( $doc['id'] ) ) {
		return; // bez faktúry, alebo už má zapísané úhrady
	}
	// Táto platba už je zapísaná pri inom doklade objednávky (napr. stornovanej faktúre) – J-Fakturácia ju presunie, nezapisuje sa druhýkrát.
	global $wpdb;
	if ( function_exists( 'jinv_table' ) && $wpdb->get_var( $wpdb->prepare( 'SELECT p.id FROM ' . jinv_table( 'payments' ) . ' p JOIN ' . jinv_table() . " d ON d.id = p.doc_id WHERE p.source = 'stripe' AND p.ref = %s AND d.order_id = %d LIMIT 1", (string) $order->get_meta( '_jpl_pi' ), $order->get_id() ) ) ) { // phpcs:ignore
		return;
	}
	$date = $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'Y-m-d' ) : current_time( 'Y-m-d' );
	$a    = array( 'date' => $date, 'method' => 'card', 'source' => 'stripe', 'ref' => (string) $order->get_meta( '_jpl_pi' ), 'note' => trim( 'Stripe ' . $order->get_meta( '_jpl_card' ) ) );
	// Priamy zápis (faktúra už môže byť „uhradená“ bez záznamu – jinv_add_payment by doplnil aj „pôvodnú úhradu“).
	$id = jinv_insert_payment( $doc['id'], (float) $doc['total'], $a );
	jinv_sync_paid( $doc['id'], true );
	do_action( 'jinv_payment_added', $id, $doc['id'], (float) $doc['total'], $a );
}

add_action( 'jinv_document_created', function ( $doc_id, $kind, $order ) {
	if ( 'issued' === $kind && $order instanceof WC_Order ) {
		jpl_record_invoice_payment( $order->get_id() );
	}
}, 20, 3 );

add_action( 'jpl_paid', function ( $order_id ) {
	jpl_record_invoice_payment( $order_id );
	jpl_post_fee( $order_id );
}, 20 );

/** Poplatok Stripe do J-Účtovníctva: 568 / 261 (interný doklad, dátum platby). */
function jpl_post_fee( $order_id ) {
	if ( ! function_exists( 'juc_sync_source' ) || ! jpl_opt( 'post_fees' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || '' === (string) $order->get_meta( '_jpl_fee' ) || 'test' === $order->get_meta( '_jpl_mode' ) ) {
		return; // testovacie platby sa neúčtujú
	}
	$fee  = (float) $order->get_meta( '_jpl_fee' );
	$date = $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'Y-m-d' ) : current_time( 'Y-m-d' );
	$card = function_exists( 'juc_acc_opt' ) ? juc_acc_opt( 'acc_card', '261' ) : '261';
	$exp  = function_exists( 'juc_acc_opt' ) ? juc_acc_opt( 'acc_bank_fees', '568' ) : '568';
	$built = $fee >= 0.005 ? array(
		'head'  => array( 'series' => 'ID', 'number' => '', 'entry_date' => $date, 'descr' => 'Poplatok Stripe – objednávka č. ' . $order->get_order_number(), 'partner_name' => 'Stripe', 'vs' => (string) $order->get_order_number() ),
		'lines' => array( array( 'md' => $exp, 'd' => $card, 'amount' => round( $fee, 2 ), 'text' => 'Poplatok za platbu kartou – obj. ' . $order->get_order_number(), 'vs' => (string) $order->get_order_number() ) ),
	) : null;
	$r = juc_sync_source( 'jpl', 'fee', $order->get_id(), $built );
	if ( is_wp_error( $r ) ) {
		$order->add_order_note( 'J-Platby: poplatok Stripe sa nepodarilo zaúčtovať – ' . $r->get_error_message() );
	}
}

// Platby, ktorým chýba poplatok (Stripe ho ešte neuviedol) – doplnenie raz za hodinu.
add_action( 'jpl_hourly', function () {
	$orders = wc_get_orders( array( 'limit' => 20, 'payment_method' => 'jpl_stripe', 'meta_query' => array( array( 'key' => '_jpl_pi', 'compare' => 'EXISTS' ), array( 'key' => '_jpl_fee', 'compare' => 'NOT EXISTS' ) ), 'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ) ) );
	foreach ( $orders as $o ) {
		if ( jpl_fill_charge( $o ) ) {
			$o->save();
			jpl_post_fee( $o->get_id() );
		}
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'jpl_hourly' ) ) {
		wp_schedule_event( time() + 900, 'hourly', 'jpl_hourly' );
	}
} );
