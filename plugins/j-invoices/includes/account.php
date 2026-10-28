<?php
/**
 * Môj účet: zákazník si stiahne doklady k svojej objednávke (faktúra, zálohová faktúra, dobropis – aj prevzaté
 * z Woo Faktura). Tlačidlá v zozname objednávok a zoznam dokladov v detaile objednávky.
 * Prístup: prihlásený majiteľ objednávky alebo platný kľúč objednávky (odkaz z e-mailu / stránky Ďakujeme).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Doklady v Môj účet: zapnuté v nastaveniach a nebeží Woo Faktura (režim prechodu – zákazníkom sa zatiaľ nič nemení). */
function jinv_account_docs_on() {
	return jinv_opt( 'account_docs' ) && ! jinv_woo_faktura_active();
}

/** Doklady objednávky, ktoré smie vidieť zákazník (bez stornovaných). */
function jinv_customer_docs( WC_Order $o ) {
	return array_values( array_filter( jinv_docs_for_order( $o->get_id() ), function ( $d ) {
		return 'cancelled' !== $d['status'] && ( 'woo-faktura' !== $d['source'] || ( $d['file'] && jinv_wf_file_path( $d['file'] ) ) );
	} ) );
}

function jinv_customer_doc_url( WC_Order $o, $doc_id ) {
	return add_query_arg( array( 'jinv_doc' => (int) $doc_id, 'key' => $o->get_order_key() ), home_url( '/' ) );
}

add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
	if ( ! jinv_account_docs_on() || ! $order instanceof WC_Order ) {
		return $actions;
	}
	foreach ( jinv_customer_docs( $order ) as $d ) {
		$actions[ 'jinv-' . $d['id'] ] = array(
			'url'  => jinv_customer_doc_url( $order, $d['id'] ),
			'name' => jinv_kind_label( $d['kind'] ) . ' (PDF)',
		);
	}
	return $actions;
}, 10, 2 );

add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
	if ( ! jinv_account_docs_on() || ! $order instanceof WC_Order ) {
		return;
	}
	$docs = jinv_customer_docs( $order );
	if ( ! $docs ) {
		return;
	}
	echo '<section class="jinv-account-docs"><h2>Doklady k objednávke</h2><ul>';
	foreach ( $docs as $d ) {
		echo '<li><a href="' . esc_url( jinv_customer_doc_url( $order, $d['id'] ) ) . '">' . esc_html( jinv_kind_label( $d['kind'] ) . ' č. ' . $d['number'] ) . '</a> (PDF, ' . esc_html( jinv_date( $d['issue_date'] ) ) . ')</li>';
	}
	echo '</ul></section>';
} );

// Stiahnutie dokladu zákazníkom.
add_action( 'template_redirect', function () {
	if ( empty( $_GET['jinv_doc'] ) ) { // phpcs:ignore
		return;
	}
	$inv = jinv_get( (int) $_GET['jinv_doc'] ); // phpcs:ignore
	$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
	$o   = $inv && $inv['order_id'] ? wc_get_order( $inv['order_id'] ) : null;
	$ok  = $o && 'cancelled' !== $inv['status'] && (
		( $key && hash_equals( (string) $o->get_order_key(), (string) $key ) )
		|| ( is_user_logged_in() && (int) $o->get_customer_id() === get_current_user_id() )
		|| current_user_can( 'manage_woocommerce' )
	);
	if ( ! $ok || ( ! jinv_account_docs_on() && ! current_user_can( 'manage_woocommerce' ) ) ) {
		wp_die( esc_html__( 'Doklad nie je dostupný.', 'j-invoices' ), '', array( 'response' => 403 ) );
	}
	jinv_pdf_send( $inv, false );
} );
