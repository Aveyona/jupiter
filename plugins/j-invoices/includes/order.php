<?php
/** Box „Faktúra“ v detaile objednávky (klasické objednávky aj HPOS) a stĺpec v zozname objednávok. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $scr ) {
		add_meta_box( 'jinv-order-box', 'Faktúra (J-Fakturácia)', 'jinv_order_box', $scr, 'side', 'high' );
	}
} );

function jinv_order_box( $post_or_order ) {
	$o = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $o ) {
		return;
	}
	$inv  = jinv_by_order( $o->get_id() );
	$back = $o->get_edit_order_url();
	if ( $inv ) {
		echo '<p><strong>č. ' . esc_html( $inv['number'] ) . '</strong> ' . jinv_status_badge( $inv ) . '</p>'; // phpcs:ignore
		echo '<p>Vystavená ' . esc_html( jinv_date( $inv['issue_date'] ) ) . ', splatná ' . esc_html( jinv_date( $inv['due_date'] ) ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( jinv_admin_url( array( 'view' => $inv['id'] ) ) ) . '">Zobraziť faktúru</a></p>';
	} else {
		echo '<p>Faktúra zatiaľ nie je vystavená.</p>';
		echo '<p class="description">Automaticky: prevod na účet po zaplatení, dobierka a osobný odber po vybavení objednávky.</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( jinv_action_url( 'create', $o->get_id(), $back ) ) . '">Vystaviť faktúru teraz</a></p>';
	}
}

// Stĺpec „Faktúra“ v zozname objednávok.
$jinv_col = function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'order_status' === $k ) {
			$new['jinv'] = 'Faktúra';
		}
	}
	return $new;
};
add_filter( 'manage_edit-shop_order_columns', $jinv_col, 20 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', $jinv_col, 20 );
$jinv_cell = function ( $col, $order ) {
	if ( 'jinv' !== $col ) {
		return;
	}
	$o   = $order instanceof WC_Order ? $order : wc_get_order( $order );
	$num = $o ? (string) $o->get_meta( '_jinv_invoice' ) : '';
	echo $num ? esc_html( $num ) : '–';
};
add_action( 'manage_shop_order_posts_custom_column', $jinv_cell, 20, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', $jinv_cell, 20, 2 );
